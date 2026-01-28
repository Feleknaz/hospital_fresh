<?php
// admin_sikayet_.php
// Geliştirilmiş, fesyonel şikayet / resim yönetimi paneli
// Gereksinimler: PDO ile $db (veritabanı bağlantısı) hazır olmalı, uploads dizini yazılabilir olmalı
// Güvenlik: CSRF token, dosya doğrulama, path traversal engelleme, AJAX ile kırpma işlemi

session_start();
require_once __DIR__ . "/../config/lang_loader.php"; // dil ayarları

// Basit admin kontrolü
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// CSRF token (basit)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helperlar
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function is_valid_filename($name) {
    // Sadece basename al, geçerli uzantı kontrolü
    $base = basename($name);
    return preg_match('/^[A-Za-z0-9._-]+$/', $base);
}

$uploads_dir = realpath(__DIR__ . '/../uploads');
if ($uploads_dir === false) {
    die('Uploads klasörü bulunamadı.');
}

// POST AJAX işlemleri (delete, crop)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Basit CSRF kontrolü (AJAX istekleri için token header veya field ile gönderilebilir)
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        if (isset($_POST['action']) && $_POST['action'] === 'crop') {
            json_response(['success' => false, 'message' => 'CSRF doğrulaması başarısız.'], 403);
        }
        die('CSRF doğrulaması başarısız.');
    }

    $action = $_POST['action'];

    if ($action === 'delete') {
        // Silme işlemi (form submit ile gelen)
        $del_id = (int) ($_POST['delete_id'] ?? 0);
        if (!$del_id) {
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // Resim yolunu al
        $stmt = $db->prepare('SELECT resim_yolu FROM sikayetler WHERE id = ?');
        $stmt->execute([$del_id]);
        $img = $stmt->fetchColumn();

        if ($img) {
            $safe = basename($img);
            $fullpath = $uploads_dir . DIRECTORY_SEPARATOR . $safe;
            if (is_file($fullpath)) {
                @unlink($fullpath);
            }
        }

        $stmt_del = $db->prepare('DELETE FROM sikayetler WHERE id = ?');
        $stmt_del->execute([$del_id]);

        // Basit redirect ile geri dön
        $_SESSION['flash_success'] = 'Şikayet silindi.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'crop') {
        // AJAX kırpma -> JSON ile cevap verecek
        // Beklenen alanlar: img_name, cropData (JSON: x,y,width,height,rotate,scaleX,scaleY)
        $img_name = $_POST['img_name'] ?? '';
        $crop_json = $_POST['cropData'] ?? '';

        if (!$img_name || !$crop_json) {
            json_response(['success' => false, 'message' => 'Eksik parametre.']);
        }

        if (!is_valid_filename($img_name)) {
            json_response(['success' => false, 'message' => 'Geçersiz dosya adı.']);
        }

        $safe = basename($img_name);
        $fullpath = $uploads_dir . DIRECTORY_SEPARATOR . $safe;

        if (!is_file($fullpath) || !is_readable($fullpath)) {
            json_response(['success' => false, 'message' => 'Dosya bulunamadı.']);
        }

        $cropData = json_decode($crop_json, true);
        if (!$cropData) {
            json_response(['success' => false, 'message' => 'Kırpma verisi okunamadı.']);
        }

        // Gerekli alanları ayıkla
        $x = isset($cropData['x']) ? (int)round($cropData['x']) : 0;
        $y = isset($cropData['y']) ? (int)round($cropData['y']) : 0;
        $w = isset($cropData['width']) ? (int)round($cropData['width']) : 0;
        $h = isset($cropData['height']) ? (int)round($cropData['height']) : 0;
        $rotate = isset($cropData['rotate']) ? (float)$cropData['rotate'] : 0.0;
        $scaleX = isset($cropData['scaleX']) ? (float)$cropData['scaleX'] : 1.0;
        $scaleY = isset($cropData['scaleY']) ? (float)$cropData['scaleY'] : 1.0;

        if ($w <= 0 || $h <= 0) {
            json_response(['success' => false, 'message' => 'Geçersiz kırpma boyutu.']);
        }

        // Görseli yükle (GD kullanarak güvenli şekilde)
        $info = getimagesize($fullpath);
        if ($info === false) {
            json_response(['success' => false, 'message' => 'Görsel bilgisi okunamadı.']);
        }

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg': $src = imagecreatefromjpeg($fullpath); break;
            case 'image/png': $src = imagecreatefrompng($fullpath); break;
            case 'image/gif': $src = imagecreatefromgif($fullpath); break;
            default:
                json_response(['success' => false, 'message' => 'Desteklenmeyen görsel formatı.']);
        }

        if (!$src) json_response(['success' => false, 'message' => 'Görsel yüklenemedi.']);

        $orig_w = imagesx($src);
        $orig_h = imagesy($src);

        // Cropper.js cropData koordinatları zaten resim üzerindeki piksel koordinatlarıdır.
        // Ancak rotate/scale uygulandıysa, sunucuda dönme ve flip uygulamamız gerekir.

        // 1) Rotate uygula (eğer 0 değilse)
        if ($rotate != 0.0) {
            // imagerotate() 90 derecelik katlarını daha iyi temsil eder, değilse -1 ile kullan
            $rotated = imagerotate($src, -$rotate, 0);
            if ($rotated) {
                imagedestroy($src);
                $src = $rotated;
                $orig_w = imagesx($src);
                $orig_h = imagesy($src);
            }
        }

        // 2) Flip (scaleX / scaleY)
        // scaleX = -1 -> yatay flip
        if ($scaleX === -1.0 || $scaleY === -1.0) {
            // Basit flip fonksiyonu
            $flipped = imagecreatetruecolor($orig_w, $orig_h);
            // preserve alpha for png/gif
            imagealphablending($flipped, false);
            imagesavealpha($flipped, true);

            for ($i = 0; $i < $orig_w; $i++) {
                for ($j = 0; $j < $orig_h; $j++) {
                    $sx = ($scaleX === -1.0) ? ($orig_w - $i - 1) : $i;
                    $sy = ($scaleY === -1.0) ? ($orig_h - $j - 1) : $j;
                    $color = imagecolorat($src, $sx, $sy);
                    imagesetpixel($flipped, $i, $j, $color);
                }
            }
            imagedestroy($src);
            $src = $flipped;
        }

        // 3) Güvenli kırpma koordinatları sınırlandırması
        $x = max(0, min($x, imagesx($src) - 1));
        $y = max(0, min($y, imagesy($src) - 1));
        $w = max(1, min($w, imagesx($src) - $x));
        $h = max(1, min($h, imagesy($src) - $y));

        // 4) Kırp
        $crop_img = imagecreatetruecolor($w, $h);
        // Transparan koruma
        imagealphablending($crop_img, false);
        imagesavealpha($crop_img, true);

        if (!imagecopy($crop_img, $src, 0, 0, $x, $y, $w, $h)) {
            imagedestroy($src);
            imagedestroy($crop_img);
            json_response(['success' => false, 'message' => 'Kırpma başarısız.']);
        }

        // 5) Üzerine kaydet (orijinal dosyayı değiştirme isterseniz, yedekleyin)
        $backup_path = $uploads_dir . DIRECTORY_SEPARATOR . 'bak_' . $safe;
        if (!file_exists($backup_path)) {
            // Orijinali yedekle (ilk seferde)
            copy($fullpath, $backup_path);
        }

        $saved = false;
        switch ($mime) {
            case 'image/jpeg': $saved = imagejpeg($crop_img, $fullpath, 90); break;
            case 'image/png': $saved = imagepng($crop_img, $fullpath); break;
            case 'image/gif': $saved = imagegif($crop_img, $fullpath); break;
        }

        imagedestroy($src);
        imagedestroy($crop_img);

        if (!$saved) {
            json_response(['success' => false, 'message' => 'Dosya kaydedilemedi.']);
        }

        json_response(['success' => true, 'message' => 'Resim başarıyla kırpıldı ve kaydedildi.']);
    }

    // Unknown action
    header('HTTP/1.1 400 Bad Request');
    exit;
}

// Eğer GET ise sayfa render
// Flash mesajları al
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

// Şikayetleri al
$stmt = $db->query("SELECT s.*, u.name AS user_name FROM sikayetler s JOIN users u ON s.user_id = u.id ORDER BY s.tarih DESC");
$sikayetler = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($dil_kodu ?? 'tr'); ?>" dir="<?php echo htmlspecialchars($dil_yonu ?? 'ltr'); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Şikayet Yönetimi - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
    <style>
        .img-preview { max-height: 180px; }
        .img-container { max-height: 540px; }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard">Admin Paneli</a>
        <div class="collapse navbar-collapse"><ul class="navbar-nav ms-auto"><li class="nav-item"><a class="nav-link" href="dashboard.php">Panele Dön</a></li></ul></div>
    </div>
</nav>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Şikayetler</h3>
        <small class="text-muted"><?php echo date('d.m.Y H:i'); ?></small>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($sikayetler as $s): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($s['user_name']); ?></strong>
                            <div class="small text-muted"><?php echo htmlspecialchars($s['tarih']); ?></div>
                        </div>
                        <div>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="delete_id" value="<?php echo (int)$s['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <button class="btn btn-sm btn-outline-danger" title="Sil" type="submit"><i class="bi bi-trash"></i> Sil</button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title text-primary"><?php echo htmlspecialchars($s['konu']); ?></h5>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($s['mesaj'])); ?></p>

                        <?php if (!empty($s['resim_yolu'])): ?>
                            <?php $safe = htmlspecialchars(basename($s['resim_yolu'])); ?>
                            <div class="text-center mt-3 p-2 border rounded bg-white">
                                <img src="../uploads/<?php echo $safe; ?>?t=<?php echo time(); ?>" class="img-fluid img-preview rounded mb-2" alt="image">
                                <div class="d-grid">
                                    <button class="btn btn-primary btn-sm btn-crop" 
                                            data-imgname="<?php echo $safe; ?>" 
                                            data-imgsrc="../uploads/<?php echo $safe; ?>?t=<?php echo time(); ?>" 
                                            data-bs-toggle="modal" data-bs-target="#cropModal">
                                        <i class="bi bi-crop"></i> Düzenle / Kırp
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Kırp Modal -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resmi Kırp ve Düzenle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="img-container text-center">
                            <img id="image-to-crop" src="" alt="to crop" style="max-width:100%; max-height:520px;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h6>Önizleme</h6>
                        <div class="preview rounded border" style="width:100%; height:220px; overflow:hidden"></div>

                        <div class="mt-3">
                            <button class="btn btn-secondary btn-sm me-1" id="rotateLeft">⟲</button>
                            <button class="btn btn-secondary btn-sm me-1" id="rotateRight">⟲</button>
                            <button class="btn btn-outline-secondary btn-sm me-1" id="flipH">Flip H</button>
                            <button class="btn btn-outline-secondary btn-sm" id="flipV">Flip V</button>
                        </div>

                        <hr>
                        <div id="cropInfo" class="small text-muted"></div>

                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="modal_img_name">
                <input type="hidden" id="modal_img_src">
                <input type="hidden" id="csrf_token" value="<?php echo $csrf_token; ?>">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-success" id="saveCrop">Kırp ve Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
    let cropper = null;
    const image = document.getElementById('image-to-crop');
    const preview = document.querySelector('.preview');
    const cropInfo = document.getElementById('cropInfo');

    // Modal açıldığında
    const cropModal = document.getElementById('cropModal');
    cropModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const src = button.getAttribute('data-imgsrc');
        const name = button.getAttribute('data-imgname');

        document.getElementById('modal_img_name').value = name;
        document.getElementById('modal_img_src').value = src;

        image.src = src;

        // Cropper başlatmak için resim yüklendiğinde
        image.onload = function () {
            if (cropper) { cropper.destroy(); cropper = null; }
            cropper = new Cropper(image, {
                viewMode: 1,
                autoCropArea: 0.8,
                responsive: true,
                preview: '.preview',
                ready() {
                    updateInfo();
                },
                crop() {
                    updateInfo();
                }
            });
        };
    });

    cropModal.addEventListener('hidden.bs.modal', function () {
        if (cropper) { cropper.destroy(); cropper = null; }
        image.src = '';
        preview.innerHTML = '';
        cropInfo.textContent = '';
    });

    // Kontroller
    document.getElementById('rotateLeft').addEventListener('click', function () { if(cropper) cropper.rotate(-90); });
    document.getElementById('rotateRight').addEventListener('click', function () { if(cropper) cropper.rotate(90); });
    document.getElementById('flipH').addEventListener('click', function () { if(cropper) cropper.scaleX(-cropper.getData().scaleX || -1); });
    document.getElementById('flipV').addEventListener('click', function () { if(cropper) cropper.scaleY(-cropper.getData().scaleY || -1); });

    function updateInfo() {
        if (!cropper) return;
        const d = cropper.getData(true); // integer
        cropInfo.textContent = `x:${Math.round(d.x)} y:${Math.round(d.y)} w:${Math.round(d.width)} h:${Math.round(d.height)} rotate:${d.rotate ?? 0}`;
    }

    // Kaydet
    document.getElementById('saveCrop').addEventListener('click', function () {
        if (!cropper) return alert('Cropper başlatılamadı.');
        const name = document.getElementById('modal_img_name').value;
        const csrf = document.getElementById('csrf_token').value;
        const data = cropper.getData(true);

        const formData = new FormData();
        formData.append('action', 'crop');
        formData.append('img_name', name);
        formData.append('cropData', JSON.stringify(data));
        formData.append('csrf_token', csrf);

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        }).then(r => r.json()).then(res => {
            if (res.success) {
                // Yenile veya küçük bildirim göster
                location.reload();
            } else {
                alert(res.message || 'Kırpma sırasında hata oluştu.');
            }
        }).catch(err => {
            console.error(err);
            alert('Sunucuya bağlanırken hata oluştu.');
        });
    });
</script>
</body>
</html>

<?php
require_once __DIR__ . "/config/lang_loader.php"; 

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];
$error = ''; $success = '';

if (isset($_POST['submit'])) {
    $konu = htmlspecialchars($_POST['konu']);
    $mesaj = htmlspecialchars($_POST['mesaj']);
    $resim_adi = NULL; // Resim yüklenmezse NULL kalacak

    // --- RESİM İŞLEME VE YÜKLEME (KRİTER 6 & 15) ---
    if (isset($_FILES['resim']) && $_FILES['resim']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['resim']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = $lang['dosya_hatasi'];
        } else {
            // Benzersiz isim oluştur
            $resim_adi = "sikayet_" . $user_id . "_" . time() . "." . $ext;
            $hedef_yol = "uploads/" . $resim_adi;
            
            // 1. Resmi geçici hafızaya al
            list($width, $height) = getimagesize($_FILES['resim']['tmp_name']);
            
            // 2. Yeni boyutları belirle (Genişlik en fazla 800px olsun, boy oranını koru)
            $max_width = 800;
            if ($width > $max_width) {
                $ratio = $max_width / $width;
                $new_width = $max_width;
                $new_height = $height * $ratio;
            } else {
                $new_width = $width;
                $new_height = $height;
            }

            // 3. Boş bir tuval yarat
            $thumb = imagecreatetruecolor($new_width, $new_height);

            // 4. Formatına göre resmi aç
            if ($ext == 'png') {
                $source = imagecreatefrompng($_FILES['resim']['tmp_name']);
                // PNG şeffaflığını koru
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
            } else {
                $source = imagecreatefromjpeg($_FILES['resim']['tmp_name']);
            }

            // 5. Resmi yeniden boyutlandırarak kopyala (Resize)
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

            // 6. İşlenmiş resmi kaydet
            if ($ext == 'png') {
                imagepng($thumb, $hedef_yol);
            } else {
                imagejpeg($thumb, $hedef_yol, 80); // Kalite %80
            }
            
            // Belleği temizle
            imagedestroy($thumb);
            imagedestroy($source);
        }
    }

    if (empty($error)) {
        $stmt = $db->prepare("INSERT INTO sikayetler (user_id, konu, mesaj, resim_yolu) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$user_id, $konu, $mesaj, $resim_adi])) {
            $success = $lang['sikayet_basarili'];
        } else {
            $error = "Veritabanı hatası.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8"><title><?php echo $lang['sikayet_kutusu']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($dil_yonu == 'rtl'): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="text-end mb-2"><a href="user/dashboard" class="btn btn-secondary"><?php echo $lang['panele_geri_don']; ?></a></div>
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0"><?php echo $lang['sikayet_kutusu']; ?></h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label><?php echo $lang['konu']; ?></label>
                            <select name="konu" class="form-select">
                                <option>Hastane Temizliği</option>
                                <option>Doktor/Personel Davranışı</option>
                                <option>Randevu Sistemi</option>
                                <option>Öneri</option>
                                <option>Diğer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label><?php echo $lang['mesajiniz']; ?></label>
                            <textarea name="mesaj" class="form-control" rows="5" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label><?php echo $lang['resim_yukle_opsiyonel']; ?></label>
                            <input type="file" name="resim" class="form-control" accept="image/*">
                            <small class="text-muted">JPG, PNG (Max 5MB, otomatik küçültülür)</small>
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary w-100"><?php echo $lang['gonder']; ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
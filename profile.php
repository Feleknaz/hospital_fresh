<?php
// PHP Sürümü (Dil Yükleyici dahil)
require_once __DIR__ . "/config/lang_loader.php"; 

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = $_SESSION['user_id'];
$error = ''; $success = '';

// Veritabanından mevcut bilgileri çek
$stmt = $db->prepare("SELECT name, email, password, profile_pic FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ==========================================================
// FORM 1: BİLGİ GÜNCELLEME
// ==========================================================
if (isset($_POST['update_info'])) {
    $new_name = trim($_POST['name']); $new_email = trim($_POST['email']);
    $stmt_check_email = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?"); $stmt_check_email->execute([$new_email, $user_id]);
    if ($stmt_check_email->fetch()) { $error = $lang['email_zaten_kayitli']; } 
    elseif (empty($new_name) || empty($new_email)) { $error = $lang['lutfen_tum_alanlari_doldurun']; } 
    else { try { $stmt_update = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?"); $stmt_update->execute([$new_name, $new_email, $user_id]); $_SESSION['user_name'] = $new_name; $user['name'] = $new_name; $user['email'] = $new_email; $success = $lang['guncelleme_basarili']; } catch (PDOException $e) { $error = "Hata: " . $e->getMessage(); } }
}

// ==========================================================
// FORM 2: ŞİFRE GÜNCELLEME
// ==========================================================
if (isset($_POST['update_password'])) {
    $current_pass = $_POST['current_password']; $new_pass = $_POST['new_password']; $confirm_pass = $_POST['confirm_password'];
    $uppercase = preg_match('@[A-Z]@', $new_pass); $lowercase = preg_match('@[a-z]@', $new_pass); $number = preg_match('@[0-9]@', $new_pass); $length = strlen($new_pass) >= 8;
    if (!password_verify($current_pass, $user['password'])) { $error = $lang['eski_sifre_yanlis']; } elseif ($new_pass != $confirm_pass) { $error = $lang['sifreler_uyusmuyor']; } elseif (!$uppercase || !$lowercase || !$number || !$length) { $error = $lang['sifre_guclu_degil']; }
    else { try { $hashed_password = password_hash($new_pass, PASSWORD_BCRYPT); $stmt_update = $db->prepare("UPDATE users SET password = ? WHERE id = ?"); $stmt_update->execute([$hashed_password, $user_id]); $success = $lang['guncelleme_basarili']; } catch (PDOException $e) { $error = "Hata: " . $e->getMessage(); } }
}

// ==========================================================
// FORM 3: RESİM YÜKLEME
// ==========================================================
if (isset($_POST['upload_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png']; $filesize = $_FILES['profile_image']['size']; $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) { $error = $lang['hata_uzanti']; } elseif ($filesize > 2 * 1024 * 1024) { $error = $lang['hata_boyut']; } 
        else {
            if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png') {
                $old_path = "uploads/" . $user['profile_pic'];
                if (file_exists($old_path)) { unlink($old_path); }
            }
            if (!is_dir('uploads')) { mkdir('uploads'); } $new_filename = "user_" . $user_id . "_" . time() . "." . $ext; $upload_path = "uploads/" . $new_filename;
            
            list($width, $height) = getimagesize($_FILES['profile_image']['tmp_name']); $new_w = 300; $new_h = 300; $thumb = imagecreatetruecolor($new_w, $new_h);
            if ($ext == 'png') { $source = imagecreatefrompng($_FILES['profile_image']['tmp_name']); imagealphablending($thumb, false); imagesavealpha($thumb, true); } else { $source = imagecreatefromjpeg($_FILES['profile_image']['tmp_name']); }
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_w, $new_h, $width, $height);
            
            if (imagejpeg($thumb, $upload_path, 90)) {
                $stmt_update = $db->prepare("UPDATE users SET profile_pic = ? WHERE id = ?"); $stmt_update->execute([$new_filename, $user_id]);
                $success = $lang['basarili_yukleme'];
                header("Refresh: 1; url=profile"); 
            } else { $error = $lang['hata_genel']; }
            imagedestroy($thumb); imagedestroy($source);
        }
    }
}

// ==========================================================
// FORM 4: RESİM SİLME
// ==========================================================
if (isset($_POST['delete_image'])) {
    if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png') {
        $old_path = "uploads/" . $user['profile_pic'];
        if (file_exists($old_path)) { unlink($old_path); }
        $stmt_delete = $db->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
        $stmt_delete->execute([$user_id]);
        $success = "Profil resmi silindi."; // Bu mesaj dil dosyasında yoksa da kalabilir
        header("Refresh: 1; url=profile");
    }
}

$has_custom_image = (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png' && file_exists("uploads/" . $user['profile_pic']));
$img_src = $has_custom_image ? "uploads/" . $user['profile_pic'] . "?t=" . time() : "https://ui-avatars.com/api/?name=" . urlencode($user['name']) . "&background=random&size=150";
?>

<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8"><title><?php echo $lang['profilim']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php if ($dil_yonu == 'rtl'): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"><?php endif; ?>
    <style>
        .profile-container { position: relative; display: inline-block; }
        .profile-overlay { 
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); border-radius: 50%; color: white; 
            display: flex; align-items: center; justify-content: center; 
            opacity: 0; transition: opacity 0.3s; cursor: pointer; font-size: 2rem;
        }
        .profile-container:hover .profile-overlay { opacity: 1; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo $lang['profilim']; ?></h4>
                    <a href="<?php echo ($_SESSION['role']=='admin'?'admin/dashboard':'user/dashboard'); ?>" class="btn btn-sm btn-light"><?php echo $lang['panele_geri_don']; ?></a>
                </div>
                <div class="card-body">
                    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                    <div class="row">
                        <div class="col-md-5 text-center border-end d-flex flex-column align-items-center justify-content-center">
                            <form method="POST" enctype="multipart/form-data" id="image-upload-form">
                                <div class="profile-container mb-3" onclick="document.getElementById('image-upload-input').click()">
                                    <img src="<?php echo $img_src; ?>" alt="Profil" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                                    <div class="profile-overlay"><i class="bi bi-camera"></i></div>
                                </div>
                                <input type="file" name="profile_image" id="image-upload-input" style="display: none;" accept="image/*">
                                <button type="submit" name="upload_image" id="auto-submit-btn" style="display:none;"></button>
                            </form>

                            <h6><?php echo htmlspecialchars($user['name']); ?></h6>
                            <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>

                            <?php if ($has_custom_image): ?>
                                <form method="POST" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                    <button type="submit" name="delete_image" class="btn btn-outline-danger btn-sm mt-2">
                                        <i class="bi bi-trash"></i> <?php echo $lang['resmi_kaldir'] ?? 'Resmi Sil'; ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn btn-primary btn-sm mt-2" onclick="document.getElementById('image-upload-input').click()">
                                    <i class="bi bi-upload"></i> <?php echo $lang['resim_yukle']; ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-7">
                            <h5 class="mb-3"><?php echo $lang['guncelle_bilgiler']; ?></h5>
                            <form method="POST">
                                <div class="mb-3"><label class="form-label"><?php echo $lang['ad_soyad']; ?></label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required></div>
                                <div class="mb-3"><label class="form-label"><?php echo $lang['email']; ?></label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
                                <button type="submit" name="update_info" class="btn btn-success w-100"><?php echo $lang['guncelle']; ?></button>
                            </form>
                            <hr class="my-4">
                            <h5 class="mb-3"><?php echo $lang['guncelle_sifre']; ?></h5>
                            <form method="POST">
                                <div class="mb-3"><label class="form-label"><?php echo $lang['mevcut_sifre']; ?></label><input type="password" name="current_password" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label"><?php echo $lang['yeni_sifre_gir']; ?></label><input type="password" name="new_password" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label"><?php echo $lang['yeni_sifre_dogrula']; ?></label><input type="password" name="confirm_password" class="form-control" required></div>
                                <button type="submit" name="update_password" class="btn btn-primary w-100"><?php echo $lang['guncelle']; ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('image-upload-input').addEventListener('change', function() {
    if (this.files.length > 0) { document.getElementById('auto-submit-btn').click(); }
});
</script>
</body>
</html>
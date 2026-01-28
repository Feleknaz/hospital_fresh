<?php
require_once __DIR__ . "/config/lang_loader.php";
if (file_exists(__DIR__ . "/config/mail_helper.php")) {
    require_once __DIR__ . "/config/mail_helper.php";
}

$error = '';
$success = '';

// !!! BURASI ÇOK ÖNEMLİ !!!
// Linkin telefonda açılması için senin IP adresini buraya sabitliyoruz.
$sabit_ip = "10.207.220.118"; 
$proje_klasoru = "/hospital_fresh"; // Klasör adın farklıysa burayı değiştir
// Linki manuel olarak oluşturuyoruz
$reset_link = "http://localhost" . $proje_klasoru . "/reset_password.php?token=" . $token . "&lang=" . $dil_kodu;

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);

    try {
        $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            
            // Zaman dilimi sorununu çözmek için SQL saati kullanıyoruz
            $stmt_update = $db->prepare("UPDATE users SET reset_token = ?, reset_token_expire = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
            $stmt_update->execute([$token, $email]);

            // --- LİNKİ ZORLA IP ADRESİ İLE OLUŞTUR ---
            // Artık localhost yazmayacak, direkt 192.168... yazacak
            $reset_link = "http://" . $sabit_ip . $proje_klasoru . "/reset_password.php?token=" . $token . "&lang=" . $dil_kodu;

            if (function_exists('gonderMail')) {
                $konu = "Şifre Sıfırlama";
                $mesaj = "Merhaba {$user['name']},<br><br>Şifrenizi değiştirmek için tıklayın:<br><a href='$reset_link'>$reset_link</a>";
                
                if (gonderMail($email, $user['name'], $konu, $mesaj)) {
                    $success = $lang['link_gonderildi'];
                } else {
                    $success = $lang['link_gonderildi'] . "<br><strong>Demo Link:</strong> <a href='$reset_link'>Tıkla</a>";
                }
            } else {
                $success = $lang['link_gonderildi'] . "<br><strong>Demo Link:</strong> <a href='$reset_link'>Tıkla</a>";
            }

        } else {
            $error = $lang['email_bulunamadi'];
        }
    } catch (PDOException $e) {
        $error = "Hata: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['sifremi_unuttum']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($dil_yonu == 'rtl'): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header text-center">
                    <h4><?php echo $lang['sifremi_unuttum']; ?></h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label><?php echo $lang['email']; ?></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="submit" class="btn btn-warning">
                                <?php echo $lang['sifre_sifirlama_linki_gonder']; ?>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <a href="login.php"><?php echo $lang['giris_yap']; ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
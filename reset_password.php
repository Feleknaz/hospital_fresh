<?php
require_once __DIR__ . "/config/lang_loader.php"; 

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

// 1. ADIM: Token Kontrolü (Link geçerli mi?)
if (empty($token)) {
    die("Hatalı istek: Token bulunamadı.");
}

// Veritabanında bu token'a sahip ve süresi dolmamış kullanıcı var mı?
$stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expire > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Token veritabanında yoksa (yani daha önce kullanılıp silindiyse) veya süresi dolduysa:
    die('
    <div class="container mt-5">
        <div class="alert alert-danger text-center">
            <h4>Link Geçersiz veya Kullanılmış!</h4>
            <p>' . $lang['gecersiz_token'] . '</p>
            <p>Bu link daha önce kullanılmış olabilir veya süresi dolmuş olabilir.</p>
            <a href="login" class="btn btn-primary">' . $lang['giris_yap'] . '</a>
        </div>
    </div>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    ');
}

// 2. ADIM: Form Gönderildiyse (Yeni Şifre Belirleme)
if (isset($_POST['submit'])) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    // GÜÇLÜ ŞİFRE KONTROLÜ
    $uppercase = preg_match('@[A-Z]@', $password);
    $lowercase = preg_match('@[a-z]@', $password);
    $number    = preg_match('@[0-9]@', $password);
    $length    = strlen($password) >= 8;

    if (!$uppercase || !$lowercase || !$number || !$length) {
        $error = $lang['sifre_guclu_degil'];
    } elseif ($password != $password_confirm) {
        $error = $lang['sifreler_uyusmuyor'];
    } else {
        // Şifreyi Hashle
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // --- KRİTİK NOKTA: TOKEN SİLME İŞLEMİ ---
        // Şifreyi güncellerken, reset_token ve reset_token_expire alanlarını NULL yapıyoruz.
        // Böylece bu link bir daha asla kullanılamaz.
        $stmt_update = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expire = NULL WHERE id = ?");
        
        if ($stmt_update->execute([$hashed_password, $user['id']])) {
            $success = $lang['sifre_basariyla_degisti'];
            // 3 saniye sonra giriş sayfasına at
            header("Refresh: 3; url=login.php");
        } else {
            $error = "Veritabanı hatası.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['sifre_sifirlama']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php if ($dil_yonu == 'rtl'): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"><?php endif; ?>
    
    <style>
        #password-requirements { font-size: 0.85rem; margin-top: 5px; display: none; }
        .valid { color: green; } .invalid { color: red; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header text-center"><h4><?php echo $lang['sifre_sifirlama']; ?></h4></div>
                <div class="card-body">
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                            <br>Yönlendiriliyorsunuz...
                        </div>
                    <?php else: ?>

                        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label><?php echo $lang['yeni_sifre']; ?></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye-slash" id="togglePasswordIcon"></i></button>
                                </div>
                                <div id="password-requirements" class="card p-2 bg-white border-0">
                                    <div id="req-length" class="invalid"><i class="bi bi-x-circle"></i> <?php echo $lang['kural_uzunluk']; ?></div>
                                    <div id="req-upper" class="invalid"><i class="bi bi-x-circle"></i> <?php echo $lang['kural_buyuk']; ?></div>
                                    <div id="req-lower" class="invalid"><i class="bi bi-x-circle"></i> <?php echo $lang['kural_kucuk']; ?></div>
                                    <div id="req-number" class="invalid"><i class="bi bi-x-circle"></i> <?php echo $lang['kural_rakam']; ?></div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label><?php echo $lang['yeni_sifre_tekrar']; ?></label>
                                <input type="password" name="password_confirm" class="form-control" required>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="submit" class="btn btn-primary"><?php echo $lang['sifre_guncelle']; ?></button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const icon = document.querySelector('#togglePasswordIcon');
    
    if(togglePassword) {
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            icon.classList.toggle('bi-eye');
            icon.classList.toggle('bi-eye-slash');
        });
    }

    const reqBox = document.getElementById('password-requirements');
    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqLower = document.getElementById('req-lower');
    const reqNumber = document.getElementById('req-number');

    password.addEventListener('focus', () => { reqBox.style.display = 'block'; });
    password.addEventListener('input', function() {
        const val = password.value;
        if (val.length >= 8) setValid(reqLength); else setInvalid(reqLength);
        if (/[A-Z]/.test(val)) setValid(reqUpper); else setInvalid(reqUpper);
        if (/[a-z]/.test(val)) setValid(reqLower); else setInvalid(reqLower);
        if (/[0-9]/.test(val)) setValid(reqNumber); else setInvalid(reqNumber);
    });

    function setValid(el) { el.classList.remove('invalid'); el.classList.add('valid'); el.querySelector('i').className = 'bi bi-check-circle'; }
    function setInvalid(el) { el.classList.remove('valid'); el.classList.add('invalid'); el.querySelector('i').className = 'bi bi-x-circle'; }
});
</script>
</body>
</html>
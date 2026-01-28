<?php
require_once __DIR__ . "/config/lang_loader.php"; 
$current_page = basename($_SERVER['PHP_SELF']); $error = ''; $success = '';

if (isset($_POST['submit'])) {
    $name = trim($_POST['name']); $email = trim($_POST['email']); $password = $_POST['password']; $password_confirm = $_POST['password_confirm'];
    $uppercase = preg_match('@[A-Z]@', $password); $lowercase = preg_match('@[a-z]@', $password); $number = preg_match('@[0-9]@', $password); $length = strlen($password) >= 8;

    if (empty($name) || empty($email) || empty($password)) { $error = $lang['lutfen_tum_alanlari_doldurun']; }
    elseif (!$uppercase || !$lowercase || !$number || !$length) { $error = $lang['sifre_guclu_degil']; }
    elseif ($password != $password_confirm) { $error = $lang['sifreler_uyusmuyor']; }
    else {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?"); $stmt->execute([$email]);
            if ($stmt->fetch()) { $error = $lang['email_kullanimda']; }
            else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt_insert = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'user')");
                if ($stmt_insert->execute([$name, $email, $hashed_password])) { $success = $lang['kayit_basarili']; header("Refresh: 2; url=login.php"); }
                else { $error = "Hata oluştu."; }
            }
        } catch (PDOException $e) { $error = $e->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8"><title><?php echo $lang['kayit_ol']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php if ($dil_yonu == 'rtl'): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"><?php endif; ?>
    <style> #password-requirements { font-size: 0.85rem; margin-top: 5px; display: none; } .valid { color: green; } .invalid { color: red; } </style>
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-5">
            <div class="text-center mb-3">
                <a href="?lang=tr" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu=='tr') echo 'active'; ?>">TR</a>
                <a href="?lang=en" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu=='en') echo 'active'; ?>">EN</a>
                <a href="?lang=de" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu=='de') echo 'active'; ?>">DE</a>
                <a href="?lang=ar" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu=='ar') echo 'active'; ?>">AR</a>
            </div>
            <div class="card">
                <div class="card-body">
                    <h2 class="text-center mb-4"><?php echo $lang['kayit_ol']; ?></h2>
                    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="mb-3"><label><?php echo $lang['ad_soyad']; ?></label><input type="text" name="name" class="form-control" required></div>
                        <div class="mb-3"><label><?php echo $lang['email']; ?></label><input type="email" name="email" class="form-control" required></div>
                        <div class="mb-3"><label><?php echo $lang['sifre']; ?></label>
                            <div class="input-group"><input type="password" name="password" id="password" class="form-control" required><button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye-slash"></i></button></div>
                            <div id="password-requirements" class="card p-2 bg-white border-0">
                                <div id="req-length" class="invalid"><i class="bi bi-x-circle"></i> <?php echo $lang['kural_uzunluk']; ?></div>
                                <div id="req-upper" class="invalid"><i class="bi bi-x-circle"></i> <?php echo $lang['kural_buyuk']; ?></div>
                                <div id="req-lower" class="invalid"><i class="bi bi-x-circle"></i> <?php echo $lang['kural_kucuk']; ?></div>
                                <div id="req-number" class="invalid"><i class="bi bi-x-circle"></i> <?php echo $lang['kural_rakam']; ?></div>
                            </div>
                        </div>
                        <div class="mb-3"><label><?php echo $lang['sifre_tekrar']; ?></label><div class="input-group"><input type="password" name="password_confirm" id="password_confirm" class="form-control" required><button class="btn btn-outline-secondary" type="button" id="togglePasswordConfirm"><i class="bi bi-eye-slash"></i></button></div></div>
                        <div class="d-grid"><button type="submit" name="submit" class="btn btn-primary"><?php echo $lang['kayit_ol']; ?></button></div>
                    </form>
                </div>
                <div class="card-footer text-center"><?php echo $lang['hesabin_var_mi']; ?> <a href="login"><?php echo $lang['giris_yap']; ?></a></div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Sadece Göz İkonu ve Şifre Kontrolü Kaldı
    const togglePassword = document.querySelector('#togglePassword'); const password = document.querySelector('#password');
    if(togglePassword) togglePassword.addEventListener('click', () => { const type = password.getAttribute('type')==='password'?'text':'password'; password.setAttribute('type',type); });
    
    const reqBox = document.getElementById('password-requirements'); const reqLength = document.getElementById('req-length'); const reqUpper = document.getElementById('req-upper'); const reqLower = document.getElementById('req-lower'); const reqNumber = document.getElementById('req-number');
    password.addEventListener('focus', () => { reqBox.style.display = 'block'; });
    password.addEventListener('input', () => {
        const val = password.value;
        val.length>=8 ? setValid(reqLength):setInvalid(reqLength); /[A-Z]/.test(val) ? setValid(reqUpper):setInvalid(reqUpper); /[a-z]/.test(val) ? setValid(reqLower):setInvalid(reqLower); /[0-9]/.test(val) ? setValid(reqNumber):setInvalid(reqNumber);
    });
    function setValid(el) { el.className='valid'; el.querySelector('i').className='bi bi-check-circle'; }
    function setInvalid(el) { el.className='invalid'; el.querySelector('i').className='bi bi-x-circle'; }
});
</script>
</body>
</html>
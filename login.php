<?php
require_once __DIR__ . "/config/lang_loader.php"; 
$current_page = basename($_SERVER['PHP_SELF']);
$error = '';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] == 'admin' ? 'admin/dashboard' : 'user/dashboard'));
    exit;
}

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id']; 
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            if (isset($_POST['remember'])) { setcookie('user_login', $user['id'], time() + (86400 * 30), "/"); }
            
            header("Location: " . ($user['role'] == 'admin' ? 'admin/dashboard' : 'user/dashboard'));
            exit;
        } else { $error = $lang['gecersiz_bilgi']; }
    } catch (PDOException $e) { $error = "Hata: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8"><title><?php echo $lang['giris_yap']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php if ($dil_yonu == 'rtl'): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"><?php endif; ?>
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
                    <h2 class="text-center mb-4"><?php echo $lang['giris_yap']; ?></h2>
                    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                    <form method="POST">
                        <div class="mb-3"><label><?php echo $lang['email']; ?></label><input type="email" name="email" class="form-control" required></div>
                        <div class="mb-3"><label><?php echo $lang['sifre']; ?></label>
                            <div class="input-group"><input type="password" name="password" id="password" class="form-control" required><button class="btn btn-outline-secondary" type="button" id="togglePassword"><i class="bi bi-eye-slash"></i></button></div>
                        </div>
                        <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="remember" name="remember"><label class="form-check-label" for="remember"><?php echo $lang['beni_hatirla']; ?></label></div>
                        <div class="mb-3 text-end"><a href="forgot_password.php" class="text-decoration-none small"><?php echo $lang['sifremi_unuttum']; ?>?</a></div>
                        <div class="d-grid"><button type="submit" name="submit" class="btn btn-primary"><?php echo $lang['giris_yap']; ?></button></div>
                    </form>
                </div>
                <div class="card-footer text-center"><?php echo $lang['hesabin_yok_mu']; ?> <a href="register"><?php echo $lang['kayit_ol']; ?></a></div>
            </div>
        </div>
    </div>
</div>
<script>
const togglePassword = document.querySelector('#togglePassword'); const password = document.querySelector('#password'); const icon = togglePassword.querySelector('i');
if (togglePassword) { togglePassword.addEventListener('click', () => { const type = password.getAttribute('type') === 'password' ? 'text' : 'password'; password.setAttribute('type', type); icon.classList.toggle('bi-eye'); icon.classList.toggle('bi-eye-slash'); }); }
</script>
</body>
</html>
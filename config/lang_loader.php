<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$izin_verilen_diller = ['tr', 'en', 'de', 'ar'];
if (isset($_GET['lang'])) {
    $secilen_dil = $_GET['lang'];
    if (in_array($secilen_dil, $izin_verilen_diller)) {
        $_SESSION['lang'] = $secilen_dil;
    }
}
$dil_kodu = $_SESSION['lang'] ?? 'tr';
$dil_dosyasi = __DIR__ . '/../lang/' . $dil_kodu . '.php';

if (file_exists($dil_dosyasi)) {
    require_once $dil_dosyasi;
} else {
    require_once __DIR__ . '/../lang/tr.php';
}
$dil_yonu = $lang['yon'] ?? 'ltr';

require_once __DIR__ . '/db.php';

// --- OTOMATİK GİRİŞ (COOKIE) ---
if (!isset($_SESSION['user_id']) && isset($_COOKIE['user_login'])) {
    $cookie_user_id = $_COOKIE['user_login'];
    try {
        $stmt_cookie = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt_cookie->execute([$cookie_user_id]);
        $user_cookie = $stmt_cookie->fetch(PDO::FETCH_ASSOC);

        if ($user_cookie) {
            $_SESSION['user_id'] = $user_cookie['id'];
            $_SESSION['user_name'] = $user_cookie['name'];
            $_SESSION['role'] = $user_cookie['role'];
        }
    } catch (Exception $e) {}
}
?>
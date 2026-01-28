<?php
require_once __DIR__ . "/../config/lang_loader.php"; 
$current_page = basename($_SERVER['PHP_SELF']);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { header("Location: ../login.php"); exit; }

$admin_name = $_SESSION['user_name'] ?? 'Admin';
$all_appointments = []; $error = '';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action']; $appointment_id = $_GET['id'];
    $new_status = ($action === 'confirm') ? 'confirmed' : (($action === 'cancel') ? 'cancelled' : '');
    if (!empty($new_status)) {
        try { $stmt_update = $db->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?"); $stmt_update->execute([$new_status, $appointment_id]); header("Location: dashboard.php"); exit; } catch (PDOException $e) { $error = $e->getMessage(); }
    }
}

$success = $_SESSION['admin_success_message'] ?? ''; unset($_SESSION['admin_success_message']);
$error = $_SESSION['admin_error_message'] ?? $error; unset($_SESSION['admin_error_message']);

try {
    $stmt = $db->prepare("SELECT a.appointment_id, a.appointment_date, a.appointment_time, h.hastane_adi, b.bolum_adi, d.doktor_adi, u.name as user_name, a.status FROM appointments a JOIN hastaneler h ON a.hastane_id = h.hastane_id JOIN bolumler b ON a.bolum_id = b.bolum_id JOIN doktorlar d ON a.doktor_id = d.doktor_id JOIN users u ON a.user_id = u.id ORDER BY a.appointment_date DESC, a.appointment_time DESC");
    $stmt->execute(); $all_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8"><title><?php echo $lang['admin_paneli']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($dil_yonu == 'rtl'): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"><?php endif; ?>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard"><?php echo $lang['admin_paneli']; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="dashboard"><?php echo $lang['randevulari_goruntule']; ?></a></li>
                <li class="nav-item"><a class="nav-link" href="anket_sonuclari"><?php echo $lang['anket_sonuclari']; ?></a></li>
                
                <li class="nav-item"><a class="nav-link" href="sikayetler"><?php echo $lang['sikayetler']; ?></a></li>
                
                <li class="nav-item"><a class="nav-link" href="../profile"><?php echo $lang['profilim']; ?></a></li>
                <li class="nav-item"><a class="nav-link text-danger" href="../logout.php"><?php echo $lang['cikis_yap']; ?></a></li>
                
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5">
    <div class="text-end mb-3"><a href="?lang=tr" class="btn btn-sm btn-outline-secondary">TR</a> <a href="?lang=en" class="btn btn-sm btn-outline-secondary">EN</a> <a href="?lang=ar" class="btn btn-sm btn-outline-secondary">AR</a></div>
    <div class="d-flex justify-content-between align-items-center mb-3"><h2><?php echo $lang['tum_kullanici_randevulari']; ?></h2><span class="text-muted"><?php echo $lang['hos_geldin']; ?>, <?php echo htmlspecialchars($admin_name); ?></span></div>
    <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover"><thead class="table-dark"><tr><th><?php echo $lang['hasta_adi']; ?></th><th><?php echo $lang['tarih']; ?></th><th><?php echo $lang['saat']; ?></th><th><?php echo $lang['hastane']; ?></th><th><?php echo $lang['durum']; ?></th><th><?php echo $lang['islemler']; ?></th></tr></thead><tbody>
        <?php if (empty($all_appointments)): ?><tr><td colspan="8"><div class="alert alert-info"><?php echo $lang['sistemde_randevu_yok']; ?></div></td></tr><?php else: ?>
        <?php foreach ($all_appointments as $app): ?>
            <tr>
                <td><?php echo htmlspecialchars($app['user_name']); ?></td>
                <td><?php echo $app['appointment_date']; ?></td>
                <td><?php echo $app['appointment_time']; ?></td>
                <td><?php echo htmlspecialchars($app['hastane_adi']); ?></td>
                <td>
                    <?php if ($app['status'] == 'pending'): ?><span class="badge bg-warning text-dark"><?php echo $lang['durum_admin_onayi_bekliyor']; ?></span>
                    <?php elseif ($app['status'] == 'pending_user_approval'): ?><span class="badge bg-info text-dark"><?php echo $lang['durum_hasta_onayi_bekleniyor']; ?></span>
                    <?php elseif ($app['status'] == 'confirmed'): ?><span class="badge bg-success"><?php echo $lang['durum_onaylandi']; ?></span>
                    <?php elseif ($app['status'] == 'cancelled'): ?><span class="badge bg-danger"><?php echo $lang['durum_iptal_edildi']; ?></span><?php endif; ?>
                </td>
                <td>
                    <?php if ($app['status'] == 'pending'): ?>
                        <a href="dashboard?action=confirm&id=<?php echo $app['appointment_id']; ?>" class="btn btn-success btn-sm"><?php echo $lang['onayla']; ?></a>
                        <a href="ertele?id=<?php echo $app['appointment_id']; ?>" class="btn btn-info btn-sm"><?php echo $lang['ertele']; ?></a>
                        <a href="dashboard?action=cancel&id=<?php echo $app['appointment_id']; ?>" class="btn btn-warning btn-sm"><?php echo $lang['iptal_et']; ?></a>
                    <?php elseif ($app['status'] == 'confirmed'): ?>
                        <a href="ertele?id=<?php echo $app['appointment_id']; ?>" class="btn btn-info btn-sm"><?php echo $lang['ertele']; ?></a>
                        <a href="dashboard?action=cancel&id=<?php echo $app['appointment_id']; ?>" class="btn btn-warning btn-sm"><?php echo $lang['iptal_et']; ?></a>
                    <?php elseif ($app['status'] == 'pending_user_approval'): ?>
                        <a href="dashboard?action=cancel&id=<?php echo $app['appointment_id']; ?>" class="btn btn-warning btn-sm"><?php echo $lang['iptal_et']; ?></a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody></table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
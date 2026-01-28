<?php
// 1. Adım: Dil Yükleyiciyi ve Veritabanını çağır (HATA DÜZELTİLDİ: .php eklendi)
require_once __DIR__ . "/../config/lang_loader.php"; 

// 2. Adım: Dil seçici linkleri için mevcut sayfanın URL'sini al
$current_page = basename($_SERVER['PHP_SELF']);

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php"); 
    exit;
}

$error = '';

if (!isset($_GET['id'])) {
    $_SESSION['admin_error_message'] = "Geçersiz randevu ID'si.";
    header("Location: dashboard.php");
    exit;
}
$appointment_id = $_GET['id'];

// Randevu bilgisi çekme
try {
    $stmt = $db->prepare("
        SELECT 
            a.appointment_date, a.appointment_time, 
            h.hastane_adi, b.bolum_adi, d.doktor_adi, u.name as user_name
        FROM appointments a
        JOIN hastaneler h ON a.hastane_id = h.hastane_id
        JOIN bolumler b ON a.bolum_id = b.bolum_id
        JOIN doktorlar d ON a.doktor_id = d.doktor_id
        JOIN users u ON a.user_id = u.id
        WHERE a.appointment_id = ?
    ");
    $stmt->execute([$appointment_id]); 
    $app = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$app) {
        $_SESSION['admin_error_message'] = "Randevu bulunamadı.";
        header("Location: dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    $error = "Randevu bilgileri çekilirken hata oluştu: " . $e->getMessage();
}

// Form gönderme
if (isset($_POST['submit'])) {
    $new_date = $_POST['appointment_date'] ?? null;
    $new_time = $_POST['appointment_time'] ?? null;

    if (empty($new_date) || empty($new_time)) {
        $error = $lang['lutfen_tum_alanlari_doldurun'];
    } else {
        try {
            $stmt_update = $db->prepare("UPDATE appointments 
                                       SET 
                                           appointment_date = ?, 
                                           appointment_time = ?, 
                                           status = 'pending_user_approval' 
                                       WHERE 
                                           appointment_id = ?");
            
            $stmt_update->execute([$new_date, $new_time, $appointment_id]);

            $_SESSION['admin_success_message'] = "Randevu ertelendi ve 'Hasta Onayı Bekliyor' olarak işaretlendi.";
            header("Location: dashboard.php");
            exit;

        } catch (PDOException $e) {
            $error = "Erteleme sırasında hata: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['randevu_ertele_admin']; ?> - <?php echo $lang['admin_paneli']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <?php if ($dil_yonu == 'rtl'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">

    <div class="text-end mb-3">
        <a href="<?php echo $current_page; ?>?lang=tr&id=<?php echo $appointment_id; ?>" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'tr') echo 'active'; ?>">TR</a>
        <a href="<?php echo $current_page; ?>?lang=en&id=<?php echo $appointment_id; ?>" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'en') echo 'active'; ?>">EN</a>
        <a href="<?php echo $current_page; ?>?lang=de&id=<?php echo $appointment_id; ?>" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'de') echo 'active'; ?>">DE</a>
        <a href="<?php echo $current_page; ?>?lang=ar&id=<?php echo $appointment_id; ?>" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'ar') echo 'active'; ?>">AR</a>
    </div>

    <h2><?php echo $lang['randevu_ertele_admin']; ?></h2>
    <p><?php echo $lang['yeni_tarih_sec']; ?></p>

    <a href="dashboard" class="btn btn-sm btn-outline-secondary mb-3"><?php echo $lang['panele_geri_don']; ?></a>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header"><?php echo $lang['mevcut_randevu_bilgileri']; ?></div>
        <div class="card-body">
            <p><strong><?php echo $lang['hasta_adi']; ?>:</strong> <?php echo htmlspecialchars($app['user_name']); ?></p>
            <p><strong><?php echo $lang['hastane']; ?>:</strong> <?php echo htmlspecialchars($app['hastane_adi']); ?></p>
            <p><strong><?php echo $lang['bolum']; ?>:</strong> <?php echo htmlspecialchars($app['bolum_adi']); ?></p>
            <p><strong><?php echo $lang['doktor']; ?>:</strong> <?php echo htmlspecialchars($app['doktor_adi']); ?></p>
            <p class="text-danger"><strong><?php echo $lang['eski_tarih_saat']; ?>:</strong> <?php echo date("d.m.Y", strtotime($app['appointment_date'])) . " - " . date("H:i", strtotime($app['appointment_time'])); ?></p>
        </div>
    </div>

    <form method="POST" action="ertele.php?id=<?php echo $appointment_id; ?>">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo $lang['yeni_randevu_tarihi']; ?></label>
                <input type="text" id="tarih-secici" name="appointment_date" class="form-control" placeholder="<?php echo $lang['seciniz']; ?>..." required>
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo $lang['yeni_randevu_saati']; ?></label>
                <select name="appointment_time" class="form-select" required>
                    <option value=""><?php echo $lang['saat']; ?> <?php echo $lang['seciniz']; ?></option>
                    <?php
                        $baslangic = 8 * 60; $bitis = 17 * 60;
                        $ogle_basi = 12 * 60; $ogle_sonu = 13 * 60; $aralik = 15;
                        for ($dakika = $baslangic; $dakika <= $bitis; $dakika += $aralik) {
                            if ($dakika >= $ogle_basi && $dakika < $ogle_sonu) continue; 
                            $saat_str = sprintf('%02d:%02d', floor($dakika / 60), $dakika % 60);
                            echo "<option value=\"$saat_str\">$saat_str</option>";
                        }
                    ?>
                </select>
            </div>
        </div>
        <button type="submit" name="submit" class="btn btn-primary mt-3"><?php echo $lang['randevuyu_ertele_ve_onaya_gonder']; ?></button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/<?php echo ($dil_kodu == 'ar' || $dil_kodu == 'tr' || $dil_kodu == 'de' || $dil_kodu == 'fr') ? $dil_kodu : 'tr'; ?>.js"></script>

<script>
// PHP'den dil kodunu al
const dilKodu = "<?php echo $dil_kodu; ?>";

document.addEventListener('DOMContentLoaded', () => {
    // Takvim ayarları
    const today = new Date().toISOString().split('T')[0];
    const resmiTatiller = [
        "2025-01-01", "2025-03-31", "2025-04-01", "2025-04-23", 
        "2025-05-01", "2025-05-19", "2025-06-05", "2025-06-06", 
        "2025-06-09", "2025-08-30", "2025-10-29",
    ];
    flatpickr("#tarih-secici", {
        "locale": dilKodu, // Dinamik dil kodu
        "minDate": today,
        "disable": [
            function(date) { return (date.getDay() === 0 || date.getDay() === 6); },
            function(date) { const dateString = date.toISOString().split('T')[0]; return resmiTatiller.includes(dateString); }
        ]
    });
});
</script>
</body>
</html>
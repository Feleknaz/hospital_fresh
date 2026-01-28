<?php
// 1. Adım: Dil Yükleyiciyi ve Veritabanını çağır
require_once __DIR__ . "/config/lang_loader.php"; 

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); 
    exit;
}

// 2. Adım: Dil seçici linkleri için mevcut sayfanın URL'sini al
$current_page = basename($_SERVER['PHP_SELF']);
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    // 3. Adım: Kullanıcı daha önce oy vermiş mi?
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM anket_cevaplari WHERE user_id = ?");
    $stmt_check->execute([$user_id]);
    $oy_vermis = $stmt_check->fetchColumn() > 0;

    if ($oy_vermis) {
        $error = $lang['anket_zaten_dolduruldu'];
    }

    // 4. Adım: GÜNCELLENDİ (Soruları dile göre çek)
    // Hangi dil sütununu seçeceğimizi belirle
    $soru_kolonu = "soru_metni"; // Varsayılan TR
    if ($dil_kodu != 'tr' && in_array($dil_kodu, ['en', 'de', 'ar'])) {
        $soru_kolonu = "soru_metni_" . $dil_kodu;
    }

    $stmt_sorular = $db->query("SELECT soru_id, $soru_kolonu AS soru_metni_goster 
                              FROM anket_sorulari 
                              WHERE soru_tipi = 'puanlama'");
    $sorular = $stmt_sorular->fetchAll(PDO::FETCH_ASSOC);

    // Form gönderildiyse
    if (isset($_POST['submit']) && !$oy_vermis) {
        
        $db->beginTransaction(); 
        $stmt_insert = $db->prepare("INSERT INTO anket_cevaplari (soru_id, user_id, cevap_puani) VALUES (?, ?, ?)");

        foreach ($sorular as $soru) {
            $soru_id = $soru['soru_id'];
            $cevap = $_POST['cevap_' . $soru_id] ?? null;

            if ($cevap === null) {
                throw new Exception($lang['anket_soru_bos']);
            }
            $stmt_insert->execute([$soru_id, $user_id, (int)$cevap]);
        }
        
        $db->commit(); 
        $success = $lang['anket_basarili'];
        $oy_vermis = true; 
    
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack(); 
    }
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['memnuniyet_anketi']; ?> - <?php echo $lang['baslik']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <?php if ($dil_yonu == 'rtl'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    <style>
        .puanlama-grubu label { margin-right: 15px; }
        /* RTL için özel düzeltme (Arapça'da puanlar sağa yaslanır) */
        html[dir="rtl"] .form-check-inline { margin-left: 1rem; margin-right: 0; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div classtext-end mb-3">
                <a href="<?php echo $current_page; ?>?lang=tr" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'tr') echo 'active'; ?>">TR</a>
                <a href="<?php echo $current_page; ?>?lang=en" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'en') echo 'active'; ?>">EN</a>
                <a href="<?php echo $current_page; ?>?lang=de" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'de') echo 'active'; ?>">DE</a>
                <a href="<?php echo $current_page; ?>?lang=ar" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'ar') echo 'active'; ?>">AR</a>
            </div>
            <h2><?php echo $lang['memnuniyet_anketi']; ?></h2>
            <p><?php echo $lang['anket_aciklama']; ?></p>
            
            <a href="user/dashboard" class="btn btn-sm btn-outline-secondary mb-3"><?php echo $lang['panele_geri_don']; ?></a>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (empty($error) && !$oy_vermis): ?>
                <form method="POST" action="anket.php">
                    <?php foreach ($sorular as $soru): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($soru['soru_metni_goster']); ?></h5>
                                <div class="puanlama-grubu">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" 
                                                   name="cevap_<?php echo $soru['soru_id']; ?>" 
                                                   id="soru_<?php echo $soru['soru_id']; ?>_puan_<?php echo $i; ?>" 
                                                   value="<?php echo $i; ?>" required>
                                            <label class="form-check-label" 
                                                   for="soru_<?php echo $soru['soru_id']; ?>_puan_<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </label>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" name="submit" class="btn btn-primary mt-3"><?php echo $lang['anketi_gonder']; ?></button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
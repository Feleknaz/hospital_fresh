<?php
// 1. Dil Yükleyiciyi ve Veritabanını çağır
require_once __DIR__ . '/../config/lang_loader.php'; 

// 2. Dil seçici linkleri için
$current_page = basename($_SERVER['PHP_SELF']);

// Admin kontrolü
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php"); 
    exit;
}

$admin_name = $_SESSION['user_name'] ?? 'Admin';
$anket_verileri = [];
$error = '';

try {
    // 3. GÜNCELLENDİ: Soruları dile göre çek
    $soru_kolonu = "soru_metni"; // Varsayılan TR
    if ($dil_kodu != 'tr' && in_array($dil_kodu, ['en', 'de', 'fr', 'ar'])) {
        $soru_kolonu = "soru_metni_" . $dil_kodu;
    }
    
    $stmt_sorular = $db->query("SELECT soru_id, $soru_kolonu AS soru_metni_goster FROM anket_sorulari WHERE soru_tipi = 'puanlama'");
    $sorular = $stmt_sorular->fetchAll(PDO::FETCH_ASSOC);

    // Her soru için cevapları topla (Bu sorgu aynı kaldı)
    $stmt_cevaplar = $db->prepare("
        SELECT cevap_puani, COUNT(*) as oy_sayisi
        FROM anket_cevaplari
        WHERE soru_id = ?
        GROUP BY cevap_puani
        ORDER BY cevap_puani ASC
    ");

    foreach ($sorular as $soru) {
        $soru_id = $soru['soru_id'];
        $stmt_cevaplar->execute([$soru_id]);
        $cevaplar = $stmt_cevaplar->fetchAll(PDO::FETCH_ASSOC);
        
        $grafik_datasi = [0, 0, 0, 0, 0];
        foreach ($cevaplar as $cevap) {
            $puan_indexi = (int)$cevap['cevap_puani'] - 1; 
            $grafik_datasi[$puan_indexi] = (int)$cevap['oy_sayisi'];
        }
        
        $anket_verileri[] = [
            'soru_metni' => $soru['soru_metni_goster'], // Dile göre gelen metni kullan
            'soru_id' => $soru_id,
            'grafik_datasi' => $grafik_datasi 
        ];
    }

} catch (PDOException $e) {
    $error = "Anket verileri çekilirken hata oluştu: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['anket_sonuclari']; ?> - <?php echo $lang['admin_paneli']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <?php if ($dil_yonu == 'rtl'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard"><?php echo $lang['admin_paneli']; ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard"><?php echo $lang['randevulari_goruntule']; ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="anket_sonuclari"><?php echo $lang['anket_sonuclari']; ?></a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="../logout.php"><?php echo $lang['cikis_yap']; ?></a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-5">
    
    <div class="text-end mb-3">
        <a href="<?php echo $current_page; ?>?lang=tr" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'tr') echo 'active'; ?>">TR</a>
        <a href="<?php echo $current_page; ?>?lang=en" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'en') echo 'active'; ?>">EN</a>
        <a href="<?php echo $current_page; ?>?lang=de" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'de') echo 'active'; ?>">DE</a>
        <a href="<?php echo $current_page; ?>?lang=fr" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'fr') echo 'active'; ?>">FR</a>
        <a href="<?php echo $current_page; ?>?lang=ar" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'ar') echo 'active'; ?>">AR</a>
    </div>

    <h2><?php echo $lang['anket_sonuclari']; ?> (Grafiksel)</h2>
    <p><?php echo $lang['anket_aciklama']; ?></p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (empty($anket_verileri) && empty($error)): ?>
        <div class="alert alert-info">Henüz anket dolduran kimse yok.</div>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($anket_verileri as $veri): ?>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($veri['soru_metni']); ?></h5>
                        <canvas id="chart_soru_<?php echo $veri['soru_id']; ?>"></canvas>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const anketVerileri = <?php echo json_encode($anket_verileri); ?>;

    anketVerileri.forEach(veri => {
        const ctx = document.getElementById('chart_soru_' + veri.soru_id).getContext('2d');
        
        new Chart(ctx, {
            type: 'bar', 
            data: {
                labels: ['1 Puan', '2 Puan', '3 Puan', '4 Puan', '5 Puan'],
                datasets: [{
                    label: 'Alınan Oy Sayısı',
                    data: veri.grafik_datasi, 
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.2)', 'rgba(255, 159, 64, 0.2)',
                        'rgba(255, 205, 86, 0.2)', 'rgba(75, 192, 192, 0.2)',
                        'rgba(54, 162, 235, 0.2)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)', 'rgba(255, 159, 64, 1)',
                        'rgba(255, 205, 86, 1)', 'rgba(75, 192, 192, 1)',
                        'rgba(54, 162, 235, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
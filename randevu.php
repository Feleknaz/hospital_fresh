<?php
require_once __DIR__ . "/config/lang_loader.php"; 

if (!isset($_SESSION['user_id'])) { 
    header("Location: login"); 
    exit; 
}

$current_page = basename($_SERVER['PHP_SELF']);
$error = '';

// Form gönderildiyse
if (isset($_POST['submit']) && isset($db)) {
    
    $user_id = $_SESSION['user_id'];
    // Gerekli tüm ID'leri alıyoruz
    $il_id = $_POST['city'] ?? null;
    $ilce_id = $_POST['district'] ?? null;
    $hastane_id = $_POST['hospital'] ?? null;
    $bolum_id = $_POST['department'] ?? null;
    $doktor_id = $_POST['doctor_id'] ?? null; // Düzgün değişken adı
    $appointment_date = $_POST['appointment_date'] ?? null;
    $appointment_time = $_POST['appointment_time'] ?? null;

    if (empty($hastane_id) || empty($bolum_id) || empty($doktor_id) || empty($il_id) || empty($ilce_id) || empty($appointment_date) || empty($appointment_time)) {
        $error = $lang['lutfen_tum_alanlari_doldurun'];
    } else {
        try {
            // ÇAKIŞMA KONTROLÜ (HATA DÜZELTİLDİ: doktor_id sütunu kullanılıyor)
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM appointments 
                                        WHERE doktor_id = :doktor_id 
                                        AND appointment_date = :date 
                                        AND appointment_time = :time 
                                        AND status != 'cancelled'");
            
            $stmt_check->bindParam(':doktor_id', $doktor_id);
            $stmt_check->bindParam(':date', $appointment_date);
            $stmt_check->bindParam(':time', $appointment_time);
            $stmt_check->execute();
            $is_booked = $stmt_check->fetchColumn();

            if ($is_booked > 0) {
                $error = $lang['randevu_dolu_uyari'];
            } else {
                // RANDEVU KAYIT İŞLEMİ
                $stmt = $db->prepare("INSERT INTO appointments 
                    (user_id, il_id, ilce_id, hastane_id, bolum_id, doktor_id, appointment_date, appointment_time, status) 
                    VALUES 
                    (:user_id, :il_id, :ilce_id, :hastane_id, :bolum_id, :doktor_id, :appointment_date, :appointment_time, 'pending')");
                
                $stmt->bindParam(":user_id", $user_id);
                $stmt->bindParam(":il_id", $il_id);
                $stmt->bindParam(":ilce_id", $ilce_id);
                $stmt->bindParam(":hastane_id", $hastane_id);
                $stmt->bindParam(":bolum_id", $bolum_id);
                $stmt->bindParam(":doktor_id", $doktor_id); // Düzeltildi
                $stmt->bindParam(":appointment_date", $appointment_date);
                $stmt->bindParam(":appointment_time", $appointment_time);

                if ($stmt->execute()) {
                    $_SESSION['appointment_success'] = "Randevunuz başarıyla alındı ve admin onayına gönderildi.";
                    header("Location: user/dashboard");
                    exit();
                } else {
                    $error = "Randevu kaydı sırasında bir hata oluştu.";
                }
            }
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['randevu_al']; ?> - <?php echo $lang['baslik']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <?php if ($dil_yonu == 'rtl'): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php endif; ?>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">

    <div class="text-end mb-3">
        <a href="?lang=tr" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'tr') echo 'active'; ?>">TR</a>
        <a href="?lang=en" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'en') echo 'active'; ?>">EN</a>
        <a href="?lang=de" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'de') echo 'active'; ?>">DE</a>
        <a href="?lang=ar" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'ar') echo 'active'; ?>">AR</a>
    </div>

    <h2><?php echo $lang['randevu_al']; ?></h2>

    <?php if (!empty($error)) { echo "<div class='alert alert-danger'>$error</div>"; } ?>

    <a href="user/dashboard" class="btn btn-sm btn-outline-secondary mb-3"><?php echo $lang['panele_geri_don']; ?></a>

    <form method="POST" action="randevu.php">
        
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo $lang['sehir']; ?></label>
                <select id="city" name="city" class="form-select" required>
                    <option value=""><?php echo $lang['seciniz']; ?></option>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo $lang['ilce']; ?></label>
                <select id="district" name="district" class="form-select" required disabled>
                    <option value=""><?php echo $lang['sehir']; ?> <?php echo $lang['seciniz']; ?></option>
                </select>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo $lang['hastane']; ?></label>
            <select id="hospital" name="hospital" class="form-select" required disabled>
                <option value=""><?php echo $lang['ilce']; ?> <?php echo $lang['seciniz']; ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo $lang['bolum']; ?></label>
            <select id="department" name="department" class="form-select" required disabled>
                <option value=""><?php echo $lang['hastane']; ?> <?php echo $lang['seciniz']; ?></option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo $lang['doktor']; ?></label>
            <select id="doctor_id" name="doctor_id" class="form-select" required disabled>
                <option value=""><?php echo $lang['bolum']; ?> <?php echo $lang['seciniz']; ?></option>
            </select>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo $lang['randevu_tarihi']; ?></label>
                <input type="text" id="tarih-secici" name="appointment_date" class="form-control" placeholder="GG.AA.YYYY" required>
            </div>
            
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo $lang['randevu_saati']; ?></label>
                <select name="appointment_time" class="form-select" required>
                    <option value=""><?php echo $lang['saat']; ?> <?php echo $lang['seciniz']; ?></option>
                    <?php
                        $baslangic = 8 * 60; $bitis = 17 * 60; $ogle_basi = 12 * 60; $ogle_sonu = 13 * 60; $aralik = 15;
                        for ($dakika = $baslangic; $dakika <= $bitis; $dakika += $aralik) {
                            if ($dakika >= $ogle_basi && $dakika < $ogle_sonu) continue; 
                            $saat_str = sprintf('%02d:%02d', floor($dakika / 60), $dakika % 60);
                            echo "<option value=\"$saat_str\">$saat_str</option>";
                        }
                    ?>
                </select>
            </div>
        </div>

        <button type="submit" name="submit" class="btn btn-primary mt-3"><?php echo $lang['randevu_al']; ?></button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/<?php echo ($dil_kodu == 'ar' || $dil_kodu == 'tr' || $dil_kodu == 'de' || $dil_kodu == 'fr') ? $dil_kodu : 'tr'; ?>.js"></script>

<script>
// Dil değişkenlerini JS'e taşı
const lang = {
    seciniz: "<?php echo $lang['seciniz']; ?>",
    sehirSeciniz: "<?php echo $lang['sehir']; ?> <?php echo $lang['seciniz']; ?>",
    ilceSeciniz: "<?php echo $lang['ilce']; ?> <?php echo $lang['seciniz']; ?>",
    hastaneSeciniz: "<?php echo $lang['hastane']; ?> <?php echo $lang['seciniz']; ?>",
    bolumSeciniz: "<?php echo $lang['bolum']; ?> <?php echo $lang['seciniz']; ?>",
    doktorSeciniz: "<?php echo $lang['doktor']; ?> <?php echo $lang['seciniz']; ?>",
    yukleniyor: "Yükleniyor...", 
    veriYuklenemedi: "Veri yüklenemedi"
};
const dilKodu = "<?php echo $dil_kodu; ?>";

document.addEventListener('DOMContentLoaded', () => {
    
    const citySelect = document.getElementById('city');
    const districtSelect = document.getElementById('district');
    const hospitalSelect = document.getElementById('hospital');
    const departmentSelect = document.getElementById('department');
    const doctorSelect = document.getElementById('doctor_id');

    async function fetchData(url, selectElement, defaultOptionText) {
        selectElement.innerHTML = `<option value="">${lang.yukleniyor}</option>`;
        selectElement.disabled = true;
        try {
            const response = await fetch(url);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();
            if (data.error) throw new Error(data.error);
            
            selectElement.innerHTML = `<option value="">${defaultOptionText}</option>`;
            data.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id || item.name;
                opt.textContent = item.name;
                selectElement.appendChild(opt);
            });
            selectElement.disabled = false;
        } catch (error) {
            console.error('Fetch Hatası:', error);
            selectElement.innerHTML = `<option value="">${lang.veriYuklenemedi}</option>`;
            selectElement.disabled = true;
        }
    }

    // 1. Şehirleri yükle
    fetchData('get_data.php?type=cities', citySelect, lang.sehirSeciniz);

    // 2. Şehir değişince ilçeleri getir
    citySelect.addEventListener('change', () => {
        const cityId = citySelect.value;
        districtSelect.innerHTML = `<option value="">${lang.sehirSeciniz}</option>`;
        districtSelect.disabled = true;
        hospitalSelect.innerHTML = `<option value="">${lang.ilceSeciniz}</option>`;
        hospitalSelect.disabled = true;
        departmentSelect.innerHTML = `<option value="">${lang.hastaneSeciniz}</option>`;
        departmentSelect.disabled = true;
        doctorSelect.innerHTML = `<option value="">${lang.bolumSeciniz}</option>`;
        doctorSelect.disabled = true;
        if (cityId) {
            fetchData('get_data.php?type=districts&id='+cityId, districtSelect, lang.ilceSeciniz);
        }
    });

    // 3. İlçe değişince hastaneleri getir
    districtSelect.addEventListener('change', () => {
        const districtId = districtSelect.value;
        hospitalSelect.innerHTML = `<option value="">${lang.ilceSeciniz}</option>`;
        hospitalSelect.disabled = true;
        departmentSelect.innerHTML = `<option value="">${lang.hastaneSeciniz}</option>`;
        departmentSelect.disabled = true;
        doctorSelect.innerHTML = `<option value="">${lang.bolumSeciniz}</option>`;
        doctorSelect.disabled = true;
        if (districtId) {
            fetchData('get_data.php?type=hospitals&id='+districtId, hospitalSelect, lang.hastaneSeciniz);
        }
    });

    // 4. Hastane değişince bölümleri getir
    hospitalSelect.addEventListener('change', () => {
        const hospitalId = hospitalSelect.value;
        departmentSelect.innerHTML = `<option value="">${lang.hastaneSeciniz}</option>`;
        departmentSelect.disabled = true;
        doctorSelect.innerHTML = `<option value="">${lang.bolumSeciniz}</option>`;
        doctorSelect.disabled = true;
        if (hospitalId) {
            fetchData('get_data.php?type=departments&id='+hospitalId, departmentSelect, lang.bolumSeciniz);
        }
    });

    // 5. Bölüm değişince doktorları getir
    departmentSelect.addEventListener('change', () => {
        const hospitalId = hospitalSelect.value;
        const departmentId = departmentSelect.value;
        doctorSelect.innerHTML = `<option value="">${lang.bolumSeciniz}</option>`;
        doctorSelect.disabled = true;
        if (hospitalId && departmentId) {
            fetchData('get_data.php?type=doctors&id='+hospitalId+'&id2='+departmentId, doctorSelect, lang.doktorSeciniz);
        }
    });

    // Flatpickr Takvim Ayarları
    const today = new Date().toISOString().split('T')[0];
    const resmiTatiller = ["2025-01-01", "2025-03-31", "2025-04-01", "2025-04-23", "2025-05-01", "2025-05-19", "2025-06-05", "2025-06-06", "2025-06-09", "2025-08-30", "2025-10-29"];
    flatpickr("#tarih-secici", {
        "locale": dilKodu,
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
<?php
// Veritabanı bağlantı ayarları
$sunucu = "localhost";
$veritabani_adi = "hospital_fresh";
$kullanici_adi = "root";
$sifre = "";

try {
    // 1. Veritabanı Bağlantısını Kur
    $db = new PDO("mysql:host=$sunucu;dbname=$veritabani_adi;charset=utf8", $kullanici_adi, $sifre);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // -----------------------------------------------------------
    // YENİ EKLENDİ: OTOMATİK ONAYLAMA (CRON JOB)
    // -----------------------------------------------------------
    // Sitenin herhangi bir sayfası yüklendiğinde bu kod çalışır
    
    // Sadece 'pending_user_approval' (Hastanın onayını bekleyen) VE
    // 1 günden (24 saat) daha eski olan randevuları bul
    $sql_auto_confirm = "UPDATE appointments 
                         SET status = 'confirmed' 
                         WHERE status = 'pending_user_approval' 
                         AND updated_at < (NOW() - INTERVAL 1 DAY)";
                         
    // Bu sorguyu çalıştır
    $db->query($sql_auto_confirm);
    
    // -----------------------------------------------------------
    // OTOMATİK ONAYLAMA BİTTİ
    // -----------------------------------------------------------

} catch (PDOException $e) {
    // Bağlantı hatası varsa programı durdur
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}
?>
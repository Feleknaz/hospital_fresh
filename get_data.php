<?php
// Bu dosya, tüm dinamik veri çekme işlemleri için API görevi görür.
require_once __DIR__ . "/config/db.php";

if (!isset($db) || $db === null) {
    echo json_encode(['error' => 'Veritabanı bağlantısı kurulamadı.']);
    exit;
}

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;
$id2 = $_GET['id2'] ?? 0; // Bu artık bolum_id için kullanılacak

try {
    $response = [];

    // --- Bu kısım aynı kaldı ---
    if ($type === 'cities') {
        $stmt = $db->query("SELECT il_id as id, il_adi as name FROM iller ORDER BY il_adi");
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    
    else if ($type === 'districts' && !empty($id)) {
        $stmt = $db->prepare("SELECT ilce_id as id, ilce_adi as name FROM ilceler WHERE il_id = ? ORDER BY ilce_adi");
        $stmt->execute([$id]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    
    else if ($type === 'hospitals' && !empty($id)) {
        $stmt = $db->prepare("SELECT hastane_id as id, hastane_adi as name FROM hastaneler WHERE ilce_id = ? ORDER BY hastane_adi");
        $stmt->execute([$id]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========= BURASI DEĞİŞTİ (YENİ BÖLÜM MANTIĞI) =========
    // Yeni 'bolumler' ve 'doktorlar' tablolarını kullanır
    else if ($type === 'departments' && !empty($id)) {
        // $id = hastane_id oluyor
        // Sadece o hastanede (doktorlar tablosuna göre) bulunan bölümleri getir
        $stmt = $db->prepare("SELECT DISTINCT b.bolum_id as id, b.bolum_adi as name 
                             FROM doktorlar d
                             JOIN bolumler b ON d.bolum_id = b.bolum_id
                             WHERE d.hastane_id = ?
                             ORDER BY b.bolum_adi");
        $stmt->execute([$id]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========= BURASI DEĞİŞTİ (YENİ DOKTOR MANTIĞI) =========
    // Yeni 'doktorlar' tablosunu ve 'bolum_id'yi kullanır
    else if ($type === 'doctors' && !empty($id) && !empty($id2)) {
        // $id = hastane_id
        // $id2 = bolum_id oluyor
        $stmt = $db->prepare("SELECT doktor_id as id, doktor_adi as name 
                             FROM doktorlar 
                             WHERE hastane_id = ? AND bolum_id = ? 
                             ORDER BY doktor_adi");
        $stmt->execute([$id, $id2]);
        $response = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- Bu kısım aynı kaldı ---
    echo json_encode($response);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
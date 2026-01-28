<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);

$cacheFile = __DIR__ . '/weather_cache.json';
$cacheTime = 600; // 10 dakika

// 1️⃣ Cache varsa ve süresi dolmadıysa → ANINDA ver
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    exit;
}

// 2️⃣ Cache yoksa veya süre dolduysa → Yeni verileri ayarla
$data = [
    "name" => "Balikesir",
    "main" => [
        "temp" => 9  // Sıcaklık 9 derece yapıldı
    ],
    "weather" => [
        [
            "description" => "hafif yağışlı", // Açıklama değişti
            "icon" => "10d" // İkon yağmurlu (10d) yapıldı
        ]
    ]
];

// Cache'e yaz
file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));

// Gönder
echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>
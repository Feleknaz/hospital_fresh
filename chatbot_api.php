<?php
// chatbot_api.php - İNSANCIL SÜRÜM

$apiKey = "AIzaSyDDysT6hu6kQ-Qu42WYJhZE1qiKUXWyuKc"; 

// Hata gizleme
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!isset($input['message'])) {
    echo json_encode(["error" => "Mesaj boş olamaz."]);
    exit;
}

$userMessage = $input['message'];

// Ücretsiz ve çalışan model
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $apiKey;

// --- BURAYI DEĞİŞTİRDİM: DAHA DOĞAL KONUŞMASI İÇİN ---
$systemInstruction = "Sen yardımsever, nazik ve bilgili bir hastane karşılama asistanısın. 
Görevin: Kullanıcının şikayetini dinleyip en doğru hastane bölümüne (polikliniğe) yönlendirmek.

Kurallar:
1. Soğuk ve tek kelimelik cevaplar verme. Tam cümle kur.
2. Kullanıcıya 'Geçmiş olsun' veya 'Yardımcı olayım' gibi nazik ifadeler kullan.
3. Tıbbi teşhis koyma (doktor değilsin), sadece yönlendirme yap.
4. Cevabın 1-2 cümleyi geçmesin, kısa ve öz olsun.

Örnek İdeal Cevaplar:
- 'Baş ağrısı şikayetiniz için Nöroloji bölümünden randevu almanız uygun olacaktır. Geçmiş olsun.'
- 'Nefes darlığı ciddi olabilir, lütfen Göğüs Hastalıkları bölümüne görünün veya acil durumsa Acil Servis'e başvurun.'
";

$data = [
    "contents" => [
        [
            "parts" => [
                ["text" => $systemInstruction . "\n\nKullanıcı Şikayeti: " . $userMessage]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// Bağlantı Ayarları
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(["error" => "Bağlantı Sorunu: " . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$decoded = json_decode($response, true);

if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    $reply = trim($decoded['candidates'][0]['content']['parts'][0]['text']);
    // Temizlik (Markdown yıldızlarını temizle)
    $reply = str_replace(['*', '#'], "", $reply);
    echo json_encode(["reply" => $reply]);
} else {
    if (isset($decoded['error']['message'])) {
        echo json_encode(["error" => "Hata: " . $decoded['error']['message']]);
    } else {
        echo json_encode(["error" => "Cevap alınamadı."]);
    }
}
?>
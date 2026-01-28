<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer dosyalarını doğru yoldan çağırıyoruz (Senin klasör yapına göre ayarlandı)
require __DIR__ . '/../PHPMailer/Exception.php';
require __DIR__ . '/../PHPMailer/PHPMailer.php';
require __DIR__ . '/../PHPMailer/SMTP.php';

function gonderMail($alici_email, $alici_adi, $konu, $mesaj) {
    $mail = new PHPMailer(true);

    try {
        // --- SUNUCU AYARLARI ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        // !!! BURAYI DOLDURUN !!!
        $mail->Username   = 'feleknazyasar@gmail.com'; // Kendi Gmail adresini yaz
        $mail->Password   = 'hihl kzgi cotn vkvr';        // Gmail'den aldığın 16 haneli "Uygulama Şifresi"ni yaz
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // --- GÖNDEREN VE ALICI ---
        $mail->setFrom('feleknazyasar@gmail.com', 'Hastane Randevu Sistemi'); // Kendi mailini tekrar yaz
        $mail->addAddress($alici_email, $alici_adi);

        // --- İÇERİK ---
        $mail->isHTML(true);
        $mail->Subject = $konu;
        $mail->Body    = $mesaj;
        $mail->AltBody = strip_tags($mesaj);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Hata olursa false döndür (Hata mesajını görmek istersen: echo $mail->ErrorInfo;)
        return false;
    }
}
?>
<?php
session_start();
require_once __DIR__ . "/../config/lang_loader.php"; 
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id']; 
$user_name = $_SESSION['user_name'] ?? 'Kullanıcı'; 
$appointments = []; 

$success = $_SESSION['appointment_success'] ?? ''; unset($_SESSION['appointment_success']);
$error = $_SESSION['error_message'] ?? ''; unset($_SESSION['error_message']);

// İptal ve Onay İşlemleri
if (isset($_GET['cancel_id'])) {
    $stmt = $db->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND user_id = ? AND (status = 'pending' OR status = 'confirmed' OR status = 'pending_user_approval')");
    $stmt->execute([$_GET['cancel_id'], $user_id]);
    $_SESSION['appointment_success'] = "Randevunuz iptal edildi."; header("Location: dashboard.php"); exit;
}
if (isset($_GET['confirm_id'])) {
    $stmt = $db->prepare("UPDATE appointments SET status = 'confirmed' WHERE appointment_id = ? AND user_id = ? AND status = 'pending_user_approval'");
    $stmt->execute([$_GET['confirm_id'], $user_id]);
    $_SESSION['appointment_success'] = "Randevunuz onaylandı."; header("Location: dashboard.php"); exit;
}

try {
    $stmt = $db->prepare("SELECT a.*, h.hastane_adi, b.bolum_adi, d.doktor_adi FROM appointments a JOIN hastaneler h ON a.hastane_id=h.hastane_id JOIN bolumler b ON a.bolum_id=b.bolum_id JOIN doktorlar d ON a.doktor_id=d.doktor_id WHERE a.user_id=? ORDER BY a.appointment_date DESC");
    $stmt->execute([$user_id]); $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = "Hata: " . $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="<?php echo $dil_kodu; ?>" dir="<?php echo $dil_yonu; ?>">
<head>
    <meta charset="UTF-8"><title><?php echo $lang['kullanici_paneli']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php if ($dil_yonu == 'rtl'): ?><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css"><?php endif; ?>
    
    <style>
        .weather-card { background: linear-gradient(to right, #00c6ff, #0072ff); color: white; border: none; border-radius: 15px; transition: transform 0.3s; }
        .weather-card:hover { transform: scale(1.02); }
        .weather-temp { font-size: 2.2rem; font-weight: bold; }
        .weather-select { background: rgba(255,255,255,0.2); border: none; color: white; font-size: 0.85rem; padding: 2px 5px; border-radius: 5px; cursor: pointer; }
        .weather-select option { color: black; }
        
        /* Chatbot Ek Stiller */
        .chat-bubble { padding: 8px 12px; border-radius: 15px; font-size: 14px; max-width: 85%; margin-bottom: 8px; word-wrap: break-word;}
        .chat-user { align-self: flex-end; background-color: #0d6efd; color: white; border-bottom-right-radius: 2px; }
        .chat-bot { align-self: flex-start; background-color: #e9ecef; color: black; border-bottom-left-radius: 2px; }
        .chat-loading { align-self: flex-start; color: gray; font-style: italic; font-size: 12px; margin-left: 5px;}
    </style>
</head>
<body class="bg-light">
<div style="position: fixed; top: 10px; left: 10px; z-index: 9999; background: rgba(0,0,0,0.8); padding: 5px 10px; border-radius: 20px; display: flex; gap: 10px;">
    <button onclick="toggleDarkMode()" class="btn btn-sm btn-dark text-warning" title="Gece/Gündüz Modu"><i class="bi bi-moon-stars-fill"></i></button>
    <button onclick="changeFontSize(1)" class="btn btn-sm btn-light" title="Yazıyı Büyüt">A+</button>
    <button onclick="changeFontSize(-1)" class="btn btn-sm btn-light" title="Yazıyı Küçült">A-</button>
</div>

<script>
    // Gece Modu Fonksiyonu
    function toggleDarkMode() {
        document.body.classList.toggle('bg-dark');
        document.body.classList.toggle('text-white');
        // Tabloları da düzelt
        document.querySelectorAll('.table').forEach(t => t.classList.toggle('table-dark'));
        document.querySelectorAll('.card').forEach(c => {
            c.classList.toggle('bg-secondary');
            c.classList.toggle('text-white');
        });
    }

    // Font Büyütme Fonksiyonu
    let currentSize = 16;
    function changeFontSize(amount) {
        currentSize += amount;
        document.body.style.fontSize = currentSize + 'px';
    }
</script>
<div class="container mt-5">

    <div class="text-end mb-3">
        <a href="dashboard.php?lang=tr" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'tr') echo 'active'; ?>">TR</a>
        <a href="dashboard.php?lang=en" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'en') echo 'active'; ?>">EN</a>
        <a href="dashboard.php?lang=de" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'de') echo 'active'; ?>">DE</a>
        <a href="dashboard.php?lang=ar" class="btn btn-sm btn-outline-secondary <?php if($dil_kodu == 'ar') echo 'active'; ?>">AR</a>
    </div>

    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2><?php echo $lang['hos_geldin']; ?>, <?php echo htmlspecialchars($user_name); ?>!</h2>
            <p class="text-muted">Sağlıklı günler dileriz.</p>
        </div>
        
        <div class="col-md-4">
            <div class="card weather-card shadow-sm" id="weather-box" style="display: none;">
                <div class="card-body p-3 d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="card-title m-0 mb-1"><i class="bi bi-geo-alt-fill"></i> 
                            <select id="city-selector" class="weather-select">
                                <option value="Adana">Adana</option>
                                <option value="Adiyaman">Adıyaman</option>
                                <option value="Afyonkarahisar">Afyonkarahisar</option>
                                <option value="Agri">Ağrı</option>
                                <option value="Amasya">Amasya</option>
                                <option value="Ankara">Ankara</option>
                                <option value="Antalya">Antalya</option>
                                <option value="Artvin">Artvin</option>
                                <option value="Aydin">Aydın</option>
                                <option value="Balikesir">Balıkesir</option>
                                <option value="Bilecik">Bilecik</option>
                                <option value="Bingol">Bingöl</option>
                                <option value="Bitlis">Bitlis</option>
                                <option value="Bolu">Bolu</option>
                                <option value="Burdur">Burdur</option>
                                <option value="Bursa">Bursa</option>
                                <option value="Canakkale">Çanakkale</option>
                                <option value="Cankiri">Çankırı</option>
                                <option value="Corum">Çorum</option>
                                <option value="Denizli">Denizli</option>
                                <option value="Diyarbakir">Diyarbakır</option>
                                <option value="Edirne">Edirne</option>
                                <option value="Elazig">Elazığ</option>
                                <option value="Erzincan">Erzincan</option>
                                <option value="Erzurum">Erzurum</option>
                                <option value="Eskisehir">Eskişehir</option>
                                <option value="Gaziantep">Gaziantep</option>
                                <option value="Giresun">Giresun</option>
                                <option value="Gumushane">Gümüşhane</option>
                                <option value="Hakkari">Hakkari</option>
                                <option value="Hatay">Hatay</option>
                                <option value="Isparta">Isparta</option>
                                <option value="Mersin">Mersin</option>
                                <option value="Istanbul" selected>İstanbul</option>
                                <option value="Izmir">İzmir</option>
                                <option value="Kars">Kars</option>
                                <option value="Kastamonu">Kastamonu</option>
                                <option value="Kayseri">Kayseri</option>
                                <option value="Kirklareli">Kırklareli</option>
                                <option value="Kirsehir">Kırşehir</option>
                                <option value="Kocaeli">Kocaeli</option>
                                <option value="Konya">Konya</option>
                                <option value="Kutahya">Kütahya</option>
                                <option value="Malatya">Malatya</option>
                                <option value="Manisa">Manisa</option>
                                <option value="Kahramanmaras">Kahramanmaraş</option>
                                <option value="Mardin">Mardin</option>
                                <option value="Mugla">Muğla</option>
                                <option value="Mus">Muş</option>
                                <option value="Nevsehir">Nevşehir</option>
                                <option value="Nigde">Niğde</option>
                                <option value="Ordu">Ordu</option>
                                <option value="Rize">Rize</option>
                                <option value="Sakarya">Sakarya</option>
                                <option value="Samsun">Samsun</option>
                                <option value="Siirt">Siirt</option>
                                <option value="Sinop">Sinop</option>
                                <option value="Sivas">Sivas</option>
                                <option value="Tekirdag">Tekirdağ</option>
                                <option value="Tokat">Tokat</option>
                                <option value="Trabzon">Trabzon</option>
                                <option value="Tunceli">Tunceli</option>
                                <option value="Sanliurfa">Şanlıurfa</option>
                                <option value="Usak">Uşak</option>
                                <option value="Van">Van</option>
                                <option value="Yozgat">Yozgat</option>
                                <option value="Zonguldak">Zonguldak</option>
                                <option value="Aksaray">Aksaray</option>
                                <option value="Bayburt">Bayburt</option>
                                <option value="Karaman">Karaman</option>
                                <option value="Kirikkale">Kırıkkale</option>
                                <option value="Batman">Batman</option>
                                <option value="Sirnak">Şırnak</option>
                                <option value="Bartin">Bartın</option>
                                <option value="Ardahan">Ardahan</option>
                                <option value="Igdir">Iğdır</option>
                                <option value="Yalova">Yalova</option>
                                <option value="Karabuk">Karabük</option>
                                <option value="Kilis">Kilis</option>
                                <option value="Osmaniye">Osmaniye</option>
                                <option value="Duzce">Düzce</option>
                            </select>
                        </h5>
                        <p class="card-text m-0" id="weather-desc" style="text-transform: capitalize; font-size: 0.9rem;">Yükleniyor...</p>
                    </div>
                    <div class="text-end"><img id="weather-icon" src="" alt="" width="50"><div class="weather-temp" id="weather-temp">--°C</div></div>
                </div>
            </div>
        </div>
    </div>

   <div class="mb-4 d-flex flex-wrap align-items-center gap-2"> 
    <a href="../randevu" class="btn btn-primary mb-2"><?php echo $lang['yeni_randevu_al']; ?></a>
    <a href="../anket" class="btn btn-info text-white mb-2"><?php echo $lang['memnuniyet_anketi']; ?></a>
    
    <a href="../sikayet" class="btn btn-warning text-dark mb-2"><?php echo $lang['sikayet_kutusu']; ?></a>
    
    <a href="../profile" class="btn btn-secondary mb-2"><?php echo $lang['profilim']; ?></a>
    
    <a href="../logout" class="btn btn-danger mb-2"><?php echo $lang['cikis_yap']; ?></a>
</div>
    
    <?php if (!empty($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

    <h3><?php echo $lang['randevularim']; ?></h3>
    
    <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th><?php echo $lang['tarih']; ?></th><th><?php echo $lang['saat']; ?></th><th><?php echo $lang['hastane']; ?></th><th><?php echo $lang['bolum']; ?></th><th><?php echo $lang['doktor']; ?></th><th><?php echo $lang['durum']; ?></th><th><?php echo $lang['islem']; ?></th></tr></thead>
            <tbody>
            <?php if (empty($appointments)): ?>
                <tr><td colspan="7"><div class="alert alert-info"><?php echo $lang['randevu_yok']; ?></div></td></tr>
            <?php else: ?>
                <?php foreach ($appointments as $app): ?>
                    <tr>
                      <td><?php echo date("d.m.Y", strtotime($app['appointment_date'])); ?></td>
                      <td><?php echo date("H:i", strtotime($app['appointment_time'])); ?></td>
                      <td><?php echo htmlspecialchars($app['hastane_adi']); ?></td>

                        <td><?php echo htmlspecialchars($app['bolum_adi']); ?></td>
                        <td><?php echo htmlspecialchars($app['doktor_adi']); ?></td>
                        <td>
                            <?php if ($app['status'] == 'pending'): ?><span class="badge bg-warning text-dark"><?php echo $lang['durum_admin_onayi_bekliyor']; ?></span>
                            <?php elseif ($app['status'] == 'pending_user_approval'): ?><span class="badge bg-info text-dark"><?php echo $lang['durum_sizin_onayiniz_bekleniyor']; ?></span>
                            <?php elseif ($app['status'] == 'confirmed'): ?><span class="badge bg-success"><?php echo $lang['durum_onaylandi']; ?></span>
                            <?php elseif ($app['status'] == 'cancelled'): ?><span class="badge bg-danger"><?php echo $lang['durum_iptal_edildi']; ?></span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($app['status'] == 'pending'): ?>
                                <a href="?cancel_id=<?php echo $app['appointment_id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('İptal?')"><?php echo $lang['iptal_et']; ?></a>
                            <?php elseif ($app['status'] == 'pending_user_approval'): ?>
                                <a href="?confirm_id=<?php echo $app['appointment_id']; ?>" class="btn btn-success btn-sm"><?php echo $lang['onayla']; ?></a>
                                <a href="?cancel_id=<?php echo $app['appointment_id']; ?>" class="btn btn-warning btn-sm"><?php echo $lang['iptal_et']; ?></a>
                            <?php elseif ($app['status'] == 'confirmed'): ?>
                                <a href="?cancel_id=<?php echo $app['appointment_id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('İptal?')"><?php echo $lang['iptal_et']; ?></a>
                            <?php else: ?><span class="text-muted">-</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<button id="chatbot-btn" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999; width: 60px; height: 60px; border-radius: 50%; background-color: #0d6efd; color: white; border: none; box-shadow: 0 4px 8px rgba(0,0,0,0.2); cursor: pointer; font-size: 30px;"><i class="bi bi-robot"></i></button>

<div id="chat-window" style="display: none; position: fixed; bottom: 90px; right: 20px; z-index: 9999; width: 350px; height: 450px; background-color: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); flex-direction: column; overflow: hidden;">
    <div style="background-color: #0d6efd; color: white; padding: 10px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;">
        <span><i class="bi bi-robot"></i> Asistan</span>
        <button id="close-chat" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer;">&times;</button>
    </div>
    
    <div id="chat-messages" style="flex: 1; padding: 15px; overflow-y: auto; background-color: #f8f9fa; display: flex; flex-direction: column;">
        <div class="chat-bubble chat-bot">Merhaba! Şikayetinizi yazın, ilgili bölüme yönlendireyim.</div>
    </div>
    
    <div style="padding: 10px; border-top: 1px solid #dee2e6; display: flex; gap: 5px;">
        <input type="text" id="user-input" class="form-control" placeholder="Yazın..." style="font-size: 14px;">
        <button id="send-btn" class="btn btn-primary"><i class="bi bi-send"></i></button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- HAVA DURUMU ---
    const citySelector = document.getElementById('city-selector'); 
    const weatherBox = document.getElementById('weather-box');
    
    // 1. Kayıtlı şehri bul, yoksa İstanbul
    const savedCity = localStorage.getItem('selectedCity') || 'Istanbul';
    citySelector.value = savedCity;

    // 2. Hava durumunu getiren fonksiyon
    function getWeather(city) {
        fetch('../weather.php?city=' + city)
            .then(response => response.json())
            .then(data => {
                if(!data.error) {
                    document.getElementById('weather-temp').innerText = Math.round(data.main.temp) + "°C";
                    document.getElementById('weather-desc').innerText = data.weather[0].description;
                  document.getElementById('weather-icon').src =
                 "https://openweathermap.org/img/wn/" + data.weather[0].icon + "@2x.png";
                    weatherBox.style.display = 'block'; // Veri gelince göster
                } else {
                    document.getElementById('weather-desc').innerText = "Veri Hatası";
                    document.getElementById('weather-icon').src = "";
                    document.getElementById('weather-temp').innerText = "--°C";
                }
            })
            .catch(err => console.log("Hava durumu hatası:", err));
    }

    // 3. İlk açılışta hava durumunu getir
    getWeather(savedCity);

    // 4. Şehir değişince çalış (localStorage'a kaydet)
    citySelector.addEventListener('change', function() {
        const newCity = this.value;
        localStorage.setItem('selectedCity', newCity); 
        getWeather(newCity); 
    });


    // --- CHATBOT (GÜNCELLENMİŞ KISIM) ---
    const chatBtn = document.getElementById('chatbot-btn'); 
    const chatWindow = document.getElementById('chat-window'); 
    const closeChat = document.getElementById('close-chat'); 
    const sendBtn = document.getElementById('send-btn'); 
    const userInput = document.getElementById('user-input'); 
    const chatMessages = document.getElementById('chat-messages');

    chatBtn.addEventListener('click', () => { 
        chatWindow.style.display = chatWindow.style.display === 'none' ? 'flex' : 'none'; 
        if(chatWindow.style.display === 'flex') userInput.focus(); 
    });
    
    closeChat.addEventListener('click', () => { chatWindow.style.display = 'none'; });
    
    async function sendMessage() {
        const message = userInput.value.trim(); 
        if (message === "") return;

        // 1. Kullanıcı mesajını ekle
        addMessage(message, 'user');
        userInput.value = '';

        // 2. Loading göstergesi ekle
        const loadingId = "loading-" + Date.now();
        const loadingDiv = document.createElement('div');
        loadingDiv.id = loadingId;
        loadingDiv.className = 'chat-loading';
        loadingDiv.textContent = 'AI düşünüyor...';
        chatMessages.appendChild(loadingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;

        try {
            // 3. PHP API'ye istek (Proxy)
            const response = await fetch('../chatbot_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message })
            });

            if (!response.ok) throw new Error("API Hatası");

            const data = await response.json();

            // Loading'i kaldır
            const loadingElem = document.getElementById(loadingId);
            if(loadingElem) loadingElem.remove();

            // 4. Cevabı işle
            if (data.reply) {
                // Yapay zeka cevabını kalın yaparak ekle
                addMessage(data.reply, 'bot');
            } else if (data.error) {
                addMessage("⚠️ Hata: " + data.error, 'bot');
            }

        } catch (error) {
            const loadingElem = document.getElementById(loadingId);
            if(loadingElem) loadingElem.remove();
            addMessage("❌ Bağlantı hatası: Yapay zekaya ulaşılamadı.", 'bot');
            console.error(error);
        }
    }
    
    // Enter tuşunu dinle
    userInput.addEventListener('keypress', (e) => { 
        if (e.key === 'Enter') sendMessage(); 
    }); 
    
    sendBtn.addEventListener('click', sendMessage);
    
    // Mesaj ekleme fonksiyonu (HTML destekli)
    function addMessage(text, sender) {
        const div = document.createElement('div'); 
        div.className = 'chat-bubble ' + (sender === 'user' ? 'chat-user' : 'chat-bot');
        
        // Asistan ise kalın yazsın diye innerHTML, kullanıcı ise güvenli textContent
        if (sender === 'bot') {
            div.innerHTML = `<strong>Asistan:</strong> ${text}`;
        } else {
            div.textContent = text;
        }
        
        chatMessages.appendChild(div); 
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});
</script>

</body>
</html>
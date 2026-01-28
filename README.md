# 🏥 Web Tabanlı Hastane Randevu ve Yönetim Sistemi
> **Yapay Zeka Destekli, Çoklu Dil Seçenekli ve Tam Responsive Web Uygulaması**

Bu proje, hastaların hızlıca randevu almasını sağlayan, doktor ve bölümlerin dinamik yönetildiği, **Google Gemini AI** destekli modern bir sağlık yönetim sistemidir. **İnternet Programcılığı II** dersi kapsamında, modern web teknolojileri ve güvenlik standartları (PDO, Hash, XSS Koruması) dikkate alınarak geliştirilmiştir.

![Project Status](https://img.shields.io/badge/Status-Completed-success)
![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/Database-MySQL-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Frontend-Bootstrap%205-7952B3?logo=bootstrap&logoColor=white)
![AI](https://img.shields.io/badge/AI-Google%20Gemini-4285F4?logo=google&logoColor=white)

---

## 🌟 Öne Çıkan Özellikler

### 🤖 1. Yapay Zeka (AI) Asistanı
- **Google Gemini API** entegrasyonu sayesinde, hastaların şikayetlerini (Örn: "Başım ağrıyor ve midem bulanıyor") doğal dilde analiz eder.
- Hastayı otomatik olarak **en uygun tıbbi bölüme** yönlendirir.
- İnsancıl ve empatik yanıtlar verir.

### 🌍 2. Gelişmiş Dil ve Yerelleştirme
- **4 Dil Desteği:** Türkçe, İngilizce, Almanca ve Arapça.
- **RTL Desteği:** Arapça seçildiğinde arayüz otomatik olarak sağdan-sola (Right-to-Left) düzenine geçer.

### 🌤️ 3. API Entegrasyonları
- **Hava Durumu:** Open-Meteo API kullanılarak, kullanıcının bulunduğu şehrin anlık hava durumu (Sıcaklık, Durum İkonu) panelde gösterilir.
- **Dinamik Veri:** AJAX (Fetch API) kullanılarak il seçildiğinde hastaneler, hastane seçildiğinde doktorlar sayfa yenilenmeden listelenir.

### 🛠️ 4. Teknik ve Güvenlik Özellikleri
- **Görüntü İşleme:** `Cropper.js` ile kullanıcılar yükledikleri resimleri tarayıcı üzerinde kırpabilir, döndürebilir ve sunucuya optimize edilmiş halde yükleyebilir.
- **Güvenlik:**
  - SQL Injection'a karşı `%100 PDO Prepared Statements`.
  - XSS saldırılarına karşı `htmlspecialchars()` filtrelemesi.
  - Şifreler veritabanında `password_hash()` (Bcrypt) ile saklanır.
- **UX/UI:** Gece/Gündüz modu (Dark Mode) ve Font Büyütme (Erişilebilirlik) seçenekleri.

---

## 🚀 Kurulum (Nasıl Çalıştırılır?)

Projeyi yerel sunucunuzda (**XAMPP / WAMP**) çalıştırmak için adımları izleyin:

### 1️⃣ Repoyu Klonlayın
```bash
git clone https://github.com/Feleknaz/hospital_fresh.git
 

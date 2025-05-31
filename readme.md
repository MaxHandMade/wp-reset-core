# WP Reset Core (WordPress Sıfırlama Eklentisi)

## Proje Açıklaması
WP Reset Core, test ve geliştirme amaçlı kullanılan WordPress sitelerini hızlı, güvenli ve kapsamlı bir şekilde ilk kurulum haline getirmeyi amaçlayan bir eklentidir. Eklenti; temaları, diğer eklentileri, özel kodları, yazıları, sayfaları, yorumları ve diğer içerikleri temizler. Ayrıca, veritabanını WordPress'in çekirdek yapılarını koruyarak ve eklentilere ait tabloları silerek ilk kurulduğu günkü temiz haline döndürür.

## Amaçlar
*   Test ortamlarını hızla yenilemek ve tutarlı bir başlangıç noktası sağlamak.
*   WordPress'i ilk kurulumdaki "tertemiz" ve varsayılan ayarlarına geri döndürmek.
*   Gereksiz verileri, dosyaları (eklentiler, temalar, yüklemeler) ve veritabanı kalıntılarını temizlemek.
*   Kullanıcıya işlemin ciddiyetini bildiren, güvenli ve kullanıcı dostu bir sıfırlama arayüzü sunmak.

## Temizlik ve Son Güncellemeler
- Kullanılmayan ve boş olan `options-reset.php` dosyası kaldırıldı.
- Ana eklenti dosyasındaki gereksiz `require_once` satırı temizlendi.
- Kodlar sadeleştirildi ve gereksiz dosya/bağlantılar kaldırıldı.
- Proje güncel WordPress standartlarına ve güvenlik pratiklerine uygundur.

## Tamamlanan Temel Özellikler
*   **Kapsamlı Veritabanı Sıfırlama:**
    *   WordPress dışı (eklentiler/temalar tarafından eklenen) tüm tabloları silme.
    *   Yazıları, sayfaları, yorumları, kategorileri, etiketleri ve bunlarla ilişkili tüm meta verileri silme (`wp_posts`, `wp_postmeta`, `wp_comments`, `wp_commentmeta`, `wp_terms`, `wp_termmeta`, `wp_term_taxonomy`, `wp_term_relationships`, `wp_links` tablolarını boşaltma).
    *   Sıfırlama işlemini yapan yönetici kullanıcısı hariç tüm diğer kullanıcıları silme.
    *   `wp_options` tablosunu agresif bir şekilde temizleyip, ardından WordPress'in ilk kurulum varsayılan seçeneklerini (`populate_options()` ve `wp_install_defaults()` ile) yeniden yükleme.
    *   Genel WordPress geçici verilerini (`_transient_%`, `_site_transient_%`) ve bilinen bazı Action Scheduler kalıntılarını temizleme.
*   **Dosya Sistemi Temizliği:**
    *   Aktif "WP Reset Core" eklentisi hariç tüm diğer eklentileri devre dışı bırakma ve silme.
    *   Varsayılan WordPress temaları (örn: Twenty Twenty-Five, Twenty Twenty-Four vb.) dışındaki tüm temaları silme.
    *   Gerekirse (sistemde hiç varsayılan tema yoksa) en güncel WordPress varsayılan temasını WordPress.org'dan indirip kurma ve aktif etme.
    *   `wp-content/uploads` klasörünün içeriğini tamamen silme.
*   **WordPress Çekirdeğini Yeniden Başlatma:**
    *   Varsayılan WordPress içeriğini ("Merhaba Dünya!" yazısı, "Örnek Sayfa", varsayılan yorum vb.) yeniden oluşturma (`wp_install_defaults()` ile).
    *   `.htaccess` dosyasını varsayılan ayarlarına döndürme (kalıcı bağlantıları sıfırlayarak).
*   **Kullanıcı Arayüzü ve Güvenlik:**
    *   WordPress yönetici panelinde özel bir ayarlar sayfası.
    *   İşlemin geri alınamaz olduğuna dair açık ve net uyarı mesajları.
    *   Çok adımlı onay mekanizması (metin girişi ve onay kutusu).
    *   WordPress Nonce (Number used once) ile form gönderim güvenliği.
    *   İşlem öncesi tam yedekleme yapılması gerektiğine dair güçlü uyarı.

## Dosya Yapısı (Güncel)
wp-reset-core/
├── css/
│   └── admin-style.css
├── includes/
│   ├── admin/
│   │   ├── admin-assets.php
│   │   ├── admin-menu.php
│   │   └── admin-page-display.php
│   └── reset-actions/
│       ├── database-reset.php
│       ├── file-system-reset.php
│       ├── main-reset-controller.php
│       ├── user-reset.php
│       └── wordpress-defaults.php
├── languages/
│   └── (Boş - Çeviri dosyaları eklenebilir)
├── wp-reset-core.php              (Ana eklenti dosyası)
└── readme.md

## Kullanım Uyarısı
**BU EKLENTİ ÇOK GÜÇLÜDÜR VE SİTENİZDEKİ TÜM VERİLERİ SİLEREK GERİ DÖNÜLEMEZ DEĞİŞİKLİKLER YAPAR.**
*   **KESİNLİKLE CANLI SİTELERDE KULLANMAYIN!** Sadece test ve geliştirme ortamları için tasarlanmıştır.
*   Bu eklentiyi kullanmadan önce sitenizin **TAM BİR YEDEĞİNİ (TÜM DOSYALAR + VERİTABANI)** aldığınızdan emin olun.
*   Eklenti, ücretli (premium) temalarınızı ve eklentilerinizi de silecektir. Bunları tekrar yüklemek için orijinal dosyalarına ve lisans anahtarlarına ihtiyacınız olacaktır.
*   Sıfırlama sonrası bazı karmaşık eklentiler (örneğin WooCommerce) yeniden kurulduğunda, tüm ayarlarını ve veritabanı tablolarını doğru bir şekilde oluşturamayabilir. Bu tür durumlar eklentinin kendi kurulum rutinlerine bağlıdır. Eklentimiz, WordPress'i olabildiğince temiz bir hale getirmeyi hedefler.

## Geliştirme Notları ve Gelecek Fikirler
*   [X] WooCommerce'in yeniden kurulumda tablo oluşturmama sorunu büyük ölçüde çözüldü (agresif `wp_options` temizliği ile).
*   [ ] Kullanıcı arayüzüne sıfırlama işlemi sırasında bir "işlem yapılıyor" göstergesi (spinner) eklenebilir. (Sıradaki Adım)
*   [ ] Belirli kısımları sıfırlama seçeneği (örneğin sadece temalar, sadece eklentiler, sadece uploads).
*   [ ] Çeviri için `.pot` dosyası oluşturulması.
*   [ ] Tema indirme/aktif etme mantığında, en güncel varsayılan temayı WordPress.org API'sinden dinamik olarak belirleme.

## Son Durum ve Bilinen Sorunlar (2024)

### ✔️ Çalışan Özellikler
- Eklentiler, temalar, uploads klasörü ve dosya sistemi başarıyla temizleniyor.
- WordPress çekirdek dışı (eklentilere ait) tablolar tam sıfırlamada da siliniyor.
- Kullanıcı arayüzü modern ve mobil uyumlu.

### ❗ Bilinen Sorunlar
- Tam sıfırlama sonrası ana sayfa (front page) otomatik olarak "Son Yazılar"a ayarlansa da, bazı durumlarda WordPress ayarları hemen güncellenmiyor ve ana sayfa 404 veya "kritik hata" verebiliyor. Manuel olarak "Ayarlar > Okuma > Giriş sayfası görüntülenmesi > Son yazılarınız" seçilirse sorun çözülüyor.
- Sıfırlama sonrası bazı eklentiler (özellikle WooCommerce, Elementor gibi) yeniden kurulduğunda, kendi tablolarını veya ayarlarını oluşturmakta sorun yaşayabiliyor. Bu, eklentinin kendi kurulum rutinine bağlıdır.
- Veritabanı işlemlerinde, bazı hostinglerde veya local kurulumlarda foreign key kısıtlamaları nedeniyle tablo silme işlemleri başarısız olabiliyor.
- Sıfırlama sırasında eklenti tabloları silindikten sonra, ilgili eklentiler devre dışı bırakılırken hata oluşabilir. (Öneri: Önce eklentileri devre dışı bırak, sonra tabloları sil.)

### ➡️ Bir Sonraki Geliştirme İçin Notlar
- **Sıfırlama sıralaması değiştirilecek:** Önce eklentiler devre dışı bırakılacak ve silinecek, ardından veritabanı işlemleri yapılacak. Böylece eklenti tabloları silindikten sonra oluşan hata riskleri azaltılacak.
- Ana sayfa ayarlarının sıfırlama sonrası kesin olarak güncellenmesi için alternatif yöntemler (ör. doğrudan veritabanı güncellemesi veya geçici yönlendirme) araştırılacak.
- Sıfırlama sonrası otomatik olarak bir "Hoşgeldiniz" yazısı veya yönlendirme eklenebilir.
- Kullanıcıya, sıfırlama sonrası yapılması gereken manuel adımlar (ör. ana sayfa ayarı) hakkında bilgi veren bir uyarı gösterilebilir.

---

Bu bölüm, eklentinin mevcut eksiklerini ve geliştirme planlarını özetler. Katkı ve geri bildirimleriniz için teşekkürler!

Bu adımı tamamladığımıza göre, şimdi **Adım C: Kullanıcı Arayüzü İyileştirmesi (Spinner Ekleme)**'ye geçebiliriz. Hazır mısınız?
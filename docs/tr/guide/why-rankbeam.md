---
title: Rankbeam nedir? Laravel SEO altyapısına bakış
description: "Rankbeam, Laravel için açık çekirdekli SEO altyapısıdır. Meta verileri, kanonik URL'ler, JSON-LD, site haritaları ve tarayıcı botlara yönelik denetimler için ücretsiz MIT çekirdeği; ticari Pro izleme motoru ve isteğe bağlı Filament arayüzü."
---

# Rankbeam nedir? {#what-is-rankbeam}

**Rankbeam, Laravel için açık çekirdekli SEO altyapısıdır: meta verileri, kanonik URL'ler, sosyal kartlar, bağlantılı JSON-LD, site haritaları ve tarayıcı botlara yönelik denetimler için ücretsiz MIT çekirdeği; ayrıca isteğe bağlı ticari Pro izleme ve iş akışları.** Uygulamanın yanına eklenen bir çalışma zamanı etiket yardımcısı değildir. SEO verilerini kendi modellerinizden ve yapılandırmanızdan çözümler; aynı türlendirilmiş veriyi Blade, Inertia head veya JSON API olarak sunar ve Pro ile dağıtımdan sonra da izlemeyi sürdürür.

## Paket ailesi {#the-package-family}

Rankbeam, tek destek matrisi paylaşan üç paketten oluşur:

| Paket | Lisans | Nedir? |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, ücretsiz** | Çekirdek: meta çözümleme, bağlantılı JSON-LD şema grafı, XML site haritaları, tarayıcı botlara yönelik denetimler, ücretsiz `seo:audit` ve içe aktarıcılar |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, ücretsiz** | Çekirdeğin `seo_meta` tablosuna yazan Filament 4/5 form alanları ve anlık önizlemeler |
| `rankbeam/laravel-seo-pro` | **ticari** | Operasyon motoru: 0–100 puanlı kuyruklu taramalar, yönlendirme yöneticisi, IP saklamayan 404 izleme, bozuk bağlantı tarayıcısı, Search Console analizleri ve kendi anahtarınızla yapay zekâ yardımı |

Sınır bilinçlidir. Üretilen sayfanın tüm çıktıları MIT kapsamındadır ve daima ücretsizdir; ödeme yaptığınız kısım canlı ortam **denetimi ve izleme** katmanıdır. Ticari Pro ayrı pakettir; ücretsiz çekirdeğin içinde asla sunulmaz.

## Kimler için? {#who-it-s-for}

Rankbeam, SEO verileri **kalıcı saklandığında, modele bağlandığında, çok dilli olduğunda, arayüzden bağımsız sunulduğunda ve denetlendiğinde** değer sağlar: dinamik veya modele dayalı içeriği olan bir Laravel canlı uygulamasında. Birkaç statik sayfaya tek başlık ve açıklama gerekiyorsa küçük bir çalışma zamanı meta yardımcısı daha uygundur; aşağıdaki [ayrı paketlerin birlikte kullanımının hâlâ uygun olduğu durumlar](#what-is-honestly-not-in-the-free-core) notu bunu açıkça söyler.

## Desteklenen sürümler {#supported-versions}

Tüm aile için tek matris:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13 (Laravel 13, PHP 8.3+ gerektirir)
- **Filament** 4 / 5 (isteğe bağlı)

## Rankbeam nelerin yerini almaz? {#what-rankbeam-doesn-t-replace}

Rankbeam, Laravel uygulamasının kendi SEO çıktısını koordine eder. Barındırılan sıralama takip aracı, anahtar kelime araştırma paketi veya analitik ürün değildir; sıralama, dizine eklenme veya yapay zekâ kaynak gösterimi vaat etmez. XML site haritası üretiminde [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap) paketini yeniden icat etmek yerine kullanır; içeriğinizi, rotalarınızı ve analitiğinizi oldukları yerde bırakır.

Yeni misiniz? [Ücretsiz çekirdeği kurun](/tr/guide/installation) veya gerçek canlı uygulama geçişinin kanıtları için okumaya devam edin. Pro ve ilk müşterilere özel teklif [rankbeam.dev](https://rankbeam.dev/tr/) adresindedir.

## Neden üç paket ve aralarındaki özel bağlantı kodu değil? {#why-not-three-packages-glue}

Çoğu Laravel uygulamasının tek bir “SEO paketi” yoktur; bir **SEO sistemi** vardır: model başına meta saklayan paket, Filament'e alan ekleyen ikinci paket, sayfaları tarayan üçüncü paket ve üçünün aynı sonucu vermesini sağlayan uygulamaya özel bağlantı katmanı. Her parça tek başına işe yarar. Maliyet, aralarındaki birleşim noktalarıdır; o bağlantı kodunu sürekli sizin sürdürmeniz gerekir.

Bu sayfa, tam olarak böyle bir sistemin kaldırılıp Rankbeam ailesiyle değiştirildiği gerçek canlı uygulama geçişinin kayıtlarını sunar. Aşağıdaki sayılar pazarlama için üretilmemiştir; ölçülmüştür.

## Referans uygulama {#the-reference-app}

Gerçek, canlıda çalışan bir Laravel içerik sitesi (burada anonimleştirilmiştir):

- Yaklaşık 3 aydır canlıda olan **hastane / kurumsal içerik sitesi**.
- **WordPress'ten taşınmış**, site haritasına göre yaklaşık 900 sayfa.
- Günde yaklaşık **20.000 ziyaret**.
- **Laravel 12**, **Filament 4** yönetim paneli, Blade ön yüzü, MySQL.

Geçişten önce kullandığı SEO sistemi:

| Katman | Paket |
|---|---|
| Meta depolama (model başına `seo` tablosu) | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Filament SEO alanları | `ralphjsmit/laravel-filament-seo` |
| Sayfa tarayıcısı | `backstage/laravel-seo-scanner` |
| Aradaki her şey | **Yaklaşık 30 özel uygulama sınıfı** |

Üç paketi kaldırdık, Rankbeam **çekirdek + Pro + Filament** kurduk, SEO test paketini çalıştırdık ve uygulama **sıfır SEO regresyonuyla** başladı. Aşağıda bağlantı katmanının gerçek maliyeti ve nelerin ortadan kalktığı yer alıyor.

## Geçiş neleri sildi? {#what-the-swap-deleted}

Tarayıcı sistemini Rankbeam ile değiştirmek, **12 özel sınıfı doğrudan sildi**. Eşdeğer işi artık paket ailesi üstlendiğinden uygulama bunlardan sorumlu değil:

| Silinen uygulama sınıfı | Ne yapıyordu? | Şimdi sağlayan |
|---|---|---|
| `Services/SeoService.php` | Uygulamanın SEO giriş noktası sarmalayıcısı | Çekirdek çözümleyici + `SEO` facade'ı |
| `Services/SeoWarningEvaluator.php` | Başlık/açıklama uzunluğu + görsel boyutu eşikleri | Çekirdek `SEOWarningEvaluator` (denetim, önizleme ve tarama paylaşır) |
| `Services/Seo/SeoAssetInspector.php` | Yerel görsel boyutlarının incelenmesi | Çekirdek `LocalImageInspector` |
| `Jobs/ScanAllPagesSeo.php` | Kuyruklu site geneli tarama başlatma | Pro kuyruklu [tarama hattı](/tr/pro/scan-issues) |
| `Jobs/ScanPageSeo.php` | Sayfa başına tarama | Pro `PageScanner` |
| `Jobs/ScanPublicPageSeo.php` | Herkese açık sayfa başına tarama | Pro tarama hattı |
| `Models/SeoScanBatch.php` | Tarama çalıştırmalarının kayıt takibi | Pro `seo_scan_runs` |
| `Filament/Pages/SeoDashboard.php` | SEO yönetim panosu | Pro `SeoDashboard` eklentisi |
| `Filament/Widgets/SeoScanProgressWidget.php` | Tarama ilerleme bileşeni | Pro tarama bileşenleri |
| `Filament/Widgets/SeoTrendChartWidget.php` | Tarama eğilim bileşeni | Pro tarama bileşenleri |
| `Facades/Seo.php` | Depolama paketinin üzerindeki uygulama facade'ı | Çekirdek `SEO` facade'ı |
| `Console/Commands/RecoverLegacySeoMetadata.php` | Tek seferlik meta veri kurtarma | Çekirdek [içe aktarıcıları](/tr/guide/migrate-from-wordpress) (`seo:import-from`) |

::: info Kalanların açık dökümü
Geçiş, uygulamanın özel bozuk bağlantı tarayıcısını bilinçli olarak **korudu** (yaklaşık 17 sınıf: tarama işi, kontrol aracı, başlangıç kaynak oluşturucusu, kaynak çözümleyici, iki model, iki enum, iki olay, Filament kaynağı + üç bileşen, iki komut). Birkaç meta/şema yardımcısı da kaldı (`CustomSEO`, `EntitySeoSection`, `DynamicSeoDataResolver`, `SitewideSchema`, `SeoKeywords`); toplam yaklaşık **22 ek sınıf**. Bunlar ilk gün silinmedi; Rankbeam karşılıkları daha sonra geldi: özel tarayıcı için [Pro bozuk bağlantı tarayıcısı](/tr/pro/production), `CustomSEO`/`EntitySeoSection` için Filament **ilişkili model hedefi** + **SERP/sosyal önizleme**, `SitewideSchema` için çekirdek **şema grafı**. Tüm aileyi benimsediğinizde toplamda yaklaşık **üç düzine sınıflık** bu özel alan, sizin değil paketin işi olur.
:::

Konu bu paketlerden birinin kötü olması değildir. *Entegrasyon* — meta değişikliğinin tarayıcıya, panoya ve üretilen head bölümüne yansımasını sağlamak için onları birbirine bağlayan on ikiden fazla sınıf — üst projesi, sizinkiler dışında testleri veya başkalarının hata raporları olmayan özel koddur.

## Yan yana karşılaştırma {#side-by-side}

| Yetenek | Birleştirilmiş sistem (3 paket + bağlantı kodu) | Rankbeam ailesi |
|---|---|---|
| Model başına meta depolama | Meta paketi | **çekirdek** (`seo_meta`, MIT) |
| **Dile duyarlı** depolama | Genellikle bağlantı kodunun sorumluluğu | **çekirdek** — `seo_meta` sütun üzerinden dile göre kapsamlanır |
| Filament SEO alanları | Filament-SEO paketi | **`laravel-seo-filament`** (MIT) |
| **İlişkili** modelin SEO'sunu düzenleme | Alan bileşenini kendiniz sarmalarsınız | Yerleşik `target:` çözümleyicisi |
| Anlık **SERP + sosyal** önizleme | Elle hazırlanmış Blade/Alpine | Yerleşik sekmeli editoryal önizleme |
| Arayüzden bağımsız çıktı (Inertia / Livewire / JSON) | Referans uygulama Blade kullanıyordu; diğer yapılar entegrasyon gerektirir | **tek çözümleyici** → Blade, Inertia, Livewire, JSON ([sözleşmeyle test edilmiş](/tr/contributing/rendering-contract)) |
| Sayfa tarayıcısı + öncelik sıralı sorunlar | Tarayıcı paketi | **Pro** [tarama hattı](/tr/pro/scan-issues) + `IssueRegistry` |
| 0–100 puanı | Özel bağlantı kodu / yok | **Pro** şeffaf, [sürümlenmiş değerlendirme ölçeği](/tr/pro/scoring) |
| Yönlendirmeler + 404 kurtarma | Başka paket / özel kod | **Pro** yönlendirme yöneticisi + IP saklamayan 404 izleme |
| Bozuk bağlantı tarayıcısı | Özel (uygulama kendisi oluşturdu) | **Pro** sınırları belirli, devam ettirilebilir tarayıcı |
| JSON-LD şema **grafı** | Oluşturucu + kendi `@id` bağlantılarınız | **çekirdek** çapraz bağlı Organization/WebSite/WebPage grafı |
| XML site haritaları | Site haritası paketi | **çekirdek** site haritası kayıt sistemi (`spatie/laravel-sitemap` kullanır) |
| WordPress / Yoast / Rank Math içe aktarma | Tek seferlik script'ler | **çekirdek** `seo:import-from` + [uygulama kılavuzu](/tr/guide/wordpress-migration-runbook) |
| **Birleşim noktalarını kim sürdürür?** | **siz** | Tek sürüm çizgisindeki paket ailesi |

## Bağlantı kodunun iyi yapamadığı üç şey {#the-three-things-glue-can-t-do-well}

**1 — Bütünlüklü tek aile, tek sürüm çizgisi.** Üç paket; üç bakım sorumlusu, üç değişiklik günlüğü ve üç yükseltme takvimi demektir. Bağlantı kodu aralarındaki farklılaşmayı karşılar. Rankbeam çekirdek, Pro ve Filament; tek [destek matrisi](#tested-where-it-runs) ve belgelenmiş [yükseltme sınırlarıyla](/tr/reference/configuration) birlikte sürümlenir. Davranış değişikliği iki paket çeliştiğinde keşfedilmez; tek yerde duyurulur.

**2 — Gelenekle değil, sütunla dile duyarlı depolama.** `seo_meta` depolama katmanında hem polimorfik **hem de** dile göre kapsamlıdır. Çok dilli SEO, `(model, locale)` başına bir satırdır; serileştirilmiş veri yığını veya eklemeyi hatırladığınız bağlantı tablosu değildir. [Çözümleyici öncelik sırası](/tr/concepts/resolver-precedence), etkin dili doğrudan okur.

**3 — Tek çözümleyiciden arayüzden bağımsız çıktı.** Rankbeam türlendirilmiş `SEOData` çözümler ve *aynı* veriyi HTML, Inertia `Head` yükü veya JSON dizisi olarak üretir. [Blade](/tr/guide/blade), [Inertia](/tr/guide/inertia-json) (Vue/React/Svelte) ve [Livewire](/tr/guide/livewire) için ortak [çıktı sözleşmesiyle](/tr/contributing/rendering-contract) doğrulanmıştır. Yönetim paneli gerekmez; her Pro özelliği [Artisan üzerinden arayüzsüz](/tr/pro/headless) de çalışır.

## Ücretsiz çekirdekte gerçekten neler yok? {#what-is-honestly-not-in-the-free-core}

Rankbeam açık çekirdeklidir ve sınır bilinçlidir; `composer require` çalıştırmadan önce tam olarak ne aldığınızı bilirsiniz:

| Paket | Lisans | İçeriği |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, ücretsiz** | Meta çözümleme, JSON-LD şema grafı, site haritaları, ücretsiz `seo:audit`, içe aktarıcılar |
| `rankbeam/laravel-seo-filament` | **MIT, ücretsiz** | `seo_meta` tablosuna yazan Filament form alanları/bölümleri |
| `rankbeam/laravel-seo-pro` | **ticari** | Kuyruklu taramalar + öncelik sıralı sorunlar + 0–100 puanı, yönlendirmeler, 404 izleme, bozuk bağlantı tarayıcısı, Search Console, yapay zekâ yardımı, Filament panosu |

Ödeme yaptığınız kısımlar **teknik SEO denetimi** ve **site izleme** araçlarıdır: taramalar, puan, yönlendirmeler, 404 kurtarma ve tarayıcı. Meta veri motoru, şema grafı, site haritaları ve ücretsiz işlem içi denetim MIT kapsamındadır ve ücretsiz kalır.

İncelemeye dayanan iki özellik:

- **Çalışma sırasında lisans kontrolü yoktur.** Pro kurulumda proje başına lisanslanır; hiçbir şey lisans sunucusuna geri bağlanmaz ve uygulamanızı kapatabilecek bir durdurma anahtarı yoktur. (Pro kendi günlükleriniz için *yerel* operasyon telemetrisi üretir; kapatılabilir ve bize asla gönderilmez.)
- **Kendi anahtarınızla yapay zekâ.** İsteğe bağlı [yapay zekâ yardımı](/tr/pro/ai-assist), *sizin* Anthropic, OpenAI, Google veya yerel model anahtarınızı kullanır. Rankbeam isteklerinize aracılık etmez, kullanım üzerinden ücret almaz veya sağlayıcı hizmetlerini yeniden satmaz; özellik varsayılan olarak kapalıdır.

::: tip Ayrı paketlerin birlikte kullanımı ne zaman hâlâ uygundur?
Birkaç statik sayfada tek `<title>` ve açıklama gerekiyorsa çalışma zamanı etiket oluşturucu yeterlidir. Rankbeam, SEO **saklandığında**, **çok dilli olduğunda**, **modele bağlandığında**, **arayüzden bağımsız sunulduğunda** ve **denetlendiğinde** değer sağlar; paketler arasındaki bağlantı, sürdürdüğünüz gerçek koda dönüştüğünde.
:::

## En düşük riskli geçiş: WordPress'ten çıkış {#the-lowest-risk-switch-off-wordpress}

Referans uygulama yaklaşık 900 sayfalık WordPress geçişiydi; yılların Yoast/Rank Math optimizasyonuyla en fazla kayıp riski taşıyan kullanıcı grubuydu. Rankbeam bunu korkutucu değil, güvenli yol olarak ele alır:

1. **Birlikte çalıştırın.** Rankbeam'i canlı sitenin yanına kurun; henüz hiçbir şey kaldırılmaz.
2. **İçe aktarın (önce deneme).** `seo:import-from yoast` / `rank-math` / `wordpress-csv`; başlık, açıklama, kanonik URL, robots, odak anahtar kelime ve sosyal paylaşım özel değerlerini okur. İçe aktarıcılar **idempotenttir** ve **varsayılan olarak yalnızca boşları doldurur**. `--overwrite` olmadan zaten atadığınız meta verilerini korurlar; `--dry-run` hiçbir şey yazmaz.
3. **Yönlendirmeleri devredin.** Çekirdek sürümlenmiş yönlendirme CSV'si üretir; Pro'nun `seo-pro:redirects-import` komutu yazmadan önce her satırı doğrular, döngüleri, güvensiz hedefleri ve kopyaları reddeder.
4. **Bir şey silmeden önce doğrulayın.** `seo:audit --strict`, herhangi bir sorunda sıfırdan farklı kodla çıkan CI/yayına geçiş kontrolüdür. Eski WordPress veritabanı, siz silmeyi seçene kadar değişmez.

Tam işlem sırası [WordPress geçiş uygulama kılavuzundadır](/tr/guide/wordpress-migration-runbook); alan eşlemeleri ve belirteçlerin işlenmesi [WordPress'ten geçişte](/tr/guide/migrate-from-wordpress) açıklanır. Bunun yerine **Laravel** SEO paketinden mi geçiyorsunuz (ralphjsmit, artesaos, Spatie)? [Paket geçiş kılavuzuna bakın](/tr/guide/migrate-from-other-packages).

## Ölçek büyüdüğünde dayanır mı? {#does-it-hold-up-at-scale}

Referans uygulamanın en zor iki kısıtının — günlük yaklaşık 20 bin isteğin her birinde çözümleyici ve yaklaşık 900 sayfalık bağlantı taraması — test paketinde ayrı performans ölçümleri vardır. Bunlar özel ayarlanmış duvar saati sürelerini değil, **deterministik** kazanımları (sorgu sayıları ve iş sınırları) doğrular:

**Çözümleyici önbelleği: hazır önbellekten okuma veritabanına hiç dokunmaz.** İsteğe bağlı çözümleme önbelleği açıkken önbellekli çözümleme *tüm* öncelik zincirini atlar. Ölçüm aynı modeli 25 kez çözümler:

| | Veritabanı sorguları |
|---|---|
| Önbelleksiz (her çözümleme `seo_meta` verisini yeniden okur) | **≥ 25** |
| Hazır önbellekten okuma | **0** |

Önbellek **varsayılan olarak kapalıdır** ve ölçeklendirme aracı olarak belgelenir. `seo_meta`, içerik alanı veya varsayılanlar değiştiğinde geçersizleştirme doğru girdileri temizler. Bkz. [Yapılandırma → önbellek](/tr/reference/configuration).

**Bozuk bağlantı tarayıcısı: 900 sayfada sınırlı işler.** Tarayıcı ölçümü, üretilmiş yaklaşık 900 sayfalık içerik kümesini gerçek iş üzerinden çalıştırır:

- **≥ 18 sınırlı işte** tamamlanır (iş başına 50 sayfa sınırı).
- **Hiçbir iş**, 50 sayfalık sınırını aşmaz.
- **1.800 bağlantı** kontrol edilir; her ölü hedef kalıcı, bozukluğu doğrulanmış bulgu olur.

Çalıştırma başına sonlu sınırlar ve iş başına kesin zaman bütçesiyle sınırlandırılmıştır. Başlangıç isteğinde **ve her yönlendirme adımında** SSRF doğrulaması, kapsam başına yalnızca bir çalıştırmanın etkin olmasını sağlayan veritabanı kilit kiralaması vardır. Operasyonlar [canlı ortam kurulum kılavuzunda](/tr/pro/production) açıklanır.

## Çalıştığı ortamlarda test edildi {#tested-where-it-runs}

Tüm aile için üç değil, tek destek matrisi:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13
- **Filament** 4 / 5

## Öyleyse neden üç paketi birbirine bağlayasınız? {#so-—-why-glue-three-packages-together}

Birleştirilmiş sistem; entegrasyon için on ikiden fazla özel sınıf, kontrol etmediğiniz sürüm takvimi, teknolojiye özgü çıktı entegrasyonu ve elle eklenen dil yönetimi gerektiriyorsa; bütünlüklü, arayüzden bağımsız ve dili doğrudan ele alan bir aile bu bağlantı kodunu kaldırıyor, gerçek 900 sayfalık / günde 20 bin ziyaretli canlı uygulamada doğrulanıyorsa, birleştirilmiş sistem artık güvenli varsayılan olmaktan çıkar.

[Hızlı başlangıçla](/tr/guide/quickstart) başlayın: `composer require` komutundan eksiksiz oluşturulan `<head>` bölümüne beş dakikada.

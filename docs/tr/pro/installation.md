---
description: "laravel-seo-pro kurulumu: core üzerine sorun takibiyle kuyrukta çalışan site taramaları, yönlendirme yöneticisi ve 404 izleme ekleyin. Her Laravel 11–13 uygulamasında çalışır; Filament isteğe bağlıdır."
---

# Pro kurulumu {#installing-pro}

`rankbeam/laravel-seo-pro`, core paketine sorun takibiyle kuyrukta çalışan site taramaları, yönlendirme yöneticisi ve 404 izleme ekler. Motor, Blade, Inertia veya yalnızca API kullanan **her Laravel 11–13 uygulamasında** çalışır.
Filament isteğe bağlı bir arayüz katmanıdır: kurarsanız SEO panosu, yönlendirme yöneticisi ve 404 izleme panel sayfaları olarak sunulur; kurmazsanız her şeyi [Artisan komutlarıyla](/tr/pro/headless) yönetirsiniz.

## Gereksinimler {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13) |
| Laravel | 11, 12 veya 13 |
| `rankbeam/laravel-seo` | ^3.20 (Pro 2.40+ tarafından otomatik kurulur) |
| `filament/filament` | **isteğe bağlı** — 4.x veya 5.x, yalnızca yönetim arayüzü için |
| `rankbeam/laravel-seo-filament` | **isteğe bağlı** — Pro 2.36+ ile SEO düzenleyicisini kullanırken ^1.11 |

Mevcut bir Laravel uygulaması ve yapılandırılmış bir veritabanıyla başlayın. Bir modelin meta veri üretmesi ve core tablolarının bulunması için önce [core Hızlı başlangıç](/tr/guide/quickstart) adımlarını tamamlayın. Pro lisansı, aşağıdaki Composer kimlik bilgilerini sağlar.

Sonucun görsel bir örneği için [tarama → düzeltme → rapor](/tr/pro/walkthrough) akışına bakın.

## Paketi kurun {#install-the-package}

Pro, lisansınıza bağlı özel bir Composer deposundan dağıtılır. Depoyu bir kez ekleyip paketi kurun; Composer, lisans e-postanızı (kullanıcı adı) ve lisans anahtarınızı (parola) ister:

Ödemeyi kayıtlı satıcı olarak Lemon Squeezy alır. Ödemeden sonra özel makbuz sayfası indirme anahtarınızı ve Composer talimatlarını sunar. Kullanıcı adı olarak satın alma e-postasını kullanın. Paket deposunu Rankbeam barındırır; Anystack hesabı gerekmez. Makbuz bağlantısını ve `auth.json` dosyasını gizli tutun. Tam iade, kurulu uygulamanın çalışmasını kesintiye uğratmadan gelecekteki indirmeleri ve güncellemeleri iptal eder.

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details Etkileşimsiz Composer kimlik doğrulaması
CI veya etkileşimsiz ortamlar için kimlik bilgilerini önceden kaydedin:

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

Ardından kurulum komutunu çalıştırın:

```bash
php artisan seo-pro:install
```

Kurulum komutu `config/seo-pro.php` dosyasını ve Pro migration dosyalarını uygulamaya yayımlar, `migrate` komutunu çalıştırır ve sonraki adımları gösterir. Core ve Pro tabloları artık uygulamanızın veritabanında bulunmalıdır.

::: details Elle kurulum ve kurulum seçenekleri
Pro migration dosyaları uygulamanıza yayımlanır; paketten otomatik yüklenmez. Aynı işlemin elle uygulanan adımları şunlardır:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Kurulum komutu yeniden çalıştırılabilir. `--no-migrate`, migration çalıştırmadan dosyaları yayımlar. `--force` seçeneğini yalnızca yapılandırmanız dahil yayımlanmış dosyaların üzerine yazmak istediğinizde kullanın.
:::

## Tarama hedeflerini kaydedin {#register-scan-targets}

Bir servis sağlayıcıda tarayıcıya neleri tarayacağını belirtin: model sınıfları, adlandırılmış rotalar veya [site haritası kaynak kaydınızdaki](/tr/guide/sitemaps) her şey:

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

`Post` yerine `HasSEO` kullanan kendi modelinizi yazın. Bir model tarama sonucu görmek için en az bir kayıt gerekir. Rota hedefleri mevcut rotaların adlarını kullanmalıdır; yalnızca modelleri taramak istiyorsanız bu kaydı eklemeyin.

## Kurulumu doğrulayın {#verify-your-install}

Yapılandırma kontrolünü çalıştırın:

```bash
php artisan seo:doctor
```

Core ve Pro tablolarının bulunduğunu, uygulama URL'nizin doğru olduğunu ve tarama hedeflerinizin listelendiğini doğrulayın. Bildirilen düzeltmeleri uygulayın. Aşağıdaki işlemleri aynı süreçte çalıştıran komutları denerken `sync` kuyruğu uyarısı beklenir; canlı ortam taramalarını zamanlamadan önce bir kuyruk işçisi yapılandırın.

::: details Örnek sağlık kontrolü çıktısı
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor`, ağ çağrısı yapmadan ve gizli bilgileri yazdırmadan yapılandırmayı ve yakın tarihli çalıştırma geçmişini kontrol eder. Harici bir cron görevinin veya işçinin çalıştığını kanıtlayamaz. Kritik hatalar sıfırdan farklı çıkış kodu döndürür; uyarılar döndürmez. Makinece okunabilir sonuç için `--json` kullanın.
:::

## İlk taramanızı çalıştırın {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

İlk komut taramayı aynı süreçte tamamlar; bu ilk kontrol için kuyruk işçisi gerekmez. İkincisi son çalıştırmayı ve sonuçlarını gösterir. Kayıtlı hedefleriniz işlenmiş ve çalıştırma tamamlanmış olmalıdır; taramayı tamamlanmış saymadan önce başarısız hedefleri araştırın.

Bildirilen alanlardan birini düzeltip kaydedin ve taramayı tekrar çalıştırın. [Adım adım örnek](/tr/pro/walkthrough), eksik açıklama ve değişiklik raporu üzerinden bunu gösterir. [Teknik puan](/tr/pro/scoring), sıralama tahmini değil, tanı amaçlı bir sonuçtur.

## Panelsiz kullanım {#path-b-headless}

Motor, panel olmadan kullanıma hazırdır. [Artisan komutları](/tr/pro/headless) ile tarama yapabilir, sorunları inceleyebilir, yönlendirmeler oluşturabilir ve rapor üretebilirsiniz. Yönlendirme ve 404 middleware'leri varsayılan olarak otomatik kaydolur; ayarları `config/seo-pro.php` dosyasındadır.

Zamanlanmış işler için [Canlı ortam kurulumu](/tr/pro/production) rehberini izleyerek kuyrukları, işçileri, zamanlayıcıyı ve saklama sürelerini yapılandırın.

## Filament paneli ekleyin (isteğe bağlı) {#path-a-with-a-filament-panel}

Mevcut bir Filament 4 veya 5 paneli için aşağıdaki Pro eklentisini kaydedin. Uygulamanızda henüz panel yoksa önce arayüz paketlerini kurup bir panel oluşturun:

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

Bu işlem **SEO panosunu** (tüm hedefleri tarama eylemi, canlı ilerleme, tek tıklamayla yeniden tarama sunan sorun listesi), **yönlendirme yöneticisini** ve tek tıklamalı *Yönlendirme oluştur* eylemiyle **404 izlemeyi** ekler. `rankbeam/laravel-seo-filament` ayrıca kaynak formlarınıza [SEO alanları bölümünü](/tr/guide/filament) ekler.

## Sorun giderme {#troubleshooting}

| Sonuç | Sonraki adım |
|---|---|
| Composer kimlik bilgilerini reddediyor | `blog.rankbeam.dev` için lisans e-postasını ve anahtarı kontrol edin. Kimlik bilgilerini sürüm kontrolüne eklemeyin. |
| Doctor eksik tablolar bildiriyor | Core Hızlı başlangıç adımlarını tamamlayın, ardından uygulamayla aynı veritabanında `seo-pro:install` ve `migrate` çalıştırın. |
| Tarama hiçbir hedef işlemiyor | Servis sağlayıcı kaydınızı ve modelin kayıt içerdiğini kontrol edin. |
| Kuyruktaki tarama beklemede kalıyor | Yapılandırılmış kuyruk işçisini başlatın veya aynı süreçte kontrol için `--sync` kullanın. |
| Bir hedef başarısız oluyor | Yeniden taramadan önce çalıştırma ayrıntılarını, rota adlarını ve uygulama URL'sini kontrol edin. |
| Pano görünmüyor | `SeoProPlugin` eklentisini gerçekten kullandığınız panele kaydedin ve erişim gate'lerini kontrol edin. |

İşçi kurtarma ve sürekli işletim için [Canlı ortam kurulumu](/tr/pro/production) rehberine bakın.

## Lisans ve iadeler {#license}

İlk müşterilere özel lisans tek seferlik 179 €'dur ve müşteri projeleri dahil en fazla beş canlı projeyi, ömür boyu güncellemelerle kapsar. Bu projelerin geliştirme ve hazırlık ortamı kopyaları ayrıca sayılmaz. Kurulum/geçiş yardımı, 60 dakikalık kurulum görüşmesi ve duyurulan başlangıç paketi dahildir. 30 gün içinde makbuzunuz üzerinden veya valentinogoxhaj@gmail.com adresine yazarak koşulsuz tam iade isteyebilirsiniz. Tam iadeden sonra Pro kullanımını durdurun. Pro'yu lisanslı projeleriniz için değiştirebilirsiniz; ancak kaynak kodunu yayımlayamaz, bağımsız paket veya başlangıç kiti olarak yeniden satamazsınız. Tam lisans koşulları pakette bulunur.

Pro'yu müşteri projeleri dahil en fazla beş canlı projede kullanın. Bu projelerin geliştirme, hazırlık ve test kopyaları ayrıca sayılmaz. Ömür boyu güncellemeler gelecekteki Pro sürümlerini kapsar; sürekli kişisel uygulama geliştirme hizmetini kapsamaz.

Bir adet 60 dakikalık kurulum ve yapılandırma görüşmesi ile başlangıçtaki tek bir proje için meta veri taşıma dahildir. Taşıma, desteklenen kaynakları kapsar; başlamadan önce kapsamı birlikte netleştiririz ve uygulamaya özel değişiklikler ayrıca fiyatlandırılır. Başlangıç kurulumu, ücretsiz Core'da bulunan özelliklerle aynı projedeki llms.txt, robots.txt içindeki yapay zekâ tarayıcı kuralları ve botlara sunulan Markdown yanıtlarının incelenmesini ve yapılandırılmasını kapsar. Dahil olan yardımı planlamak için hello@rankbeam.dev adresine yazın.

Siparişiniz için satın alma sırasında gösterilen teklif geçerlidir.

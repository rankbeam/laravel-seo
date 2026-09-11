---
description: "Pro'yu ölçekli çalıştırın: ayrı kuyruklar, zamanlayıcı, yeniden deneme ve kurtarma politikası, saklama süreleri ve telemetri. Yaklaşık 900 sayfalık canlı kurulumun Filament'ten bağımsız yapısı."
---

# Canlı ortam kurulumu {#production-setup}

Pro'nun günlük işleri; site taramaları, bozuk bağlantı tarayıcısı, isteğe bağlı yönlendirme isabetlerini veritabanına aktarma ve 404 kayıtlarını temizleme, Laravel'in kuyruğu ve zamanlayıcısı üzerinde çalışır. Bu sayfa, ölçekli işletim için temel başvuru rehberidir: ayrı kuyruklar, zamanlayıcı, yeniden deneme ve kurtarma politikası, saklama süreleri ve hepsini izleyecek telemetri. Günde yaklaşık 20 bin ziyaret alan, yaklaşık 900 sayfalık bir canlı kurulumun yapısını yeniden uygulanabilecek şekilde anlatır.

Buradaki her şey **Filament'ten bağımsızdır**: motor, komutlar, kuyruklar ve telemetri panel olsa da olmasa da aynıdır. Filament kullanırsanız üzerine görünümler eklenir; işlerin zamanlanma veya işlenme biçimi değişmez.

[[toc]]

## Güvenli devreye alma sırası {#safe-rollout-order}

Bu adımları sırayla uygulayın; her biri sonrakine geçmeden doğrulanabilir:

1. **Kurun**: yapılandırmayı ve migration dosyalarını yayımlayıp çalıştırın:

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install`, `config/seo-pro.php` dosyasını ve Pro migration dosyalarını yayımlar, ardından `migrate` çalıştırır. Pro migration dosyaları **yalnızca yayımlanarak kullanılır** (paket bunları hiçbir zaman otomatik yüklemez); dolayısıyla yalnızca `composer require` çalıştırılmış bir kurulumu çalışan veritabanı şemasına dönüştüren adım budur. İşlem idempotenttir; istediğiniz zaman yeniden çalıştırabilirsiniz. Yayımlanmış dosyaların üzerine yazmak için `--force`, migration çalıştırmadan yayımlamak için `--no-migrate` ekleyin.

2. Bir servis sağlayıcıda (`AppServiceProvider::boot()`) **tarama hedeflerini kaydedin**:

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. Arka plan işlerini açmadan önce bağlantıları **doğrulayın**:

   ```bash
   php artisan seo:doctor
   ```

   Gösterilen bütün uyarıları giderin; her biri tam komut veya yapılandırma satırını içerir. CI'da `--json` ekleyin ve değişmeyen kontrol kimliklerini kullanın.

4. **Kuyrukları ve zamanlayıcıyı yapılandırın** (aşağıda), bir kuyruk işçisi ve `schedule:run` için cron girdisi kurun.

5. **İsteğe bağlı özellikleri en son açın**: bozuk bağlantı tarayıcısı, yapay zekâ yardımı ve Search Console varsayılan olarak kapalıdır. Tarayıcı için tabloların migration işlemi tamamlanmış olmalı (1. adım dosyaları zaten yayımladı) ve ayrı bir işçi bulunmalıdır (aşağıda).

**Pro 2.41.0'a yükseltme:** tarama işçilerini duraklatın, `php artisan vendor:publish --tag=seo-pro-migrations --force` ile migration dosyalarını yayımlayın, `php artisan migrate` çalıştırın, ardından işçileri yeniden başlatıp `php artisan seo:doctor` çalıştırın. Yeni `seo_scan_target_completions` tablosu ve `seo_scan_runs.target_tracking` sütunu zorunludur. Çalıştırma/hedef bazında tamamlanma kayıtları, yinelenen son durumların sayaçları şişirmesini önler; ilk kabul edilen sonuç geçerlidir. Henüz hedef işlememiş eski kuyruk çalıştırmaları devam eder. Yükseltmeden önce kısmen işlenmiş çalıştırmalar geçmişi korur, ancak sonraki teslimde yeni tarama talimatıyla kapanır. Deneme hakkı tükenmiş hedefleri yeni bir çalıştırmada yeniden deneyin. Geri dönüş için migration işlemini geri almadan önce işçileri durdurup kodu geri alın; sonraki taramaları da geri almanız gerekiyorsa yükseltme öncesi veritabanı yedeğini saklayın.

## Her iş yükü için ayrı kuyruklar {#dedicated-queues-per-workload}

Uzun bir tarama, kullanıcıya yönelik işlerin (e-posta, bildirimler) önünde beklememelidir. Her SEO iş yüküne kendi kuyruğunu ve işçisini ayırın.

Sayfa tarama hattı ve bozuk bağlantı tarayıcısı ayrı ayrı yapılandırılabilir kuyruk kullanır:

| İş yükü | Yapılandırma | Ortam değişkeni | Varsayılan kuyruk |
|---|---|---|---|
| Sayfa içi tarama işleri | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | varsayılan kuyruk |
| Bozuk bağlantı tarama işleri | `seo-pro.broken_links.queue.name` (+ `.connection`) | `SEO_PRO_BROKEN_LINKS_QUEUE` (+ `_CONNECTION`) | `seo-broken-links` |

### Redis örneği (canlı ortam yapısı) {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Her kuyruk için bir işçi çalıştırın; her biri ayrı bir süreç / Supervisor programıdır:

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

Tarayıcı işçisinin `--timeout` değeri, `seo-pro.broken_links.batch.hard_time_budget_seconds` (varsayılan 180) ile HTTP zaman aşımının toplamından büyük olmalıdır; böylece grup işlemi kayıtlar güncellenirken sonlandırılmaz. İş kendi `$timeout` değerini bu toplama ayarlar; işçi seçeneğini de buna uyumlu tutun. Tarayıcı için `--tries=1` kullanın: sonlanan işi sonraki devam işi (veya `seo-pro:broken-links-recover`) geri alır; kuyruk düzeyinde yeniden denemeye gerek yoktur.

`seo:doctor`, her iş yükünün kuyruğunu bildirir ve biri `sync` olarak çözümlendiğinde uyarır; bu durumda iş aynı süreçte çalışıp süreci engeller.

## Zamanlayıcı {#scheduler}

Laravel 11, 12 ve 13'te zamanlama **`routes/console.php`** dosyasındadır. `app/Console/Kernel.php` içindeki `schedule()` metodu yalnızca Laravel 10'dan yükseltilmiş uygulamalarda bulunur; sizde hâlâ varsa aynı girdileri oraya koyun. Zamanlayıcının her dakika çalışması için tek bir sistem cron girdisi ekleyin:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Ardından her yinelenen komutu önerilen sıklıkla kaydedin:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Önerilen sıklıklara genel bakış:

| Komut | Sıklık | Amaç |
|---|---|---|
| `seo:sitemap` | günlük | Site haritasını güncel içerikten yenilemek |
| `seo-pro:scan` | haftalık (içerik hızla değişiyorsa günlük) | Her hedefi yeniden denetlemek |
| `seo-pro:scan-recover` | saatlik | Sonlanan işçi nedeniyle yarım kalan çalıştırmaları geri almak |
| `seo-pro:scan-prune` | günlük | Tarama çalıştırmalarının saklama süresini uygulamak |
| `seo-pro:redirects-flush-hits` | yalnızca `redirects.hits.flush_immediately=false` olduğunda her 5 dakika | Önbellekte biriken isabet sayaçlarını veritabanına aktarmak |
| `seo-pro:404-prune` | günlük | 404 günlüğünü saklama süresine ve satır sınırına göre temizlemek |
| `seo-pro:404-recheck` | günlük | Açık 404 yollarını yeniden istemek; kaynağında düzeltilip artık 200 döndürenleri düzelmiş işaretlemek |
| `seo-pro:broken-links-scan` | haftalık | Bozuk bağlantılar için yeniden taramak (doğrulama taramalar arasındadır) |
| `seo-pro:broken-links-recover` | saatlik | Sonlanan işçi nedeniyle yarım kalan bağlantı taramalarını geri almak |
| `seo-pro:broken-links-prune` | günlük | Bağlantı tarayıcısının saklama sürelerini uygulamak |

`seo-pro:scan` ve `seo-pro:broken-links-scan` yalnızca işleri **kuyruğa alır**; işi işçi yapar. Kurtarma/temizleme komutları aynı süreçte çalışır ve düşük maliyetlidir.

::: tip Taramalar arasında bozuk bağlantı doğrulaması
Bir bağlantı, yalnızca `seo-pro.broken_links.mark_broken_after_failures` kadar **ardışık tarama** ona ulaşamazsa bozuk işaretlenir; herhangi bir başarı sayacı sıfırlar. Bu nedenle tarama tek seferlik değil, zamanlanmıştır: tek bir geçici kesinti bağlantıyı bozuk işaretlemez. Varsayılan 3 eşiğinde, haftalık taramalar ilk başarısız gözlemden yaklaşık iki hafta sonra veya bozulmadan itibaren yaklaşık üç haftaya kadar sürede doğrular. Daha hızlı doğrulama için sıklığı artırın veya eşiği düşürün.
:::

## Grup ayarları (bozuk bağlantı tarayıcısı) {#batch-tuning-broken-link-crawler}

Tarama, sınırları belirli ve kendi devam işini yeniden kuyruğa alan birçok iş üzerinden yürür. Varsayılanlar sınırlıdır; sitenizin ve kontrol ettiğiniz sunucuların kapasitesine göre ayarlayın. Ayarlar `seo-pro.broken_links` içindedir:

| Anahtar | Varsayılan | Sınırladığı değer |
|---|---|---|
| `max_pages_per_run` | `2000` | Bir çalıştırmada getirilen sayfalar. `null` = sınırsız kullanımın açıkça seçilmesi (asla varsayılan değildir) |
| `max_links_per_page` | `200` | Sayfa başına kontrol edilen bağlantılar |
| `max_total_links` | `null` | Çalıştırma genelindeki bağlantı kontrolleri için isteğe bağlı toplam sınır |
| `batch.max_pages_per_job` | `50` | Kuyruk işi başına sayfa sayısı |
| `batch.max_links_per_job` | `1500` | Kuyruk işi başına bağlantı kontrolü sayısı |
| `batch.hard_time_budget_seconds` | `180` | Bundan sonra iş **yeni istek başlatmaz** ve bir devam işini yeniden kuyruğa alır |
| `batch.dispatch_delay_seconds` | `1` | Devam işleri arasındaki gecikme |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | İstek başına sınırlar |
| `http.max_response_bytes` | `seo-pro.http.max_response_bytes` değerini devralır | Sayfa ve hedef kontrolü yanıt gövdeleri için akış sırasında uygulanan sınır |
| `seed.max_response_bytes` | tarayıcı/ortak HTTP sınırını devralır | Başlangıç kaynakları alınırken getirilen ham site haritası XML / `.gz` baytları |
| `seed.max_inflated_bytes` | başlangıç kaynağı/tarayıcı/ortak sınırı devralır | `.gz` site haritasından kabul edilen açılmış baytlar |
| `http.per_host_delay_ms` | `0` | Kontroller arasındaki yükü azaltan gecikme (`internal_and_external` için artırın) |

`batch.hard_time_budget_seconds` değerini tarayıcı işçisinin `--timeout` değerinin yeterince altında tutun. Devam eden bir istek ortasında kesilemez; `http.timeout` ile sınırlanır. Bu nedenle işçi zaman aşımı = zaman bütçesi + HTTP zaman aşımı + güvenlik payıdır.

`internal_and_external` taraması için SsrfGuard'ın dış kontrolleri kabul etmesi amacıyla `seo-pro.http.scope` (veya `seo-pro.http.allowed_hosts`) kapsamını genişletin; üçüncü taraf sunucuya çok sık istek gitmemesi için `http.per_host_delay_ms` değerini artırın. Tarama dış sunucuları kapsıyor ancak koruma kapsamı her kontrolü engelliyorsa `seo:doctor` uyarı verir.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Her kuyruk için bir program. Örnek `/etc/supervisor/conf.d/app-workers.conf`:

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

`stopwaitsecs`, işçinin `--timeout` değerinden büyük olmalıdır; böylece kontrollü yeniden başlatma, işi grubun ortasında sonlandırmaz.

### Horizon {#horizon}

Horizon kullanıyorsanız `config/horizon.php` içinde her iş yükü için bir supervisor tanımlayın ve süreçleri Supervisor yerine onun yönetmesini sağlayın:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Yeniden deneme ve hata yönetimi {#retry-failure-handling}

Tarama hedefi işi kendi yeniden deneme politikasını yapılandırmadan alır; işçinin `--tries` seçeneğine **dayanmaz**:

| Anahtar | Varsayılan | Anlamı |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Hedef işi başına deneme sayısı |
| `seo-pro.scan.backoff` | `30` | Denemeler arasındaki saniye sayısı |
| `seo-pro.scan.timeout` | `300` | Hedef işi başına zaman aşımı (çakışma kilidi zaman aşımı + 60'ta sona erer) |

Yeniden deneme hakkı tükenen hedef işi, hedefi **başarısız** kaydeder ve çalıştırma yine sona erer (`partial` veya `failed`); yönetilen hedef hataları çalıştırmayı `running` durumunda bırakmaz. Kayıtlar güncellenmeden sonlandırılan bir işçi için aşağıdaki kurtarma taraması yine gerekir. Hatalar standart `failed_jobs` tablosuna yazılır; bunları her zamanki şekilde yönetin:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Bu tablonun sınırlı kalması için SEO girdilerinin yanında `queue:prune-failed` komutunu da zamanlayın:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

Bozuk bağlantı taraması `--tries=1` kullanır: sonlanan iş, kendi sonraki devam işi (iş sahipliği canlılık sinyali eskir) veya `seo-pro:broken-links-recover` tarafından geri alınır. Kuyruk yeniden denemeleri yalnızca işi tekrarlar.

## Kurtarma {#recovery}

İlerleme kayıtlarının kendi kendini düzeltemediği durum, işçinin iş ortasında sonlanmasıdır. Bunu iki kurtarma taraması kapatır; ikisini de **saatlik** zamanlayın:

- `seo-pro:scan-recover`: `seo-pro.scan.recovery.stuck_scan_timeout_hours` süresince (varsayılan 2) ilerlemeyen sayfa içi tarama çalıştırmalarını başarısız işaretler.
- `seo-pro:broken-links-recover`: iş sahipliği canlılık sinyali eskimiş bağlantı taramalarını (`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, varsayılan 2) geri alır; başarısız işaretler ve kapsam başına tek etkin çalıştırma yerini serbest bırakır.

`seo:doctor` bunu **yakın tarihli canlılık sinyali kanıtı** olarak gösterir: tarama kullanılmaya başladıktan sonra takılan çalıştırmaları bildirip kurtarma komutuna yönlendirir. Cron görevinizin gerçekten çalıştığını kanıtlayamaz; hiçbir komut kanıtlayamaz. Çalıştırma geçmişinin gösterdiğini bildirir.

## Saklama süreleri {#retention}

Tabloları sınırlı tutun. Varsayılanlar şöyledir; hepsi `seo-pro.*` içindedir ve `null` ilgili temizlemeyi kapatır:

| Veri | Yapılandırma | Varsayılan | Komut |
|---|---|---|---|
| Tarama çalıştırmaları (+ sorunlar) | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| 404 günlüğü | `monitor_404.retention_days` (+ `max_rows` `10000`) | `90` | `seo-pro:404-prune` |
| Bağlantı tarama çalıştırmaları | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Çözülmüş bulgular | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## İşletim telemetrisi {#operational-telemetry}

Tamamlanan her sayfa içi tarama **ve** bozuk bağlantı taraması, günlükleme sistemi üzerinden yapılandırılmış bir tamamlanma satırı üretir; böylece panel olmadan da ölçüm geçmişiniz olur. Veri yalnızca sayıları ve süreleri içerir; URL, gövde, üstbilgi veya ziyaretçi verisi içermez:

| Ölçüm | Sayfa taraması | Bağlantı taraması |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (SSRF nedeniyle reddedilen hedefler) | — | ✓ |
| `transient_failures` (sonraki taramada tekrar kontrol edilen ağ hataları) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (kuyruğa alınma → ilk grup) | ✓ | ✓ |

`seo-pro.telemetry` içinde yapılandırın:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Satırları uygulama günlüklerine karıştırmadan gerçek bir hedefe (Loki / Datadog / CloudWatch) göndermek için `channel` değerini ayrı bir günlük kanalına yöneltin:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Daha kapsamlı işleme için olayları doğrudan dinleyin; her biri aynı `metrics()` verisini sunar:

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

Telemetri mümkün olduğu ölçüde çalışır: yanlış yapılandırılmış bir kanal hiçbir zaman taramayı başarısız kılamaz.

## Filament'ten bağımsız dağıtım {#filament-independent-deployment}

Bu sayfadaki hiçbir işlem panel gerektirmez. Motor, bütün komutlar, kuyruklar, zamanlayıcı, kurtarma, saklama ve telemetri panelsiz kullanımda da aynıdır. Filament paneli (`SeoProPlugin`) yalnızca **görünümler** ekler: canlı tarama ilerlemesi, sorun tablosu, yönlendirme CRUD işlemleri, 404 izleme, bozuk bağlantı panosu. Motoru dağıtıp CLI ve zamanlayıcıyla işletin; daha sonra panel ekleyebilir veya hiç eklemeyebilirsiniz. Bunun için migration ya da işleri yeniden yapmak gerekmez. Tam komut başvurusu için [Panelsiz kullanım](/tr/pro/headless) sayfasına bakın.

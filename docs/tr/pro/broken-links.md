---
description: "Çözümlenemeyen bağlantıları kaydeden sınırlı, devam edebilir tarayıcı: tek tıklamayla yönlendirilerek düzeltilebilen ölü iç rotalar ve isteğe bağlı bozuk dış bağlantılar. Varsayılan olarak kapalıdır."
---

# Bozuk bağlantı tarayıcısı {#broken-link-crawler}

Sitenizi dolaşan, her sayfadaki bağlantıları izleyen ve çözümlenemeyenleri kaydeden **sınırlı, kaldığı yerden devam edebilir tarayıcı**: bozuk **iç** bağlantılar (kendi sunucunuzdaki ölü rota; tek tıklamayla yönlendirme olarak düzeltilebilir) ve isteğe bağlı bozuk **dış** bağlantılar. **Varsayılan olarak kapalıdır**.

Tasarımı üç özellik belirler:

- **Sınırlı ve devam edebilir.** Tarama, her biri az sayıda sayfayla sınırlı birçok küçük kuyruk işi üzerinden yürür. Çalıştırma tamamlanana veya sınırlarına ulaşana kadar devam işi yeniden kuyruğa alınır. Bütün çalıştırma da sınırlıdır: varsayılan 2000 sayfa; `null`, sınırsız kullanımın açıkça seçilmesidir ve asla varsayılan değildir. Diğer grup/zaman sınırları yine geçerlidir. Sınırları ve gecikmeleri siteye ve sunucu kapasitesine uygun tutun.
- **Varsayılan olarak güvenli.** Varsayılan kapsam `internal_only` değeridir: yalnızca kendi sunucunuzdaki bağlantılar kontrol edilir, üçüncü taraf istekleri yapılmaz. İç veya dış her istek ortak **SsrfGuard** üzerinden geçer: şema izin listesi, sunucu kapsamı ve özel adres reddi. Dış bağlantı kontrolü ayrıca açılır ve yine korumalıdır.
- **SEO puanından ayrı.** Bulgular kendi tablolarında bulunur; `seo_scan_issues` veya 0–100 puanına asla yazmaz. Bir sayfanın dışarıya giden bağlantıları bozuk olsa da olmasa da puanı değişmez. Bozuk bağlantılar, ayrı izlenen bir işletim konusudur.

## Neler sunar {#what-you-get}

Filament panosunda, yalnızca özellik açıkken:

- **Bozuk bağlantı özeti**: bulgu tablosuna bağlantıyla açık bozuk bağlantı sayıları (iç ve dış) ve son tarama.
- **Bozuk bağlantı taraması**: süren taramanın canlı ilerlemesi; taranan sayfalar, kontrol edilen bağlantılar, bulunan bozuk bağlantılar.
- **Tarama başına bozuk bağlantı**: yakın tarihli bağlantı taramalarındaki eğilim.
- **Bulgu yönetimi**: bozuk her `source → target` bağlantısı; filtrelenebilir, iç bağlantılar yönlendirme olarak düzeltilebilir.

Panelsiz kullanımda aynı veriye `seo-pro:broken-links-*` komutlarıyla erişilir.

## Neden varsayılan olarak kapalı {#why-it-s-off-by-default}

Pasif çıktı üretme ve puanlama özelliklerinin aksine tarayıcı **ağ istekleri yapar** ve biraz altyapı gerektirir. Bu nedenle açılması bilinçli seçimdir; kurulumda sessizce başlamamalıdır:

- İki ana tablosu, bütün Pro migration dosyaları gibi **yalnızca yayımlanarak kullanılır**. Arayüz sorgulamadan önce migration işlemleri çalıştırılmalıdır. Türlere ayrılmış incelemeler ayrıca `seo_broken_link_inspections` kullanır.
- Tarama **ayrı bir kuyruğa alınır** ve çalışmak için **işçi** gerekir; işçisiz tarama ilerlemez.
- Doğrulama **taramalar arasında** yapılır (aşağıda). Bu nedenle açıldığı anda sonuç üretmek yerine haftalar boyunca **zamanlanmış** çalışmak üzere tasarlanmıştır.

## Kurulum {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Ardından migration işlemlerini çalıştırın. `seo-pro:install`, bütün Pro migration dosyalarını yayımlayıp çalıştırır; idempotenttir ve tekrar çalıştırılabilir:

```bash
php artisan seo-pro:install
```

Taramanın kuyruğu için **ayrı bir işçi** çalıştırın. `seo-broken-links` özellikle ayrı kuyruktur; uzun tarama, kullanıcıya yönelik işlerin önünde beklememelidir:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Bağlantıları doğrulayın. `seo:doctor`, etkinleştirme seçeneğini, tabloları ve tarama kuyruğunun gerçek bir bağlantıya (`sync` olmayan) çözümlenip çözümlenmediğini kontrol eder; her biri için tam düzeltme adımını verir:

```bash
php artisan seo:doctor
```

Tam çoklu kuyruk yapısı (Redis, Supervisor, ayrı bağlantılar) ve grup ayarları için [Canlı ortam kurulumu](/tr/pro/production) sayfasına bakın.

## Tarama çalıştırmak {#running-a-crawl}

Panodaki **Şimdi tara** eyleminden veya panelsiz olarak başlatın:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

İki komut da taramayı yalnızca **kuyruğa alır**; asıl işi işçi yapar.

## Bir bağlantı nasıl işaretlenir {#how-a-link-gets-flagged}

Bağlantı, yalnızca `seo-pro.broken_links.mark_broken_after_failures` kadar **ardışık tarama** ona ulaşamazsa bozuk bildirilir. Başarılı herhangi bir kontrol sayacı sıfırlar; varsayılan **3**'tür. Tek bir geçici kesinti bağlantıyı işaretlemez. Bu nedenle tarama tek seferlik değil, **zamanlanmış** çalışmalıdır. Haftalık sıklık ve varsayılan eşikte üç başarısız tarama bağlantıyı doğrular: ilk gözlemden yaklaşık iki hafta sonra veya bozulmasından itibaren yaklaşık üç haftaya kadar sürede. Daha hızlı doğrulama için sıklığı artırın veya eşiği düşürün.

## Türlere ayrılmış bağlantı incelemeleri {#typed-link-inspections}

“Ulaşılabilir mi?” kontrolünün ötesinde, taranan her bağlantı **türlere ayrılmış incelemelerden** geçer. Bu URL düzeni sınıflandırması; sondaki eğik çizgi tutarsızlığını, sorunlu kodlamaları, yönlendirme zincirlerini, `javascript:` href değerlerini, bozuk sayfa içi bağlantı hedeflerini, açıklayıcı olmayan bağlantı metnini ve daha fazlasını işaretler. Her inceleme sabit **önem derecesi** taşır: `critical` · `warning` · `notice`. [Tarama sorunlarıyla](/tr/pro/scan-issues) aynı söz varlığını kullanır; tek CI geçiş koşulu ikisini de kapsar. Her bağlantı taraması için `seo_broken_link_inspections` içinde kaydedilir. Birkaç ardışık taramadan sonra doğrulanan bozuk bağlantı *bulgusundan* farklı olarak inceleme, çalıştırma başına anlık görüntüdür: **ilk taramada, hemen** görünür. CI geçiş koşulunun ihtiyacı tam olarak budur.

### İnceleme başvurusu {#inspection-reference}

| İnceleme | Önem derecesi | İşaretlediği durum | Kapsam |
| --- | --- | --- | --- |
| `broken_link` | critical | Hedef HTTP ≥ 400 döndürdü | her bağlantı |
| `redirect_chain` | notice · warning | Hedef yalnızca yönlendirmeyle çözülüyor; `redirect_chain_warning_hops` aşıldığında `warning` | her bağlantı |
| `link_unreachable` | notice | Bu taramada ulaşılamadı (ağ hatası, zaman aşımı, engelleme); geçici olabilir | her bağlantı |
| `insecure_link` | warning | `https` sitesinde `http://` bağlantısı (aktarım güvenliği düşürülüyor) | her bağlantı |
| `trailing_slash` | notice | İç yol, bildirilen sondaki eğik çizgi kuralına uymuyor (**`trailing_slash` ayarlanmadıkça kapalı**) | iç bağlantılar |
| `double_slash_url` | warning | İç yol `//` içeriyor (boş bölüm) | iç bağlantılar |
| `duplicate_query_param` | notice | Sorgu anahtarı yineleniyor (`?a=1&a=2`); `key[]` dizi sözdizimi istisnadır | iç bağlantılar |
| `non_ascii_url` | notice | İç yolda kodlanmamış ASCII dışı karakterler var | iç bağlantılar |
| `uppercase_url` | notice | İç yolda büyük harfler var; ayrı sunulan büyük/küçük harf varyantlarını inceleyin | iç bağlantılar |
| `underscore_in_url` | notice | İç yolda alt çizgi var; SEO için tercih edilen ayraç kısa çizgidir | iç bağlantılar |
| `javascript_link` | warning | Bağlantı `javascript:` href kullanıyor; normal taranabilir hedef değil | her bağlantı öğesi |
| `missing_fragment` | warning | Aynı sayfadaki `#fragment` için eşleşen `id`/`name` yok | aynı sayfa |
| `non_descriptive_anchor` | notice | Bağlantı metni genel (“buraya tıklayın”, “devamını okuyun”) veya çıplak URL | her bağlantı öğesi |
| `absolute_internal_link` | notice | İç bağlantı, köke göreli yol yerine mutlak URL olarak yazılmış | iç bağlantılar |

Düzen incelemeleri (sondaki eğik çizgi, büyük/küçük harf, kodlama, çift eğik çizgi…) yalnızca **iç** bağlantılara uygulanır; dış sitenin URL biçimini denetlemek sizin göreviniz değildir. Yönlendirme, bozukluk, ulaşılamama ve güvensizlik incelemeleri her bağlantıya uygulanır. İlk çalıştırmada gereksiz bildirim olmaması için kendi framework rotalarınıza ve statik varlıklarınıza bağlantılar atlanır; aşağıdaki `exclude_paths` / `exclude_extensions` ayarlarına bakın.

Her bağlantı normalleştirilmiş biçimi yerine **tam yazıldığı URL'den** getirilir; yalnızca `#fragment` kaldırılır. Böylece `/about/ → /about` gibi sunucu tarafı kanonik yönlendirme gerçekten gözlemlenir ve normalleştirmeyle önceden ortadan kaldırılmak yerine `redirect_chain` olarak görünür. Sayfadaki bağlantının farklı yazılmış her biçimi incelenir; `/page#ok` ve `/page#missing` (veya `/a//b` ve `/a/b`) yalnızca ilki değil, ayrı ayrı değerlendirilir. Alttaki bozuk bağlantı *bulgusu*, hedefin bütün takma biçimlerini yine tek kimlikte birleştirir. İnceleme satırları `(page, target, inspection)` başına kaydedilir; dolayısıyla birkaç bozuk sayfa içi bağlantı hedefi bulunan bir hedef, her bağlantı için ayrı satır değil, örnek içeren tek `missing_fragment` satırı üretir.

### Sınıflandırmayı ayarlamak {#tuning-the-taxonomy}

Her şey `seo-pro.broken_links.inspections` altındadır:

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

Sınıfını `rules` listesinden çıkararak **bir kuralı kapatın**; `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false` ile **bütün sınıflandırmayı kapatın**. İki kuralı baştan bilmek yararlıdır:

- `trailing_slash`, **bir biçim kuralı bildirene kadar kapalıdır** (`'always'` / `'never'`). Çünkü hem `/x` hem `/x/` için `200` döndüren sitede işaretlenecek “yanlış” biçim yoktur. Sunucu yönlendirmeyle kanonik biçim belirliyorsa bu zaten `redirect_chain` olarak görünür.
- `absolute_internal_link`, mutlak URL olarak yazılmış **her** iç bağlantıda tetiklenir. Sitenizin kuralı mutlak iç URL üretmekse çok sayıda zararsız, `notice` düzeyinde satır oluşur. Susturmak için `rules` listesinden çıkarın.

## Sürekli entegrasyon {#continuous-integration}

Bağlantı taraması da [SEO denetimi](/tr/pro/scan-issues) de **derlemeyi başarısız kılabilir** ve **rapor dosyası yazabilir**. Böylece Rankbeam bir panodan kalite geçiş koşuluna dönüşür. `--fail-on-error`, `critical` düzeyine eşlenir (bozuk bağlantı, kritik sorun); `--fail-on-warning`, `critical` **veya** `warning` durumunda başarısız olur. Ayrı bir “error” düzeyi yoktur.

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` rapor dosyasını yazar; dizin verilirse dosya adı türetilir. `--format`, `json` (varsayılan), `md` veya `html` olabilir. İş hattında ayrıştırılacak biçim JSON'dur; HTML, çalıştırmaya eklenebilen bağımsız sayfadır.

### GitHub Actions {#github-actions}

Tarayıcı sayfalarınızı HTTP üzerinden getirir. Bu nedenle CI, onu erişebileceği içeriğe yöneltmelidir: aşağıdaki gibi yerel sunulan uygulama veya `SEO_PRO_BROKEN_LINKS_BASE_URL` üzerinden hazırlık ortamı URL'si. Taramanın başlangıç kaynağı bulabilmesi için modelleriniz/site haritanız kayıtlı olmalıdır.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## Zamanlama {#scheduling}

Tarama ve bakım işlerini `routes/console.php` içinde kaydedin:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Komut başvurusu {#command-reference}

| Komut | İşlevi |
| --- | --- |
| `seo-pro:broken-links-scan` | Sınırlı, devam edebilir taramayı kuyruğa alır (`--scope=internal_only\|internal_and_external`, ek başlangıç kaynakları için `--url=*`) |
| `seo-pro:broken-links-status` | Son bağlantı taramasının özeti, açık bozukluk bulguları ve bu çalıştırmanın inceleme sayıları; **CI geçiş koşulları** (`--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html`) |
| `seo-pro:broken-links-cancel` | Çalışan/kuyruktaki taramayı iptal eder (`{run?}`; varsayılan son etkin çalıştırmadır) |
| `seo-pro:broken-links-recover` | Sonlanan işçinin yarım bıraktığı taramaları başarısız işaretler (eskimiş iş sahipliği) |
| `seo-pro:broken-links-prune` | Tarayıcının saklama politikasını uygular (eski çalıştırmalar ve çözülmüş bulgular) |

## Ayarlama {#tuning}

Taramanın sınırları; çalıştırma başına sayfa, sayfa başına bağlantı, iş başına üst sınırlar, kesin zaman bütçesi ve sunucu başına bekleme süreleri, `seo-pro.broken_links` içindedir. Varsayılanlar temkinli ve sonludur; artırmadan önce [Canlı ortam kurulumundaki grup ayarları tablosuna](/tr/pro/production) bakın.

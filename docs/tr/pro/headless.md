---
description: "Pro'nun tarama, yönlendirme ve 404 günlükleme özelliklerinin tümü Filament olmadan çalışır. Pro'yu tamamen Artisan üzerinden yönetmek için komut başvurusu."
---

# Panelsiz kullanım {#headless-usage}

Pro'nun tarama, yönlendirme ve 404 günlükleme özelliklerinin tümü panelsiz çalışır: motorda bulunur ve Filament gerektirmez. Panel yalnızca yönetim arayüzüdür; bu komutlar onun panelsiz karşılığıdır.

## Komut başvurusu {#command-reference}

### Kurulum ve sağlık kontrolü {#setup-health-check}

| Komut | İşlevi |
|---|---|
| `seo-pro:install` | `config/seo-pro.php` dosyasını ve Pro migration dosyalarını yayımlayıp çalıştırır, ardından sonraki adımları gösterir (`--no-migrate`, `--force`) |
| `seo:doctor` | Tek seferlik sağlık kontrolü: uygulama URL'si, core ve Pro tabloları, tarama hedefleri, site haritası, iş yükü başına kuyruklar, isteğe bağlı özellikler ve işletim sağlığı. Her uyarı tam düzeltme adımını içerir (izleme için `--json`) |

`seo-pro:install`, belgelenmiş kurulum yoludur. Pro migration dosyaları yalnızca yayımlanarak kullanılır; paket bunları hiçbir zaman otomatik yüklemez. Dolayısıyla yalnızca `composer require` çalıştırılmış kurulumu çalışan şemaya dönüştüren şey kurulum komutudur. İdempotenttir; istediğiniz zaman tekrar çalıştırabilirsiniz.

`seo:doctor` ağ çağrısı yapmaz ve gizli değerleri asla göstermez; yapay zekâ kontrolü yalnızca yapılandırılmış anahtar değişkeninin *atanmış* olup olmadığını bildirir. Yapılandırmayı ve yakın tarihli çalıştırma geçmişini doğrular; harici cron görevinin veya işçinin gerçekten çalıştığını kanıtlayamaz. Yalnızca zorunlu tablonun eksikliği gibi kritik hatalarda sıfırdan farklı kodla çıkar; uyarı içeren localhost geliştirme ortamı yine başarılı çıkar. `--json`, her kontrolü eşleştirmek için değişmeyen bir `id` verir. [Kurulumun](/tr/pro/installation) hemen ardından ve CI'da çalıştırın.

### Tarama {#scanning}

| Komut | İşlevi |
|---|---|
| `seo-pro:scan` | Kayıtlı her hedefin tam taramasını kuyruğa alır (aynı süreçte çalıştırmak için `--sync`; **CI geçiş koşulları** `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=json\|md\|html`, `--sync` gerektirir) |
| `seo-pro:scan-status` | Son çalıştırmanın özeti ve önem derecesi en yüksekten başlayan açık sorunlar (`--limit=20`, `--severity=critical\|warning\|notice`) |
| `seo-pro:scan-recover` | Sonlanan kuyruk işçisinin yarım bıraktığı çalıştırmaları başarısız işaretler |
| `seo-pro:scan-prune` | Saklama süresini aşmış tamamlanan çalıştırmaları ve sorunlarını siler |

### Bozuk bağlantı tarayıcısı {#broken-link-crawler}

Varsayılan olarak kapalıdır. `seo-pro.broken_links.enabled` seçeneğini açıp iki tablosunun migration işlemini çalıştırın; `seo-pro:install` bunları yayımlar. Tarama, sınırları belirli kuyruk işleri üzerinden yürür; kuyruğu için ayrı bir işçi çalıştırın. Ayarlar için [Canlı ortam kurulumu](/tr/pro/production) sayfasına bakın.

| Komut | İşlevi |
|---|---|
| `seo-pro:broken-links-scan` | Sınırlı, kaldığı yerden devam edebilen taramayı kuyruğa alır (`--scope=internal_only\|internal_and_external`, ek başlangıç kaynakları için `--url=*`) |
| `seo-pro:broken-links-status` | Son bağlantı taramasının özeti, açık bulgular ve bu çalıştırmanın [türlere ayrılmış incelemeleri](/tr/pro/broken-links#typed-link-inspections); **CI geçiş koşulları** (`--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=`) |
| `seo-pro:broken-links-cancel` | Çalışan/kuyruktaki taramayı iptal eder (`{run?}`; varsayılan, son etkin çalıştırmadır) |
| `seo-pro:broken-links-recover` | Sonlanan işçinin yarım bıraktığı taramaları başarısız işaretler (eskimiş iş sahipliği) |
| `seo-pro:broken-links-prune` | Tarayıcının saklama politikasını uygular (eski çalıştırmalar ve çözülmüş bulgular) |

### Yönlendirmeler ve 404'ler {#redirects-404s}

| Komut | İşlevi |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Yönlendirme kuralı oluşturur (`--code=301`, `--regex`, `--no-preserve-query`, `--note=`) |
| `seo-pro:404-list` | En çok isabet alandan başlayarak kaydedilmiş 404'leri gösterir (`--status=new\|ignored\|redirected\|all`, `--limit=20`) |
| `seo-pro:redirects-flush-hits` | `redirects.hits.flush_immediately=false` olduğunda önbellekte biriktirilen yönlendirme isabet sayaçlarını veritabanına yazar |
| `seo-pro:404-prune` | Eski 404 kayıtlarını siler ve satır sınırını uygular |

### Sayfa içi kontrol listesi {#on-page-checklist}

| Komut | İşlevi |
|---|---|
| `seo-pro:checklist {model} {id}` | Bir model için anahtar kelimeye dayalı geçti/uyarı/başarısız kontrol listesi (`--json`, `--strict`, `--locale=`); [Sayfa içi kontrol listesine](/tr/pro/on-page-checklist) bakın |

Aynı kontrol listesine `SeoPro::checklistFor($model)` ile erişilebilir. Anahtar kelime yerleşimi, uzunluk, görseller ve iç bağlantılara yönelik editoryal geri bildirim döngüsüdür; [SEO puanı](/tr/pro/scoring) **değildir**.

### Search Console (salt okunur) {#search-console-read-only}

| Komut | İşlevi |
|---|---|
| `seo-pro:search-console` | Açık sorunları **ve** arama trafiği olan sayfalar, en büyük kaçırılan fırsat önce (varsayılan `--view=attention`) |
| `seo-pro:search-console --view=pages` | Gösterim/tıklama/TO/konuma göre en iyi sayfalar |
| `seo-pro:search-console --view=queries` | En iyi sorgular (`--days=`, `--limit=`, `--json`) |

Aynı ölçümlere `SeoPro::searchConsole()` ile erişilebilir; [Search Console](/tr/pro/search-console) sayfasına bakın. Varsayılan olarak kapalıdır ve kesinlikle salt okunurdur.

### Yapay zekâ yardımı {#ai-assist}

| Komut | İşlevi |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | JSON olarak başlık/açıklama önerileri (`--field=title\|description\|all`); [Yapay zekâ yardımına](/tr/pro/ai-assist) bakın |
| `seo-pro:ai-suggest --issue={id}` | Bir tarama sorunu için sade dille düzeltme açıklaması; JSON olarak |

### Bir 404'ü tek adımda çözmek {#resolving-a-404-in-one-step}

`--from-404={path}`, 404 izleyicisinin tek tıklamalı *Yönlendirme oluştur* eyleminin panelsiz karşılığıdır: kuralı oluşturur **ve** eşleşen günlük kaydını yönlendirilmiş işaretleyerek yeni kurala bağlar:

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

Komut, Filament formuyla aynı doğrulayıcıları çalıştırır. Geçersiz regex kalıpları, aşırı büyük değerler ve izin listesinde olmayan dış hedefler, herhangi bir şey yazılmadan reddedilir.

## Önerilen zamanlama {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Yukarıdaki yinelenen her komut için önerilen sıklık [Canlı ortam kurulumu](/tr/pro/production) rehberindedir. Aynı rehber kuyruk yapısını, işçi yapılandırmasını, yeniden deneme/kurtarma politikasını, saklama sürelerini ve tamamlanan her çalıştırmanın ürettiği yapılandırılmış **telemetriyi** (getirilen sayfalar, kontrol edilen bağlantılar, engellenen URL'ler, süre, kuyruk gecikmesi) açıklar.

## Filament arayüzü ne için gerekir? {#what-needs-the-filament-ui}

İşlevsel olarak hiçbir şey için. Tam motor; tarama hattı, sorun takibi, yönlendirme eşleştirme, 404 günlükleme, temizleme ve kurtarma, Filament olsa da olmasa da aynıdır. Panel *görünümler* ekler: canlı tarama ilerlemesi ve önem derecesi istatistikleri bulunan pano, filtreli sorun gezintisi ve sayfa bazında pencereler, yoksay/yeniden aç düğmeleri, yönlendirme CRUD formları ve tek tıklamalı eylemiyle 404 tablosu. Sorun yoksayma/yeniden açma için şu anda ayrı bir komut yoktur. Panelden veya tinker ya da kendi kodunuzda `SEOScanIssue` modeli üzerinden (`markIgnored()` / `reopen()`) yapın.

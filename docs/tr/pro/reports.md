---
description: "Tek komutla markanıza özel PDF raporu: puan, sorun eğilimi, düzeltilen/yeni sorunlar, düzelen 404'ler, Search Console değişimleri ve yapay zekâ botu etkinliği. İsteğe bağlı zamanlanmış e-posta."
---

# Kendi markanızla raporlar {#white-label-reports}

Tek bir site için markanıza özel **PDF raporu**: genel puan, bulunan sorunların eğilimi, **son rapordan bu yana düzeltilen ve yeni sorunlar**, düzelen 404'ler ve bozuk bağlantılar, Search Console değişimleri ve yapay zekâ botu etkinliği. Tek komutla üretilir ve istenirse **zamanlanmış e-postayla gönderilir**. Ajanslar için hazırlanmıştır: logonuzu, renginizi ve “{client} için hazırlandı” bilgisini ekleyip müşteriye iletin.

[Oluşturulmuş İngilizce örnek raporu indirin (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf) veya [tarama → düzeltme → rapor akışını](/tr/pro/walkthrough) izleyin. Örnekte başlangıçta eklenen Merchant içeriği ve iki yeni tarama kullanılır; bir düzeltilen ve 19 hâlâ açık sorun gösterir, Search Console verisi içermez.

[![Oluşturulan Merchant demo raporunun ilk sayfası.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## Neler içerir {#what-s-in-it}

- **Genel puan**: sayfa başına son puanların ortalaması (yayımlanmış [ölçütler](/tr/pro/scoring): A ≥ 90 … F), son rapora göre değişim ve yakın tarihli taramalarınızdaki **genel puan eğilimi**. Her tarama artık site puanını çalıştırmaya kaydeder; eğilim gerçek tarama geçmişidir. Yükseltmeden sonraki ilk taramadan itibaren birikmeye başlar; puan taşımayan eski çalıştırmalar atlanır.
- **Tarama başına bulunan sorunlar**: yakın tarihli tamamlanan taramalarınızdaki gerçek eğilim; az olması daha iyidir.
- **Düzeltilen ve yeni sorunlar**: son rapordan bu yana kaç kusuru giderdiğiniz ve kaçının ortaya çıktığı. Sorunlar artık düzeltilme / yeniden açılma [yaşam döngüsü](/tr/pro/scan-issues#issue-lifecycle) taşır; bu sistemde tam bir dönem geçince gerçek sorun geçmişinden, aksi hâlde önceki raporun anlık görüntüsünden okunur.
- **Düzelenler**: çözülmüş bozuk bağlantılar, **düzelen** 404'ler (yol kendi başına yeniden 200 döndürür; [`seo-pro:404-recheck`](/tr/pro/production#scheduler) sayfasına bakın), son rapordan bu yana **yönlendirilen** 404'ler ve hâlâ açık olanlar. Düzelen 404, kaynak tarafındaki gerçek düzeltmedir ve yönlendirmeden ayrı sayılır.
- **Search Console**: en iyi sorgular ve sayfalar, ayrıca **değişimler**, yani son rapora göre en büyük tıklama değişiklikleri. GSC kurulmamışsa bölüm sorunsuzca atlanır.
- **Yapay zekâ botu etkinliği**: user-agent üzerinden ilişkilendirilen istekler (doğrulanmış bot kimliği değildir), tüm zamanların toplamları ve [günlük dilim geçmişi](/tr/pro/ai-bot-monitor#period-metrics-daily-buckets) dönemi kapsadığında **bu dönemdeki gerçek isabetler ve her botun taradığı farklı URL'ler**. Aksi hâlde anlık görüntüler arasındaki tüm zamanlar toplamı farkına başvurulur.

## “Son rapordan bu yana” {#since-the-last-report}

Rapor, rastgele bir tarihe göre değil, **önceki raporunuza göre dönem karşılaştırmasıdır**. Her rapor ürettiğinizde küçük bir anlık görüntü (`seo_report_runs`) saklanır: puan, açık sorun kimlikleri, Search Console satırları ve her botun isabet sayacı. Sonraki rapor bugünkü durumu bu görüntüyle karşılaştırır.

Kendi geçmişini tutmayan sinyaller için kullanılan yöntem budur; sayfa başına puanların yalnızca son hâli saklanır. Rapor anında görüntü almak bunları doğru bir karşılaştırmaya dönüştürür. Artık bazı sinyaller **gerçek** geçmiş tutar ve rapor önce bunu kullanır; anlık görüntüye yalnızca gerektiğinde başvurur. Sorunlar düzeltilme / yeniden açılma [yaşam döngüsünü](/tr/pro/scan-issues#issue-lifecycle) taşır (tam bir dönem geçince gerçek düzeltilen/yeni sayıları), Search Console [günlük ölçümler](/tr/pro/search-console#historical-metrics), yapay zekâ botu isabetleri de [günlük dilimler](/tr/pro/ai-bot-monitor#period-metrics-daily-buckets) tutar (döneme ait gerçek isabetler ve farklı URL'ler). Her biri, yükseltmeden sonraki ilk raporda veya geçmiş zaman penceresini kapsamıyorsa anlık görüntü farkına döner.

Bunun iki sonucu vardır:

- **İlk rapor başlangıçtır.** Mevcut durumu gösterir; “düzeltilen”, “yeni”, değişimler ve “son rapordan bu yana” sayıları *ikinci* rapordan itibaren dolar.
- **Sıklığı siz belirlersiniz.** Aylık üretirseniz farklar bir ayı, haftalık üretirseniz bir haftayı kapsar. Karşılaştırma temelini değiştirmemesi gereken anlık önizleme için `--no-store` kullanın.

## Rapor oluşturun {#generate-a-report}

```bash
php artisan seo-pro:report
```

Seçenek olmadan `storage/app/seo-reports/` konumuna PDF yazar. Başka konum belirtin veya e-postayla gönderin:

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### Seçenekler {#options}

| Seçenek | Etkisi |
| --- | --- |
| `--client=` | “İçin hazırlandı” müşteri etiketini değiştirir |
| `--agency=` | Rapordaki ajans adını değiştirir |
| `--accent=` | Vurgu rengini değiştirir (hex, örneğin `#3D5AFE`) |
| `--logo=` | Logo görselinin yolunu değiştirir |
| `--email=` | Alıcı adresi; tekrarlanabilir, raporu e-postayla gönderir |
| `--send` | Yapılandırılmış alıcılara e-posta gönderir |
| `--output=` | PDF'yi bu dosyaya veya dizine yazar |
| `--no-store` | Anlık görüntü saklamaz; dönem farklarının temeli ilerlemez |
| `--json` | Makinece okunabilir özet üretir |

## E-postayı zamanlayın {#schedule-the-e-mail}

Paket kendini zamanlamaz; sıklığı siz belirlersiniz. Uygulamanızın konsol zamanlamasında (`routes/console.php` veya `app/Console/Kernel.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Varsayılan alıcıları yapılandırmada veya `.env` içinde bir kez ayarlayın:

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` bunları kullanır; açıkça verilen `--email` seçenekleri önceliklidir.

## Markalama {#branding}

Markalama gizli bilgi olmadığından yapılandırmada yer alır. Bir kez ayarlayın; her rapor kullanır. Yukarıdaki komut seçenekleriyle her alan rapor bazında değiştirilebilir; tek kurulumdan birkaç müşteriye rapor üretilirken yararlıdır.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Notlar:

- **Logo**: `PNG`/`JPG`/`GIF`/`WEBP`/`SVG` dosyasının mutlak yolu. Uygulama dosyayı okuduktan sonra PDF'ye veri URI'si olarak gömülür; oluşturucunun görseli ağdan getirmesi gerekmez. En güvenli seçenekler `PNG` veya `JPG` biçimleridir.
- **Vurgu rengi**: hex renk sabiti olarak doğrulanır; hatalı değer varsayılana döner. Yalnızca renk olarak kullanılır, asla ham CSS olarak kullanılmaz.
- **Ajans adı**: varsayılan olarak uygulamanızın adı (`config('app.name')`).

Tam yapılandırma bloğu `config/seo-pro.php` içindeki `reports` altındadır. `paper` (varsayılan `a4`), `include_gsc` ve dahil edilecek eğilim çalıştırması / GSC satırı / bot sayıları da buradadır.

## Kurulum başına bir site {#one-site-per-install}

Pro kurulu olduğu tek uygulamayı tarar; rapor da **o kurulumu** anlatır. Birkaç müşteri sitesi işleten ajans, her kurulum için bir rapor üretir; `--client` / markalama seçenekleri bunları etiketler. Çok kiracılı bir “siteler” modeli yoktur.

## Nasıl oluşturulur {#how-it-s-built}

PDF varsayılan olarak **dompdf** ile oluşturulur: yalnızca PHP, Node veya başsız Chromium gerekmez. Zamanlanmış rapor kuyruk işçisi veya cron içinde, sistem çalıştırılabilir dosyası gerektirmeden üretilir; Pro panelsiz kalır. Oluşturucuda uzak kaynak getirme kapalıdır; tek görsel olan logo gömülüdür. Dolayısıyla oluşturulan alanlardaki hiçbir içerik ağ isteği tetikleyemez.

### Her yazı sisteminde raporlar (Browsershot oluşturucusu) {#reports-in-every-script-browsershot-renderer}

Core 3.20 / Pro 2.40'tan itibaren Chrome oluşturucuları JavaScript'i kapatır, HTTP(S), FTP ve WebSocket varlık isteklerini engeller. Yayımlanmış şablonlar gömülü varlıklarla statik HTML/CSS kullanmalıdır. Bu kontroller sayfa varlıklarıyla ilgilidir; Chrome için sunucunun ve sandbox'ın yine doğru yapılandırılması gerekir. Fontconfig, karma metindeki daha az kullanılan yazı sistemi dahil eksik yazı sistemi bildirirse PDF oluşturucusu uygulanabilir bir yazı tipi kurulum uyarısı kaydeder. Eksik yazı tipi Chrome'un PDF üretmesini durdurmaz; raporu göndermeden önce çıktıyı inceleyin.

dompdf yalnızca gömdüğü yazı tipiyle çizer: DejaVu Sans; Latin, Kiril, Yunanca. Bu nedenle Japonca, Tayca veya Arapça kullanan bir müşterinin raporunda eksik karakter kutuları görünür. Pro 2.34'ten beri rapor bunun yerine `spatie/browsershot` üzerinden **başsız Chrome** ile oluşturulabilir. Core'un OG görselleri için kullandığı bağımlılıkla aynıdır; makine bir kez yapılandırılır:

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chrome sunucuda kurulu yazı tiplerini kullanır. Şablon da core'un yazı sistemi bazında yazı tipi listesini kullanır: `Noto Sans`, önce sayfa dilinin `Noto Sans CJK` ailesi, ardından Tayca, Arapça, İbranice, Devanagari, renkli emoji ve Latin temel olarak DejaVu Sans. Gereken aileleri [OG görsellerinde](/tr/guide/multilingual#og-images-in-every-script) olduğu gibi kurun: Debian ve Ubuntu'da `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. `seo:og-images`, sayfanın yazı sistemi için kurulu aile yoksa çalıştırma sırasında uyarır; raporlar için çözüm de aynıdır. Blade şablonu, veri ve anlık görüntü her iki motorda aynıdır; yalnızca çizim motoru değişir. Hangisinin bağlı olduğunu `ReportGenerator::renderer()` söyler.

### Okuyucunun yerel ayarına göre tarihler ve sayılar {#dates-and-numbers-in-the-reader-s-locale}

Rapor oluşturulurken `seo-pro.reports.locale` yakalanır. Null, uygulamanın yerel ayarını kullanır. Çözümlenen çeviri dili PDF ve e-posta etiketlerini, varsayılan konuyu, yazı tipi seçimini ve HTML `lang` değerini belirler. Kendi çeviri dosyası olmayan bölgesel yerel ayarlar, paketle gelen temel dile, sonra İngilizceye döner. Basitleştirilmiş (`zh_CN`) ve Geleneksel (`zh_TW`) Çince ayrı kalır.

`ext-intl` kuruluysa tarihler ve sayılar ICU üzerinden istenen yerel ayara uyar. Farklı bölgesel biçimi bilinçli olarak seçmek için `seo-pro.reports.format_locale` ayarlayın: `locale=it` ve `format_locale=en_US`, İtalyanca etiketleri ABD tarih ve sayı biçimleriyle üretir. `ext-intl` yoksa İngilizce tarih ve virgülle gruplandırılan sayı yedek biçimi korunur.

Kuyruğa alınmış e-posta, işçi yapılandırması değişse bile yakalanmış dili, biçimlendirmeyi ve konuyu korur. Dili PDF'yi üretmeden önce seçin; mailable nesnesinin yerel ayarını sonradan değiştirmek eki çeviremez. Pro 2.39 öncesindeki eski kuyruk verileri, yakalanmış ayarları olmadığından işçi yapılandırmasını kullanır. Özel konular, markalama ve saklanan sorun mesajları kaynak veri olarak kalır.

CLI gösterimi ayrıdır: `php artisan seo-pro:report --display-locale=it` komut özetini çevirir; müşterinin PDF/e-posta dilini rapor yapılandırması seçer. CLI varsayılan olarak İngilizcedir; `SEO_PRO_CLI_LOCALE` ile yapılandırılır. İnsanların okuduğu etiketler çevrilebilirken JSON anahtarları ve kodlar sabit kalır. `lang/vendor/seo-pro/{locale}/seo-pro.php` içindeki rapor ve iş akışı mesajlarını değiştirmek için `seo-pro-lang` yayımlayın.

Kod içinde container'dan `ReportGenerator` çözümleyin:

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```


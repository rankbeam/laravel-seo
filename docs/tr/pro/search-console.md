---
description: "Tamamen salt okunur Google Search Console paneli: gösterim, tıklama, CTR ve konum verileriyle öne çıkan sorgular ve sayfalar, tarayıcının bildiği sayfalarla birleştirilir. Varsayılan olarak kapalıdır."
---

# Search Console (salt okunur) {#search-console-read-only}

**Salt okunur** Google Search Console paneli: **gösterim, tıklama, CTR ve
ortalama konum** verileriyle öne çıkan sorgularınız ve sayfalarınız,
tarayıcının zaten bildiği sayfalarla birleştirilir. Böylece *“bu sayfanın
sorunları var **ve** gösterim kaybediyor”* bilgisini tek yerde görebilirsiniz.
**Varsayılan olarak kapalıdır.**

Tasarımı üç ilke belirler:

- **Kesinlikle salt okunur.** Entegrasyon, pakette sabit tanımlanmış tek bir
  OAuth kapsamı ister: `webmasters.readonly`. Search Analytics’i okuyabilir, başka bir
  şey yapamaz: site haritası göndermez, dizine ekleme istemez ve Search Console’da
  hiçbir şeyi değiştirmez. Kapsamı genişleten yapılandırma ayarı yoktur.
- **Mülkünüz, kimlik bilgileriniz.** İstekler *sunucunuzdan* doğrudan Google’a,
  *sizin* hizmet hesabı veya OAuth kimlik bilgilerinizle gider. Aracılık,
  kullanım ölçümü veya yeniden satış yapılmaz; paket telemetri göndermez.
- **Hatalar bağlam içinde kalır.** Eksik kimlik bilgileri, 403, kota hataları
  veya zaman aşımları, panelin görüntülenmesini kesmeden satır içinde mesaj
  üretir. Geçmiş eşitleme komutu, aşağıda anlatıldığı gibi hataları raporlar
  ve sonraki günleri getirmeyi durdurur.

## Neler sunar? {#what-you-get}

- **İlgilenilmesi gereken sayfalar**: asıl yararlı birleşim; **açık tarama
  sorunları** olan ancak **hâlâ arama trafiği alan** sayfalar. Sorunlu sayfalar
  arasında en çok gösterim alanlar, yani en büyük fırsatlar önce gelir.
  Önce bunları düzeltin.
- **En iyi sayfalar** ve **En iyi sorgular**: alışılmış Search Analytics tabloları.

Filament gösterge panelinde, *SEO* gezinme grubu altında **Search Console**
sayfasıdır; yalnızca entegrasyon açıkken görünür. Arayüzden bağımsız kullanımda
aynı ölçümler `seo-pro:search-console` komutundan ve `SeoPro::searchConsole()` üzerinden gelir.

## Kurulum {#setup}

Search Console mülkünü okuyabilen Google kimlik bilgileri gerekir. İki mod
desteklenir; sunucu için en basiti **hizmet hesabıdır**.

### Hizmet hesabı (önerilen) {#service-account-recommended}

1. Google Cloud’da **Search Console API** hizmetini etkinleştirin ve
   **hizmet hesabı** oluşturun; JSON anahtarını indirin.
2. Search Console → *Ayarlar → Kullanıcılar ve izinler* bölümünde hizmet
   hesabının e-postasını (`…@….iam.gserviceaccount.com`) kullanıcı olarak ekleyin.
   Salt okunur erişim için Kısıtlı izin yeterlidir.
3. Paketi anahtara ve mülke yöneltin:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

`SEO_PRO_GSC_SITE_URL` verilmezse `app.url` üzerinden URL ön ekli mülk türetilir.

### OAuth (çevrimdışı yenileme belirteci) {#oauth-offline-refresh-token}

OAuth istemciniz ve tercihen yalnızca `webmasters.readonly` için yetkilendirilmiş,
uzun ömürlü **yenileme belirteciniz** varsa aşağıdaki gibi yapılandırın.
Her yenilemede bu kapsam istenir. Yanıt tam olarak salt okunur kapsamı
açıkça doğrulamıyorsa paket dönen belirteci reddeder; Google’ın daha geniş
bir yetkiyi her zaman daralttığını varsaymaz.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Belirteç migration’ını yayınlayın {#publish-the-token-migration}

Şifreli erişim belirteci önbelleği `seo_gsc_tokens` tablosunda tutulur.
Bir kez yayınlayıp migration’ı çalıştırın:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Ardından `php artisan seo:doctor` ile kurulumu doğrulayın. Search Console’un açık ve
yapılandırılmış olup olmadığını bildirir; ağ çağrısı yapmaz, gizli bilgi göstermez.

## Arayüzden bağımsız kullanım {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## Geçmiş ölçümler {#historical-metrics}

Yukarıdaki panel ve komut **canlı, kayan bir zaman aralığını** okur; tek veri
deposu Search Console’un kendisidir. Geçmiş bir dönem için sorgulayabileceğiniz
**günlük geçmiş** elde etmek üzere eşitleme komutunu çalıştırın. Bu komut,
gün ve sorgu ile gün ve sayfa bazında ölçümleri `seo_gsc_metrics` tablosuna kaydeder:

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **İlk çalıştırma geçmişi doldurur:** `sync.backfill_days` (varsayılan 90;
  Search Console yaklaşık 16 ay tutar, daha fazlası için artırın).
  Sonraki çalıştırmalar **son saklanan tarihten devam eder**; yakın tarihli
  verilerin Search Console’da geç kesinleşmesini yakalamak için sondaki
  `sync.overlap_days` yeniden çekilir. Aralık her zaman veri gecikmesi nedeniyle 3 gün önce biter.
- **İdempotenttir.** Satırlar `(date, dimension, key)` üzerinden eklenir veya güncellenir;
  yeniden çalıştırmak güvenlidir. Hata veren bir gün (ör. kota hatası),
  çalıştırmayı düzgün biçimde durdurur ve kaç satır saklandığını bildirir.
  Sonraki çalıştırma kaldığı yerden devam eder.
- **Neleri besler?** Tablo her iki dönemi kapsadığında markanıza özel
  [raporun](/tr/pro/reports) Search Console **değişim** bölümü, önceki rapor
  görüntüsünün farkını almak yerine gerçek dönem karşılaştırmasına geçer:
  bu dönem ve hemen öncesindeki eşit uzunlukta dönem. Aynı zamanda daha
  kapsamlı anahtar kelime analizlerinin temelidir.

Yalnızca toplu ölçümler saklanır: sorgu metni, sayfa URL’si ve gün başına dört
ölçüm (tıklama, gösterim, CTR, konum). Kullanıcı veya istek başına veri
hiçbir zaman getirilmez veya yazılmaz.

## Veri işleme ve güvenlik {#data-handling-security}

- **Salt okunur kapsam kontrol edilir.** Hizmet hesabı JWT’si yalnızca
  `webmasters.readonly` ister. OAuth yenileme istekleri de öyledir; eksik veya daha
  geniş kapsamlı yanıt reddedilir. Yalnızca okuma erişimine yetkilendirilmiş
  kimlik bilgisi kullanın. Pakette Search Console’u değiştiren çağrı yoktur.

- **Kimlik bilgileri ortamda kalır.** Hizmet hesabı anahtarı / OAuth gizli
  anahtarı + yenileme belirteci, yapay zekâ anahtarı gibi çağrı anında **adı
  belirtilen** ortam değişkenlerinden okunur. Böylece `php artisan config:cache` bunları
  `bootstrap/cache/config.php` içine yazmaz. Önbelleklenmiş yapılandırma `.env`
  yüklenmesini önlüyorsa süreç ortamında kullanılabilir kılın.
- **Belirteçler saklanırken şifrelenir.** Kimlik bilgilerinizden üretilen kısa
  ömürlü erişim belirteci, `seo_gsc_tokens` içinde **şifreli** (uygulama anahtarıyla)
  saklanır ve süresi dolmaya yaklaşana kadar yeniden kullanılır. Böylece her
  görüntülemede belirteç değişimi yapılmaz. Uzun ömürlü kimlik bilgisi hiçbir
  zaman veritabanına yazılmaz; yalnızca ortamınızda kalır.
- **Her istek SSRF korumalıdır.** Belirteç değişimi ve Search Analytics çağrısı,
  ortak `SsrfGuard` üzerinden yapılır: yalnızca HTTPS, sunucu adı herkese açık
  adrese çözümlenmeli. Yönlendirmeler kapalıdır; istek dahili hizmete yöneltilemez.
- **Gizli bilgi günlüğe yazılmaz.** Erişim belirteçleri, anahtarlar ve kimlik
  doğrulama başlıkları kaydedilmez. API hatasında yalnızca Google’ın temizlenmiş,
  uzunluğu sınırlanmış hata mesajı gösterilir.
- **Ölçümler yerel önbelleğe alınır:** `seo-pro.search_console.cache_ttl` saniye (varsayılan 30 dakika).
  Panel her görüntülemede API’ye yeniden gitmez. Canlı panel/komut bu önbellek
  ve şifreli erişim belirteci dışında hiçbir şey saklamaz. Yalnızca isteğe
  bağlı `seo-pro:gsc-sync` komutu ölçümleri kalıcı yazar: `seo_gsc_metrics` içinde gün
  başına toplu sorgu/sayfa sayıları; kullanıcı başına veri yoktur.

## Yapılandırma başvurusu {#configuration-reference}

Tüm anahtarlar `config/seo-pro.php` → `search_console` altındadır:

| Anahtar | Varsayılan | Amaç |
| --- | --- | --- |
| `enabled` | `false` | Ana anahtar (`SEO_PRO_GSC_ENABLED`). |
| `connection` | `service_account` | `service_account` veya `oauth`. |
| `site_url` | `app.url` üzerinden türetilir | Mülk (`https://example.com/` veya `sc-domain:example.com`). |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | Anahtar JSON’unu veya yolunu içeren ortam değişkeninin **adı**. |
| `oauth.client_id` | — | OAuth istemci kimliği (gizli değil). |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | İstemci gizli anahtarını içeren ortam değişkeninin **adı**. |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | Yenileme belirtecini içeren ortam değişkeninin **adı**. |
| `default_days` | `28` | Raporlama aralığı (GSC veri gecikmesi nedeniyle 3 gün önce biter). |
| `row_limit` | `100` | Rapor başına ilk N satır (API üst sınırı 25000). |
| `cache_ttl` | `1800` | Getirilen raporun önbellekte tutulduğu saniye. |
| `sync.backfill_days` | `90` | İlk `gsc-sync` çalıştırmasında getirilen gün sayısı (boş tablo). |
| `sync.overlap_days` | `2` | Her çalıştırmada yeniden çekilen son günler (geç kesinleşme). |
| `sync.row_limit` | `5000` | Eşitlemenin gün ve boyut başına istediği en fazla satır. |

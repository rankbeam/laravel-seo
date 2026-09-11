---
description: "Yoast veya Rank Math'te elle yazılmış SEO verilerini Laravel modellerinize taşıyın: başlıklar, açıklamalar, kanonikler, robots ve odak anahtar kelimeler. İçe aktarıcı alan eşleme başvurusu."
---

# WordPress'ten geçiş {#migrating-from-wordpress}

Bir içerik sitesini WordPress'ten mi taşıyorsunuz? Rankbeam, ekibinizin Yoast veya Rank Math'te elle yazdığı SEO meta verilerini — başlıklar, açıklamalar, kanonik URL'ler, robots yönergeleri, odak anahtar kelimeler ve sosyal paylaşım için özel değerler — Laravel modellerinize aktarabilir; böylece geçişte yılların optimizasyonunu kaybetmezsiniz.

::: tip Gerçek bir yayına geçiş mi yapıyorsunuz?
Bu sayfa içe aktarıcının *başvuru kaynağıdır* (alan eşlemesi, belirteçler, kaynak anahtarları). Birlikte çalıştırma, içe aktarma, doğrulama ve kullanımdan kaldırma adımlarını içeren, riski düşük **işlem sırası** için [WordPress geçiş uygulama kılavuzunu](/tr/guide/wordpress-migration-runbook) izleyin.
:::

Aynı `seo:import-from` komutunun yönettiği iki yol vardır:

| Yol | Kaynak | Uygun kullanım |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | WordPress'ten dışa aktardığınız tablo dosyası | Çoğu ajans geçişi; URL'leri tam olarak siz kontrol edersiniz |
| [**Veritabanı**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | Canlı WordPress veritabanı | OpenGraph/Twitter özel değerleri ve Rank Math yönlendirmeleri dahil verileri tam koruma |

İkisi de **idempotenttir** (yeniden çalıştırmak aynı satırları günceller, kopya oluşturmaz), **`--dry-run`** destekler ve varsayılan olarak yalnızca boş alanları *doldurur*. Rankbeam'de zaten atadığınız SEO verilerinin üzerine yazmazlar. Mevcut değerleri içe aktarılanlarla değiştirmek için **`--overwrite`** iletin.

## WordPress satırları nasıl `seo_meta` satırlarına dönüşür? {#how-wordpress-rows-become-seo-meta-rows}

WordPress verileri Laravel morph verileri değildir: bir WordPress satırı **URL** veya **yazı kimliğiyle** anahtarlanır; Rankbeam'in `seo_meta` tablosu ise polimorfiktir, her satır gerçek bir Eloquent modeline bağlanır. Bu nedenle içe aktarıcı her WordPress satırını modellerinizden biriyle eşleştirir ve raporda hangi satırların bağlandığını, hangilerinin yalnızca URL olarak kaldığını açıkça belirtir:

- **Modele bağlı.** Hedef modeli `--model="App\Models\Post"` ile belirtirsiniz. Her satırın **slug'ı** (URL'nin son yol parçası veya WordPress `post_name` değeri), varsayılan olarak modelin rota anahtarıyla veya `--match-by=` ile seçtiğiniz sütunla eşleştirilir. Eşleşen satırlar `seo_meta` içine yazılır.
- **Yalnızca URL.** Hiçbir modelle eşleşmeyen satır (veya `--model` verilmeden çalıştırma), bağlanacağı model olmadığından `seo_meta` satırına dönüşemez. `url-only` nedeniyle atlanmış olarak raporlanır. Kanonik URL'si yine de [yönlendirme adayı](#redirects) olabilir.

WordPress yazıları ve sayfaları genellikle *farklı* Laravel modellerine eşlenir. Bu nedenle içe aktarıcıyı içerik türü başına bir kez çalıştırın ve satırların kapsamını sınırlandırın:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Özel yazı türleri varsayılan olarak taranmaz
Veritabanı okuyucuları yalnızca **`post`** ve **`page`** yazı türlerini dolaşır. Özel yazı türlerine dayanan siteler (temanın `product`, `event`, `pathology` türleri vb.) her birini açıkça belirtmelidir; `--post-type=` tekrarlanabilir:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. CSV içe aktarma {#_1-csv-import}

CSV yolu çoğu ajans geçişini kapsar. Bu başlıkla URL başına bir satır dışa aktarın (sütunların sırası serbesttir; tanınmayan sütunlar yok sayılır ve raporlanır):

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Çalıştırın:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Sütun | `seo_meta` karşılığı | Notlar |
|---|---|---|
| `url` | *(eşleştirme anahtarı)* | Slug (son yol parçası) modelle eşleştirilir. Zorunlu. |
| `title` | `title` | 70 karaktere kısaltılır; sınırı aşan değerler raporlanır. |
| `description` | `description` | 160 karaktere kısaltılır. |
| `canonical` | `canonical` | [Yönlendirme adaylarını](#redirects) da belirler. |
| `robots` | `robots` | Aynen saklanır (örneğin `noindex, nofollow`); 50 karaktere kısaltılır. |
| `focus_keyword` | `focus_keywords` | Virgülle ayrılır; ilk anahtar kelime birincildir. |

Bozuk satırlar atlanır ve sayılır: `url` değeri olmayan veya sütun sayısı başlıkla eşleşmeyen satırlar.

---

## 2. Veritabanından içe aktarma (Yoast / Rank Math) {#_2-database-import-yoast-rank-math}

WordPress veritabanınız hâlâ varsa içe aktarıcı SEO meta verilerini doğrudan okuyabilir. CSV dışa aktarımında genellikle kaybolan OpenGraph/Twitter özel değerleri ve (Rank Math için) yönlendirmeler de dahildir.

### WordPress için bağlantı tanımlayın {#point-a-connection-at-wordpress}

WordPress veritabanını `config/database.php` içine bağlantı olarak ekleyin:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Ardından içe aktarın (varsayılan tablo öneki `wp_`; `--table=` ile değiştirin):

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

Okuyucu `{prefix}posts` tablosunu (yayımlanmış yazılar/sayfalar) dolaşır, her yazının eklenti meta verilerini `{prefix}postmeta` üzerinden alır ve `post_name` slug'ını modelinizle eşleştirir.

::: tip Varsayılan dışı tablo öneki
Yönetilen WordPress sunucuları sıklıkla rastgeleleştirilmiş önek kullanır (örneğin `wp_` yerine `wppg_`). Dökümünüzdeki `CREATE TABLE` adlarını kontrol edip gerçek öneki iletin: `--table=wppg_`. Böylece okuyucu `{prefix}posts` ve `{prefix}postmeta` tablolarını bulur.
:::

::: tip MySQL 8'de geri yüklenmiş dökümden okuma
Yerelde okumak için WordPress dökümünü MySQL 8+ içine yüklediyseniz `.sql` dosyasını çalıştırmadan önce katı SQL modunu gevşetin. WordPress'in `'0000-00-00'` datetime varsayılanları MySQL 8'in varsayılan `STRICT`/`NO_ZERO_DATE` modlarınca reddedilir; bu nedenle SEO içe aktarma başlamadan dökümün yüklenmesi başarısız olur (`Invalid default value for 'post_date'`):

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Alan eşlemesi {#field-mapping}

İki içe aktarıcı da alanları **açıkça** eşler. Core 3 sütunu olmayan anahtar *unmapped* olarak raporlanır; karşılığı uydurulmaz.

| Yoast meta anahtarı | Rank Math meta anahtarı | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Yalnızca WordPress varsayılanlarından farklı değerler saklanır. Normal, dizine eklenebilir sayfa `robots` değerini null bırakır ve site varsayılanınızı devralır. Yoast'ın ayrı `noindex` / `nofollow` / gelişmiş (`noarchive`, `nosnippet`, `noimageindex`) bayrakları tek dizede birleştirilir. Rank Math'in serileştirilmiş `robots` dizisi de aynı biçimde okunur; `index` / `follow` varsayılanları çıkarılır.

**Eşlenmeyen anahtarlar** (raporlanır, asla kopyalanmaz): ek görsel kimlikleri (`*-image-id`), anahtar kelime/SEO puanları (`linkdex`, `content_score`, `rank_math_seo_score`), birincil kategori seçimleri ve Rank Math'in zengin sonuç şema işaretleri. [Şema grafı](/tr/guide/schema), bunların yerine daha zengin, türlere dayalı bir çözüm sunar.

::: warning Kanonik URL'ler aynen içe aktarılır
Açıkça atanmış kanonik URL (`rank_math_canonical_url` / `_yoast_wpseo_canonical`) **saklandığı gibi** kopyalanır. Sayfa kanonik URL'sini *eski* alan adındaki mutlak adrese sabitlediyse — yönetilen/hazırlık sunucularında yaygındır, örneğin `https://oldsite-staging.example.com/page/` — içe aktarıldığında hâlâ orayı gösterir. İçe aktarıcı ana makineyi asla yeniden yazmaz. `--site-url`, [yönlendirme adayları](#redirects) ve CSV satır eşleştirmesi için mutlak URL'lerden istek *yollarını* türetir; kayıtlı kanonik değerleri **yeniden yazmaz**. Alan adı değişiminden sonra içe aktarılan kanonik URL'leri inceleyip ana makineyi güncelleyin veya çözümleyicinin kendine işaret eden kanonik URL'sine dönmek için temizleyin. (Çoğu sayfada açık kanonik değer yoktur ve etkilenmez; Yoast ve Rank Math kanonik URL'yi çıktı üretirken otomatik oluşturur.)
:::

### Şablon belirteçleri {#template-tokens}

Yoast ve Rank Math, başlık ve açıklamaları belirteç içeren **şablonlar** olarak saklar: Yoast `%%title%%`, Rank Math `%title%` kullanır. İçe aktarıcı **türetebildiği belirteçleri çözümler**, **kalanları kaldırır**; kayıtlı değer ham bir `%%token%%` dizesi olarak kalmaz:

| Belirteç | Çözümlendiği değer |
|---|---|
| `%%title%%` / `%title%` | WordPress yazı başlığı |
| `%%sitename%%` / `%sitename%` | `wp_options` içindeki blog adı (veritabanından içe aktarma) |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *kaldırılır* (boş bırakılır, çevredeki ayırıcılar düzenlenir) |

Herhangi bir belirteci çözümleyen çalıştırma bunu raporda belirtir. İstediğiniz gibi okunduklarını doğrulamak için **içe aktarılan başlıkları inceleyin**; türetemediğimiz belirteçlere bağlı olanları düzeltin.

---

## Yönlendirmeler {#redirects}

`seo_redirects` bir [Rankbeam **Pro**](/tr/pro/installation) özelliğidir; çekirdek içe aktarıcı bu tabloya doğrudan yazmaz. Bunun yerine `--redirects-csv=` iletin; içe aktarıcı Pro yönlendirme tablosuyla aynı sütunlara sahip **CSV üretir** (`source_path,target_url,status_code,note`), bunu Pro'ya aktarırsınız.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Yönlendirme adaylarının kaynakları:

- **CSV içe aktarma** — `canonical` değeri kendi `url` değerinden **farklı yola** işaret eden satır, eski yoldan kanonik URL'ye bir `301` olur. Kendine işaret eden kanonik URL (aynı yol) üretilmez; döngü oluşturur.
- **Rank Math veritabanı** — `{prefix}rank_math_redirections` tablosundaki etkin kurallar. Yalnızca **tam eşleşme** kuralları üretilir. Regex/içerir/başlar/biter kuralları tek yola eşlenemediğinden atlanmış olarak raporlanır.
- **Yoast (ücretsiz)** yönlendirme tablosu içermez; yalnızca Yoast Premium içerir ve şeması ücretsiz paketin parçası değildir. Yoast yönlendirmelerinde CSV yolunu kullanın.

Adaylar **tavsiye niteliğindedir**. CSV'yi inceleyin, ardından her satırı doğrulayan (döngüleri, güvensiz hedefleri ve kopyaları reddeden) [`seo-pro:redirects-import`](/tr/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro) ile Pro'ya aktarın. CSV yapısı sabit bir sözleşmedir: **yönlendirme CSV biçimi v1**, `source_path,target_url,status_code,note`.

---

## Rapor ne anlatır? {#what-the-report-tells-you}

`--json` kullanılmayan çalıştırma, sonuç tablosu (created / updated / unchanged / skipped / scanned), **Verification report** ve inceleme bölümleri gösterir:

- **Verification report** — onaylamadan önce bir bakışta kontrol edeceğiniz dağılım: **matched** (modele bağlanan satırlar), **url-only** (modelle eşleşmeyenler) ve truncated / unmapped sayıları.
- **Truncated** — `seo_meta` sütununa sığması için kısaltılan değerler.
- **Not imported** — veri içeren ancak Core 3'te yeri olmayan kaynak anahtarları; **her farklı `author` değeri dahil**. Yazar kayıtlı sütun değildir, [`getSEOAuthor()`](/tr/concepts/resolver-precedence) kapsamında ele alınır. Rapor, sessizce kaybolmasına izin vermek yerine başka yere taşımanız gerekenleri listeler.
- **Redirect candidates** — kaç adayın hangi dosyaya yazıldığı.
- **Skipped rows by reason** — yalnızca URL olan satırlar, SEO meta verisi olmayan yazılar, tam eşleşmeyen yönlendirme kuralları.
- **Warnings** — örneğin şablon belirteçlerinin çözümlendiği bilgisi.

Tüm bunların makine tarafından okunabilir sürümü için `--json` ekleyin (`verification` bloğu matched/url-only sayılarını ve her yazar değerini taşır).

### Doğrulama {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict`, herhangi bir sayfada sorun varsa sıfırdan farklı kodla çıkar. Bkz. [Ücretsiz SEO denetimi](/tr/guide/audit). Birlikte çalıştır → içe aktar → doğrula → kullanımdan kaldır şeklindeki tam yayına geçiş sırası için [WordPress geçiş uygulama kılavuzunu](/tr/guide/wordpress-migration-runbook) izleyin.

---

Bunun yerine **Laravel** SEO paketinden mi geçiyorsunuz (ralphjsmit, artesaos, Spatie)? [Diğer Laravel paketlerinden geçişe](/tr/guide/migrate-from-other-packages) bakın.

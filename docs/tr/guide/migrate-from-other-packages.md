---
description: "Başka Laravel SEO paketinden Rankbeam'e geçin: API ve depolamayı HasSEO trait'ine ve saveSEO() metoduna eşleyin; model başına SEO verilerini tek komutla içe aktarın."
---

# Diğer Laravel SEO paketlerinden geçiş {#migrating-from-other-laravel-seo-packages}

Zaten başka bir SEO paketi mi kullanıyorsunuz? Rankbeam'e geçişin yeniden yazım değil, bir günlük iş olması amaçlanır. Bu kılavuz yaygın paketlerin API ve depolamasını Rankbeam'in iki temel öğesine — [`HasSEO`](/tr/guide/quickstart) trait'i ve `saveSEO()` — eşler; SEO verilerini model başına saklayan paket için tek komutluk içe aktarıcı sunar.

::: tip WordPress'ten mi geliyorsunuz?
Bir içerik sitesini WordPress'ten (Yoast veya Rank Math) taşıyorsanız özel [**WordPress'ten geçiş**](/tr/guide/migrate-from-wordpress) kılavuzuna bakın; CSV içe aktarıcısını ve canlı veritabanı okuyucularını kapsar.
:::

| Kaynak paket | Veriyi sakladığı yer | Geçiş yolu |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | `seo` morph tablosu | **`php artisan seo:import-from ralphjsmit`** + trait değişimi |
| [`artesaos/seotools`](#from-artesaos-seotools) | Saklamaz (çalışma zamanı + yapılandırma) | Kod değişimi; değerleri `saveSEO()` / hesaplanan getter'lar üzerinden atayın |
| [`spatie/*`](#from-spatie-packages) | Saklamaz (schema-org / site haritası oluşturucuları) | Tamamlayıcı parçaları koruyun, kalanları Rankbeam'e taşıyın |

Yalnızca **ralphjsmit** SEO verilerini veritabanı tablosunda kalıcı saklar; dolayısıyla toplu içe aktarılacak verisi olan tek paket odur. Diğerleri çalışma zamanında etiket oluşturur; okunacak tablo yoktur. İstek başına çağrılarını kayıtlı `seo_meta` verileriyle değiştirirsiniz.

---

## `ralphjsmit/laravel-seo` paketinden geçiş {#from-ralphjsmit-laravel-seo}

`ralphjsmit/laravel-seo`, model başına bir polimorfik satırı, yapısı Rankbeam'in `seo_meta` tablosuna yakın olan `seo` tablosunda saklar. Bu, temiz ve idempotent bir toplu içe aktarımı mümkün kılar.

### 1. Rankbeam'i yanına kurun {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Geçiş sırasında iki paket birlikte bulunabilir; farklı tablolar (`seo` ve `seo_meta`) ve farklı trait ad alanları kullanırlar.

::: warning İki değil, tek yapılandırma dosyası
Uygulamanızda `ralphjsmit/laravel-seo` tarafından yayımlanmış `config/seo.php` hâlâ varsa Rankbeam yapılandırmasını gölgeler; aynı `seo` anahtarını paylaşırlar. Yedekleyin, silin ve Rankbeam'in dosyasını yeniden yayımlayın: `php artisan vendor:publish
--tag=seo-config`.
:::

### 2. İçe aktarıcıyı çalıştırın {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

İçe aktarıcı ralphjsmit'in `seo` tablosunu okur, her satırı gerçek Eloquent modeline çözümler ve veriyi `seo_meta` içine yazar.

| Seçenek | Etki |
|---|---|
| `--dry-run` | Nelerin içe aktarılacağını raporlar; hiçbir şey yazmaz. |
| `--model="App\Models\Post"` | Bir veya daha fazla model sınıfıyla sınırlar (tekrarlanabilir). |
| `--locale=fr` | İçe aktarılan satırları bu dil için yazar (varsayılan: uygulamanın dili). |
| `--table=legacy_seo` | Adı değiştirilmiş kaynak tabloyu okur. |
| `--connection=legacy` | Kaynak tabloyu başka veritabanı bağlantısından okur. |
| `--limit=100` | En fazla N satır aktarır (aşamalı geçiş için yararlı). |
| `--overwrite` | Mevcut dolu değerleri değiştirir (varsayılan: yalnızca boş alanları doldurur). |
| `--json` | Makine tarafından okunabilir rapor. |
| `--force` | Onay istemini atlar (script/CI için). |

**İdempotenttir**: yeniden çalıştırmak aynı satırları günceller, asla kopya oluşturmaz. Varsayılan olarak yalnızca boş alanları *doldurur*; Rankbeam'de zaten atadığınız SEO verilerinin üzerine yazmaz. İçe aktarılan değerlerin mevcut değerlerin yerini almasını istiyorsanız `--overwrite` iletin.

### 3. Modellerinizdeki trait'i değiştirin {#_3-swap-the-trait-on-your-models}

ralphjsmit trait'ini Rankbeam'inkiyle değiştirin. Metot adları biraz farklıdır; trait artık `seo_meta` tablosunu okur.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

SEO verilerini ralphjsmit'in `getDynamicSEOData()` metoduyla özelleştirdiyseniz bu mantığı Rankbeam'in alan başına hesaplanan getter'larına taşıyın (`getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()`, `getSEOAlternates()`); bkz. [Hızlı başlangıç](/tr/guide/quickstart). Kalıcı öncelikli değerler `saveSEO()` üzerinden kaydedilir:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Alan eşlemesi {#field-mapping}

İçe aktarıcı alanları **açıkça** eşler; Core 3 şemasında bulunmayan sütunu körlemesine kopyalamaz.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Notlar |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | Aynen kopyalanmaz; mevcut modelden **yeniden çözümlenir** (aşağıya bakın). |
| `title` | `title` | 70 karaktere kısaltılır (`seo_meta` sütun uzunluğu); sınırı aşan değerler raporlanır. |
| `description` | `description` | 160 karaktere kısaltılır; sınırı aşan değerler raporlanır. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | 50 karaktere kısaltılır. |
| `image` | `og_image` | `twitter:image` çözümleyici üzerinden otomatik devralır. |
| `author` | *(içe aktarılmaz)* | Core 3'ün `seo_meta` tablosunda yazar sütunu yoktur; makale yazarı kayıtlı sosyal meta değil, çözümleyici düzeyindeki bir konudur. Yazarı olan satırlar **sayılır ve raporlanır**; böylece nerede tutulacağına karar verebilirsiniz (örneğin `getSEOData` tarzı hesaplanan değer). |
| `id`, `created_at`, `updated_at` | *(içe aktarılmaz)* | Yapısal alanlar. |

**Morph türü neden yeniden çözümlenir?** Her kaynak satır gerçek modeline çözümlenir ve `seoable` anahtarları modelin kendi `getMorphClass()` değerinden alınır. ralphjsmit farklı bir düzen saklamış olsa bile uygulamanızın *güncel* [morph eşlemesi](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types) altında ilişki doğru kalır. Ayrıca modeli sonradan silinmiş satırlar atlanabilir; atlanmış olarak raporlanır, asla sahipsiz kayıt olarak yazılmaz.

### Rapor ne anlatır? {#what-the-report-tells-you}

`--json` kullanılmayan çalıştırma, sonuç tablosunun yanında üç inceleme bölümü gösterir:

- **Truncated** — `seo_meta` sütununa sığması için kısaltılmış değerler. Bunları inceleyin.
- **Not imported** — veri içeren ancak Core 3'te karşılığı olmayan kaynak sütunlar (örneğin `author`).
- **Skipped rows by reason** — boş kaynak satırları, silinmiş modeller, çözümlenemeyen model türleri.

### Doğrulama {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Sonuçtan memnun kaldığınızda `ralphjsmit/laravel-seo` paketini kaldırın ve `seo` tablosunu silin.

---

## `artesaos/seotools` paketinden geçiş {#from-artesaos-seotools}

`artesaos/seotools` bir **çalışma zamanı** etiket oluşturucusudur: değerleri istek başına `SEOMeta`, `OpenGraph`, `TwitterCard` ve `JsonLd` facade'ları üzerinden (çoğunlukla controller'da), `config/seotools.php` varsayılanlarıyla destekleyerek ayarlarsınız. Model başına hiçbir şey saklanmaz; içe aktarılacak tablo yoktur. İstek başına çağrıları kayıtlı veya hesaplanan değerlere taşırsınız.

| artesaos/seotools çağrısı | Rankbeam karşılığı |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` veya `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` veya `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` veya `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | Eşdeğer keywords meta etiketi yoktur; odak anahtar kelimeler dahili editoryal kontroller içindir. `saveSEO(['focus_keywords' => [...]])` (bkz. [denetim](/tr/guide/audit)) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [JSON-LD şema grafı](/tr/guide/schema) |
| `config/seotools.php` varsayılanları | `config/seo.php` site varsayılanları + [çözümleyici öncelik sırası](/tr/concepts/resolver-precedence) |
| Yerleşimde `{!! SEO::generate() !!}` | `@seo($model)` (bkz. [Blade](/tr/guide/blade)) |

Değişiklik kavramsaldır: her controller'da etiketleri adım adım ayarlamak yerine SEO verilerini bir kez saklarsınız (model başına, `seo_meta` içinde) ve Rankbeam çözümleyicisi çıktıyı üretir. `config/seotools.php` içindeki site geneli yedek değerler Rankbeam'in [yapılandırma varsayılanlarına](/tr/reference/configuration) dönüşür; rota başına statik sayfalar `@seoForRoute()` kullanır.

---

## Spatie paketlerinden geçiş {#from-spatie-packages}

`spatie/laravel-seo` adlı meta depolama paketi yoktur; içe aktarılacak bir şey bulunmaz. SEO yanında kullanılan Spatie paketleri **tamamlayıcı oluşturuculardır**; bunları parça parça koruyabilir veya değiştirebilirsiniz:

- **`spatie/schema-org`** — akıcı API'li JSON-LD oluşturucu. Rankbeam'in kendi [şema grafı](/tr/guide/schema), `seo_meta.schema_jsonld` içine kaydeden ve yinelenmeleri giderilmiş çıktı üreten türe özel `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` ve `Organization` oluşturucularına sahiptir. Elle oluşturulmuş `spatie/schema-org` nesneleriniz varsa `->toArray()` çıktılarını `saveSEO(['schema_jsonld' => $array])` içine iletin veya Rankbeam oluşturucularıyla yeniden ifade edin.
- **`spatie/laravel-sitemap`** — site haritası oluşturucu. Rankbeam'in [site haritası kayıt sistemi](/tr/guide/sitemaps) bunun üzerine kuruludur. Modellerinizi kaynak olarak kaydedip birleşik site haritasını Rankbeam'e ürettirebilir veya mevcut Spatie site haritanızı koruyup Rankbeam rotasını kapatabilirsiniz.

(Başka bir çalışma zamanı/struct tabanlı meta oluşturucu olan [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO) kullanıyorsanız artesaos ile aynı yolu izleyin: istek başına `setTitle`/`addMeta` çağrılarını `saveSEO()` veya hesaplanan getter'lara taşıyın.)

---

## İçe aktarıcıyı genişletme {#extending-the-importer}

`seo:import-from` komutu küçük bir `Rankbeam\Seo\Importing\Contracts\Importer` uygulamaları kayıt sistemiyle desteklenir; yeni kaynaklar komuta dokunmadan eklenebilir. Mevcut yerleşik kaynaklar: `ralphjsmit` ve WordPress içe aktarıcıları (`wordpress-csv`, `yoast`, `rank-math`; bkz. [WordPress'ten geçiş](/tr/guide/migrate-from-wordpress)). Kendi kaynağınızı servis sağlayıcısında kaydedin:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```


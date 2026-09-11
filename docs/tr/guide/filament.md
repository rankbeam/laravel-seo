---
description: "Ücretsiz laravel-seo-filament paketiyle herhangi bir Filament kaynak formuna iki satırda eksiksiz SEO bölümü ekleyin. HasSEO trait'i üzerinde Filament 4.x ve 5.x desteği."
---

# Filament yönetim alanları {#filament-admin-fields}

Ücretsiz [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) paketi, herhangi bir Filament kaynak formuna eksiksiz SEO bölümü ekler: **kaynak başına iki satır**. Filament **4.x ve 5.x** sürümlerini (Livewire 3 ve 4) destekler. Meta verilerini düzenlemek ücretsizdir; Pro, aşağıdaki örnekte görülen taramaları ve puanı ekler.

## Ön koşullar {#prerequisites}

Mevcut bir Filament 4 veya 5 paneli ve çekirdeğin `HasSEO` trait'ini kullanan bir model gerekir. Editörü eklemeden önce migration ve çıktı üretimi dahil [çekirdek Hızlı başlangıç](/tr/guide/quickstart) adımlarını tamamlayın.

## Kurulum {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

Kaynağın arkasındaki model, çekirdeğin `HasSEO` trait'ini kullanmalıdır.

## Bölümü kaynağa ekleyin {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## Kaydedilen sonucu kontrol edin {#check-the-saved-result}

Mevcut bir kaydı açın. SEO açıklaması girin, kaydedin ve formu yeniden yükleyin. Değer kalmalı, önizleme onu göstermeli ve kaynağı **Elle girilmiş** olarak görünmelidir. Aynı açıklamanın ziyaretçilerinize ulaştığını doğrulamak için üretilen sayfanın `<head>` bölümünü kontrol edin.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="Merchant demosundaki SEO alanları: başlık, açıklama, kanonik URL, sosyal görsel, arama önizlemesi ve çözümlenen değerlerin kaynakları." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Merchant demosundan örnek. Alanlar panelinizin temasını kullanır; kullanılabilir kontroller ve karakter bütçeleri, kurulu sürümünüze ve yapılandırmanıza bağlıdır.*

Bölüm şunları içerir:

- Anlık karakter sayaçlarıyla **başlık ve açıklama**. Bütçe, yazılan metnin yazı sistemi için çekirdeğin [uzunluk politikasından](/tr/guide/multilingual#title-and-description-budgets-per-script) gelir (Latin metinlerde 60/160, CJK için yaklaşık 30/80; grafem olarak sayılır).
- **Odak anahtar kelimeler** — etiket girişi. Düz anahtar kelimeler yazarsınız; çekirdeğin yapılandırılmış `[{keyword, is_primary}]` biçiminde saklanırlar (ilk öğe birincildir). Böylece `getPrimaryKeyword()` ve `SEOData` bunları değişmeden okur. [`seo:audit`](/tr/guide/audit) komutunun ve Pro taramasının anahtar kelimesi hâlâ eksik sayfaları işaretlemesi için `seo.keywords.enabled` etkinleştirin (varsayılan olarak kapalıdır; tek ayar, bkz. [Yapılandırma](/tr/reference/configuration#focus-keywords)).
- **Canonical URL** (boş = otomatik; sorgu dizesi kaldırılır).
- **Robots** seçimi (boş = site varsayılanı).
- **Sosyal paylaşım görseli** yükleme (og:image / twitter:image); Filament'in varsayılan diskinde `seo/` altında saklanır.
- Yazarken çözümleyicinin yedek değer zincirini anlık yansıtan **arama sonucu önizlemesi**.
- **Kaynak göstergeleri** — her alanın geçerli değerini hangi çözümleyici katmanının ürettiği: *Elle girilmiş*, *İçerikten türetilmiş*, *Model türü varsayılanı*, *Genel varsayılan*, *Site yapılandırması* veya *URL'den türetilmiş*.

## Alanları sınırlandırma {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

`title`, `description`, `focus_keywords`, `canonical`, `robots` ve `og_image` alanlarının herhangi bir alt kümesini kabul eder.

Trait olmadan `SEOFields::make(?array $only)` aynı bölümü doğrudan döndürür.

## Değerler nasıl saklanır? {#how-values-persist}

Bölüm, bir `seo_meta` durum grubuna bağlanır ve çekirdeğin `seoMeta()` ilişkisi üzerinden kaydeder (güncelle veya oluştur). Kendi tablolarınızda sütun gerekmez; değerler hemen [çözümleyicinin](/tr/concepts/resolver-precedence) 6. katmanı (açıkça atanan) olur.

## Birden fazla dil {#several-languages}

Çekirdek, [her (model, dil kodu) için bir `seo_meta` satırı](/tr/guide/multilingual) tutar. Sayfanın yayımlandığı dilleri iletin; bölüm **dil başına bir sekme** oluşturur (Filament 1.9):

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

Ya da tüm kaynaklar için paket yapılandırmasında bir kez ayarlayın:

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

Her sekme kendi satırını düzenler ve kendine ait şu öğeleri taşır:

- **Sayaçlar** — o dilin yazı sistemi için [uzunluk politikası](/tr/guide/multilingual#title-and-description-budgets-per-script). Aynı sayfada boş Japonca başlık `0 / 30` gösterirken İngilizce sekme `0 / 60` gösterir.
- O dilin çözümlenen değerlerinden oluşturulan **önizleme** (SERP / sosyal kart).
- O dilin satırını açıklayan **yedek değer göstergeleri**.
- O sürümde doldurulmuş alan sayısını gösteren **rozet**; böylece boş çeviriler fark edilir.

`ext-intl` yüklüyse sekme etiketi dilin adını panel dilinde gösterir (`Italiano` / `Italian`); aksi halde kodu gösterir. Tüm sekmeler birlikte doğrulanır ve kaydedilir; hiçbir şey girilmeyen bir dil için yer tutucu satır oluşturulmaz.

::: details Özel form durumu bağlamaları
Birden fazla dilde durum yolu `seo_meta.{locale}.title` olur; tek dilde `seo_meta.title` olarak kalır. Özel form eylemlerinde uygun yolu kullanın.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Merchant demosundaki İngilizce, İtalyanca ve Japonca sekmeler; Japonca başlık ve açıklama bütçeleri 30 ve 80, açıklama alanı boş." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Merchant demosu, 9 Eylül 2026, `locales: ['en', 'it', 'ja']` ile. Boş Japonca sekme kendi sayaçlarını kullanır. Buradaki İngilizce başlık, demo modelinin içerikten türetilen yedek değerinden gelir: dil sekmesi eklemek içeriğinizi çevirmez. Alanların üzerindeki Pro puanı, kaydın son tarama sonucudur; her dil sekmesinin ayrı puanı değildir.*

### Çevrilebilir içerik eklentisiyle {#with-a-translatable-plugin}

`lara-zeus/spatie-translatable` paketinin **Filament 4 üzerinde 1.x** veya **Filament 5 üzerinde 2.x** sürümüyle, Düzenle ve Oluştur sayfalarında Rankbeam'in sayfa adaptörlerini kullanın. Yalnızca sayfa trait içe aktarımlarını değiştirin; eklentinin kaynak/liste trait'lerini, panel eklentisini ve `LocaleSwitcher` eylemini koruyun:

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

Her sayfa sınıfının içinde yine `use Translatable;` bildirir. Eklenti isteğe bağlı uygulama bağımlılığı olarak kalır. En son yama sürümünü kullanın; yerel entegrasyon örneği eklenti 1.0.4 / Filament 4.13.1 ve eklenti 2.0.1 / Filament 5.8.1 sürümlerini kapsar.

Dil değiştirmek, kaydedilmemiş ana içerik, SEO meta verisi ve yapılandırılmış veri taslaklarını editörde tutar. Kaydetme, ziyaret edilmiş her dili doğrular ve hepsini bir veritabanı işlemi içinde kaydeder. Doğrulama hatası, dikkat gerektiren dili açar. Yüklemeler Kaydet sırasında saklanır; sayfadan ayrılmak veya sayfayı yenilemek kaydedilmemiş taslakları siler. Taslak kaydetmek, eksik içeriği sizin için çevirmez.

Adaptörler normal önce/sonra kancalarını ve form verisi dönüştürücülerini korur. Sayfanız `handleRecordCreation()`, `handleRecordUpdate()`, `callHook()` veya işlem metotlarını geçersiz kılıyorsa adaptör davranışını bu özelleştirmeye dahil edin ve kaydetme akışını test edin. Veritabanı işlemleri dosya sistemi yazmalarını geri almaz; uygulamalar olağan sahipsiz dosya temizliğini sürdürmelidir.

Livewire 3'te özel canlı metin alanları için açık bir debounce yerine `->live()` veya `->live(onBlur: true)` tercih edin. Açık debounce, yerel model durumunu geciktirir ve hızlı bir dil değişiminde son tuş vuruşlarını kaybedebilir. Rankbeam'in başlık ve açıklama alanları varsayılan istek debounce'unu kullanır.

Üst paketin sayfa trait'leri tek başına kullanıldığında dil değişiminde formları yeniden doldurur. Rankbeam bunların yanlışlıkla meta veri yazmasına karşı korur, ancak bu trait'ler SEO taslaklarını korumaz; Düzenle/Oluştur sayfalarını adaptörlere taşıyın. Açıkça tanımlanan `locales:` sekmeleri ortak editör olarak kalır ve sayfa dil seçicisinden önceliklidir.

Açık dil listesi veya sayfa dili yoksa bölüm uygulamanın dilini düzenler.

## Yapılandırılmış veri (schema.org) {#structured-data-schema-org}

İsteğe bağlı **Yapılandırılmış veri** bölümü, editörlerin kod yazmadan JSON-LD zengin sonuç şeması eklemesini sağlar. SEO bölümünün yanına ekleyin:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

(veya trait olmadan doğrudan `SEOSchemaFields::make()` kullanın).

Çekirdeğin `seo_meta.schema_jsonld` sütununa yazar; [şema renderer'ının](/tr/guide/schema) ürettiği değerle aynıdır. **Yalnızca arayüz bağlamasıdır**: her belge çekirdeğin şema oluşturucusuyla üretilir ve kaydedilmeden önce çekirdeğin `SchemaValidator` doğrulayıcısından geçer. Kendine ait şema mantığı eklemez.

Bölüm şunları sunar:

- **Otomatik breadcrumb** — yapılandırma gerektirmeyen ilk seçenek olarak sunulan tek anahtar. `BreadcrumbSchema::fromModelAncestors()` aracılığıyla kaydın üst öğe zincirinden bir `BreadcrumbList` türetir. Doldurulacak bir alan yoktur; modelin üst öğelerini izler.
- **Şema blokları** — tekrarlayıcı alan. Her blok ya **SSS** (soru/cevap çiftleri → `FAQPage`) ya da **Ürün** (ad, açıklama, görsel, marka, SKU, fiyat + para birimi, stok durumu → `Product`) olur. Çekirdeğin `FAQSchema` / `ProductSchema` oluşturucuları tarafından üretilir.

### Doğrulama {#validation}

Geçersiz JSON-LD oluşturacak bir blok **kaydetme sırasında reddedilir** ve çekirdek doğrulayıcının mesajı gösterilir. Örneğin cevapsız bir SSS girdisi veya görseli ya da teklifi olmayan bir Ürün (bu oluşturucu bu alanları gerektirir; bu ifade Google'ın her Product arama özelliğine ilişkin gereksinimlerinin eksiksiz açıklaması değildir). Boş bırakılan bloklar yok sayılır.

### Neyi saklar? {#what-it-stores}

`schema_jsonld` oluşturulan belgeleri tutar: tek belge varsa tek nesne, birden fazlaysa JSON dizisi (önce breadcrumb, ardından bloklarınız). Her ikisi de geçerli JSON-LD'dir ve `@seo` / `renderSchema()` üzerinden değişmeden üretilir.

### Yönetmediği şemalar {#schema-it-doesn-t-manage}

Kodda yazdığınız ve editörün temsil edemediği şema — elle oluşturulmuş bir `@graph`, alışılmadık bir `@type` veya formun sunmadığı alanları (incelemeler, değerlendirmeler, GTIN/MPN) taşıyan bir Product — **aynen korunur**. Formu açıp kaydetmek bu şemanın üzerine asla yazmaz.

## Sorun giderme {#troubleshooting}

- **Kaydedilen alan sayfada görünmüyor:** şablonunuzun aynı kayıt ve dil için `@seo($model)` ürettiğini doğrulayın.
- **Alan hâlâ yedek değer kullanıyor:** etkin dilde alana ait kaydedilmiş bir değer var mı kontrol edin. Kaynak göstergeleri çözümlenen katmanı belirtir.
- **Dil sekmesi eksik:** açık `locales:` argümanını, paket yapılandırmasını ve sayfa düzeyindeki çeviri seçicilerini kontrol edin. Öncelikleri yukarıda açıklanmıştır.

::: details Testbench'te özel paneli test etme

Filament'i orchestra/testbench içinde başlatıyorsanız Filament'in `SupportServiceProvider` sağlayıcısını `LivewireServiceProvider` sağlayıcısından **önce** kaydedin. Filament, Livewire'ın `DataStore` bağlamasını yeniden tanımlar; yanlış sıralama tüm Livewire testlerini `ViewErrorBag::put(): ... null given` hatasıyla başarısız kılar. Gerçek uygulamalar etkilenmez (paket keşfi sağlayıcıları doğru sıralar).
:::

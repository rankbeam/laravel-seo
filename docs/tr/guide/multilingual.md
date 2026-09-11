---
description: "Rankbeam'in İngilizce dışındaki içeriği ele alışı: yazı sistemine göre başlık/açıklama bütçeleri, grafem güvenli kısaltma, dile duyarlı harf dönüşümü, hreflang normalleştirme ve politikaları, inLanguage, bölgesel arama motorları, site doğrulama, OG yazı tipleri ve Unicode URL'ler."
---

# Çok dilli içerik {#multilingual-content}

[Çeviriler](/tr/guide/translations), *paketin* sizin dilinizi konuşmasını sağlar. Bu sayfa diğer yarıyı ele alır: paketin **içeriğinizin dilini anlaması**. Japonca için 60 karakterlik başlık sınırı uygun değildir; sözcük sınırında kesmek Tayca metni bozar; `İstanbul` ve `istanbul` Türkçede aynı sözcüktür; `it_IT` hreflang kodu geçersizdir; Korece bir site yalnızca Google'ın değil, Naver'ın tarayıcısını da önemser. Bunlar çeviri değil, doğruluk konularıdır; tüm arayüzlerin aynı davranması için karar çekirdekte verilir.

Varsayılanlar ve politika değişiklikleri `config/seo.php` içindedir. Bazı yetenekler, ICU sözcük bölümleme ve kurulu yazı tipleri dahil çalışma zamanı bağımlılıkları gerektirir; çevrilmiş içerik uygulamanızdan gelmelidir.

## İçerik dili ve arayüz dili {#content-locale-and-interface-locale}

Çekirdek 3.17, Filament 1.11 ve Pro 2.36, seçilen içerik dilini meta verileri, hesaplanan değer kancaları, önizleme URL'leri, kontrol listesi anahtar kelimeleri ve yapay zekâ istekleri boyunca taşır. İngilizce bir panel, etiketlerini değiştirmeden İtalyanca ve Japonca içerik düzenleyebilir.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

Bu okuma işlemleri ilgili dilin meta veri satırını seçer ve `getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()` ve `getSEOSchema()` gibi model kancalarını geçici bir dil kapsamında çalıştırır. Kanca istisna fırlatsa bile çağıranın modeli ve uygulamanın dil kodu korunur. Spatie'nin `setLocale()` ve `getTranslatableAttributes()` metotlarını uygulayan modellerde dil kodu, modelin yalıtılmış bir örneğine de uygulanır. Kancalarınız yine çevrilmiş içerik döndürmelidir; Rankbeam sıradan veritabanı özniteliklerini otomatik çevirmez.

Pro, modele dayalı yapay zekâ metotlarında ve toplu doldurmada açık bir `locale:` kabul eder. Verilmezse çeviri modelinin geçersiz kılınmış `seoData()` varsayılanı içerik dilini belirler; yedek olarak uygulamanın dili kullanılır. Filament eylemleri, tek dilli editör ve izleme modu dahil, kendi alanlarının dilini alır. Özel kuyruk işlerinde seçilen dili serileştirin ve iş çalıştığında açıkça iletin. Worker'ın o anki diline güvenmeyin.

Özel eşzamanlı içerik okuyucularında `ModelLocale::run($model, $locale, $callback)`, callback'e yalıtılmış model iletir ve uygulamanın dilini `finally` içinde geri yükler. Dile bağlı tüm okumaları callback içinde bitirin; tembel bir iterator veya closure döndürmek kapsamı uzatmaz.

## Yazı sistemine göre başlık ve açıklama bütçeleri {#title-and-description-budgets-per-script}

Rankbeam, Latin başlık/açıklamalar için 60/160, CJK için 30/80 grafemlik editoryal bütçeler kullanır. Bunlar yapılandırılabilir yaklaşık değerlerdir; piksel ölçümü veya arama motorunun tüm değeri göstereceğine dair garanti değildir. Google [başlık bağlantıları](https://developers.google.com/search/docs/appearance/title-link) veya [meta açıklamaları](https://developers.google.com/search/docs/appearance/snippet) için sabit karakter sınırı belirtmez; gösterilen metin cihaz genişliğine göre kısaltılabilir.

`Rankbeam\Seo\I18n\LengthPolicy`, kendisine verilen metin için bütçeyi belirler:

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

Editör uyarıları (`SEOWarningEvaluator`), ücretsiz `seo:audit`, hesaplanan açıklamanın kısaltılması, Pro taraması ve Filament sayaçları bu politikayı okur; hepsi aynı politikayı kullanır. Uyarılar başlık son eki dahil çözümlenen değerleri değerlendirir; editör ise kaydedilmemiş metni de gösterebilir. Uzunluklar bayt veya kod noktası yerine **grafem kümelerini** sayar. Küme sınırları kurulu Unicode uygulamasına bağlıdır; hece sayacı veya arama sonucu piksellerinin ölçümü değildir.

Satırlar `seo.length_policy` içinde, yazı sistemi grubu anahtarıyla bulunur (`latin`, `cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew`, `devanagari`). Listelenmeyen her grup için `default` kullanılır. Bir satır yalnızca bazı anahtarları belirleyip kalanları devralabilir:

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Varsayılan olarak yalnızca `cjk` farklıdır. Eski yayımlanmış yapılandırmayla yükseltilen kurulum, hiçbir şeyi değiştirmeden yerleşik `cjk` satırını alır.

::: tip Karma başlıklar
Algılama, ağırlıklı harf sayımına dayanır: CJK glifi iki kez sayılır. Bu nedenle “Laravel SEO の完全ガイド” CJK olarak çözümlenirken “Laravel SEO for the 東京 developer” Latin kalır. Hiç harf içermeyen değer (yıl, fiyat), sayfa dilinin yazı sistemini alır.
:::

Bu değerleri okuyan kodlar için `SEOWarningEvaluator::TITLE_MAX_LENGTH` / `DESCRIPTION_MAX_LENGTH` sabitleri Latin varsayılanları olarak varlığını sürdürür.

## Grafem güvenli, yazı sistemine duyarlı kısaltma {#grapheme-safe-script-aware-truncation}

Hesaplanan açıklama (`seo.computed.description_max_length`, Latin bütçesi) politika tarafından ölçeklenir; CJK açıklaması bunun yarısını alır. Kesme işlemini `Rankbeam\Seo\I18n\Truncator` yapar:

- Sözcükler arasında boşluk bulunan metin, eski kuralı korur: sınır içindeki son sözcük sınırı (sınırın en az %60'ına ulaşıyorsa), üç nokta eklenmeden ve sondaki noktalama temizlenerek; Latin metinler için öncekiyle bayt düzeyinde aynı çıktı.
- Han, Kana ve Tayca sözcük boşluğu kullanmaz. Bu nedenle kesme, önce sınır içindeki son cümle veya yan cümle işaretini (。！？、，…) tercih eder; sonra metinde varsa boşluğu (Korece), en son doğrudan kesmeyi kullanır.
- Kesme grafem kümeleri üzerinde yapılır; birleşen dizinin içine denk gelemez. Tayca ünlü işareti veya emoji değiştiricisi tabanından ayrılmaz.

## Dile duyarlı büyük/küçük harf dönüşümü {#locale-aware-casing}

`mb_strtolower()` dil kodunu dikkate almaz. `Rankbeam\Seo\I18n\CaseFolder` alır:

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` görüntüleme biçimidir; `fold()`, `equals()`, `contains()` ve `containsWord()` karşılaştırmalar içindir. Çekirdek, markayı dikkate alarak başlık son ekini atlamak için bunu kullanır (`seo.title_suffix_skip_when_contains`). Böylece Türkçe marka her iki `i` biçiminde eşleşir ve aksanlı marka gerçek sözcük sınırıyla ele alınır. Pro anahtar kelime kontrolleri aynı yardımcı üzerine kuruludur.

Harf katlama aksanları korur. Her aksanlı ve aksansız yazımı eşdeğer yapmaz. Dile özgü bir kök bulucu kendi indirgemelerini uygulayabilir; bu, `CaseFolder` ve özdeşlik eşleştirmesinden ayrıdır.

## hreflang {#hreflang}

Google, `language[-Script][-REGION]` okur: ISO 639-1 iki harfli dil, isteğe bağlı ISO 15924 yazı sistemi ve isteğe bağlı ISO 3166-1 alpha-2 bölge; ayrıca `x-default`. `es-419` gibi sayısal bölgeler BCP47 için geçerlidir, ancak [Google'ın hreflang sözleşmesinin](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes) dışındadır. Laravel uygulamaları bunun yerine genellikle kendi *dil kodunu* iletir (`it_IT`, `pt_br`); alt çizgi orada geçersizdir. `seo.hreflang` içindeki üç politika, modelin `getSEOAlternates()` listesine, liste `<link rel="alternate">` etiketlerine, site haritası `<xhtml:link>` girdilerine, `llms.txt` bağlantılarına ve denetim girdisine dönüşmeden **önce** uygulanır. Hepsi aynı politikayı kullanır; `llms.txt`, “Also in” bağlantılarından sayfanın kendisini ve `x-default` girdisini çıkarır:

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`** (varsayılan olarak açık), ayırıcıları, harf büyüklüğünü ve kayıtlı takma adları uyarlar (`iw_IL` → `he-IL`). Yinelenen ayırıcıları korur (`en__US` → `en--US`); böylece denetim bunları işaretleyebilir. İletilen baytları korumak için kapatın.
- **`include_self`**, listede sayfanın ne URL'si ne de kodu varsa kendi dil kodunu ve kanonik URL'sini ekler. Google her dil sürümünün kendisini listelemesini gerektirir; kancanız yalnızca *diğer* dilleri döndürüyorsa bunu açın.
- **`x_default`**, listede yoksa alternatifi `x-default` olarak kopyalanacak dili belirtir.

Boş liste boş kalır: çevirisi olmayan sayfa ne kendine başvuru ne de `x-default` alır.

Ücretsiz denetim, politika uygulanmış listeye üç kontrol ekler:

| Kod | Önem derecesi | Anlam |
|---|---|---|
| `hreflang_invalid_code` | warning | Google'ın sözleşmesi dışındaki kod (`en-UK`, `jp`, `english`, `es-419`, `fil`). |
| `hreflang_duplicate_code` | notice | Aynı kod iki kez listelenmiş. |
| `hreflang_missing_self` | warning | Sayfanın kendi URL'si listesinde yok. |

Karşılıklılık (diğer sayfa geri bağlantı veriyor mu?) tarama gerektirir; bu Pro taramasının işidir. İsteğe bağlı `check_hreflang_reciprocity`, her alternatifi SsrfGuard üzerinden getirir ve diğer sayfa bu sayfanın URL'sini **dil koduyla birlikte** bildirmiyorsa `hreflang_not_reciprocal` üretir (Pro 2.38+, bkz. [tarama sorunları](/tr/pro/scan-issues#network-codes)). İhtiyacınız varsa yardımcı herkese açık API'dir:

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### Üç dil kodu sözleşmesi {#three-language-code-contracts}

Çekirdek **3.18+**, uygulama ayarını HTML'de sunulan değerden ayırır:

| Girdi | Uygulama normalleştirmesi | HTML dili | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | Sunulduğu haliyle geçersiz | Sunulduğu haliyle geçersiz |
| `de-CH-1901` | Korunur | Geçerli kayıtlı varyant | Desteklenmeyen varyant |
| `es-419` | Korunur | Geçerli sayısal bölge | Desteklenmeyen sayısal bölge |
| `zh-Hant-TW` | Korunur | Geçerli | Geçerli |
| `fil` | Korunur | Geçerli kayıtlı dil | İki harfli sözleşmenin dışında |
| `iw_IL` | `he-IL` | Alt çizgi geçersizdir; `iw-IL` kullanımdan kaldırılmış, geçerli bir etiket olarak kalır | Normalleştirilmiş `he-IL` kullanın |
| `en__US` | `en--US` | Geçersiz | Geçersiz |
| `x-default` | Korunur | Rankbeam'in içerik dili politikası reddeder | Geçerli yedek işaretçisi |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Çekirdek 3.17 ve önceki sürümlerden geçiş:** `Hreflang::isValid()` ve `parse()`, sunulan kodları katı biçimde doğrular. Çağıran kod bir Laravel dil kodu sağlıyorsa önce `fromLocale()` çağırın. HTML `lang` özniteliğini inceliyorsa baştaki/sondaki boşlukları kırpmadan veya normalleştirmeden `LanguageTag::isValidHtml()` kullanın. Kullanımdan kaldırılmış kayıtlı etiketler HTML için geçerli kalır; normalleştirme yalnızca IANA'nın açıkça tercih edilen takma adlarını uygular, `en-UK` kodunun `en-GB` anlamına geldiğini tahmin etmez. Bozuk girdiler, denetim bunları bildiremeden önce süzülüp çıkarılmaz.

Doğrulayıcı, IANA'nın **2026-08-08** tarihli kayıt verilerini kaynak hash'leri ve yeniden üretilebilir oluşturucuyla birlikte paketler. RFC 5646 yapısını, kayıtlı alt etiketleri, extlang öneklerini ve yinelenen varyant/uzantıları kontrol eder. Eski biçimiyle kabul edilen etiketleri ve özel kullanım aralıklarını destekler. Varyant öneki önerileri zorunlu geçerlilik kuralları değildir; uzantı ad alanları ve yapı kontrol edilirken CLDR seçenek anlamları ve özel kullanımın anlamı API dışında kalır. ICU veya çalışma sırasında indirme gerekmez. Bkz. [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) ve [HTML dil tanımı](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes).

Pro **2.38+**, bulunmayan veya boş `lang` değerini bilinmeyen/eksik olarak raporlar; bozuk sunulan baytlar `html_lang_invalid` üretir. Yazı sistemi uyuşmazlığı kontrolleri gerçek yazı sistemi alt etiketini veya IANA'nın kayıtlı varsayılanını kullanır. Özel/uzantı yükleri ve tanınmayan diller Latin varsayımı doğurmaz. Desteklenmeyen yazı sistemi grupları hakkında yargı verilmez. Bu kontroller tam bir dil algılayıcısı değildir.

Karşılıklılık, kaynak sayfanın geçerli kendine başvuru kodlarını kullanır; bunlar yoksa geçerli, Google uyumlu HTML dilini kullanır. Başka dil kodu altındaki geri dönüş URL'si kontrolü geçmez. Kaynak sayfanın dil kodu belirlenemiyorsa sonuç `hreflang_target_unverified` kalır. Yinelenen hedef URL'ler mevcut alternatif/gövde sınırları içinde bir kez getirilir; SSRF korumaları, yönlendirme reddi ve doğrulanamayan hataların ele alınışı yürürlükte kalır.

## Şema grafında `inLanguage` {#inlanguage-in-the-schema-graph}

`WebPage` düğümü, sayfanın çözümlenen dil kodundan `inLanguage` taşır (`it_IT` → `it-IT`); `ArticleSchema::fromModel()` bunu kayıtlı `seo_meta` dil kodundan alır. `WebSite` düğümü dillerini yapılandırmadan alır:

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Bölgesel arama motorları {#regional-search-engines}

`seo:robots-txt` arkasındaki tarayıcı kataloğu artık Google/Bing dışındaki dünyada önemli olan klasik web arama tarayıcılarını da içerir: Yandex, Baidu, Naver (`Yeti`), Seznam, Sogou, 360, Cốc Cốc ve DuckDuckGo. `search_engine` amacıyla etiketlenirler ve varsayılan olarak izinlidirler. Politikaya ve bot başına değişikliklere katılırlar; böylece Çin'e hizmet vermeyen bir mağaza bu iki tarayıcının mağazanın bant genişliğini kullanmasını önleyebilir:

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` ve `match()` yalnızca yapay zekâ botlarını kapsar (Pro yapay zekâ botu günlüğü ve tüm “N yapay zekâ tarayıcısı” sayıları değişmez). Arama motorlarını almak için `searchEngines()`, `all(true)` veya `match($ua, true)` kullanın. Bkz. [yapay zekâ tarayıcı denetimi](/tr/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
Tarayıcı ve doğrulama etiketi desteği, Baidu'da keşfedilmeyi, dizine eklenmeyi veya sıralamayı garanti etmez.
:::

## Site doğrulama {#site-verification}

Sahiplik belirteçleri, yapılandırılmış motor başına bir meta etiketi olarak her sayfada üretilir (Google etiketi her yerde kabul eder; Yandex, Baidu ve Naver kök sayfaya bakar, o da kapsanır). Boş bıraktığınız anahtar için hiçbir çıktı üretilmez:

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

Bir değer, belirteç listesi olabilir (Google her mülk sahibi için bir tane verir).

## Her yazı sisteminde OG görselleri {#og-images-in-every-script}

Paketle gelen kart yazı tipi Latin, Kiril ve Yunancayı kapsar. Diğer her yazı sistemi, `seo:og-images` çalıştıran makinede kurulu yazı tipine bağlıdır; CJK yazı tipi 16 MB'dan büyük olduğu için başka yazı tipi paketlenmez. Şablonlar artık yazı sistemine göre yedek yazı tipi listesi taşır (`seo.og_image.font_stack`; Han karakterlerinin doğru bölgesel glif biçimlerini alması için sayfa dilinin Noto CJK ailesi öne alınır). Makinede oluşturulacak başlık için yazı tipi yoksa komut yazı sistemi başına bir kez uyarır:

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Debian/Ubuntu'da: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. Ayrıntılar [oluşturulan OG görsellerinde](/tr/guide/og-image#fonts-and-non-latin-scripts).

## Birden fazla dilde `llms.txt` {#llms-txt-in-several-languages}

`seo.llms_txt.alternates` açıkken, başka dillerde de bulunan sayfanın madde işareti `Also in: [it](…), [de](…)` ile biter: politikadan geçirilmiş alternatifler, `x-default` ve sayfanın kendisi çıkarılarak sunulur. Varsayılan olarak kapalıdır.

## Unicode URL'ler {#unicode-urls}

Rankbeam URL'lerinizi asla slug'a dönüştürmez veya yeniden yazmaz; `/città/` veya `/検索` gibi yollar her çıktıda değişmeden kalır. IDN ana makineli (`https://münchen.example/`), Unicode veya yüzde kodlamalı yol içeren kanonik URL'ler denetim tarafından kabul edilir (`Rankbeam\Seo\I18n\Url::isValid()`, PHP'nin yalnızca ASCII kabul eden `FILTER_VALIDATE_URL` doğrulamasının yerini alır). URL başına tek biçim kullanın: ham Unicode *veya* yüzde kodlaması; ikisini birden değil. Böylece kanonik, hreflang ve site haritası girdileri bayt düzeyinde eşit karşılaştırılır.

## Hangi diller destekleniyor ve bu ne anlama geliyor? {#which-languages-are-supported-and-what-that-means}

Paketler aşağıdaki on yedi dil kodu için metinleri ve analiz yönlendirmesini sunar. Bu tablo mühendislik kapsamını açıklar; anadili konuşuru editoryal onayı veya yapılandırılmamış bir makinede garanti edilmiş görüntüleme anlamına gelmez. Japonca/Çince sözcük analizi kullanılabilir ICU gerektirir; yoksa etkilenen sözcük tabanlı kontroller atlanır. Latin dışı görüntüleme uygun yazı tipleri gerektirir. Yönlendirme her iki depoda testlerle doğrulanır: çekirdekteki `tests/Feature/I18n/SupportedLanguagesTest.php` dil listesini, hreflang kodlarını ve bütçeleri sabitler; Pro'daki `tests/Feature/OnPage/LanguageSupportMatrixTest.php` analiz motorlarını sabitler. Böylece bir satır artık doğru değilse CI başarısız olur.

| Dil | Dil kodu | Başlık / açıklama | Sözcük sayımı | Anahtar kelime eşleştirme | Okunabilirlik |
|---|---|---|---|---|---|
| İngilizce | `en` | 60 / 160 | boşluklar | Snowball | Flesch Reading Ease |
| İtalyanca | `it` | 60 / 160 | boşluklar | Snowball | Gulpease |
| Almanca | `de` | 60 / 160 | boşluklar | Snowball | Wiener Sachtextformel |
| Fransızca | `fr` | 60 / 160 | boşluklar | Snowball | Kandel-Moles |
| İspanyolca | `es` | 60 / 160 | boşluklar | Snowball | Fernández-Huerta |
| Portekizce (Brezilya) | `pt_BR` | 60 / 160 | boşluklar | Snowball | Martins |
| Felemenkçe | `nl` | 60 / 160 | boşluklar | Snowball | Flesch-Douma |
| Türkçe | `tr` | 60 / 160 | boşluklar | Snowball | Ateşman |
| Rusça | `ru` | 60 / 160 | boşluklar | Snowball | Oborneva |
| Lehçe | `pl` | 60 / 160 | boşluklar | Snowball | Pisarek |
| Japonca | `ja` | 30 / 80 | ICU sözlüğü | tam eşleşme, harf katlamalı | sezgisel, **puansız** |
| Çince (Basitleştirilmiş) | `zh_CN` | 30 / 80 | ICU sözlüğü | tam eşleşme, harf katlamalı | sezgisel, **puansız** |
| Çince (Geleneksel) | `zh_TW` | 30 / 80 | ICU sözlüğü | tam eşleşme, harf katlamalı | sezgisel, **puansız** |
| Korece | `ko` | 30 / 80 | boşluklar | tam eşleşme, harf katlamalı | sezgisel, **puansız** |
| Yunanca | `el` | 60 / 160 | boşluklar | Snowball | LIX |
| Ukraynaca | `uk` | 60 / 160 | boşluklar | tam eşleşme, harf katlamalı | LIX |
| Çekçe | `cs` | 60 / 160 | boşluklar | Snowball | LIX |

Tablonun açıkça belirttiği üç nokta:

- **Snowball, Pro 2.37'den itibaren paketlenir.** On iki dil, isteğe bağlı paketlerden bağımsız olarak sabitlenmiş 3.1.1 algoritmalarını kullanır. Ukraynaca ve CJK özdeşlik eşleştirmesi kullanır; paket bunlar için sözcük eki kuralları uydurmaz. Özdeşlik eşleştirmesi çekimli biçimleri kaçırabilir; kök bulma ise farklı sözcükleri birleştirebilir. Bkz. [motor kontrolleri ve geçiş notları](/tr/pro/on-page-checklist#upgrading-from-pro-2-36).
- **“Sezgisel, puansız” ile “LIX” aynı şey değildir.** Japonca, Çince ve Korece bu pakette puansız yöntem kullanır: kontrol listesi, cümle uzunluğu ve kanji oranından bir *düzey* bildirir, puanı `null` olur ve yapılandırmanız ne olursa olsun tavsiye niteliğinde kalır. Yunanca, Ukraynaca ve Çekçe için burada özel formül uygulanmadığından LIX kullanılır. LIX hece bilgisi gerektirmez; ancak eşikleri her dil için kalibre edilmemiştir. Tüm formüllerin girdileri tahminler içerir; bkz. [istatistik sözleşmesi](/tr/pro/on-page-checklist#text-statistics-and-api-limits).
- `TRANSLATING.md` bir anadili konuşurunun incelediğini belirtmedikçe **çeviriler ilk taslaktır**. İtalyanca incelenmiştir; diğerleri inceleyen birini bekler. Birini incelemek, dilinizin pakette katkı olarak anılmasını sağlamanın en düşük maliyetli yoludur.

Listelenmeyen dil kodları İngilizce metinlere, yazı sistemi/varsayılan bütçelerine, özdeşlik tabanlı anahtar kelime eşleştirmesine ve LIX veya sezgisel okunabilirliğe dönebilir. Bu yedek davranış doğrulanmış dil desteği değildir. Kontrol listesinin `analysis` bloğu yazı sistemini, bölümleyiciyi, kök bulucuyu ve okunabilirlik yöntemini belirtir; etiketlerin yanı sıra kullanılabilirliğini ve atlanan değerlendirmeleri de inceleyin.

### Yerelde önemli arama motorlarına ulaşma {#reaching-the-search-engines-that-matter-locally}

Bir dili sunmak yalnızca metinle ilgili değildir. Tarayıcı kataloğu Google ve Bing'in yanında Yandex, Baidu, Naver'ın Yeti'si, Seznam, Sogou, 360 ve Cốc Cốc'u içerir; `seo.verification` bunların site doğrulama etiketlerini üretir. Korece site için Naver, Çekçe site için Seznam, Ukraynaca veya Rusça site için Yandex. Bkz. [Bölgesel arama motorları](#regional-search-engines) ve [Site doğrulama](#site-verification).

## Diğer paketler ne ekler? {#what-the-other-packages-add}

- **laravel-seo-filament**, anlık sayaçlar ve SERP önizlemesi için aynı uzunluk politikasını okur. Ayrıca (1.9) [dil başına bir `seo_meta` satırı](/tr/guide/filament#several-languages) düzenler: kendi sayaçları, önizlemesi ve yedek değer göstergeleriyle dil başına bir sekme veya çevrilebilir içerik eklentisinin dil seçicisini izleme.
- **laravel-seo-pro**, taramanın `title_length` / `description_length` kontrollerinde ve yapay zekâ yardım istemlerinde bu politikayı okur; ayrıca (2.34) sayfayı kendi dilinde analiz eder. Çince, Japonca ve Tayca için ICU sözcük bölümleme; Snowball kök bulma; bu `CaseFolder` üzerinden dile duyarlı anahtar kelime eşleştirmesi; on dil için tahmini girdilerle yayımlanmış okunabilirlik formülleri; CJK için etiketli sezgisel yöntemler, Yunanca/Ukraynaca/Çekçe için açıkça belirtilen LIX; on altı dil için durak sözcükleri; `html lang` ve hreflang karşılıklılığı tarama kontrolleri; sayfanın dilini belirten yapay zekâ istemleri ve dompdf'in çizemediği yazı sistemleri için Chrome ile oluşturulan rapor. Bkz. [sayfa içi kontrol listesi](/tr/pro/on-page-checklist#keyword-matching), [tarama sorunları](/tr/pro/scan-issues), [yapay zekâ yardımı](/tr/pro/ai-assist#output-language) ve [raporlar](/tr/pro/reports#reports-in-every-script-browsershot-renderer).

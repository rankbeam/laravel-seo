---
description: "Anahtar kelimeye dayalı geçti/uyarı/başarısız sayfa içi kontrol listesi: odak anahtar kelime seçin; başlık, URL, giriş paragrafı, meta veri, uzunluk, görseller ve okunabilirliği kontrol edin."
---

# Sayfa içi kontrol listesi: anahtar kelimeye dayalı, geçti/uyarı/başarısız {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

Sayfa içi kontrol listesi, RankMath veya Yoast kullanıcısının beklediği canlı editoryal geri bildirim döngüsüdür. Odak anahtar kelime seçin ve “bu sayfa onun için optimize edilmiş mi?” kontrollerini trafik ışığı renklerinde görün: başlıkta, URL'de, giriş paragrafında ve meta açıklamada anahtar kelime; ayrıca uzunluk, görseller, iç bağlantılar ve **okunabilirlik**.

Model, [çözümleyici](/tr/concepts/resolver-precedence) ve sayfanın kendi metni üzerinden **istek içinde** çalışır; kuyruk veya ağ kullanmaz ve bilinçli olarak **sayısal değildir**.

::: tip Kontrol listesi ≠ puan
Kontrol listesi **yalnızca geçti / uyarı / başarısız** sonucu verir ve [Pro SEO puanından](/tr/pro/scoring) tamamen ayrıdır. Puanlama ölçütleriyle kod paylaşmaz ve puanı asla değiştiremez; editoryal ipuçları sayısal ölçütlerden ayrı kalır. Özellikle anahtar kelime yoğunluğu ve okunabilirlik **tavsiye niteliğindedir**; aşağıya bakın.
:::

## Neleri kontrol eder {#what-it-checks}

| Kontrol | Grup | Aradığı şey |
|---|---|---|
| `keyword_in_title` | keyword | Odak anahtar kelime SEO başlığında yer alıyor. |
| `keyword_in_description` | keyword | Odak anahtar kelime meta açıklamada yer alıyor. |
| `keyword_in_url` | keyword | Odak anahtar kelime URL slug'ında yer alıyor. |
| `keyword_in_first_paragraph` | keyword | Odak anahtar kelime giriş paragrafında yer alıyor. |
| `keyword_density` | keyword | **Tavsiye.** Yoğunluk doğal okunuyor; hedef oran yok, aşağıya bakın. |
| `title_length` | meta | Başlık, düzenleyici ve taramayla aynı aralıkta: core [uzunluk politikasına](/tr/guide/multilingual#title-and-description-budgets-per-script) göre Latin metinde 30–60, CJK'de ~15–30 (Pro 2.33). |
| `description_length` | meta | Açıklama aynı aralıkta: Latin için 70–160, CJK için ~35–80. |
| `content_length` | content | Yeterli gövde metni var; sözcük sayısı aralıkları yapılandırılır. |
| `readability` | content | **Tavsiye.** Seçilen formülle (on dil), LIX yedek yöntemiyle veya Japonca, Çince ve Korece için etiketlenmiş, puanlanmayan sezgisel yöntemle tahmini okunabilirlik düzeyi. |
| `has_image` | media | İçerik en az bir görsel içeriyor. |
| `internal_links` | links | İçerik, ilgili iç sayfalara bağlantı veriyor. |

Odak anahtar kelime atanmamışsa anahtar kelime kontrolleri **atlanır**; ne geçer ne başarısız olur. Kontrol listesi bir tane eklemenizi söyler. [Odak anahtar kelime alanı](/tr/guide/filament) veya `saveSEO(['focus_keywords' => …])` ile ekleyin.

### Anahtar kelime eşleştirme {#keyword-matching}

Anahtar kelime ve metin, **büyük/küçük harf katlama ve kök indirgeme** sonrasında karşılaştırılır. Böylece “espresso grinder”, “espresso grinders” ile eşleşir; analiz yerel ayarıyla Türkçe “İstanbul”, “istanbul” ile, Yunanca “ΟΔΟΣ”, “οδος” ile ve Almanca “Straße”, “STRASSE” ile eşleşir (core `CaseFolder`). Analiz edilecek yerel ayarı iletin: `SeoPro::checklistFor($post, 'it')` veya `--locale=it`.

Pro 2.36.1'den itibaren anahtar kelimeler, eş anlamlılar ve alan metni, kök indirgemeden önce aynı sözcük ayırıcıyı kullanır. Eşleşme, ardışık **tam sözcük birimleri** gerektirir: `cat`, `education` ile eşleşmez; Japonca ifadeler gövdeyle aynı ICU sözcük sınırlarını kullanır. Kesme işaretleri ve kısa çizgiler birimleri ayırır; dolayısıyla `meta-tag`, `meta tag` ile eşleşir ve düz/kıvrık kesme işaretleri aynı davranır. Birleşen işaretler harflerine bağlı kalır. Harf katlama aksanları korur; belirli bir dilin kök indirgeyicisi ek indirgemeler uygulayabilir.

Geçiş sayımı, her konumda eşleşen en uzun anahtar kelime/eş anlamlıyı seçer ve o aralığı bir kez sayar. Yinelenen eş anlamlılar ve örtüşen kısa alternatifler yoğunluğu şişirmez. Örneğin `seo tools` eş anlamlısına sahip `seo` anahtar kelimesi, `seo tools seo` içinde iki kez geçer. Boşluksuz yazı sistemlerinde sözlük tabanlı sözcük sınırları için ICU yine gereklidir; regex yedek yöntemi bu sınırları sağlayamaz.

Pro 2.37'den itibaren kök indirgeme, **paketle gelen Snowball 3.1.1 alt kümesini** kullanır. Ek Composer paketi gerektirmez ve çalışma sırasında hiçbir şey indirmez. PHP 8.2 desteği sürer.

| Motor | Ne zaman | Diller |
| --- | --- | --- |
| `snowball` | Varsayılan; mevcut `auto` ayarları aynı paket içi motoru seçer | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | Açıkça `seo-pro.checklist.analysis.stemmer = builtin` seçildiğinde | Yalnızca İngilizce; eski hafif çekim eki indirgeyicisini kullanır. Diğer dillerde kök indirgenmeden eşleştirme yapılır |
| `identity` | Desteklenmeyen dil veya açıkça `none` modu | Ukraynaca, Japonca, Çince, Korece, Tayca ve paket içi alt kümenin dışındaki diğer diller |

Karşılaştırmanın iki tarafı aynı motoru kullanır. Kök indirgeme, ekleri azaltan bir algoritmadır; eş anlamlı sözlüğü veya dilsel eşdeğerlik garantisi değildir. Örneğin Yunanca algoritması, kök indirgenmeden eşleştirmenin ayrı tuttuğu aksanlı ve aksansız biçimleri eşleştirebilir. Tam sözcük sınırları, `cat` ile `education` eşleşmesini yine engeller.

#### Pro 2.36'dan yükseltme {#upgrading-from-pro-2-36}

Mevcut `auto` yapılandırması, `wamania/php-stemmer` kurulu olsa da olmasa da artık tutarlı biçimde paket içi algoritmaları kullanır. Yükseltmeden sonra editoryal önerileri yeniden kontrol edin: güncellenmiş algoritmalar eşleşmeleri değiştirebilir; Türkçe, Yunanca, Lehçe ve Çekçede artık kök indirgeme vardır. İsteğe bağlı sarmalayıcının ek Katalanca, Danca, Fince, Norveççe, Romence ve İsveççe algoritmaları bu alt kümenin dışındadır ve artık kök indirgenmeden eşleştirme kullanır.

Önceki yalnızca İngilizce yedek yöntem için `SEO_PRO_CHECKLIST_STEMMER=builtin`, her dilde harf katlamalı, kök indirgenmeden eşleştirme için `none` ayarlayın. Bu ayarı değiştirdikten sonra önbelleğe alınmış yapılandırmayı yeniden oluşturun. Bu kontroller eski isteğe bağlı sarmalayıcının çok dilli algoritmalarını yeniden üretmez; tam olarak o sonuçları korumak, önceki Pro sürümünü korumayı gerektirir. Saklanan SEO meta verileri yeniden yazılmaz.

Paket içi adaptör, PHP 8.2, 8.3 ve 8.4'te sabitlenmiş 600.395 resmî sözlük/çıktı çiftinin tamamını geçer. Bu, algoritmaya uyumu gösterir; ana dilde editoryal onay değildir. Kaynak özetleri, yalnızca sözdizimini değiştiren PHP 8.2 uyarlaması ve üst kaynak lisansları paketle gelir. Kaynak dağıtımındaki `THIRD-PARTY-NOTICES.md` dosyasına bakın.

### Sözcük bölümleme {#word-segmentation}

Sözcük sayıları, anahtar kelime yoğunluğu ve okunabilirlik istatistikleri sözcüklere ihtiyaç duyar. Boşluklu yazı sistemlerinde regex, sabit harf/rakam birimi sınırlarını kullanır. Çince, Japonca ve Tayca sözlük tabanlı bölümleme gerektirir; regex bir paragrafı tek “sözcük” görebilir. **ext-intl** yüklüyse sözcük ayırıcı bu metin dizilerini ICU'nun sözlük tabanlı sınır yineleyicisine (`IntlBreakIterator::createWordInstance`) aktarır; bu, 東京タワーは東京のランドマークです metnini sözcüklere böler. ICU eksikse, kapalıysa veya başlatılamıyorsa Pro, etkilenen içerik uzunluğu, okunabilirlik ve anahtar kelime kontrollerini kurulum/yapılandırma mesajıyla atlar. Güvenilmez sayımı başarısızlığa dönüştürmez. Başlık uzunluğu ve boşluklu yazı sistemi eşleştirmesi dahil ilgisiz kontroller çalışmaya devam eder. `seo-pro.checklist.analysis.segmenter = regex`, sözlük bölümlemesi gereken metinlerde aynı kullanılamaz durumunu zorlar.

`analysis` bloğu, `word_count_status` (`available` veya `unavailable`) ve `segmentation_reason` (`null`, `missing_intl`, `disabled` veya `initialization_failed`) içerir. Alt düzey sözcük ayırıcı uyumluluk için yedek birimleri korur; bunları sözcük olarak yorumlamadan önce bu durumu inceleyin.

Oluşturulmuş sayfa taraması, yetersiz içerik hükmü yerine puanlanmayan `word_segmentation_unavailable` bildirimi üretir. Önceden doğrulanmış yetersiz içerik bulgusu tekrar kontrol edilebilene kadar açık kalır. Bu eksik tarama sayfa puanını yenilemez: mevcut puan ilk `scored_at` değerini korur; ilk taramada ise bölümleme çalışana kadar puan yoktur. Kontrolleri sürdürmek için PHP `ext-intl` eklentisini kurun, `auto` bölümleyicisini açıp yeniden tarayın.

### Sayfayı hangi motorlar analiz etti {#which-engines-analysed-the-page}

Her kontrol listesi bir `analysis` bloğu taşır: metnin baskın yazı sistemi, sözcük ayırıcı (`intl` / `regex`), kök indirgeyici (`snowball` / `builtin` / `identity`) ve okunabilirlik yöntemi (`formula` / `heuristic` / `lix`). Bunlar `toArray()` / `--json` içinde, Filament penceresinin altbilgi satırında ve `seo-pro:checklist` çıktısının son satırında yer alır:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

Altbilgi gerçekten kullanılan motoru belirtir; ext-intl yokken regex bölümlemesi ve kök indirgeme kapalıyken indirgemesiz eşleştirme de buna dahildir.

### Anahtar kelime yoğunluğu tavsiye niteliğindedir {#keyword-density-is-advisory}

Kontrol listesi sıralama için ideal anahtar kelime yoğunluğu tanımlamaz. Bu kontrol **tavsiye niteliğindedir**: farkındalık için sayıyı gösterir, asla başarısız olmaz ve **genel sayfa durumunu asla belirlemez**. Yüzde hedeflemek yerine tekrarların doğal okunup okunmadığını inceleyin.

### Okunabilirlik tavsiye niteliğindedir {#readability-is-advisory}

Kontrol listesi, analiz yerel ayarı için seçilen yöntemle okunabilirliği tahmin eder. Şu anda uygulanan formüller ve yedek yöntemler şunlardır:

| Yerel ayar | Formül | Kaynak |
| --- | --- | --- |
| İngilizce (`en`) | Flesch Reading Ease | Flesch 1948 |
| İtalyanca (`it`) | Gulpease Index | Lucisano & Piemontese 1988 |
| İspanyolca (`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| Fransızca (`fr`) | Kandel-Moles | Kandel & Moles 1958 |
| Almanca (`de`) | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| Portekizce (`pt`, `pt_BR`) | Brezilya Portekizcesine uyarlanmış Flesch | Martins ve diğerleri 1996 |
| Felemenkçe (`nl`) | Flesch-Douma | Douma 1960 |
| Rusça (`ru`) | Oborneva'nın Flesch uyarlaması | Оборнева 2006 |
| Türkçe (`tr`) | Ateşman | Ateşman 1997 |
| Lehçe (`pl`) | Pisarek (eğitim yılı endeksi, normalleştirilmiş) | Pisarek 1969 |
| Japonca, Çince, Korece (`ja`, `zh`, `ko`) | **sezgisel, puansız**; aşağıya bakın | — |
| Yunanca, Ukraynaca, Çekçe (`el`, `uk`, `cs`) | LIX: burada özel formül uygulanmadığı için yedek yöntem; bu diller için kalibre edilmemiştir | Björnsson 1968 |
| diğerleri | LIX (Läsbarhetsindex): kalibre edilmemiş yedek yöntem | Björnsson 1968 |

Gösterilen **0–100 puanı (yüksek = daha kolay)** pakete ait bir kabuldür. Flesch ailesi ve Gulpease sonuçları bu aralığa sıkıştırılır; Wiener sınıf düzeyi, Pisarek ve LIX endeksleri bu ölçeğe dönüştürülür. Farklı dillerde eşit puanlar, eşit okuma güçlüğü anlamına **gelmez**. Katsayı formülleri yayımlanmış çalışmalardan gelir; Rankbeam'in sözcük birimi, cümle ve hece tahminleri bütün bir ölçüm aracı olarak doğrulanmamıştır. Anlamayı veya arama sıralamalarını öngörmezler.

Pro 2.37.1'den itibaren Türkçe ve Rusçada yan yana ünlüler ayrı hece sayılır (`saat`: 2; `поэт`: 2). Diğer hece tahmincilerinin hâlâ sınırları vardır: ünlü grupları bazı ayrı okunan ünlüleri ve sessiz ünlüleri kaçırır. İngilizcede küçük bir istisna listesi bulunur; telaffuz sözlüğü değildir. Örneğin İspanyolca `país` ve Fransızca `monde` yanlış sayılabilir. Bilmediğiniz sözcükleri ve özel adları elle inceleyin.

#### Metin istatistikleri ve API sınırları {#text-statistics-and-api-limits}

HTML blok etiketleri ve `br` öğeleri metni ayırır; satır içi vurgu sözcüğüne bağlı kalır. Sıradan HTML içindeki kaynak satır sonları boşluğa dönüşürken düz metin ve `pre` satır sınırlarını korur. Script, style ve noscript içerikleri dışarıda bırakılır. Çıkarma işlemi CSS görünürlüğünü veya oluşturulmuş sayfayı değerlendirmez. Karakter başvuruları bir kez çözülür. Formül istatistiklerinde harf/rakam dizileri sözcüktür; tek başına noktalama değildir. Kısa çizgiler ve kesme işaretleri sözcükleri ayırır. Rakamlar birim sayılır, ancak onlar için hece tahmin edilmez. Harfler özgün metinden sayılır; kök indirgeme veya Almanca `ß` → `ss` harf katlaması uzunluklarını değiştirmez.

Cümle tahminleri, sonlandırıcı `. ! ? 。 ！ ？` işaretlerinde ve blok/satır sınırlarında bölünür; sonlandırılmamış son parçayı dahil eder, ondalıkları ve küçük bir yaygın kısaltma listesini (`Dr.`, `Prof.`, `e.g.` ve benzer İngilizce biçimler) korur. Bu nedenle başlıklar ve liste öğeleri cümle sayılabilir. Diğer kısaltmalar, alıntılar, sayılar, karma yazı sistemleri ve az noktalama içeren metinler özellikle dikkat gerektirir. Seçilen yerel ayar yöntemi belirler; her cümlenin o dilde olup olmadığını tespit etmez.

Doğrudan hesaplayıcının `toArray()` çıktısı bir `assessment` bloğu ekler:

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method`, `formula`, `lix`, `heuristic` ve `unavailable` durumlarını ayırır; formül meta verisi olmadan elle oluşturulan sonuçlarda `unspecified` kullanılır. Boş veya yalnızca noktalama içeren girdi `insufficient` olur ve `isValid()` false değerindedir. Eski `score: 0` değeri bir güçlük puanı değil, kullanılamazlık göstergesidir. Mevcut İngilizce/İtalyanca sınıf düzeyi etiketleri yaklaşık değerlerdir; diğer dillere ve sezgisel/LIX sonuçlarına artık bu okul sınıfı etiketleri verilmez. `calculateFleschKincaid()`, uyumluluk için genel metot adını korur, ancak Flesch-Kincaid sınıf düzeyini değil **Flesch Reading Ease** hesaplar.

Formül testleri, adı belirtilen on formülün tamamı ve LIX için bağımsız sayılmış girdileri ve beklenen aritmetiği sabitler. Hesaplama davranışını doğrular; ana dilde editoryal kaliteyi doğrulamaz. Okunabilirlik Pro SEO puanından ayrı kalır.

::: warning Japonca, Çince ve Korece: etiketli sezgisel yöntem, asla sayı değil
Rankbeam bu diller için puanlanmayan yöntem uygular. Hesaplayıcı, pakete özgü yaklaşık kurallardan bir **düzey** döndürür: karakter cinsinden ortalama cümle uzunluğu (ja ≤ 40/60/80, zh ≤ 30/45/60) veya sözcük cinsinden uzunluk (ko ≤ 12/18/25); Japonca için ayrıca kanji oranı. Yaklaşık %45'in üzeri, paketin güçlük aralıklarında bir basamak daha yüksek değerlendirilir. Sonuç `heuristic: true` olarak işaretlenir ve **puan null olur**. Kontrol listesi mesajı “sezgisel” der; **`readability.advisory` ne olursa olsun bu dillerde kontrol tavsiye niteliğinde kalır**. Yaklaşık kural bilgi verir; kontrol listesinin genel durumunu asla belirlemez. `ja`/`zh` için kontrol listesi sözcük sayıları çalışan ICU bölümlemesi gerektirir; yoksa bu kontroller atlanır.
:::

Anahtar kelime yoğunluğu gibi okunabilirlik de **varsayılan olarak tavsiye niteliğindedir**: yazarı bilgilendirir ancak genel sayfa durumunu **belirlemez**. Yoast'ın Readability ve SEO analizlerini ayırmasıyla aynı ayrımdır. Asgari sözcük sayısının altında **atlanır**; yetersiz sayfalar okunabilirliğin değil, `content_length` kontrolünün işidir. Zor okunan sayfanın başarısız olmasını istiyorsanız belirleyici hâle getirin:

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## Kontrol listesini okumak {#reading-the-checklist}

### Panelsiz {#headless}

Pro 2.36, çözümlenen meta verileri, `getContentForSEO()` ve odak anahtar kelimeleri istenen içerik yerel ayarında okur; kontrol listesi etiketleri ise operatörün dilinde kalır. Açık yerel ayar verilmezse çeviri modelinin `seoData()` varsayılanına uyulur. Filament eylemi alanın dil sekmesini veya sayfanın dil seçicisini izler.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Her `CheckResult`; `id`, `group`, `label`, `status`, `message`, isteğe bağlı `recommendation` ve `advisory` bayrağı taşır.

### Komut {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### Düzenleyicide (Filament, isteğe bağlı) {#in-the-editor-filament-optional}

[`rankbeam/laravel-seo-filament`](/tr/guide/filament) kuruluysa odak anahtar kelime alanında **Sayfa içi kontrol listesi** eylemi görünür. Tıklamak, kaydın kaydedilmiş içeriği için aynı geçti/uyarı/başarısız kontrollerini içeren pencereyi açar. Filament paketi hiçbir zaman Pro'ya bağımlı değildir; eylem, yapay zekâ önerilerinin kullandığı aynı tek yönlü genişletme kancasıyla bağlanır. Panelsiz kurulumlar etkilenmez.

## Yapılandırma {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Özel kontrol yazmak {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Sınıfını `seo-pro.checklist.rules` listesine ekleyerek kaydedin. Kontrol, [tarama sorun kodu](/tr/pro/scan-issues) kimliğini **yeniden kullanmamalıdır**; kontrol listesi ayrı, puanlamaya katılmayan bir ad alanıdır.

## İçerik nasıl okunur {#how-the-content-is-read}

`SeoPro::checklistFor($model)` şunları analiz eder:

- **Başlık / açıklama**: düzenleyici sayaçlarının ve taramanın ölçtüğü aynı *çözümlenmiş* (geçerli) değerler; kontrol listesi bunlarla çelişmez.
- **İçerik**: `$model->getContentForSEO()`; core `HasSEO` erişimcisi, varsayılan olarak `content` / `body` / `text`. Gerçek gövde metnine işaret etmesi için modelinizde geçersiz kılın.
- **URL**: `$model->getUrlForSEO()`.
- **Odak anahtar kelimeler**: saklanan `seo_meta.focus_keywords`.

Bu yalnızca analizdir: sayfa getirilmez ve hiçbir şey yazılmaz.

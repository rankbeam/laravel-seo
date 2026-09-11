---
description: "Pro taramasının bildirdiği her sorunun dayandığı sabit sorun kodu kaydı. Her kodun önem derecesi ve alanı sabittir; panolar ve dışa aktarımlar mesajları değil, kodları okur."
---

# Tarama sorunları: sorun kodu kaydı {#scan-issues-—-the-issue-code-registry}

Pro taramasının bildirdiği her sorun, tek bir kayıt olan `Rankbeam\Seo\Pro\Scanning\IssueRegistry` içinden alınan **sabit sorun kodudur**. Tarayıcılar işlem sırasında kod uydurmaz; her sorunu `IssueRegistry::make()` üzerinden oluşturur. Bu işlem önem derecesini ve alanı kayıttan atar, **tanımlı olmayan her kodu reddeder**. Böylece aşağıdaki katalog, üzerine sistem kurabileceğiniz bir sözleşmedir: panolar, dışa aktarımlar ve [Pro puanı](/tr/pro/scoring) mesajları ayrıştırmak yerine kodları okur. Ücretsiz [`seo:audit`](/tr/guide/audit), daha dar kapsamlı ve bazı hreflang kodları farklı olan kendi core meta veri kaydını kullanır.

Her kod şunları taşır:

- **id**: `seo_scan_issues.issue_type` olarak saklanan sabit dize.
- **severity**: `critical`, `warning` veya `notice`; **kod başına sabittir**. Önem derecesini değiştirmek yerine kodu ayırırız.
- **field**: ilgili `seo_meta` alanı veya sayfa düzeyindeki bulgular için _page_.
- **execution class**: bunu tespit etmek için tüketicinin neye ihtiyaç duyduğu; aşağıda açıklanır.
- **evidence**: sorunun `context` dizisinin taşıdığı anahtarlar.

## Yürütme sınıfları {#execution-classes}

Her kontrol, çalışmak için ihtiyaç duyduğu şeye göre tam olarak üç sınıftan birine girer:

| Sınıf | Gerekenler | Çalıştırabilen |
|---|---|---|
| **metadata** | model ve core çözümleyicisi; sayfa getirilmez | model taraması (`PageScanner`); ücretsiz [`seo:audit`](/tr/guide/audit), meta veri kontrollerinin bir alt kümesini kapsar |
| **rendered** | sayfanın sunulan HTML'si; süreç içi kernel isteği veya dışarıdan getirme | URL taraması (`UrlScanner`) |
| **network** | _ayrı_ bir hedefi (başka yere işaret eden kanonik URL) doğrulamak için **dışarıya** istek | URL taraması, **her zaman `SsrfGuard` üzerinden** |

Ücretsiz süreç içi denetimin tam Pro taramasına eşit olamamasının nedeni budur. Sayfa oluşturmadan yalnızca **metadata** kodları hesaplanabilir; oluşturulmuş HTML'yi getirip kanonik hedefleri ağ üzerinden doğrulayan yalnızca Pro hattıdır. Kaydı sınıfa göre `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)` ile filtreleyin.

## Meta veri kodları {#metadata-codes}

Model ve çözümleyiciden `PageScanner` tarafından tespit edilir. `missing_title`, `missing_description` ve uzunluk kodları, sunulan `<head>` değerini ölçen oluşturulmuş URL taraması tarafından da üretilir; kodlar ve anlamları aynıdır.

| Kod | Önem derecesi | Alan | Kanıt | Anlamı |
|---|---|---|---|---|
| `missing_title` | critical | title | — | Başlık ve hesaplanabilir yedek değer yok. |
| `missing_description` | warning | description | — | Meta açıklama ve hesaplanabilir yedek değer yok. |
| `missing_og_image` | notice | og_image | — | Open Graph görseli ve hesaplanabilir yedek değer yok. |
| `missing_focus_keyword` | notice | focus_keywords | — | Odak anahtar kelime atanmamış. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | Başlık aynı yerel ayardaki başka sayfalarda da kullanılıyor. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | Açıklama aynı yerel ayardaki başka sayfalarda da kullanılıyor. |
| `title_too_long` | warning | title | `length`, `max`, `script` | Çözümlenen başlık, yazı sistemi için önerilen uzunluğu aşıyor (Latin için 60, CJK için ~30). |
| `title_too_short` | notice | title | `length`, `min`, `script` | Çözümlenen başlık, yazı sisteminin alt sınırının altında (Latin için 30, CJK için ~15). |
| `description_too_long` | warning | description | `length`, `max`, `script` | Çözümlenen açıklama, yazı sistemi için önerilen uzunluğu aşıyor (160 / ~80). |
| `description_too_short` | notice | description | `length`, `min`, `script` | Çözümlenen açıklama, yazı sisteminin alt sınırının altında (70 / ~35). |
| `robots_conflict_indexing` | critical | robots | `robots` | Robots yönergesi hem `index` hem `noindex` içeriyor. |
| `robots_conflict_following` | warning | robots | `robots` | Robots yönergesi hem `follow` hem `nofollow` içeriyor. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | Kendine işaret eden kanonik URL'ye sahip sayfa `noindex` durumunda. İncelenecek sezgisel bulgudur; sayfanın dizine eklenmesi gerektiğinin kanıtı değildir. Hem model hem oluşturulmuş URL taramasında üretilir. |
| `invalid_canonical` | critical | canonical | `canonical` | Kanonik değer geçerli URL değil. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | Kanonik URL, sayfadan farklı sunucuya işaret ediyor. |
| `shared_canonical` | notice | canonical | `canonical` | Birkaç sayfa aynı kanonik URL'yi bildiriyor. |
| `insecure_canonical` | warning | canonical | `canonical` | `https` sitesinde `http://` kanonik URL. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | Hreflang alternatifi, `x-default` veya geçerli BCP-47 dil kodu olmayan değer kullanıyor. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | Alternatifler bildirilmiş ancak hiçbiri sayfanın kendi yerel ayarına işaret etmiyor; kendine referans veren hreflang eksik. |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | Aynı hreflang kodu birden fazla URL'ye eşlenmiş; küme belirsiz. |
| `hreflang_missing_x_default` | notice | alternates | `languages` | Çok dilli hreflang kümesinde `x-default` yedek hedefi yok. |
| `aeo_missing_author` | notice | schema | — | Sayfanın yapılandırılmış verisindeki yazıda yazar varlığı yok; yazarlık / köken şemada açıkça belirtilmemiş. |
| `aeo_article_missing_date` | notice | schema | — | Sayfanın yapılandırılmış verisindeki yazıda yayın/değişiklik tarihi yok; yazının zaman çizelgesi şemada açıkça belirtilmemiş. |

Uzunluk eşikleri, core'un yazı sistemine duyarlı [uzunluk politikasından](/tr/guide/multilingual#title-and-description-budgets-per-script) gelir (Pro 2.33): Latin için 60/160, CJK için ~30/80. Grafem sayılır; böylece tarama düzenleyicinin karakter sayaçlarıyla çelişmez. Alt sınırlar (Latin için başlık 30, açıklama 70; CJK için yaklaşık yarısı), taramanın yetersiz optimizasyon eşiğidir. `script` bağlam anahtarı uygulanan grubu belirtir. Uzunluk, **çözümlenen** başlık/açıklamaya göre ölçülür: yedek değerler ve başlık son eki dahil gerçekten oluşturulan değer.

`hreflang_*` kodları, çözümleyicinin `alternates` alanından okunan, sayfanın bildirdiği hreflang alternatiflerini doğrular: geçersiz/yinelenen kodlar, eksik kendine referans ve çok dilli kümede eksik `x-default`. Yalnızca sayfa alternatifler bildiriyorsa çalışırlar. Sayfalar arası **karşılıklılık** (“geri dönüş etiketleri”) bu meta veri kontrollerinde incelenmez; aşağıdaki isteğe bağlı ağ kontrolü diğer sayfayı getirir.

`aeo_*` kodları **yanıt hazırlığı (AEO)** sinyalleridir: sayfanın yazı içeriği yapılandırılmış verisinde anlaşılabilir mi? Çözümlenen JSON-LD grafını okur ve **yalnızca** `author` varlığı (açık yazarlık / köken) veya `datePublished` / `dateModified` (açık zaman çizelgesi) eksik yazı türünde yapılandırılmış veri (`Article`, `BlogPosting`, `NewsArticle`, …) bildirildiğinde tetiklenir. Yazı içermeyen sayfa asla işaretlenmez. Varsayılan olarak açık `seo-pro.scan.checks.aeo` seçeneğine bağlıdır ve ücretsiz [`seo:audit`](/tr/guide/audit) ile aynı davranışı gösterir.

::: tip `missing_focus_keyword` koşula bağlıdır
Odak anahtar kelime bildirimi yalnızca **core** odak anahtar kelime iş akışı açıkken tetiklenir (`seo.keywords.enabled`, varsayılan `false`). Kapalıyken tarama, odak anahtar kelimesi olmayan sayfayı işaretlemez. Ücretsiz [`seo:audit`](/tr/guide/audit) komutu ve Filament düzenleyicisi **aynı** core seçeneğini okur; tarama, denetim ve düzenleyici hatırlatması daima uyumludur. Yalnızca tek bir etkinleştirme koşulu vardır.
:::

## Oluşturulmuş HTML kodları {#rendered-codes}

`UrlScanner` tarafından sunulan HTML'den tespit edilir. Aynı sunucudaki hedeflerde süreç içi kernel isteği kullanılır (dış trafik yok); dış hedeflerde korumalı getirme yapılır.

| Kod | Önem derecesi | Alan | Kanıt | Anlamı |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | URL, 4xx/5xx durumuyla yanıt verdi. |
| `empty_response` | critical | page | — | URL boş gövde döndürdü. |
| `missing_canonical` | notice | canonical | — | Oluşturulan head içinde `<link rel="canonical">` yok. |
| `noindex_page` | notice | robots | `robots` | Oluşturulmuş sayfa `noindex` durumunda (bilgi amaçlı). Aynı zamanda **kendine işaret eden kanonik URL** kullanan `noindex` sayfa, bunun yerine puanlanan `noindex_warning` durumuna yükseltilir. |
| `missing_h1` | notice | page | — | `<h1>` başlığı yok. |
| `multiple_h1` | notice | page | `count` | Birden fazla `<h1>` var (bilgi amaçlı). |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | İçerik görsellerinde `alt` özniteliği eksik. Açıkça belirtilen `alt=""` dekoratif sayılır ve işaretlenmez. |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter` | Gövde metni yapılandırılmış sözcük sayısının altında. Sözcükler kontrol listesi sözcük ayırıcısıyla sayılır: boşluklu yazı sistemlerinde boşluklar, Çince, Japonca ve Taycada ICU sözlük bölümlemesi (`segmenter: intl`; ext-intl gerekir). Böylece 400 sözcüklük Japonca yazı tek “sözcük” sayılmaz. |
| `mixed_content` | warning | page | `count`, `sample` | `https` sayfasında `http://` alt kaynakları. |
| `html_lang_missing` | notice | page | — | `<html lang>` yok veya boş. Yardımcı teknoloji uygun olmayan ses seçebilir. |
| `html_lang_invalid` | notice | page | `declared` | `lang` değeri BCP-47 etiketi değil (`english`, alt çizgili `en_US`, `jp`). |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script` | Görünür gövde metni, bildirilen dilin kullanmadığı yazı sisteminde: Japonca sayfada `lang="en"`, Latin metinde `lang="ru"`. Yalnızca yazı sistemi düzeyindedir; Latin metnin yanlış Latin dilini bildirmesi tahmin gerektirir ve tarama tahmin etmez. Gövde metninde en az 40 harf gerekir. |

## Ağ kodları {#network-codes}

`UrlScanner` tarafından yalnızca ilgili isteğe bağlı seçenek açıkken tespit edilir: kanonik hedef için `seo-pro.scan.url_checks.check_canonical_target`, hreflang alternatifleri için `check_hreflang_reciprocity`. Her hedef **`SsrfGuard` üzerinden** getirilir: şema izin listesi, sunucu kapsamı, özel IP reddi, yönlendirme/zaman/boyut bütçeleri. Yönlendirmeler **izlenmez**; böylece yönlendiren kanonik URL görünür olur. Kendine işaret eden kanonik veya alternatif atlanır; sayfanın kendisi zaten az önce getirilmiştir.

| Kod | Önem derecesi | Alan | Kanıt | Anlamı |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | Hedef, HTTP başlamadan önce `SsrfGuard` tarafından reddedildi. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | Kanonik URL, HTTP hatası döndüren sayfaya işaret ediyor. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | Kanonik URL yönlendiren sayfaya işaret ediyor; son URL'yi kullanın. |
| `canonical_target_noindex` | warning | canonical | `canonical` | Kanonik URL, kendisi de `noindex` olan sayfaya işaret ediyor. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | Kanonik hedef doğrulanamadı (koruma reddi / çözümlenememe). |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status` | Sayfanın bildirdiği alternatif, bu sayfayı geri bildirmiyor. Hreflang çifti yok sayılabilir; bu durum tek başına çeviriyi dizine eklenemez yapmaz. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason` | Alternatif getirilemedi (koruma reddi, hata durumu, yönlendirme, boyut sınırı aşımı); karşılıklılık hiç kontrol edilmedi. Kusur değil, kanıt yokluğudur. |

Karşılıklılık kontrolü, sayfa başına en fazla `hreflang_max_alternates` (varsayılan 10) hedef getirir; `x-default` dahildir, yinelenenler ve sayfanın kendisi atlanır. Yukarıdaki model düzeyindeki `hreflang_*` meta veri kodları *bildirilen* listeyi doğrular; diğer sayfaya ihtiyaç duyan kontrol bu taramadır.

Buradaki her ağ yolu ortak `SsrfGuard` kullanır. Tehdit modeli ve kalan TOCTOU riski notu için [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md) dosyasına bakın.

## Kodlar puanı nasıl etkiler {#how-codes-feed-the-score}

[Pro SEO puanı](/tr/pro/scoring), `100 −` toplam kesinti hesabıyla bulunur; puanlanan her sorun için yukarıdaki önem derecesine göre ağırlıklandırılmış sabit bir kesinti uygulanır. Çoğu kod hesaba katılır; bazıları bilinçli olarak dışarıdadır: `missing_focus_keyword` (tavsiye), `noindex_page` ve `multiple_h1` (bilgi), `blocked_url` / `canonical_target_blocked` / `hreflang_target_unverified` (“kontrol edemedik” bir kusur değildir), ayrıca `hreflang_*`, `html_lang_*` ve `aeo_*` kodları (şimdilik puanın dışında tutulan tavsiye sinyalleri). [Puanlama sayfası](/tr/pro/scoring), tam izin listesini ve her kodun kesintisini içerir.

## Sorun yaşam döngüsü {#issue-lifecycle}

Sorun, yalnızca problem varken bulunan bir satır değildir; yaşam döngüsü vardır. Tarama hedefin sorunlarını silip yeniden oluşturmak yerine **uzlaştırır**. Her sorunun sabit kimliği, hedef (model için `scannable_type` + `scannable_id` veya rota/site haritası hedefi için `url`) ile `issue_type` birleşimidir. Her kod, tarama başına hedef başına en fazla bir kez üretilir. “N sorunlu öğe” kodları (`missing_image_alt`, `mixed_content`, `hreflang_*`, …) örneklerini `count` / `sample` içeren tek satırda birleştirir; kimlik bu nedenle benzersizdir.

Her taramada, her hedef için:

- **Mevcut satırı olmayan** bulgu `open` olarak oluşturulur ve `detected_at` damgalanır;
- **Mevcut açık satırla eşleşen** bulgunun kanıtı yenilenir, ilk `detected_at` değeri korunur; her taramada sıfırlanmayan sabit *ilk görülme* zamanıdır;
- tamamlanan kontrolün **artık bulmadığı** açık sorun **`fixed`** işaretlenir (`resolved_at` damgalanır); satır **silinmez, korunur**, böylece gerçek düzeltme kaydedilir;
- **geri dönen** bir **`fixed`** sorunu yerinde **yeniden açılır** (gerileme) ve `detected_at` yeniden damgalanır;
- kullanıcının panoda **`ignored`** işaretlediği soruna dokunulmaz.

| Durum | Anlamı | Ayarlayan |
|---|---|---|
| `open` | Şu anda mevcut. | tarama (yeni veya hâlâ bulunan) |
| `fixed` | Önceden vardı, artık bulunmuyor. | tarama (otomatik), tekrar bulunmadığı sonraki çalıştırmada |
| `ignored` | Kullanıcı tarafından susturuldu; açık sayılarından ve puandan çıkarılır. | panodaki Yoksay eylemi |

Düzeltmeler artık atılmayıp kaydedildiğinden, markalı [rapor](/tr/pro/reports) raporlar arası anlık görüntü farkı yerine dönem boyunca **gerçek düzeltilen / yeni sayılarını** gösterebilir. Açık sayısını kullanan pano, [`seo-pro:scan-status`](/tr/pro/headless) komutu ve [puan](/tr/pro/scoring), yalnızca `open` durumunu filtreler; saklanan `fixed` satırları bunları asla şişirmez. Düzeltilen satırlar, onları çözen çalıştırmaya bağlanır ve normal tarama çalıştırması [saklama süresiyle](/tr/pro/production) temizlenir.

## Yapılandırma {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

Korumalı isteklerin yanıt boyutu bütçesi `seo-pro.http.max_response_bytes` değeridir (varsayılan 2 MB); aynı sunucudaki süreç içi tarama sınırlanmaz.

## Uyumluluk notu (sorun kodu değişikliği) {#compatibility-note-issue-code-rename}

Önceki tek `robots_conflict` kodu iki önem derecesi taşıyordu; her kodun tam bir önem derecesine eşlenmesi için ayrıldı:

| Eski kod | Yeni kod | Önem derecesi |
|---|---|---|
| `robots_conflict` (index + noindex) | `robots_conflict_indexing` | critical |
| `robots_conflict` (follow + nofollow) | `robots_conflict_following` | warning |

`robots_conflict` saklıyor veya buna göre filtreliyorsanız iki yeni kodu kullanacak şekilde güncelleyin.

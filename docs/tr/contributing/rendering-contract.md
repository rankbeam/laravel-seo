---
description: "Rankbeam SEO verilerini üretirken her ön yüzün head bölümünün karşılaması gereken temel kontrol listesi; çekirdek çıktı testlerinin ve örnek uygulamaların dayanağı."
---

# Çıktı Sözleşmesi {#the-rendering-contract}

Bu, Rankbeam SEO verilerini üretirken her ön yüzün `<head>` bölümünün karşılaması gereken **tek temel kontrol listesidir**. Şunların doğruluk kaynağıdır:

- Çekirdekteki çıktı biçimi birim testleri (`tests/Unit/Services/RenderingContractTest.php`): paket CI'ının kapsadığı, framework gerektirmeyen hızlı test aşaması.
- `rankbeam-examples` içindeki her teknolojiye özel örnek uygulamalar (Blade, Inertia + Vue / React / Svelte, Livewire): tarayıcı ve SSR testleri aynı koşulları gerçek DOM'da doğrular.
- Bu sözleşmeyi ihlal eden bir yöntemi asla belgelememesi gereken framework kılavuzları (Blade, Inertia ve JSON, Livewire).

Bir teknoloji bir maddeyi karşılayamıyorsa bu bir **hata veya belgelenmiş kısıttır**; sözleşmeyi gevşetmek için gerekçe değildir. Veri katmanı (`SEOResolver` → değiştirilemez `SEOData` → `TagRenderer`) framework'ten bağımsızdır. Yalnızca *çözümlenen verilerin DOM'a nasıl ulaştığı, istemci tarafındaki gezinmelerde nasıl korunduğu ve tarayıcı botlar tarafından nasıl görülebildiği* teknolojiye göre değişir. Bu sözleşme tam olarak bunları tanımlar.

> Bu belirtim bağımsız bir tasarım incelemesiyle sağlamlaştırılmıştır.
> Yalnızca önemli ölçüde değişirse yeniden inceleyin.

---

## 1. Değerler — uyumlu bir `<head>` neler içerir? {#_1-values-—-what-a-compliant-head-contains}

### Başlık, açıklama ve kanonik URL {#title-description-canonical}

- *Çözümlenen* başlığı taşıyan **tam olarak bir `<title>`**; son ek asla iki kez eklenmez (çözümleyici, başlığın zaten aynı ekle bitip bitmediğini kontrol ederek `seo.title_suffix` değerini bir kez ekler).
- Yalnızca bir açıklama çözümlendiyse **bir meta açıklaması**; boş etiket yoktur.
- **Bir `<link rel="canonical">`**.

### Robots {#robots}

- `<meta name="robots">` **yalnızca yönerge site varsayılanından farklıysa** üretilir. Gereksiz bir `index,follow` fazlalıktır; etiketin *yokluğunu* tarayıcı bot zaten `index,follow` olarak yorumlar. Karşılaştırma boşluklara duyarsızdır (`index, follow` ≡ `index,follow`); farklı yönerge **aynen** üretilir. `seo.robots.emit_default = true` etiketi zorunlu kılar.
- Deterministik **gelişmiş yönergeler** desteklenir: `noindex`, `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`, `max-video-preview`, `notranslate`, `unavailable_after`. Bunlar çözümlenen dize değerleridir; **öncelikleri çözümleyici zincirine bağlıdır** (genel → rota → model → açıkça atanan). Aynı girdiler ⇒ aynı çıktı.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`, `og:locale`.
- `article:*` (`published_time`, `modified_time`, `author`, `section`, `tag`) **yalnızca `og:type === 'article'` olduğunda ve değer gerçekse** üretilir; asla uydurulmaz, makale olmayan sayfalarda üretilmez.
- `og:image` ile birlikte, **biliniyorsa** `og:image:width` / `og:image:height` / `og:image:alt` ve `og:image:type` üretilir. Birden çok görsel **gruplandırılır**: her `og:image` öğesini hemen kendi boyut/alternatif metin/tür özellikleri izler.

### Twitter Cards {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` ve `twitter:image:alt` (görselin alternatif metni biliniyorsa).
- `twitter:site` ve `twitter:creator` **isteğe bağlı ve birbirinden bağımsızdır**. Biri olmadan diğeri bulunabilir; hiçbiri diğerinden uydurulmaz.

### Hreflang ve dil kodu {#hreflang-locale}

- Hreflang, modelin `getSEOAlternates()` kancası üzerinden doğrudan desteklenen bir çözümleme yoluna sahiptir.
- Hreflang alternatifleri varsa **mutlak, normalleştirilmiş ve dil başına benzersizdir**; veriler tamamlandığında karşılıklıdır. `x-default` yalnızca yapılandırılmışsa bulunur.
- `og:locale:alternate`, **yalnızca** gerçek bir sosyal paylaşım varyantı olan dilleri yansıtır (`en-US` → `en_US` eşlemesini uygulayın; karşılaştırmayı eşlenmiş biçim üzerinden yapın, birebir eşitlik aramayın).
- `<html lang>`, çözümlenen dil koduyla uyumludur (`<html>` öğesini *uygulama* üretse de bu madde sözleşmeye dahildir).

### Sayfa başına JSON-LD {#per-page-json-ld}

- Ayrıştırılabilir ve `</script>` dizisine karşı güvenlidir (veri `JSON_HEX_TAG` ile kodlanır; hiçbir değer script öğesini erken kapatamaz. Bu, kalıcı XSS saldırılarına karşı korumadır).
- **Birden çok `<script>` bloğu VEYA birleştirilmiş bir `@graph`** kabul edilir.
- Sabit `@id` **yalnızca varlıkların gerçekten bağlantı kurduğu yerde** kullanılır (Organization ↔ WebSite ↔ WebPage). Bağımsız düğümlerde sabit `@id` zorunlu *değildir*.

---

## 2. Normalleştirme ve değişmez koşullar {#_2-normalization-invariants}

- `canonical`, `og:url`, `og:image` ve `twitter:image` için **mutlak `http(s)` URL'leri** kullanılır. DOM'a hiçbir zaman **boş veya null etiket ulaşmaz**.
- **`canonical` ve `og:url` aynı normalleştirilmiş URL'ye çözümlenmek ZORUNDADIR.** Uyuşmazlık uyarı değil, **KESİN başarısızlıktır**.
- **Kanonik URL normalleştirme politikası** tüm çıktılarda tutarlıdır: protokol / sunucu adı / port / yolun harf büyüklüğü / izin verilen sorgu parametreleri / sondaki eğik çizgi her seferinde aynı biçimde ele alınır. Dizine eklenebilir sayfalar **kendilerine işaret eder**; `noindex` sayfası başka bir sayfanın kanonik URL stratejisini **devralmaz**.
- **Kaçışlama çıktı bağlamına özeldir**: HTML özniteliği, metin ve JSON için doğru kodlayıcı kullanılır. Testler **baytları değil, çözümlenmiş anlamsal değerleri** karşılaştırır.
- **Çıktı üreticileri arasındaki eşdeğerlik bayt düzeyinde değil, anlamsaldır.** *Normalleştirmeden sonra* `render()` (HTML) ≡ `toArray()` ≡ `toInertiaHead()`. Üç gösterim, etiket sırası ve biçimi bakımından geçerli biçimde farklılaşabilir. Tekil ve yinelenebilir özelliklerin kuralları açıktır (bir `og:title`; birden çok `article:tag`).
- **Etiket sahipliği**: istemci tarafındaki çıktı üreticisi, uygulamaya ait ilgisiz etiketleri silmeden *pakete ait* etiketleri değiştirir (anahtarlar kullanılır; bkz. §4).

---

## 3. Davranış — istemci tarafında gezinme {#_3-behaviour-—-client-side-navigation}

Her Inertia ziyareti veya Livewire `wire:navigate` sonrasında:

- **Her tekil öğeden tam olarak bir tane** bulunur (`<title>`, açıklama, kanonik URL, her `og:*`/`twitter:*`); **hiçbiri eski kalmaz**.
- **JSON-LD birikmez**. Önceki sayfanın şeması, üstüne yenisi eklenmek yerine kaldırılır. Livewire `<script>` öğesini kaldırılamayan bir varlık olarak ele aldığından şema script'leri `data-seo-schema` ve URL'ye özel kimlikle işaretlenir; önceki sayfanın script'leri `livewire:navigated` sırasında kaldırılır. Livewire kılavuzuna bakın.
- **Meta verileri zengin bir sayfadan yalın bir sayfaya geçişte ek etiketler kaldırılır**. Yalın sayfa önceki sayfanın açıklama/og/şema verilerini tutmaz.
- **Hydration uyarısı oluşmaz**; meta veriler hydration öncesinde ve sonrasında anlamsal olarak aynıdır.

---

## 4. Inertia head anahtarları (etiket sahipliği) {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` her meta/bağlantı girdisine sabit bir **`head-key`** ekler. Inertia, head öğelerindeki yinelenmeleri bu özniteliğe göre giderir: sayfanın `<Head>` etiketi, yerleşimdeki etiketle aynı `head-key` değerine sahipse ikinci bir kopya olarak eklenmek yerine onun *yerini alır*.

- Temel anahtar: meta için `name ?? property`, bağlantılar için `rel`.
- Her birinin benzersiz anahtarı olması için **yinelenebilir etiketler ayrıştırılır**: `article:tag` → `article:tag`, `article:tag:1`, …; hreflang → `alternate:en-US`, `alternate:fr-FR`.

Şablonlarda **`:head-key`** olarak bağlayın; Vue'nun `:key` değeriyle *değil*. Bu, ilgisiz bir `v-for` eşleştirme anahtarıdır ve Inertia'nın head yinelenmelerini gidermesine etkisi yoktur.

---

## 5. Tarayıcı bot görünürlüğü (açık çalışma biçimleri) {#_5-crawler-visibility-explicit-modes}

- **SSR / önceden oluşturma**, sözleşmenin tamamını **ham HTTP HTML yanıtında** üretmek ZORUNDADIR. Bu, hydration uygulanmış DOM'dan ayrı olarak, JavaScript kapalıyken test edilir.
- **Yalnızca CSR kullanan uygulamalar tarayıcı bot uyumluluğu iddia edemez.** Varsayılan Inertia (SSR olmadan), meta verileri *istemci tarafında* ekler; botun aldığı ilk HTML'de SEO meta verileri yoktur. Bu gizlenmez, belgelenir: **tarayıcı botların görebildiği meta verileri için Inertia SSR veya önceden oluşturma gerekir**. Botlara yönelik JSON-LD de sunucuda oluşturulmalıdır.

---

## 6. Kapsam dışı konular ve hedeflenmeyenler {#_6-out-of-scope-non-goals}

- **Çıktı üreticisinin değil, uygulamanın sorumlulukları**: `charset`, `viewport`, favicon'lar. (Not: `<meta charset>`, ASCII dışı meta verilerden önce gelmelidir; bu nedenle head içindeki bu öğelerin sıralamasından uygulama sorumludur.)
- **Uçtan uca test yalnızca üretilen çıktıyı doğrular.** Google'ın dizine eklemesini, kanonik URL *seçimini*, zengin sonuç uygunluğunu veya sıralamayı **doğrulamaz**; uzak görsellerin MIME türünü veya erişilebilirliğini de **doğrulamaz**. Bunlar tarayıcı matrisine değil, isteğe bağlı entegrasyon/HTTP testlerine aittir.

---

## 7. Uyumluluk durumu {#_7-conformance-status}

Her maddeyi bugün hangi kanıtın doğruladığı aşağıda gösterilmiştir. **Birim** = `RenderingContractTest` (çekirdek, paket CI'ı). **Tarayıcı/SSR** = `rankbeam-examples` (zamanlanmış matris). **Uygulama** = sorumluluk paketi kullanan uygulamadadır. **Planlandı** = sözleşmedeki hedeftir, ancak veriler henüz `SEOData` içinde modellenmediği için çıktı üreticisi güvenli alt kümeyi üretir.

| Madde | Durum |
|---|---|
| Tam olarak bir çözümlenmiş `<title>`, yinelenen son ek yok | **Birim** + Tarayıcı |
| Meta açıklaması yalnızca varsa üretilir | **Birim** + Tarayıcı |
| Bir `<link rel="canonical">`, asla boş değil | **Birim** + Tarayıcı |
| Robots yalnızca varsayılandan farklıysa, aynen üretilir; `emit_default` ayarı | **Birim** + Tarayıcı |
| Çözümleyici önceliğiyle gelişmiş robots yönergeleri | **Birim** (çözümleyici) |
| `og:title/description/type/url/site_name/locale`; dil kodu `en-US`→`en_US` | **Birim** + Tarayıcı |
| `article:*` yalnızca `og:type=article` olduğunda ve değer gerçekse | **Birim** + Tarayıcı |
| `og:image` mevcut ve mutlak | **Birim** + Tarayıcı |
| `og:image:width/height/alt`, `og:image:type`, birden çok görselin gruplandırılması | **Planlandı** — `SEOData` tek bir `ogImage` dizesi taşır; boyut/alternatif metin/tür henüz modellenmedi. Çıktı üreticisi tek bir mutlak `og:image` üretir. |
| `twitter:card/title/description/image`; `site`/`creator` bağımsız | **Birim** + Tarayıcı |
| `twitter:image:alt` | **Planlandı** — görsel alternatif metin alanı henüz modellenmedi. |
| Hreflang mutlak, dil başına benzersiz | **Birim** + Tarayıcı |
| Hreflang karşılıklılığı, yapılandırılmışsa `x-default` | Tarayıcı (veriye bağlı) |
| `og:locale:alternate` gerçek sosyal paylaşım varyantlarını yansıtır | **Planlandı** — dil başına sosyal paylaşım varyantı eşlemesi henüz modellenmedi. |
| `<html lang>` uyumu | **Uygulama** (+ Tarayıcı doğrular) |
| JSON-LD ayrıştırılabilir ve `</script>` dizisine karşı güvenli | **Birim** + Tarayıcı |
| Birden çok script VEYA `@graph`; varlıkların bağlandığı yerde sabit `@id` | **Birim** (merchant grafı) + Tarayıcı |
| Mutlak URL'ler; boş/null etiket yok | **Birim** + Tarayıcı |
| `canonical` ≡ `og:url` (uyuşmazlık kesin başarısızlık) | **Birim** + Tarayıcı |
| Tutarlı kanonik URL normalleştirme; kendine başvuru; noindex yalıtımı | Tarayıcı |
| Bağlama özel kaçışlama; çözümlenmiş anlamsal eşdeğerlik | **Birim** |
| Çıktı üreticileri arasında anlamsal eşdeğerlik (`render()` ≡ `toArray()` ≡ `toInertiaHead()`) | **Birim** |
| Sabit Inertia `head-key` ve yinelenebilir öğelerin ayrıştırılması | **Birim** + Tarayıcı |
| İstemci gezinmesi: her tekil öğeden bir tane; eski etiket yok; JSON-LD birikmez; ek etiketler kaldırılır | Tarayıcı — çıktı üreticisi, temizliğin ihtiyaç duyduğu `data-seo-schema` kancalarını sağlar |
| Hydration uyarısı yok; hydration öncesi/sonrası eşdeğerlik | Tarayıcı |
| SSR sözleşmenin tamamını ham HTML'de üretir; yalnızca CSR kullanımının uyumsuzluğu belgelenmiştir | Tarayıcı + belgeler |

**Planlanan maddeler**, bilinen ve belgelenmiş eksiklerdir. Sözleşme kalıcı hedeftir; bunlar ilerideki bir görev için geriye uyumlu eklemelerdir (yeni `SEOData` alanları / sütunları ve SemVer minor sürümü gerektirir). Çıktı üreticisi bugün güvenli alt kümeyi üretir; sahip olmadığı bir değeri asla uydurmaz.

---
description: "Kendi anahtarınızla isteğe bağlı yapay zekâ desteği: başlık ve meta önerileri, tarama sorunlarının sade açıklamaları, tek tıkla yeniden yazım ve schema.org önerileri. Varsayılan olarak kapalıdır."
---

# Yapay zekâ desteği {#ai-assist}

İsteğe bağlı, **kendi anahtarınızla** çalışan yapay zekâ desteği: başlık ve
meta açıklama önerileri, tarama sorunlarının sade dille açıklamaları, tek tıkla
**açıklama yeniden yazımı** ve **yapılandırılmış veri (schema.org) önerisi**.
**Varsayılan olarak kapalıdır**; bayrak kapalıyken hiçbir yapay zekâ kod yolu çalışmaz.

Tasarımı üç ilke belirler:

- **Anahtarınız, sağlayıcınız.** İstekler *sizin sunucunuzdan* doğrudan *sizin*
  yapılandırdığınız sağlayıcıya gider: Anthropic, OpenAI, Google veya yerel /
  OpenAI uyumlu sunucu. Geçerli olduğunda sizin hesabınıza faturalandırılır.
  Aracılık, kullanım ölçümü veya yeniden satış yapılmaz; paket hiçbir yere
  telemetri göndermez.
- **Etkileşimli öneriler açık kabul gerektirir.** Öneri seçmek formu doldurur;
  gösterge paneli düzeltmeleri Uygula işlemini gerektirir. Pro 2.42’den beri
  toplu CLI varsayılan olarak özel taslaklar kaydeder. `--auto-apply` seçeneğini
  yalnızca bilinçli olarak anında yazma istiyorsanız kullanın.
- **Hiçbir zaman işlemi durduran hata üretmez.** Eksik veya geçersiz anahtar,
  bakiyesi bitmiş hesap, hız sınırı veya zaman aşımı satır içi mesaj üretir.
  Kaydetmeyi, çıktı üretimini veya taramayı asla engelleyemez.

## Sağlayıcılara genel bakış {#providers-at-a-glance}

Hesap erişimine, veri gereksinimlerine ve maliyete göre seçin. Dört entegrasyon
da aynı görevleri sunar; ancak model desteği, çıktı biçimi, hız ve kalite değişebilir.

| Sağlayıcı | Paketle gelen varsayılan model | Yapılandırılmış çıktı | Örnek maliyet | Kullanım |
|---|---|---|---|---|
| **Yerel** (Ollama / LM Studio / vLLM) | `llama3.1` (kendiniz seçin) | Destek ölçüsünde (`response_format`) | Kendi barındırdığınız çıkarım için **$0 API ücreti**; altyapı maliyetleri sürer | Verinin gittiği yer üzerinde kontrol |
| **OpenAI** | `gpt-5.5` | Seçilen model destekliyorsa Structured Outputs | Aşağıdaki örnek varsayımlarla öneri başına ~$0.005 | Mevcut OpenAI hesabı |
| **Anthropic** | `claude-opus-4-8` | Desteklendiğinde `output_config.format` | Aynı varsayımlarla öneri başına ~$0.015 | Mevcut Anthropic hesabı |
| **Google** | `gemini-2.5-flash` | Desteklendiğinde `responseSchema` | Aynı varsayımlarla öneri başına ~$0.0005 | Google hesabı; model kotalarını ve fiyatlarını kontrol edin |

Bu adlar, hesabınızda şu an kesin olarak kullanılabilir modelleri değil,
paketle gelen yapılandırmayı tanımlar. Maliyetler doğrulanmış güncel fiyatlar
değil, paketin örnek varsayımlarıdır. Entegrasyon davranışı ve yayınlanmış
denemelerden gözlemler:

- **Yapılandırılmış çıktı.** Desteklenen OpenAI, Google ve Anthropic yollarına
  JSON şeması gönderilir; geçersiz yanıtlar düzgün biçimde hata verir.
  Yerel sunuculara `response_format` destek ölçüsünde gönderilir. Yok sayılırsa
  toleranslı ayrıştırma ya geçerli liste ya hata döndürür; kısmi çıktı uygulanmaz.
- **Akıl yürütme, token tüketimini değiştirir.** Anlatılan Gemini denemesi bir
  açıklama için yaklaşık 500 gizli akıl yürütme token’ı ve yaklaşık 100 görünür
  token kullandı. Test edilen Anthropic çağrıları gizli akıl yürütme token’ı
  bildirmedi. Bu, model ailelerinin evrensel özelliği değildir. Gizli token’lar
  çıktı olarak ücretlendirilebilir; aşağıdaki akıl yürütme alt sınırının nedeni budur.
- **Modeller yapılandırılabilir.** `SEO_PRO_AI_MODEL` değerini, bağdaştırıcının API’si
  ve parametreleriyle uyumlu, kullanılabilir bir modele ayarlayın. Örnekler:
  `claude-haiku-4-5`, `gpt-5.4-mini` ve `gemma-3-12b-it`. Koleksiyonun tamamında kullanmadan
  önce model desteğini ve çıktı kalitesini doğrulayın.

## Kurulum {#setup}

Özelliği açın ve sağlayıcı anahtarınızı ortama koyun. Bulut sağlayıcısını
değiştirirken sağlayıcıyı ve anahtarı güncelleyin; model için özel bir değer
atadıysanız onu da kontrol edin. Yerel bağdaştırıcı ayrıca sunucu URL’si ister.

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip Sağlayıcı API anahtarı, Claude / ChatGPT aboneliğinden ayrıdır
Claude Code, Claude.ai veya ChatGPT **aboneliği**, **API** kullanımını
karşılamaz. `SEO_PRO_AI_API_KEY`, sağlayıcının geliştirici konsolundan alınmış,
kendi kredi bakiyesi olan *kullandıkça ödenen API anahtarı* olmalıdır;
Google AI Studio anahtarı da olabilir. Yalnızca aboneliği olan veya bakiyesi
olmayan hesap kimlik doğrulamayı geçer, ancak **kredi / kota bitti** hatası
döndürür; bkz. [Sorun giderme](#troubleshooting).
:::

Yapılandırma dosyası (`config/seo-pro.php`, `ai` bloğu), `timeout`,
`max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models` +
`reasoning_min_output_tokens`, `suggestion_count`, `bulk_model` (toplu doldurma için düşük
maliyetli katman; bkz. [Maliyet](#cheaper-bulk-generation)), `retry`,
`pricing` tablosu ve `local` alt bloğunu sunar. Tümü
[Sınırlar ve ayarlama](#limits-and-tuning) bölümünde ele alınır.

::: warning Önbelleklenmiş yapılandırmada anahtar yönetimi
Yapılandırma yalnızca ortam değişkeninin **adını** (`api_key_env`) saklar,
anahtarı değil. Bu yüzden `php artisan config:cache` anahtarınızı hiçbir zaman
`bootstrap/cache/config.php` içine yazmaz. Bunun diğer tarafı şudur: yapılandırma
önbellekliyken `.env` yüklenmez. Sunucuda `SEO_PRO_AI_API_KEY` değerini
gerçek ortam değişkeni olarak ayarlayın.
:::

## Yerel çıkarım ve bulut seçenekleri {#running-at-0-and-the-cheapest-paid-option}

- **Çıkarımı kendiniz barındırmak, sağlayıcının token başına API ücretini önler.**
  Donanım, elektrik ve operasyonlar yine maliyetlidir. İçerik, yalnızca
  yapılandırılan çıkarım sunucusu ve bağımlılıkları ağınızda kaldığında ağınızda kalır.
- **Google’ın kotaları ve fiyatları modele ve katmana özeldir.** AI Studio
  anahtarı (`aistudio.google.com/apikey`, biçim `AIza…`) ücretsiz katman denemelerine
  izin verebilir. Gerektiğinde faturalandırmayı açmadan önce sınırların iş
  yükünüze uyup uymadığını kontrol edin. Paketle gelen varsayılan
  `gemini-2.5-flash` değeridir. Gemini ve Gemma modellerinin düşünme yetenekleri
  aynı değildir; `reasoning_models` yetenek testi değil, yapılandırılmış ad kalıplarını uygular.

Çıkarımın nerede çalışacağını kontrol etmek için:

- **Yerel / OpenAI uyumlu.** `provider=local` ile OpenAI Chat Completions uyumlu
  **Ollama**, **LM Studio**, **vLLM** veya **LocalAI** sunucusu ya da
  **OpenRouter** gibi uzak ağ geçidi kullanın. `SEO_PRO_AI_LOCAL_BASE_URL` değerini API
  köküne ayarlayın (`/chat/completions` eklenir) ve kullanılabilir `SEO_PRO_AI_MODEL`
  seçin. Uzak ağ geçidi veriyi ağınızın dışında alır ve ücret isteyebilir:
  `local` bağdaştırıcı adı, çıkarımın yerel olduğu anlamına gelmez.

::: warning Yerel `base_url` doğrulanır — localhost için açıkça izin verin
`base_url` ayrıcalıklı ayardır ve diğer tüm dış isteklerle aynı
`SsrfGuard` üzerinden doğrulanır: yalnızca http/https, kullanıcı bilgisi
yok ve varsayılan olarak **herkese açık** adrese çözümlenmelidir. Böylece
hatalı veya saldırgan `base_url`, dahili hizmetleri yoklama aracına dönüşemez.
Gerçek yerel sunucu özel adres olan `127.0.0.1` üzerinde çalışır; bu nedenle
yerel sağlayıcı açık izin gerektirir: `seo-pro.ai.local.allow_local_addresses` (`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`).
Herkese açık ağ geçidinde (OpenRouter) kapalı bırakın. İstek yolu sabittir
ve yönlendirmeler asla izlenmez; anahtar başka sunucu adına yönlendirilemez.
:::

::: tip Desteklenen Ollama modellerinde düşünmeyi kontrol edin
Yerel düşünme modeli varsayılan zaman aşımını geçebilir. Model ve sunucu
sürümü destekliyorsa `seo-pro.ai.local.extra_body` içindeki `['think' => false]`, öneriler için
düşünmeyi kapatabilir. Destek değişir; [Ollama belgelerini](https://docs.ollama.com/capabilities/thinking)
kontrol edin. Gerekiyorsa `seo-pro.ai.timeout` artırın. Paket `temperature` göndermez;
bazı modeller bunu reddeder.
:::

## Maliyet {#cost}

Paket ek ücret koymaz; sağlayıcıya doğrudan ödeme yaparsınız. Kendi
barındırdığınız çıkarımda sağlayıcı API ücreti yoktur; altyapı maliyetleri
sürer. İki sayı önemlidir: etkileşimli kullanımda **öneri başına** maliyet ve
koleksiyonun tamamı için **toplu doldurma** maliyeti.

`seo-pro.ai.pricing` tablosu (1.000.000 token başına USD), token tahminini toplu
doldurma onay isteminin gösterdiği dolar tutarına çevirir. Bunlar
**paketle gelen tahmin varsayımlarıdır**; doğrulanmış güncel genel fiyatlar
değildir. Doğru tahmin için **sağlayıcınızın yayınladığı güncel fiyatlarla değiştirin**:

| Model kalıbı | Girdi $/1M | Çıktı $/1M |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

Yayınlanmış örnek, test edilen sayfanın token tüketimini (bir başlık ve bir
açıklama) bu varsayımlarla kullanır. Son sütun, desteklenen bağdaştırıcılarda
örnek %50 toplu işlem indirimini uygular. Bunlar güncel fiyat teklifi değildir.

| Sağlayıcı / model | ~ öneri çifti başına | ~ 1.000 kayıt başına (toplu doldurma) | ~ 1.000 kayıt başına (`--batch`) |
|---|---|---|---|
| Yerel `gemma`/`llama` (Ollama) | **$0 API ücreti** | **$0 API ücreti** | uygulanmaz (bağdaştırıcıda yok) |
| Google `gemini-2.5-flash` (ücretli) | ~$0.001 | ~$0.40 | uygulanmaz (Rankbeam bağdaştırıcısında yok) |
| OpenAI `gpt-5.5` | ~$0.008 | ~$5.25 | **~$2.63** (%50 indirim) |
| Anthropic `claude-opus-4-8` | ~$0.03 | ~$20 | **~$10** (%50 indirim) |

Komut tahminini yaklaşık ±%50 olarak etiketler; ancak **bu bir harcama
sınırı veya garanti edilmiş hata aralığı değildir**. Gerçek girdiler,
çıktılar ve fiyatlar toplamı değiştirir. Tahmin görünür çıktıyı modeller;
ücretlendirilen gizli akıl yürütme maliyeti bunun üzerine çıkarabilir.
Fiyat girdisi olmayan modellerde yalnızca token tahmini gösterilir.

### Daha ucuz toplu üretim {#cheaper-bulk-generation}

Farklı modeli **yalnızca toplu doldurmada** (`seo-pro:ai-fill` / `SeoPro::aiFill()`)
kullanmak için `seo-pro.ai.bulk_model` (`SEO_PRO_AI_BULK_MODEL`) ayarlayın. Filament ve
`seo-pro:ai-suggest`, `model` kullanmaya devam eder. Null olduğunda toplu
doldurma da `model` kullanır. Tahmin, seçilen modelin fiyat kalıbını
kullanır. Hacmi artırmadan önce temsili çıktıyı değerlendirin; daha ucuz
model otomatik olarak uygun değildir.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Paket örnekleri **anthropic** `claude-haiku-4-5`, **openai** `gpt-5.5-mini`,
**google** `gemini-2.5-flash` veya daha küçük **yerel** model kullanır. Fiyat
kalıbı, sağlayıcının sunmadığı bir adla bile eşleşebilir. Yapılandırmadan
önce gerçek model kimliğini, API uyumluluğunu ve fiyatı doğrulayın.

**100 sayfalık doldurma** (her sayfada hem başlık hem açıklama eksik = 200
sağlayıcı çağrısı), paketle gelen `pricing` varsayılanları ve tahmincinin
çağrı başına token modeliyle (600 girdi + 150 çıktı token’ı/çağrı)
fiyatlandırılır. Kalite modeli ve onun `bulk_model` düşük maliyetli katmanı:

| Sağlayıcı | Kalite modeli — 100 sayfa | `bulk_model` düşük maliyetli katman — 100 sayfa |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **$4.05** | `claude-haiku-4-5` ≈ **$0.27** |
| **OpenAI** | `gpt-5.5` ≈ **$1.05** | `gpt-5.5-mini` ≈ **$0.11** |
| **Google** | `gemini-2.5-pro` ≈ **$0.45** | `gemini-2.5-flash` ≈ **$0.04** |
| **Yerel** (Ollama / vLLM) | her model — **$0 API ücreti** | her model — **$0 API ücreti** |

Bunlar örnek tahminlerdir; garanti edilmiş ±%50 aralık veya gizli akıl
yürütme payı içermezler. Tahmine güvenmeden önce `seo-pro.ai.pricing` değerlerini
seçilen sağlayıcının yayınlanmış fiyatlarıyla güncelleyin.

## Çıktı dili {#output-language}

Her istem, sayfanın dilini ve BCP-47 kodunu adlandırır: *“alıntıdaki diğer
dillerden bağımsız olarak, sayfanın dili olan Brezilya Portekizcesinde
(pt-BR)”*. Modele gönderilen sayfa bağlamı `Language:` satırını taşır
(Pro 2.34). Önceden istemler “kaynak içerikle aynı dilde” diyordu; model
kısa veya kodla karışık alıntıdan tahmin etmek zorunda kalıyordu. Alıntısında
İngilizce marka adı bulunan Türkçe sayfa, İngilizce sonuç alabiliyordu.
Dil kodu, sayfanın meta verilerinin çözümlendiği dildir; sayfanın dili yoksa
uygulama dili kullanılır. [Uzunluk bütçesi](/tr/guide/multilingual#title-and-description-budgets-per-script)
de aynı dil için seçilir; Japonca sayfa bu yüzden *Japonca* yaklaşık
30 karakterlik başlıklar ister.

Pro 2.36’dan beri açık içerik dili, meta veri satırını, içerik kancalarını
ve istem dilini birlikte kontrol eder. Operatörün arayüz dili değişmez.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Mevcut konumsal bağımsız değişkenler değişmez. Modelin `seoData()`
varsayılanını kullanmak için `locale:` vermeyin; ayrı çeviri modelleri
kendi dillerini bildirebilir. Alıntı, boş olmayan içerik döndürdüğünde
`getContentForSEO()` kullanır; yoksa yapılandırılan içerik alanlarına döner.
Filament 1.11, tek dil ve sayfa değiştirici modları dahil, seçilen sekmenin
dilini otomatik aktarır.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

Toplu `plan()`, `fill()` ve `submitBatchFill()` da sonda
`locale:` kabul eder. `FillProgress(..., locale: 'it')` oluştururken ve çalıştırmayı
gönderirken aynı dili kullanın. Her toplu işlem öğesi içerik dilini
kaydeder; sonuç toplama, bu kayıtlı dili kullanır ve yazmadan önce meta
veri satırını yeniden kontrol eder. Açık dil verilen çalıştırmaların ayrı
kontrol noktası dosyaları vardır; işlenmiş işaretleri dilleri ayırır.
Sonuçları toplamak için aynı komutu yeniden çalıştırın. Özel işler içerik
dilini serileştirip aktarmalıdır.

Pro 2.36’dan önce oluşturulan kontrol noktaları içerik dilini kaydetmezdi.
Bekleyen eski toplu işlem korunur ancak otomatik toplama reddedilir.
`--fresh` ile silmeden önce sağlayıcı sonuçlarını ve amaçlanan dili
uzlaştırın; aksi hâlde yeni gönderim aynı işi yeniden ücretlendirebilir.
İşlenmiş kayıtları olan eski sıralı kontrol noktası da sıfırlanmadan önce uzlaştırılmalıdır.

### Dil başına değerlendirme {#per-language-evaluation}

Pro kaynak deposunda 17 dilde 170 girdi sayfası ve isteğe bağlı değerlendirme
düzeneği vardır. Girdi sayfalarında yapısal ve temel dil sezgisi kontrolleri
bulunur; bağımsız ana dil onayı hâlâ beklemektedir.

Düzenek **hem başlıkları hem açıklamaları** kontrol eder, grafem uzunluklarını
ve dil/yazı sistemi kanıtlarını kaydeder; doğrulamalardan önce her sağlayıcı
yanıtını korur. Kısa başlıklar, karışık metin ve ortak Çince/Japonca
karakterler belirsiz kalabilir. Temel dilin Portekizce tahmin edilmesi,
Brezilya kullanımını doğrulamaz; kısmi Çince karakter kontrolleri de bölgesel
yazım kalitesini sertifikalandırmaz.

Canlı çalıştırmalar `SEO_PRO_AI_EVAL=1`, açık `SEO_PRO_AI_EVAL_LOCALES` seçimi ve
`SEO_PRO_AI_EVAL_RUN` kimliği gerektirir. Sağlayıcı ücreti doğurabilirler; hiçbiri
varsayılan olarak çalışmaz. Her çalıştırma, kanıtlarını sağlayıcıya,
istenen/dönen modele, test verisi/istek/kod hash’lerine ve zaman damgalarına
bağlar. Başarısız denemeler korunur. Devam etme, kaydedilen yanıtları yeniden
kullanır; kesilen istek sağlayıcıya ulaşmış olabileceğinden açık yeniden deneme gerektirir.

Kanıtlar, kaynak test ortamında `storage/app/seo-ai-evals/<run-id>/` altında saklanır. Test
verisinin `README.md` dosyası sürümlü şemayı ve komutları belgeler.
Ana dil incelemecileri, ayrı inceleme kayıtlarında tam çıktı hash’lerini
puanlar. Başarılı otomatik kontrol, ana dil onayı veya yayınlanabilir metin garantisi değildir.

## Sınırlar ve ayarlama {#limits-and-tuning}

Her ayar `config/seo-pro.php` içindeki `ai` bloğunda yer alır:

- **`timeout`** (varsayılan `15` saniye, ortam değişkeni
  `SEO_PRO_AI_TIMEOUT`): Filament öneri penceresi açılırken yapılan eşzamanlı
  çağrının da sınırıdır; kullanıcı deneyimi için kısa tutulur. **Yavaş
  akıl yürütme veya yerel model 15 saniyeyi geçip** zaman aşımına uğrayabilir.
  Böyle bir model kullanıyorsanız `SEO_PRO_AI_TIMEOUT` ile artırın; yukarıdaki
  Ollama `think => false` ipucuna da bakın. Zaman aşımı ana işlemi durdurmaz:
  satır içi hata üretir, kaydetmeyi engellemez.
- **`max_input_chars`** (varsayılan `6000`): istek başına gönderilen
  sayfa içeriğinin (HTML’si kaldırılmış düz metin) maliyet ve gizlilik sınırı.
- **`max_output_tokens`** (varsayılan `1000`): üretilen token’ların temel
  üst sınırı. Sınıra ulaşan yanıt, sessiz yarım cevap değil, ayrı
  `truncated` hatası döndürür.
- **`token_budgets`**: görev başına çıktı sınırları (`suggestions` 800,
  `explanation` 600, `rewrite` 300, `schema_suggestion` 700).
  Hiçbiri varsayılanın tamamına ihtiyaç duymaz; ancak akıl yürütme alt sınırı ayrıca uygulanır.
- **`reasoning_models`** + **`reasoning_min_output_tokens`** (varsayılan `2000`):
  adı bir kalıpla eşleşen modelin (`*gemma*`, `gemini-2.5-*`,
  `o1*`/`o3*`/`o4*`) çıktı bütçesi bu alt sınıra
  yükseltilir. Düşünen model görünür çıktıdan önce gizli token harcar;
  küçük bütçe yanıtı keserdi.
- **`suggestion_count`** (varsayılan `3`): istenecek başlık/açıklama
  alternatifi sayısı.
- **`retry`**: yalnızca *geçici* hatalar için otomatik yeniden deneme;
  bkz. [Yanıtlar nasıl işlenir?](#how-replies-are-handled).

## Filament’te {#in-filament}

İsteğe bağlı Filament paketleri (`rankbeam/laravel-seo-filament` >= 1.1) kuruluysa yapay
zekâ desteğini açmak şunları ekler:

- SEO bölümünü kullanan her kaynağın düzenleme sayfasında SEO başlığı ve
  açıklama alanlarında **Yapay zekâ ile öner**. Pencere, üretilen
  alternatifleri karakter sayılarıyla gösterir; birini seçmek inceleme için alanı doldurur.
- Gösterge paneli sorun tablosunda **Açıkla (yapay zekâ)**: sorunun ve
  somut düzeltmenin kısa, sade açıklaması.
- Gösterge paneli sorun tablosunda Açıkla’nın yanında **Açıklamayı yeniden
  yaz (yapay zekâ)**: her zaman sayfanın açıklama bütçesinde kalan tek
  geliştirilmiş meta açıklama önerir. Latin metninde 160, CJK’de yaklaşık
  80 karakter; çekirdeğin [uzunluk politikası](/tr/guide/multilingual#title-and-description-budgets-per-script).
  Pencerede inceleyin; **Yeniden yazımı uygula** seçeneği sayfanın
  `seo_meta` kaydına yazar. Uygulayana kadar hiçbir şey yazılmaz.
- Gösterge paneli sorun tablosunda **Yapılandırılmış veri öner (yapay zekâ)**:
  en uygun schema.org zengin sonuç türünü (Product, Article veya Breadcrumb)
  önerir ve bunun için oluşturulan JSON-LD’yi gösterir. **Yapılandırılmış
  veriyi uygula**, veriyi sayfanın `seo_meta.schema_jsonld` alanına ekler. Bu,
  isteğe bağlı [yapılandırılmış veri editörünün](/tr/guide/filament#structured-data-schema-org)
  yönettiği aynı sütundur; veri gidip gelebilir ve orada düzenlenebilir kalır.
  Eksik öneri (ör. yazarı veya görseli olmayan Article), eksik alanlarıyla
  gösterilir ve **uygulanmaz**.

Son ikisi sınırlandırılmış düzeltmelerdir; bkz.
[Sınırlandırılmış düzeltmeler](#bounded-fixes-propose-never-auto-apply).

## Sınırlandırılmış düzeltmeler (önerir, otomatik uygulamaz) {#bounded-fixes-propose-never-auto-apply}

İki destek işlemi önerinin bir adım ötesine geçer: tek tıkla uygulayabileceğiniz
tek, *kısıtlı* değer üretir. İkisi de yine **yalnızca önerir**;
açıkça kabul etmedikçe hiçbir şey kalıcı kaydedilmez.

- **Açıklamayı yeniden yaz** (`SeoSuggestionService::rewriteDescription($model, $issue?)`), **her zaman çekirdeğin uzunluk
  politikasındaki sayfa yazı sistemi bütçesine uyan** tek meta açıklama
  döndürür: Latin için 160, CJK için yaklaşık 80. Bu, sayfanın kendi
  çözümlenmiş değerinden seçilen ve başlık/açıklama öneri istemlerinin taşıdığı
  bütçenin aynısıdır. Model aşarsa metin, cümle sınırında, ardından sözcük
  sınırında deterministik olarak kısaltılır. Kabul edilen yeniden yazım
  böylece `description_too_long` uyarısını kendisi tetikleyemez. Tarama sorununu
  aktarmak yeniden yazıma yön verir; ör. *çok uzun* veya *eksik*.
- **Yapılandırılmış veri öner** (`SeoSuggestionService::suggestSchemaType($model)`), modelden yalnızca **tür
  önerisi ve yaprak alan değerleri** ister; ham JSON-LD istemez. Ardından
  deterministik kod, çekirdek şema oluşturucularıyla (`ProductSchema` /
  `ArticleSchema` / `BreadcrumbSchema`) belgeyi oluşturur ve çekirdeğin
  `SchemaValidator` bileşeniyle doğrular. Uydurulmuş `@type`,
  `@context` veya yapı böylece sayfaya ulaşamaz. Oluşturulan belgede
  zorunlu alan eksikse *eksik* olarak gösterilir ve uygulanmaz. Canlı
  testte bir sağlayıcı zayıf içerikli sayfa için `Article` önerdi;
  yazar ve görsel eksik olduğu için doğru biçimde bekletildi. Diğer
  sağlayıcılar ise tür önermemeyi seçti.

## Arayüzden bağımsız kullanım {#headless}

Betikler ve Filament kullanmayan uygulamalar için aynı yetenek JSON olarak sunulur:

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

Çıktı; önerileri (veya önerilen türü, oluşturulmuş JSON-LD’yi ve doğrulamadan
geçip geçmediğini), kullanılan modeli ve **istek başına token kullanımını**
(girdi / çıktı / akıl yürütme) içerir. Sağlayıcı ücretleriyle maliyet
hesaplamaya yardımcı olur; token sayıları fatura değildir. Komut her hatada
sıfırdan farklı kodla çıkar; hata JSON zarfındadır. Her destek yüzeyi gibi
`seo-pro:suggest-schema` **yalnızca önerir**; belgeyi gösterir, hiçbir şey yazmaz.

## Eksik meta verileri toplu doldurma {#bulk-fill-missing-metadata}

**Pro 2.42, CLI varsayılanını değiştirir:** `seo-pro:ai-fill`, üretilen alan
başına bir özel taslak kaydeder. Yayınlanmış SEO meta verileri onaya kadar
değişmez. Mevcut değerler ve hesaplanan yedek değerler atlanır. Güncel
bekleyen taslak yeniden üretilmek yerine yeniden kullanılır.

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review`, bekleyen ilk 100 taslağı JSON olarak listeler; değerini ve
özel kanıtlarını okumak için kimliği inceleyin. Onay ve ret, yapay zekâ
kapalıyken çalışır ve sağlayıcı çağrısı yapmaz. Onay operatör etiketi
gerektirir; kaynak kayıt veya hedef meta verileri değişen ya da kaydı
silinen taslakları reddeder. Etiket, operatörün kim olduğunu söylediğini
kaydeder; esaslı insan incelemesini kanıtlamaz. Yapılandırılmış, varsayılan
olmayan veritabanı için `--connection=NAME` kullanın.

`--auto-apply`, hâlâ eksik alanların anında yayınlanmasını açıkça geri açar.
`--force`, **incelemeyi değil**, onay istemini atlar. `--dry-run`,
taslak veya meta veri kaydetmeden değer üretir ve gösterir; yine sağlayıcı
çağırır ve ücret doğurabilir. `--field`, `--limit` ve
`--locale` üretimin kapsamını belirler. Yükseltmeden sonra zamanlanmış
komutları bilinçli olarak güncelleyin.

### Büyük ölçekte: tempo, maliyet tahmini ve kesintiden devam {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms` varsayılan olarak 200 milisaniyedir. `confirm_over` eşiğinde
(varsayılan 100 kayıt), komut `seo-pro.ai.pricing` tahminini gösterir ve üretimden
önce sorar. Kontrol noktaları tamamlanan alanları kesintiler boyunca korur.
Sağlayıcı kabulünden sonraki zaman aşımı yine de yinelenen ücrete yol
açabilir; `--fresh` öncesinde belirsiz işleri uzlaştırın. Aynı kapsama
uyan tek bir toplu işi aynı anda çalıştırın.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

Özel entegrasyonlarda taslak hazırlamak için `review: true` verin.
Daha alt düzey PHP API’si uyumluluk için `apply: true, review: false` varsayılanını
korur; mevcut çağrılar hâlâ hemen yazar. `apply: false` kalıcı kaydetmeden
önizler. Özet anahtarı `filled`, inceleme modunda taslaklananlar dahil
işlenen kayıtları sayar; CLI bunları `staged` olarak etiketler.

### Toplu işlem modu (%50 daha ucuz) {#batch-mode-50-cheaper}

`--batch`, desteklenen Anthropic veya OpenAI asenkron uç noktasını
kullanır. Belgelenen indirim tahmine yansır; yine de güncel model fiyatlarını
kontrol edin. Google ve yerel bağdaştırıcılar sıralı üretime döner.
Şimdi gönderin, taslakları toplamak için aynı komutu daha sonra çalıştırın:

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

Gönderim ve toplama arasında sağlayıcıyı, dili ve yayınlama modunu
değiştirmeyin. İnceleme ve `--auto-apply` ayrı kontrol noktaları kullanır;
eşleşen toplu işlem beklerken CLI diğer modu başlatmayı reddeder. Belirsiz
gönderim uzlaştırma için durur. Kısmi başarılar korunur; geçici hatalar
yeniden denenebilir. Toplama, eksik alanları yeniden kontrol eder; onay
ayrıca gönderimden önceki kaynak görüntüsünü kontrol eder. `seo-pro.ai.fill.batch.request_timeout`
varsayılanı 120 saniyedir. Zamanlanmış toplama varsayılan olarak taslak kaydeder.

## Köken kayıtları, migration’lar ve veri filtreleme {#origin-review-and-filtering}

**Core 3.21 ve Pro 2.42** gerektirir. Core, migration’ını otomatik yükler.
Kaydedilecek önerileri üretmeden önce Pro migration’larını yayınlayın ve
SEO modellerinin kullandığı her veritabanında çalıştırın:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Özel `seo_ai_proposals` tablosu, üretilen değerleri ve sağlayıcı/model/istek
kanıtlarını Laravel şifreli cast’leriyle saklar. `APP_KEY` ve yedeğini
güvende tutun; kaybederseniz bu değerler okunamaz. Kayıt tanımlayıcıları,
durum ve karar meta verileri sıradan veritabanı sütunlarında kalır. Form
bırakılırsa alternatifler `offered` veya `selected` durumunda
kalabilir. Otomatik temizleme yoktur: uygulama saklama politikası belirleyin,
bekleyen taslakları ve `seo_meta.ai_provenance` tarafından hâlâ başvurulan kanıtları
koruyun; veritabanı dışa aktarımlarına ve inceleme komutu çıktısına erişimi kısıtlayın.

Kabul edilen form önerileri, gösterge paneli düzeltmeleri ve toplu değerler
alan kökenini taşır. Sonraki Eloquent düzenlemeleri `origin: ai` değerini
korur ve `edited: true` ayarlar. Bu, değerin değiştiğini gösterir; insanın
doğruladığını değil. Alanı temizlemek işaretini kaldırır. Core HTML, dizi,
JSON ve Inertia çıktıları yalnızca alan adını, kökeni ve düzenlenme durumunu
açar; uygun yerde özel `rankbeam:ai-origin` meta etiketi kullanılır. Üretim
kimlikleri ve sağlayıcı ayrıntıları özel kalır. Ham `SEOMeta`
modellerini herkese açık API’de sunmayın.

Bu, geçmiş içeriği veya her düzenleme sürümünü değil, gelecekteki desteklenen
kayıt yollarını kaydeder. Doğrudan SQL, sorgu oluşturucu güncellemeleri ve
özel çıktı üreticileri bu kontrolleri atlayabilir. Değeri bağımsız olarak yazılmış bir metinle değiştirdiğinizde, gerekiyorsa
kökeni açıkça sıfırlayın; sıradan düzenlemeler kökeni korur. Özel işaret, standart filigran, değiştirilemez
atıf veya Madde 50 uyumluluğu iddiası değildir. Gerçek sağlayıcı çıktı
kalitesi ve sağlayıcının kendi işaretleri ayrı değerlendirme gerektirir.

İsteğe bağlı olarak `AiPromptFilter` uygulayın ve `seo-pro.ai.context_filter` yapılandırın.
Birleştirilmiş kullanıcı istemini eşzamanlı veya toplu gönderimden önce
filtreler. Hata, gönderimi önler ve temizlenmiş hata döndürür. Sistem
talimatları değişmez. Varsayılan `null` değeridir; **hassas veriler
otomatik maskelenmez**. Bu örnek yalnızca bilinen tek değeri değiştirir;
uygulamanıza uygun kuralları uygulayıp test edin:

```php
namespace App\Support;

use Rankbeam\Seo\Pro\Ai\AiPromptFilter;

final class RedactAiContext implements AiPromptFilter
{
    public function filter(string $prompt): string
    {
        return str_replace('internal@example.com', '[redacted]', $prompt);
    }
}

// Configure seo-pro.ai.context_filter with this class in config/seo-pro.php.
// Runtime equivalent:
config(['seo-pro.ai.context_filter' => RedactAiContext::class]);
```

## Yanıtlar nasıl işlenir? {#how-replies-are-handled}

Her çağrı sağlayıcıdan bağımsız tek zarf döndürür; davranış sağlayıcılar
arasında ve sonradan eklenenlerde aynıdır:

- **Sağlayıcı destekliyorsa yapılandırılmış çıktı.** OpenAI (yerleşik
  Structured Outputs), Google (Gemini `responseSchema`) ve Anthropic
  (`output_config.format`), JSON biçimini API düzeyinde uygular. Geçersiz JSON
  düzgün biçimde hata verir; metin kazınarak kurtarılmaz. Yerel / OpenAI
  uyumlu sunucudan da `response_format` ile destek ölçüsünde istenir.
  Alanı yok sayan sunucunun kullanılabilir metni, yedek olarak toleranslı
  ayrıştırılır. Her iki durumda da düzgün liste veya açık hata alırsınız;
  yarım ayrıştırılmış yanıt değil.
- **Kesilme açık, giderilebilir hatadır.** Yanıt çıktı token sınırında
  kesilirse sessizce kısaltılmış başlık değil, `seo-pro.ai.max_output_tokens` artırmanızı
  söyleyen `truncated` hatası alırsınız. Bu, özellikle **akıl yürüten /
  düşünen modellerde** yaygındır; bunlara daha yüksek `reasoning_min_output_tokens` alt
  sınırı otomatik uygulanır.
- **Geçici hatalar otomatik yeniden denenir.** `429` hız sınırı veya
  `5xx`, sınırlı üstel beklemeyle yeniden denenir. Varsa
  `Retry-After` başlığına uyulur; saldırgan değer isteği durduramasın diye
  sınırlandırılır. **Deterministik** hatalar **yeniden denenmez**: yanlış
  anahtar, bozuk istek, fazla büyük yük, **zaman aşımı** veya **kredisi /
  kotası bitmiş hesap**. Bakiyesiz hesabı tekrar denemek yalnızca beklemeyi
  uzatır. Yeniden denemeyi `retry` bloğundan ayarlayın veya kapatın;
  kapatmak için `max_attempts` değerini `0` yapın.
- **Hatalar türlendirilmiş ve temizlenmiştir.** Her hata kararlı kod
  (`unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`,
  `content_too_large`, `bad_request`, `truncated`, `content_filtered`,
  `provider_error`, …) ve geçici olanlarda `retryable` bayrağı taşır.
  Mesaj kısa, temizlenmiş dizedir. **Normal uygulama yolu ham sağlayıcı
  yanıt gövdesini göstermez veya günlüğe yazmaz; yukarıdaki isteğe bağlı
  değerlendirme düzeneği yanıtları kanıt olarak korur.** Filament’te hata,
  biçimlendirilmiş pencere parçasında, yaygın durumlar için uygun sonraki
  adım ipucuyla gösterilir; bkz. [Sorun giderme](#troubleshooting).

## Sorun giderme {#troubleshooting}

Her hata satır içindedir, işlemi engellemez; tür kodu ve temizlenmiş
mesaj taşır. Yaygın durumlar ve etkili çözümleri:

| Belirti (hata kodu) | Anlamı | Çözüm |
|---|---|---|
| **`quota_exceeded`** — *“sağlayıcı hesabının kredisi veya kotası bitti”* | Anahtar geçerlidir ancak **API hesabında kredi / kota yoktur**. Hız sınırı değildir; yeniden denemek işe yaramaz. Sağlayıcıya göre farklıdır: Anthropic *“credit balance is too low”*, OpenAI *“exceeded your current quota… check your plan and billing”* (`insufficient_quota`), Google *“prepayment credits are depleted”* döndürür. | Sağlayıcı konsolunda kredi ekleyin / faturalandırmayı açın veya sağlayıcı API ücreti olmayan **yerel** modele geçin. Claude/ChatGPT **aboneliğinin API** kullanımını karşılamadığını unutmayın. |
| **`unauthorized`** — *“kimlik doğrulama başarısız”* | Anahtar eksik, yanlış veya yapılandırılan sağlayıcı için geçersizdir. | `seo-pro.ai.api_key_env` ile adlandırılan ortam değişkenindeki anahtarı kontrol edin (varsayılan `SEO_PRO_AI_API_KEY`): ayarlı, güncel ve `SEO_PRO_AI_PROVIDER` ile uyumlu olmalı. |
| **`rate_limited`** — *“sağlayıcının hız sınırına ulaşıldı”* | Gerçek, **geçici** hız sınırı; önce otomatik yeniden denenir. | Bekleyip yeniden deneyin veya sunucu kapasitenize bağlı bir **yerel** modele geçin. Düşük katmandaki toplu işler için `seo-pro.ai.fill.throttle_ms` artırın. |
| **`timeout`** — *“istek zaman aşımına uğradı”* | Sağlayıcı `seo-pro.ai.timeout` içinde yanıt vermedi (varsayılan 15 saniye). **Yavaş yerel akıl yürütme modelinde** yaygındır. | `SEO_PRO_AI_TIMEOUT` ile artırın; Ollama için `seo-pro.ai.local.extra_body` içinde `['think' => false]` da ayarlayın. |
| **`truncated`** — *“max_output_tokens sınırına ulaşıldı”* | Yanıt, gizli akıl yürütme dahil olabilen çıktı bütçesine ulaştı. | `seo-pro.ai.max_output_tokens` artırın (akıl yürütme modelleri 2000+ isteyebilir) veya alt sınır uygulansın diye modelin `reasoning_models` kalıbıyla eşleştiğini doğrulayın. |
| **`content_too_large`** (HTTP 413) | Gönderilen sayfa içeriği sağlayıcı sınırını aştı. | Daha kısa alıntı için `seo-pro.ai.max_input_chars` azaltın. |
| **`bad_request`** | Bozuk istek; genellikle hesabın erişemediği **model adı** veya desteklenmeyen parametre. | `SEO_PRO_AI_MODEL` değerinin, yapılandırılan sağlayıcıda anahtarınızın/sunucunuzun erişebildiği model olduğunu kontrol edin. |
| **`content_filtered`** | Sağlayıcının güvenlik filtresi yanıtlamayı reddetti. | İçeriği ve sağlayıcı yönergelerini inceleyin; reddedilen isteği otomatik tekrarlamayın. |

::: tip Yerel çıkarım için de çalışan sunucu gerekir
Kendi barındırdığınız çıkarımla `SEO_PRO_AI_PROVIDER=local` kullanmak, bulut sağlayıcısının
kredi sorunlarını önler. Donanım, model, API uyumluluğu, zaman aşımı ve
kapasite yine önemlidir. Bu bağdaştırıcıyla yapılandırılan uzak ağ geçidi
anahtar ve ödeme gerektirebilir.
:::

## Sunucunuzdan çıkan veriler {#what-leaves-your-server}

Yalnızca yapılandırdığınız sağlayıcıya, yalnızca açık işlemle (tıklanan
işlem veya çağrılan komut), tam olarak şunlar gider:

- *Öneriler*: modelin sınıf kısa adı ve anahtarı (ör. “Post #3”), o anda
  çözümlenmiş başlık ve açıklama, kanonik URL ve `max_input_chars` ile
  sınırlandırılmış, HTML’si kaldırılmış düz metin içerik alıntısı
  (varsayılan 6000 karakter).
- *Sorun açıklamaları*: sorun türü, önem derecesi, alan, mesaj ve hedef URL;
  model varsa ayrıca etkilenen modelin sınıfı/anahtarı, çözümlenmiş başlık ve
  açıklaması, kanonik URL’si ve sınırlandırılmış düz metin içerik alıntısı.
- *Açıklama yeniden yazımı*: öneriyle aynı asgari sayfa bağlamı; verilmişse
  ayrıca tarama sorununun türü ve mesajı.
- *Yapılandırılmış veri önerisi*: öneriyle aynı asgari sayfa bağlamı. Model
  yalnızca tür ve yaprak alan değerleri döndürür; JSON-LD yerel oluşturulur.

Paket, istemlere bilerek ziyaretçi verileri, IP adresleri, istek başlıkları
veya kimlik bilgileri toplamaz; tam HTML göndermez. **İçerik alanlarınız ve
alıntılarınızın kendisi hassas bilgiler içerebilir**; uygulamanızın neleri
açığa çıkardığını inceleyin. Sağlayıcı kimlik bilgisi isteğin kimlik
doğrulamasında kullanılır. Veri işleme başvurusu, Pro deposunun SECURITY.md dosyasıdır.

---
description: "SEO puanının yanında ikinci deterministik puan: tarayıcıların sayfaya ulaşıp okuyabilmesine ilişkin, Rankbeam’in tanımladığı teknik yapay zekâya hazırlık ölçüsü. SEO puanından ayrıdır."
---

# Yapay zekâya hazırlık puanı — ikinci, deterministik eksen {#the-ai-readiness-score-—-a-second-deterministic-axis}

Pro taraması, her sayfaya [SEO puanının](/tr/pro/scoring) yanında ikinci bir
sayı verir: **0-100 yapay zekâya hazırlık puanı**. Farklı bir soruyu yanıtlar:
*yapay zekâ tarayıcıları ve yanıt motorları bu içeriğe ulaşabilir, onu okuyabilir
ve kaynağıyla ilişkilendirebilir mi?* Bu puan **organik SEO puanına asla karıştırılmaz**.
Kendi değerlendirme kuralları, sürümleri ve sütunları olan iki ayrı eksendir.

::: warning Bu sayı neyi ifade eder, neyi etmez?
Yapay zekâya hazırlık puanı, **Rankbeam’in tanımladığı deterministik bir teknik
uyumluluk ölçüsüdür**: sayfadaki taramaya dayalı sinyallerin bulunup bulunmadığını
ve doğru biçimde olup olmadığını ölçer. Herhangi bir arama veya yapay zekâ
sisteminde sıralama, dizine eklenme, sonuçlara dahil edilme veya atıf alma tahmini
**değildir**; hiçbir puan bu sonuçları garanti etmez. `air_llms_txt` kontrolü,
kullanmayı tercih eden araçlar için **isteğe bağlı** `llms.txt` uyumluluk dosyasına
puan verir. Google Arama bunu zorunlu tutmaz ve bu dosya bir sıralama sinyali değildir.
:::

SEO puanı gibi **tamamen deterministik ve tekrarlanabilirdir**: her puan,
adı belli olan taramaya dayalı bir kontrole bağlanır; aynı sinyaller her zaman
aynı sayıyı üretir. **Puanlamanın hiçbir yerinde yapay zekâ çağrısı yoktur.**
Amaç budur: “yapay zekâ görünürlüğü” SaaS ürünleri gibi LLM örneklemesi değil,
şeffaf ve denetlenebilir ölçüm.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip İki eksen, asla birleşmez
`AI-readiness: 74/100`, `SEO: 82/100` yanında durur; biri diğerini değiştirmez. Yapay zekâya
hazırlık puanı, Pro `seo_scan_results` satırında kendi `ai_readiness_*` sütunlarında tutulur.
SEO puanında olduğu gibi **sayısal puan Pro özelliğidir**; ücretsiz çekirdeğin
[`seo:audit`](/tr/guide/audit) komutu sayı göstermez.
:::

## Ceza yerine eklenen puan {#additive-credit-not-penalty}

[SEO puanı](/tr/pro/scoring) 100’den başlar ve cezaları *düşer*. Yapay zekâya
hazırlık bunun tersini yapar: **0** ile başlar ve her kontrolün ağırlığını tamamen
veya kısmen **ekler**. “Hazırlık” bir sitenin biriktirdiği bir şeydir; yapay zekâ
sinyalleri olmayan bir site bu yüzden “100 eksi birkaç” yerine açıkça 0’a yakın
puan alır. Ağırlıkların toplamı tam olarak **100**’dür.

## Değerlendirme kuralları {#the-rubric}

Puan, dört kategoride on kontrolden oluşan **yayınlanmış ve sürümlenmiş
değerlendirme kurallarından** hesaplanır: `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric`.

### A · Bot erişimi ve kontrolü — 30 puan {#a-·-bot-access-control-—-30-points}

Yapay zekâ araması ve asistan tarayıcıları size gerçekten ulaşabilir mi? Sitenin
**sunduğu `/robots.txt`** dosyasına göre, **taranan sayfanın kendi yolu** için
çözümlenerek değerlendirilir. Kök açık olsa bile `Disallow: /section` kuralı kapsamındaki bir sayfaya
izin verilmez; robots.txt uyumlu tarayıcılar için yönergedir, ağ erişim
engeli değildir. [Yapay zekâ tarayıcısı kataloğunun](/tr/guide/ai-crawlers)
amaç sınıflandırması kullanılır: eğitim / arama / asistan.

| Kontrol | Ağırlık | Verilen puan |
|---|---|---|
| `air_robots_reachable` — `robots.txt` sunuluyor ve okunabiliyor | 6 | var / yok |
| `air_ai_search_access` — yapay zekâ **arama** tarayıcıları (ziyaretçi yönlendirme kanalı) siteye erişmesine izin veriliyor | 10 | izin verilen oran |
| `air_ai_assistant_access` — yapay zekâ **asistan** tarayıcıları siteye erişmesine izin veriliyor | 8 | izin verilen oran |
| `air_explicit_ai_policy` — bilinen bir yapay zekâ botu için açık `robots.txt` kuralı | 6 | var / yok |

::: tip Eğitim tarayıcılarını engellemek “daha az hazır” olmak değildir
Eğitim botlarına (GPTBot, CCBot…) izin vermemek geçerli bir tercihtir ve **asla**
ceza nedeni değildir. Eğitim yalnızca `air_explicit_ai_policy` üzerinden puan getirir:
bilinçli, açık bir tutum belirlemek. Eğitim botlarını engelleyip arama ve asistan
tarayıcılarına izin veren site, bu kategoride tam puan alabilir.
:::

### B · Keşfedilebilirlik — 20 puan {#b-·-discoverability-—-20-points}

| Kontrol | Ağırlık | Verilen puan |
|---|---|---|
| `air_sitemap_discoverable` — XML site haritasına erişilebiliyor **ve** `Sitemap:` yönergesiyle başvuruluyor | 12 | ikisi / biri / hiçbiri |
| `air_llms_txt` — geçerli `/llms.txt` (başlık + bağlantılar) sunuluyor | 8 | geçerli / var / yok |

### C · Makine tarafından okunabilir içerik — 22 puan {#c-·-machine-readable-content-—-22-points}

| Kontrol | Ağırlık | Verilen puan |
|---|---|---|
| `air_server_rendered_content` — sunucuda üretilmiş HTML’de anlamlı miktarda metin var (JS çalıştırılmadan içerik mevcut) | 14 | sözcük sayısına göre |
| `air_markdown_twin` — içerik uzlaşmasıyla sayfanın Markdown karşılığı sunuluyor | 8 | var / yok |

### D · Yapılandırılmış veriler ve yanıta hazırlık — 28 puan {#d-·-structured-data-answer-readiness-—-28-points}

| Kontrol | Ağırlık | Verilen puan |
|---|---|---|
| `air_schema_completeness` — JSON-LD mevcut, ana varlığın türü tanımlı, yazar ve tarih mevcut (makalelerde yazar + tarih) | 18 | tam / kısmi / yok |
| `air_answer_structure` — yanıt çıkarmayı kolaylaştıran yapılar: FAQ/QA/HowTo şeması, başlık hiyerarşisi, listeler, kısa giriş | 10 | yapı sayısına göre |

Her kontrol **tam**, **kısmi** veya **sıfır** puan döndürür. Gereken sinyal
toplanamamışsa (sayfa getirilmeden taranan bir hedefte sayfa düzeyi kontrolü)
**atlanmış** olur. Atlanan kontrol 0 puan alır ancak işaretlenir; böylece
*kontrol edilemeyen* bir sinyal, doğrulanmış yokluk olarak gösterilmez.

### Ücretsiz denetimin kapsamı {#free-audit-reach}

Şema bütünlüğü (`air_schema_completeness`), modelin yapılandırılmış verilerinden ağ isteği
olmadan çözümlenebilir. Ücretsiz denetim, yanıta hazırlık bulgularında zaten aynı
yolu kullanır. Diğer dokuz kontrol tarama gerektirir; bu nedenle tam sayısal
puan **Pro taramasının** kapsamındadır.

## Açık kapsam — bu eksenin dışarıda bıraktıkları {#honest-scope-—-what-this-axis-excludes}

Bu eksen **deterministik içerik sinyallerini** puanlar. Çalışan uygulama veya
DNS ile ilgili ajan altyapısı kontrollerini dışarıda bırakır:

| Dışarıda bırakılan | Neden |
|---|---|
| **DNS-AID** (DNS ajan keşif kayıtları) | Sunulan sayfa özelliği değil, DNS / DNSSEC altyapısı. |
| **Web Bot Auth** (istek başına imzalama) | Statik içerik değil, etkileşimli kriptografik el sıkışması. |
| **Protokol keşfi** (API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills, WebMCP…) | Çalışan uygulama / API / MCP sunucusu gerektirir. |
| **Ticaret** (x402, MPP, UCP, ACP) | Ajan ödeme altyapıları; içerik sitesinin ücretlendireceği bir şey yoktur. |

Şema varlığı bütünlüğünü ve yanıt bloğu yapısını içerir. Bunlar düzeni ve
kaynakla ilişkilendirmeyi tanımlayan içerik sinyalleridir; bir arama veya yanıt
motorunun bunları kullanacağını garanti etmez.

## Sürümleme — geçmiş puanlar sessizce değişmez {#versioning-—-historical-scores-never-silently-change}

Kaydedilen her yapay zekâya hazırlık puanı, onu üreten `AiReadinessRubric::VERSION`
(`ai_readiness_version`) ile damgalanır. Kontrol kümesindeki, ağırlıktaki veya puan
verme modelindeki her değişiklik, değerlendirme kuralı değişikliğidir ve
**sürümü yükseltir**. Böylece saklanan sayı, kendisini açıklayan kuralları her
zaman kaydeder ve geçmiş sayılar karşılaştırılabilir kalır. Puan **saklanır;
okuma sırasında yeniden hesaplanmaz.** Puanı etkileyen eşikler (sözcük ve yapı
sayıları), sürüme bağlı kod sabitleridir; yapılandırma değildir. Bir ayar,
yayınlanmış sayıyı sessizce değiştiremez.

::: warning Sürümün sabitlemediği bir girdi
Bot erişim kontrolleri, çekirdekteki **güncel**
[yapay zekâ tarayıcısı kataloğunu](/tr/guide/ai-crawlers) okur. Kataloğa yeni
bot eklenmesi veya bir amacın yeniden sınıflandırılması girdileri fiilen
değiştirir; `AiReadinessRubric::VERSION` yükselmeden iki bot erişim alt puanını değiştirebilir.
Sürüm, kataloğu değil *değerlendirme kurallarını* izler. Bu bilinçli bir
tercihtir: kontrol, dondurulmuş liste yerine güncel gerçek bot listesini
okuduğunda daha yararlıdır. Geçmişle tam karşılaştırılabilirlik için çekirdek
paket sürümünü de değerlendirme sürümüyle birlikte sabitleyin.
:::

## Nerede saklanır? {#where-it-s-stored}

Her tarama, yapay zekâya hazırlık sütunlarını SEO puanıyla **aynı**
`seo_scan_results` satırına ekler veya günceller:

| Sütun | İçeriği |
|---|---|
| `ai_readiness_score` | 0-100 sayısı (eksen açıkken hedef taranana kadar null). |
| `ai_readiness_version` | Puanı üreten değerlendirme kuralları. |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]` — tam iz. |

Çalıştırma tamamlandığında çalıştırma başına ortalama `seo_scan_runs.avg_ai_readiness` üzerine
kaydedilir; `avg_score` ile aynı yaklaşım kullanılır. Bu, yapay zekâya hazırlık eğilimidir.

## Puanı okuma {#reading-the-score}

**Arayüzden bağımsız kullanım** — modelin en son sonucu iki ekseni de taşır:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament** — herhangi bir kaynak tablosunda SEO puanı sütununun yanına
eşlik eden sütunu ekleyin:

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

Puan, sayfa içi puan kartında (SEO başlığı alanının üzerinde) eşlik eden rozet
olarak da görünür. [Markanıza özel raporda](/tr/pro/reports) (PDF ve e-posta)
ayrı bölümde sayı, harf notu, önceki rapora göre değişim ve tarama bazında
eğilim sunar. Her zaman organik puanın yanında gösterilir; ona karıştırılmaz.

### Harf notu aralıkları {#grade-bands}

Sayıdan türetilen, sunum amaçlı harf notu (esas olan sayıdır); tutarlılık
için SEO puanıyla aynı aralıkları kullanır:

| Puan | Not |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Yapılandırma {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

Kontroller ve ağırlıklar yapılandırılabilir **değildir**: belirli bir
`ai_readiness_version` için puan her kurulumda deterministik olmalıdır. Hesaplama
yöntemini değiştirmek ayar değil, kod düzeyinde değerlendirme kuralı değişikliğidir.

::: warning Site sinyali algılama, süreç içi istek yolunu kullanır
Aynı sunucu adına ait hedefte tarama; `/robots.txt`, `/llms.txt` ve sayfayı
Laravel’in süreç içi HTTP çekirdeği üzerinden çözümler. Taramanın geri kalanı
da bu yolu kullanır. Laravel yönlendirmesini atlayarak **statik dosya** olarak
sunulan `robots.txt` veya `llms.txt` görülmez. Puanlamaya dahil olmaları
için paket rotalarından sunun; önerilen kurulum budur.
:::

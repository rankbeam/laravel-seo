---
description: "Pro taramasının 0–100 SEO puanı tamamen denetlenebilir ve deterministiktir. Düşülen her puan tek bir tarama sorununa dayanır; aynı sorunlar daima aynı sayıyı üretir."
---

# SEO puanı: şeffaf, sürümlü ve Pro'ya ait {#the-seo-score-—-transparent-versioned-pro-owned}

Pro taraması her sayfaya **0–100 SEO puanı** verir; RankMath veya Yoast'tan geçenlerin aradığı tek sayı budur. Hesaplama yöntemi gizli bir notun aksine **tamamen denetlenebilir**: düşülen her puan tam olarak bir [tarama sorununa](/tr/pro/scan-issues) dayanır ve aynı sorun kümesi daima aynı sayıyı üretir.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip Tek sayı, tek sorumlu
Sayısal puan bir **Pro** özelliğidir. Pro'nun `seo_scan_results` kaydında yer alır; core'un `seo_meta` kaydına geri yazılmaz. Eski `seo_score` sütunu Core 3'te kaldırılmıştır. Ücretsiz core [`seo:audit`](/tr/guide/audit), sayfa başına **geçti / uyarı / başarısız** sonucu bildirir ve **sayı vermez**; puan ücretli ek değerdir.
:::

## Puanlama ölçütleri {#the-rubric}

Puan, **yayımlanmış ve sürümlü ölçütlerle** hesaplanır: `Rankbeam\Seo\Pro\Scanning\ScoreRubric`. Bunu iki şey belirler: hesaba katılan sorun kodlarının açık bir **izin listesi** ve **önem derecesi başına sabit puan kesintisi**.

| Önem derecesi | Kesinti | Anlamı |
|---|---|---|
| `critical` | **−40** | Bu ölçütlerde yüksek etkili bulgu. |
| `warning` | **−15** | Yakında araştırılması gereken bulgu. |
| `notice` | **−5** | Yapılması yararlı bir iyileştirme. |

Her kodun önem derecesi, tek doğruluk kaynağı olan [sorun kaydından](/tr/pro/scan-issues) doğrudan okunur; ölçütler bunu yeniden türetmez. Puanın deterministik kalması için her kodun tek bir önem derecesi vardır.

### Puanın hesaba kattıkları {#what-the-score-counts}

Bunlar, editoryal yorum gerektiren sezgisel kontroller dahil, Rankbeam'in ürün ölçütleriyle seçilmiş deterministik kontrollerdir. Kritik sorun 40, uyarı 15, bildirim 5 puan düşürür; puan arama performansının tahmini değildir.

| Kod | Önem derecesi | Kesinti |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

Meta veri kodları model taramasında, oluşturulmuş sayfa/ağ kodları ise yalnızca URL taramasında tespit edildiğinden ([yürütme sınıflarına](/tr/pro/scan-issues#execution-classes) bakın), **model** hedefinin puanı meta veri kontrollerini, **URL** hedefinin puanı ise oluşturulmuş sayfayı yansıtır. Model taramasında 100 puan, “meta veri kusuru yok” demektir; “oluşturulan sayfa kusursuz” demek değildir. Bunun için URL'yi tarayın.

### Puanın bilinçli olarak hesaba KATMADIKLARI {#what-the-score-deliberately-does-not-count}

Bu kayıt kodları özellikle dışarıda bırakılmıştır. İstisnalar sözleşmenin parçasıdır; bir test, kayıtlı her kodun puanlandığını veya burada listelendiğini doğrular:

| Kod | Neden dışarıda |
|---|---|
| `missing_focus_keyword` | **Tavsiye.** İsteğe bağlı `seo.keywords.enabled` iş akışına bağlıdır. Bir sayfa odak anahtar kelime kullanmadığı için düşük puan almamalı ve puan bir yapılandırma seçeneğine bağlı olmamalıdır. |
| `noindex_page` | **Bilgi.** `noindex`, meta veri kalitesi kusuru değil, bilinçli bir durumdur. “Kendine işaret eden kanonik URL ile noindex” sezgisel kontrolü bunun yerine `noindex_warning` üzerinden puanlanır. |
| `multiple_h1` | **Bilgi.** Google birden fazla H1'e tolerans gösterir; çoklu H1 kesintisi yoktur. |
| `blocked_url` | **Kanıt yokluğu.** SsrfGuard isteği reddettiği için sayfa hiç kontrol edilmedi; sayfanın kusuru değildir. |
| `canonical_target_blocked` | **Kanıt yokluğu.** Kanonik hedef doğrulanamadı; sayfanın kusuru değildir. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Şimdilik tavsiye.** Bu Pro hreflang kodları taramada görünür; ücretsiz denetimin kendi hreflang kodları vardır ancak henüz puanı değiştirmez. Eklenmesi `VERSION` artışı gerektirir. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **Tavsiye.** Dil kontrolleri bu ölçütlerin dışında tutulur. |
| `hreflang_not_reciprocal` | **Tavsiye.** İsteğe bağlı karşılıklılık kontrolüdür; puanlanmaz. |
| `hreflang_target_unverified` | **Kanıt yokluğu.** Karşılıklılık doğrulanamadı. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Tavsiye.** Yanıt hazırlığı (AEO) sinyalleri, tarama ve ücretsiz denetimde yazar varlığı veya yayın tarihi eksik yazıları işaretler; puanı değiştirmez. Bunun için `VERSION` artışı gerekir. |

Anahtar kelime yoğunluğu, etkili sözcükler ve [sayfa içi kontrol listesinin](/tr/pro/on-page-checklist) geri kalanı puana hiç katılmaz. Bunlar kayıt kodları değil, ayrı bir geçti/uyarı/başarısız listesi oluşturan tavsiye kontrolleridir.

## Sürümleme: geçmiş puanlar sessizce değişmez {#versioning-—-historical-scores-never-silently-change}

Saklanan her puan, onu üreten `ScoreRubric::VERSION` ile damgalanır (`rubric_version`). Bunun iki sonucu vardır:

- **Yeni** bir sorun kodu, izin listesine bilinçli olarak eklenene kadar **hiç puan düşürmez**. Dolayısıyla yeni kontrol yayımlamak saklanan puanı geriye dönük değiştiremez. İzin listesi veya ağırlık değişikliği de ölçüt değişikliğidir ve sürümü artırır.
- Puan **saklanır; okunurken yeniden hesaplanmaz**. Geçen hafta gördüğünüz sayı, onu açıklayan ölçütlerle birlikte bugün de aynıdır.

## Nerede saklanır {#where-it-s-stored}

Her tarama, `seo_scan_results` içinde hedef başına bir satır ekler veya günceller:

| Sütun | İçeriği |
|---|---|
| `scannable_type` / `scannable_id` | Puanlanan model (URL hedefleri için null). |
| `url` | Puanlanan URL. |
| `score` | 0–100 arasındaki sayı. |
| `rubric_version` | Puanı üreten ölçütler. |
| `penalty_total` | Alt sınır olan 0 uygulanmadan **önceki** ham kesinti toplamı. |
| `scored_issues` | Sayıyı değiştiren sorun sayısı. |
| `breakdown` | `[{code, severity, penalty}, …]`: tam hesap dökümü. |
| `keywords_enabled` | Tarama anındaki `seo.keywords.enabled` durumu (şeffaflık için kaydedilir; puan buna bağlı değildir). |
| `scan_run_id` | Puanı hesaplayan çalıştırma (çalıştırma temizlenince satır silinmez, bu alan null yapılır; puanlar çalıştırma geçmişi değil, güncel durumdur). |
| `scored_at` | Puanlama zamanı. |

## Puanı okumak {#reading-the-score}

**Panelsiz**: bir modelin son puanı:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status`, özetinde **ortalama site puanını** gösterir. Filament panosu bunu nota göre renklendirilmiş “Ort. SEO puanı” ana istatistiği olarak sunar.

### Not aralıkları {#grade-bands}

Sayıdan türetilen, sunum amaçlı harf notu; esas alınan değer sayıdır:

| Puan | Not |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Yayın öncesi kontrol sinyali (`noindex_warning`) {#the-shipping-signal-noindex-warning}

`noindex_warning`, bir sayfa `noindex` ile **kendine işaret eden kanonik URL'yi** bir arada kullandığında tetiklenir. Rankbeam bunu yayın öncesinde incelenecek bir sinyal sayar. Kendine işaret eden kanonik URL, dizine ekleme niyetini **kanıtlamaz**; bu birleşim bilinçli olabilir. Başka alan adına işaret eden kanonik URL bu sezgisel kontrolü tetiklemez. Sorun, `context.shipping_signal` (örneğin `self_canonical`) ile karşılaştırılan `canonical` ve `page_url` değerlerini taşır.

İki tarayıcı da bu kontrolü uygular. Model taraması (`PageScanner`), saklanan kanonik URL'yi model URL'siyle karşılaştırır. Oluşturulan sayfanın URL taraması (`UrlScanner`), kendine işaret eden kanonik URL'ye sahip `noindex` sayfayı bilgi amaçlı `noindex_page` durumundan puanlanan `noindex_warning` durumuna yükseltir. `noindex_page` kodunun kendisi bu nedenle dışarıdadır: olası çelişki her iki yolda da `noindex_warning` ile ele alınır. Dizine ekleme yönergesini değiştirmeden önce sayfanın gerçek amacını inceleyin.

## Yapılandırma {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

İzin listesi ve ağırlıklar **yapılandırılamaz**: belirli bir `rubric_version` için bütün kurulumlarda puan deterministik olmalıdır. Hesaplama biçimini değiştirmek bir ayar değil, kod düzeyinde ölçüt değişikliğidir.

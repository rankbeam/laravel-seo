---
description: "php artisan seo:audit ile mevcut SEO sorunlarını sayfa başına pass/warn/fail tablosunda görün. İşlem içinde çalışır; kuyruk, lisans veya ağ gerekmez. Ücretsiz çekirdek özelliği."
---

# Ücretsiz SEO denetimi (`seo:audit`) {#free-seo-audit-seo-audit}

`php artisan seo:audit` tek komutla, ücretsiz olarak tek bir soruyu yanıtlar: **SEO'mda şu anda ne yanlış?** `HasSEO` modellerinizi işlem içinde dolaşır; **kuyruk, lisans veya ağ gerekmez**. Sayfa başına **pass / warn / fail** tablosu ve özet gösterir.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## Neleri kontrol eder? {#what-it-checks}

Denetim yalnızca **metadata** yürütme sınıfını çalıştırır: sayfayı getirmeden, yalnızca modelden ve [çözümleyiciden](/tr/concepts/resolver-precedence) sonuçlandırılabilen kontroller:

| Kontrol | Kodlar |
|---|---|
| Başlık / açıklama varlığı (yedek değerleri dikkate alır) | `missing_title`, `missing_description` |
| OG görseli varlığı (yedek değerleri dikkate alır) | `missing_og_image` |
| Başlık / açıklama uzunluğu | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Site genelinde yinelenen başlık / açıklama | `duplicate_title`, `duplicate_description` |
| Robots çakışmaları ve şüpheli noindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Kanonik URL biçimi / farklı alan adı / ortak kullanım / güvensiz protokol | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Yanıt motorlarına hazırlık (AEO) — makale yapılandırılmış verileri | `aeo_missing_author`, `aeo_article_missing_date` |
| Odak anahtar kelime atanması (isteğe bağlı) | `missing_focus_keyword` |
| hreflang alternatifleri (sayfada varsa çekirdek kayıt sistemi üzerinden) | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

Kodların çoğu Pro taramasında da bulunur, ancak kayıt sistemleri ayrıdır. Özellikle çekirdek `hreflang_missing_self` kullanırken Pro `hreflang_missing_self_reference` kullanır; `hreflang_duplicate_code` çekirdekte bildirim, Pro'da uyarıdır. Aynı adın kapsamın veya önem derecesinin aynı olduğu anlamına geldiğini varsaymayın. `blank_explicit_override` çekirdek kayıt sistemine aittir. Uzunluk, editörün [yazı sistemine göre bütçesini](/tr/guide/multilingual#title-and-description-budgets-per-script) kullanır: Latin metinlerde 60/160, CJK için yaklaşık 30/80 karakter. Grafem olarak sayılır ve son ek dahil **çözümlenen** değer üzerinden ölçülür; böylece denetim [Filament editörünün](/tr/guide/filament) karakter sayaçlarıyla çelişmez. Hreflang kontrolleri, `seo.hreflang` politikaları uygulandıktan sonraki liste üzerinde çalışır (etiketlerin ve site haritasının ürettiği listeyle aynı). Karşılıklılık kontrolü tarama gerektirir ve Pro'da kalır.

**Yanıt motorlarına hazırlık (AEO)** kontrolleri yalnızca sayfa makale türünde JSON-LD (`Article`, `BlogPosting`, `NewsArticle`, …) bildiriyorsa ve makaleyi yapılandırılmış veride anlaşılır kılan bir sinyal eksikse devreye girer: `author` varlığı (açık yazarlık / kaynak bilgisi) veya `datePublished` / `dateModified` (açık zaman çizelgesi). Makale içermeyen sayfa asla işaretlenmez; AEO'nun geçerli olmadığı yerde denetim sessiz kalır. Bu kontroller tavsiye niteliğindedir (bildirim düzeyi) ve Pro'nun 0–100 puanına dahil edilmez.

## Neleri kontrol etmez? Kapsam sınırı {#what-it-does-not-check-—-the-capability-boundary}

Ücretsiz, işlem içi denetim tam Pro taramasına eşdeğer değildir; komut her çalıştırmada bunu belirtir. Şunları **çalıştırmaz**:

- **Üretilen HTML kontrolleri** — `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content`, `mixed_content`. Bunlar sayfanın sunulan HTML'ini gerektirir.
- **Canlı kanonik URL ağ kontrolleri** — `canonical_target_broken` / `_redirect` / `_noindex`. Bunlar dışarıya korumalı bir istek gerektirir.
- **Sayısal 0–100 puanı.** Puan, sürümlenmiş bir değerlendirme ölçeğiyle tarama sonucu kaydında saklanan bir Pro özelliğidir; bkz. [SEO puanı](/tr/pro/scoring).

Bunlar **Pro taramasında** bulunur; tam [sorun kayıt sistemine](/tr/pro/scan-issues) bakın.

## Nelerin denetleneceğini seçme {#choosing-what-to-audit}

Komut varsayılan olarak `seo.audit.models` altında listelenen modelleri denetler; yedek kaynak olarak `seo.sitemap.models` kullanır:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Modelleri açıkça da iletebilirsiniz:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Seçenekler {#options}

| Seçenek | Etki |
|---|---|
| `--model=` | Denetlenecek bir `HasSEO` model sınıfı (tekrarlanabilir). Yapılandırmanın yerini alır. |
| `--locale=` | SEO verilerini bu dil koduyla çözümle (varsayılan: uygulamanın dil kodu). |
| `--limit=` | Model başına denetlenecek en fazla kayıt (`0` = tümü). |
| `--issues-only` | Yalnızca en az bir sorunu olan sayfaları listele. |
| `--strict` | Herhangi bir sorun bulunursa sıfırdan farklı durum koduyla çık; CI için. |
| `--json` | Tablo yerine makine tarafından okunabilir JSON üret (sayfalar, özet, kapsam). |

### CI kontrolü {#ci-gate}

`--strict` denetimi bir derleme kontrolüne dönüştürür:

```bash
php artisan seo:audit --strict
```

Herhangi bir sayfada uyarı veya başarısızlık varsa `1`, denetlenen tüm sayfalar geçerse `0` koduyla çıkar.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Odak anahtar kelimeler {#focus-keywords}

`missing_focus_keyword` bildirimi **varsayılan olarak kapalıdır**. Yalnızca odak anahtar kelime iş akışını etkinleştirdiğinizde devreye girer:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Pro taraması **aynı** bayrağı okur; bu nedenle denetim, tarama ve Pro editör hatırlatıcısı her zaman aynı kararı verir. Bir sayfanın anahtar kelimelerini [Filament odak anahtar kelime alanıyla](/tr/guide/filament) veya `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])` ile ayarlayın.

## Bir değer beklediğiniz gibi değilse: `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` *neyin yanlış olduğunu* söyler; [`seo:explain`](/tr/guide/explain) *alanın neden o değere çözümlendiğini* gösterir: her değeri hangi katmanın (yapılandırma / varsayılan / hesaplanan / açıkça atanan) belirlediğini, neyi geçersiz kıldığını ve sonraki işlemlerin (başlık son eki, kanonik sorgu temizliği, dizine ekleme koruması) neyi değiştirdiğini. Bir denetim bulgusu veya üretilen etiket şaşırtıcıysa bu komutu kullanın:

```bash
php artisan seo:explain "App\Models\Post" 42
```


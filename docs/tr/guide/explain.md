---
description: "seo:explain, her SEO alanını hangi çözümleyici katmanının belirlediğini ve neyi geçersiz kıldığını gösterir. Salt okunurdur; ağ veya lisans gerekmeden beklenmedik başlık ve robots değerlerini inceleyin."
---

# Değer çözümlemeyi açıklama (`seo:explain`) {#explain-the-resolution-seo-explain}

Rankbeam, sayfanın SEO verilerini [katmanlı bir öncelik sırasıyla](/tr/concepts/resolver-precedence) çözümler: yapılandırma, veritabanı varsayılanları (genel / model türü / rota), hesaplanan model değerleri ve son olarak açıkça atanan `seo_meta`. Ardından son işlemler (başlık son eki, kanonik URL, görsel URL'lerini mutlak hale getirme) ve [dizine ekleme koruması](/tr/guide/indexing-guard) uygulanır. Üretilen `<title>` veya `robots` etiketi beklediğiniz gibi değilse **`seo:explain` her alanı tam olarak hangi katmanın belirlediğini ve hangi değerleri geçersiz kıldığını gösterir.**

Salt okunurdur, ağ bağlantısı veya lisans gerektirmez ve birleştirme mantığını yeniden uygulamaz: kaynak bilgisi çözümleyicinin kendi katman katkılarından, son değerler ise gerçek çözümleyiciden gelir. Bu nedenle açıklama, gerçekte üretilen çıktıdan sapamaz.

## Kullanım {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

Model [`HasSEO`](/tr/guide/quickstart) trait'ini kullanmalıdır.

## Çıktıyı okuma {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by** — kazanan katman (null olmayan bir değer belirleyen en yüksek öncelikli katman) veya hiçbir katman alanı belirlemediği halde bir değer *türetildiyse* `post-processing` (istek/model URL'sinden kanonik URL, kanonik URL'den og:url, mutlak hale getirilen görsel URL'si).
- **Overrode** — değer sağlayıp kaybeden tüm daha düşük öncelikli katmanlar, sırasıyla gösterilir; böylece hangi değerlerin gölgede kaldığını görebilirsiniz.
- **↳ notes** — katmanlar birleştirildikten sonra değeri değiştiren son işlemler: başlık son eki, kanonik URL'nin sorgu dizesinin kaldırılması, og:url türetme, görsel URL'lerini mutlak hale getirme ve tüm katmanların üzerinde `noindex` zorlayan dizine ekleme koruması.

::: tip og:type ve twitter:card
Bu iki alan null olmayan framework varsayılanları taşır (`website` / `summary_large_image`). Dolayısıyla onları belirleyen en yüksek katman — normalde `computed` — `config` katmanından önceliklidir. Kayıtlı `seo_meta` satırı olmayan bir sayfa, bu alanlara hiçbir değer sağlamaz. Böylece `article` gibi hesaplanan bir `og:type` değeri, yalnızca yalın `website` varsayılanı nedeniyle gölgede kalmaz. Bu, birleştirmenin onları gerçekte çözümleme biçimiyle aynıdır.
:::

## Site düzeyinde çözümleme {#site-level-resolution}

[Site yapılandırması kaynak dökümüne ilişkin ek açıklamaya](/tr/concepts/resolver-precedence) göre `seo:explain`, kaynağı sıkça karıştırılan site geneli değerlerini de raporlar: **kanonik ana makineyi, site adını ve varsayılan dil kodunu hangi kaynak belirledi?**

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Kanonik ana makine, kontrol edilmesi en yararlı değerdir. Yanlış ana makine — çıktıya sızan bir `localhost`, `https` kullanan bir sitede `http://` veya model URL'siyle uyuşmayan uygulama URL'si — sayfanın kendine işaret etmesi gereken kanonik bağlantılardaki hataların yaygın nedenidir.

## JSON çıktısı {#json-output}

`--json`, araçlar veya CI için tüm izi üretir: `target`, alan başına `winner` / `losers` / `final` / `notes` ve `site_level` kaynak dökümü:

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## Ayrıca bakın {#see-also}

- [Çözümleyici öncelik sırası](/tr/concepts/resolver-precedence) — `seo:explain` tarafından izlenen tüm zincir.
- [Ücretsiz SEO denetimi](/tr/guide/audit) — `seo:audit` *neyin yanlış olduğunu* bulur; `seo:explain` *bir değerin neden o şekilde olduğunu* gösterir.

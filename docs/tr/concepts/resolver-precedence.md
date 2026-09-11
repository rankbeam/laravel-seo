---
description: "Rankbeam her SEO değerini nasıl çözümler? Altı katman öncelik sırasıyla birleşir, üst katmanlar kazanır ve null mevcut değerin üzerine yazmaz; her sayfa anlamlı bir çıktı üretir."
---

# Çözümleyici öncelik sırası {#resolver-precedence}

Geçerli her SEO değeri — başlık, açıklama, kanonik URL, robots ve görseller — `SEOResolver` tarafından **altı katman** birleştirilerek üretilir. Üst katmanlar kazanır; `null` alt katmandaki bir değerin üzerine asla yazmaz. Böylece her sayfa anlamlı bir çıktı üretir.

## Altı katman {#the-six-layers}

En düşükten (her zaman bulunur) en yükseğe (her zaman kazanır):

| # | Katman | Kaynak | Yaygın kullanım |
|---|---|---|---|
| 1 | **Site yapılandırması** | `config/seo.php` (`site_name`, `title_suffix`, `default_og_image`, `default_robots`, …) | Marka genelindeki varsayılanlar |
| 2 | **Genel veritabanı varsayılanları** | Model türü belirtilmeyen `seo_defaults` satırları | Yeni sürüm dağıtmadan düzenlenebilen site geneli varsayılanları |
| 3 | **Model türü varsayılanları** | Bir model sınıfıyla sınırlandırılmış `seo_defaults` satırları | “Tüm ürünlerde bu OG görseli kullanılsın” |
| 4 | **Rota varsayılanları** | Bir rota adıyla sınırlandırılmış `seo_defaults` satırları | Modeli olmayan statik sayfalar (`home`, `contact`) |
| 5 | **Hesaplanan değerler** | Modelin kendi özniteliklerinden türetilir | Yedek başlık `title` üzerinden, açıklama `excerpt`/`body` üzerinden, … |
| 6 | **Açıkça atanan değerler** | Modelin `seo_meta` satırı (`saveSEO()`) | Editörlerin elle ayarladıkları |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

Sonuç, tüm renderer'ların (Blade, dizi, Inertia) kullandığı değiştirilemez bir `SEOData` değer nesnesidir.

## Hesaplanan yedek değerler (5. katman) {#computed-fallbacks-layer-5}

Açıkça atanmış bir değer olmadığında çözümleyici modelden bir değer türetir:

- **Başlık** — modelin `title`/`name` özniteliği.
- **Açıklama** — `seo.computed.description_fields` içindeki anlamlı metin içeren ilk öznitelik (varsayılan sıra: `excerpt`, `summary`, `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`, `article`). HTML kaldırılır, HTML varlıkları çözümlenir ve metin sözcük sınırında kısaltılır (`seo.computed.description_max_length`, varsayılan 160; üç nokta eklenmez).
- **Robots** — modelin `getSEORobots()` kancasından veya `is_indexable` özniteliğinden alınır (bkz. [robots ve dizine eklenebilirliği yönetme](#controlling-robots-and-indexability)).
- **URL'den türetilen değerler** — kanonik URL ve `og:url`, `getUrlForSEO()` üzerinden türetilir.

## Robots ve dizine eklenebilirliği yönetme {#controlling-robots-and-indexability}

Model başına `noindex` desteği yerleşiktir; ek paket veya özel sütun kurulumu gerekmez. `HasSEO` trait'i robots metodunu *tanımlamaz* (metot isteğe bağlıdır), bu nedenle gözden kaçabilir. Ancak çözümleyici aşağıdaki üç kaynağı zaten dikkate alır; en yüksek öncelik ilk sıradadır:

| Öncelik | Kaynak | Örnek |
|---|---|---|
| 1 | **Açıkça atanan `seo_meta.robots`** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | Modelde bir **`getSEORobots(): ?string` kancası** | `'noindex, nofollow'` döndürün veya sonraki kaynağa geçmek için `null` döndürün |
| 3 | Bir **`is_indexable` özniteliği** (sütun veya accessor) | false olarak değerlendirilen değer ⇒ `noindex, nofollow`; true olarak değerlendirilen değer ⇒ `index, follow` |

```php
class Page extends Model
{
    use HasSEO;

    // Option A: let the resolver derive robots from a boolean flag.
    //   Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));

    // Option B: compute it from your own state.
    public function getSEORobots(): ?string
    {
        return $this->status === 'draft' ? 'noindex, nofollow' : null;
    }
}

// Option C: set it explicitly per page (wins over A and B).
$page->saveSEO(['robots' => 'noindex, follow']);
```

### Çıktıda gerçekte ne bulunur? {#what-actually-renders}

Çözümlenen yönerge, `<head>` bölümüne ulaşmadan önce **çıktı politikası** tarafından süzülür: `<meta name="robots">` etiketi **yalnızca yönerge `default_robots` değerinden farklıysa** üretilir (varsayılan `index,follow`). Buna göre:

- **Dizine eklenebilir** bir sayfa (`index, follow` olarak çözümlenir) **robots etiketi üretmez**; tarayıcı botlar bu etiketin yokluğunu zaten index,follow olarak yorumlar.
- **Dizine eklenemeyen** bir sayfa `<meta name="robots" content="noindex, nofollow">` üretir.
- Varsayılandan farklı her yönerge (`noindex`, `max-snippet:-1`, `unavailable_after`, …) yazdığınız boşluklar korunarak **aynen** üretilir.

Etiketi her zaman üretmek için `seo.robots.emit_default = true` ayarını yapın. Tüm ayrıntılar [robots çıktı politikasında](/tr/reference/configuration#robots-rendering-policy) açıklanır.

## Çözümlemeden sonra uygulanan politikalar {#policies-applied-after-resolution}

Değerin hangi katmandan geldiğine bakılmaksızın uygulanırlar:

- **Başlık son eki** — çözümlenen başlık zaten bu ekle bitmiyorsa `title_suffix` eklenir. Bir rota varsayılanı şablonu markanızı zaten içeriyorsa “Brand — X | Brand” sonucunu önlemek için şablonu son ekle bitirin.
- **Kanonik URL'den sorgu parametrelerini kaldırma** — *türetilen* kanonik URL'lerin (model URL'si / geçerli URL) sorgu dizesi kaldırılır; [`canonical.query_whitelist`](/tr/reference/configuration#canonical-urls) içindeki anahtarlar korunur (örneğin sayfalanmış arşivler için `page`). *Açıkça atanan* kanonik URL'ler aynen korunur.
- **Mutlak sosyal görsel URL'leri** — saklanan değer göreli bir yol olsa bile `og:image` ve `twitter:image` her zaman mutlak URL olarak üretilir (Open Graph belirtimi bunu gerektirir).

## Hangi katmanın kazandığını inceleme {#inspecting-which-layer-won}

[Filament paketi](/tr/guide/filament) bunu her alan için gösterir (Elle girilmiş / İçerikten türetilmiş / Model türü varsayılanı / Genel varsayılan / Site yapılandırması / URL'den türetilmiş). Kodda `SEOWarningEvaluator`, kendi yönetim göstergelerinizi oluşturmanız için aynı elle girilen değer / yedek değer ayrımını sunar.

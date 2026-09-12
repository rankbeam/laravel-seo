---
description: "Jak Rankbeam vyhodnocuje každou SEO hodnotu: šest vrstev podle přednosti, vyšší vrstvy vítězí a null nikdy nepřepisuje, aby stránky vykreslovaly smysluplné hodnoty."
---

# Pořadí přednosti resolveru {#resolver-precedence}

Každou výslednou SEO hodnotu — titulek, popis, kanonickou adresu, robots i obrázky — vytváří `SEOResolver` sloučením **šesti vrstev**. Vyšší vrstvy mají přednost a `null` nikdy nepřepisuje hodnotu nižší vrstvy, takže stránka vykresluje smysluplný výsledek.

## Šest vrstev {#the-six-layers}

Od nejnižší, vždy přítomné, po nejvyšší, která má vždy přednost:

| # | Vrstva | Zdroj | Typické použití |
|---|---|---|---|
| 1 | **Konfigurace webu** | `config/seo.php` (`site_name`, `title_suffix`, `default_og_image`, `default_robots`, …) | Výchozí hodnoty celé značky |
| 2 | **Globální výchozí hodnoty z DB** | Řádky `seo_defaults` bez typu modelu | Úpravy výchozích hodnot celého webu bez nasazení |
| 3 | **Výchozí hodnoty typu modelu** | Řádky `seo_defaults` pro konkrétní třídu modelu | „Všechny produkty dostanou tento obrázek OG“ |
| 4 | **Výchozí hodnoty routy** | Řádky `seo_defaults` pro název routy | Statické stránky (`home`, `contact`) bez modelu |
| 5 | **Vypočtené hodnoty** | Odvozené z vlastních atributů modelu | Náhradní titulek z `title`, popis z `excerpt`/`body`, … |
| 6 | **Explicitní hodnoty** | Řádek `seo_meta` modelu (`saveSEO()`) | Ručně nastavené hodnoty redaktorů |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

Výsledkem je neměnný hodnotový objekt `SEOData` používaný všemi renderery: Blade, pole i Inertia.

## Vypočtené náhradní hodnoty (vrstva 5) {#computed-fallbacks-layer-5}

Pokud explicitní hodnota neexistuje, resolver ji odvodí z modelu:

- **Titulek** — atribut `title`/`name` modelu.
- **Popis** — první atribut v `seo.computed.description_fields`, který obsahuje smysluplný text. Výchozí pořadí: `excerpt`, `summary`, `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`, `article`. HTML se odstraní, entity dekódují a text se zkrátí na hranici slova (`seo.computed.description_max_length`, výchozí hodnota 160, bez výpustky).
- **Robots** — metoda `getSEORobots()` modelu nebo atribut `is_indexable`. Viz [ovládání robots a indexovatelnosti](#controlling-robots-and-indexability).
- **Hodnoty odvozené z URL** — kanonická adresa a `og:url` z `getUrlForSEO()`.

## Ovládání robots a indexovatelnosti {#controlling-robots-and-indexability}

`noindex` pro jednotlivé modely je vestavěné, bez dalšího balíčku a zbytečného přidávání sloupců. Trait `HasSEO` metodu robots *nedeklaruje*, protože je volitelná, takže ji lze snadno přehlédnout. Resolver ale už respektuje tři zdroje, v pořadí od nejvyšší priority:

| Priorita | Zdroj | Příklad |
|---|---|---|
| 1 | **Explicitní `seo_meta.robots`** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | **Metoda `getSEORobots(): ?string`** na modelu | Vrátí `'noindex, nofollow'`, případně `null` pro přechod na další zdroj |
| 3 | **Atribut `is_indexable`** (sloupec nebo accessor) | Nepravdivá hodnota ⇒ `noindex, nofollow`; pravdivá ⇒ `index, follow` |

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

### Co se skutečně vykreslí {#what-actually-renders}

Vyhodnocený pokyn před vložením do `<head>` prochází **pravidly výstupu**. Značka `<meta name="robots">` se vypíše **jen tehdy, když se pokyn liší od `default_robots`** (výchozí `index,follow`). Platí tedy:

- **Indexovatelná** stránka vyhodnocená jako `index, follow` nevypíše **žádnou značku robots**. Její nepřítomnost robot vyhodnocuje právě jako index,follow.
- **Neindexovatelná** stránka vypíše `<meta name="robots" content="noindex, nofollow">`.
- Odlišný pokyn (`noindex`, `max-snippet:-1`, `unavailable_after`, …) se vypíše **beze změny**, včetně zadaných mezer.

Pro vždy vykreslenou značku nastavte `seo.robots.emit_default = true`. Podrobnosti najdete v [pravidlech vykreslování robots](/cs/reference/configuration#robots-rendering-policy).

## Pravidla uplatňovaná po vyhodnocení {#policies-applied-after-resolution}

Použijí se bez ohledu na vrstvu, ze které hodnota pochází:

- **Přípona titulku** — `title_suffix` se přidá, pokud jí vyhodnocený titulek už nekončí. Pokud šablona výchozí hodnoty routy obsahuje značku, zakončete ji příponou, aby nevzniklo „Brand — X | Brand“.
- **Odstranění parametrů kanonické URL** — z *odvozených* kanonických adres, tedy URL modelu nebo aktuální URL, se odstraní řetězec dotazu. Výjimkou jsou klíče z [`canonical.query_whitelist`](/cs/reference/configuration#canonical-urls), například `page` pro stránkované archivy, které zůstávají. *Explicitně nastavené* kanonické adresy se zachovávají beze změny.
- **Absolutní sociální obrázky** — `og:image` a `twitter:image` se vždy vypisují jako absolutní URL, jak vyžaduje specifikace Open Graph, i když je uložená hodnota relativní cestou.

## Zjištění vítězné vrstvy {#inspecting-which-layer-won}

[Balíček Filament](/cs/guide/filament) zobrazuje zdroj u jednotlivých polí: Ručně / Náhrada z obsahu / Výchozí pro typ modelu / Globální výchozí / Konfigurace webu / Odvozeno z URL. V kódu `SEOWarningEvaluator` poskytuje stejné rozlišení mezi ruční a náhradní hodnotou pro vlastní ukazatele v administraci.

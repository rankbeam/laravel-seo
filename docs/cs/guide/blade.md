---
description: "Vykreslujte SEO v serverových aplikacích Laravelu pomocí direktiv Blade: univerzální @seo vyhodnotí model a vypíše metadata, Open Graph, Twitter Cards a JSON-LD."
---

# Průvodce Blade {#blade-guide}

Pro klasické aplikace vykreslované na serveru poskytuje balíček sedm direktiv Blade. Obvykle stačí jediná: `@seo`.

## Univerzální direktiva {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` vyhodnotí model podle [pořadí přednosti](/cs/concepts/resolver-precedence) a vykreslí úplný blok hlavičky: `<title>`, meta popis, kanonický odkaz, robots, značky Open Graph a Twitter Card a připojená JSON-LD. Značka robots se vypíše **jen při odchylce od výchozí hodnoty webu**. Nadbytečné `index,follow` se vynechá, protože už nepřítomnost značky znamená index,follow. Pro vždy vykreslenou značku nastavte `seo.robots.emit_default`. Viz úplná [pravidla vykreslování](/cs/contributing/rendering-contract).

Signatury:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` přijímá `Model`, ručně sestavený `SEOData` nebo `null`. Argumenty routy a jazykové verze se použijí pouze pro `Model`/`null`. Ručně sestavený `SEOData` nese vlastní hodnoty.

## Stránky rout bez modelu {#route-pages-no-model}

Pro statické stránky, archivy a další stránky založené na routách:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Hodnoty rout pocházejí z řádků `seo_defaults` přiřazených k názvu routy.

## Stránky bez modelu: ručně sestavený `SEOData` {#model-less-pages-hand-built-seodata}

Výpisy, výsledky vyhledávání a další obsah sestavený v controlleru často nemají jeden společný model. Vytvořte `SEOData` a předejte ho přímo do `@seo` nebo fasády `SEO`. Není potřeba používat `app(TagRenderer::class)->render(...)`:

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

Ručně sestavený `SEOData` se považuje za **explicitní záměr**. Každá nastavená hodnota zůstane zachována; při vykreslování se doplní jen chybějící údaje:

- Chybějící `canonical` / `og:url` se odvodí z aktuální URL. Explicitní `canonical` zůstane beze změny včetně řetězce dotazu.
- `title_suffix` se přidá jen tehdy, když v titulku chybí. Pokud už titulek obsahuje token značky, zcela se vynechá; viz [`title_suffix_skip_when_contains`](/cs/reference/configuration).
- Relativní cesty `og:image` / `twitter:image` se převedou na absolutní pomocí `url()`. Respektuje aktuální schéma a **nevynucuje HTTPS**.
- `og:site_name` a `locale` se doplní z konfigurace a jazykové verze aplikace.

Databázové pořadí přednosti — globální výchozí hodnoty, hodnoty typu modelu, routy a `seo_meta` — se do ručně sestaveného `SEOData` **neslučuje**. Vykreslí se předané hodnoty s výše uvedeným doplněním chybějících údajů.

Stejná hodnota funguje i přes fasádu:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Použitelný vzor layoutu {#a-layout-pattern-that-scales}

Jeden layout pro stránky modelů, rout i ostatní případy:

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

Controllery potom předávají `'seoModel' => $post` nebo `'seoRoute' => 'blog.index'` a do značek nezasahují.

## Jednotlivé direktivy {#granular-directives}

Pokud potřebujete ovládat značky samostatně, například při kombinaci s výstupem jiného balíčku:

| Direktiva | Výstup |
|---|---|
| `@seoTitle($post)` | Pouze `<title>` |
| `@seoMeta($post)` | Pouze meta popis |
| `@seoCanonical($post)` | Pouze kanonický odkaz, s aktuální URL jako náhradní hodnotou |
| `@seoRobots($post)` | Pouze meta robots, vždy vykreslené. Jde o výslovné zapnutí, takže se **neuplatňuje** vynechání výchozí hodnoty jako u `@seo` |
| `@seoSchema($post)` | Pouze JSON-LD `<script>`, platné v hlavičce i těle |

Všechny přijímají stejný výraz `($model, $route, $locale)` jako `@seo` nebo žádný argument pro aktuální stránku.

## Alternativy hreflang {#hreflang-alternates}

Modely používající `HasSEO` mohou odkazy hreflang poskytovat přímo přes resolver:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Používejte absolutní URL. `@seo($post)` záznamy vyhodnotí a každý vykreslí jako `<link rel="alternate" hreflang="..." href="...">`. Kódy se nejprve převedou do tvaru BCP 47 (`it_IT` → `it-IT`). Pravidla `seo.hreflang` mohou přidat odkaz stránky na sebe a `x-default`. Bezplatný audit označí neplatné, duplicitní záznamy a chybějící odkaz na sebe. Viz [vícejazyčný obsah](/cs/guide/multilingual#hreflang).

## Escapování a bezpečnost {#escaping-and-safety}

Textové hodnoty se escapují pomocí `e()`. JSON-LD se kóduje s `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`, takže `</script>` v uživatelském obsahu nemůže předčasně ukončit element skriptu.

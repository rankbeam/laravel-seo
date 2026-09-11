---
description: "Generuj SEO w aplikacji Laravel renderowanej na serwerze za pomocą dyrektyw Blade. Dyrektywa @seo rozstrzyga dane modelu i zwraca meta, Open Graph, Twitter Cards oraz JSON-LD."
---

# Przewodnik Blade {#blade-guide}

Do klasycznych aplikacji renderowanych na serwerze pakiet dostarcza siedem dyrektyw Blade. Zwykle wystarcza jedna z nich: `@seo`.

## Dyrektywa generująca całość {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` rozstrzyga dane SEO modelu według [łańcucha priorytetów](/pl/concepts/resolver-precedence) i generuje kompletny blok head: `<title>`, metaopis, link kanoniczny, robots, tagi Open Graph, tagi Twitter Card i dołączone JSON-LD. Tag robots jest generowany **tylko wtedy, gdy różni się od domyślnej wartości witryny**. Zbędne `index,follow` jest pomijane, ponieważ jego brak już oznacza index,follow. Ustaw `seo.robots.emit_default`, aby zawsze go generować. Zobacz pełny [kontrakt renderowania](/pl/contributing/rendering-contract).

Sygnatury:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` przyjmuje `Model`, ręcznie zbudowane `SEOData` lub `null`. Argumenty trasy i locale dotyczą tylko ścieżki `Model`/`null`; ręcznie zbudowane `SEOData` zawiera własne wartości.

## Strony tras bez modelu {#route-pages-no-model}

Dla stron statycznych, archiwów i innych stron opartych na trasach:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Wartości trasy pochodzą z rekordów `seo_defaults` przypisanych do jej nazwy.

## Strony bez modelu: ręcznie zbudowane `SEOData` {#model-less-pages-hand-built-seodata}

Listy, wyniki wyszukiwania i strony składane w kontrolerze często nie mają jednego modelu źródłowego. Zbuduj `SEOData` i przekaż bezpośrednio do `@seo` (lub fasady `SEO`); nie musisz sięgać po `app(TagRenderer::class)->render(...)`:

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

Ręcznie zbudowane `SEOData` jest traktowane jako **jawna intencja**. Każda ustawiona wartość zostaje zachowana. Podczas renderowania uzupełniane są jedynie braki:

- `canonical` / `og:url` są wyprowadzane z bieżącego URL-a, gdy ich brakuje (jawne `canonical` pozostaje bez zmian, wraz z ciągiem zapytania);
- `title_suffix` jest dopisywany tylko wtedy, gdy tytuł go nie zawiera, a pomijany całkowicie, jeśli tytuł zawiera już wskazany token marki — zobacz [`title_suffix_skip_when_contains`](/pl/reference/configuration);
- względne ścieżki `og:image` / `twitter:image` są zamieniane na bezwzględne przez `url()` (z uwzględnieniem bieżącego schematu URL-a; **nie** wymusza HTTPS);
- `og:site_name` i `locale` są uzupełniane z konfiguracji / locale aplikacji.

Łańcuch priorytetów bazy danych (wartości domyślne globalne / dla typu modelu / dla trasy / `seo_meta`) **nie** jest scalany z ręcznie zbudowanym `SEOData`. Renderowane są przekazane dane, z uzupełnieniem opisanych wyżej braków.

Ten sam obiekt działa przez fasadę:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Wzorzec układu, który można rozbudowywać {#a-layout-pattern-that-scales}

Jeden układ obsługujący strony modeli, strony tras i pozostałe przypadki:

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

Kontrolery przekazują następnie `'seoModel' => $post` lub `'seoRoute' => 'blog.index'` i nie ingerują w znaczniki.

## Dyrektywy szczegółowe {#granular-directives}

Gdy potrzebujesz kontroli nad pojedynczymi tagami, np. łącząc wynik z innym pakietem:

| Dyrektywa | Co generuje |
|---|---|
| `@seoTitle($post)` | Tylko `<title>` |
| `@seoMeta($post)` | Tylko metaopis |
| `@seoCanonical($post)` | Tylko link kanoniczny (zastępczo używa bieżącego URL-a) |
| `@seoRobots($post)` | Tylko tag meta robots — zawsze generowany. To jawne włączenie, więc **nie** stosuje pomijania wartości domyślnej, które wykonuje `@seo` |
| `@seoSchema($post)` | Tylko `<script>` JSON-LD — poprawny zarówno w head, jak i body |

Wszystkie przyjmują to samo wyrażenie `($model, $route, $locale)` co `@seo` lub działają bez argumentu dla bieżącej strony.

## Warianty hreflang {#hreflang-alternates}

Modele używające `HasSEO` mogą dostarczać linki hreflang bezpośrednio przez resolver:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Używaj bezwzględnych URL-i. `@seo($post)` rozstrzyga te wpisy i generuje każdy jako `<link rel="alternate" hreflang="..." href="...">`. Najpierw kody są przekształcane do formatu BCP 47 (`it_IT` → `it-IT`), a reguły `seo.hreflang` mogą dodać odwołanie do samej strony i `x-default`. Bezpłatny audyt oznacza wpisy nieprawidłowe, zduplikowane lub pozbawione odwołania do siebie. Zobacz [Treści wielojęzyczne](/pl/guide/multilingual#hreflang).

## Escapowanie i bezpieczeństwo {#escaping-and-safety}

Wartości tekstowe są escapowane za pomocą `e()`. JSON-LD jest kodowany z `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`, więc `</script>` w treści użytkownika nie może wydostać się poza element script.

---
description: "Render SEO in servergerenderde Laravel-apps met de Blade-directives van het pakket — de alles-in-één-directive @seo verwerkt een model en genereert metadata, Open Graph, Twitter Cards en JSON-LD."
---

# Blade-gids {#blade-guide}

Voor klassieke servergerenderde apps levert het pakket zeven Blade-directives.
Eén daarvan — `@seo` — is meestal alles wat je nodig hebt.

## De alles-in-één-directive {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` verwerkt het model via de [voorrangsvolgorde](/nl/concepts/resolver-precedence)
en rendert het volledige head-blok: `<title>`, metabeschrijving, canonieke
link, robots, Open Graph-tags, Twitter Card-tags en gekoppelde JSON-LD. De
robots-tag wordt **alleen gegenereerd wanneer hij afwijkt van de sitestandaard** — een
overbodige `index,follow` wordt weggelaten (de afwezigheid betekent al index,follow).
Stel `seo.robots.emit_default` in om hem altijd te renderen. Zie het volledige
[renderingcontract](/nl/contributing/rendering-contract).

Aanroepvormen:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` accepteert een `Model`, een handmatig opgebouwd `SEOData` of `null`. De argumenten voor route en locale
gelden alleen voor het `Model`-/`null`-pad — een handmatig opgebouwd `SEOData` bevat
zijn eigen waarden.

## Routepagina's (zonder model) {#route-pages-no-model}

Voor statische pagina's, archieven en andere routegebaseerde pagina's:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Routewaarden komen uit `seo_defaults`-rijen die aan de routenaam zijn gekoppeld.

## Pagina's zonder model: handmatig opgebouwde `SEOData` {#model-less-pages-hand-built-seodata}

Overzichten, zoekresultaten en andere pagina's die in een controller worden samengesteld, zijn vaak niet op
één model gebaseerd. Bouw een `SEOData` en geef die rechtstreeks door aan `@seo` (of de
`SEO`-facade) — `app(TagRenderer::class)->render(...)` is daarvoor niet nodig:

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

Een handmatig opgebouwd `SEOData` geldt als een **expliciete keuze**. Elke ingestelde waarde
blijft behouden; alleen ontbrekende waarden worden tijdens rendering aangevuld:

- `canonical` / `og:url` worden bij ontbreken afgeleid uit de huidige URL (een
  expliciete `canonical` blijft letterlijk behouden, inclusief querystring);
- het `title_suffix` wordt alleen toegevoegd wanneer het nog niet in de titel staat (en wordt
  helemaal overgeslagen als de titel al een merktoken bevat — zie
  [`title_suffix_skip_when_contains`](/nl/reference/configuration));
- relatieve `og:image`-/`twitter:image`-paden worden absoluut gemaakt met `url()`
  (dat het huidige URL-protocol respecteert en HTTPS **niet** afdwingt);
- `og:site_name` en `locale` worden ingevuld vanuit de configuratie / app-locale.

De voorrangsvolgorde uit de database (globale standaarden / modeltypestandaarden / routestandaarden /
`seo_meta`-standaarden) wordt **niet** in een handmatig opgebouwd `SEOData` samengevoegd — wat je meegeeft, wordt
gerenderd, met alleen de bovenstaande aanvullingen.

Dezelfde waarde werkt via de facade:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Een layoutpatroon dat meegroeit {#a-layout-pattern-that-scales}

Eén layout voor modelpagina's, routepagina's en alle andere pagina's:

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

Controllers geven vervolgens `'seoModel' => $post` of `'seoRoute' => 'blog.index'` door
en hoeven nooit markup aan te passen.

## Afzonderlijke directives {#granular-directives}

Wanneer je individuele tags wilt beheren (bijvoorbeeld om ze met de uitvoer van een ander
pakket te combineren):

| Directive | Rendert |
|---|---|
| `@seoTitle($post)` | alleen `<title>` |
| `@seoMeta($post)` | alleen de metabeschrijving |
| `@seoCanonical($post)` | alleen de canonieke link (valt terug op de huidige URL) |
| `@seoRobots($post)` | alleen robots-metadata — altijd gerenderd (dit is een expliciete keuze, dus de onderdrukking bij een ongewijzigde standaardwaarde die `@seo` toepast, geldt hier **niet**) |
| `@seoSchema($post)` | alleen het JSON-LD-`<script>` — geldig in head of body |

Ze accepteren allemaal dezelfde `($model, $route, $locale)`-expressie als `@seo`,
of geen argument voor de huidige pagina.

## Hreflang-alternatieven {#hreflang-alternates}

Modellen met `HasSEO` kunnen hreflang-links rechtstreeks via de resolver leveren:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Gebruik absolute URL's. `@seo($post)` verwerkt deze vermeldingen en rendert elke vermelding als
`<link rel="alternate" hreflang="..." href="...">`. Codes worden eerst omgezet naar
hun BCP 47-vorm (`it_IT` → `it-IT`). De `seo.hreflang`-regels kunnen
de zelfverwijzing van de pagina en een `x-default` toevoegen; de gratis audit signaleert
ongeldige codes, duplicaten en ontbrekende zelfverwijzingen. Zie
[Meertalige content](/nl/guide/multilingual#hreflang).

## Escaping en veiligheid {#escaping-and-safety}

Tekstwaarden worden met `e()` geëscapet. JSON-LD wordt gecodeerd met
`JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`, zodat een
`</script>` in gebruikerscontent niet uit het script-element kan ontsnappen.

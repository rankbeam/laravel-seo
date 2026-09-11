---
description: "Hoe Rankbeam elke SEO-waarde bepaalt: zes lagen worden op prioriteit samengevoegd, hogere lagen gaan voor en null overschrijft nooit een waarde, zodat elke pagina zinvolle uitvoer heeft."
---

# Voorrangsvolgorde van de resolver {#resolver-precedence}

Elke effectieve SEO-waarde — titel, beschrijving, canonieke URL, robots en afbeeldingen —
ontstaat doordat `SEOResolver` **zes lagen** samenvoegt. Hogere lagen gaan voor en
`null` overschrijft nooit een waarde uit een lagere laag, zodat elke pagina altijd
zinvolle uitvoer heeft.

## De zes lagen {#the-six-layers}

Van de laagste (altijd aanwezig) tot de hoogste (gaat altijd voor):

| # | Laag | Bron | Typisch gebruik |
|---|---|---|---|
| 1 | **Siteconfiguratie** | `config/seo.php` (`site_name`, `title_suffix`, `default_og_image`, `default_robots`, …) | Standaardwaarden voor het hele merk |
| 2 | **Globale databasestandaarden** | `seo_defaults`-rijen zonder modeltype | Bewerkbare standaardwaarden voor de hele site, zonder deployment |
| 3 | **Standaarden per modeltype** | `seo_defaults`-rijen voor een modelklasse | "Alle producten krijgen deze OG-afbeelding" |
| 4 | **Routestandaarden** | `seo_defaults`-rijen voor een routenaam | Statische pagina's (`home`, `contact`) zonder model |
| 5 | **Berekende waarden** | Afgeleid uit de eigen attributen van het model | Terugvaltitel uit `title`, beschrijving uit `excerpt`/`body`, … |
| 6 | **Expliciete waarden** | De `seo_meta`-rij van het model (`saveSEO()`) | Wat redacteuren handmatig instellen |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

Het resultaat is een onveranderlijk `SEOData`-waardeobject dat elke renderer gebruikt
(Blade, array, Inertia).

## Berekende terugvalwaarden (laag 5) {#computed-fallbacks-layer-5}

Wanneer er geen expliciete waarde is, leidt de resolver er een af uit het model:

- **Titel** — het attribuut `title`/`name` van het model.
- **Beschrijving** — het eerste attribuut in
  `seo.computed.description_fields` (standaardvolgorde: `excerpt`, `summary`,
  `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`,
  `article`) dat betekenisvolle tekst bevat. HTML wordt verwijderd, entiteiten worden
  gedecodeerd en de tekst wordt op een woordgrens afgekapt
  (`seo.computed.description_max_length`, standaard 160 — zonder weglatingsteken).
- **Robots** — uit een `getSEORobots()`-hook of een `is_indexable`-attribuut
  van het model (zie [Robots en indexeerbaarheid beheren](#controlling-robots-and-indexability)).
- **Waarden afgeleid uit de URL** — de canonieke URL en `og:url` uit `getUrlForSEO()`.

## Robots en indexeerbaarheid beheren {#controlling-robots-and-indexability}

`noindex` per model is ingebouwd — zonder extra pakket of omslachtige kolomconfiguratie. De
`HasSEO`-trait *declareert* geen robots-methode (die is optioneel), waardoor je die gemakkelijk
over het hoofd ziet. De resolver gebruikt echter al drie bronnen, met de hoogste prioriteit eerst:

| Prioriteit | Bron | Voorbeeld |
|---|---|---|
| 1 | **Expliciete `seo_meta.robots`** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | **Een `getSEORobots(): ?string`-hook** op het model | retourneer `'noindex, nofollow'` of `null` om naar de volgende bron door te gaan |
| 3 | **Een `is_indexable`-attribuut** (kolom of accessor) | falsy ⇒ `noindex, nofollow`; truthy ⇒ `index, follow` |

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

### Wat daadwerkelijk wordt gerenderd {#what-actually-renders}

De uiteindelijke directive wordt door het **uitvoerbeleid** gefilterd voordat ze de
`<head>` bereikt: de `<meta name="robots">`-tag wordt **alleen gegenereerd wanneer de directive
afwijkt van `default_robots`** (standaard `index,follow`). Dus:

- een **indexeerbare** pagina (waarvoor `index, follow` geldt) genereert **geen robots-tag** —
  de afwezigheid daarvan leest een crawler juist als index,follow;
- een **niet-indexeerbare** pagina genereert `<meta name="robots" content="noindex, nofollow">`;
- elke afwijkende directive (`noindex`, `max-snippet:-1`, `unavailable_after`, …)
  wordt **letterlijk** gegenereerd, met behoud van de ingevoerde spaties.

Stel `seo.robots.emit_default = true` in om de tag altijd te renderen. Alle details staan bij het
[renderingbeleid voor robots](/nl/reference/configuration#robots-rendering-policy).

## Regels die na de waardebepaling worden toegepast {#policies-applied-after-resolution}

Deze gelden ongeacht welke laag de waarde heeft geleverd:

- **Titelachtervoegsel** — `title_suffix` wordt toegevoegd, tenzij de uiteindelijke titel
  er al op eindigt. Als een template voor een routestandaard je merk al bevat,
  laat de template dan eindigen met het achtervoegsel om "Brand — X | Brand" te voorkomen.
- **Querystring uit de canonieke URL verwijderen** — bij *afgeleide* canonieke URL's (model-URL / huidige URL)
  wordt de querystring verwijderd, behalve de sleutels in
  [`canonical.query_whitelist`](/nl/reference/configuration#canonical-urls) (bijvoorbeeld
  `page` voor gepagineerde archieven), die behouden blijven; *expliciet ingestelde* canonieke URL's
  blijven letterlijk behouden.
- **Absolute sociale afbeeldingen** — `og:image` en `twitter:image` worden altijd
  als absolute URL's gegenereerd (de Open Graph-specificatie vereist dit), ook wanneer de
  opgeslagen waarde een relatief pad is.

## Bekijken welke laag voorrang kreeg {#inspecting-which-layer-won}

Het [Filament-pakket](/nl/guide/filament) toont dit per veld (Handmatig /
Uit de content / Standaard van het modeltype / Globale standaard / Siteconfiguratie /
Afgeleid van de URL). In code maakt `SEOWarningEvaluator` hetzelfde onderscheid tussen
handmatige waarden en terugvalwaarden beschikbaar om je eigen beheerindicatoren te bouwen.

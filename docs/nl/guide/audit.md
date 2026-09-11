---
description: "Voer php artisan seo:audit uit voor een tabel met geslaagde controles, waarschuwingen en fouten per pagina. Rechtstreeks in het proces, zonder queue, licentie of netwerk. Gratis in Core."
---

# Gratis SEO-audit (`seo:audit`) {#free-seo-audit-seo-audit}

`php artisan seo:audit` beantwoordt gratis, met één commando, één vraag: **wat is er op dit
moment mis met mijn SEO?** Het doorloopt je `HasSEO`-modellen rechtstreeks in
het proces, **zonder queue, licentie of netwerk**, en toont per pagina een tabel
met **pass / warn / fail (geslaagd / waarschuwing / fout)** en een samenvatting.

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

## Wat wordt gecontroleerd {#what-it-checks}

De audit voert alleen controles van de uitvoeringsklasse **metadata** uit:
controles waarvoor het model en de [resolver](/nl/concepts/resolver-precedence)
voldoende zijn, zonder de pagina op te halen.

| Controle | Codes |
|---|---|
| Titel/beschrijving aanwezig (rekening houdend met terugvalwaarden) | `missing_title`, `missing_description` |
| OG-afbeelding aanwezig (rekening houdend met terugvalwaarden) | `missing_og_image` |
| Lengte van titel/beschrijving | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Dubbele titel/beschrijving binnen de site | `duplicate_title`, `duplicate_description` |
| Conflicterende robots-instructies en verdachte noindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Canonieke URL: notatie, ander domein, gedeeld of onbeveiligd | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Geschiktheid voor antwoordmachines (AEO): gestructureerde artikelgegevens | `aeo_missing_author`, `aeo_article_missing_date` |
| Focuszoekwoord ingesteld (optioneel) | `missing_focus_keyword` |
| hreflang-alternatieven (Core-register, wanneer een pagina die heeft) | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

De meeste codes komen ook in de Pro-scan voor, maar de registers zijn gescheiden.
Core gebruikt bijvoorbeeld `hreflang_missing_self`, terwijl Pro `hreflang_missing_self_reference` gebruikt.
`hreflang_duplicate_code` is in Core een informatieve melding en in Pro een waarschuwing.
Ga bij een gedeelde naam dus niet uit van dezelfde dekking of ernst.
`blank_explicit_override` hoort bij het Core-register. De lengtecontrole gebruikt dezelfde
[limieten per schrift](/nl/guide/multilingual#title-and-description-budgets-per-script)
als de editor: 60/160 tekens voor Latijnse tekst en ongeveer 30/80 voor CJK,
geteld in grafemen en gemeten over de **uiteindelijke** waarde, inclusief
achtervoegsel. De audit spreekt de tekentellers in de [Filament-editor](/nl/guide/filament)
dus nooit tegen. De hreflang-controles gebruiken de lijst nadat het beleid
van `seo.hreflang` is toegepast: dezelfde lijst die de tags en de sitemap weergeven.
Wederkerigheid controleren vereist een crawl en blijft een Pro-functie.

De controles voor **geschiktheid voor antwoordmachines (AEO)** gaan alleen af
wanneer een pagina artikelachtige JSON-LD declareert (`Article`, `BlogPosting`,
`NewsArticle`, …) waarin een signaal ontbreekt dat het artikel begrijpelijk maakt
als gestructureerde gegevens: een `author`-entiteit voor expliciet auteurschap
en herkomst, of `datePublished` / `dateModified` voor een expliciete tijdlijn.
Een pagina zonder artikel krijgt nooit zo'n melding. De audit blijft dus stil
waar AEO niet van toepassing is. Deze controles zijn adviserend, op het niveau
van een informatieve melding, en tellen niet mee in de Pro-score van 0–100.

## Wat *niet* wordt gecontroleerd: de grenzen {#what-it-does-not-check-—-the-capability-boundary}

Een gratis audit binnen het applicatieproces kan nooit gelijk zijn aan de
volledige Pro-scan. Het commando vermeldt dit bij elke uitvoering. Het voert
de volgende controles **niet** uit:

- **Controles op gerenderde HTML** — `missing_h1`, `multiple_h1`, `missing_image_alt`,
  `thin_content`, `mixed_content`. Hiervoor is de aangeboden HTML van de pagina nodig.
- **Netwerkcontroles op live canonieke URL's** — `canonical_target_broken` / `_redirect` /
  `_noindex`. Hiervoor is een uitgaande aanvraag met veiligheidscontroles nodig.
- **De numerieke score van 0–100.** De score is een Pro-functie en wordt met een
  beoordelingsmodel met versienummer in het scanresultaat opgeslagen. Zie [SEO-score](/nl/pro/scoring).

Deze mogelijkheden zitten in de **Pro-scan**. Bekijk het volledige [register van bevindingen](/nl/pro/scan-issues).

## Kiezen wat je wilt auditen {#choosing-what-to-audit}

Standaard controleert het commando de modellen onder `seo.audit.models`,
met `seo.sitemap.models` als terugvaloptie:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Of geef de modellen expliciet mee:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Opties {#options}

| Optie | Effect |
|---|---|
| `--model=` | Een `HasSEO`-modelklasse om te auditen (herhaalbaar). Heeft voorrang op de configuratie. |
| `--locale=` | Bepaal SEO-gegevens in deze locale (standaard de applicatielocale). |
| `--limit=` | Maximumaantal records per model (`0` = alle). |
| `--issues-only` | Toon alleen pagina's met minstens één bevinding. |
| `--strict` | Sluit af met een niet-nulstatus als er een bevinding is; bedoeld voor CI. |
| `--json` | Geef machinaal leesbare JSON weer (pagina's, samenvatting, dekking) in plaats van de tabel. |

### Controle in CI {#ci-gate}

`--strict` maakt van de audit een buildcontrole:

```bash
php artisan seo:audit --strict
```

Het commando sluit af met `1` als een pagina een waarschuwing of fout
heeft, en met `0` als alle gecontroleerde pagina's slagen.

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

## Focuszoekwoorden {#focus-keywords}

De melding `missing_focus_keyword` staat **standaard uit**. Ze verschijnt pas wanneer je
de werkwijze met focuszoekwoorden inschakelt:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

De Pro-scan leest **dezelfde** instelling. De audit, de scan en de herinnering in
de Pro-editor komen dus altijd overeen. Stel de zoekwoorden van een pagina in via
het [veld voor focuszoekwoorden in Filament](/nl/guide/filament) of
`$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Wanneer een waarde afwijkt van je verwachting: `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` vertelt *wat er mis is*; [`seo:explain`](/nl/guide/explain) vertelt
*waarom een veld die waarde heeft gekregen*. Je ziet welke laag (configuratie,
standaardwaarde, berekend of expliciet) elke waarde instelde, wat die overschreef
en welke nabewerking daarna iets veranderde: titelachtervoegsel, het opschonen
van de canonieke URL of indexeringsbeveiliging. Gebruik het wanneer een
auditbevinding of gerenderde tag je verrast:

```bash
php artisan seo:explain "App\Models\Post" 42
```


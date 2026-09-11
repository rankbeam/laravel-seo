---
description: "De SEO-score van 0–100 in de Pro-scan is controleerbaar en deterministisch. Elk afgetrokken punt hoort bij precies één scanbevinding; dezelfde bevindingen geven altijd dezelfde score."
---

# De SEO-score: transparant, met versies, onderdeel van Pro {#the-seo-score-—-transparent-versioned-pro-owned}

De Pro-scan geeft elke pagina een **SEO-score van 0–100**: het cijfer dat
gebruikers die van Rank Math of Yoast overstappen zoeken. Dit is geen
ondoorzichtig oordeel, maar **volledig controleerbaar**. Elk afgetrokken
punt is terug te voeren op precies één [scanbevinding](/nl/pro/scan-issues),
en dezelfde set bevindingen levert altijd hetzelfde cijfer op.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip Eén cijfer, één verantwoordelijke laag
De numerieke score is een **Pro-functie**. Ze staat in het Pro-record
`seo_scan_results`, nooit in `seo_meta` van Core. De oude kolom `seo_score`
is in Core 3 verwijderd. Het gratis Core-commando
[`seo:audit`](/nl/guide/audit) rapporteert per pagina **pass / warn / fail (geslaagd / waarschuwing / fout)**,
**zonder cijfer**. De score is een betaalde toevoeging.
:::

## Het beoordelingsmodel {#the-rubric}

De score wordt berekend met een **gepubliceerd beoordelingsmodel met
versienummer**, `Rankbeam\Seo\Pro\Scanning\ScoreRubric`. Twee zaken bepalen het: een expliciete
**lijst van meetellende bevindingscodes** en een vaste **aftrek per ernstniveau**.

| Ernst | Aftrek | Betekenis |
|---|---|---|
| `critical` | **−40** | Bevinding met grote impact binnen dit beoordelingsmodel. |
| `warning` | **−15** | Bevinding om op korte termijn te onderzoeken. |
| `notice` | **−5** | Een wenselijke verbetering. |

De ernst van elke code komt rechtstreeks uit het
[register van bevindingen](/nl/pro/scan-issues), de enige gezaghebbende bron.
Het beoordelingsmodel leidt die niet opnieuw af. Elke code heeft precies
één ernstniveau, zodat de score deterministisch blijft.

### Wat meetelt in de score {#what-the-score-counts}

Dit zijn deterministische controles die Rankbeam voor zijn
productbeoordeling heeft gekozen. Ze omvatten ook vuistregels die
redactionele interpretatie vragen. Een kritieke bevinding kost 40 punten,
een waarschuwing 15 en een informatieve melding 5. De score voorspelt
geen prestaties in zoekmachines.

| Code | Ernst | Aftrek |
|---|---|---|
| `missing_title` | kritiek | −40 |
| `missing_description` | waarschuwing | −15 |
| `missing_og_image` | informatief | −5 |
| `duplicate_title` | waarschuwing | −15 |
| `duplicate_description` | waarschuwing | −15 |
| `title_too_long` | waarschuwing | −15 |
| `title_too_short` | informatief | −5 |
| `description_too_long` | waarschuwing | −15 |
| `description_too_short` | informatief | −5 |
| `robots_conflict_indexing` | kritiek | −40 |
| `robots_conflict_following` | waarschuwing | −15 |
| `noindex_warning` | waarschuwing | −15 |
| `invalid_canonical` | kritiek | −40 |
| `cross_domain_canonical` | waarschuwing | −15 |
| `shared_canonical` | informatief | −5 |
| `insecure_canonical` | waarschuwing | −15 |
| `http_error` | kritiek | −40 |
| `empty_response` | kritiek | −40 |
| `missing_canonical` | informatief | −5 |
| `missing_h1` | informatief | −5 |
| `missing_image_alt` | waarschuwing | −15 |
| `thin_content` | informatief | −5 |
| `mixed_content` | waarschuwing | −15 |
| `canonical_target_broken` | kritiek | −40 |
| `canonical_target_redirect` | waarschuwing | −15 |
| `canonical_target_noindex` | waarschuwing | −15 |

Metadatacodes worden tijdens een modelscan gedetecteerd; codes voor
gerenderde inhoud en netwerkcontroles alleen tijdens een URL-scan.
Zie de [uitvoeringsklassen](/nl/pro/scan-issues#execution-classes).
De score van een **modeldoel** weerspiegelt dus de metadatacontroles en
die van een **URL-doel** de gerenderde pagina. Een modelscore van 100
betekent dat er geen metadatadefecten zijn gevonden, niet dat de
gerenderde pagina perfect is. Scan daarvoor de URL.

### Wat bewust NIET meetelt {#what-the-score-deliberately-does-not-count}

Deze registercodes worden bewust uitgesloten. De uitsluitingen horen
bij het contract: een test controleert of elke registercode meetelt of
hier wordt vermeld.

| Code | Waarom uitgesloten |
|---|---|
| `missing_focus_keyword` | **Adviserend.** Afhankelijk van de optionele werkwijze `seo.keywords.enabled`. Een pagina mag niet lager scoren omdat ze geen focuszoekwoorden gebruikt, en de score mag niet van een configuratieoptie afhangen. |
| `noindex_page` | **Informatief.** `noindex` is een bewuste toestand, geen defect in metadatakwaliteit. De vuistregel voor noindex met een zelfverwijzende canonieke URL telt in plaats daarvan via `noindex_warning` mee. |
| `multiple_h1` | **Informatief.** Google staat meerdere H1's toe; geen aftrek voor meerdere H1's. |
| `blocked_url` | **Geen bewijs beschikbaar.** SsrfGuard weigerde het ophalen, dus de pagina is niet gecontroleerd. Dit is geen defect van de pagina. |
| `canonical_target_blocked` | **Geen bewijs beschikbaar.** Het canonieke doel kon niet worden geverifieerd. Dit is geen defect van de pagina. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Voorlopig adviserend.** Deze Pro-hreflang-codes verschijnen in de scan. De gratis audit heeft eigen hreflang-codes, maar hreflang telt nog niet mee in de score. Daarvoor moet `VERSION` worden verhoogd. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **Adviserend.** Taalcontroles vallen buiten dit beoordelingsmodel. |
| `hreflang_not_reciprocal` | **Adviserend.** Optionele controle op wederkerigheid, zonder scoreaftrek. |
| `hreflang_target_unverified` | **Geen bewijs beschikbaar.** Wederkerigheid kon niet worden geverifieerd. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Adviserend.** Signalen voor geschiktheid voor antwoordmachines (AEO). Ze melden in de scan en gratis audit dat een artikel geen auteursentiteit of publicatiedatum heeft, maar veranderen de score niet. Dat zou een verhoging van `VERSION` vereisen. |

Zoekwoorddichtheid, krachtige woorden en de overige
[on-page checklist](/nl/pro/on-page-checklist) tellen helemaal niet mee
in de score. Het zijn adviserende controles in een afzonderlijke
pass/warn/fail-lijst, geen registercodes.

## Versies: historische scores veranderen nooit stilzwijgend {#versioning-—-historical-scores-never-silently-change}

Elke opgeslagen score krijgt de `ScoreRubric::VERSION` die haar heeft berekend
(`rubric_version`). Dat heeft twee gevolgen:

- Een **nieuwe** bevindingscode telt **niet mee** totdat die bewust aan
  de lijst is toegevoegd. Een nieuwe controle kan een opgeslagen score
  dus nooit achteraf veranderen. Een wijziging aan de lijst of gewichten
  is zelf een wijziging van het beoordelingsmodel en verhoogt de versie.
- De score wordt **opgeslagen, niet opnieuw berekend bij het lezen**.
  Het cijfer van vorige week blijft vandaag hetzelfde, met het
  bijbehorende beoordelingsmodel als verklaring.

## Waar de score wordt opgeslagen {#where-it-s-stored}

Elke scan voegt per doel één rij toe aan `seo_scan_results` of werkt die bij:

| Kolom | Inhoud |
|---|---|
| `scannable_type` / `scannable_id` | Het beoordeelde model; null voor URL-doelen. |
| `url` | De beoordeelde URL. |
| `score` | Het cijfer van 0–100. |
| `rubric_version` | Het beoordelingsmodel dat de score berekende. |
| `penalty_total` | Ruwe som van de aftrek **vóór** toepassing van de ondergrens 0. |
| `scored_issues` | Hoeveel bevindingen het cijfer beïnvloedden. |
| `breakdown` | `[{code, severity, penalty}, …]`: de volledige berekening. |
| `keywords_enabled` | De toestand van `seo.keywords.enabled` tijdens de scan, voor transparantie vastgelegd. De score hangt er niet van af. |
| `scan_run_id` | De uitvoering die de score berekende. Wordt null, niet verwijderd, als die uitvoering wordt opgeruimd: scores zijn de huidige toestand, geen uitvoeringsgeschiedenis. |
| `scored_at` | Wanneer de score is berekend. |

## De score lezen {#reading-the-score}

**Headless**: de laatste score voor een model:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` toont de **gemiddelde sitescore** in zijn samenvatting.
Het Filament-dashboard toont die als hoofdstatistiek “Gem. SEO-score”,
met een kleur op basis van de beoordeling.

### Beoordelingsklassen {#grade-bands}

Een letter voor de presentatie, afgeleid van het cijfer. Het cijfer zelf
is het contract:

| Score | Beoordeling |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Het publicatiesignaal (`noindex_warning`) {#the-shipping-signal-noindex-warning}

`noindex_warning` gaat af wanneer een pagina `noindex` combineert met een
**zelfverwijzende canonieke URL**. Rankbeam behandelt dit als een signaal
om vóór publicatie te controleren. Een zelfverwijzende canonieke URL
bewijst **niet** dat indexering de bedoeling is; de combinatie kan bewust
zijn gekozen. Een canonieke URL op een ander domein activeert deze
vuistregel niet. De bevinding bevat `context.shipping_signal`, bijvoorbeeld
`self_canonical`, en de vergeleken `canonical` en `page_url`.

Beide scanners passen de controle toe. De modelscan (`PageScanner`)
vergelijkt de opgeslagen canonieke URL met de model-URL. De scan van
de gerenderde URL (`UrlScanner`) verhoogt een `noindex`-pagina
met een zelfverwijzende canonieke URL van de informatieve `noindex_page`
naar de meetellende `noindex_warning`. Daarom is `noindex_page` zelf
uitgesloten: op beide routes behandelt `noindex_warning` het mogelijke
conflict. Controleer de werkelijke bedoeling van de pagina voordat je
de indexeringsinstructie wijzigt.

## Configuratie {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

De lijst en gewichten zijn **niet configureerbaar**. Voor een bepaalde
`rubric_version` moet de score in elke installatie deterministisch zijn.
Een andere berekening vereist daarom een wijziging van het
beoordelingsmodel in de code, geen instelling.

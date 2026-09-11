---
description: "Het register met stabiele probleemcodes achter elke melding van de Pro-scan. Elke code heeft een vaste ernst en een vast veld, zodat dashboards en exports codes kunnen gebruiken in plaats van meldingsteksten."
---

# Scanproblemen: het register met probleemcodes {#scan-issues-—-the-issue-code-registry}

Elk probleem dat de Pro-scan meldt, heeft een **stabiele probleemcode** uit één
register: `Rankbeam\Seo\Pro\Scanning\IssueRegistry`. Scanners bedenken zelf geen codes; ze maken elke melding
via `IssueRegistry::make()`, dat de ernst en het veld uit het register overneemt en
**ongedefinieerde codes weigert**. De catalogus hieronder vormt daarmee een
vast contract voor dashboards, exports en de [Pro-score](/nl/pro/scoring).
Deze gebruiken codes in plaats van meldingsteksten te ontleden. De gratis
[`seo:audit`](/nl/guide/audit) gebruikt een eigen metadataregister in de core,
met een beperkter bereik en enkele andere hreflang-codes.

Elke code bevat:

- **id** — de stabiele tekenreeks die wordt opgeslagen als `seo_scan_issues.issue_type`.
- **severity** — `critical`, `warning` of `notice`; **vast per code**.
  Als verschillende ernstniveaus nodig zijn, splitsen we de code.
- **field** — het betreffende `seo_meta`-veld, of _page_ voor bevindingen op paginaniveau.
- **execution class** — wat nodig is om het probleem vast te stellen; zie hieronder.
- **evidence** — de sleutels in de `context`-array van het probleem.

## Uitvoeringsklassen {#execution-classes}

Elke controle behoort tot precies één van drie klassen, op basis van wat hij nodig heeft:

| Klasse | Benodigd | Wie de controle kan uitvoeren |
|---|---|---|
| **metadata** | Het model en de core-resolver; geen pagina ophalen | De modelscan (`PageScanner`); de gratis [`seo:audit`](/nl/guide/audit) bevat een deel van de metadatacontroles |
| **rendered** | De HTML die de pagina serveert, via een kernelverzoek binnen het proces of extern ophalen | De URL-scan (`UrlScanner`) |
| **network** | Een **uitgaand** verzoek om een _afzonderlijk_ doel te controleren, zoals een canonical die naar een andere pagina verwijst | De URL-scan, **altijd via de `SsrfGuard`** |

Daarom kan een gratis audit binnen het proces nooit hetzelfde dekken als de
volledige Pro-scan: alleen **metadata**-codes zijn te berekenen zonder een pagina
te renderen. Alleen de Pro-pipeline haalt gerenderde HTML op en controleert
canonical-doelen via het netwerk. Filter het register op klasse met `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`.

## Metadatacodes {#metadata-codes}

`PageScanner` stelt deze vast op basis van het model en de resolver.
De gerenderde URL-scan meldt ook `missing_title`, `missing_description` en de lengtecodes
door de geserveerde `<head>` te meten: dezelfde codes, dezelfde betekenis.

| Code | Ernst | Veld | Bewijs | Betekenis |
|---|---|---|---|---|
| `missing_title` | critical | title | — | Geen titel en geen berekenbare terugvalwaarde. |
| `missing_description` | warning | description | — | Geen metabeschrijving en geen berekenbare terugvalwaarde. |
| `missing_og_image` | notice | og_image | — | Geen Open Graph-afbeelding en geen berekenbare terugvalwaarde. |
| `missing_focus_keyword` | notice | focus_keywords | — | Geen focuszoekwoord ingesteld. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | De titel wordt ook op andere pagina's in dezelfde locale gebruikt. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | De beschrijving wordt ook op andere pagina's in dezelfde locale gebruikt. |
| `title_too_long` | warning | title | `length`, `max`, `script` | De bepaalde titel overschrijdt de aanbeveling voor het schrift: 60 voor Latijns, circa 30 voor CJK. |
| `title_too_short` | notice | title | `length`, `min`, `script` | De bepaalde titel blijft onder de ondergrens voor het schrift: 30 voor Latijns, circa 15 voor CJK. |
| `description_too_long` | warning | description | `length`, `max`, `script` | De bepaalde beschrijving overschrijdt de aanbeveling voor het schrift: 160 / circa 80. |
| `description_too_short` | notice | description | `length`, `min`, `script` | De bepaalde beschrijving blijft onder de ondergrens voor het schrift: 70 / circa 35. |
| `robots_conflict_indexing` | critical | robots | `robots` | De robotsinstructie bevat zowel `index` als `noindex`. |
| `robots_conflict_following` | warning | robots | `robots` | De robotsinstructie bevat zowel `follow` als `nofollow`. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | Een pagina met een canonical naar zichzelf is `noindex`. Een aanwijzing om te beoordelen, geen bewijs dat de pagina geïndexeerd moet worden. Zowel de modelscan als de gerenderde URL-scan meldt dit. |
| `invalid_canonical` | critical | canonical | `canonical` | De canonical-waarde is geen geldige URL. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | De canonical verwijst naar een andere host dan de pagina. |
| `shared_canonical` | notice | canonical | `canonical` | Meerdere pagina's geven dezelfde canonical op. |
| `insecure_canonical` | warning | canonical | `canonical` | Een `http://`-canonical op een `https`-site. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | Een hreflang-alternatief gebruikt een waarde die noch `x-default` noch een geldige BCP-47-taalcode is. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | Er zijn alternatieven opgegeven, maar geen ervan verwijst naar de eigen locale van de pagina: de zelfverwijzende hreflang ontbreekt. |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | Dezelfde hreflang-code verwijst naar meer dan één URL, waardoor de groep dubbelzinnig is. |
| `hreflang_missing_x_default` | notice | alternates | `languages` | Een meertalige hreflang-groep heeft geen `x-default`-terugvaloptie. |
| `aeo_missing_author` | notice | schema | — | Een artikel in de gestructureerde gegevens van de pagina heeft geen auteursentiteit. Auteurschap of herkomst is niet expliciet vastgelegd in het schema. |
| `aeo_article_missing_date` | notice | schema | — | Een artikel in de gestructureerde gegevens van de pagina heeft geen publicatie- of wijzigingsdatum. De tijdlijn is niet expliciet vastgelegd in het schema. |

De lengtegrenzen komen uit het [lengtebeleid per schrift](/nl/guide/multilingual#title-and-description-budgets-per-script)
van de core, sinds Pro 2.33: 60/160 voor Latijnse tekst, circa 30/80 voor CJK.
De telling gebruikt grafemen, zodat de scan niet afwijkt van de tekentellers in
de editor. De ondergrenzen — titel 30 en beschrijving 70 voor Latijns, ongeveer
de helft voor CJK — zijn de grenzen waaronder de scan onvoldoende optimalisatie
signaleert. De contextsleutel `script` noemt de gebruikte categorie.
De lengte wordt gemeten op de **bepaalde** titel of beschrijving: de waarde die
daadwerkelijk wordt gerenderd, inclusief terugvalwaarden en titelachtervoegsel.

De `hreflang_*`-codes valideren de opgegeven hreflang-alternatieven van een
pagina, gelezen uit `alternates` van de resolver: ongeldige of dubbele codes,
een ontbrekende zelfverwijzing en een ontbrekende `x-default` in een
meertalige groep. Ze worden alleen uitgevoerd als de pagina alternatieven
opgeeft. Deze metadatacontroles controleren geen **wederzijdse verwijzingen**
tussen pagina's. De optionele netwerkcontrole hieronder haalt daarvoor de andere pagina op.

De `aeo_*`-codes zijn signalen voor **geschiktheid voor antwoordmachines
(AEO)**: is de artikelinhoud van de pagina herkenbaar in de gestructureerde
gegevens? Ze lezen de bepaalde JSON-LD-graaf en worden **alleen** gemeld als
die gestructureerde gegevens van een artikeltype bevat, zoals `Article`,
`BlogPosting` of `NewsArticle`, zonder `author`-entiteit voor expliciet
auteurschap of herkomst, of zonder `datePublished` / `dateModified` voor een
expliciete tijdlijn. Een pagina zonder artikel krijgt deze meldingen nooit.
Ze worden bestuurd door `seo-pro.scan.checks.aeo`, standaard aan, en komen overeen met
de gratis [`seo:audit`](/nl/guide/audit).

::: tip `missing_focus_keyword` heeft een inschakelvoorwaarde
De melding over een ontbrekend focuszoekwoord verschijnt alleen als de
focuszoekwoordworkflow in de **core** is ingeschakeld: `seo.keywords.enabled`,
standaard `false`. Als die uitstaat, meldt de scan geen ontbrekend
focuszoekwoord. Het gratis [`seo:audit`](/nl/guide/audit)-commando en de
Filament-editor gebruiken **dezelfde** core-instelling. Scan, audit en
editorherinnering komen daardoor altijd overeen: er is maar één schakelaar.
:::

## Codes uit gerenderde HTML {#rendered-codes}

`UrlScanner` stelt deze vast uit de geserveerde HTML. Voor doelen op dezelfde
host gebeurt dat met een kernelverzoek binnen het proces, zonder uitgaand
verkeer; externe doelen worden via een beveiligd verzoek opgehaald.

| Code | Ernst | Veld | Bewijs | Betekenis |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | De URL gaf een 4xx/5xx-status terug. |
| `empty_response` | critical | page | — | De URL gaf een leeg antwoord terug. |
| `missing_canonical` | notice | canonical | — | Geen `<link rel="canonical">` in de gerenderde head. |
| `noindex_page` | notice | robots | `robots` | De gerenderde pagina is `noindex`, ter informatie. Een `noindex`-pagina die ook **een canonical naar zichzelf** heeft, krijgt in plaats daarvan `noindex_warning`, dat meetelt in de score. |
| `missing_h1` | notice | page | — | Geen `<h1>`-kop. |
| `multiple_h1` | notice | page | `count` | Meer dan één `<h1>`, ter informatie. |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | Inhoudelijke afbeeldingen missen een `alt`-attribuut. Expliciet `alt=""` geldt als decoratief en wordt niet gemeld. |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter` | De hoofdtekst blijft onder het ingestelde aantal woorden. De tokenizer van de checklist telt woorden via witruimte voor schriften met spaties, en via ICU-woordenboeksegmentatie (`segmenter: intl`, vereist ext-intl) voor Chinees, Japans en Thais. Een Japans artikel van 400 woorden telt dus niet als één 'woord'. |
| `mixed_content` | warning | page | `count`, `sample` | `http://`-subresources op een `https`-pagina. |
| `html_lang_missing` | notice | page | — | Geen `<html lang>`, of een lege waarde. Ondersteunende technologie kan daardoor een ongeschikte stem kiezen. |
| `html_lang_invalid` | notice | page | `declared` | De waarde van `lang` is geen BCP-47-tag: `english`, `en_US` met een underscore, `jp`. |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script` | Het schrift van de zichtbare hoofdtekst past niet bij de opgegeven taal, bijvoorbeeld `lang="en"` op een Japanse pagina of `lang="ru"` bij Latijnse tekst. Alleen controle op schrift: een verkeerde taal binnen hetzelfde Latijnse schrift zou een gok zijn, en de scan gokt niet. Vereist minstens 40 letters hoofdtekst. |

## Netwerkcodes {#network-codes}

`UrlScanner` stelt deze alleen vast als de bijbehorende optionele instelling
aanstaat: `seo-pro.scan.url_checks.check_canonical_target` voor het canonical-doel en `check_hreflang_reciprocity` voor
hreflang-alternatieven. Elk doel wordt **via de `SsrfGuard`** opgehaald,
met toegestane URL-protocollen en hosts, weigering van privé-IP-adressen en
limieten voor redirects, tijd en omvang. Redirects worden **niet** gevolgd,
zodat een doorverwijzende canonical zichtbaar blijft. Een canonical of
alternatief dat naar de pagina zelf verwijst, wordt overgeslagen: die pagina
is zojuist al opgehaald.

| Code | Ernst | Veld | Bewijs | Betekenis |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | Een doel is door de `SsrfGuard` geweigerd voordat een HTTP-verzoek plaatsvond. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | De canonical verwijst naar een pagina die een HTTP-fout teruggeeft. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | De canonical verwijst naar een pagina die doorstuurt; gebruik de uiteindelijke URL. |
| `canonical_target_noindex` | warning | canonical | `canonical` | De canonical verwijst naar een pagina die zelf `noindex` is. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | Het canonical-doel kon niet worden geverifieerd: geweigerd door de beveiliging of niet op te lossen. |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status` | Een opgegeven alternatief verwijst niet terug naar de pagina. Het hreflang-paar kan worden genegeerd; dit maakt de vertaling op zichzelf niet onindexeerbaar. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason` | Het alternatief kon niet worden opgehaald door weigering, foutstatus, redirect of overschrijding van de omvanglimiet. Wederkerigheid is dus niet gecontroleerd. Ontbrekend bewijs, geen vastgesteld gebrek. |

De controle op wederkerigheid haalt maximaal `hreflang_max_alternates` doelen per pagina
op, standaard 10, inclusief `x-default`. Dubbele doelen en de pagina zelf
worden overgeslagen. De `hreflang_*`-metadatacodes op modelniveau valideren
de *opgegeven* lijst; deze crawl is de controle die de andere pagina nodig heeft.

Elk netwerkpad hier gebruikt de gedeelde `SsrfGuard`. Zie
[SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md)
voor het dreigingsmodel en de toelichting op het resterende TOCTOU-risico.

## Hoe codes de score bepalen {#how-codes-feed-the-score}

De [Pro SEO-score](/nl/pro/scoring) is `100 −` een vaste aftrek per
meetellend probleem, gewogen op basis van de ernst hierboven. De meeste codes
tellen mee. Enkele zijn bewust uitgesloten: `missing_focus_keyword` als advies,
`noindex_page` en `multiple_h1` ter informatie,
`blocked_url` / `canonical_target_blocked` / `hreflang_target_unverified` omdat 'niet kunnen
controleren' geen gebrek is, en `hreflang_*`, `html_lang_*` en
`aeo_*` als adviessignalen die voorlopig buiten de score blijven.
De [scorepagina](/nl/pro/scoring) bevat de volledige lijst van meetellende
codes en de aftrek per code.

## Levenscyclus van een probleem {#issue-lifecycle}

Een probleem is meer dan een rij die alleen bestaat zolang het gebrek aanwezig
is. Het heeft een levenscyclus: een scan **werkt bestaande problemen bij** in
plaats van ze te wissen en opnieuw aan te maken. Elk probleem heeft een stabiele
identiteit: het doel — `scannable_type` + `scannable_id` voor een model, of
`url` voor een route- of sitemapdoel — plus `issue_type`.
Elke code wordt per doel hoogstens eenmaal per scan gemeld. Codes met meerdere
gevallen, zoals `missing_image_alt`, `mixed_content` en `hreflang_*`, bundelen
die in één rij met `count` / `sample`. De identiteit is daardoor uniek.

Bij elke scan geldt voor elk doel:

- Een bevinding **zonder bestaande rij** wordt aangemaakt als `open`
  en krijgt `detected_at`.
- Een bevinding die **overeenkomt met een bestaande open rij** krijgt bijgewerkt
  bewijs en behoudt zijn oorspronkelijke `detected_at`. De eerste waarneming
  blijft zo bewaard en wordt niet bij elke scan opnieuw ingesteld.
- Een open probleem dat een afgeronde controle **niet meer vindt**, wordt
  **`fixed`**, met `resolved_at` vastgelegd. De rij wordt **bewaard**,
  zodat een echte oplossing geregistreerd blijft.
- Een **`fixed`** probleem dat **terugkomt**, wordt in dezelfde rij
  **heropend**: een terugval, met een nieuwe `detected_at`.
- Een probleem dat een gebruiker in het dashboard op **`ignored`** heeft
  gezet, blijft ongemoeid.

| Status | Betekenis | Ingesteld door |
|---|---|---|
| `open` | Momenteel aanwezig. | De scan, nieuw of opnieuw gevonden |
| `fixed` | Was aanwezig, wordt niet meer gevonden. | Automatisch door de volgende scan die het niet terugvindt |
| `ignored` | Door een gebruiker genegeerd; telt niet mee bij openstaande problemen of in de score. | De actie Negeren in het dashboard |

Doordat oplossingen nu worden vastgelegd, kan het [rapport in je eigen
huisstijl](/nl/pro/reports) **werkelijke aantallen opgeloste en nieuwe problemen**
over een periode tonen, in plaats van verschillen tussen rapportmomentopnamen.
Alle onderdelen die openstaande problemen tellen — het dashboard, het
[`seo-pro:scan-status`](/nl/pro/headless)-commando en de [score](/nl/pro/scoring) —
filteren op `open`. Bewaarde `fixed`-rijen verhogen die
aantallen dus nooit. Opgeloste rijen worden toegeschreven aan de uitvoering
die ze oploste en vervallen volgens de normale [bewaartermijn](/nl/pro/production)
voor scans.

## Configuratie {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

De maximale antwoordomvang voor beveiligde ophaalverzoeken is `seo-pro.http.max_response_bytes`,
standaard 2 MB. De scan binnen het proces voor dezelfde host heeft deze limiet niet.

## Compatibiliteit bij hernoemde probleemcodes {#compatibility-note-issue-code-rename}

De eerdere code `robots_conflict` had twee ernstniveaus en is gesplitst, zodat
elke code precies één ernstniveau heeft:

| Oude code | Nieuwe code | Ernst |
|---|---|---|
| `robots_conflict` (index + noindex) | `robots_conflict_indexing` | critical |
| `robots_conflict` (follow + nofollow) | `robots_conflict_following` | warning |

Als je `robots_conflict` hebt opgeslagen of erop filtert, stap dan over op de twee nieuwe codes.

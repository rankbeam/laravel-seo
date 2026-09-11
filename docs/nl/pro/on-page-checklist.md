---
description: "Een paginachecklist met goed, waarschuwing en fout op basis van je focuszoekwoord. Controleer titel, URL, openingsalinea, metadata, lengte, afbeeldingen en leesbaarheid."
---

# De paginachecklist: zoekwoordgericht, met goed, waarschuwing en fout {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

De paginachecklist biedt de directe redactionele feedback die gebruikers van
RankMath of Yoast verwachten. Kies een focuszoekwoord en krijg controles met
verkeerslichtstatussen: is deze pagina daarop geoptimaliseerd? De checklist
controleert het zoekwoord in titel, URL, openingsalinea en metabeschrijving,
plus lengte, afbeeldingen, interne links en **leesbaarheid**.

Hij draait **binnen het verzoek**, zonder wachtrij of netwerk, op basis van
het model, de [resolver](/nl/concepts/resolver-precedence) en de eigen tekst
van de pagina. De uitkomst is bewust **geen getal**.

::: tip Checklist ≠ score
De checklist geeft uitsluitend **goed / waarschuwing / fout** en staat
volledig los van de [Pro SEO-score](/nl/pro/scoring). Hij deelt geen codes
met de beoordelingsmethode van die score en kan deze nooit veranderen.
Redactionele aanwijzingen blijven gescheiden van de numerieke beoordeling.
Vooral zoekwoorddichtheid en leesbaarheid zijn **adviserend**; zie hieronder.
:::

## Wat wordt gecontroleerd {#what-it-checks}

| Controle | Groep | Waarop wordt gecontroleerd |
|---|---|---|
| `keyword_in_title` | keyword | Het focuszoekwoord staat in de SEO-titel. |
| `keyword_in_description` | keyword | Het focuszoekwoord staat in de metabeschrijving. |
| `keyword_in_url` | keyword | Het focuszoekwoord staat in de URL-slug. |
| `keyword_in_first_paragraph` | keyword | Het focuszoekwoord staat in de openingsalinea. |
| `keyword_density` | keyword | **Adviserend.** De herhaling leest natuurlijk; er is geen streefwaarde, zie hieronder. |
| `title_length` | meta | De titel valt binnen dezelfde grenzen als in editor en scan: 30–60 voor Latijnse tekst en circa 15–30 voor CJK, volgens het [lengtebeleid](/nl/guide/multilingual#title-and-description-budgets-per-script) van de core, sinds Pro 2.33. |
| `description_length` | meta | De beschrijving valt binnen dezelfde grenzen: 70–160 voor Latijns, circa 35–80 voor CJK. |
| `content_length` | content | Voldoende hoofdtekst, volgens ingestelde woordenaantalgrenzen. |
| `readability` | content | **Adviserend.** Een geschat leesbaarheidsniveau via de gekozen formule voor tien talen, LIX als terugvaloptie, of een herkenbaar aangeduide heuristiek zonder score voor Japans, Chinees en Koreaans. |
| `has_image` | media | De inhoud bevat minstens één afbeelding. |
| `internal_links` | links | De inhoud verwijst naar verwante interne pagina's. |

Zonder ingesteld focuszoekwoord worden zoekwoordcontroles **overgeslagen**:
ze slagen noch mislukken. De checklist vraagt dan om een zoekwoord.
Voeg het toe via het [focuszoekwoordveld](/nl/guide/filament) of `saveSEO(['focus_keywords' => …])`.

### Zoekwoorden vergelijken {#keyword-matching}

Zoekwoord en tekst worden vergeleken na **hoofdletternormalisatie en
stamreductie**. Zo past 'espresso grinder' ook op 'espresso grinders'.
Met de analyselocale past Turks 'İstanbul' op 'istanbul', Grieks 'ΟΔΟΣ'
op 'οδος' en Duits 'Straße' op 'STRASSE', via `CaseFolder` uit de core.
Geef de analyselocale door met `SeoPro::checklistFor($post, 'it')` of `--locale=it`.

Vanaf Pro 2.36.1 gebruiken zoekwoorden, synoniemen en veldtekst vóór
stamreductie dezelfde tokenizer. Overeenkomsten vereisen opeenvolgende
**hele tokens**: `cat` past niet op `education`.
Japanse zinsdelen gebruiken dezelfde ICU-woordgrenzen als de hoofdtekst.
Apostroffen en koppeltekens scheiden tokens; `meta-tag` past dus op
`meta tag` en rechte en gekrulde apostroffen gedragen zich gelijk.
Combinatietekens blijven aan hun letters gekoppeld. Hoofdletternormalisatie
behoudt accenten; een taalspecifieke stemmer kan aanvullende reducties toepassen.

De telling kiest op elke positie het langste overeenkomende zoekwoord of
synoniem en telt dat bereik eenmaal. Dubbele synoniemen en overlappende
kortere alternatieven verhogen de dichtheid niet. Het zoekwoord
`seo` met synoniem `seo tools` komt bijvoorbeeld tweemaal
voor in `seo tools seo`. Voor woordgrenzen op basis van een woordenboek
in schriften zonder spaties blijft ICU nodig; een regex-terugvaloptie kan
die grenzen niet leveren.

Vanaf Pro 2.37 gebruikt stamreductie een **meegeleverde subset van
Snowball 3.1.1**. Er is geen extra Composer-pakket nodig en tijdens
uitvoering wordt niets gedownload. PHP 8.2 blijft ondersteund.

| Engine | Wanneer | Talen |
| --- | --- | --- |
| `snowball` | Standaard; bestaande `auto`-instellingen kiezen dezelfde meegeleverde engine | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | Expliciet `seo-pro.checklist.analysis.stemmer = builtin` | Alleen Engels, met de oudere lichte verbuigingsstemmer; andere talen gebruiken vergelijking zonder stamreductie |
| `identity` | Niet-ondersteunde taal of expliciete modus `none` | Oekraïens, Japans, Chinees, Koreaans, Thais en andere talen buiten de meegeleverde subset |

Beide kanten van een vergelijking gebruiken dezelfde engine. Stamreductie
is een algoritme voor het reduceren van achtervoegsels, geen
synoniemenwoordenboek of garantie op taalkundige gelijkwaardigheid.
Het Griekse algoritme kan bijvoorbeeld vormen met en zonder accent
gelijkstellen die bij vergelijking zonder stamreductie apart blijven.
Grenzen voor hele tokens voorkomen nog steeds dat `cat`
op `education` past.

#### Upgraden vanaf Pro 2.36 {#upgrading-from-pro-2-36}

Bestaande `auto`-configuratie gebruikt nu altijd de meegeleverde
algoritmen, ongeacht of `wamania/php-stemmer` is geïnstalleerd. Controleer
redactionele suggesties na de upgrade opnieuw: gewijzigde algoritmen kunnen
andere overeenkomsten opleveren, en Turks, Grieks, Pools en Tsjechisch
hebben nu stamreductie. De aanvullende algoritmen van de optionele wrapper
voor Catalaans, Deens, Fins, Noors, Roemeens en Zweeds vallen buiten deze
subset en gebruiken nu vergelijking zonder stamreductie.

Stel `SEO_PRO_CHECKLIST_STEMMER=builtin` in voor de eerdere terugvaloptie met alleen Engelse
stamreductie, of `none` voor hoofdletternormalisatie zonder
stamreductie in elke taal. Bouw de gecachete configuratie opnieuw op na
wijziging. Deze instellingen reproduceren niet de oude meertalige
wrapperalgoritmen. Voor precies die resultaten moet je de vorige
Pro-release behouden. Opgeslagen SEO-metadata worden niet herschreven.

De meegeleverde adapter doorstaat 600.395 vastgelegde officiële
woord-/uitvoerparen op PHP 8.2, 8.3 en 8.4. Dit toont overeenstemming met
het algoritme, geen redactionele goedkeuring door moedertaalsprekers.
Bronhashes, de aanpassing van alleen de syntaxis voor PHP 8.2 en de
oorspronkelijke licenties worden meegeleverd. Zie `THIRD-PARTY-NOTICES.md` in de brondistributie.

### Woordsegmentatie {#word-segmentation}

Woordenaantallen, zoekwoorddichtheid en leesbaarheidsstatistieken hebben
woorden nodig. Voor schriften met spaties gebruikt een regex stabiele
tokenbegrenzing op letters en cijfers. Chinees, Japans en Thais vereisen
woordenboeksegmentatie; een regex kan een hele alinea als één 'woord' zien.
Als **ext-intl** is geladen, geeft de tokenizer zulke tekstreeksen door
aan ICU's woordgrensiterator met woordenboek (`IntlBreakIterator::createWordInstance`).
Die verdeelt 東京タワーは東京のランドマークです in woorden. Ontbreekt ICU,
staat het uit of kan het niet initialiseren, dan slaat Pro de betreffende
inhoudslengte-, leesbaarheids- en zoekwoordcontroles over met een melding
over installatie of configuratie. Een onbetrouwbare telling wordt niet
als fout gebruikt. Andere controles, zoals titellengte en overeenkomsten
in schriften met spaties, blijven werken. `seo-pro.checklist.analysis.segmenter = regex` dwingt dezelfde
niet-beschikbare status af voor tekst die woordenboeksegmentatie nodig heeft.

Het blok `analysis` bevat `word_count_status`, met `available` of
`unavailable`, en `segmentation_reason`, met `null`, `missing_intl`,
`disabled` of `initialization_failed`. De onderliggende tokenizer behoudt
terugvaltokens voor compatibiliteit. Controleer deze status voordat je
die tokens als woorden interpreteert.

De scan van gerenderde pagina's geeft een melding `word_segmentation_unavailable` die niet
meetelt in de score, in plaats van een oordeel over te weinig inhoud.
Een eerder bevestigd probleem met te weinig inhoud blijft open totdat het
opnieuw kan worden gecontroleerd. Deze onvolledige scan werkt de paginascore
niet bij: een bestaande score behoudt zijn oorspronkelijke `scored_at`.
Een eerste scan krijgt geen score totdat segmentatie werkt. Installeer
PHP-`ext-intl`, schakel de `auto`-segmenter in en scan opnieuw
om deze controles te hervatten.

### Welke engines de pagina hebben geanalyseerd {#which-engines-analysed-the-page}

Elke checklist bevat een `analysis`-blok met het overheersende schrift,
de tokenizer (`intl` / `regex`), de stemmer
(`snowball` / `builtin` / `identity`) en de
leesbaarheidsmethode (`formula` / `heuristic` / `lix`).
Dit staat in `toArray()` / `--json`, als voetregel in het
Filament-venster en als laatste regel van `seo-pro:checklist`:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

De voetregel noemt de daadwerkelijk gebruikte engine, ook regexsegmentatie
bij ontbrekende ext-intl en vergelijking zonder stamreductie als die is uitgeschakeld.

### Zoekwoorddichtheid is een advies {#keyword-density-is-advisory}

De checklist definieert geen ideale zoekwoorddichtheid voor ranking.
Deze controle is **adviserend**: hij toont de telling ter informatie,
mislukt nooit en **bepaalt nooit de algemene paginastatus**. Beoordeel
of herhaling natuurlijk leest in plaats van naar een percentage toe te werken.

### Leesbaarheid is een advies {#readability-is-advisory}

De checklist schat de leesbaarheid met de methode voor de analyselocale.
De volgende formules en terugvalopties zijn momenteel geïmplementeerd:

| Locale | Formule | Bron |
| --- | --- | --- |
| Engels (`en`) | Flesch Reading Ease | Flesch 1948 |
| Italiaans (`it`) | Gulpease Index | Lucisano & Piemontese 1988 |
| Spaans (`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| Frans (`fr`) | Kandel-Moles | Kandel & Moles 1958 |
| Duits (`de`) | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| Portugees (`pt`, `pt_BR`) | Flesch aangepast voor Braziliaans Portugees | Martins et al. 1996 |
| Nederlands (`nl`) | Flesch-Douma | Douma 1960 |
| Russisch (`ru`) | Flesch-aanpassing van Oborneva | Оборнева 2006 |
| Turks (`tr`) | Ateşman | Ateşman 1997 |
| Pools (`pl`) | Pisarek, genormaliseerde index van scholingsjaren | Pisarek 1969 |
| Japans, Chinees, Koreaans (`ja`, `zh`, `ko`) | **Heuristiek, geen score**; zie hieronder | — |
| Grieks, Oekraïens, Tsjechisch (`el`, `uk`, `cs`) | LIX als terugvaloptie omdat hier geen eigen formule is geïmplementeerd; niet gekalibreerd voor deze talen | Björnsson 1968 |
| Overige talen | LIX (Läsbarhetsindex), ongekalibreerde terugvaloptie | Björnsson 1968 |

De weergegeven **score van 0–100, hoger is eenvoudiger**, is een conventie
van het pakket. Flesch- en Gulpease-resultaten worden begrensd; Wiener-,
Pisarek- en LIX-indexen worden naar deze schaal omgerekend. Gelijke scores
in verschillende talen betekenen **niet** dezelfde leesmoeilijkheid.
De formules komen uit gepubliceerd onderzoek; Rankbeams schattingen van
tokens, zinnen en lettergrepen zijn niet als volledig meetinstrument
gevalideerd. Ze voorspellen geen tekstbegrip of zoekposities.

Vanaf Pro 2.37.1 worden aangrenzende klinkers in het Turks en Russisch
als afzonderlijke lettergrepen geteld: `saat`: 2;
`поэт`: 2. Andere lettergreepschatters blijven beperkt:
klinkergroepen missen soms afzonderlijk uitgesproken klinkers en stille
klinkers. Engels heeft een kleine uitzonderingenlijst, geen
uitspraakwoordenboek. Spaans `país` en Frans `monde`
kunnen bijvoorbeeld verkeerd worden geteld. Controleer onbekende woorden
en eigennamen handmatig.

#### Tekststatistieken en API-beperkingen {#text-statistics-and-api-limits}

HTML-bloktags en `br`-elementen scheiden tekst; inline nadruk
blijft aan het woord vastzitten. Regelafbrekingen in gewone HTML-broncode
worden spaties, terwijl platte tekst en `pre` regelgrenzen
behouden. Script-, style- en noscript-inhoud worden uitgesloten.
Extractie beoordeelt geen CSS-zichtbaarheid of gerenderde pagina.
Entiteiten worden eenmaal gedecodeerd. Voor de formulestatistieken gelden
reeksen letters of cijfers als woorden, losse leestekens niet.
Koppeltekens en apostroffen scheiden woorden. Cijfers tellen als tokens,
maar krijgen geen geschatte lettergrepen. Letters worden geteld in de
oorspronkelijke tekst; stamreductie en Duitse hoofdletternormalisatie van
`ß` naar `ss` veranderen die lengte niet.

De zinsschatting splitst op afsluitende `. ! ? 。 ！ ？` en blok- of
regelgrenzen, telt een laatste fragment zonder afsluitend leesteken mee
en beschermt decimalen en een kleine lijst gebruikelijke afkortingen,
zoals `Dr.`, `Prof.`, `e.g.` en vergelijkbare
Engelse vormen. Koppen en lijstitems kunnen dus als zinnen tellen.
Andere afkortingen, citaten, getallen, gemengde schriften en tekst met
weinig interpunctie vragen extra aandacht. De gekozen locale bepaalt
de methode, maar detecteert niet of elke zin in die taal staat.

De `toArray()` van de directe rekenfunctie voegt een
`assessment`-blok toe:

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` onderscheidt `formula`, `lix`,
`heuristic` en `unavailable`, of `unspecified` bij handmatig
gemaakte resultaten zonder formulemetadata. Lege invoer of alleen
leestekens geeft `insufficient` en `isValid()` is false.
De oude waarde `score: 0` betekent dan niet-beschikbaar, geen
moeilijkheidsscore. Bestaande Engelse en Italiaanse schoolniveaulabels
zijn benaderingen; andere talen en heuristische/LIX-resultaten krijgen
die labels niet meer. `calculateFleschKincaid()` behoudt voor compatibiliteit
zijn publieke methodenaam, maar berekent **Flesch Reading Ease**,
niet het Flesch-Kincaid-schoolniveau.

De formuletests leggen onafhankelijk getelde invoer en verwachte
berekeningen vast voor alle tien benoemde formules plus LIX. Ze toetsen
het rekengedrag, geen redactionele kwaliteit volgens moedertaalsprekers.
Leesbaarheid blijft losstaan van de Pro SEO-score.

::: warning Japans, Chinees en Koreaans: benoemde heuristiek, nooit een getal
Rankbeam gebruikt voor deze talen een methode zonder score. De rekenfunctie
geeft een **niveau** terug op basis van pakketgebonden vuistregels:
gemiddelde zinslengte in tekens voor ja ≤ 40/60/80 en zh ≤ 30/45/60,
of woorden voor ko ≤ 12/18/25. Voor Japans telt ook het aandeel kanji mee;
boven circa 45% schuift het pakket één moeilijkheidsniveau op.
Het resultaat is gemarkeerd als `heuristic: true` met **null als score**.
De checklist noemt het 'heuristiek', en de controle blijft **voor deze
talen adviserend, ongeacht `readability.advisory`**. Een vuistregel informeert
en bepaalt nooit de algemene checkliststatus. Woordenaantallen voor
`ja`/`zh` vereisen werkende ICU-segmentatie;
zonder die segmentatie worden deze controles overgeslagen.
:::

Net als zoekwoorddichtheid is leesbaarheid **standaard adviserend**:
het informeert de schrijver, maar bepaalt **niet** de algemene paginastatus.
Dat is dezelfde scheiding die Yoast tussen leesbaarheids- en SEO-analyse
maakt. Onder een minimaal woordenaantal wordt de controle **overgeslagen**.
Te weinig inhoud valt onder `content_length`, niet onder leesbaarheid.
Maak de controle bepalend als een moeilijk leesbare pagina moet mislukken:

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## De checklist uitlezen {#reading-the-checklist}

### Zonder paneel {#headless}

Pro 2.36 leest bepaalde metadata, `getContentForSEO()` en focuszoekwoorden in
de aangevraagde inhoudslocale. Checklistlabels blijven in de taal van de
gebruiker. Zonder expliciete locale wordt de `seoData()`-standaard
van een vertaalmodel gevolgd. De Filament-actie volgt het taaltabblad
van het veld of de localeschakelaar van de pagina.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Elke `CheckResult` bevat `id`, `group`,
`label`, `status`, `message`, optioneel
`recommendation` en een `advisory`-markering.

### Commando {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### In de editor (Filament, optioneel) {#in-the-editor-filament-optional}

Met [`rankbeam/laravel-seo-filament`](/nl/guide/filament) geïnstalleerd verschijnt bij
het focuszoekwoordveld een actie **On-page checklist** (paginachecklist). Die opent een
venster met dezelfde controles voor de opgeslagen inhoud van het record.
Het Filament-pakket is nooit afhankelijk van Pro. De actie sluit aan
via dezelfde eenrichtingsuitbreidingshook als AI-suggesties, zodat
installaties zonder paneel ongemoeid blijven.

## Configuratie {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Een eigen controle schrijven {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Registreer de controle door zijn klasse aan `seo-pro.checklist.rules` toe te
voegen. Een controle **mag geen** ID van een [scanprobleemcode](/nl/pro/scan-issues)
hergebruiken. De checklist heeft een eigen naamruimte die geen score berekent.

## Hoe de inhoud wordt gelezen {#how-the-content-is-read}

`SeoPro::checklistFor($model)` analyseert:

- **Titel en beschrijving**: de *bepaalde*, effectieve waarden, dezelfde
  die de editortellers en scan meten. De checklist spreekt die dus niet tegen.
- **Inhoud**: `$model->getContentForSEO()`, de core-accessor `HasSEO`,
  standaard `content` / `body` / `text`.
  Overschrijf die op je model om naar de echte hoofdtekst te verwijzen.
- **URL**: `$model->getUrlForSEO()`.
- **Focuszoekwoorden**: de opgeslagen `seo_meta.focus_keywords`.

Dit is uitsluitend analyse: er wordt geen pagina opgehaald en niets geschreven.

---
description: "Zoekwoordinzichten uit je eigen Search Console-gegevens: vijf overzichten van positiebereiken, zoekopdrachten waarvan je de CTR kunt beoordelen en zoektermen waarvoor meerdere pagina's verschijnen."
---

# Search Console-inzichten {#search-console-insights}

Vijf overzichten berekend uit je eigen Search Console-gegevens:
zoekopdrachten binnen een gekozen positiebereik, kandidaten voor
CTR-beoordeling, overlappende zoekopdracht-/paginaresultaten,
zoekopdrachtgroepen en periodeveranderingen. Drie gebruiken
gesynchroniseerde geschiedenis; twee delen een gecachet live verzoek.
Ze bieden deze specifieke analyses, niet de volledige gegevensverzameling
of mogelijkheden van een extern zoekwoordplatform.

Dit bouwt voort op de [alleen-lezenintegratie met Search Console](/nl/pro/search-console)
en de historische synchronisatie. Als de daar beschreven `seo-pro:gsc-sync`
al draait, werken drie van de vijf overzichten **zonder extra API-kosten**.

::: tip Voorwaarde
De drie overzichten met *opgeslagen gegevens* lezen de geschiedenis in
`seo_gsc_metrics`. Plan daarom eerst `seo-pro:gsc-sync` in; zie
[Search Console → geschiedenis](/nl/pro/search-console).
Hoe meer dagen je hebt gesynchroniseerd, hoe verder de trendvergelijking teruggaat.
:::

## De vijf overzichten {#the-five-surfaces}

### 1. Zoekwoorden op positie 5–20 {#_1-striking-distance-keywords}

Zoekopdrachten waarvan de **naar vertoningen gewogen gemiddelde positie
tussen 5 en 20 ligt**, gerangschikt op vertoningen. Gebruik ze om
relevantie en interne links te beoordelen. Dit bereik bewijst niet dat
een kleine wijziging een zoekopdracht naar de eerste pagina brengt.

### 2. CTR-kansen {#_2-ctr-opportunities}

Zoekopdrachten die **goed staan maar minder klikken krijgen dan voor
hun positie wordt verwacht**. De werkelijke CTR wordt vergeleken met
een samengestelde branchecurve van CTR per positie. Zoekopdrachten
met echte vertoningen die daar ver onder blijven, zijn **kandidaten
voor het herschrijven van titel en beschrijving**. Ze worden gesorteerd
op geschatte *gemiste klikken*. Deze lijst sluit aan op de
[AI-metasuggesties](/nl/pro/ai-assist): hij geeft precies de
zoekopdrachten waarvoor je een herschrijving kunt overwegen.

### 3. Kannibalisatie {#_3-cannibalization}

Zoekopdrachten waarvoor **twee of meer van je URL's verschijnen**.
Overlap is niet per definitie schadelijk. Beoordeel of de pagina's
verschillende zoekintenties bedienen voordat je ze samenvoegt of
duidelijker van elkaar onderscheidt.

### 4. Zoekopdrachtgroepen {#_4-query-clusters}

De **zoekopdrachten waarvoor elke pagina daadwerkelijk verschijnt**,
gegroepeerd per pagina: de onderwerpen die Google in de praktijk aan
de pagina koppelt. Dit helpt pagina's herkennen die afwijken van hun
bedoelde onderwerp, of ongemerkt verschijnen voor een waardevolle
term waarop je niet had ingezet.

### 5. Trend tegenover de vorige periode {#_5-trend-vs-previous-period}

De **grootste veranderingen** in klikken, vertoningen, positie en CTR
tussen het huidige tijdsvenster en de even lange periode direct ervoor.
Positie wordt alleen vergeleken als een zoekopdracht in beide perioden
verkeer had. Een volledig nieuwe of geheel verdwenen zoekopdracht heeft
geen vergelijkbare positie in de andere periode.

## Waar de cijfers vandaan komen: live of opgeslagen {#where-the-numbers-come-from-live-vs-snapshot}

Elk overzicht gebruikt de bron die de vraag correct en met de laagste
kosten beantwoordt. De opgeslagen geschiedenis kan niet reconstrueren
welke **zoekopdracht** bij welke **pagina** hoorde: beide dimensies
worden afzonderlijk bewaard. Alleen de twee overzichten die dat paar
nodig hebben, halen live gegevens op. Ze **delen één gecachet verzoek**.

| Overzicht | Bron | Reden |
|---|---|---|
| Zoekwoorden op positie 5–20 | **Lokaal opgeslagen gegevens** | Positie en vertoningen per zoekopdracht staan al in de gesynchroniseerde geschiedenis; geen API-kosten |
| CTR-kansen | **Lokaal opgeslagen gegevens** | Dezelfde eigen gegevens; de verwachte-CTR-curve is een statische referentie, geen externe opvraging |
| Trendverschillen | **Lokaal opgeslagen gegevens** | Gebruikt werkelijke geschiedenis per dag, precies wat de synchronisatie bewaart |
| Kannibalisatie | **Live**, zoekopdracht × pagina | De koppeling tussen zoekopdracht en pagina wordt niet bewaard; elk paar opslaan zou de opslagomvang vermenigvuldigen |
| Zoekopdrachtgroepen | **Live**, deelt het verzoek van overzicht 3 | Dezelfde paren, gegroepeerd per pagina in plaats van per zoekopdracht |

Een bezoek aan de inzichtenpagina doet dus **hoogstens één** Search
Analytics-verzoek, gecachet gedurende `search_console.cache_ttl` seconden. De
overzichten met paren zijn bewust live: bij kannibalisatie en groepering
wil je het actuele beeld. De gedeelde cache beperkt herhaalde verzoeken.
Tokenvernieuwing kan een extra authenticatieverzoek vereisen en Googles
quota blijven gelden. De overzichten met opgeslagen gegevens gebruiken
nooit het netwerk.

## In het dashboard {#in-the-dashboard}

Met de Filament-plugin geïnstalleerd verschijnt **Search Console-inzichten**
onder de navigatiegroep *SEO*, alleen als de integratie is ingeschakeld.
Alles is uitsluitend leesbaar. Elk overzicht heeft een eigen onderdeel.
Lege overzichten met opgeslagen gegevens tonen de aanwijzing om de
geschiedenis te synchroniseren. Een mislukte live opvraging voor de
paren toont een opgeschoonde melding op die plek en blokkeert nooit de pagina.

## Configuratie {#configuration}

Alles staat onder `search_console.insights` in `config/seo-pro.php`. De standaardwaarden
bieden een uitgangspunt; stem de drempels af op de omvang van je site.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info Verwachte-CTR-curve
De curve voor CTR-kansen is een **heuristiek**, samengesteld uit
gepubliceerde gemiddelden van organische CTR per positie. Het is een
referentie, geen uitspraak over jouw specifieke site. Een gemarkeerde
zoekopdracht is een *kandidaat om te beoordelen*, geen bewezen gebrek.
Heb je een eigen gemeten curve, vul die dan in bij `insights.ctr_curve` als
een `position => percent`-mapping.
:::

## Zie ook {#see-also}

- [Search Console](/nl/pro/search-console): de alleen-lezenintegratie
  en historische synchronisatie waarop deze inzichten berusten.
- [Rapporten in je eigen huisstijl](/nl/pro/reports): veranderingen
  tussen perioden in de PDF met je huisstijl.
- [AI-ondersteuning](/nl/pro/ai-assist): titels en beschrijvingen
  herschrijven voor de kandidaten uit het CTR-overzicht.

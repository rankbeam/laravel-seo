---
description: "Een tweede deterministische score naast de SEO-score: Rankbeams eigen maat voor technische AI-geschiktheid. Kunnen crawlers een pagina bereiken en lezen? Deze score blijft los van de SEO-score."
---

# De AI-Readiness-score: een tweede, deterministische maat {#the-ai-readiness-score-—-a-second-deterministic-axis}

De Pro-scan geeft elke pagina naast de [SEO-score](/nl/pro/scoring) een
tweede getal: een **AI-Readiness-score van 0–100**. Die beantwoordt de vraag
of *AI-crawlers en antwoordmachines deze inhoud kunnen bereiken, lezen en
aan een bron toeschrijven*. Hij wordt **nooit vermengd met de organische
SEO-score**. Het zijn twee afzonderlijke maten, elk met een eigen
beoordelingsmethode, versie en kolom.

::: warning Wat het getal wel en niet betekent
De AI-Readiness-score is een **door Rankbeam gedefinieerde, deterministische
maat voor technische compatibiliteit**: zijn de via crawlen onderzochte
signalen op een pagina aanwezig en correct opgebouwd? Hij voorspelt **geen**
ranking, indexering, opname of citatie door een zoek- of AI-systeem.
Geen enkele score garandeert die uitkomsten. De controle `air_llms_txt`
kent punten toe aan een **optioneel** compatibiliteitsbestand `llms.txt`
voor hulpmiddelen die ervoor kiezen het te gebruiken. Google Search vereist
dit bestand niet en het is geen rankingsignaal.
:::

Net als de SEO-score is deze score **volledig deterministisch en reproduceerbaar**.
Elk punt is terug te voeren op een benoemde crawlcontrole, en dezelfde signalen
leveren altijd hetzelfde getal op. **De scoreberekening doet nergens
AI-aanroepen.** Het is een transparante, controleerbare meting, geen
steekproef van LLM-antwoorden zoals bij SaaS-producten voor 'AI-zichtbaarheid'.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip Twee afzonderlijke maten
`AI-readiness: 74/100` staat naast `SEO: 82/100`; ze beïnvloeden elkaar niet.
AI-Readiness staat in eigen `ai_readiness_*`-kolommen op de Pro-rij
`seo_scan_results`. Net als bij SEO is **het getal een Pro-functie**;
het gratis Core-commando [`seo:audit`](/nl/guide/audit) toont geen getal.
:::

## Punten opbouwen in plaats van aftrekken {#additive-credit-not-penalty}

De [SEO-score](/nl/pro/scoring) begint op 100 en *trekt punten af*.
AI-Readiness begint juist op **0** en **kent** het gewicht van elke controle
geheel of gedeeltelijk **toe**. Geschiktheid bouw je op; een site zonder
AI-signalen scoort daarom rond 0, in plaats van '100 min een paar punten'.
De gewichten tellen op tot precies **100**.

## De beoordelingsmethode {#the-rubric}

De score wordt berekend met een **gepubliceerde beoordelingsmethode met
versiebeheer**, `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric`, met tien controles in vier categorieën:

### A · Bottoegang en beheer — 30 punten {#a-·-bot-access-control-—-30-points}

Kunnen crawlers voor AI-zoekdiensten en assistenten je bereiken? Dit wordt
beoordeeld aan de hand van de **geserveerde `/robots.txt`**, toegepast op
**het eigen pad van de gescande pagina**. Een pagina onder `Disallow: /section`
is niet toegestaan, ook als de siteroot open is. Robots.txt is een instructie
voor crawlers die deze naleven, geen blokkade van netwerktoegang.
De controle gebruikt de indeling uit de [AI-crawlercatalogus](/nl/guide/ai-crawlers):
training, zoeken en assistent.

| Controle | Gewicht | Puntentoekenning |
|---|---|---|
| `air_robots_reachable` — er wordt een leesbare `robots.txt` geserveerd | 6 | Aanwezig / afwezig |
| `air_ai_search_access` — AI-**zoekcrawlers**, het kanaal voor verwijzend verkeer, mogen de site bereiken | 10 | Toegestaan aandeel |
| `air_ai_assistant_access` — AI-**assistentcrawlers** mogen de site bereiken | 8 | Toegestaan aandeel |
| `air_explicit_ai_policy` — een expliciete `robots.txt`-regel voor een bekende AI-bot | 6 | Aanwezig / afwezig |

::: tip Trainingsbots blokkeren verlaagt je geschiktheid niet
Trainingsbots zoals GPTBot en CCBot weigeren is een legitieme keuze en geeft
**nooit** puntenaftrek. Training telt alleen mee via `air_explicit_ai_policy`:
een bewust, expliciet standpunt. Een site die trainingsbots blokkeert maar
zoek- en assistentcrawlers toestaat, kan in deze categorie alle punten halen.
:::

### B · Vindbaarheid — 20 punten {#b-·-discoverability-—-20-points}

| Controle | Gewicht | Puntentoekenning |
|---|---|---|
| `air_sitemap_discoverable` — een XML-sitemap is bereikbaar **en** wordt genoemd in een `Sitemap:`-instructie | 12 | Beide / één / geen |
| `air_llms_txt` — er wordt een geldige `/llms.txt` met kop en links geserveerd | 8 | Geldig / aanwezig / afwezig |

### C · Machineleesbare inhoud — 22 punten {#c-·-machine-readable-content-—-22-points}

| Controle | Gewicht | Puntentoekenning |
|---|---|---|
| `air_server_rendered_content` — de op de server gerenderde HTML bevat substantiële tekst; inhoud bestaat zonder JS uit te voeren | 14 | Op basis van woordenaantal |
| `air_markdown_twin` — via contentonderhandeling wordt een Markdown-versie van dezelfde pagina geserveerd | 8 | Aanwezig / afwezig |

### D · Gestructureerde gegevens en geschiktheid voor antwoorden — 28 punten {#d-·-structured-data-answer-readiness-—-28-points}

| Controle | Gewicht | Puntentoekenning |
|---|---|---|
| `air_schema_completeness` — JSON-LD aanwezig, hoofdentiteit met type, auteurschap en datum aanwezig; auteur en datum voor artikelen | 18 | Volledig / gedeeltelijk / geen |
| `air_answer_structure` — structuur die het extraheren van antwoorden ondersteunt: FAQ/QA/HowTo-schema, koppenhiërarchie, lijsten en een beknopte inleiding | 10 | Op basis van het aantal aanwezige kenmerken |

Elke controle geeft **volledige**, **gedeeltelijke** of **geen** punten,
of wordt **overgeslagen** als het benodigde signaal niet kon worden verzameld,
bijvoorbeeld een paginacontrole bij een doel dat zonder pagina-ophaalactie
wordt gescand. Een overgeslagen controle scoort 0, maar wordt als zodanig
gemarkeerd. *Niet kunnen controleren* wordt dus nooit als bevestigde afwezigheid getoond.

### Bereik van de gratis audit {#free-audit-reach}

Volledigheid van het schema (`air_schema_completeness`) is te bepalen uit de
gestructureerde gegevens van een model, zonder iets op te halen. Dat is
hetzelfde pad dat de gratis audit gebruikt voor zijn bevindingen over
geschiktheid voor antwoordmachines. De overige negen controles vereisen
een crawl; het volledige getal hoort daarom bij de **Pro-scan**.

## Afbakening: wat deze maat uitsluit {#honest-scope-—-what-this-axis-excludes}

Deze maat beoordeelt **deterministische inhoudssignalen**. Controles op
agentinfrastructuur die een draaiende applicatie of DNS betreffen, vallen erbuiten:

| Uitgesloten | Reden |
|---|---|
| **DNS-AID**, DNS-records voor agentontdekking | DNS-/DNSSEC-infrastructuur, geen eigenschap van een geserveerde pagina. |
| **Web Bot Auth**, ondertekening per verzoek | Een interactieve cryptografische handshake, geen statische inhoud. |
| **Protocolontdekking**, zoals API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills en WebMCP | Vereist een draaiende app, API of MCP-server. |
| **Handel**, zoals x402, MPP, UCP en ACP | Betaalinfrastructuur voor agents; een inhoudssite heeft niets waarvoor betaald moet worden. |

De maat omvat wel de volledigheid van schema-entiteiten en de structuur van
antwoordblokken. Die inhoudssignalen beschrijven ordening en toeschrijving,
zonder te garanderen dat een zoek- of antwoordmachine ze gebruikt.

## Versiebeheer: historische scores veranderen nooit ongemerkt {#versioning-—-historical-scores-never-silently-change}

Elke opgeslagen AI-Readiness-score krijgt de `AiReadinessRubric::VERSION` waarmee hij is
berekend (`ai_readiness_version`). Een wijziging in controles, gewicht of
puntentoekenning verandert de beoordelingsmethode en **verhoogt de versie**.
Zo blijft bij elk getal vastgelegd welke methode het verklaart en blijven
historische getallen vergelijkbaar. De score wordt **opgeslagen, niet opnieuw
berekend bij uitlezen**. Drempels die de score beïnvloeden, zoals woorden-
en kenmerkaantallen, zijn constanten in de code die aan de versie zijn gekoppeld.
Ze zijn nooit configuratie, zodat een instelling een gepubliceerd getal niet
ongemerkt kan veranderen.

::: warning Eén invoerbron staat niet vast door de versie
De bottoegangscontroles lezen de **actuele** [AI-crawlercatalogus](/nl/guide/ai-crawlers)
uit de core. Een cataloguswijziging, zoals een nieuwe bot of een ander doel,
verandert de invoer en kan de twee bottoegangssubscores verschuiven zonder
`AiReadinessRubric::VERSION` te verhogen. De versie volgt de *beoordelingsmethode*, niet
de catalogus. Dat is bewust: de controle is nuttiger met de actuele botlijst
dan met een bevroren lijst. Leg voor exacte historische vergelijkbaarheid ook
de core-pakketversie vast, naast de versie van de beoordelingsmethode.
:::

## Opslag {#where-it-s-stored}

Elke scan voegt de AI-Readiness-kolommen in of werkt ze bij op **dezelfde**
`seo_scan_results`-rij als de SEO-score:

| Kolom | Inhoud |
|---|---|
| `ai_readiness_score` | Het getal van 0–100; null totdat het doel met deze maat ingeschakeld is gescand. |
| `ai_readiness_version` | De beoordelingsmethode waarmee het is berekend. |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]`: de volledige herleiding. |

Bij afronding wordt het gemiddelde per uitvoering vastgelegd in
`seo_scan_runs.avg_ai_readiness`, net als `avg_score`. Dit vormt de AI-Readiness-trend.

## De score uitlezen {#reading-the-score}

**Zonder paneel** bevat het laatste resultaat voor een model beide maten:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**In Filament** plaats je de bijbehorende kolom naast de SEO-scorekolom in
elke resourcetabel:

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

De score verschijnt ook als badge naast de paginascore boven het SEO-titelveld,
en als eigen onderdeel in het [rapport in je eigen huisstijl](/nl/pro/reports),
in PDF en e-mail: getal, letterbeoordeling, verandering tegenover het vorige
rapport en trend per scan. Hij staat altijd naast de organische score en
wordt er nooit mee vermengd.

### Letterbeoordelingen {#grade-bands}

Een letterbeoordeling voor de weergave, afgeleid van het getal; het getal is
het vaste contract. Voor consistentie gelden dezelfde intervallen als bij de SEO-score:

| Score | Beoordeling |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Configuratie {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

Controles en gewichten zijn **niet** instelbaar. Voor een bepaalde
`ai_readiness_version` moet de score op elke installatie deterministisch zijn.
De berekening wijzigen is daarom een wijziging van de beoordelingsmethode
in de code, geen instelling.

::: warning Sitesignalen worden via het verzoekpad binnen het proces ontdekt
Voor een doel op dezelfde host haalt de scan `/robots.txt`,
`/llms.txt` en de pagina op via de HTTP-kernel van Laravel binnen het
proces, net als de rest van de scan. Een `robots.txt` of `llms.txt`
die als **statisch bestand** buiten de Laravel-routering wordt geserveerd,
wordt niet gezien. Serveer deze via de pakketroutes, de aanbevolen inrichting,
om ze in de score te laten meetellen.
:::

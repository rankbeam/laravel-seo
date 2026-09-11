---
description: "Optionele AI-hulp met je eigen API-sleutel: titel- en metasuggesties, begrijpelijke uitleg bij scanproblemen, herschrijven met één klik en schema.org-suggesties. Standaard uitgeschakeld."
---

# AI-assistentie {#ai-assist}

Optionele AI-assistentie **met je eigen API-sleutel**: suggesties voor titels
en metabeschrijvingen, begrijpelijke uitleg bij scanproblemen, **een beschrijving
herschrijven** met één klik en **suggesties voor gestructureerde gegevens
(schema.org)**. De functie staat **standaard uit**. Als de instelling uitstaat,
wordt geen enkel AI-codepad uitgevoerd.

Drie uitgangspunten bepalen het ontwerp:

- **Jouw sleutel, jouw provider.** Verzoeken gaan rechtstreeks van *jouw server*
  naar de provider die *jij* instelt: Anthropic, OpenAI, Google of een lokale of
  OpenAI-compatibele server. Eventuele kosten komen op jouw account. Er wordt
  niets via een tussenpartij geleid, per gebruik afgerekend of doorverkocht, en
  het pakket stuurt nergens telemetrie naartoe.
- **Interactieve suggesties vereisen expliciete acceptatie.** Een suggestie
  kiezen vult het formulier in; oplossingen in het dashboard vereisen Toepassen.
  Sinds Pro 2.42 slaat de bulk-CLI standaard privéconcepten op. Gebruik
  `--auto-apply` alleen als je bewust direct wilt schrijven.
- **Fouten blijven niet-fataal.** Een ontbrekende of ongeldige sleutel, opgebruikt
  tegoed, een verzoeklimiet of time-out levert een melding in de interface op.
  Dit kan opslaan, renderen of scannen nooit blokkeren.

## Providers op een rij {#providers-at-a-glance}

Kies op basis van beschikbare accounts, gegevensvereisten en kosten. Alle vier
integraties bieden dezelfde taken, maar modelondersteuning, uitvoerformaat,
snelheid en kwaliteit kunnen verschillen.

| Provider | Meegeleverd standaardmodel | Gestructureerde uitvoer | Indicatieve kosten | Gebruik |
|---|---|---|---|---|
| **Lokaal** (Ollama / LM Studio / vLLM) | `llama3.1`, zelf in te stellen | Naar beste vermogen, via `response_format` | **$0 API-kosten** bij zelf gehoste inferentie; infrastructuur kost wel geld | Controle over de bestemming van gegevens |
| **OpenAI** | `gpt-5.5` | Structured Outputs waar het gekozen model dit ondersteunt | Circa $0,005 per suggestie bij de voorbeeldaannames hieronder | Een bestaand OpenAI-account |
| **Anthropic** | `claude-opus-4-8` | `output_config.format` waar ondersteund | Circa $0,015 per suggestie bij die aannames | Een bestaand Anthropic-account |
| **Google** | `gemini-2.5-flash` | `responseSchema` waar ondersteund | Circa $0,0005 per suggestie bij die aannames | Een Google-account; controleer modelquota en prijzen |

Deze namen beschrijven de meegeleverde configuratie, geen gegarandeerde actuele
beschikbaarheid op je account. De kosten gebruiken de voorbeeldaannames van het
pakket, geen geverifieerde actuele prijzen. Het gedrag van de integraties en
waarnemingen uit de gepubliceerde proeven:

- **Gestructureerde uitvoer.** Ondersteunde OpenAI-, Google- en Anthropic-paden
  krijgen een JSON-schema; ongeldige antwoorden worden als fout afgehandeld.
  Lokale servers krijgen `response_format` als verzoek naar beste vermogen. Als ze
  dat negeren, levert tolerante parsing een geldige lijst of een fout op,
  zonder gedeeltelijke uitvoer toe te passen.
- **Redeneren verandert het tokenverbruik.** De beschreven Gemini-proef gebruikte
  voor een beschrijving ongeveer 500 verborgen redeneertokens en ongeveer 100
  zichtbare tokens. De geteste Anthropic-aanroepen meldden geen verborgen
  redeneertokens. Dit geldt niet universeel voor die modelfamilies. Verborgen
  tokens kunnen als uitvoer worden gefactureerd; daarom bestaat de hieronder
  beschreven ondergrens voor redeneermodellen.
- **Modellen zijn instelbaar.** Stel `SEO_PRO_AI_MODEL` in op een beschikbaar model
  dat compatibel is met de API en parameters van de adapter. Voorbeelden zijn
  `claude-haiku-4-5`, `gpt-5.4-mini` en `gemma-3-12b-it`. Controleer ondersteuning en
  uitvoerkwaliteit voordat je een model voor een hele verzameling gebruikt.

## Inrichting {#setup}

Schakel de functie in en zet je providersleutel in de omgeving. Wissel van
cloudprovider door provider en sleutel te wijzigen; controleer daarbij ook
een eventueel zelf ingesteld model. De lokale adapter vereist ook een server-URL.

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip Een API-sleutel staat los van een Claude- of ChatGPT-abonnement
Een **abonnement** op Claude Code, Claude.ai of ChatGPT betaalt niet voor
**API-gebruik**. `SEO_PRO_AI_API_KEY` moet een *API-sleutel met betaling naar gebruik*
uit de ontwikkelaarsconsole van de provider zijn, of een Google AI Studio-sleutel,
met een eigen tegoed. Een account met alleen een abonnement of zonder tegoed
kan wel authenticeren, maar geeft een fout over **opgebruikt tegoed of quotum**.
Zie [Problemen oplossen](#troubleshooting).
:::

Het configuratiebestand (`config/seo-pro.php`, blok `ai`) bevat
`timeout`, `max_input_chars`, `max_output_tokens`, `token_budgets`,
`reasoning_models` + `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model`
(het goedkopere model voor bulk-aanvulling; zie [Kosten](#cheaper-bulk-generation)),
`retry`, de tabel `pricing` en het subblok `local`.
Deze staan beschreven onder [Grenzen en afstelling](#limits-and-tuning).

::: warning Sleutelbeheer bij gecachete configuratie
De configuratie bewaart alleen de **naam** van de omgevingsvariabele
(`api_key_env`), nooit de sleutel. `php artisan config:cache` schrijft je sleutel dus
nooit naar `bootstrap/cache/config.php`. De keerzijde: met gecachete configuratie wordt
`.env` niet geladen. Stel `SEO_PRO_AI_API_KEY` daarom als echte
omgevingsvariabele op de server in.
:::

## Lokale inferentie en cloudopties {#running-at-0-and-the-cheapest-paid-option}

- **Bij zelf gehoste inferentie betaal je geen API-tarief per token aan een
  provider.** Hardware, stroom en beheer kosten nog steeds geld. Inhoud blijft
  alleen binnen je netwerk als ook de ingestelde inferentieserver en zijn
  afhankelijkheden daar blijven.
- **Google heeft quota en prijzen per model en gebruiksniveau.** Een AI
  Studio-sleutel (`aistudio.google.com/apikey`, formaat `AIza…`) kan proeven binnen
  een gratis quotum toestaan. Controleer of de limieten passen bij je werklast
  voordat je zo nodig facturering inschakelt. `gemini-2.5-flash` is de meegeleverde
  standaard. Gemini- en Gemma-modellen hebben niet allemaal dezelfde
  redeneermogelijkheden: `reasoning_models` gebruikt ingestelde naampatronen,
  geen test van modelcapaciteiten.

Om te bepalen waar inferentie plaatsvindt:

- **Lokaal of OpenAI-compatibel.** Gebruik `provider=local` met een server die
  OpenAI Chat Completions ondersteunt, zoals **Ollama**, **LM Studio**,
  **vLLM** of **LocalAI**, of met een externe gateway zoals **OpenRouter**.
  Stel `SEO_PRO_AI_LOCAL_BASE_URL` in op de API-root; `/chat/completions` wordt toegevoegd.
  Kies een beschikbare `SEO_PRO_AI_MODEL`. Een externe gateway ontvangt gegevens
  buiten je netwerk en kan kosten rekenen: de adapternaam `local`
  betekent niet dat inferentie lokaal plaatsvindt.

::: warning Lokale `base_url` wordt gevalideerd; localhost vereist expliciete toestemming
De `base_url` is een instelling met uitgebreide rechten, gevalideerd via
dezelfde `SsrfGuard` als andere uitgaande ophaalverzoeken: alleen http/https,
geen gebruikersgegevens in de URL en standaard alleen een **openbaar** adres.
Een verkeerd ingestelde of kwaadaardige `base_url` kan daardoor niet worden
gebruikt om interne diensten af te tasten. Een werkelijk lokale server draait
op `127.0.0.1`, een privéadres. Daarvoor moet je expliciet
`seo-pro.ai.local.allow_local_addresses` inschakelen (`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`). Laat dit uit voor een openbare
gateway zoals OpenRouter. Het verzoekpad staat vast en redirects worden nooit
gevolgd, zodat de sleutel niet naar een andere host kan worden doorgestuurd.
:::

::: tip Redeneren instellen voor ondersteunde Ollama-modellen
Een lokaal redeneermodel kan de standaardtime-out overschrijden. Als het model
en de serverversie dit ondersteunen, kan `['think' => false]` in `seo-pro.ai.local.extra_body`
het redeneren voor suggesties uitschakelen. Ondersteuning verschilt; raadpleeg
de [Ollama-documentatie](https://docs.ollama.com/capabilities/thinking).
Verhoog zo nodig `seo-pro.ai.timeout`. Het pakket stuurt geen `temperature` mee,
omdat sommige modellen dat weigeren.
:::

## Kosten {#cost}

Het pakket rekent geen opslag; je betaalt de provider rechtstreeks. Bij zelf
gehoste inferentie zijn er geen API-kosten van een provider, maar wel
infrastructuurkosten. Twee bedragen zijn van belang: de kosten **per suggestie**
bij interactief gebruik en de kosten van **bulk-aanvulling** voor een hele verzameling.

De tabel `seo-pro.ai.pricing`, in USD per 1.000.000 tokens, zet een tokenschatting
om in het dollarbedrag dat de bevestigingsvraag bij bulk-aanvulling toont.
Dit zijn **meegeleverde schattingsaannames**, geen geverifieerde actuele
publieke prijzen. **Vervang ze door de actuele gepubliceerde prijzen van je
provider** voor een juiste schatting:

| Modelpatroon | Invoer $/1M | Uitvoer $/1M |
|---|---|---|
| `claude-opus-*` | 15,00 | 75,00 |
| `claude-sonnet-*` | 3,00 | 15,00 |
| `claude-haiku-*` | 1,00 | 5,00 |
| `gpt-5*mini*` | 0,50 | 1,50 |
| `gpt-5*` | 5,00 | 15,00 |
| `gemini-2.5-pro*` | 1,25 | 10,00 |
| `gemini-*flash*` | 0,15 | 0,60 |

Het gepubliceerde voorbeeld gebruikt het tokenverbruik van een geteste pagina,
voor één titel en één beschrijving, met die aannames. De laatste kolom past de
voorbeeldkorting van 50% voor batchverwerking toe op ondersteunde adapters.
Dit zijn geen actuele prijsopgaven.

| Provider / model | Circa per suggestiepaar | Circa per 1000 records (bulk-aanvulling) | Circa per 1000 records (`--batch`) |
|---|---|---|---|
| Lokaal `gemma`/`llama` (Ollama) | **$0 API-kosten** | **$0 API-kosten** | Niet van toepassing; niet geïmplementeerd door de adapter |
| Google `gemini-2.5-flash` (betaald) | Circa $0,001 | Circa $0,40 | Niet van toepassing; niet geïmplementeerd door de Rankbeam-adapter |
| OpenAI `gpt-5.5` | Circa $0,008 | Circa $5,25 | **Circa $2,63**, 50% korting |
| Anthropic `claude-opus-4-8` | Circa $0,03 | Circa $20 | **Circa $10**, 50% korting |

Het commando geeft ongeveer ±50% bij de schatting aan, maar **dat is geen
bestedingslimiet of gegarandeerde foutmarge**. Werkelijke invoer, uitvoer en
prijzen veranderen het totaal. De schatting rekent met zichtbare uitvoer;
gefactureerde verborgen redeneertokens kunnen de kosten verder verhogen.
Voor modellen zonder prijsvermelding wordt alleen een tokenschatting getoond.

### Goedkopere bulk-generatie {#cheaper-bulk-generation}

Stel `seo-pro.ai.bulk_model` (`SEO_PRO_AI_BULK_MODEL`) in om **alleen voor bulk-aanvulling**
een ander model te gebruiken: `seo-pro:ai-fill` / `SeoPro::aiFill()`. Filament en
`seo-pro:ai-suggest` blijven `model` gebruiken. Bij null gebruikt
bulk-aanvulling ook `model`. De schatting gebruikt het prijspatroon van
het gekozen model. Beoordeel representatieve uitvoer voordat je het volume
verhoogt; een goedkoper model is niet automatisch geschikt.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Pakketvoorbeelden gebruiken **anthropic** `claude-haiku-4-5`, **openai**
`gpt-5.5-mini`, **google** `gemini-2.5-flash` of een kleiner **lokaal** model.
Een prijspatroon kan op een naam passen die de provider niet aanbiedt.
Controleer het werkelijke model-ID, de API-compatibiliteit en de prijs voordat
je het model instelt.

Een **aanvulling van 100 pagina's**, elk zonder titel én beschrijving, betekent
200 provideraanroepen. Hieronder staan schattingen op basis van de
meegeleverde `pricing`-standaardwaarden en het tokenmodel van de schatter:
600 invoer- en 150 uitvoertokens per aanroep. Het kwaliteitsmodel staat naast
het goedkopere `bulk_model`-model:

| Provider | Kwaliteitsmodel — 100 pagina's | Goedkoper `bulk_model`-model — 100 pagina's |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **$4,05** | `claude-haiku-4-5` ≈ **$0,27** |
| **OpenAI** | `gpt-5.5` ≈ **$1,05** | `gpt-5.5-mini` ≈ **$0,11** |
| **Google** | `gemini-2.5-pro` ≈ **$0,45** | `gemini-2.5-flash` ≈ **$0,04** |
| **Lokaal** (Ollama / vLLM) | Elk model: **$0 API-kosten** | Elk model: **$0 API-kosten** |

Dit zijn illustratieve schattingen, zonder gegarandeerde marge van ±50% en
zonder reservering voor verborgen redeneertokens. Werk `seo-pro.ai.pricing` bij met
de gepubliceerde prijzen van de gekozen provider voordat je op de schatting vertrouwt.

## Taal van de uitvoer {#output-language}

Elke prompt noemt de paginataal en de BCP-47-code, bijvoorbeeld *'in
Braziliaans Portugees (pt-BR), de taal van de pagina, ongeacht andere talen
in het fragment'*. De paginacontext voor het model bevat sinds Pro 2.34 een
`Language:`-regel. Daarvoor vroegen prompts om 'dezelfde taal als de
broninhoud'. Het model moest de taal afleiden uit een kort fragment, soms
vermengd met code; een Turkse pagina met een Engelse merknaam kon daardoor
Engelse uitvoer krijgen. De locale is die waarin de metadata van de pagina
zijn bepaald, of de applicatielocale als de pagina er geen heeft. Diezelfde
locale bepaalt het [lengtebudget](/nl/guide/multilingual#title-and-description-budgets-per-script).
Een Japanse pagina vraagt dus om titels van circa 30 tekens *in het Japans*.

Sinds Pro 2.36 bepaalt een expliciete inhoudslocale samen de metadatarij,
inhoudshooks en prompttaal. De interfacetaal van de gebruiker blijft ongewijzigd.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Bestaande positionele argumenten blijven hetzelfde. Laat `locale:` weg
om de `seoData()`-standaard van het model te gebruiken; afzonderlijke
vertaalmodellen kunnen zo hun taal opgeven. Het fragment gebruikt
`getContentForSEO()` als die niet-lege inhoud teruggeeft en valt anders terug op de
ingestelde inhoudsvelden. Filament 1.11 geeft automatisch de locale van het
geselecteerde tabblad door, ook bij één locale en bij gebruik van de localeschakelaar op de pagina.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

De bulkfuncties `plan()`, `fill()` en `submitBatchFill()` accepteren
ook een afsluitend `locale:`-argument. Gebruik dezelfde locale bij het
maken van `FillProgress(..., locale: 'it')` en het indienen van de uitvoering. Elk batchitem
slaat zijn inhoudslocale op. Het ophalen van resultaten gebruikt die locale
en controleert de metadatarij opnieuw voordat het schrijft. Uitvoeringen met
een expliciete locale hebben aparte checkpointbestanden, en de markeringen
voor verwerkte records onderscheiden talen. Voer hetzelfde commando opnieuw
uit om resultaten op te halen. Eigen taken moeten de inhoudslocale serialiseren
en doorgeven.

Checkpoints van vóór Pro 2.36 legden geen inhoudslocale vast. Een openstaande
oude batch blijft bewaard en wordt niet automatisch opgehaald. Controleer de
providerresultaten en bedoelde locale voordat je hem met `--fresh`
verwijdert; anders kan een nieuwe inzending hetzelfde werk opnieuw laten
factureren. Ook een oud sequentieel checkpoint met verwerkte records vereist
afstemming voordat je het opnieuw instelt.

### Evaluatie per taal {#per-language-evaluation}

De Pro-bronrepository bevat 170 invoerpagina's verdeeld over 17 locales en
een optioneel evaluatiehulpmiddel. De invoerpagina's hebben structurele
controles en heuristische controles op basistaal; onafhankelijke beoordeling
door moedertaalsprekers staat nog open.

Het hulpmiddel controleert **titels én beschrijvingen**, registreert
grafeemlengtes en aanwijzingen voor taal en schrift, en bewaart elk
providerantwoord vóór de assertions. Korte titels, gemengde tekst en gedeelde
Chinese/Japanse tekens kunnen onzeker blijven. Een vermoeden van Portugees
als basistaal bewijst geen Braziliaans taalgebruik. Ook gedeeltelijke controles
op Chinese tekens certificeren geen regionale schrijfkwaliteit.

Live uitvoeringen vereisen `SEO_PRO_AI_EVAL=1`, een expliciete selectie voor
`SEO_PRO_AI_EVAL_LOCALES` en een `SEO_PRO_AI_EVAL_RUN`-ID. Ze kunnen providerkosten
veroorzaken en draaien nooit standaard. Elke uitvoering koppelt het bewijs
aan de provider, het aangevraagde en teruggegeven model, hashes van fixtures,
verzoeken en code, en tijdstempels. Mislukte pogingen blijven bewaard.
Hervatten hergebruikt opgeslagen antwoorden; een onderbroken verzoek vereist
een expliciete herhaalpoging, omdat het de provider al kan hebben bereikt.

Bewijs staat onder `storage/app/seo-ai-evals/<run-id>/` in de testomgeving van de bronrepository.
De `README.md` van de fixtures beschrijft het schema met versiebeheer en
de commando's. Moedertaalbeoordelaars geven in aparte beoordelingsrecords
scores aan exacte uitvoerhashes. Een geslaagde automatische controle is geen
goedkeuring door een moedertaalspreker of garantie op publiceerbare tekst.

## Grenzen en afstelling {#limits-and-tuning}

Alle instellingen staan in het blok `ai` van `config/seo-pro.php`:

- **`timeout`**, standaard `15` seconden,
  omgevingsvariabele `SEO_PRO_AI_TIMEOUT`. Dit begrenst ook de synchrone aanroep
  tijdens het openen van het suggestievenster in Filament en is daarom kort
  gehouden. Een **traag redeneer- of lokaal model kan meer dan 15 seconden
  nodig hebben** en een time-out krijgen. Verhoog de waarde met
  `SEO_PRO_AI_TIMEOUT`; zie ook de Ollama-tip over `think => false` hierboven.
  Een time-out geeft altijd een melding in de interface en blokkeert nooit het opslaan.
- **`max_input_chars`**, standaard `6000`. De grens voor hoeveel
  pagina-inhoud per verzoek wordt verstuurd, als platte tekst zonder HTML,
  om kosten en gegevensdeling te beperken.
- **`max_output_tokens`**, standaard `1000`. De basislimiet voor
  gegenereerde tokens. Een antwoord dat deze bereikt, geeft de aparte fout
  `truncated`, nooit ongemerkt een half antwoord.
- **`token_budgets`**: uitvoerlimieten per taak — `suggestions` 800,
  `explanation` 600, `rewrite` 300 en `schema_suggestion` 700.
  Geen daarvan heeft de volledige standaard nodig, maar de ondergrens voor
  redeneermodellen wordt er nog op toegepast.
- **`reasoning_models`** + **`reasoning_min_output_tokens`**, standaard `2000`.
  Elk model waarvan de naam op een patroon past, zoals `*gemma*`,
  `gemini-2.5-*` of `o1*`/`o3*`/`o4*`,
  krijgt een uitvoerbudget van minstens deze ondergrens. Een redeneermodel
  verbruikt verborgen tokens voordat zichtbare uitvoer verschijnt; een klein
  budget zou het antwoord afbreken.
- **`suggestion_count`**, standaard `3`. Het aantal aan te vragen
  alternatieven voor titel en beschrijving.
- **`retry`**: automatische herhaalpogingen voor uitsluitend
  *tijdelijke* fouten; zie [Antwoordverwerking](#how-replies-are-handled).

## In Filament {#in-filament}

Met de optionele Filament-pakketten geïnstalleerd (`rankbeam/laravel-seo-filament` >= 1.1)
voegt het inschakelen van AI-assistentie het volgende toe:

- **Voorstellen met AI** bij de SEO-titel en beschrijving van elke resource
  die de SEO-sectie op bewerkpagina's gebruikt. Het venster toont alternatieven
  met tekentellingen. Een alternatief kiezen vult het veld in voor beoordeling.
- **Uitleggen (AI)** in de problementabel van het dashboard: een korte,
  begrijpelijke uitleg van het probleem en de concrete oplossing.
- **Description herschrijven (AI)** (beschrijving herschrijven) in de problementabel, naast Uitleggen.
  Dit stelt één verbeterde metabeschrijving voor, altijd binnen het
  beschrijvingsbudget van de pagina: 160 tekens voor Latijnse tekst en circa
  80 voor CJK volgens het [lengtebeleid](/nl/guide/multilingual#title-and-description-budgets-per-script)
  van de core. Beoordeel hem in het venster. **Herschrijving toepassen**
  schrijft hem naar het `seo_meta`-record van de pagina. Tot die tijd
  wordt niets geschreven.
- **Gestructureerde data voorstellen (AI)** in de problementabel.
  Dit stelt het geschiktste schema.org-type voor uitgebreide zoekresultaten
  voor — Product, Article of Breadcrumb — en toont de opgebouwde JSON-LD.
  **Gestructureerde data toepassen** voegt die toe aan
  `seo_meta.schema_jsonld` van de pagina. De optionele [editor voor gestructureerde
  gegevens](../guide/filament#structured-data-schema-org) beheert dezelfde
  kolom, zodat de gegevens daar bewerkbaar blijven. Een onvolledige suggestie,
  bijvoorbeeld een Article zonder auteur of afbeelding, toont de ontbrekende
  velden en wordt **niet** toegepast.

De laatste twee zijn begrensde oplossingen; zie [Begrensde oplossingen](#bounded-fixes-propose-never-auto-apply).

## Begrensde oplossingen: voorstellen, nooit automatisch toepassen {#bounded-fixes-propose-never-auto-apply}

Twee acties gaan een stap verder dan een suggestielijst: ze leveren één
*begrensde* waarde op die je met één klik kunt toepassen. Beide doen nog steeds
**alleen een voorstel**. Er wordt niets opgeslagen voordat je expliciet accepteert.

- **Beschrijving herschrijven** (`SeoSuggestionService::rewriteDescription($model, $issue?)`) geeft één metabeschrijving
  terug, **altijd binnen het core-budget voor het schrift van de pagina:
  160 voor Latijns, circa 80 voor CJK**. Dit is hetzelfde budget als in de
  prompts voor titel- en beschrijvingssuggesties, gekozen op basis van de
  eigen bepaalde waarde van de pagina. Bij overschrijding kort deterministische
  code de tekst in op een zinsgrens, of anders een woordgrens. Een geaccepteerde
  herschrijving kan daardoor zelf nooit de waarschuwing `description_too_long`
  veroorzaken. Het meegegeven scanprobleem stuurt de herschrijving, bijvoorbeeld
  *te lang* tegenover *ontbrekend*.
- **Gestructureerde gegevens voorstellen** (`SeoSuggestionService::suggestSchemaType($model)`) vraagt het model
  alleen om **een typeadvies en waarden voor eindvelden**, nooit ruwe JSON-LD.
  Deterministische code bouwt daarna het document op met de core-schemabouwers
  `ProductSchema` / `ArticleSchema` / `BreadcrumbSchema` en valideert het met
  `SchemaValidator` uit de core. Een verzonnen `@type`,
  `@context` of structuur kan zo de pagina niet bereiken. Ontbreekt een
  verplicht veld, dan wordt het document als *onvolledig* getoond en niet
  toegepast. Tijdens een live test stelde één provider een `Article`
  voor bij een pagina met weinig inhoud. Dat werd terecht tegengehouden door
  een ontbrekende auteur en afbeelding; andere providers stelden geen type voor.

## Zonder paneel {#headless}

Dezelfde mogelijkheden als JSON, voor scripts en apps zonder Filament:

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

De uitvoer bevat de suggesties, of het aanbevolen type, de opgebouwde JSON-LD
en de validatiestatus, plus het gebruikte model en het **tokenverbruik per
verzoek**: invoer, uitvoer en redeneren. Dat helpt kosten berekenen met de
tarieven van je provider; tokentellingen zijn geen factuur. Bij elke fout
sluit het commando af met een niet-nulstatus en de fout in het JSON-antwoord.
Net als de andere assistentiefuncties doet `seo-pro:suggest-schema` **alleen een
voorstel**: het drukt het document af en schrijft niets weg.

## Ontbrekende metadata in bulk aanvullen {#bulk-fill-missing-metadata}

**Pro 2.42 verandert de CLI-standaard:** `seo-pro:ai-fill` slaat per
gegenereerd veld één privéconcept op. Gepubliceerde SEO-metadata blijven
ongewijzigd tot goedkeuring. Bestaande waarden en berekende terugvalwaarden
worden overgeslagen. Een actueel openstaand concept wordt hergebruikt in
plaats van opnieuw gegenereerd.

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review` toont de eerste 100 openstaande concepten als JSON.
Inspecteer een ID om de waarde en het privébewijs te lezen. Goedkeuren en
afwijzen werken ook met AI uitgeschakeld en doen geen provideraanroepen.
Goedkeuring vereist een gebruikerslabel en weigert concepten waarvan het
bronrecord of de doelmetadata zijn gewijzigd, of het record is verwijderd.
Het label registreert de opgegeven identiteit van de gebruiker; het bewijst
geen inhoudelijke menselijke beoordeling. Gebruik `--connection=NAME` voor een
ingestelde database die niet de standaard is.

`--auto-apply` herstelt expliciet het direct publiceren van nog ontbrekende
velden. `--force` slaat de bevestigingsvraag over, **niet de beoordeling**.
`--dry-run` genereert waarden en drukt ze af zonder concepten of metadata
op te slaan; het roept nog steeds de provider aan en kan geld kosten.
`--field`, `--limit` en `--locale` begrenzen de generatie.
Werk ingeplande commando's na een upgrade bewust bij.

### Op schaal: tempo, kostenschatting en hervatten na uitval {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms` is standaard 200 milliseconden. Bij `confirm_over`,
standaard 100 records, toont het commando de schatting uit `seo-pro.ai.pricing`
en vraagt het bevestiging vóór generatie. Checkpoints bewaren voltooide velden
bij onderbrekingen. Een time-out nadat de provider het verzoek heeft aanvaard,
kan nog steeds dubbele kosten veroorzaken; stem onzeker werk af voordat je
`--fresh` gebruikt. Laat maar één overeenkomende bulktaak tegelijk draaien.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

Geef voor eigen integraties `review: true` door om concepten klaar te zetten.
De onderliggende PHP-API behoudt `apply: true, review: false` voor compatibiliteit,
waardoor bestaande aanroepen nog steeds direct schrijven. `apply: false`
toont een voorbeeld zonder iets op te slaan. De samenvattingssleutel
`filled` telt afgehandelde records, inclusief klaargezette concepten
in beoordelingsmodus. De CLI noemt ze `staged`.

### Batchmodus (50% goedkoper) {#batch-mode-50-cheaper}

`--batch` gebruikt het ondersteunde asynchrone endpoint van Anthropic
of OpenAI. Hun gedocumenteerde korting is in de schatting verwerkt; controleer
wel de actuele modelprijzen. Google- en lokale adapters vallen terug op
sequentiële generatie. Dien de batch in en voer later hetzelfde commando
opnieuw uit om de concepten op te halen:

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

Houd provider, locale en publicatiemodus gelijk tussen indienen en ophalen.
Beoordelingsmodus en `--auto-apply` gebruiken aparte checkpoints. De CLI
weigert de andere modus te starten zolang een overeenkomende batch openstaat.
Een onzekere inzending stopt voor afstemming. Gedeeltelijk geslaagde resultaten
blijven bewaard; tijdelijke fouten kunnen opnieuw worden geprobeerd. Ophalen
controleert ontbrekende velden opnieuw. Goedkeuring controleert ook de
bronmomentopname van vóór het indienen. `seo-pro.ai.fill.batch.request_timeout` is standaard
120 seconden. Ingepland ophalen slaat standaard concepten op.

## Herkomstregistratie, migraties en gegevensfiltering {#origin-review-and-filtering}

Vereist **Core 3.21 en Pro 2.42**. De core laadt zijn migratie automatisch.
Publiceer de Pro-migraties en voer ze uit op elke database die SEO-modellen
gebruiken voordat je suggesties met opslag genereert:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

De privétabel `seo_ai_proposals` bewaart gegenereerde waarden en bewijs over
provider, model en verzoek met versleutelde Laravel-casts. Bewaar
`APP_KEY` en de back-up veilig: bij verlies worden deze waarden
onleesbaar. Recordidentificaties, status en beslissingsmetadata blijven
gewone databasekolommen. Formulieralternatieven kunnen op `offered`
of `selected` blijven staan wanneer iemand een formulier verlaat.
Er is geen automatische opschoning. Stel een bewaarbeleid in de applicatie in,
behoud openstaande concepten en bewijs waar `seo_meta.ai_provenance` nog naar verwijst,
en beperk toegang tot database-exports en uitvoer van beoordelingscommando's.

Geaccepteerde formuliersuggesties, dashboardoplossingen en bulkwaarden krijgen
een herkomst per veld. Latere Eloquent-wijzigingen behouden `origin: ai`
en zetten `edited: true`. Dat betekent dat de waarde is veranderd, niet
dat een mens hem heeft geverifieerd. Het veld leegmaken verwijdert de markering.
De HTML-, array-, JSON- en Inertia-uitvoer van de core toont alleen veldnaam,
herkomst en bewerkingsstatus, waar van toepassing via de eigen
`rankbeam:ai-origin`-metatag. Generatie-ID's en providerdetails blijven privé.
Stel ruwe `SEOMeta`-modellen niet beschikbaar via een publieke API.

Dit registreert toekomstige ondersteunde opslagpaden, geen historische inhoud
of elke bewerkingsversie. Directe SQL, querybuilder-updates en eigen renderers
kunnen deze controles omzeilen. Stel de herkomst expliciet opnieuw in als een
onafhankelijk geschreven vervanging dat rechtvaardigt; gewone bewerkingen
behouden de herkomst. De eigen markering is geen gestandaardiseerd watermerk,
manipulatiebestendige toeschrijving of claim van naleving van artikel 50.
Werkelijke providerkwaliteit en providereigen markeringen vereisen afzonderlijke evaluatie.

Je kunt `AiPromptFilter` implementeren en `seo-pro.ai.context_filter` instellen.
Dit filtert de samengestelde gebruikersprompt vóór een synchrone of
batchinzending. Bij een fout wordt niets ingediend en verschijnt een opgeschoonde
foutmelding. Systeeminstructies blijven ongewijzigd. De standaard is
`null`, **zonder automatische verwijdering van gevoelige gegevens**.
Dit voorbeeld vervangt slechts één bekende waarde. Implementeer en test regels
die bij jouw applicatie passen:

```php
namespace App\Support;

use Rankbeam\Seo\Pro\Ai\AiPromptFilter;

final class RedactAiContext implements AiPromptFilter
{
    public function filter(string $prompt): string
    {
        return str_replace('internal@example.com', '[redacted]', $prompt);
    }
}

// Configure seo-pro.ai.context_filter with this class in config/seo-pro.php.
// Runtime equivalent:
config(['seo-pro.ai.context_filter' => RedactAiContext::class]);
```

## Antwoordverwerking {#how-replies-are-handled}

Elke aanroep geeft één provideronafhankelijk antwoordformaat terug, zodat het
gedrag gelijk is voor alle providers, ook later toegevoegde:

- **Gestructureerde uitvoer waar de provider dit ondersteunt.** OpenAI met
  native Structured Outputs, Google met Gemini `responseSchema` en Anthropic
  met `output_config.format` laten de API de JSON-vorm afdwingen. Ongeldige JSON
  wordt als fout afgehandeld, nooit via tekstextractie gerepareerd. Een lokale
  of OpenAI-compatibele server krijgt hetzelfde verzoek via `response_format`,
  naar beste vermogen. Als de server dit veld negeert, kan hij nog bruikbare
  tekst teruggeven die als terugvaloptie tolerant wordt geparseerd. Je krijgt
  een geldige lijst of een duidelijke fout, nooit een half geparseerd antwoord.
- **Afgebroken uitvoer geeft een expliciete fout met een vervolgstap.** Als een
  antwoord de uitvoertokenlimiet bereikt, krijg je `truncated` met de
  instructie `seo-pro.ai.max_output_tokens` te verhogen, niet ongemerkt een ingekorte titel.
  Dit komt vooral voor bij **redeneer- of thinking-modellen**, die automatisch
  de hogere ondergrens `reasoning_min_output_tokens` krijgen.
- **Tijdelijke fouten worden automatisch opnieuw geprobeerd.** Een
  `429`-verzoeklimiet of `5xx` leidt tot nieuwe pogingen
  met begrensd exponentieel oplopende wachttijd. Een aanwezige
  `Retry-After`-header wordt gevolgd binnen grenzen, zodat een kwaadwillige
  waarde het verzoek niet kan stilleggen. **Deterministische** fouten worden
  **niet** opnieuw geprobeerd: een verkeerde sleutel, ongeldig verzoek, te
  grote inhoud, **time-out** of **opgebruikt tegoed of quotum**. Herhalen
  zonder tegoed kost alleen wachttijd. Stel herhaalpogingen in via
  `retry`; zet `max_attempts` op `0` om ze uit te schakelen.
- **Fouten hebben een type en worden opgeschoond.** Elke fout heeft een
  stabiele code, zoals `unauthorized`, `quota_exceeded`, `rate_limited`,
  `timeout`, `content_too_large`, `bad_request`, `truncated`,
  `content_filtered` of `provider_error`, en tijdelijke fouten hebben een
  `retryable`-markering. De melding is kort en opgeschoond. **Het normale
  applicatiepad toont of logt geen ruwe providerantwoorden; het optionele
  evaluatiehulpmiddel hierboven bewaart ze wel als bewijs.** In Filament
  verschijnt de fout in het opgemaakte vensteronderdeel, met een passende
  vervolgstap voor veelvoorkomende gevallen; zie [Problemen oplossen](#troubleshooting).

## Problemen oplossen {#troubleshooting}

Elke fout verschijnt in de interface en is niet-fataal, met een typecode en
opgeschoonde melding. De gebruikelijke gevallen en hun oplossing:

| Symptoom (foutcode) | Betekenis | Oplossing |
|---|---|---|
| **`quota_exceeded`** — *'het provideraccount heeft geen tegoed of quotum meer'* | De sleutel is geldig, maar het **API-account heeft geen tegoed of quotum**. Dit is geen verzoeklimiet; opnieuw proberen helpt niet. Providers melden dit verschillend: Anthropic 'credit balance is too low', OpenAI 'exceeded your current quota… check your plan and billing' (`insufficient_quota`), Google 'prepayment credits are depleted'. | Voeg tegoed toe of schakel facturering in bij de provider, of kies een **lokaal** model zonder API-kosten van een provider. Een Claude- of ChatGPT-**abonnement** betaalt niet voor de **API**. |
| **`unauthorized`** — *'authenticatie mislukt'* | De sleutel ontbreekt, is verkeerd of is niet geldig voor de ingestelde provider. | Controleer de sleutel in de omgevingsvariabele die `seo-pro.ai.api_key_env` noemt, standaard `SEO_PRO_AI_API_KEY`: aanwezig, actueel en passend bij `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`** — *'de verzoeklimiet van de provider is bereikt'* | Een echte **tijdelijke** verzoeklimiet; er is eerst automatisch opnieuw geprobeerd. | Wacht en probeer opnieuw, of kies een **lokaal** model binnen de capaciteit van je server. Verhoog `seo-pro.ai.fill.throttle_ms` voor bulkuitvoeringen met een laag gebruikslimietniveau. |
| **`timeout`** — *'het verzoek heeft een time-out bereikt'* | De provider antwoordde niet binnen `seo-pro.ai.timeout`, standaard 15 seconden. Gebruikelijk bij een **traag lokaal redeneermodel**. | Verhoog de tijd met `SEO_PRO_AI_TIMEOUT`; stel voor Ollama ook `['think' => false]` in via `seo-pro.ai.local.extra_body`. |
| **`truncated`** — *'de limiet max_output_tokens is bereikt'* | Het antwoord bereikte zijn uitvoerbudget, mogelijk inclusief verborgen redeneertokens. | Verhoog `seo-pro.ai.max_output_tokens`; redeneermodellen kunnen 2000+ nodig hebben. Of controleer of het model op een `reasoning_models`-patroon past, zodat de ondergrens geldt. |
| **`content_too_large`** (HTTP 413) | De verstuurde pagina-inhoud overschreed de providerlimiet. | Verlaag `seo-pro.ai.max_input_chars` om een korter fragment te versturen. |
| **`bad_request`** | Een ongeldig verzoek, meestal een **modelnaam** waartoe het account geen toegang heeft, of een niet-ondersteunde parameter. | Controleer of `SEO_PRO_AI_MODEL` een model is dat je sleutel of server bij de ingestelde provider kan bereiken. |
| **`content_filtered`** | Het veiligheidsfilter van de provider weigerde te antwoorden. | Beoordeel de inhoud en de richtlijnen van de provider; herhaal het geweigerde verzoek niet automatisch. |

::: tip Lokale inferentie vereist nog steeds een werkende server
`SEO_PRO_AI_PROVIDER=local` met zelf gehoste inferentie vermijdt tegoedproblemen bij
cloudproviders. Hardware, model, API-compatibiliteit, time-out en capaciteit
blijven van belang. Een externe gateway met deze adapter kan een sleutel en
betaling vereisen.
:::

## Welke gegevens je server verlaten {#what-leaves-your-server}

Alleen het volgende gaat naar je ingestelde provider, uitsluitend na een
expliciete actie: een aangeklikte actie of uitgevoerd commando.

- *Suggesties*: de korte klassenaam en sleutel van het model, bijvoorbeeld
  'Post #3', de bepaalde titel en beschrijving, de canonieke URL en een
  inhoudsfragment als platte tekst zonder HTML, begrensd door `max_input_chars`,
  standaard 6000 tekens.
- *Probleemuitleg*: probleemtype, ernst, veld, melding en doel-URL, plus,
  wanneer een model beschikbaar is, de klasse en sleutel van dat model,
  bepaalde titel en beschrijving, canonieke URL en een begrensd fragment
  van de inhoud als platte tekst.
- *Beschrijving herschrijven*: dezelfde minimale paginacontext als bij
  suggesties, plus het type en de melding van het scanprobleem als dat is meegegeven.
- *Gestructureerde gegevens voorstellen*: dezelfde minimale paginacontext
  als bij suggesties. Het model geeft alleen een type en waarden voor
  eindvelden terug; JSON-LD wordt lokaal opgebouwd.

Het pakket verzamelt niet bewust bezoekersgegevens, IP-adressen,
verzoekheaders of inloggegevens voor prompts en verstuurt geen volledige HTML.
**Je inhoudsvelden en fragmenten kunnen zelf gevoelige informatie bevatten**;
controleer wat je applicatie beschikbaar stelt. De providersleutel wordt
gebruikt om het verzoek te authenticeren. SECURITY.md in de Pro-repository
is de referentie voor gegevensverwerking.

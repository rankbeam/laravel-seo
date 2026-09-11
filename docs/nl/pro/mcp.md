---
description: "Een stdio-MCP-server zonder extra afhankelijkheden waarmee een AI-assistent de SEO van een Laravel-site kan lezen en, met toestemming, bewerken. Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5."
---

# MCP-server {#mcp-server}

Met de Rankbeam MCP-server kan een AI-assistent **de SEO van een site lezen
en optioneel bewerken** via het [Model Context Protocol](https://modelcontextprotocol.io).
Verbind een MCP-client, zoals Claude Code, Claude Desktop, Cursor of Codex,
met je Laravel-app. Die kan dan de metadata van een pagina bepalen, een audit
uitvoeren, de Pro-score lezen, je AI-crawlerbeleid bekijken en, als je dat
toestaat, SEO-gegevens terugschrijven.

Het is een zelfstandige stdio-server **zonder extra afhankelijkheden**:
geen SDK of nieuwe pakketten. Hij draait op PHP 8.2–8.4 met Laravel 11,
PHP 8.2–8.5 met Laravel 12 en PHP 8.3–8.5 met Laravel 13.

::: tip Pro-functie
De MCP-server wordt meegeleverd met `rankbeam/laravel-seo-pro`. Hij is **standaard
alleen-lezen**. Bewerken vereist een expliciete configuratie-instelling
en een lijst met toegestane modellen.
:::

## Wat de assistent kan doen {#what-the-assistant-can-do}

### Analysetools (altijd beschikbaar) {#analysis-tools-always-available}

| Tool | Werking |
| --- | --- |
| `seo_resolve` | De volledig bepaalde SEO-metadata voor een modelrecord: titel, beschrijving, canonical, robots, Open Graph en JSON-LD. Dit is wat de pagina daadwerkelijk zou renderen. |
| `seo_audit` | De [metadata-audit](/nl/guide/audit) binnen het proces voor een modelrecord of de eerste N records: dezelfde `seo:audit`-controles, direct en zonder wachtrij. |
| `seo_score` | De laatst opgeslagen [Pro SEO-score](/nl/pro/scoring) voor een modelrecord: 0–100 en een letterbeoordeling. |
| `seo_robots_directives` | De beheerde [robots.txt-instructies voor AI-crawlers](/nl/guide/ai-crawlers) en het bepaalde toestaan-/weigerenbeleid per bot. |
| `validate_schema` | Een JSON-LD-object, of de bepaalde schemagraaf van een toegestaan model, valideren met de core-validator voor gestructureerde gegevens: vereisten voor uitgebreide Google-zoekresultaten per `@type`. |
| `analyze_robots` | De geldende beslissing toestaan/weigeren **per bekende AI-crawler**, plus de bepalende bron: een botspecifieke instelling, doelbeleid of standaard. Het beleid geldt voor de hele site. |
| `debug_social_share` | De bepaalde Open Graph- en Twitter-kaart die een sociale crawler voor een modelrecord zou zien, inclusief terugvalwaarden, met adviezen over de kaartkwaliteit. |
| `check_meta` | Een gericht metadata-overzicht voor één modelrecord: bepaalde titel, beschrijving, canonical, robots en og:image met lengtes en aanwezigheid, plus auditproblemen. |

### Tools voor site-inhoud (altijd beschikbaar) {#site-content-tools-always-available}

Deze tools maken de assistent bewust van de inhoud van je site: hij kan
pagina's opsommen en doorzoeken.

| Tool | Werking |
| --- | --- |
| `list_pages` | SEO-beheerde pagina's of records van een **toegestaan** model opsommen, elk met URL en effectieve titel. Ondersteunt paginering met `limit`/`offset`. |
| `search_pages` | Zoeken in de tekst van pagina's van een **toegestaan** model. Gebruikt Laravel [Scout](https://laravel.com/docs/scout) als het model doorzoekbaar is, anders een veilige SQL-`LIKE` op title/name/headline en gekoppelde SEO-metadata. Geeft per resultaat URL, titel en een fragment terug. |

### Beheertools (expliciet inschakelen) {#ops-tools-opt-in}

Operationele tools die de scanstatus lezen en siteconfiguratie wijzigen.
Net als de bewerktool vereisen ze **`allow_edits`**. Op een server die
alleen-lezen is, de standaard, zijn ze onzichtbaar en inactief.

| Tool | Werking |
| --- | --- |
| `list_issues` | De huidige openstaande SEO-scanproblemen, bewaard over uitvoeringen heen, en de hoofdgegevens van de laatste [scan](/nl/pro/scan-issues). Filteren met `severity` / `type`. |
| `trigger_scan` | Een gerichte scan van één toegestaan record starten en de uitvoering teruggeven, of een volledige scan van alle doelen starten. Standaard via de wachtrij, synchroon met `sync: true`. |
| `create_redirect` | Een redirectregel maken: bronpad of regex → doel, met status `301`/`302`/`307`/`308`/`410`, via de bestaande validators van het redirectmodel. |

### Bewerktool (expliciet inschakelen) {#edit-tool-opt-in}

| Tool | Werking |
| --- | --- |
| `seo_save_meta` | SEO-metadata — titel, beschrijving, canonical, robots, OG, Twitter en JSON-LD — naar een **toegestaan** modelrecord schrijven via `saveSEO()`. |

De beheertools en `seo_save_meta` worden **niet vermeld in `tools/list`
en zijn niet uitvoerbaar** zolang je bewerken niet inschakelt. Zie
[Beveiliging](#security). Een server die alleen-lezen is, vertelt de
assistent zelfs niet dat die tools bestaan.

## Een AI-client verbinden {#wiring-an-ai-client}

De server spreekt JSON-RPC via **stdio**: de client start een
Artisan-commando en communiceert ermee via de proceskanalen. Registreer de
server bij de clients die je gebruikt; dezelfde server werkt voor allemaal.

::: tip Eén commando voor elke client
Elke client hieronder voert hetzelfde startcommando uit: `php artisan seo-pro:mcp`,
gestart **vanuit de hoofdmap van je app**, zodat Artisan de app kan laden.
Staat `php` niet in de `PATH` van de client, zoals vaak
op Windows of bij grafische apps die de shellomgeving niet overnemen,
geef dan het **absolute pad naar zowel `php` als `artisan`**.
Artisan start vanuit de map van het `artisan`-script zelf, dus een
`cwd` is niet nodig.
:::

### Claude Code (CLI) {#claude-code-cli}

Eén commando registreert de server. Voer het **vanuit de hoofdmap van je app** uit:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Controleer de verbinding:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

Gebruik op Windows of met Laravel Herd absolute paden, zodat de startmap niet uitmaakt:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Bewerk het configuratiebestand via **Settings → Developer → Edit Config**
(Instellingen → Ontwikkelaar → Configuratie bewerken), of open het rechtstreeks:

- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

Gebruik op **Windows** het absolute pad naar `php.exe` en schrijf
elke backslash dubbel in JSON:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "C:\\Users\\you\\.config\\herd\\bin\\php84\\php.exe",
      "args": ["C:\\path\\to\\app\\artisan", "seo-pro:mcp"]
    }
  }
}
```

Sluit Claude Desktop volledig af en open het opnieuw. De tools verschijnen
achter het tool-/stekkerpictogram in de berichtenbalk.

### Cursor {#cursor}

Maak `.cursor/mcp.json` in je project, of `~/.cursor/mcp.json` voor alle projecten.
Gebruik het absolute `artisan`-pad zodat de server vanuit elke map kan starten:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

Gebruik op Windows het absolute pad naar `php.exe` en dubbele
backslashes, net als in het Claude Desktop-voorbeeld hierboven.
Schakel de server in via **Settings → MCP** (Instellingen → MCP).

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Geef op Windows/Herd absolute paden voor `php` en
`artisan` door, net als bij Claude Code.

De assistent kan nu de tools aanroepen.

## Een uitgewerkt gesprek {#a-worked-conversation}

Hieronder staat een echt gesprek met een demo-app, een site van een medisch
instituut met 900 pagina's. De werkelijke tooluitvoer is verkort weergegeven.
De twee toegestane leesmodellen zijn `App\Models\Page` en `App\Models\PublicPage`,
en `allow_edits` staat aan.

> **Jij:** Hoe staat het met de SEO van de pagina 'Accoglienza' (Page id 1)?
> Los duidelijke problemen op.

De assistent voert eerst een **audit** uit met `seo_audit { model: "App\\Models\\Page", id: 1 }`:

```json
[
  {
    "id": 1,
    "url": "http://localhost/accoglienza",
    "issue_count": 2,
    "issues": [
      { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 17 characters (recommended min 30).", "context": { "length": 17, "min": 30 } },
      { "code": "description_too_short", "severity": "notice", "field": "description", "message": "Description is only 11 characters (recommended min 70).", "context": { "length": 11, "min": 70 } }
    ]
  }
]
```

Hij **bepaalt** de actuele metadata met `seo_resolve { model: "App\\Models\\Page", id: 1 }` om te zien wat
daadwerkelijk wordt gerenderd. De titel heeft 17 tekens en de beschrijving
11, beide onder de aanbevolen minima. Daarom **schrijft hij een correctie**
met `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

De tool bevestigt het schrijven en geeft het opnieuw bepaalde record terug:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Ten slotte voert de assistent **opnieuw een audit** uit om de correctie te
controleren. `issue_count` is nu `0`:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Assistent:** Klaar. De titel van 17 tekens en beschrijving van 11 tekens
> zaten beide onder de aanbevolen minima. Ik heb een volledigere,
> beschrijvende titel en beschrijving ingesteld. De nieuwe audit meldt
> geen problemen meer (0 problemen).

Het schrijven ging via dezelfde `saveSEO()` als elke programmatische
opslagactie. De waarde wordt dus op dezelfde manier opgeslagen en bepaald
als een waarde uit code, via mass assignment met de invulbare velden en
casts van het model. De lengtevalidators van het Filament-formulier worden
**niet** uitgevoerd. De herhaalde audit bevestigt dus alleen zijn
automatische controles. Redactionele juistheid vereist nog beoordeling;
een audit zonder meldingen certificeert geen tekstkwaliteit.

### Vragen stellen over de inhoud van je site {#talking-to-your-site-s-content}

De tools voor site-inhoud laten de assistent vragen **over de site zelf**
beantwoorden, naast vragen over een genoemd record.

> **Jij:** Welke pagina's noemen 'immunoterapia', en zijn hun titels in orde?

De assistent **zoekt** met `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

```json
{
  "model": "App\\Models\\Page",
  "query": "immunoterapia",
  "driver": "like",
  "count": 3,
  "results": [
    { "id": 42, "url": "http://localhost/ricerca/immunoterapia-oncologica", "title": "Immunoterapia oncologica", "snippet": "Il programma di immunoterapia dell'IDI: terapie, sperimentazioni e accesso…" },
    { "id": 87, "url": "http://localhost/reparti/dermatologia-oncologica", "title": "Dermatologia oncologica", "snippet": "…include protocolli di immunoterapia per i tumori cutanei avanzati…" },
    { "id": 91, "url": "http://localhost/news/nuova-immunoterapia-2026", "title": "Nuova", "snippet": "Al via una nuova immunoterapia…" }
  ]
}
```

De derde titel is alleen 'Nuova'. Hij **controleert de metadata van die
pagina** met `check_meta { model: "App\\Models\\Page", id: 91 }`:

```json
{
  "url": "http://localhost/news/nuova-immunoterapia-2026",
  "title": { "value": "Nuova", "length": 5, "present": true },
  "description": { "value": null, "length": 0, "present": false },
  "og_image": { "value": null, "present": false },
  "issue_count": 2,
  "issues": [
    { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 5 characters (recommended min 30)." },
    { "code": "missing_description", "severity": "warning", "field": "description", "message": "No meta description is set." }
  ]
}
```

> **Assistent:** Drie pagina's noemen 'immunoterapia'. Twee lijken goed,
> maar **/news/nuova-immunoterapia-2026** heeft een titel van 5 tekens,
> 'Nuova', en geen metabeschrijving. Wil je dat ik een volledigere titel
> en beschrijving schrijf?

Met `allow_edits` aan kan hij dit direct aanpassen via `seo_save_meta`.
In alleen-lezenmodus blijft het bij een aanbeveling. `search_pages`
gebruikte hier de SQL-`LIKE`-terugvaloptie (`"driver": "like"`),
omdat het model niet door Scout is geïndexeerd. Voeg [Laravel Scout](https://laravel.com/docs/scout)
toe en dezelfde tool doorzoekt automatisch je zoekengine.

## Beveiliging {#security}

Drie lagen beveiligen de server standaard. Alle drie zijn actief in de
standaardmodus alleen-lezen; je versoepelt ze bewust.

### 1. Bewerken vereist inschakeling (standaard uit) {#_1-edits-are-gated-off-by-default}

De schrijftool blijft onzichtbaar en inactief totdat je een instelling aanzet:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

Met `allow_edits` uit, de standaard, wordt `seo_save_meta` **niet
teruggegeven door `tools/list`**. Een `tools/call` ervoor mislukt met
JSON-RPC-fout `-32602`. De assistent kan niet schrijven en kan zelfs
niet ontdekken dat die mogelijkheid bestaat. Schakel dit alleen in voor
een client en database die je vertrouwt.

### 2. De lijst met toegestane modellen {#_2-the-model-allowlist}

Elke modelgebonden tool, voor lezen *of* schrijven, kan alleen een
`HasSEO`-model op de toestemmingslijst benaderen. Een AI-client kan
nooit een willekeurige klasse opgeven, zoals een `User` of facturatiemodel:

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Bij een niet-toegestane klasse krijgt de assistent een leesbaar foutresultaat
(`Model [App\Models\User] is not in the MCP allowlist`). De klasse wordt niet benaderd. Als `models` leeg
is, wordt teruggevallen op je ingestelde `seo.audit.models` / `seo.sitemap.models`.
MCP krijgt daardoor precies hetzelfde modelbereik als de rest van het pakket,
nooit een ruimer bereik.

### 3. Alleen stdio: geen netwerktoegang tot de server {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

De server communiceert **alleen via stdio**. De client start het proces
en stuurt JSON-RPC via de in- en uitvoerkanalen. Er is **geen HTTP-listener,
poort of socket**: niets dat vanaf een andere machine bereikbaar is,
en geen extern toegangspunt waarvoor authenticatie nodig is. STDOUT bevat
alleen protocolverkeer; diagnostiek gaat naar STDERR en wordt door je client
gelogd. Claude Desktop schrijft dit bijvoorbeeld naar `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`.
Een losse logregel kan daardoor de protocolstroom niet beschadigen.

::: warning Behandel ingeschakeld bewerken als schrijftoegang tot je database
`allow_edits` laat een verbonden assistent SEO-rijen wijzigen in de
database waarop het commando draait. Gebruik lokaal of staging tijdens
experimenten, houd de modellenlijst beperkt en schakel bewerken daarna weer
uit. De hoofdschakelaar `'enabled' => false` verhindert dat het commando start.
Lokaal stdio-verkeer verhindert niet dat de AI-client toolresultaten naar
zijn eigen provider stuurt. Bekijk daarom ook de gegevensinstellingen van die client.
:::

## Configuratie {#configuration}

```php
// config/seo-pro.php
'mcp' => [
    'enabled'     => true,           // master switch; the command refuses to run when false
    'allow_edits' => false,          // expose + permit the ops tools + seo_save_meta
    'models'      => [],             // allowlist; [] = fall back to audit/sitemap models
    'server_name' => 'rankbeam-seo', // reported in the MCP initialize handshake

    // Optional Server Card discovery route (off by default) — see below.
    'server_card' => [
        'enabled'     => false,      // serve GET {path} with the discovery card
        'path'        => '.well-known/mcp/server-card.json',
        'name'        => null,       // reverse-DNS server name (null = derived from app.url)
        'schema_url'  => 'https://modelcontextprotocol.io/schemas/draft/server-card.json',
        'website_url' => null,       // optional homepage/docs URL stamped on the card
    ],
],
```

## Server Card voor ontdekking: experimentele conceptspecificatie {#server-card-discovery-—-experimental-draft-spec}

Een MCP **Server Card** is een klein JSON-document op een vaste, bekende
URL waarmee een client vóór verbinding een server kan ontdekken: naam,
versie en mogelijkheden. Rankbeam kan er een voor je site serveren om
agenttools te laten weten dat de site een MCP-server heeft. De functie
staat **standaard uit** en is aanvullend; inschakelen verandert verder niets.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Na inschakelen geeft `GET /.well-known/mcp/server-card.json` een kaart als deze terug:

```json
{
  "$schema": "https://modelcontextprotocol.io/schemas/draft/server-card.json",
  "name": "com.example/rankbeam-seo",
  "version": "1.0.0",
  "title": "Rankbeam SEO MCP server",
  "description": "Read — and optionally edit — this site's SEO over the Model Context Protocol…",
  "_meta": {
    "io.rankbeam.seo/transport": "stdio",
    "io.rankbeam.seo/launch": "php artisan seo-pro:mcp",
    "io.rankbeam.seo/tool_count": 10,
    "io.rankbeam.seo/tools": [ { "name": "seo_resolve", "description": "…" } ]
  }
}
```

De kaart noemt alleen **momenteel ingeschakelde** tools. Een alleen-lezenserver
maakt via de kaart dus geen beheer- of bewerktools bekend.

::: warning Volgt een conceptspecificatie
Dit volgt het **conceptvoorstel** voor MCP Server Card,
[SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127),
op 10 september 2026 nog open en niet gemerged. Het bekende pad, de
`$schema`-URL en de precieze velden zijn **niet definitief**.
Daarom zijn ze instelbaar via `path`, `schema_url`,
`name` en `website_url`. Deze server gebruikt **stdio**
(`php artisan seo-pro:mcp`), dus de kaart bevat geen HTTP-`remotes`-blok.
Het is een aanwijzing voor ontdekking, geen HTTP-endpoint om verbinding
mee te maken. Controleer pad en structuur tegen je client voordat je erop
vertrouwt, en laat de functie uit als je hem niet nodig hebt.
:::

## Protocolnotities {#protocol-notes}

Een MCP-server met alleen tools is een kleine JSON-RPC 2.0-interface.
Deze server implementeert die rechtstreeks: `initialize` voor
versieonderhandeling en uitwisseling van mogelijkheden, `tools/list`,
`tools/call` en `ping`. Hij meldt protocolversie
`2025-06-18` en begrijpt ook `2025-03-26` en `2024-11-05`.
Onbekende methoden krijgen `-32601` en ongeldige regels
`-32700`. Een **toolfout** verschijnt als een leesbaar
`isError`-resultaat, niet als transportfout. Meldingen zonder
`id`, zoals `notifications/initialized`, krijgen terecht geen antwoord.

## Zonder paneel en uitbreiden {#headless-extending}

`SeoPro::mcp()` geeft het toolregister terug. Je kunt daarmee de
beschikbare tools inspecteren of eigen tools registreren:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Een eigen tool implementeert `McpTool` met `name`,
`description`, `inputSchema`, `isEnabled` en `handle`.
Breid `AbstractTool` uit om de modelcontrole op basis van de
toestemmingslijst te hergebruiken. Zo erft je tool dezelfde
beveiligingswaarborgen als de ingebouwde tools.

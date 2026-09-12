---
description: "Server MCP přes stdio bez dalších závislostí umožňuje asistentovi AI číst a po povolení upravovat SEO Laravelu. Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5."
---

# Server MCP {#mcp-server}

Server MCP Rankbeamu umožňuje asistentovi AI **číst a volitelně upravovat SEO webu** přes [Model Context Protocol](https://modelcontextprotocol.io). Připojte klienta MCP, například Claude Code / Claude Desktop, Cursor nebo Codex, ke své aplikaci Laravel. Může vyhodnotit metadata stránky, spustit audit, přečíst skóre Pro, zobrazit pravidla pro roboty AI a po vašem povolení zapsat SEO zpět.

Jde o samostatný server stdio **bez dalších závislostí**, SDK nebo nových balíčků. Běží na PHP 8.2–8.4 s Laravelem 11, PHP 8.2–8.5 s Laravelem 12 a PHP 8.3–8.5 s Laravelem 13.

::: tip Funkce Pro
Server MCP se dodává s `rankbeam/laravel-seo-pro`. **Ve výchozím nastavení slouží jen ke čtení.** Úpravy se zapínají výslovně konfiguračním přepínačem a seznamem povolených modelů.
:::

## Co může asistent dělat {#what-the-assistant-can-do}

### Analytické nástroje (vždy dostupné) {#analysis-tools-always-available}

| Nástroj | Co dělá |
| --- | --- |
| `seo_resolve` | Plně vyhodnocená metadata SEO záznamu modelu: titulek, popis, kanonická URL, robots, Open Graph a JSON-LD, tedy skutečný výstup stránky. |
| `seo_audit` | [Audit metadat](/cs/guide/audit) uvnitř procesu pro záznam modelu nebo prvních N záznamů; stejné kontroly `seo:audit` přímo, bez fronty. |
| `seo_score` | Poslední uložené [skóre SEO v Pro](/cs/pro/scoring) pro záznam modelu, 0–100 a známka. |
| `seo_robots_directives` | Spravované [direktivy robots.txt pro roboty AI](/cs/guide/ai-crawlers) a vyhodnocené povolení či zákaz pro každého bota. |
| `validate_schema` | Ověří objekt JSON-LD nebo vyhodnocený graf schématu povoleného modelu validátorem strukturovaných dat Core, s požadavky Googlu na rozšířené výsledky podle `@type`. |
| `analyze_robots` | Rozhodující výsledek povoleno/zakázáno **pro každého známého robota AI** s důvodem: přepsání pro bota, pravidlo účelu nebo výchozí hodnota. Pravidla jsou společná pro celý web. |
| `debug_social_share` | Vyhodnocená karta Open Graph a Twitter, kterou by robot sociální sítě skutečně viděl pro záznam modelu po náhradních hodnotách, s doporučeními ke stavu karty. |
| `check_meta` | Přehled stavu metadat jednoho záznamu: vyhodnocené title/description/canonical/robots/og:image s délkami a přítomností a problémy auditu. |

### Nástroje obsahu webu (vždy dostupné) {#site-content-tools-always-available}

Nástroje pro „rozhovor s webem“ umožňují asistentovi vypsat a prohledat vaše stránky s porozuměním jejich obsahu.

| Nástroj | Co dělá |
| --- | --- |
| `list_pages` | Vypíše stránky, tedy záznamy **povoleného** modelu se správou SEO, každou s URL a výsledným titulkem. Podporuje stránkování `limit`/`offset`. |
| `search_pages` | Fulltextově prohledá stránky **povoleného** modelu přes Laravelem [Scout](https://laravel.com/docs/scout), pokud model vyhledávání podporuje, jinak bezpečným SQL `LIKE` nad title/name/headline a připojenými metadaty SEO. Každý výsledek obsahuje URL, titulek a úryvek. |

### Provozní nástroje (volitelně) {#ops-tools-opt-in}

Provozní nástroje čtou stav skenu a mění konfiguraci webu. Stejně jako nástroj úprav jsou **podmíněné `allow_edits`**. Na serveru jen pro čtení, což je výchozí nastavení, jsou neviditelné a nečinné.

| Nástroj | Co dělá |
| --- | --- |
| `list_issues` | Aktuální otevřené problémy skenu SEO, tedy trvalá otevřená sada napříč běhy, a hlavička posledního [běhu skenu](/cs/pro/scan-issues). Filtruje podle `severity` / `type`. |
| `trigger_scan` | Spustí cílený sken jednoho povoleného záznamu a vrátí běh nebo úplný sken všech cílů, standardně do fronty, případně přímo s `sync: true`. |
| `create_redirect` | Vytvoří pravidlo přesměrování, zdrojová cesta nebo regulární výraz → cíl, stav `301`/`302`/`307`/`308`/`410`, s využitím validátorů modelu přesměrování. |

### Nástroj úprav (volitelně) {#edit-tool-opt-in}

| Nástroj | Co dělá |
| --- | --- |
| `seo_save_meta` | Zapíše metadata SEO, titulek, popis, kanonickou URL, robots, OG, Twitter a JSON-LD, na záznam **povoleného** modelu přes `saveSEO()`. |

Provozní nástroje a `seo_save_meta` se **nenabízejí v `tools/list` a nelze je spustit**, dokud nezapnete úpravy; viz [Bezpečnost](#security). Server jen pro čtení asistentovi ani neřekne, že tyto nástroje existují.

## Připojení klienta AI {#wiring-an-ai-client}

Server používá JSON-RPC přes **stdio**. Klient spustí příkaz Artisan a komunikuje s ním přes rouru. Zaregistrujte jej v používaných klientech; stejný server funguje pro všechny.

::: tip Jeden příkaz, libovolný klient
Každý klient níže spouští totožný příkaz `php artisan seo-pro:mcp` **z kořene aplikace**, aby Artisan mohl aplikaci zavést. Pokud `php` není na `PATH` klienta, což je běžné ve Windows nebo GUI aplikacích nedědících prostředí shellu, zadejte **absolutní cestu k `php` i `artisan`**. Artisan zavádí aplikaci z adresáře vlastního skriptu `artisan`, takže nepotřebuje `cwd`.
:::

### Claude Code (CLI) {#claude-code-cli}

Zaregistruje jej jediný příkaz. Spusťte jej **z kořene aplikace**:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Ověřte připojení:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

Ve Windows / Laravel Herd určete absolutní cesty, aby připojení fungovalo nezávisle na adresáři spuštění:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Upravte konfigurační soubor přes **Settings → Developer → Edit Config** nebo jej otevřete přímo:

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

Ve **Windows** použijte absolutní `php.exe` a každé zpětné lomítko v JSON zdvojte:

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

Claude Desktop zcela ukončete a znovu otevřete. Nástroje se objeví pod ikonou nástrojů či zástrčky v liště zprávy.

### Cursor {#cursor}

Vytvořte `.cursor/mcp.json` v projektu nebo `~/.cursor/mcp.json` pro všechny projekty. Zadejte absolutní cestu k `artisan`, aby se spustil odkudkoli:

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

Ve Windows použijte absolutní `php.exe` a zdvojená zpětná lomítka stejně jako ve výše uvedeném příkladu Claude Desktop. Server zapněte v **Settings → MCP**.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Stejně jako u Claude Code předejte ve Windows/Herd absolutní cesty k `php` a `artisan`.

Hotovo, asistent nyní může volat nástroje.

## Ukázkový rozhovor {#a-worked-conversation}

Toto je skutečná výměna s demo aplikací, webem zdravotnického institutu o 900 stránkách, se zkráceným skutečným výstupem nástrojů. Dva modely povolené ke čtení jsou `App\Models\Page` a `App\Models\PublicPage` a `allow_edits` je zapnuté.

> **Vy:** Jak je na tom SEO stránky „Accoglienza“, Page id 1? Oprav zjevné nedostatky.

Asistent nejprve provede **audit**, `seo_audit { model: "App\\Models\\Page", id: 1 }`:

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

**Vyhodnotí** současná metadata pomocí `seo_resolve { model: "App\\Models\\Page", id: 1 }`, aby zjistil skutečně vykreslované hodnoty. Najde titulek o 17 znacích a popis o 11 znacích, oba pod doporučeným minimem. Proto **zapíše opravu**, `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

Nástroj potvrdí zápis a vrátí nově vyhodnocený záznam:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Nakonec **zopakuje audit** pro ověření opravy; `issue_count` je nyní `0`:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Asistent:** Hotovo. Titulek o 17 znacích i popis o 11 znacích byly pod doporučenými minimy. Nastavil jsem úplnější a výstižnější titulek i popis a opakovaný audit nyní nehlásí problémy, 0 zjištění.

Zápis prošel stejným `saveSEO()` jako každé programové uložení. Hodnota se proto uloží a vyhodnotí stejně jako při nastavení v kódu, hromadným přiřazením přes povolená pole a přetypování modelu. **Neprochází** validátory délky formuláře Filamentu. Opakovaný audit tedy ověřuje jen své automatické kontroly. Redakční správnost stále vyžaduje kontrolu; čistý audit není certifikací kvality textu.

### Rozhovor o obsahu webu {#talking-to-your-site-s-content}

Nástroje obsahu umožňují asistentovi odpovídat **o samotném webu**, nejen o jednom pojmenovaném záznamu. Toto je ukázka „rozhovoru s webem“.

> **Vy:** Které stránky zmiňují „immunoterapia“ a mají v pořádku titulky?

Asistent **vyhledá**, `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

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

Třetí titulek je pouze „Nuova“, proto **zkontroluje metadata stránky**, `check_meta { model: "App\\Models\\Page", id: 91 }`:

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

> **Asistent:** „immunoterapia“ zmiňují tři stránky. Dvě vypadají dobře, ale **/news/nuova-immunoterapia-2026** má titulek o 5 znacích, „Nuova“, a žádný meta popis. Mám pro ni napsat úplnější titulek a popis?

Se zapnutým `allow_edits` by ji mohl rovnou opravit pomocí `seo_save_meta`. V režimu jen pro čtení skončí u doporučení. Nástroj `search_pages` zde použil náhradní SQL `LIKE`, `"driver": "like"`, protože model není indexovaný Scoutem. Přidejte [Laravel Scout](https://laravel.com/docs/scout) a stejný nástroj bude bez změny rozhraní prohledávat přes váš vyhledávací systém.

## Bezpečnost {#security}

Tři vrstvy zajišťují bezpečné výchozí nastavení serveru. V režimu jen pro čtení jsou všechny zapnuté; uvolňujete je záměrně.

### 1. Úpravy mají přepínač (standardně vypnuté) {#_1-edits-are-gated-off-by-default}

Nástroj zápisu je neviditelný a nečinný, dokud nezměníte přepínač:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

Při vypnutém `allow_edits`, což je výchozí stav, `tools/list` **nevrací `seo_save_meta`** a jeho `tools/call` selže s JSON-RPC `-32602`. Asistent nemůže zapisovat ani zjistit, že by to mohl umět. Zapněte jej jen pro klienta a databázi, kterým důvěřujete.

### 2. Seznam povolených modelů {#_2-the-model-allowlist}

Každý nástroj pracující s modelem, pro čtení **i** zápis, se může dotknout jen modelu s `HasSEO` na seznamu povolených. Klient AI nikdy nemůže nástroj nasměrovat na libovolnou třídu, například `User` nebo fakturační model:

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Nástroj s požadavkem na nepovolenou třídu vrátí chybový výsledek čitelný asistentem, `Model [App\Models\User] is not in the MCP allowlist`. Třídy se nikdy nedotkne. Je-li `models` prázdné, použije nastavené `seo.audit.models` / `seo.sitemap.models`. MCP tak sdílí přesně stejný rozsah, ve kterém už pracuje zbytek balíčku, nikdy širší.

### 3. Pouze stdio — nic se nevystavuje síti {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

Server komunikuje **jen přes stdio**. Klient spustí proces a přenáší JSON-RPC rourou dovnitř i ven. Není zde **HTTP listener, port ani socket**, tedy nic dostupného z jiného počítače a žádné vzdálené rozhraní, pro které by bylo nutné řešit autentizaci. STDOUT obsahuje pouze provoz protokolu. Veškerá diagnostika jde na STDERR a klient ji loguje; například Claude Desktop do `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`. Náhodný řádek logu tak nemůže porušit proud protokolu.

::: warning Server se zapnutými úpravami berte jako přístup k zápisu do DB
`allow_edits` dovoluje připojenému asistentovi měnit řádky SEO v databázi, proti které příkaz běží. Při zkoušení jej připojte k místnímu nebo testovacímu prostředí, seznam povolených modelů držte úzký a po dokončení úpravy opět vypněte. Hlavní přepínač `'enabled' => false` odmítne příkaz vůbec spustit. Místní přenos stdio nebrání klientovi AI odeslat výsledky nástrojů vlastnímu poskytovateli. Zohledněte také datové nastavení klienta.
:::

## Konfigurace {#configuration}

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

## Server Card (objevování) — experimentální návrh specifikace {#server-card-discovery-—-experimental-draft-spec}

MCP **Server Card** je malý dokument JSON na známé URL, který klientovi umožní před připojením objevit server, jeho název, verzi a nabídku. Rankbeam jej může pro váš web poskytovat jako sdělení nástrojům agentů: *„tento web má server MCP, se kterým můžete komunikovat“*. Ve výchozím nastavení je **vypnutý** a jde jen o doplněk; zapnutí nic dalšího nemění.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Po zapnutí `GET /.well-known/mcp/server-card.json` vrátí například tuto kartu:

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

Karta vypisuje jen **aktuálně zapnuté** nástroje. Server jen pro čtení tak přes ni nikdy nenabízí podmíněné provozní nástroje ani úpravy.

::: warning Sleduje návrh specifikace
Vychází z **návrhu** MCP Server Card, [SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127), který byl k 10. září 2026 otevřeným, nesloučeným návrhem. Známá cesta, URL `$schema` a přesná sada polí **nejsou konečné**, proto jsou konfigurovatelné přes `path`, `schema_url`, `name` a `website_url`. Tento server běží přes **stdio**, `php artisan seo-pro:mcp`, takže karta nemá blok HTTP `remotes`. Jde o vodítko pro objevení serveru, nikoli HTTP endpoint k připojení. Než se na ni spolehnete, ověřte cestu a strukturu s klientem; pokud ji nepotřebujete, ponechte ji vypnutou.
:::

## Poznámky k protokolu {#protocol-notes}

Server MCP poskytující jen nástroje má malé rozhraní JSON-RPC 2.0 a tento je implementuje přímo: `initialize` pro dohodu verze a schopností, `tools/list`, `tools/call` a `ping`. Nabízí verzi protokolu `2025-06-18` a rozumí také `2025-03-26` a `2024-11-05`. Pro neznámé metody vrací `-32601`, pro chybně sestavené řádky `-32700` a selhání **nástroje** jako výsledek `isError` čitelný asistentem, nikoli chybu přenosu. Notifikace, zprávy bez `id` jako `notifications/initialized`, správně nedostávají odpověď.

## Použití bez panelu a rozšiřování {#headless-extending}

`SeoPro::mcp()` vrátí registr nástrojů, takže můžete prozkoumat nabízené nástroje nebo zaregistrovat vlastní:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Vlastní nástroj implementuje `McpTool`, tedy `name`, `description`, `inputSchema`, `isEnabled` a `handle`. Rozšiřte `AbstractTool` pro opětovné použití vyhodnocování seznamu povolených modelů, aby nástroj zdědil stejné bezpečnostní záruky jako vestavěné nástroje.

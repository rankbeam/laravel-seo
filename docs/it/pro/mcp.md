---
description: "Server MCP stdio senza dipendenze aggiuntive: un assistente AI può leggere e, se abilitato, modificare i metadati SEO di un sito Laravel. PHP 8.2–8.4 con Laravel 11–13."
---

# Server MCP {#mcp-server}

Il server MCP di Rankbeam permette a un assistente AI di **leggere e, facoltativamente, modificare i metadati SEO del sito** tramite il [Model Context Protocol](https://modelcontextprotocol.io). Collega un client MCP, come Claude Code, Claude Desktop, Cursor o Codex, alla tua app Laravel: potrà risolvere i metadati, eseguire un audit, leggere il punteggio Pro e le regole per i crawler AI e, se lo abiliti, salvare modifiche.

È un server stdio autonomo, **senza SDK o pacchetti aggiuntivi**, compatibile con le combinazioni supportate di PHP 8.2–8.4 e Laravel 11/12/13.

::: tip Funzione Pro
Il server è incluso in `rankbeam/laravel-seo-pro`. Per impostazione predefinita è **in sola lettura**; le modifiche richiedono l'attivazione esplicita e rispettano l'elenco dei modelli consentiti.
:::

## Cosa può fare l'assistente {#what-the-assistant-can-do}

### Strumenti di analisi, sempre disponibili {#analysis-tools-always-available}

| Strumento | Funzione |
| --- | --- |
| `seo_resolve` | Metadati risolti di un record: titolo, descrizione, canonical, robots, Open Graph e JSON-LD, inclusi i fallback del resolver. |
| `seo_audit` | [Audit dei metadati](/it/guide/audit) nel processo per un record o i primi N: stessi controlli di `seo:audit`, senza coda. |
| `seo_score` | Ultimo [punteggio SEO Pro](/it/pro/scoring) salvato per un record, da 0 a 100 con fascia. |
| `seo_robots_directives` | [Direttive robots.txt per i crawler AI](/it/guide/ai-crawlers) gestite dal pacchetto e regole risolte per bot. |
| `validate_schema` | Valida un oggetto JSON-LD o il grafo risolto di un modello consentito tramite il validatore core, che include controlli per tipo sui requisiti dei risultati avanzati. Non è un test live di Google. |
| `analyze_robots` | Regola allow/disallow risolta **per crawler AI noto** e sua origine: override del bot, regola per finalità o predefinito. La configurazione è globale per il sito. |
| `debug_social_share` | Dati Open Graph e Twitter risolti del record, dopo i fallback, con osservazioni sulla scheda. Non effettua una richiesta al crawler social. |
| `check_meta` | Titolo, descrizione, canonical, robots e og:image risolti, lunghezze, presenza e risultati dell'audit per un record. |

### Strumenti sui contenuti, sempre disponibili {#site-content-tools-always-available}

Permettono all'assistente di elencare e cercare le pagine del sito:

| Strumento | Funzione |
| --- | --- |
| `list_pages` | Elenca i record SEO di un modello **consentito**, con URL e titolo effettivo. Paginazione tramite `limit` e `offset`. |
| `search_pages` | Cerca nei record di un modello **consentito** tramite [Laravel Scout](https://laravel.com/docs/scout), se configurato, oppure una query SQL `LIKE` sui campi title/name/headline e sui metadati SEO associati. Restituisce URL, titolo ed estratto. |

### Strumenti operativi, da abilitare {#ops-tools-opt-in}

Leggono lo stato delle scansioni o modificano la configurazione del sito. Come lo strumento di modifica, richiedono **`allow_edits`**: nel server in sola lettura non vengono esposti né eseguiti.

| Strumento | Funzione |
| --- | --- |
| `list_issues` | Problemi SEO attualmente aperti, conservati tra esecuzioni, e intestazione dell'ultima [scansione](/it/pro/scan-issues). Filtri `severity` e `type`. |
| `trigger_scan` | Avvia una scansione di un record consentito o di tutti i target: in coda per impostazione predefinita, nel processo con `sync: true`. |
| `create_redirect` | Crea una regola di redirect, da percorso o regex a destinazione, con stato `301`, `302`, `307`, `308` o `410`, usando i validatori del modello redirect. |

### Strumento di modifica, da abilitare {#edit-tool-opt-in}

| Strumento | Funzione |
| --- | --- |
| `seo_save_meta` | Salva titolo, descrizione, canonical, robots, OG, Twitter e JSON-LD su un record **consentito**, tramite `saveSEO()`. |

Gli strumenti operativi e `seo_save_meta` **non compaiono in `tools/list` e non sono eseguibili** finché non abiliti le modifiche. Vedi [Sicurezza](#security).

## Collegare un client AI {#wiring-an-ai-client}

Il server usa JSON-RPC su **stdio**. Il client avvia un comando Artisan e comunica tramite le pipe del processo. Lo stesso comando funziona con i diversi client MCP.

::: tip Un comando per tutti i client
Tutti gli esempi avviano `php artisan seo-pro:mcp` **dalla radice dell'app**, così Artisan può inizializzarla. Se `php` non è nel `PATH` del client, situazione comune su Windows o nelle app grafiche, usa **percorsi assoluti sia per PHP sia per `artisan`**. Artisan inizializza l'app dalla directory del proprio script, senza richiedere `cwd`.
:::

### Claude Code, CLI {#claude-code-cli}

Esegui questo comando **dalla radice dell'app** per registrare il server:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Verifica la connessione:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

Su Windows con Laravel Herd, indica percorsi assoluti per non dipendere dalla directory di avvio:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Apri la configurazione da **Settings → Developer → Edit Config**, oppure direttamente:

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

Su **Windows**, usa il percorso assoluto di `php.exe` e raddoppia ogni backslash nel JSON:

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

Chiudi completamente e riapri Claude Desktop. Gli strumenti sono disponibili nei controlli degli strumenti o dei connettori del client; nomi e posizione possono variare con la versione.

### Cursor {#cursor}

Crea `.cursor/mcp.json` nel progetto, oppure `~/.cursor/mcp.json` per tutti i progetti. Usa il percorso assoluto di `artisan`:

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

Su Windows, usa il percorso assoluto di `php.exe` e backslash raddoppiati come nell'esempio Claude Desktop. Abilita il server nelle impostazioni MCP di Cursor.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Come per Claude Code, su Windows o Herd usa percorsi assoluti per PHP e `artisan`.

Una volta collegato, l'assistente può chiamare gli strumenti esposti.

## Esempio di conversazione {#a-worked-conversation}

L'esempio pubblicato usa un'app dimostrativa di un istituto medico con circa 900 pagine; gli output sono abbreviati. L'elenco dei modelli consentiti contiene `App\Models\Page` e `App\Models\PublicPage`, con `allow_edits` attivo.

> **Tu:** Come sono i metadati SEO della pagina “Accoglienza”, Page id 1? Correggi i problemi evidenti.

L'assistente esegue prima **l'audit** con `seo_audit { model: "App\\Models\\Page", id: 1 }`:

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

Poi **risolve** i metadati con `seo_resolve { model: "App\\Models\\Page", id: 1 }`: trova un titolo di 17 caratteri e una descrizione di 11, sotto le soglie consigliate. **Salva la correzione** tramite `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

Lo strumento conferma il salvataggio e restituisce il record nuovamente risolto:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Infine ripete **l'audit**: `issue_count` è ora `0`:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Assistente:** Fatto. Titolo e descrizione erano di 17 e 11 caratteri, sotto le soglie consigliate. Ho salvato valori più descrittivi; il nuovo audit non rileva problemi nei controlli eseguiti.

Il salvataggio usa `saveSEO()`, con campi fillable e cast del modello, come una modifica da codice. **Non passa dai validatori di lunghezza del form Filament**. Ripetere l'audit verifica i controlli automatici sul nuovo valore; resta necessario valutare l'accuratezza editoriale del testo. Un audit senza problemi non certifica la qualità complessiva della pagina.

### Interrogare i contenuti del sito {#talking-to-your-site-s-content}

Gli strumenti sui contenuti permettono domande sul **sito nel suo insieme**, senza conoscere in anticipo l'ID di un record.

> **Tu:** Quali pagine parlano di “immunoterapia” e hanno titoli adeguati?

L'assistente **cerca** con `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

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

Il terzo titolo è solo “Nuova”. L'assistente **controlla i metadati** con `check_meta { model: "App\\Models\\Page", id: 91 }`:

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

> **Assistente:** Tre pagine citano “immunoterapia”. Nei controlli mostrati, due non presentano problemi, mentre **/news/nuova-immunoterapia-2026** ha il titolo “Nuova”, di 5 caratteri, e nessuna meta description. Posso proporre valori più descrittivi.

Con `allow_edits` attivo può salvarli tramite `seo_save_meta`; in sola lettura può solo suggerirli. Qui `search_pages` usa il fallback SQL `LIKE`, indicato da `"driver": "like"`, perché il modello non è indicizzato con Scout. Configurando [Laravel Scout](https://laravel.com/docs/scout), lo stesso strumento usa il motore di ricerca del modello.

## Sicurezza {#security}

Tre controlli definiscono il comportamento predefinito. Amplia i permessi solo in base all'uso previsto.

### 1. Modifiche disattivate per impostazione predefinita {#_1-edits-are-gated-off-by-default}

Lo strumento di scrittura non è esposto né eseguibile finché non attivi:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

Con `allow_edits` disattivato, `seo_save_meta` **non compare in `tools/list`** e una sua chiamata fallisce con JSON-RPC `-32602`. Abilitalo solo per un client e un database su cui intendi consentire modifiche.

### 2. Elenco dei modelli consentiti {#_2-the-model-allowlist}

Ogni strumento riferito a modelli, in lettura **o** scrittura, accede solo a modelli `HasSEO` consentiti. Il client non può scegliere una classe arbitraria:

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Una classe non consentita produce un errore, come `Model [App\Models\User] is not in the MCP allowlist`, senza essere usata. Se `models` è vuoto, l'elenco deriva da `seo.audit.models` e `seo.sitemap.models`: controlla anche queste configurazioni per sapere quali record il client può leggere.

### 3. Solo stdio, senza listener di rete {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

Il client avvia il processo e scambia JSON-RPC tramite **stdio**. Il server MCP non apre listener HTTP, porte o socket di rete. L'accesso dipende quindi dal client e dai permessi del processo locale. STDOUT è riservato al protocollo; la diagnostica del server va a STDERR, raccolto nei log del client, per esempio `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`.

::: warning Abilitare le modifiche significa consentire scritture sul database
Con `allow_edits`, l'assistente collegato può modificare le righe SEO del database usato dal comando. Per le prove usa locale o staging, limita i modelli consentiti e disattiva le modifiche quando non servono più. `'enabled' => false` impedisce l'avvio del comando. Il trasporto locale non impedisce al client AI di inviare i risultati al proprio provider: considera anche la sua configurazione dei dati.
:::

## Configurazione {#configuration}

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

## Server Card per l'individuazione: proposta sperimentale {#server-card-discovery-—-experimental-draft-spec}

Una **Server Card MCP** è un documento JSON a un URL noto che descrive nome, versione e capacità di un server prima della connessione. Rankbeam può pubblicarne una per segnalare la presenza del server agli strumenti compatibili. È **disattivata per impostazione predefinita** e la sua attivazione non abilita trasporti o modifiche MCP.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Quando è attiva, `GET /.well-known/mcp/server-card.json` restituisce una scheda di questa forma:

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

La scheda elenca solo gli strumenti **attualmente abilitati**: in sola lettura non pubblicizza quelli operativi o di modifica.

::: warning Basata su una proposta
L'implementazione segue la proposta MCP Server Card [SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127). Percorso noto, URL `$schema` e campi possono cambiare: sono configurabili tramite `path`, `schema_url`, `name` e `website_url`. Il server di Rankbeam usa **stdio**, con `php artisan seo-pro:mcp`, quindi la scheda non contiene un blocco HTTP `remotes`. È un'indicazione per l'individuazione, non un endpoint HTTP a cui collegarsi. Verifica compatibilità e formato nel tuo client prima di usarla; lasciala disattivata se non serve.
:::

## Note sul protocollo {#protocol-notes}

Il server implementa direttamente JSON-RPC 2.0 per `initialize`, con negoziazione della versione e delle capacità, `tools/list`, `tools/call` e `ping`. Dichiara la versione `2025-06-18` e comprende anche `2025-03-26` e `2024-11-05`. Risponde con `-32601` ai metodi sconosciuti e `-32700` alle righe malformate. Un errore dello **strumento** viene restituito come risultato `isError`, leggibile dall'assistente, anziché errore di trasporto. Le notifiche senza `id`, come `notifications/initialized`, non ricevono risposta.

## Uso senza Filament ed estensioni {#headless-extending}

`SeoPro::mcp()` restituisce il registro degli strumenti. Puoi esaminare quelli esposti o registrarne di tuoi:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Uno strumento personalizzato implementa `McpTool`: `name`, `description`, `inputSchema`, `isEnabled` e `handle`. Estendi `AbstractTool` per riusare la risoluzione dei modelli consentiti; le altre operazioni del tuo strumento richiedono i propri controlli.

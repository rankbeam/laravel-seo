---
description: "Ein eigenständiger stdio-MCP-Server ohne zusätzliche Abhängigkeiten: KI-Assistenten können die SEO-Daten einer Laravel-Website lesen und bei Freigabe bearbeiten. Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5."
---

# MCP-Server {#mcp-server}

Mit dem MCP-Server von Rankbeam kann ein KI-Assistent die **SEO-Daten einer Website lesen und optional bearbeiten** – über das [Model Context Protocol](https://modelcontextprotocol.io). Verbinde einen MCP-Client wie Claude Code, Claude Desktop, Cursor oder Codex mit deiner Laravel-Anwendung. Der Assistent kann dann die Metadaten einer Seite auflösen, ein Audit ausführen, den Pro-Score lesen, deine Regeln für KI-Crawler prüfen und bei entsprechender Freigabe SEO-Daten speichern.

Der Server ist eigenständig und verwendet stdio. Er benötigt **keine zusätzlichen Abhängigkeiten**, kein SDK und keine weiteren Pakete. Er läuft mit PHP 8.2–8.4 (Laravel 11), PHP 8.2–8.5 (Laravel 12), PHP 8.3–8.5 (Laravel 13).

::: tip Pro-Funktion
Der MCP-Server ist Teil von `rankbeam/laravel-seo-pro`. Standardmäßig erlaubt er **nur Lesezugriffe**. Bearbeitungen müssen über einen Konfigurationsschalter und eine Modell-Allowlist freigegeben werden.
:::

## Was der Assistent tun kann {#what-the-assistant-can-do}

### Analyse-Tools (immer verfügbar) {#analysis-tools-always-available}

| Tool | Funktion |
| --- | --- |
| `seo_resolve` | Liefert die vollständig aufgelösten SEO-Metadaten eines Modelldatensatzes: Titel, Beschreibung, Canonical, Robots, Open Graph und JSON-LD – also die Werte, die die Seite tatsächlich ausgeben würde. |
| `seo_audit` | Führt das [Metadaten-Audit](/de/guide/audit) für einen Modelldatensatz oder die ersten N Datensätze direkt im Prozess aus. Dieselben Prüfungen wie `seo:audit`, ohne Queue. |
| `seo_score` | Liest den zuletzt gespeicherten [Pro-SEO-Score](/de/pro/scoring) eines Modelldatensatzes: 0–100 und eine Note. |
| `seo_robots_directives` | Liefert die verwalteten [robots.txt-Anweisungen für KI-Crawler](/de/guide/ai-crawlers) und die aufgelösten Regeln zum Erlauben oder Sperren jedes Bots. |
| `validate_schema` | Prüft ein JSON-LD-Objekt oder den aufgelösten Schema-Graphen eines freigegebenen Modells mit dem Structured-Data-Validator des Core-Pakets anhand der Google-Rich-Result-Anforderungen pro `@type`. |
| `analyze_robots` | Liefert die Entscheidung der konfigurierten Regeln **für jeden bekannten KI-Crawler** und deren Ursprung: eine botbezogene Ausnahme, die zweckbezogene Regel oder den Standardwert. Die Regeln gelten für die gesamte Website. |
| `debug_social_share` | Zeigt die aufgelösten Open-Graph- und Twitter-Card-Daten eines Modelldatensatzes nach Anwendung aller Fallbacks sowie Hinweise zu möglichen Problemen mit der Vorschau. |
| `check_meta` | Zeigt für einen Modelldatensatz gezielt den Zustand der Metadaten: aufgelöste Werte für Titel, Beschreibung, Canonical, Robots und og:image samt Längen, Vorhandensein und Audit-Befunden. |

### Tools für Website-Inhalte (immer verfügbar) {#site-content-tools-always-available}

Mit diesen Tools kann der Assistent die Seiten deiner Website auflisten und durchsuchen. Dadurch kennt er den Inhalt der Website und kann Fragen dazu beantworten.

| Tool | Funktion |
| --- | --- |
| `list_pages` | Listet die SEO-verwalteten Seiten beziehungsweise Datensätze eines **freigegebenen** Modells mit URL und effektivem Titel auf. Unterstützt Seitennavigation über `limit` und `offset`. |
| `search_pages` | Durchsucht die Seiten eines **freigegebenen** Modells mit Laravel [Scout](https://laravel.com/docs/scout), wenn das Modell durchsuchbar ist. Andernfalls verwendet es eine sichere SQL-`LIKE`-Suche in title/name/headline und den verknüpften SEO-Metadaten. Jeder Treffer enthält URL, Titel und einen Ausschnitt. |

### Betriebs-Tools (optional freizugeben) {#ops-tools-opt-in}

Diese Tools lesen den Scan-Status und ändern die Website-Konfiguration. Wie das Bearbeitungs-Tool sind sie **an `allow_edits` gebunden**. Auf einem Server mit reinem Lesezugriff – dem Standard – sind sie unsichtbar und nicht ausführbar.

| Tool | Funktion |
| --- | --- |
| `list_issues` | Liest die aktuell offenen SEO-Scan-Befunde aus dem laufübergreifenden Bestand und die Kopfdaten des letzten [Scan-Laufs](/de/pro/scan-issues). Filter nach `severity` und `type` sind möglich. |
| `trigger_scan` | Startet einen gezielten Scan eines freigegebenen Datensatzes und gibt den Lauf zurück oder startet einen vollständigen Scan aller Ziele. Standardmäßig über die Queue; mit `sync: true` direkt. |
| `create_redirect` | Erstellt eine Weiterleitungsregel von einem Quellpfad oder regulären Ausdruck zu einem Ziel mit Status `301`, `302`, `307`, `308` oder `410`. Dabei verwendet es die Validatoren des Weiterleitungsmodells. |

### Bearbeitungs-Tool (optional freizugeben) {#edit-tool-opt-in}

| Tool | Funktion |
| --- | --- |
| `seo_save_meta` | Speichert SEO-Metadaten – Titel, Beschreibung, Canonical, Robots, OG, Twitter und JSON-LD – über `saveSEO()` in einem **freigegebenen** Modelldatensatz. |

Die Betriebs-Tools und `seo_save_meta` werden **weder in `tools/list` angeboten noch ausgeführt**, solange Bearbeitungen deaktiviert sind. Siehe [Sicherheit](#security). Ein Server mit reinem Lesezugriff teilt dem Assistenten nicht einmal mit, dass diese Tools verfügbar sein könnten.

## Einen KI-Client verbinden {#wiring-an-ai-client}

Der Server kommuniziert per JSON-RPC über **stdio**. Der Client startet einen Artisan-Befehl und tauscht Nachrichten über dessen Ein- und Ausgabe aus. Registriere den Server in den Clients, die du verwendest; derselbe Server funktioniert mit allen.

::: tip Ein Befehl für jeden Client
Alle folgenden Clients starten denselben Befehl: `php artisan seo-pro:mcp`, ausgeführt **im Stammverzeichnis deiner Anwendung**, damit Artisan die Anwendung starten kann. Falls `php` nicht im `PATH` des Clients liegt – häufig unter Windows oder bei grafischen Anwendungen ohne deine Shell-Umgebung –, gib **absolute Pfade für `php` und `artisan`** an. Artisan startet die Anwendung aus dem Verzeichnis des `artisan`-Skripts; dafür ist kein `cwd` erforderlich.
:::

### Claude Code (CLI) {#claude-code-cli}

Ein Befehl registriert den Server. Führe ihn **im Stammverzeichnis deiner Anwendung** aus:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Prüfe anschließend die Verbindung:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

Verwende unter Windows beziehungsweise mit Laravel Herd absolute Pfade, damit der Start unabhängig vom aktuellen Verzeichnis funktioniert:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Bearbeite die Konfigurationsdatei über **Settings → Developer → Edit Config** oder öffne sie direkt:

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

Verwende unter **Windows** den absoluten Pfad zu `php.exe` und maskiere jeden Backslash in JSON durch einen zweiten Backslash:

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

Beende Claude Desktop vollständig und öffne es erneut. Die Tools erscheinen hinter dem Werkzeug-/Steckersymbol in der Nachrichtenleiste.

### Cursor {#cursor}

Erstelle `.cursor/mcp.json` in deinem Projekt oder `~/.cursor/mcp.json` für alle Projekte. Verwende einen absoluten Pfad zu `artisan`, damit der Server aus jedem Verzeichnis startet:

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

Verwende unter Windows den absoluten Pfad zu `php.exe` und doppelte Backslashes wie im Beispiel für Claude Desktop. Aktiviere den Server unter **Settings → MCP**.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Gib unter Windows beziehungsweise mit Herd wie bei Claude Code absolute Pfade für `php` und `artisan` an.

Der Assistent kann nun die Tools aufrufen.

## Ein vollständiges Gesprächsbeispiel {#a-worked-conversation}

Der folgende Austausch stammt aus der beschriebenen Demo-Anwendung, einer Website eines medizinischen Instituts mit 900 Seiten. Die tatsächlichen Tool-Ausgaben sind gekürzt. Auf der Allowlist stehen die beiden lesbaren Modelle `App\Models\Page` und `App\Models\PublicPage`; `allow_edits` ist aktiviert.

> **Du:** Wie steht es um die SEO-Daten der Seite „Accoglienza“ (Page mit ID 1)? Behebe offensichtliche Probleme.

Der Assistent führt zuerst ein **Audit** aus: `seo_audit { model: "App\\Models\\Page", id: 1 }`:

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

Er **löst die aktuellen Metadaten auf**, um die tatsächliche Ausgabe zu sehen: `seo_resolve { model: "App\\Models\\Page", id: 1 }`. Dabei findet er einen Titel mit 17 Zeichen und eine Beschreibung mit 11 Zeichen. Beide liegen unter den empfohlenen Mindestlängen. Anschließend **speichert er eine Korrektur** mit `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

Das Tool bestätigt den Schreibvorgang und gibt den frisch aufgelösten Datensatz zurück:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Zum Schluss führt der Assistent **erneut ein Audit** aus. `issue_count` beträgt jetzt `0`:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Assistent:** Erledigt. Der Titel mit 17 Zeichen und die Beschreibung mit 11 Zeichen lagen unter den empfohlenen Mindestlängen. Ich habe beide ausführlicher und beschreibender formuliert. Das erneute Audit meldet keine Befunde mehr (0).

Der Schreibvorgang verwendet dasselbe `saveSEO()` wie ein programmatisches Speichern. Die Werte werden über die ausfüllbaren Felder und Casts des Modells gespeichert und genauso aufgelöst wie im Code gesetzte Werte. Die Längenvalidatoren des Filament-Formulars werden dabei **nicht** ausgeführt. Das erneute Audit bestätigt deshalb nur seine automatischen Prüfungen. Die redaktionelle Richtigkeit braucht weiterhin eine Prüfung; ein Audit ohne Befunde bescheinigt keine Textqualität.

### Fragen zu den Inhalten deiner Website {#talking-to-your-site-s-content}

Die Inhalts-Tools ermöglichen Fragen **zur Website selbst**, ohne dass du vorab einen bestimmten Datensatz nennen musst.

> **Du:** Welche Seiten erwähnen „immunoterapia“, und sind ihre Titel in Ordnung?

Der Assistent **sucht** mit `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

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

Der dritte Titel lautet nur „Nuova“. Deshalb **prüft er die Metadaten dieser Seite** mit `check_meta { model: "App\\Models\\Page", id: 91 }`:

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

> **Assistent:** Drei Seiten erwähnen „immunoterapia“. Zwei sehen gut aus. **/news/nuova-immunoterapia-2026** hat jedoch nur den fünf Zeichen langen Titel „Nuova“ und keine Meta-Beschreibung. Soll ich einen ausführlicheren Titel und eine Beschreibung dafür schreiben?

Mit aktiviertem `allow_edits` könnte der Assistent die Änderung direkt über `seo_save_meta` speichern. Bei reinem Lesezugriff bleibt es bei der Empfehlung. `search_pages` hat hier den SQL-`LIKE`-Fallback verwendet (`"driver": "like"`), weil das Modell nicht mit Scout indexiert ist. Ergänze [Laravel Scout](https://laravel.com/docs/scout), und dasselbe Tool durchsucht stattdessen deine Such-Engine.

## Sicherheit {#security}

Drei Schutzebenen begrenzen den Server standardmäßig. Im Standardbetrieb mit reinem Lesezugriff sind alle aktiv; Freigaben nimmst du ausdrücklich vor.

### 1. Bearbeitungen sind gesperrt (standardmäßig deaktiviert) {#_1-edits-are-gated-off-by-default}

Das Schreib-Tool bleibt unsichtbar und nicht ausführbar, bis du den Schalter aktivierst:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

Ist `allow_edits` deaktiviert, erscheint `seo_save_meta` **nicht in `tools/list`**. Ein Aufruf über `tools/call` scheitert mit dem JSON-RPC-Fehler `-32602`. Der Assistent kann weder schreiben noch das Schreib-Tool über die Tool-Liste entdecken. Aktiviere es nur für einen vertrauenswürdigen Client und eine passende Datenbank.

### 2. Die Modell-Allowlist {#_2-the-model-allowlist}

Jedes modellbezogene Tool darf beim Lesen **und** Schreiben ausschließlich auf ein `HasSEO`-Modell der Allowlist zugreifen. Ein KI-Client kann kein Tool auf eine beliebige Klasse wie `User` oder ein Abrechnungsmodell richten:

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Bei einer nicht freigegebenen Klasse erhält der Assistent ein Fehlerergebnis, etwa `Model [App\Models\User] is not in the MCP allowlist`. Auf die Klasse wird nicht zugegriffen. Ist `models` leer, verwendet der Server die konfigurierten Modelle aus `seo.audit.models` beziehungsweise `seo.sitemap.models`. MCP arbeitet damit auf derselben Modellmenge, die das Paket bereits verwendet, und erweitert sie nicht.

### 3. Nur stdio – kein Netzwerkzugang zum Server {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

Der Server kommuniziert **ausschließlich über stdio**. Der Client startet den Prozess und leitet JSON-RPC-Nachrichten über dessen Ein- und Ausgabe. Es gibt **keinen HTTP-Listener, keinen Port und keinen Netzwerk-Socket**, also keinen Server-Endpunkt, den andere Rechner erreichen könnten. Entsprechend braucht dieser lokale Transport keine Authentifizierung für Fernzugriffe. STDOUT enthält ausschließlich Protokollnachrichten; Diagnosen gehen an STDERR. Dein Client protokolliert sie, beispielsweise Claude Desktop unter `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`. So vermischen sich die Diagnosen des Servers nicht mit dem Protokollstrom.

::: warning Ein Server mit Bearbeitungsfreigabe hat Schreibzugriff auf deine Datenbank
Mit `allow_edits` kann der verbundene Assistent SEO-Datensätze in der Datenbank ändern, gegen die der Befehl läuft. Verwende zum Ausprobieren eine lokale oder Staging-Umgebung, halte die Allowlist eng und deaktiviere Bearbeitungen anschließend wieder. Der Hauptschalter `'enabled' => false` verhindert bereits den Start des Befehls. Der lokale stdio-Transport hindert den KI-Client nicht daran, Tool-Ergebnisse an seinen eigenen Anbieter zu senden. Berücksichtige deshalb auch die Datenkonfiguration des Clients.
:::

## Konfiguration {#configuration}

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

## Server Card zur Erkennung – experimenteller Spezifikationsentwurf {#server-card-discovery-—-experimental-draft-spec}

Eine MCP-**Server-Card** ist ein kleines JSON-Dokument unter einer festgelegten URL. Damit kann ein Client vor dem Verbindungsaufbau Name, Version und Angebot eines Servers erkennen. Rankbeam kann eine solche Karte für deine Website bereitstellen und Agenten-Tools damit auf den MCP-Server hinweisen. Die Funktion ist **standardmäßig deaktiviert**. Sie ergänzt nur die Karte; ihre Aktivierung verändert sonst nichts.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Bei aktivierter Funktion liefert `GET /.well-known/mcp/server-card.json` beispielsweise diese Karte:

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

Die Karte nennt nur die **aktuell aktivierten** Tools. Ein Server mit reinem Lesezugriff bewirbt darin keine gesperrten Betriebs- oder Bearbeitungs-Tools.

::: warning Grundlage ist ein Spezifikationsentwurf
Die Umsetzung folgt dem MCP-Server-Card-**Vorschlag** [SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127), der am 10. September 2026 offen und noch nicht gemergt war. Der festgelegte Pfad, die `$schema`-URL und die genauen Felder sind **nicht endgültig**. Deshalb sind `path`, `schema_url`, `name` und `website_url` konfigurierbar. Dieser Server läuft über **stdio** (`php artisan seo-pro:mcp`); die Karte enthält daher keinen HTTP-Block `remotes`. Sie dient der Erkennung und stellt keinen HTTP-Endpunkt für eine MCP-Verbindung bereit. Prüfe Pfad und Format mit deinem Client, bevor du dich darauf verlässt. Lass die Funktion deaktiviert, wenn du sie nicht brauchst.
:::

## Hinweise zum Protokoll {#protocol-notes}

Ein MCP-Server, der nur Tools anbietet, benötigt eine kleine JSON-RPC-2.0-Schnittstelle. Dieser Server implementiert sie direkt: `initialize` für Versionsaushandlung und Funktionsabgleich, `tools/list`, `tools/call` und `ping`. Er bietet Protokollversion `2025-06-18` an und versteht außerdem `2025-03-26` und `2024-11-05`. Unbekannte Methoden liefern `-32601`, fehlerhafte Zeilen `-32700`. Ein **Tool-Fehler** kommt als lesbares Ergebnis mit `isError` zurück, nicht als Transportfehler. Notifications – Nachrichten ohne `id`, etwa `notifications/initialized` – erhalten keine Antwort.

## Verwendung ohne Oberfläche und Erweiterungen {#headless-extending}

`SeoPro::mcp()` gibt die Tool-Registry zurück. Damit kannst du die angebotenen Tools untersuchen oder eigene registrieren:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Ein eigenes Tool implementiert `McpTool` mit `name`, `description`, `inputSchema`, `isEnabled` und `handle`. Leite es von `AbstractTool` ab, um die Auflösung über die Modell-Allowlist wiederzuverwenden. Dein Tool übernimmt damit dieselbe modellbezogene Zugriffsbeschränkung wie die integrierten Tools.

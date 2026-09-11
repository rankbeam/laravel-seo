---
description: "Samodzielny serwer MCP przez stdio, bez dodatkowych zależności, pozwalający asystentowi AI odczytywać i opcjonalnie edytować SEO witryny Laravel. Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5."
---

# Serwer MCP {#mcp-server}

Serwer MCP Rankbeam pozwala asystentowi AI **odczytywać i opcjonalnie edytować SEO witryny** przez [Model Context Protocol](https://modelcontextprotocol.io). Skieruj klienta MCP (Claude Code / Claude Desktop, Cursor, Codex, …) do swojej aplikacji Laravel, a będzie mógł rozstrzygać metadane strony, uruchamiać audyt, odczytywać ocenę Pro, sprawdzać zasady dla robotów AI i — po Twoim zezwoleniu — zapisywać dane SEO.

To samodzielny serwer stdio **bez dodatkowych zależności**: bez SDK i nowych pakietów. Działa w PHP 8.2–8.4 (Laravel 11), PHP 8.2–8.5 (Laravel 12) i PHP 8.3–8.5 (Laravel 13).

::: tip Funkcja Pro
Serwer MCP jest częścią `rankbeam/laravel-seo-pro`. **Domyślnie pozwala tylko na odczyt**. Edycja wymaga włączenia opcji konfiguracji i listy dozwolonych modeli.
:::

## Co może zrobić asystent {#what-the-assistant-can-do}

### Narzędzia analizy (zawsze dostępne) {#analysis-tools-always-available}

| Narzędzie | Działanie |
|---|---|
| `seo_resolve` | W pełni rozstrzygnięte metadane SEO rekordu modelu: tytuł, opis, adres kanoniczny, robots, Open Graph i JSON-LD, czyli to, co strona faktycznie wyrenderuje. |
| `seo_audit` | [Audyt metadanych](/pl/guide/audit) rekordu modelu lub pierwszych N rekordów w procesie aplikacji. Te same kontrole `seo:audit`, na bieżąco, bez kolejki. |
| `seo_score` | Ostatnia zapisana [ocena SEO Pro](/pl/pro/scoring) (0–100 i ocena literowa) rekordu modelu. |
| `seo_robots_directives` | Zarządzane [dyrektywy robots.txt dla robotów AI](/pl/guide/ai-crawlers) i rozstrzygnięte zasady allow/disallow dla każdego bota. |
| `validate_schema` | Sprawdza obiekt JSON-LD lub rozstrzygnięty graf schematu dozwolonego modelu walidatorem danych strukturalnych rdzenia (wymagania wyników rozszerzonych Google według `@type`). |
| `analyze_robots` | Wiążący wynik allow/disallow **dla każdego znanego robota AI** oraz źródło decyzji: nadpisanie dla bota, zasady według celu lub wartość domyślna. Zasady obowiązują globalnie w witrynie. |
| `debug_social_share` | Rozstrzygnięta karta Open Graph i Twitter rekordu modelu, którą faktycznie zobaczy robot społecznościowy po zastosowaniu wartości zastępczych, wraz z doradczymi uwagami o jej stanie. |
| `check_meta` | Ukierunkowany przegląd metadanych jednego rekordu: rozstrzygnięte title/description/canonical/robots/og:image z długościami i informacją o obecności oraz problemy audytu. |

### Narzędzia treści witryny (zawsze dostępne) {#site-content-tools-always-available}

Narzędzia „rozmowy z witryną” pozwalają asystentowi poznawać treść, wyświetlać listy stron i je przeszukiwać.

| Narzędzie | Działanie |
|---|---|
| `list_pages` | Lista stron (rekordów) **dozwolonego** modelu zarządzanych przez SEO, każda z URL-em i wynikowym tytułem. Obsługuje stronicowanie `limit`/`offset`. |
| `search_pages` | Wyszukiwanie pełnotekstowe w stronach **dozwolonego** modelu: Laravel [Scout](https://laravel.com/docs/scout), gdy model obsługuje wyszukiwanie, lub bezpieczny SQL `LIKE` (title/name/headline i dołączone metadane SEO). Każde trafienie zawiera URL, tytuł i fragment. |

### Narzędzia operacyjne (włączane opcjonalnie) {#ops-tools-opt-in}

Narzędzia operacyjne odczytują stan skanowania i zmieniają konfigurację witryny. Podobnie jak narzędzie edycji **wymagają `allow_edits`**. Na domyślnym serwerze tylko do odczytu są niewidoczne i nieaktywne.

| Narzędzie | Działanie |
|---|---|
| `list_issues` | Bieżące otwarte problemy skanowania SEO (trwały zbiór między przebiegami) i nagłówek ostatniego [przebiegu skanowania](/pl/pro/scan-issues). Filtruj przez `severity` / `type`. |
| `trigger_scan` | Rozpoczyna skanowanie jednego dozwolonego rekordu (zwraca przebieg) lub pełne skanowanie wszystkich celów. Domyślnie kolejkuje, a z `sync: true` wykonuje bezpośrednio. |
| `create_redirect` | Tworzy regułę przekierowania (ścieżka lub regex → cel, status `301`/`302`/`307`/`308`/`410`), używając własnych walidatorów modelu przekierowań. |

### Narzędzie edycji (włączane opcjonalnie) {#edit-tool-opt-in}

| Narzędzie | Działanie |
|---|---|
| `seo_save_meta` | Zapisuje metadane SEO (tytuł, opis, adres kanoniczny, robots, OG, Twitter, JSON-LD) w rekordzie **dozwolonego** modelu przez `saveSEO()`. |

Narzędzia operacyjne i `seo_save_meta` **nie są wymieniane w `tools/list` i nie można ich uruchomić**, dopóki nie włączysz edycji. Zobacz [Bezpieczeństwo](#security). Serwer tylko do odczytu nawet nie informuje asystenta o ich istnieniu.

## Podłączenie klienta AI {#wiring-an-ai-client}

Serwer komunikuje się przez JSON-RPC po **stdio**: klient uruchamia polecenie Artisan i porozumiewa się z nim przez potok. Zarejestruj go w używanych klientach; ten sam serwer działa ze wszystkimi.

::: tip Jedno polecenie, dowolny klient
Każdy poniższy klient uruchamia identyczne polecenie `php artisan seo-pro:mcp` **z katalogu głównego aplikacji**, aby Artisan mógł ją zainicjalizować. Gdy `php` nie znajduje się w `PATH` klienta (częste w Windows lub aplikacjach graficznych, które nie dziedziczą środowiska powłoki), podaj **bezwzględne ścieżki zarówno do `php`, jak i `artisan`**. Artisan startuje z katalogu własnego skryptu `artisan`, więc nie wymaga to `cwd`.
:::

### Claude Code (CLI) {#claude-code-cli}

Jedno polecenie rejestruje serwer. Uruchom je **z katalogu głównego aplikacji**:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Potwierdź połączenie:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

W Windows / Laravel Herd ustaw bezwzględne ścieżki, aby działało niezależnie od katalogu uruchomienia:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Edytuj plik konfiguracji przez **Settings → Developer → Edit Config** lub otwórz go bezpośrednio:

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

W **Windows** użyj bezwzględnej ścieżki `php.exe` i zapisz każdy ukośnik odwrotny podwójnie w JSON:

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

Całkowicie zamknij i ponownie otwórz Claude Desktop. Narzędzia pojawią się pod ikoną narzędzi/wtyczki przy polu wiadomości.

### Cursor {#cursor}

Utwórz `.cursor/mcp.json` w projekcie lub `~/.cursor/mcp.json` dla wszystkich projektów. Podaj bezwzględną ścieżkę `artisan`, aby serwer uruchamiał się z dowolnego miejsca:

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

W Windows użyj bezwzględnego `php.exe` i podwójnie zapisanych ukośników odwrotnych, dokładnie jak w przykładzie Claude Desktop. Włącz serwer w **Settings → MCP**.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Tak jak w Claude Code, w Windows/Herd podaj bezwzględne ścieżki `php` i `artisan`.

To wszystko — asystent może teraz wywoływać narzędzia.

## Przykładowa rozmowa {#a-worked-conversation}

Poniżej rzeczywista wymiana z aplikacją demonstracyjną, witryną instytutu medycznego z 900 stronami, ze skróconymi rzeczywistymi wynikami narzędzi. Dwa modele odczytu na liście dozwolonych to `App\Models\Page` i `App\Models\PublicPage`, a `allow_edits` jest włączone.

> **Ty:** Jak wygląda SEO strony „Accoglienza” (Page id 1)? Popraw oczywiste problemy.

Asystent najpierw wykonuje **audyt** przez `seo_audit { model: "App\\Models\\Page", id: 1 }`:

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

**Rozstrzyga** bieżące metadane, aby sprawdzić faktycznie renderowane wartości, przez `seo_resolve { model: "App\\Models\\Page", id: 1 }`. Znajduje tytuł długości 17 znaków i opis długości 11 znaków, oba poniżej zalecanych minimów. Następnie **zapisuje poprawkę** przez `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

Narzędzie potwierdza zapis i zwraca ponownie rozstrzygnięty rekord:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Na koniec **powtarza audyt**, aby sprawdzić poprawkę. `issue_count` wynosi teraz `0`:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Asystent:** Gotowe. Tytuł (17 znaków) i opis (11 znaków) były poniżej zalecanych minimów. Ustawiłem pełniejszy, opisowy tytuł i opis, a ponowny audyt nie zgłasza problemów (0 problemów).

Zapis przeszedł przez to samo `saveSEO()` co każdy zapis programistyczny, więc wartość jest przechowywana i rozstrzygana dokładnie jak ustawiona w kodzie, przez masowe przypisanie do pól fillable i rzutowania modelu. **Nie** przechodzi przez walidatory długości formularza Filament. Ponowny audyt weryfikuje więc tylko swoje automatyczne kontrole. Poprawność redakcyjna nadal wymaga przeglądu; czysty audyt nie jest certyfikatem jakości tekstu.

### Rozmowa o treści witryny {#talking-to-your-site-s-content}

Narzędzia treści pozwalają asystentowi odpowiadać na pytania **o samą witrynę**, a nie tylko wskazany rekord. To demonstracja „rozmowy z witryną”.

> **Ty:** Które strony wspominają „immunoterapia” i czy ich tytuły są poprawne?

Asystent **wyszukuje** przez `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

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

Trzeci tytuł to tylko „Nuova”, więc asystent **sprawdza metadane tej strony** przez `check_meta { model: "App\\Models\\Page", id: 91 }`:

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

> **Asystent:** Trzy strony wspominają „immunoterapia”. Dwie wyglądają dobrze, ale **/news/nuova-immunoterapia-2026** ma tytuł długości 5 znaków („Nuova”) i nie ma metaopisu. Czy mam przygotować pełniejszy tytuł i opis?

Z włączonym `allow_edits` mógłby od razu poprawić ją przez `seo_save_meta`. W trybie odczytu kończy na zaleceniu. `search_pages` użył tu zastępczego SQL `LIKE` (`"driver": "like"`), ponieważ model nie ma indeksu Scout. Dodaj [Laravel Scout](https://laravel.com/docs/scout), a to samo narzędzie zacznie przejrzyście korzystać z Twojego silnika wyszukiwania.

## Bezpieczeństwo {#security}

Trzy warstwy zapewniają bezpieczne ustawienia domyślne serwera. Wszystkie obowiązują w domyślnym trybie odczytu; ich poluzowanie wymaga świadomej decyzji.

### 1. Edycja wymaga włączenia (domyślnie wyłączona) {#_1-edits-are-gated-off-by-default}

Narzędzie zapisu jest niewidoczne i nieaktywne, dopóki nie włączysz opcji:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

Przy wyłączonym `allow_edits` (domyślnie) `seo_save_meta` **nie jest zwracane przez `tools/list`**, a jego `tools/call` kończy się błędem JSON-RPC `-32602`. Asystent nie może zapisywać ani nawet odkryć takiej możliwości. Włącz to tylko dla zaufanego klienta i bazy danych.

### 2. Lista dozwolonych modeli {#_2-the-model-allowlist}

Każde narzędzie dotyczące modelu, zarówno odczytujące, *jak i* zapisujące, może używać wyłącznie modelu `HasSEO` z listy dozwolonych. Klient AI nigdy nie może wskazać dowolnej klasy, takiej jak `User`, model rozliczeń czy inna:

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Żądanie klasy spoza listy daje wynik błędu czytelny dla asystenta (`Model [App\Models\User] is not in the MCP allowlist`), bez dotykania klasy. Puste `models` korzysta ze skonfigurowanych `seo.audit.models` / `seo.sitemap.models`, więc MCP ma dokładnie ten sam zakres, na którym już działa reszta pakietu, nigdy szerszy.

### 3. Tylko stdio — brak wystawienia do sieci {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

Serwer komunikuje się **wyłącznie przez stdio**: klient uruchamia proces i przekazuje JSON-RPC przez potoki wejścia i wyjścia. Nie ma **nasłuchiwania HTTP, portu ani gniazda**. Nie można dotrzeć do niego z innej maszyny i nie ma zdalnego interfejsu wymagającego uwierzytelniania. STDOUT przenosi tylko ruch protokołu, a diagnostyka trafia do STDERR, które klient zapisuje w logach (np. Claude Desktop w `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`). Przypadkowy wpis logu nie może więc uszkodzić strumienia.

::: warning Traktuj serwer z edycją jak dostęp do zapisu w bazie
`allow_edits` pozwala podłączonemu asystentowi zmieniać wiersze SEO w bazie, na której działa polecenie. Podczas eksperymentów używaj środowiska lokalnego/stagingowego, ogranicz listę dozwolonych modeli i po zakończeniu ponownie wyłącz edycję. Główny przełącznik `'enabled' => false` całkowicie blokuje uruchomienie polecenia. Lokalny transport stdio nie zapobiega wysyłaniu wyników narzędzi przez klienta AI do własnego dostawcy; uwzględnij również ustawienia danych tego klienta.
:::

## Konfiguracja {#configuration}

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

## Server Card (wykrywanie) — eksperymentalny projekt specyfikacji {#server-card-discovery-—-experimental-draft-spec}

**Server Card** MCP to mały dokument JSON pod ustalonym URL-em, dzięki któremu klient może poznać serwer przed połączeniem: nazwę, wersję i możliwości. Rankbeam może udostępnić go dla witryny, informując narzędzia agentów, że *„ta witryna ma serwer MCP, z którym można rozmawiać”*. Funkcja jest **domyślnie wyłączona** i wyłącznie dodatkowa; jej włączenie niczego innego nie zmienia.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Po włączeniu `GET /.well-known/mcp/server-card.json` zwraca kartę podobną do:

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

Karta wymienia tylko **obecnie włączone** narzędzia, więc serwer tylko do odczytu nigdy nie ujawnia przez nią ograniczonych narzędzi operacyjnych/edycji.

::: warning Zgodność z projektem specyfikacji
To implementacja **projektu** propozycji MCP Server Card ([SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127), otwarta propozycja, niescalona na 10 września 2026 r.). Ustalona ścieżka, URL `$schema` i dokładny zestaw pól **nie są ostateczne**, dlatego każdy jest konfigurowalny (`path`, `schema_url`, `name`, `website_url`). Ten serwer działa przez **stdio** (`php artisan seo-pro:mcp`), więc karta nie zawiera bloku HTTP `remotes`. Jest wskazówką do wykrywania, a nie punktem HTTP do połączenia. Zanim na niej polegniesz, sprawdź ścieżkę i strukturę w swoim kliencie. Pozostaw ją wyłączoną, jeśli jej nie potrzebujesz.
:::

## Uwagi o protokole {#protocol-notes}

Serwer MCP udostępniający tylko narzędzia ma niewielki interfejs JSON-RPC 2.0, zaimplementowany tu bezpośrednio: `initialize` (negocjacja wersji i uzgodnienie możliwości), `tools/list`, `tools/call` i `ping`. Deklaruje wersję protokołu `2025-06-18` i rozumie `2025-03-26` oraz `2024-11-05`. Zwraca `-32601` dla nieznanych metod, `-32700` dla niepoprawnych wierszy, a niepowodzenie **narzędzia** jako wynik `isError` czytelny dla asystenta, nie błąd transportu. Powiadomienia, czyli wiadomości bez `id`, np. `notifications/initialized`, poprawnie nie otrzymują odpowiedzi.

## Bez panelu i rozszerzanie {#headless-extending}

`SeoPro::mcp()` zwraca rejestr narzędzi, więc możesz sprawdzać udostępniane narzędzia lub rejestrować własne:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Własne narzędzie implementuje `McpTool` (`name`, `description`, `inputSchema`, `isEnabled`, `handle`). Rozszerz `AbstractTool`, aby użyć rozstrzygania listy dozwolonych modeli i odziedziczyć te same gwarancje bezpieczeństwa co narzędzia wbudowane.

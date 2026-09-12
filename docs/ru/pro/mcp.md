---
description: "MCP-сервер stdio без зависимостей: чтение и разрешённое редактирование SEO Laravel. Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5."
---

# MCP-сервер {#mcp-server}

MCP-сервер Rankbeam позволяет ассистенту ИИ **читать и при необходимости редактировать SEO сайта** через [Model Context Protocol](https://modelcontextprotocol.io). Подключите MCP-клиент — Claude Code, Claude Desktop, Cursor, Codex и другие — к приложению Laravel. Он сможет получать итоговые метаданные страницы, запускать аудит, читать оценку Pro, видеть правила для роботов ИИ и, с вашего разрешения, сохранять SEO-данные.

Это самостоятельный сервер stdio **без зависимостей**: без SDK и новых пакетов. Он работает на PHP 8.2–8.4 с Laravel 11, PHP 8.2–8.5 с Laravel 12 и PHP 8.3–8.5 с Laravel 13.

::: tip Возможность Pro
MCP-сервер поставляется с `rankbeam/laravel-seo-pro`. По умолчанию он **работает только для чтения**. Редактирование включается явно через флаг конфигурации и список разрешённых моделей.
:::

## Что умеет ассистент {#what-the-assistant-can-do}

### Инструменты анализа: доступны всегда {#analysis-tools-always-available}

| Инструмент | Назначение |
| --- | --- |
| `seo_resolve` | Полные итоговые SEO-метаданные записи модели: заголовок, описание, canonical, robots, Open Graph, JSON-LD — то, что страница действительно вывела бы. |
| `seo_audit` | [Аудит метаданных](/ru/guide/audit) записи модели или первых N записей внутри процесса: те же проверки `seo:audit`, сразу, без очереди. |
| `seo_score` | Последняя сохранённая [SEO-оценка Pro](/ru/pro/scoring) для записи модели: 0–100 и буквенная оценка. |
| `seo_robots_directives` | Управляемые [директивы robots.txt для роботов ИИ](/ru/guide/ai-crawlers) и итоговые разрешения или запреты для каждого бота. |
| `validate_schema` | Проверка объекта JSON-LD или итогового графа схем разрешённой модели валидатором структурированных данных ядра: требования Google к расширенным результатам по `@type`. |
| `analyze_robots` | Авторитетное решение «разрешено/запрещено» **для каждого известного робота ИИ** и его основание: переопределение бота, правило по назначению или значение по умолчанию. Политика общая для сайта. |
| `debug_social_share` | Итоговые Open Graph и Twitter Card записи модели после резервных значений — то, что действительно увидел бы социальный робот, — с рекомендациями по состоянию карточки. |
| `check_meta` | Сводка состояния метаданных одной записи: итоговые title/description/canonical/robots/og:image, длина и наличие значений, а также проблемы аудита. |

### Инструменты контента сайта: доступны всегда {#site-content-tools-always-available}

Инструменты для «разговора с сайтом» превращают сервер в ассистента, который понимает контент, умеет перечислять страницы и искать по ним.

| Инструмент | Назначение |
| --- | --- |
| `list_pages` | Список страниц — записей — **разрешённой** модели с управляемым SEO: URL и итоговый заголовок каждой. Поддерживает пагинацию `limit`/`offset`. |
| `search_pages` | Полнотекстовый поиск по страницам **разрешённой** модели: через Laravel [Scout](https://laravel.com/docs/scout), если модель поддерживает поиск, иначе безопасный SQL `LIKE` по title/name/headline и присоединённым SEO-метаданным. Для каждого совпадения возвращает URL, заголовок и фрагмент текста. |

### Эксплуатационные инструменты: включаются явно {#ops-tools-opt-in}

Эти инструменты читают состояние сканирования и меняют конфигурацию сайта. Как и инструмент редактирования, они **защищены флагом `allow_edits`**: на сервере только для чтения, то есть по умолчанию, они скрыты и не работают.

| Инструмент | Назначение |
| --- | --- |
| `list_issues` | Текущие открытые проблемы SEO-сканирования — постоянный набор между запусками — и общие данные последнего [сканирования](/ru/pro/scan-issues). Фильтры `severity` / `type`. |
| `trigger_scan` | Запуск сканирования: одной разрешённой записи с возвратом запуска либо всех целей. По умолчанию через очередь; в текущем процессе — с `sync: true`. |
| `create_redirect` | Создание правила редиректа: исходный путь или regex → цель, статус `301`/`302`/`307`/`308`/`410`. Использует собственные валидаторы модели редиректов. |

### Инструмент редактирования: включается явно {#edit-tool-opt-in}

| Инструмент | Назначение |
| --- | --- |
| `seo_save_meta` | Запись SEO-метаданных — title, description, canonical, robots, OG, Twitter, JSON-LD — в запись **разрешённой** модели через `saveSEO()`. |

Эксплуатационные инструменты и `seo_save_meta` **не объявляются в `tools/list` и не выполняются**, пока редактирование не включено: см. [Безопасность](#security). Сервер только для чтения даже не сообщает ассистенту об их существовании.

## Подключение клиента ИИ {#wiring-an-ai-client}

Сервер передаёт JSON-RPC через **stdio**: клиент запускает команду Artisan и общается с ней по каналу ввода-вывода. Зарегистрируйте сервер в своих клиентах — один и тот же сервер подходит для всех.

::: tip Одна команда для любого клиента
Все клиенты ниже выполняют одну команду запуска: `php artisan seo-pro:mcp` **из корня приложения**, чтобы Artisan мог его загрузить. Если `php` отсутствует в `PATH` клиента, что часто бывает в Windows или GUI-приложениях, не наследующих окружение оболочки, задайте **абсолютные пути и к `php`, и к `artisan`**. Artisan запускает приложение из каталога своего скрипта `artisan`, поэтому `cwd` не нужен.
:::

### Claude Code: CLI {#claude-code-cli}

Для регистрации достаточно одной команды. Выполните её **из корня приложения**:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Убедитесь, что подключение установлено:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

В Windows с Laravel Herd задайте абсолютные пути, чтобы запуск не зависел от текущего каталога:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Отредактируйте файл конфигурации через **Settings → Developer → Edit Config** — названия английского интерфейса — или откройте его напрямую:

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

В **Windows** используйте абсолютный путь к `php.exe` и удваивайте каждую обратную косую черту в JSON:

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

Полностью закройте и снова откройте Claude Desktop. Инструменты появятся за значком инструментов или подключения в строке сообщения.

### Cursor {#cursor}

Создайте `.cursor/mcp.json` в проекте или `~/.cursor/mcp.json` для всех проектов. Задайте абсолютный путь к `artisan`, чтобы запуск работал из любого каталога:

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

В Windows используйте абсолютный путь к `php.exe` и удвоенные обратные косые черты, как в примере Claude Desktop выше. Включите сервер в **Settings → MCP** — так пункт называется в английском интерфейсе.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Как и для Claude Code, в Windows/Herd передавайте абсолютные пути к `php` и `artisan`.

Готово: теперь ассистент может вызывать инструменты.

## Разобранный пример диалога {#a-worked-conversation}

Ниже реальный диалог с демонстрационным приложением — сайтом медицинского института на 900 страниц. Фактический вывод инструментов сокращён. В список моделей для чтения входят `App\Models\Page` и `App\Models\PublicPage`, флаг `allow_edits` включён.

> **Вы:** Как обстоят дела с SEO страницы "Accoglienza" (Page id 1)? Исправь очевидные проблемы.

Сначала ассистент проводит **аудит** — `seo_audit { model: "App\\Models\\Page", id: 1 }`:

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

Затем он **получает итоговые метаданные**, чтобы увидеть реальный вывод, через `seo_resolve { model: "App\\Models\\Page", id: 1 }`. Заголовок содержит 17 символов, описание — 11, оба короче рекомендованного минимума. Поэтому ассистент **сохраняет исправление** через `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

Инструмент подтверждает запись и возвращает заново вычисленные итоговые данные:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Наконец, ассистент **повторяет аудит**, чтобы проверить исправление: `issue_count` теперь равен `0`.

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Ассистент:** Готово. Заголовок из 17 символов и описание из 11 были короче
> рекомендованного минимума. Я задал более полные описательные значения,
> и повторный аудит теперь не находит проблем: 0.

Запись прошла через тот же `saveSEO()`, что используется при любом программном сохранении. Значение хранится и обрабатывается так же, как заданное из кода: массовое присваивание через разрешённые поля и приведения типов модели. Валидаторы длины формы Filament **не вызываются**, поэтому повторный аудит подтверждает только свои автоматические проверки. Редакционная точность всё ещё требует проверки; чистый аудит не удостоверяет качество текста.

### Диалог с содержимым сайта {#talking-to-your-site-s-content}

Инструменты контента позволяют ассистенту отвечать **о самом сайте**, а не только об указанной записи. Это демонстрация «разговора с сайтом».

> **Вы:** Какие страницы упоминают "immunoterapia" и всё ли в порядке с их заголовками?

Ассистент выполняет **поиск** — `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

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

Третий заголовок — всего лишь "Nuova", поэтому он **проверяет метаданные этой страницы** через `check_meta { model: "App\\Models\\Page", id: 91 }`:

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

> **Ассистент:** "immunoterapia" упоминают три страницы. Две выглядят нормально, но у
> **/news/nuova-immunoterapia-2026** заголовок из 5 символов ("Nuova") и нет
> метаописания. Написать для неё более полный заголовок и описание?

При включённом `allow_edits` ассистент мог бы сразу исправить это через `seo_save_meta`; в режиме чтения он ограничивается рекомендацией. Здесь `search_pages` использовал резервный SQL `LIKE` — `"driver": "like"`, — поскольку модель не индексируется Scout. Добавьте [Laravel Scout](https://laravel.com/docs/scout), и тот же инструмент начнёт прозрачно искать через ваш поисковый движок.

## Безопасность {#security}

По умолчанию сервер защищён тремя слоями. Все три действуют в стандартном режиме только для чтения; ослабляете их вы сами и намеренно.

### 1. Редактирование закрыто флагом и выключено по умолчанию {#_1-edits-are-gated-off-by-default}

Инструмент записи скрыт и не работает, пока вы не переключите флаг:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

При выключенном `allow_edits` — значении по умолчанию — `seo_save_meta` **не возвращается в `tools/list`**, а его `tools/call` завершается ошибкой JSON-RPC `-32602`. Ассистент не может записывать и даже обнаружить такую возможность. Включайте её только для клиента и базы данных, которым доверяете.

### 2. Список разрешённых моделей {#_2-the-model-allowlist}

Любой инструмент, работающий с моделью, — и для чтения, *и* для записи — может обращаться только к модели с `HasSEO` из списка разрешённых. Клиент ИИ не может направить инструмент на произвольный класс: `User`, модель платежей или любой другой.

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

При запросе неразрешённого класса инструмент возвращает читаемую ассистентом ошибку `Model [App\Models\User] is not in the MCP allowlist` и не обращается к классу. Если `models` пуст, используются настроенные `seo.audit.models` / `seo.sitemap.models`. Так MCP работает ровно с теми же моделями, что и остальной пакет, без расширения доступа.

### 3. Только stdio: сервер не открыт в сеть {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

Сервер общается **только через stdio**: клиент запускает процесс и передаёт JSON-RPC через его ввод и вывод. **Нет HTTP-слушателя, порта или сокета**: к серверу нельзя обратиться с другой машины, и аутентифицировать удалённый доступ не требуется, поскольку его нет. STDOUT содержит только протокольные сообщения; вся диагностика идёт в STDERR. Клиент записывает её в журнал — например, Claude Desktop использует `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`, — поэтому случайная строка лога не испортит поток протокола.

::: warning Сервер с редактированием равнозначен праву записи в вашу БД
`allow_edits` позволяет подключённому ассистенту менять SEO-строки в базе, с которой работает команда. Для экспериментов используйте локальную или тестовую среду, держите список разрешённых моделей узким и снова выключайте редактирование после работы. Главный переключатель `'enabled' => false` вообще запрещает запуск команды. Локальный транспорт stdio не мешает клиенту ИИ отправлять результаты инструментов своему провайдеру; учитывайте и настройки данных этого клиента.
:::

## Конфигурация {#configuration}

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

## Server Card для обнаружения: экспериментальная спецификация {#server-card-discovery-—-experimental-draft-spec}

MCP **Server Card** — небольшой JSON-документ по общеизвестному URL, позволяющий клиенту узнать о сервере до подключения: его название, версию и возможности. Rankbeam может отдавать такую карточку для сайта, сообщая инструментам агентов: *«у этого сайта есть MCP-сервер, с которым можно общаться»*. По умолчанию она **выключена** и лишь добавляет обнаружение; её включение ничего больше не меняет.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

После включения `GET /.well-known/mcp/server-card.json` возвращает примерно такую карточку:

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

В карточке перечислены только **включённые сейчас** инструменты, поэтому сервер только для чтения не объявляет через неё закрытые флагом эксплуатационные инструменты и редактирование.

::: warning Основано на проекте спецификации
Реализация следует **проекту** MCP Server Card: [SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127), открытому предложению, ещё не принятому по состоянию на 10 сентября 2026 года. Общеизвестный путь, URL `$schema` и точный состав полей **не окончательны**, поэтому каждый параметр настраивается: `path`, `schema_url`, `name`, `website_url`. Сервер работает через **stdio** — `php artisan seo-pro:mcp`, — поэтому карточка не содержит HTTP-блока `remotes`. Это подсказка для обнаружения, а не HTTP-endpoint для подключения. Прежде чем полагаться на карточку, сверяйте путь и структуру со своим клиентом; если она не нужна, оставьте выключенной.
:::

## Примечания о протоколе {#protocol-notes}

MCP-сервер только с инструментами — небольшой интерфейс JSON-RPC 2.0. Здесь он реализован напрямую: `initialize` согласует версию и возможности, далее поддерживаются `tools/list`, `tools/call` и `ping`. Сервер объявляет версию протокола `2025-06-18` и понимает `2025-03-26` и `2024-11-05`. Для неизвестных методов возвращается `-32601`, для некорректных строк — `-32700`. Ошибка **инструмента** возвращается как результат `isError`, который может прочитать ассистент, а не как ошибка транспорта. Уведомления — сообщения без `id`, например `notifications/initialized`, — правильно остаются без ответа.

## Использование без интерфейса и расширение {#headless-extending}

`SeoPro::mcp()` возвращает реестр инструментов, поэтому можно исследовать доступные инструменты или зарегистрировать собственный:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Собственный инструмент реализует `McpTool`: `name`, `description`, `inputSchema`, `isEnabled`, `handle`. Наследуйте `AbstractTool`, чтобы повторно использовать проверку модели по списку разрешённых и получить те же гарантии безопасности, что и встроенные инструменты.


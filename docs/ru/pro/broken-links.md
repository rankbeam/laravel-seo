---
description: "Ограниченный возобновляемый обходчик регистрирует неработающие ссылки: внутренние маршруты, исправляемые перенаправлением одним нажатием, и по желанию внешние ссылки. По умолчанию выключен."
---

# Обходчик битых ссылок {#broken-link-crawler}

**Ограниченный возобновляемый обходчик** проходит сайт, следует ссылкам каждой страницы и записывает те, по которым нельзя получить целевую страницу: битые **внутренние** ссылки (неработающий маршрут на вашем хосте, исправляемый перенаправлением одним нажатием) и по желанию битые **внешние** ссылки. По умолчанию он **выключен**.

Три принципа устройства:

- **Ограничения и возобновление.** Обход выполняется множеством небольших заданий очереди, каждое с лимитом страниц. Они отправляют продолжение до завершения запуска или достижения лимитов. Весь запуск тоже ограничен (по умолчанию 2000 страниц; `null` явно включает неограниченный режим и никогда не используется по умолчанию), а остальные ограничения пакетов и времени сохраняются. Подбирайте лимиты и задержки под возможности сайта и сервера.
- **Безопасность по умолчанию.** Область по умолчанию — `internal_only`: проверяются только ссылки собственного хоста, без сторонних запросов. Каждая загрузка, внутренняя или внешняя, проходит общий **SsrfGuard**: список разрешённых схем, область хостов и отклонение частных адресов. Проверка внешних ссылок включается явно и тоже защищена.
- **Отдельно от SEO-оценки.** Находки хранятся в собственных таблицах и никогда не записываются в `seo_scan_issues` или оценку 0–100. Оценка страницы не меняется от наличия битых исходящих ссылок. Это отдельная эксплуатационная задача.

## Что вы получаете {#what-you-get}

В панели Filament (только при включённой функции):

- **Сводка битых ссылок** — число открытых внутренних/внешних проблем и последний обход со ссылкой на таблицу находок.
- **Обход битых ссылок** — ход текущего обхода: пройденные страницы, проверенные ссылки, найденные битые ссылки.
- **Битые ссылки по сканированиям** — динамика недавних обходов.
- **Ресурс находок** — каждая битая ссылка `source → target` с фильтрами и возможностью исправить внутреннюю ссылку перенаправлением.

Без интерфейса те же данные доступны через команды `seo-pro:broken-links-*`.

## Почему по умолчанию выключен {#why-it-s-off-by-default}

В отличие от пассивного вывода и расчёта оценок, обходчик **выполняет сетевые запросы** и требует небольшой инфраструктуры. Его включение должно быть сознательным, а не незаметным следствием установки:

- Две основные таблицы **требуют публикации** (как все миграции Pro) и выполнения миграций до запросов интерфейса. Типизированные проверки также используют `seo_broken_link_inspections`.
- Обход **ставится в отдельную очередь** и требует **воркера**: без него выполнение не продвигается.
- Подтверждение выполняется **между сканированиями** (ниже), поэтому функция рассчитана на работу **по расписанию** в течение недель, а не на мгновенный результат сразу после включения.

## Настройка {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Затем выполните миграции: `seo-pro:install` публикует и выполняет все миграции Pro (идемпотентно, повторный запуск безопасен):

```bash
php artisan seo-pro:install
```

Запустите **отдельный воркер** очереди обхода. Она отдельная (`seo-broken-links`), чтобы долгий обход не задерживал пользовательские задания:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Проверьте подключение: `seo:doctor` проверяет флаг, таблицы и то, что очередь обхода использует настоящее соединение (не `sync`), указывая точное исправление для каждого случая:

```bash
php artisan seo:doctor
```

Полная схема нескольких очередей (Redis, Supervisor, отдельные соединения) и настройка пакетов обработки описаны в [рабочем окружении](/ru/pro/production).

## Запуск обхода {#running-a-crawl}

Запустите его действием **«Сканировать сейчас»** в панели или без интерфейса:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Обе команды только **ставят обход в очередь**; фактическую работу выполняет воркер.

## Когда ссылка отмечается битой {#how-a-link-gets-flagged}

Ссылка объявляется битой, только если `seo-pro.broken_links.mark_broken_after_failures` **последовательных обходов** не смогли обратиться к ней (любой успех обнуляет счётчик; по умолчанию **3**). Единичный временный сбой не помечает ссылку. Поэтому обход рассчитан **на расписание**, а не однократный запуск. При еженедельной частоте и стандартном пороге три неудачных обхода подтверждают ссылку примерно через две недели после первого наблюдения или максимум примерно через три недели после поломки. Для более быстрого подтверждения увеличьте частоту или уменьшите порог.

## Типизированные проверки ссылок {#typed-link-inspections}

Помимо доступности, каждая ссылка проходит **типизированные проверки**: классификацию нарушений оформления URL, включая непоследовательный завершающий слеш, некорректное кодирование, цепочки перенаправлений, href с `javascript:`, неработающие якоря внутри страницы, неописательный текст ссылок и другое. Каждая проверка имеет фиксированную **серьёзность** (`critical` · `warning` · `notice` — те же термины, что у [проблем сканирования](/ru/pro/scan-issues), поэтому одна проверка CI охватывает оба вида) и записывается на каждый обход в `seo_broken_link_inspections`. В отличие от *находки* битой ссылки, подтверждаемой несколькими последовательными обходами, проверка — снимок отдельного запуска: она появляется **сразу, при первом обходе**, как и нужно для CI.

### Справочник проверок {#inspection-reference}

| Проверка | Серьёзность | Что отмечает | К чему применяется |
| --- | --- | --- | --- |
| `broken_link` | critical | Цель вернула HTTP ≥ 400 | Любая ссылка |
| `redirect_chain` | notice · warning | Цель доступна только через перенаправление; `warning` после `redirect_chain_warning_hops` | Любая ссылка |
| `link_unreachable` | notice | Недоступна при этом обходе (сетевая ошибка, тайм-аут, блокировка); возможно временно | Любая ссылка |
| `insecure_link` | warning | Ссылка `http://` на сайте `https` (понижение защищённости транспорта) | Любая ссылка |
| `trailing_slash` | notice | Внутренний путь нарушает объявленное соглашение о завершающем слеше (**выключено, пока не задано `trailing_slash`**) | Внутренняя |
| `double_slash_url` | warning | Внутренний путь содержит `//` (пустой сегмент) | Внутренняя |
| `duplicate_query_param` | notice | Ключ запроса повторяется (`?a=1&a=2`); синтаксис массивов `key[]` исключён | Внутренняя |
| `non_ascii_url` | notice | Во внутреннем пути есть незакодированные символы вне ASCII | Внутренняя |
| `uppercase_url` | notice | Во внутреннем пути есть заглавные буквы (проверьте варианты регистра, отдаваемые отдельно) | Внутренняя |
| `underscore_in_url` | notice | Внутренний путь использует подчёркивания (для SEO предпочтителен разделитель-дефис) | Внутренняя |
| `javascript_link` | warning | href ссылки использует `javascript:` — это не обычный адрес для обхода | Любая ссылка-якорь |
| `missing_fragment` | warning | `#fragment` на ту же страницу без соответствующего `id`/`name` на странице | Та же страница |
| `non_descriptive_anchor` | notice | Общий текст ссылки («нажмите здесь», «читать далее») или голый URL | Любая ссылка-якорь |
| `absolute_internal_link` | notice | Внутренняя ссылка записана абсолютным URL вместо пути от корня | Внутренняя |

Проверки оформления (завершающий слеш, регистр, кодирование, двойной слеш…) применяются только к **внутренним** ссылкам: стиль URL внешнего сайта не в вашей власти. Проверки перенаправлений, поломок, недоступности и незащищённого транспорта применяются ко всем ссылкам. Ссылки на собственные маршруты фреймворка и статические ресурсы пропускаются при типизированных проверках, чтобы первый запуск не создавал лишнего шума (см. `exclude_paths` / `exclude_extensions` ниже).

Каждая ссылка запрашивается **точно по указанному URL**, удаляется только `#fragment`. Предварительная нормализация не выполняется, поэтому канонизирующее серверное перенаправление, например `/about/ → /about`, действительно наблюдается и появляется как `redirect_chain`. Проверяется каждая отличающаяся запись ссылки на странице: `/page#ok` и `/page#missing` (или `/a//b` и `/a/b`) оцениваются отдельно, а не только первая. Базовая *находка* битой ссылки всё ещё объединяет все псевдонимы цели в одну идентичность. Строки проверок записываются на `(page, target, inspection)`, поэтому цель с несколькими неработающими якорями создаёт одну строку `missing_fragment` с примером, а не строку на каждый якорь.

### Настройка классификации {#tuning-the-taxonomy}

Все настройки находятся в `seo-pro.broken_links.inspections`:

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

**Отключите одно правило**, удалив его класс из `rules`; **отключите всю классификацию** через `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`. Два правила стоит знать заранее:

- `trailing_slash` **выключено до объявления соглашения** (`'always'` / `'never'`), поскольку сайт, отдающий и `/x`, и `/x/` с `200`, не имеет «неправильного» стиля. А если сервер канонизирует через перенаправление, это уже показывается как `redirect_chain`.
- `absolute_internal_link` срабатывает на **каждую** внутреннюю ссылку в абсолютной форме. Если это соглашение вашего сайта, появится много безвредных строк уровня `notice`; удалите правило из `rules`, чтобы их не показывать.

## Непрерывная интеграция {#continuous-integration}

И проверка ссылок, и [SEO-аудит](/ru/pro/scan-issues) могут **завершить сборку с ошибкой** и **записать артефакт отчёта**, превращая Rankbeam из панели в обязательную проверку качества. `--fail-on-error` соответствует уровню `critical` (битая ссылка, критическая проблема); `--fail-on-warning` срабатывает на `critical` **или** `warning` (отдельного уровня «error» нет).

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` записывает артефакт (для каталога имя файла определяется автоматически); `--format` принимает `json` (по умолчанию), `md` или `html`. JSON предназначен для разбора в конвейере; HTML — самостоятельная страница для прикрепления к запуску.

### GitHub Actions {#github-actions}

Обходчик загружает страницы по HTTP, поэтому в CI он должен иметь доступ к контенту: локально запущенному приложению (ниже) или staging-URL через `SEO_PRO_BROKEN_LINKS_BASE_URL`. Модели/карта сайта должны быть зарегистрированы, чтобы было откуда взять начальные URL.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## Расписание {#scheduling}

Зарегистрируйте обход и его служебные задачи в `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Справочник команд {#command-reference}

| Команда | Назначение |
| --- | --- |
| `seo-pro:broken-links-scan` | Ставит ограниченный возобновляемый обход в очередь (`--scope=internal_only\|internal_and_external`, `--url=*` для дополнительных начальных URL) |
| `seo-pro:broken-links-status` | Сводка последнего обхода, открытые битые ссылки и количества проверок этого запуска; **проверка CI** (`--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html`) |
| `seo-pro:broken-links-cancel` | Отменяет выполняющийся или ожидающий обход (`{run?}` — по умолчанию последний активный) |
| `seo-pro:broken-links-recover` | Отмечает обходы, брошенные остановившимся воркером, как неудачные (устаревшая аренда) |
| `seo-pro:broken-links-prune` | Применяет сроки хранения обходчика (старые запуски и устранённые находки) |

## Настройка лимитов {#tuning}

Ограничения обхода — страницы за запуск, ссылки на страницу, лимиты заданий, жёсткий бюджет времени и задержки для каждого хоста — находятся в `seo-pro.broken_links`. Значения по умолчанию консервативны и конечны; прежде чем увеличивать их, прочитайте [таблицу настройки пакетов обработки](/ru/pro/production).


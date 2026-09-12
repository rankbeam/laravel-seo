---
description: "Все функции Pro — сканирование, перенаправления и журнал 404 — работают без Filament. Справочник команд для управления Pro целиком через artisan."
---

# Работа без интерфейса {#headless-usage}

Все функции Pro — сканирование, перенаправления и журнал 404 — работают без интерфейса: они находятся в движке и не требуют Filament. Панель — лишь интерфейс управления; эти команды предоставляют соответствующие возможности без неё.

## Справочник команд {#command-reference}

### Установка и проверка состояния {#setup-health-check}

| Команда | Назначение |
|---|---|
| `seo-pro:install` | Публикует `config/seo-pro.php` и миграции Pro, выполняет их и выводит дальнейшие шаги (`--no-migrate`, `--force`) |
| `seo:doctor` | Разовая проверка состояния: URL приложения, таблицы ядра и Pro, цели сканирования, карта сайта, очереди разных задач, необязательные функции и эксплуатационное состояние; каждое предупреждение содержит точное исправление (`--json` для мониторинга) |

`seo-pro:install` — документированный путь установки: миграции Pro требуют публикации (пакет никогда не загружает их автоматически), поэтому установщик превращает один лишь `composer require` в работающую схему. Он идемпотентен — повторяйте в любое время.

`seo:doctor` не выполняет сетевых запросов и никогда не выводит секретные значения (проверка ИИ сообщает только, *задана* ли настроенная переменная ключа). Команда проверяет конфигурацию и историю недавних запусков; она не может доказать, что внешний cron или воркер действительно работает. Ненулевой код возвращается только при критическом сбое — отсутствии обязательной таблицы, поэтому локальное окружение разработки с предупреждениями всё ещё завершается успешно. `--json` присваивает каждой проверке стабильный `id` для автоматической обработки. Запускайте команду сразу после [установки](/ru/pro/installation) и в CI.

### Сканирование {#scanning}

| Команда | Назначение |
|---|---|
| `seo-pro:scan` | Ставит полное сканирование всех зарегистрированных целей в очередь (`--sync` для синхронного выполнения; **проверка CI** `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=json\|md\|html` требует `--sync`) |
| `seo-pro:scan-status` | Сводка последнего запуска и открытые проблемы, начиная с самых серьёзных (`--limit=20`, `--severity=critical\|warning\|notice`) |
| `seo-pro:scan-recover` | Отмечает запуски, брошенные остановившимся воркером очереди, как неудачные |
| `seo-pro:scan-prune` | Удаляет завершённые запуски и их проблемы по истечении срока хранения |

### Обходчик битых ссылок {#broken-link-crawler}

По умолчанию выключен: включите `seo-pro.broken_links.enabled` и выполните миграции его двух таблиц (`seo-pro:install` публикует их). Обход выполняется ограниченными заданиями очереди; запустите отдельный воркер для его очереди. Настройка описана в [рабочем окружении](/ru/pro/production).

| Команда | Назначение |
|---|---|
| `seo-pro:broken-links-scan` | Ставит ограниченный возобновляемый обход в очередь (`--scope=internal_only\|internal_and_external`, `--url=*` для дополнительных начальных URL) |
| `seo-pro:broken-links-status` | Сводка последнего обхода, открытые находки и [типизированные проверки](/ru/pro/broken-links#typed-link-inspections) этого запуска; **проверка CI** (`--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=`) |
| `seo-pro:broken-links-cancel` | Отменяет выполняющийся или ожидающий обход (`{run?}` — по умолчанию последний активный) |
| `seo-pro:broken-links-recover` | Отмечает обходы, брошенные остановившимся воркером, как неудачные (устаревшая аренда) |
| `seo-pro:broken-links-prune` | Применяет сроки хранения обходчика (старые запуски и устранённые находки) |

### Перенаправления и 404 {#redirects-404s}

| Команда | Назначение |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Создаёт правило перенаправления (`--code=301`, `--regex`, `--no-preserve-query`, `--note=`) |
| `seo-pro:404-list` | Показывает записанные 404, начиная с самых частых (`--status=new\|ignored\|redirected\|all`, `--limit=20`) |
| `seo-pro:redirects-flush-hits` | Записывает накопленные в кеше счётчики перенаправлений в БД при `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Удаляет устаревшие записи 404 и соблюдает лимит строк |

### Чек-лист страницы {#on-page-checklist}

| Команда | Назначение |
|---|---|
| `seo-pro:checklist {model} {id}` | Чек-лист одной модели с учётом ключевого слова и результатами pass/warn/fail (`--json`, `--strict`, `--locale=`) — см. [чек-лист страницы](/ru/pro/on-page-checklist) |

Тот же чек-лист доступен как `SeoPro::checklistFor($model)`. Это инструмент редакторской работы (размещение ключевого слова, длина, изображения, внутренние ссылки), **а не** [SEO-оценка](/ru/pro/scoring).

### Search Console (только чтение) {#search-console-read-only}

| Команда | Назначение |
|---|---|
| `seo-pro:search-console` | Страницы с открытыми проблемами **и** поисковым трафиком, начиная с самых значимых упущенных возможностей (`--view=attention`, по умолчанию) |
| `seo-pro:search-console --view=pages` | Ведущие страницы по показам/кликам/CTR/позиции |
| `seo-pro:search-console --view=queries` | Ведущие запросы (`--days=`, `--limit=`, `--json`) |

Те же метрики доступны как `SeoPro::searchConsole()` — см. [Search Console](/ru/pro/search-console). По умолчанию выключено; строго только чтение.

### Помощь ИИ {#ai-assist}

| Команда | Назначение |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Предложения заголовка/описания в JSON (`--field=title\|description\|all`) — см. [помощь ИИ](/ru/pro/ai-assist) |
| `seo-pro:ai-suggest --issue={id}` | Объяснение исправления проблемы сканирования простым языком, в JSON |

### Устранение 404 за один шаг {#resolving-a-404-in-one-step}

`--from-404={path}` — вариант действия *создания перенаправления* одним нажатием из монитора 404, доступный через командную строку: команда создаёт правило **и** отмечает соответствующую запись журнала как перенаправленную, связывая её с новым правилом:

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

Команда запускает те же валидаторы, что и форма Filament: неверные регулярные выражения, слишком длинные значения и внешние адреса назначения вне списка разрешённых отклоняются до любой записи.

## Рекомендуемое расписание {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Для каждой повторяющейся команды выше рекомендованная частота указана в [настройке рабочего окружения](/ru/pro/production), вместе со схемой очередей, настройками воркеров, повторами/восстановлением, сроками хранения и структурированной **телеметрией** каждого завершённого запуска (загруженные страницы, проверенные ссылки, заблокированные URL, длительность, задержка очереди).

## Для чего нужен интерфейс Filament? {#what-needs-the-filament-ui}

Для работы функций он не требуется. Весь движок — конвейер сканирования, отслеживание проблем, сопоставление перенаправлений, журнал 404, очистка и восстановление — одинаков с Filament и без него. Панель добавляет *представления*: ход сканирования и статистику серьёзности, просмотр проблем с фильтрами и модальными окнами страниц, кнопки игнорирования/повторного открытия, CRUD-формы перенаправлений и таблицу 404 с действием одним нажатием. Для игнорирования/повторного открытия проблем пока нет отдельной команды: используйте панель или модель `SEOScanIssue` (`markIgnored()` / `reopen()`) в tinker или своём коде.


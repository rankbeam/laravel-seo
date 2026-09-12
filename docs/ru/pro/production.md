---
description: "Эксплуатация Pro под нагрузкой: отдельные очереди, планировщик, повторы, восстановление, сроки хранения и телеметрия — схема без зависимости от Filament, работающая на сайте примерно с 900 страницами."
---

# Настройка рабочего окружения {#production-setup}

Повседневные задачи Pro — сканирования сайта, обход битых ссылок, необязательная запись счётчиков перенаправлений и очистка 404 — работают через очередь и планировщик Laravel. Это основное руководство по эксплуатации под нагрузкой: отдельные очереди, планировщик, политика повторов и восстановления, сроки хранения и телеметрия для наблюдения. Описана воспроизводимая схема работающей установки примерно с 20 тысячами посещений в день и 900 страницами.

Всё здесь **не зависит от Filament**: движок, команды, очереди и телеметрия одинаковы с панелью и без неё. Filament добавляет представления поверх них, не меняя планирование или обработку задач.

[[toc]]

## Безопасный порядок внедрения {#safe-rollout-order}

Выполняйте по порядку: каждый шаг можно проверить до следующего:

1. **Установка** — опубликуйте конфигурацию и миграции, затем выполните их:

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` публикует `config/seo-pro.php` и миграции Pro, затем выполняет `migrate`. Миграции Pro **требуют публикации** (пакет никогда не загружает их автоматически), поэтому именно этот шаг превращает один лишь `composer require` в работающую схему. Он идемпотентен — повторяйте в любое время; добавьте `--force` для перезаписи опубликованных файлов или `--no-migrate` для публикации без миграций.

2. **Зарегистрируйте цели сканирования** в сервис-провайдере (`AppServiceProvider::boot()`):

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Проверьте** подключение перед включением фоновой работы:

   ```bash
   php artisan seo:doctor
   ```

   Устраните каждое предупреждение: в нём указана точная команда или строка конфигурации. В CI добавьте `--json` и ориентируйтесь на стабильные идентификаторы проверок.

4. **Настройте очереди и планировщик** (ниже), разверните воркер очереди и запись cron для `schedule:run`.

5. **Необязательные функции включайте последними**: обходчик битых ссылок, помощь ИИ и Search Console по умолчанию выключены. Обходчику нужны миграции таблиц (шаг 1 уже опубликовал их) и отдельный воркер (ниже).

**Обновление до Pro 2.41.0:** приостановите воркеры сканирования, опубликуйте миграции через `php artisan vendor:publish --tag=seo-pro-migrations --force`, выполните `php artisan migrate`, затем перезапустите воркеры и выполните `php artisan seo:doctor`. Требуются новая таблица `seo_scan_target_completions` и столбец `seo_scan_runs.target_tracking`. Записи о завершении для каждой пары запуск/цель не дают повторным конечным результатам увеличивать счётчики: учитывается первый принятый результат. Старые запуски в очереди без обработанных целей продолжаются. Частично обработанные до обновления запуски сохраняют историю, но при следующей доставке закрываются с указанием начать новое сканирование. Цели, исчерпавшие попытки, повторяйте в новом запуске. Для отката сначала остановите воркеры и верните прежний код, затем откатывайте миграцию; сохраните резервную копию БД до обновления, если нужно отменить и последующие сканирования.

## Отдельные очереди для разных задач {#dedicated-queues-per-workload}

Долгое сканирование или обход не должны задерживать пользовательские задания (почту, уведомления). Выделите каждому виду SEO-задач отдельную очередь и воркер.

Конвейер сканирования и обходчик битых ссылок используют настраиваемые очереди:

| Задачи | Конфигурация | Переменная окружения | Очередь по умолчанию |
|---|---|---|---|
| Задания сканирования страниц | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | Очередь по умолчанию |
| Задания обхода битых ссылок | `seo-pro.broken_links.queue.name` (+ `.connection`) | `SEO_PRO_BROKEN_LINKS_QUEUE` (+ `_CONNECTION`) | `seo-broken-links` |

### Пример Redis (схема рабочего окружения) {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Запустите воркер для каждой очереди (отдельный процесс / программа Supervisor):

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

`--timeout` воркера обхода должен превышать `seo-pro.broken_links.batch.hard_time_budget_seconds` (по умолчанию 180) плюс тайм-аут HTTP, чтобы пакет обработки не прерывался посреди записи состояния. Задание само задаёт `$timeout` равным этой сумме, поэтому согласуйте с ней параметр воркера. Для обхода используйте `--tries=1`: погибшее задание подхватывается следующим продолжением (или `seo-pro:broken-links-recover`), поэтому повторы на уровне очереди не нужны.

`seo:doctor` показывает очередь каждого вида задач и предупреждает, если она определяется как `sync` (это выполняло бы работу синхронно и блокировало процесс).

## Планировщик {#scheduler}

В Laravel 11, 12 и 13 расписание задаётся в **`routes/console.php`** (метод `schedule()` в `app/Console/Kernel.php` есть только в приложениях, обновлённых с Laravel 10; если он у вас сохранился, разместите те же записи там). Добавьте одну системную запись cron, чтобы планировщик срабатывал каждую минуту:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Затем зарегистрируйте повторяющиеся команды с рекомендуемой частотой:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Краткая таблица рекомендуемой частоты:

| Команда | Частота | Зачем |
|---|---|---|
| `seo:sitemap` | Ежедневно | Обновлять карту сайта по текущему контенту |
| `seo-pro:scan` | Еженедельно (ежедневно при частых изменениях контента) | Повторно проверять все цели |
| `seo-pro:scan-recover` | Ежечасно | Обрабатывать запуски, потерянные после остановки воркера |
| `seo-pro:scan-prune` | Ежедневно | Применять срок хранения запусков сканирования |
| `seo-pro:redirects-flush-hits` | Каждые 5 минут, только при `redirects.hits.flush_immediately=false` | Записывать накопленные в кеше счётчики в БД |
| `seo-pro:404-prune` | Ежедневно | Очищать журнал 404 по сроку хранения и лимиту строк |
| `seo-pro:404-recheck` | Ежедневно | Повторно запрашивать открытые пути 404; отмечать исправленные на источнике (теперь 200) как восстановленные |
| `seo-pro:broken-links-scan` | Еженедельно | Повторять обход битых ссылок (подтверждение между сканированиями) |
| `seo-pro:broken-links-recover` | Ежечасно | Обрабатывать обходы, потерянные после остановки воркера |
| `seo-pro:broken-links-prune` | Ежедневно | Применять сроки хранения обходчика |

`seo-pro:scan` и `seo-pro:broken-links-scan` только **ставят работу в очередь**; выполняет её воркер. Команды восстановления и очистки выполняются синхронно и требуют мало ресурсов.

::: tip Подтверждение битых ссылок между сканированиями
Ссылка помечается битой, только если `seo-pro.broken_links.mark_broken_after_failures` **последовательных сканирований** не смогли к ней обратиться (любой успех обнуляет счётчик). Поэтому обход выполняется по расписанию, а не однократно: единичный временный сбой не помечает ссылку. При значении по умолчанию 3 еженедельные сканирования подтверждают проблему примерно через две недели после первого неудачного наблюдения или максимум примерно через три недели после поломки. Увеличьте частоту или уменьшите порог, если подтверждение нужно быстрее.
:::

## Настройка пакетов обработки (обходчик битых ссылок) {#batch-tuning-broken-link-crawler}

Обход выполняется множеством ограниченных заданий, которые сами отправляют продолжение. Значения по умолчанию конечны; подберите их под возможности сайта и проверяемых хостов в `seo-pro.broken_links`:

| Ключ | По умолчанию | Что ограничивает |
|---|---|---|
| `max_pages_per_run` | `2000` | Число загруженных страниц за весь запуск. `null` — явное включение неограниченного режима (никогда не используется по умолчанию) |
| `max_links_per_page` | `200` | Проверки ссылок на страницу |
| `max_total_links` | `null` | Необязательный общий лимит проверок ссылок за запуск |
| `batch.max_pages_per_job` | `50` | Страницы на задание очереди |
| `batch.max_links_per_job` | `1500` | Проверки ссылок на задание очереди |
| `batch.hard_time_budget_seconds` | `180` | После этого задание **не начинает новых запросов** и отправляет продолжение |
| `batch.dispatch_delay_seconds` | `1` | Задержка между заданиями-продолжениями |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Ограничения отдельного запроса |
| `http.max_response_bytes` | Наследует `seo-pro.http.max_response_bytes` | Потоковый лимит тела ответа для страниц и проверяемых адресов |
| `seed.max_response_bytes` | Наследует HTTP-лимит обходчика / общий HTTP-лимит | Байты исходного XML / `.gz` карты сайта, загружаемые при сборе начальных URL |
| `seed.max_inflated_bytes` | Наследует лимит начальных URL / обходчика / общий лимит | Распакованные байты, принимаемые из карты сайта `.gz` |
| `http.per_host_delay_ms` | `0` | Пауза между проверками для снижения нагрузки (увеличьте для `internal_and_external`) |

Держите `batch.hard_time_budget_seconds` заметно ниже `--timeout` воркера обхода. Уже начавшийся запрос нельзя прервать посередине — его ограничивает `http.timeout`, поэтому тайм-аут воркера = бюджет + HTTP-тайм-аут + запас.

Для обхода `internal_and_external` расширьте `seo-pro.http.scope` (или `seo-pro.http.allowed_hosts`), чтобы SsrfGuard разрешал исходящие проверки, и увеличьте `http.per_host_delay_ms`, чтобы не обращаться к стороннему хосту слишком часто. `seo:doctor` предупреждает, если область обхода внешняя, а область разрешений защиты блокирует все проверки.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Одна программа на очередь. Пример `/etc/supervisor/conf.d/app-workers.conf`:

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

`stopwaitsecs` должен превышать `--timeout` воркера, чтобы штатный перезапуск не завершал задание посреди пакета обработки.

### Horizon {#horizon}

Если используете Horizon, определите supervisor для каждого вида задач в `config/horizon.php` и поручите ему управление процессами вместо Supervisor:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Повторы и обработка ошибок {#retry-failure-handling}

Задание сканирования цели получает собственную политику повторов из конфигурации и **не** полагается на `--tries` воркера:

| Ключ | По умолчанию | Значение |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Попытки на задание цели |
| `seo-pro.scan.backoff` | `30` | Секунды между попытками |
| `seo-pro.scan.timeout` | `300` | Тайм-аут задания цели (блокировка перекрытия истекает через тайм-аут + 60) |

Исчерпавшее попытки задание отмечает цель как **failed**, а запуск всё равно завершается (`partial` или `failed`): обработанные ошибки целей не оставляют запуск в состоянии `running`. Если воркер остановлен до записи состояния, всё ещё нужна описанная ниже процедура восстановления. Ошибки попадают в стандартную таблицу `failed_jobs`; управляйте ими обычным способом:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Запланируйте `queue:prune-failed` вместе с SEO-командами, чтобы ограничить размер таблицы:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

Обход битых ссылок использует `--tries=1`: погибшее задание обрабатывается следующим продолжением (сигнал активности аренды устаревает) или `seo-pro:broken-links-recover`, поэтому повторы очереди только дублировали бы работу.

## Восстановление {#recovery}

Остановка воркера посреди задания — случай, когда учёт выполнения не может исправиться сам. Его закрывают две процедуры; запланируйте обе **ежечасно**:

- `seo-pro:scan-recover` — отмечает как неудачные запуски сканирования страниц без прогресса в течение `seo-pro.scan.recovery.stuck_scan_timeout_hours` (по умолчанию 2).
- `seo-pro:broken-links-recover` — обрабатывает обходы с устаревшим сигналом активности аренды (`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, по умолчанию 2), отмечая их как неудачные и освобождая слот единственного активного запуска в области.

`seo:doctor` показывает это как **свидетельства недавней активности**: после начала использования сканирования он сообщает о зависших запусках и указывает команду восстановления. Он не может доказать, что cron действительно запускается, — ни одна команда этого не может; он показывает сведения из истории запусков.

## Сроки хранения {#retention}

Ограничивайте размер таблиц. Значения по умолчанию (все в `seo-pro.*`, `null` отключает соответствующую очистку):

| Данные | Конфигурация | По умолчанию | Команда |
|---|---|---|---|
| Запуски сканирования (+ проблемы) | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| Журнал 404 | `monitor_404.retention_days` (+ `max_rows` `10000`) | `90` | `seo-pro:404-prune` |
| Запуски обхода | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Устранённые находки | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Эксплуатационная телеметрия {#operational-telemetry}

Каждый завершённый запуск — сканирование страниц **и** обход битых ссылок — пишет одну структурированную строку завершения через стек журналирования. Так история метрик доступна без панели. Данные содержат только счётчики и время (без URL, тел ответов, заголовков или данных посетителей):

| Метрика | Сканирование | Обход |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (цели, отклонённые SSRF-защитой) | — | ✓ |
| `transient_failures` (сетевые ошибки, повторная проверка при следующем сканировании) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (постановка в очередь → первый пакет обработки) | ✓ | ✓ |

Настройте её в `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Укажите отдельный канал журналирования в `channel`, чтобы отправлять строки в рабочую систему (Loki / Datadog / CloudWatch), не смешивая их с журналом приложения:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Для более сложной обработки подпишитесь непосредственно на события: каждое предоставляет те же данные `metrics()`:

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

Телеметрия работает по возможности: неверно настроенный канал никогда не приводит к сбою сканирования.

## Развёртывание без зависимости от Filament {#filament-independent-deployment}

Ничто на этой странице не требует панели. Движок, все команды, очереди, планировщик, восстановление, сроки хранения и телеметрия одинаковы при работе без интерфейса. Панель Filament (`SeoProPlugin`) добавляет только **представления**: ход сканирования в реальном времени, таблицу проблем, CRUD перенаправлений, монитор 404 и панель битых ссылок. Разверните движок и управляйте им через CLI и планировщик; панель можно добавить позже или не добавлять вовсе, без миграции и переделок. Полный справочник команд — в [работе без интерфейса](/ru/pro/headless).


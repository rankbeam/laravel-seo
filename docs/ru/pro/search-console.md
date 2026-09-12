---
description: "Панель Google Search Console только для чтения: запросы и страницы, показы, клики, CTR и позиции рядом с данными сканирования. По умолчанию выключена."
---

# Search Console: только чтение {#search-console-read-only}

Панель Google Search Console **только для чтения**: ведущие запросы и страницы с **показами, кликами, CTR и средней позицией**, сопоставленные со страницами, уже известными сканеру. Так вы в одном месте видите: *«у этой страницы есть проблемы **и** она теряет показы»*. По умолчанию интеграция **выключена**.

Её устройство определяют три принципа:

- **Строго только чтение.** Интеграция запрашивает единственную область OAuth — `webmasters.readonly`, жёстко заданную в пакете. Она может только читать Search Analytics: никогда не отправляет карту сайта, не запрашивает индексацию и ничего не меняет в Search Console. Настройки для расширения прав нет.
- **Ваш ресурс, ваши учётные данные.** Запросы идут с *вашего сервера* прямо в Google с аутентификацией через *ваш* сервисный аккаунт или OAuth. Ничего не проксируется, использование не тарифицируется и не перепродаётся, пакет не отправляет телеметрию.
- **Ошибки остаются в контексте.** Отсутствие учётных данных, 403, превышение квоты или тайм-аут выводят сообщение в панели, не прерывая её отображение. Команда синхронизации истории сообщает об ошибках и прекращает загрузку последующих дней, как описано ниже.

## Что вы получаете {#what-you-get}

- **Страницы, требующие внимания** — важное сопоставление: страницы с **открытыми проблемами сканирования**, которые **продолжают получать поисковый трафик**. Вначале идут наиболее значимые возможности: проблемные страницы с наибольшим числом показов. Исправляйте их в первую очередь.
- **Ведущие страницы** и **ведущие запросы** — обычные таблицы Search Analytics.

В панели Filament это страница **Search Console** в группе навигации *SEO*, видимая только при включённой интеграции. Без интерфейса те же метрики доступны через команду `seo-pro:search-console` и `SeoPro::searchConsole()`.

## Настройка {#setup}

Нужны учётные данные Google с правом чтения ресурса Search Console. Поддерживаются два режима; для сервера проще всего **сервисный аккаунт**.

### Сервисный аккаунт: рекомендуемый вариант {#service-account-recommended}

1. В Google Cloud включите **Search Console API**, создайте **сервисный аккаунт** и скачайте его JSON-ключ.
2. В Search Console откройте *Settings → Users and permissions* — так путь называется в английском интерфейсе — и добавьте адрес сервисного аккаунта (`…@….iam.gserviceaccount.com`) как пользователя. Для чтения достаточно уровня Restricted.
3. Укажите пакету ключ и ресурс:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

Если `SEO_PRO_GSC_SITE_URL` не задан, ресурс с префиксом URL выводится из `app.url`.

### OAuth: токен обновления для автономного доступа {#oauth-offline-refresh-token}

Если у вас есть OAuth-клиент и долгоживущий **refresh token**, желательно выданный только для `webmasters.readonly`, настройте их следующим образом. Каждое обновление запрашивает эту область доступа. Пакет отклоняет полученный токен, если ответ явно не подтверждает ровно права только для чтения; он не предполагает, что Google всегда сузит более широкое разрешение.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Опубликуйте миграцию токенов {#publish-the-token-migration}

Зашифрованный кеш токенов доступа хранится в таблице `seo_gsc_tokens`. Один раз опубликуйте и выполните миграции:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Затем проверьте подключение через `php artisan seo:doctor`: команда сообщает, включена и настроена ли Search Console, без сетевого запроса и без вывода секретов.

## Использование без интерфейса {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## Исторические метрики {#historical-metrics}

Панель и команда выше читают **актуальное скользящее окно**, а единственным хранилищем остаётся сама Search Console. Чтобы получить **историю с точностью до дня** для запросов за произвольные прошлые периоды, запустите синхронизацию. Она сохраняет метрики по дням отдельно для запросов и страниц в таблице `seo_gsc_metrics`:

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **Первый запуск загружает историю** за `sync.backfill_days` дней: по умолчанию 90. Search Console хранит около 16 месяцев; увеличьте значение, чтобы получить больше. Последующие запуски **продолжают с последней сохранённой даты**, повторно загружая хвост из `sync.overlap_days` дней для учёта поздних уточнений недавних данных. Окно всегда заканчивается за 3 дня до текущей даты из-за задержки данных.
- **Идемпотентность.** Строки создаются или обновляются по `(date, dimension, key)`, поэтому повторный запуск безопасен. Ошибка на одном дне, например превышение квоты, корректно останавливает запуск с отчётом о сохранённых строках. Следующий запуск продолжит с места остановки.
- **Что это даёт.** Показатели **изменений** Search Console в [отчёте под вашим брендом](/ru/pro/reports) переходят с разницы снимков на настоящую историю между периодами: текущий период сравнивается с предыдущим той же длины, как только таблица покрывает оба. Это также основа более подробного анализа ключевых слов.

Сохраняются только агрегированные метрики: текст запроса, URL страницы и четыре показателя — клики, показы, CTR и позиция — по дням. Данные отдельных пользователей или запросов к серверу никогда не загружаются и не записываются.

## Обработка данных и безопасность {#data-handling-security}

- **Права только для чтения проверяются.** JWT сервисного аккаунта запрашивает только `webmasters.readonly`. Запросы обновления OAuth делают то же; пакет отклоняет ответ без указанной области или с более широкими правами. Используйте учётные данные, разрешённые только для чтения. В пакете нет изменяющих вызовов Search Console.

- **Учётные данные остаются в окружении.** Ключ сервисного аккаунта или секрет OAuth с refresh token читаются из **именованных** переменных окружения во время вызова, как и ключ ИИ. Поэтому `php artisan config:cache` никогда не записывает их в `bootstrap/cache/config.php`. Передайте их окружению процесса, если кешированная конфигурация мешает загрузке `.env`.
- **Токены зашифрованы при хранении.** Короткоживущий токен доступа, полученный из ваших учётных данных, хранится **в зашифрованном виде** с использованием ключа приложения в `seo_gsc_tokens` и повторно используется до приближения срока истечения. Поэтому обмен токенов не выполняется при каждом просмотре. Долгоживущие учётные данные никогда не сохраняются в базе, только в окружении.
- **Каждый запрос защищён от SSRF.** Обмен токена и вызов Search Analytics проходят через общий `SsrfGuard`: только HTTPS, хост должен разрешаться в публичный адрес, редиректы выключены. Поэтому запрос нельзя перенаправить на внутренний сервис.
- **Секреты не попадают в журналы.** Токены доступа, ключи и заголовки авторизации никогда не записываются в логи. При ошибке API показывается только очищенное и ограниченное по длине сообщение Google.
- **Метрики кешируются локально** на `seo-pro.search_console.cache_ttl` секунд, по умолчанию 30 минут, чтобы панель не обращалась к API при каждой отрисовке. Сетевая панель и команда не сохраняют ничего, кроме этого кеша и зашифрованного токена доступа. Только явно запускаемая `seo-pro:gsc-sync` записывает метрики постоянно: агрегированные показатели запросов и страниц по дням в `seo_gsc_metrics`, без данных отдельных пользователей.

## Справочник конфигурации {#configuration-reference}

Все ключи находятся в `config/seo-pro.php` → `search_console`:

| Ключ | По умолчанию | Назначение |
| --- | --- | --- |
| `enabled` | `false` | Главный переключатель (`SEO_PRO_GSC_ENABLED`). |
| `connection` | `service_account` | `service_account` или `oauth`. |
| `site_url` | Из `app.url` | Ресурс: `https://example.com/` или `sc-domain:example.com`. |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Имя** переменной окружения с JSON-ключом или путём к нему. |
| `oauth.client_id` | — | Идентификатор OAuth-клиента, не секрет. |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Имя** переменной окружения с секретом клиента. |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Имя** переменной окружения с refresh token. |
| `default_days` | `28` | Окно отчёта, заканчивается за 3 дня до текущей даты из-за задержки GSC. |
| `row_limit` | `100` | Первые N строк отчёта; максимум API — 25000. |
| `cache_ttl` | `1800` | Время кеширования загруженного отчёта в секундах. |
| `sync.backfill_days` | `90` | Число дней первой загрузки `gsc-sync` при пустой таблице. |
| `sync.overlap_days` | `2` | Число последних дней, повторно загружаемых при каждом запуске для поздних уточнений. |
| `sync.row_limit` | `5000` | Максимум строк за день по каждому измерению, запрашиваемый синхронизацией. |


---
description: "Сообщайте поисковым системам о публикации и обновлении URL через общий endpoint api.indexnow.org. Pro уведомляет участвующие системы. По умолчанию выключено."
---

# IndexNow — уведомление поисковых систем при публикации {#indexnow-—-push-on-publish-indexing}

Вместо ожидания, пока робот найдёт изменённую страницу, **IndexNow** позволяет *сообщить* поисковым системам о публикации или обновлении URL сразу. Pro отправляет данные на общий endpoint `api.indexnow.org`, который одним вызовом **передаёт их всем участвующим системам**, без отдельной отправки в каждую. В [официальном FAQ](https://www.indexnow.org/faq) перечислены Amazon, Bing, Naver, Seznam, Yandex и Yep. Уведомление не гарантирует индексацию.

По умолчанию возможность **выключена**. Сетевые обращения не выполняются, пока вы её не включите и не отправите URL.

## Настройка {#setup}

### 1. Создайте ключ {#_1-generate-a-key}

IndexNow использует **ключ**, чтобы подтвердить контроль над хостом. Pro принимает от 8 до 128 символов из `[a-f0-9-]`; идеально подходит шестнадцатеричная строка из 32 символов. Создайте ключ один раз, не меняйте его без необходимости и передайте через окружение:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip Ключ читается через конфигурацию и сохраняется после `config:cache`
В отличие от учётных данных Search Console, ключ IndexNow **не является секретом**: он публично отдаётся по адресу `/{key}.txt` для подтверждения владения хостом. Поэтому Pro получает его через конфигурацию — `indexnow.key`, по умолчанию `env('SEO_PRO_INDEXNOW_KEY')`. Это намеренно: значения, определённые **только в `.env`**, недоступны через `env()` после `config:cache`, поскольку Laravel больше не загружает этот файл. Настоящие переменные окружения процесса остаются доступными. При чтении через конфигурацию ключ попадает в `config:cache` и доступен постоянно. Компромисс: **после замены ключа нужно повторно выполнить `php artisan config:cache`**. Ключ никогда не записывается в журнал. Если файл ключа в продакшене возвращает 404, см. [серверы с кешированной конфигурацией](#config-cached-servers).
:::

### 2. Отдавайте файл ключа {#_2-serve-the-key-file}

Для проверки владения IndexNow загружает `https://{host}/{key}.txt`, содержащий только ключ. Когда `route` включён — это значение по умолчанию, — **Pro отдаёт файл за вас**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Работает только путь настроенного ключа; остальные пути, перехваченные этим маршрутом, возвращают 404. Когда IndexNow выключен, весь маршрут возвращает 404. Если предпочитаете размещать файл самостоятельно или на CDN, выключите `route` и укажите свой URL в `key_location`.

## Отправка URL {#submitting-urls}

### Автоматически при сохранении: уведомление о публикации {#automatically-on-save-the-push-on-publish-path}

Добавьте трейт в модель и включите `auto_submit`. Каждое сохранение ставит в очередь отправку `getUrlForSEO()` модели:

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

Трейт учитывает условие публикации: для полного контроля реализуйте `shouldSubmitToIndexNow(): bool`. В противном случае он использует атрибут `is_published`, а если и его нет — отправляет при каждом сохранении. Отправка всегда идёт **через очередь**, поэтому сохранение модели не ждёт сетевого ответа.

### Вручную {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

По умолчанию `submit()` ставит отправку в очередь; для выполнения в текущем процессе передайте `queue: false`.

### Из командной строки {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Только свой хост
Каждый URL проверяется на схему `http(s)` **и** принадлежность настроенному `host`. Всё остальное **отбрасывается**: учитывается в счётчике, но никогда не отправляется. Отправлять можно только собственные URL; endpoint в любом случае отклонит несовпадение хоста. Списки больше `max_urls_per_request` — 10000, лимита протокола — автоматически разбиваются на части.
:::

## Конфигурация {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## Как работают повторные попытки {#how-retries-work}

Задача очереди `SubmitToIndexNowJob` повторяет только те запросы, которые *имеет смысл* повторять: `429` — ограничение частоты, `5xx` или тайм-аут приводят к повторам с задержкой `backoff`, максимум `tries` попыток. `400`/`403`/`422` — постоянные ошибки клиента, например неверный ключ или несовпадение хоста, — записываются в журнал и **останавливают** обработку без бесполезных повторов. И `200`, и `202` — запрос принят или ожидает проверки ключа — считаются успехом.

В продакшене выделите задаче **отдельную очередь**, чтобы медленный endpoint не задерживал работу для пользователей:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Устранение неполадок {#troubleshooting}

### Серверы с кешированной конфигурацией {#config-cached-servers}

Если `/{key}.txt` в продакшене возвращает 404 или отправки незаметно ничего не делают, хотя `indexnow.enabled` явно равен `true`, причина почти всегда в ключе, который находится **только в `.env`** на сервере с выполненным `php artisan config:cache`. После кеширования конфигурации Laravel не читает `.env`, поэтому `env('SEO_PRO_INDEXNOW_KEY')` возвращает `null`, маршрут файла ключа не регистрируется, а каждая отправка отклоняется со статусом "not configured" — «не настроено».

Конфигурация по умолчанию получает `indexnow.key` из `env(...)`, поэтому обычная настройка сохраняется при сборке кеша и работает. Проблема возникает, только если вы **опубликовали конфигурацию и удалили значение по умолчанию `env(...)`** либо задали ключ через **собственное имя `key_env`, существующее только в `.env`**. Есть два способа исправить это:

1. **Храните ключ в конфигурации** — рекомендуемый вариант. Оставьте `indexnow.key` равным `env('SEO_PRO_INDEXNOW_KEY')` или задайте значение буквально, затем снова выполните `php artisan config:cache`. После замены ключа кеш нужно пересоздать.
2. **Передайте настоящую переменную окружения**: задайте `SEO_PRO_INDEXNOW_KEY` как переменную ОС или процесса — через `env[...]` пула PHP-FPM, `Environment=` systemd или настройки переменных вашей платформы, **а не только в `.env`**. Переменные ОС доступны даже при кешированной конфигурации.

Для подтверждения запустите `php artisan seo:doctor`: при обнаружении этой ситуации команда выводит английское сообщение **"IndexNow is enabled but no valid key resolves"** — «IndexNow включён, но корректный ключ не найден» — и точный способ исправления. Pro также один раз за процесс записывает предупреждение, если приложение запускается с кешированной конфигурацией и недоступным ключом.

::: tip Google
Google **не** участвует в IndexNow. Для Google используйте интеграцию [Search Console](/ru/pro/search-console) и актуальную карту сайта.
:::


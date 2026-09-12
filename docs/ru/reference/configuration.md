---
description: Все параметры config/seo.php, сгруппированные по слоям резолвера, с поставляемыми значениями по умолчанию.
---

# Конфигурация {#configuration}

Опубликуйте файл конфигурации:

```bash
php artisan vendor:publish --tag=seo-config
```

Все параметры ниже находятся в `config/seo.php`. Показаны значения по умолчанию.

## Общие значения сайта по умолчанию, слой 1 {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

`title_suffix` добавляется к итоговому заголовку, если тот ещё не заканчивается этим суффиксом.

`title_suffix_skip_when_contains` — список названий бренда, при наличии которых суффикс не добавляется. Если итоговый заголовок уже содержит один из этих токенов **как отдельное слово**, суффикс пропускается, чтобы бренд не повторялся. Сравнение не учитывает регистр, но учитывает границы слов: `Acmestic` не совпадает с `Acme`. Значение по умолчанию `[]` сохраняет прежнее поведение.

## Правила вывода robots {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

При формировании `<head>` тег `<meta name="robots">` пропускается, если итоговая директива равна `default_robots`, указанному выше. Избыточный `index,follow` только добавляет шум: отсутствие тега робот и так трактует как index,follow. **Отличающаяся** директива — `noindex`, `nofollow`, `max-snippet:-1` и другие — всегда выводится без изменений. Задайте `emit_default` значение `true`, чтобы всегда выводить тег и вернуть поведение до версии 3.1. Отдельная директива `@seoRobots` не затронута: это явный запрос на вывод, и она всегда формирует тег. Поддерживаемые директивы и их приоритеты описаны в [контракте вывода](/ru/contributing/rendering-contract).

## Защита от индексации вне продакшена {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Если защита включена, а приложение работает в окружении, которого **нет** в `allowed_environments`, она принудительно задаёт `noindex,nofollow` на каждой странице. Её приоритет выше всей цепочки, даже сохранённых значений отдельных страниц. Она также отправляет соответствующий заголовок `X-Robots-Tag`, формирует `robots.txt` с полным запретом обхода и добавляет предупреждение в вывод `seo:audit`. В разрешённых окружениях, по умолчанию `production`, защита не действует.

По умолчанию **выключена**: пока вы её не включите, вывод остаётся побайтно прежним. Включается через `SEO_INDEXING_GUARD=true`, выключается через `SEO_INDEXING_GUARD=false` — в обоих случаях одной строкой. Список разрешённых окружений можно переопределить через `SEO_INDEXING_GUARD_ALLOWED`: значения разделяются запятыми, поддерживаются шаблоны `Str::is()`, например `prod*`. Пустой список включает защиту во всех окружениях.

`send_header`, включённый по умолчанию внутри защиты, также отправляет `X-Robots-Tag: noindex,nofollow` для каждого ответа, проходящего через приложение. Поэтому PDF, ленты и изображения без `<meta robots>` тоже получают запрет индексации. Middleware регистрируется только при включённой защите. Рекомендуется оставить этот параметр включённым; см. полное [руководство по защите от индексации](/ru/guide/indexing-guard).

## Канонические URL {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Из канонического URL, который резолвер **вычисляет** из URL запроса или `getUrlForSEO()` модели, по умолчанию удаляется строка запроса: параметры отслеживания, фильтрации и сортировки создают канонические адреса с дублирующимся содержимым одной страницы. Ключи из `query_whitelist` **сохраняются** в вычисленных канонических URL в указанном порядке, а все остальные параметры по-прежнему удаляются. Обычный пример — `page` для архивов с пагинацией: `/blog?page=2` действительно отличается от `/blog`.

**Явно заданный** канонический URL — введённый администратором или полученный из слоя с более высоким приоритетом — всегда выводится без изменений, включая строку запроса. Список разрешённых параметров относится только к вычисляемому резервному значению. Значение по умолчанию `[]` сохраняет удаление всех параметров.

## Включение функций {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta` создаёт пустую строку `seo_meta` при создании модели с `HasSEO`. Учтите: сидеры с `WithoutModelEvents` обходят это поведение.

## Фокусные ключевые слова {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

Это **переключатель рабочего процесса** фокусных ключевых слов. Пока установлено `false`, значение по умолчанию, отсутствие фокусного ключевого слова нигде не отмечается: ни в [`seo:audit`](/ru/guide/audit), ни при сканировании Pro. Приложение, которое не использует эту возможность, не получает напоминаний о ней. Включите её, когда начнёте задавать фокусные ключевые слова, например через [поле Filament](/ru/guide/filament). Тогда бесплатный аудит, сканирование Pro и редактор Pro начнут показывать уведомление `missing_focus_keyword` на страницах, где слово ещё не задано. Все они читают один флаг, поэтому результат согласован.

## Бесплатный аудит, `seo:audit` {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

Модели, которые проверяет бесплатная команда [`seo:audit`](/ru/guide/audit), если параметр `--model` не передан. Каждая должна использовать трейт `HasSEO`. При пустом списке команда использует модели из `sitemap.models`.

## Вычисляемые резервные значения, слой 5 {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

При явно выбранной стратегии `best` построитель оценивает упорядоченный список кандидатов по близости размеров в пикселях к идеальным и **пропускает изображения меньше минимума**. Первым идёт `getSEOImage()`, сохраняя наивысший приоритет; затем хук модели `getSEOImages()`, распространённые поля изображений, первое изображение контента и настроенное значение по умолчанию. Измеряются только **локальные** изображения: относительный путь внутри `public/`, публичный диск или абсолютный URL на вашем хосте. Внешний URL никогда не загружается и служит только резервным вариантом. Если ни один локальный кандидат не достигает минимальных размеров, выбор возвращается к первому подходящему значению: `best` не оставит вас без результата, который вернул бы `first`. Предоставьте кандидатов из модели:

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## Карты сайта {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

Программная регистрация источников описана в [руководстве по реестру карт сайта](/ru/guide/sitemaps).

## Разметка JSON-LD {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

Эти значения используются в узлах [графа разметки](/ru/guide/schema).

## Маршруты {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Задайте `enabled => false`, если приложение отдаёт собственный статический `/sitemap.xml`.

## Кеш {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Кеш результатов резолвера {#resolver-result-cache}

`SEOResolver` выполняет всю цепочку приоритетов при **каждом** выводе фронтенда: конфигурация → глобальные значения, значения типа модели и маршрута по умолчанию → вычисленные значения модели → явные `seo_meta` → суффикс заголовка, канонический URL и разметка. На сайте с высокой посещаемостью, как в эталонном приложении с примерно 20 тысячами запросов в день, это несколько обращений к базе на страницу.

Включите `cache.resolver.enabled`, чтобы кешировать полностью определённые SEO-данные модели. **Попадание в кеш полностью пропускает цепочку приоритетов**: в тесте производительности пакета прогретый кеш выполняет **ноль** запросов к базе, тогда как каждое определение значений без кеша заново читает `seo_meta` модели. Кешируется обычный массив, восстанавливаемый через `SEOData::fromArray()`, а не объект: в Laravel 13 задано `cache.serializable_classes = false`, поэтому закешированный объект возвращается как `__PHP_Incomplete_Class`.

Используется настроенный выше `store`. В продакшене укажите **общее постоянное хранилище кеша**, `redis` или `memcached`: кеш и его сброс должны быть видны всем веб-процессам и воркерам очереди. Пока такого хранилища нет, оставьте кеш выключенным.

**Инвалидация выполняется автоматически и корректно**: при включённом кеше результат тот же, что при выключенном. Ключ записи — `(model class, id, locale, route, request URL)`. Записи удаляются из кеша, когда:

- строка `seo_meta` страницы **сохраняется или удаляется** любым способом: `saveSEO()`, Filament или прямая запись `SEOMeta`;
- меняется **поле контента** модели из списка `getSEOContentFields()`. По умолчанию он включает все встроенные поля вычисляемых резервных значений: title/headline, excerpt/summary/content/body/text/article и распространённые поля изображений, например `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` и `hero_image`. Переопределите список, если модель вычисляет SEO из дополнительных столбцов;
- меняется **любая строка `seo_defaults`**. Значение по умолчанию может влиять на любую модель, поэтому очищается весь кеш результатов.

В хранилище **с поддержкой тегов** — `redis`, `memcached`, `array` — записи модели удаляются через **теги** кеша. В хранилище **без тегов**, `file` или `database`, пакет использует **метку версии** для каждой модели. Оба способа работают без перебора ключей.

::: tip
Кешируются только результаты для моделей. `SEO::render()`/`@seo()` для вручную созданного `SEOData` и `@seoForRoute()` для маршрута без модели по-прежнему вычисляются при обращении.
:::

::: warning
Кеш отражает `updated_at` модели и вычисленное `modified_time` на момент последнего изменения **поля контента** или до истечения TTL. Обычный `touch()`, меняющий только `updated_at` без изменения столбца из `getSEOContentFields()`, не запускает повторное определение значений: `article:modified_time` может отставать на время до TTL. Добавьте столбцы, от которых зависят вычисляемые значения вашего приложения, в `getSEOContentFields()`, если нужен немедленный сброс кеша.
:::

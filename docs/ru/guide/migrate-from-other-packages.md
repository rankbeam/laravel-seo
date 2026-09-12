---
description: "Перейдите на Rankbeam с другого SEO-пакета Laravel: сопоставьте его API и хранилище с трейтом HasSEO и saveSEO(), а SEO-данные моделей перенесите импортом одной командой."
---

# Миграция с других SEO-пакетов Laravel {#migrating-from-other-laravel-seo-packages}

Уже используете другой SEO-пакет? Переход на Rankbeam задуман как работа на день, а не переписывание приложения. Руководство сопоставляет API и хранилище распространённых пакетов с двумя основными средствами Rankbeam: трейтом [`HasSEO`](/ru/guide/quickstart) и `saveSEO()`. Для пакета, который хранит SEO-данные каждой модели, предусмотрен импорт одной командой.

::: tip Переходите с WordPress?
Если вы переносите контентный сайт с WordPress (Yoast или Rank Math), откройте отдельное руководство [**Миграция с WordPress**](/ru/guide/migrate-from-wordpress): там описаны CSV-импорт и чтение действующей базы данных.
:::

| Исходный пакет | Где хранятся данные | Путь миграции |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | Полиморфная таблица `seo` | **`php artisan seo:import-from ralphjsmit`** и замена трейта |
| [`artesaos/seotools`](#from-artesaos-seotools) | Нигде (во время запроса и в конфигурации) | Замена кода: задавайте значения через `saveSEO()` или вычисляемые геттеры |
| [`spatie/*`](#from-spatie-packages) | Нигде (построители schema-org и карт сайта) | Оставьте дополняющие инструменты, остальное перенесите в Rankbeam |

Только **ralphjsmit** сохраняет SEO-данные в таблице базы данных, поэтому только у него есть данные для массового импорта. Остальные строят теги во время запроса: читать таблицу неоткуда; их вызовы на каждом запросе заменяются сохранёнными `seo_meta`.

---

## Переход с `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

`ralphjsmit/laravel-seo` хранит одну полиморфную строку на модель в таблице `seo`, структура которой близка к `seo_meta` Rankbeam. Это позволяет выполнить простой идемпотентный массовый импорт.

### 1. Установите Rankbeam рядом с текущим пакетом {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Во время миграции оба пакета могут сосуществовать: у них разные таблицы (`seo` и `seo_meta`) и пространства имён трейтов.

::: warning Один файл конфигурации, а не два
Если в приложении остался опубликованный `config/seo.php` от `ralphjsmit/laravel-seo`, он перекроет конфигурацию Rankbeam: оба используют ключ конфигурации `seo`. Сделайте резервную копию, удалите файл и заново опубликуйте конфигурацию Rankbeam: `php artisan vendor:publish
--tag=seo-config`.
:::

### 2. Запустите импорт {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

Импорт читает таблицу `seo` ralphjsmit, определяет реальную модель Eloquent для каждой строки и записывает данные в `seo_meta`.

| Параметр | Действие |
|---|---|
| `--dry-run` | Показать, что будет импортировано, без записи. |
| `--model="App\Models\Post"` | Ограничить одним или несколькими классами моделей; параметр можно повторять. |
| `--locale=fr` | Записать импортированные строки для этой локали; по умолчанию — локаль приложения. |
| `--table=legacy_seo` | Читать исходную таблицу с изменённым именем. |
| `--connection=legacy` | Читать исходную таблицу через другое подключение к базе данных. |
| `--limit=100` | Импортировать не более N строк; удобно для поэтапного переноса. |
| `--overwrite` | Заменять существующие непустые значения; по умолчанию заполняются только пустые поля. |
| `--json` | Машиночитаемый отчёт. |
| `--force` | Пропустить запрос подтверждения для скриптов и CI. |

Импорт **идемпотентен**: повторный запуск обновляет те же строки и не создаёт дублей. По умолчанию он только *заполняет* пустые поля и не перезаписывает SEO-данные, уже заданные в Rankbeam. Передайте `--overwrite`, если хотите заменить существующие значения импортируемыми.

### 3. Замените трейт моделей {#_3-swap-the-trait-on-your-models}

Замените трейт ralphjsmit на трейт Rankbeam. Названия методов немного отличаются; теперь трейт читает таблицу `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Если вы настраивали SEO-данные через `getDynamicSEOData()` ralphjsmit, перенесите логику в вычисляемые геттеры отдельных полей Rankbeam: `getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()`, `getSEOAlternates()`. См. [Быстрый старт](/ru/guide/quickstart). Сохранённые переопределения задаются через `saveSEO()`:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Сопоставление полей {#field-mapping}

Импорт сопоставляет поля **явно**: он не копирует вслепую столбцы, отсутствующие в схеме Core 3.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Примечания |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | **Определяются заново** из действующей модели, а не копируются дословно; см. ниже. |
| `title` | `title` | Обрезается до 70 символов — длины столбца `seo_meta`; превышения отражаются в отчёте. |
| `description` | `description` | Обрезается до 160 символов; превышения отражаются в отчёте. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Обрезается до 50 символов. |
| `image` | `og_image` | `twitter:image` автоматически наследует значение через резолвер. |
| `author` | *(не импортируется)* | В `seo_meta` Core 3 нет столбца автора: автор статьи относится к уровню резолвера, а не к сохранённым метаданным соцсетей. Строки с автором **подсчитываются и включаются в отчёт**, чтобы вы выбрали место хранения, например вычисляемое значение в стиле `getSEOData`. |
| `id`, `created_at`, `updated_at` | *(не импортируются)* | Структурные поля. |

**Почему полиморфный тип определяется заново.** Каждая исходная строка связывается с реальной моделью, а ключи `seoable` берутся из собственного `getMorphClass()` модели. Это сохраняет корректную связь при *текущей* [карте полиморфных типов](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types) приложения, даже если ralphjsmit сохранял другое представление. Также импорт пропускает строки, чьи модели уже удалены: они отмечаются как пропущенные, а не записываются без связанного объекта.

### Что сообщает отчёт {#what-the-report-tells-you}

Запуск без `--json` выводит таблицу результатов и три раздела для проверки:

- **Truncated** — значения, сокращённые до длины столбца `seo_meta`. Проверьте их.
- **Not imported** — исходные столбцы, например `author`, с данными, для которых нет места в Core 3.
- **Skipped rows by reason** — пустые исходные строки, удалённые модели, неразрешённые типы моделей.

### Проверка {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Убедившись в результате, удалите `ralphjsmit/laravel-seo` и его таблицу `seo`.

---

## Переход с `artesaos/seotools` {#from-artesaos-seotools}

`artesaos/seotools` строит теги **во время запроса**: вы задаёте значения через фасады `SEOMeta`, `OpenGraph`, `TwitterCard` и `JsonLd`, часто в контроллере, с резервными значениями из `config/seotools.php`. Для моделей ничего не сохраняется, поэтому импортировать таблицу невозможно: вызовы на каждом запросе заменяются сохранёнными или вычисляемыми значениями.

| Вызов artesaos/seotools | Эквивалент Rankbeam |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` или `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` или `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` или `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | Эквивалента метатега keywords нет: фокусные ключевые слова служат для внутренних редакционных проверок. `saveSEO(['focus_keywords' => [...]])` (см. [аудит](/ru/guide/audit)) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [Граф разметки JSON-LD](/ru/guide/schema) |
| Значения по умолчанию `config/seotools.php` | Значения сайта по умолчанию `config/seo.php` и [приоритеты резолвера](/ru/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` в шаблоне | `@seo($model)` (см. [Blade](/ru/guide/blade)) |

Меняется подход: вместо императивного задания тегов в каждом контроллере вы один раз сохраняете SEO-данные каждой модели в `seo_meta`, а резолвер Rankbeam обеспечивает их вывод. Общие резервные значения сайта из `config/seotools.php` становятся [значениями конфигурации по умолчанию](/ru/reference/configuration) Rankbeam; для статических страниц маршрутов используется `@seoForRoute()`.

---

## Переход с пакетов Spatie {#from-spatie-packages}

Пакета хранения метаданных `spatie/laravel-seo` не существует, поэтому импортировать нечего. Используемые для SEO пакеты Spatie — **дополняющие построители**, которые можно оставлять или заменять по частям:

- **`spatie/schema-org`** — fluent-построитель JSON-LD. В Rankbeam есть собственный [граф разметки](/ru/guide/schema) с типизированными построителями `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` и `Organization`, которые сохраняют данные в `seo_meta.schema_jsonld` и выводят их без дублей. Если у вас есть вручную созданные объекты `spatie/schema-org`, передайте результат их `->toArray()` в `saveSEO(['schema_jsonld' => $array])` или перепишите их с построителями Rankbeam.
- **`spatie/laravel-sitemap`** — генератор карт сайта. [Реестр карт сайта](/ru/guide/sitemaps) Rankbeam построен на нём: можно зарегистрировать модели как источники и поручить Rankbeam вывод общей карты сайта либо оставить существующую карту Spatie и выключить маршрут Rankbeam.

(Если вы использовали [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO), другой построитель метаданных на основе структур во время запроса, следуйте подходу artesaos: перенесите вызовы `setTitle`/`addMeta` каждого запроса в `saveSEO()` или вычисляемые геттеры.)

---

## Расширение импорта {#extending-the-importer}

За командой `seo:import-from` стоит небольшой реестр реализаций `Rankbeam\Seo\Importing\Contracts\Importer`, поэтому новые источники подключаются без изменения команды. Сейчас встроены `ralphjsmit` и источники WordPress: `wordpress-csv`, `yoast`, `rank-math`; см. [Миграция с WordPress](/ru/guide/migrate-from-wordpress). Зарегистрируйте собственный источник в сервис-провайдере:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```


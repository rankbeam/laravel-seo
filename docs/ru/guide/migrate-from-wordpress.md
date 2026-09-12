---
description: "Перенесите написанные вручную SEO-данные Yoast или Rank Math — заголовки, описания, канонические URL, robots и фокусные ключевые слова — в модели Laravel. Справочник сопоставления полей импорта."
---

# Миграция с WordPress {#migrating-from-wordpress}

Переносите контентный сайт с WordPress? Rankbeam может перенести в модели Laravel SEO-метаданные, которые ваша команда вручную заполняла в Yoast или Rank Math: заголовки, описания, канонические URL, директивы robots, фокусные ключевые слова и переопределения соцсетей. Так при переходе не потеряются годы оптимизации.

::: tip Готовите реальное переключение?
Эта страница — *справочник* импорта: сопоставление полей, токены и исходные ключи. Пошаговую **процедуру** с низким риском — сосуществование, импорт, проверка и затем вывод старой системы из эксплуатации — см. в [плане миграции с WordPress](/ru/guide/wordpress-migration-runbook).
:::

Есть два пути, оба используют одну команду `seo:import-from`:

| Путь | Источник | Лучше подходит для |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | Таблица, экспортированная из WordPress | Большинства агентских миграций; вы контролируете точные URL |
| [**База данных**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | Действующая база WordPress | Полного переноса, включая переопределения OpenGraph/Twitter и перенаправления Rank Math |

Оба пути **идемпотентны**: повторный запуск обновляет те же строки без дублей. Оба поддерживают **`--dry-run`** и по умолчанию только *заполняют* пустые поля, не перезаписывая SEO-данные, уже заданные в Rankbeam. Передайте **`--overwrite`**, чтобы заменить существующие значения импортируемыми.

## Как строки WordPress становятся строками `seo_meta` {#how-wordpress-rows-become-seo-meta-rows}

Данные WordPress не являются полиморфными данными Laravel: строка WordPress определяется **URL** или **ID публикации**, а `seo_meta` Rankbeam полиморфна — каждая строка связана с реальной моделью Eloquent. Поэтому импорт сопоставляет каждую строку WordPress с вашей моделью и явно сообщает, какие строки связаны, а какие остались только URL:

- **Связана с моделью.** Вы задаёте целевую модель через `--model="App\Models\Post"`. **Slug** каждой строки — последний сегмент пути URL или `post_name` WordPress — сопоставляется с моделью: по умолчанию по ключу маршрута либо по столбцу, выбранному через `--match-by=`. Совпавшие строки записываются в `seo_meta`.
- **Только URL.** Строка без соответствующей модели, как и запуск без `--model`, не может стать строкой `seo_meta`: связать её не с чем. В отчёте она пропускается с причиной `url-only`. Её канонический URL всё ещё может стать [кандидатом на перенаправление](#redirects).

Публикации и страницы WordPress обычно соответствуют *разным* моделям Laravel, поэтому запускайте импорт отдельно для каждого типа контента, ограничивая строки:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Пользовательские типы публикаций по умолчанию не просматриваются
Чтение базы данных охватывает только типы **`post`** и **`page`**. На сайтах с пользовательскими типами — например, `product`, `event`, `pathology` из темы — каждый нужно указать явно, повторяя `--post-type=`:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. Импорт CSV {#_1-csv-import}

Путь CSV подходит для большинства агентских миграций. Экспортируйте по одной строке на URL с таким заголовком. Столбцы могут идти в любом порядке; неизвестные столбцы игнорируются и отражаются в отчёте:

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Запустите:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Столбец | Поле `seo_meta` | Примечания |
|---|---|---|
| `url` | *(ключ сопоставления)* | Slug, последний сегмент пути, сопоставляется с моделью. Обязателен. |
| `title` | `title` | Обрезается до 70 символов; превышения отражаются в отчёте. |
| `description` | `description` | Обрезается до 160 символов. |
| `canonical` | `canonical` | Также используется для [кандидатов на перенаправление](#redirects). |
| `robots` | `robots` | Сохраняется дословно, например `noindex, nofollow`; обрезается до 50 символов. |
| `focus_keyword` | `focus_keywords` | Через запятую; первое ключевое слово — основное. |

Некорректные строки пропускаются и подсчитываются: без `url` либо с количеством столбцов, не совпадающим с заголовком.

---

## 2. Импорт из базы данных (Yoast / Rank Math) {#_2-database-import-yoast-rank-math}

Если база WordPress сохранилась, импорт может прочитать SEO-метаданные напрямую, включая переопределения OpenGraph/Twitter и перенаправления Rank Math, которые обычно теряются при экспорте CSV.

### Настройте подключение к WordPress {#point-a-connection-at-wordpress}

Добавьте базу WordPress как подключение в `config/database.php`:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Затем выполните импорт. Префикс таблиц по умолчанию — `wp_`; изменить его можно через `--table=`:

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

Чтение проходит по `{prefix}posts` — опубликованным публикациям и страницам — и получает метаданные плагина для каждой записи из `{prefix}postmeta`, сопоставляя её slug `post_name` с вашей моделью.

::: tip Нестандартный префикс таблиц
Управляемые хостинги WordPress часто используют случайный префикс, например `wppg_` вместо `wp_`. Проверьте имена `CREATE TABLE` в дампе и передайте реальный префикс — `--table=wppg_`, — чтобы были найдены `{prefix}posts` и `{prefix}postmeta`.
:::

::: tip Чтение восстановленного дампа на MySQL 8
Если вы восстанавливаете дамп WordPress в MySQL 8+ для локального чтения, ослабьте строгий SQL-режим перед выполнением `.sql`. Стандартные режимы MySQL 8 `STRICT`/`NO_ZERO_DATE` отклоняют значения datetime по умолчанию `'0000-00-00'` из WordPress, поэтому сам импорт дампа завершается ошибкой `Invalid default value for 'post_date'` ещё до запуска SEO-импорта:

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Сопоставление полей {#field-mapping}

Оба импортёра сопоставляют поля **явно**: ключ без столбца в Core 3 отмечается как *несопоставленный*, а не создаётся искусственно.

| Ключ метаданных Yoast | Ключ метаданных Rank Math | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Сохраняются только отклонения от значений WordPress по умолчанию, поэтому обычная индексируемая страница оставляет `robots` равным null и наследует значение вашего сайта. Отдельные флаги Yoast `noindex` / `nofollow` и расширенные `noarchive`, `nosnippet`, `noimageindex` собираются в одну строку. Сериализованный массив `robots` Rank Math читается аналогично, с удалением стандартных `index` / `follow`.

**Несопоставленные ключи**, которые попадают в отчёт, но никогда не копируются: ID вложенных изображений (`*-image-id`), оценки ключевых слов/SEO (`linkdex`, `content_score`, `rank_math_seo_score`), выбор основной категории и маркеры разметки расширенных сниппетов Rank Math. [Граф разметки](/ru/guide/schema) предоставляет более богатую типизированную замену последним.

::: warning Канонические URL импортируются дословно
Явный канонический URL (`rank_math_canonical_url` / `_yoast_wpseo_canonical`) копируется **точно в сохранённом виде**. Если страница закрепила абсолютный канонический URL на *старом* домене — частый случай на управляемом или staging-хостинге, например `https://oldsite-staging.example.com/page/`, — после импорта он продолжит вести туда: импорт никогда не переписывает хост. `--site-url` извлекает *пути* запросов из абсолютных URL для [кандидатов на перенаправление](#redirects) и сопоставления строк CSV, но **не** переписывает сохранённые канонические значения. После смены домена проверьте импортированные канонические URL и обновите хост либо очистите их, чтобы резолвер выбрал канонический URL самой страницы. Большинство страниц не имеют явного канонического URL и не затронуты: Yoast и Rank Math формируют его автоматически при выводе.
:::

### Токены шаблонов {#template-tokens}

Yoast и Rank Math хранят заголовки и описания как **шаблоны** с токенами: Yoast использует `%%title%%`, Rank Math — `%title%`. Импорт **подставляет вычислимые токены** и **удаляет остальные**, поэтому сохранённое значение не остаётся сырой строкой `%%token%%`:

| Токен | Подставляемое значение |
|---|---|
| `%%title%%` / `%title%` | Заголовок публикации WordPress |
| `%%sitename%%` / `%sitename%` | Название блога из `wp_options` при импорте из базы |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *Удаляется*: оставляется пустое место, окружающие разделители приводятся в порядок |

Если при запуске подставлялись токены, это указывается в отчёте. **Проверьте импортированные заголовки**, чтобы они читались как задумано, и исправьте те, которые зависели от невычислимых токенов.

---

## Перенаправления {#redirects}

`seo_redirects` относится к [Rankbeam **Pro**](/ru/pro/installation), поэтому импорт ядра никогда не записывает эту таблицу напрямую. Передайте `--redirects-csv=`: импорт **создаст CSV** с теми же столбцами, что и таблица перенаправлений Pro, — `source_path,target_url,status_code,note`, — для последующего импорта в Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Источники кандидатов на перенаправление:

- **Импорт CSV:** строка, чей `canonical` указывает на **другой путь**, чем её собственный `url`, становится `301` со старого пути на канонический URL. Канонический URL с тем же путём *не* выводится: это создало бы цикл.
- **База Rank Math:** активные правила таблицы `{prefix}rank_math_redirections`. Выводятся только правила **точного совпадения**; regex/contains/start/end отмечаются как пропущенные, поскольку им не соответствует один конкретный путь.
- **Yoast (бесплатный)** не имеет таблицы перенаправлений. Она есть только в Yoast Premium, и её схема не входит в бесплатный пакет. Для перенаправлений Yoast используйте CSV.

Кандидаты имеют **рекомендательный характер**. Проверьте CSV, затем импортируйте его в Pro через [`seo-pro:redirects-import`](/ru/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro), который проверяет каждую строку, отклоняя циклы, небезопасные цели и дубли. Структура CSV — стабильный контракт **формат CSV перенаправлений v1**: `source_path,target_url,status_code,note`.

---

## Что сообщает отчёт {#what-the-report-tells-you}

Запуск без `--json` выводит таблицу результатов (created / updated / unchanged / skipped / scanned), **Verification report** и разделы для проверки:

- **Verification report** — краткое распределение для вашего подтверждения: **matched** (строки, связанные с моделью), **url-only** (без соответствующей модели) и количество сокращённых и несопоставленных значений.
- **Truncated** — значения, сокращённые до длины столбца `seo_meta`.
- **Not imported** — исходные ключи с данными, для которых нет места в Core 3, **включая каждое отдельное значение `author`**. Автор не является сохраняемым столбцом, а относится к [`getSEOAuthor()`](/ru/concepts/resolver-precedence); отчёт показывает, что нужно перенести в другое место, вместо незаметной потери.
- **Redirect candidates** — сколько кандидатов записано и в какой файл.
- **Skipped rows by reason** — строки только с URL, публикации без SEO-метаданных, правила перенаправлений без точного совпадения.
- **Warnings** — например, сообщение о подстановке токенов шаблона.

Добавьте `--json` для машиночитаемой версии всего перечисленного. Блок `verification` содержит количество matched/url-only и каждое значение автора.

### Проверка {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict` возвращает ненулевой код завершения, если на любой странице есть проблема. См. [Бесплатный SEO-аудит](/ru/guide/audit). Полную последовательность переключения — сосуществование → импорт → проверка → вывод старой системы из эксплуатации — см. в [плане миграции с WordPress](/ru/guide/wordpress-migration-runbook).

---

Переходите с SEO-пакета **Laravel**: ralphjsmit, artesaos или Spatie? См. [Миграция с других пакетов Laravel](/ru/guide/migrate-from-other-packages).

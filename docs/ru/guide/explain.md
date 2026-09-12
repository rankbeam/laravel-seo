---
description: "seo:explain показывает, какой слой резолвера задал каждое поле SEO и что он переопределил. Команда только читает данные, не требует сети и лицензии и помогает разобраться с неожиданным заголовком или тегом robots."
---

# Разбор итоговых значений (`seo:explain`) {#explain-the-resolution-seo-explain}

Rankbeam определяет SEO страницы по [цепочке слоёв с разными приоритетами](/ru/concepts/resolver-precedence): конфигурация, значения по умолчанию в базе данных (глобальные / для типа модели / для маршрута), вычисляемые значения модели и затем явные `seo_meta`. После этого выполняются постобработка (суффикс заголовка, канонический URL, преобразование URL изображений в абсолютные) и [защита от индексации](/ru/guide/indexing-guard). Если выведенный тег `<title>` или `robots` не соответствует ожиданиям, **`seo:explain` точно показывает, какой слой задал каждое поле и что он переопределил.**

Команда только читает данные, не требует доступа к сети или лицензии и не реализует слияние заново: сведения об источнике берутся из вкладов слоёв самого резолвера, а итоговые значения — из настоящего резолвера. Поэтому объяснение не может разойтись с фактическим выводом.

## Использование {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

Модель должна использовать трейт [`HasSEO`](/ru/guide/quickstart).

## Как читать вывод {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by** — победивший слой (слой с наивысшим приоритетом, задавший значение, отличное от null) либо `post-processing`, если ни один слой не задал поле, но значение было *получено из других данных* (канонический URL из URL запроса или модели, og:url из канонического URL, абсолютный URL изображения).
- **Overrode** — все слои с более низким приоритетом, предложившие значение и уступившие, по порядку: так видно, какие значения были перекрыты.
- **↳ notes** — постобработка, изменившая значение после слияния слоёв: суффикс заголовка, удаление строки запроса из канонического URL, вычисление og:url, преобразование URL изображений в абсолютные и защита от индексации, которая принудительно задаёт `noindex` поверх всех слоёв.

::: tip og:type и twitter:card
У этих двух полей есть отличные от null значения по умолчанию на уровне фреймворка (`website` / `summary_large_image`), поэтому самый высокий слой, который их задаёт, — обычно `computed` — имеет приоритет над `config`. Страница без сохранённой строки `seo_meta` ничего для них не добавляет, поэтому вычисленное `og:type`, например `article`, не перекрывается базовым значением `website`. Это соответствует фактическому результату слияния.
:::

## Итоговые значения на уровне сайта {#site-level-resolution}

Согласно [дополнению об учёте конфигурации сайта](/ru/concepts/resolver-precedence), `seo:explain` также показывает общие для сайта значения, чьи источники часто вызывают путаницу: **какой источник задал канонический хост, название сайта и локаль по умолчанию**:

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

В первую очередь стоит проверить канонический хост: неверный хост (случайно попавший `localhost`, `http://` на сайте с `https` или несовпадение URL приложения и URL модели) — типичная причина ошибок в канонической ссылке страницы на саму себя.

## Вывод JSON {#json-output}

`--json` выводит полную трассировку — `target`, значения `winner` / `losers` / `final` / `notes` для каждого поля и реестр `site_level` — для инструментов или CI:

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## См. также {#see-also}

- [Приоритеты резолвера](/ru/concepts/resolver-precedence) — полная цепочка, которую отслеживает `seo:explain`.
- [Бесплатный SEO-аудит](/ru/guide/audit) — `seo:audit` находит, *что не так*; `seo:explain` показывает, *почему значение получилось именно таким*.


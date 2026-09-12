---
description: "Выводите SEO в серверном Laravel через Blade-директивы пакета: универсальная @seo получает данные модели и выводит метаданные, Open Graph, Twitter Cards и JSON-LD."
---

# Руководство по Blade {#blade-guide}

Для классических приложений с серверным рендерингом пакет предоставляет семь Blade-директив. Обычно достаточно одной из них — `@seo`.

## Универсальная директива {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo` получает значения модели через [цепочку приоритетов](/ru/concepts/resolver-precedence) и выводит полный блок head: `<title>`, метаописание, каноническую ссылку, robots, теги Open Graph и Twitter Card, а также прикреплённый JSON-LD. Тег robots выводится **только при отличии от значения сайта по умолчанию**: избыточный `index,follow` пропускается (его отсутствие уже означает index,follow). Задайте `seo.robots.emit_default`, чтобы выводить его всегда. См. полный [контракт рендеринга](/ru/contributing/rendering-contract).

Сигнатуры:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo` принимает `Model`, созданный вручную `SEOData` или `null`. Аргументы маршрута и локали применяются только при передаче `Model`/`null`: созданный вручную `SEOData` содержит собственные значения.

## Страницы маршрутов (без модели) {#route-pages-no-model}

Для статических страниц, архивов и других страниц, определяемых маршрутом:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Значения маршрута берутся из строк `seo_defaults`, привязанных к имени маршрута.

## Страницы без модели: созданный вручную `SEOData` {#model-less-pages-hand-built-seodata}

Списки, результаты поиска и страницы, собранные в контроллере, часто не связаны с одной моделью. Создайте `SEOData` и передайте его прямо в `@seo` (или фасад `SEO`) — обращаться к `app(TagRenderer::class)->render(...)` не требуется:

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

Созданный вручную `SEOData` трактуется как **явно заданное намерение**. Все заданные значения сохраняются; автоматически заполняются только пробелы, возникающие при выводе:

- `canonical` / `og:url` при отсутствии вычисляются из текущего URL (явный `canonical` сохраняется без изменений, включая строку запроса);
- `title_suffix` добавляется, только если его нет в заголовке (и полностью пропускается, если заголовок уже содержит маркер бренда — см. [`title_suffix_skip_when_contains`](/ru/reference/configuration));
- относительные пути `og:image` / `twitter:image` преобразуются в абсолютные через `url()` (с сохранением текущей схемы — HTTPS **не** навязывается);
- `og:site_name` и `locale` заполняются из конфигурации / локали приложения.

Цепочка приоритетов базы данных (глобальные значения / значения для типа модели / маршрута / `seo_meta`) **не** объединяется с созданным вручную `SEOData`: выводится то, что вы передали, с заполнением только перечисленных пробелов.

Тот же объект работает через фасад:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Шаблон, который подходит для разных страниц {#a-layout-pattern-that-scales}

Один шаблон для страниц моделей, страниц маршрутов и всех остальных:

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

После этого контроллеры передают `'seoModel' => $post` или `'seoRoute' => 'blog.index'` и не работают с разметкой.

## Отдельные директивы {#granular-directives}

Если требуется управлять отдельными тегами (например, при сочетании с выводом другого пакета):

| Директива | Что выводит |
|---|---|
| `@seoTitle($post)` | Только `<title>` |
| `@seoMeta($post)` | Только метаописание |
| `@seoCanonical($post)` | Только каноническую ссылку (при отсутствии значения используется текущий URL) |
| `@seoRobots($post)` | Только метатег robots — выводится всегда (это явное включение, поэтому подавление при совпадении со значением по умолчанию, как у `@seo`, **не** применяется) |
| `@seoSchema($post)` | Только JSON-LD `<script>` — допустим в head и body |

Все они принимают то же выражение `($model, $route, $locale)`, что и `@seo`, либо вызываются без аргумента для текущей страницы.

## Альтернативные языковые версии hreflang {#hreflang-alternates}

Модели с `HasSEO` могут передавать ссылки hreflang напрямую через резолвер:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Используйте абсолютные URL. `@seo($post)` обрабатывает эти записи и выводит каждую как `<link rel="alternate" hreflang="..." href="...">`. Сначала коды приводятся к форме BCP 47 (`it_IT` → `it-IT`), а политики `seo.hreflang` могут добавить ссылку страницы на саму себя и `x-default`; бесплатный аудит отмечает неверные и повторяющиеся записи, а также отсутствие ссылки на саму страницу. См. [многоязычный контент](/ru/guide/multilingual#hreflang).

## Экранирование и безопасность {#escaping-and-safety}

Текстовые значения экранируются через `e()`. JSON-LD кодируется с `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`, поэтому `</script>` внутри пользовательского контента не может выйти за пределы элемента script.


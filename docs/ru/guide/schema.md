---
description: "Выводите связанный граф schema.org в JSON-LD: узлы Organization, WebSite, WebPage и Article ссылаются друг на друга через стабильные @id, создавая согласованный граф на каждой странице."
---

# Граф schema.org (JSON-LD) {#schema-graph-json-ld}

Поисковым системам проще читать JSON-LD, когда узлы ссылаются друг на друга: Organization публикует WebSite, WebSite содержит WebPage, а WebPage посвящена Article. Именно такой результат создаёт `SchemaGraph`: набор узлов, связанных **стабильными значениями `@id`**, чтобы каждая страница выводила согласованный граф.

## Граф страницы {#the-page-graph}

```php
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\Schema\SchemaCollection;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

$seo = SEO::resolve($post);

$graph = new SchemaGraph();

$schemas = SchemaCollection::make()
    ->add($graph->organization())   // @id: {app_url}#organization
    ->add($graph->webSite())        // @id: {app_url}#website, publisher → #organization
    ->add($graph->webPage($seo));   // @id: {page_url}#webpage, isPartOf → #website
```

Выведите его в Blade (в head или body):

```blade
{!! $schemas->toScript() !!}
```

Данные Organization и WebSite берутся из `config/seo.php` (`schema.organization`, `schema.website`); узел WebPage заполняется из полученного `SEOData`.

Узел WebPage получает `inLanguage` из итоговой локали страницы в форме BCP 47 (`it_IT` → `it-IT`), `ArticleSchema::fromModel()` берёт его из сохранённой локали `seo_meta`, а узел WebSite перечисляет языки сайта из `schema.website.inLanguage` (один код или список). Задайте `schema.in_language` равным `false`, чтобы вообще не выводить `inLanguage`. См. [многоязычный контент](/ru/guide/multilingual#inlanguage-in-the-schema-graph).

## Типизированные построители {#typed-builders}

Для распространённых типов расширенных результатов есть готовые построители:

| Построитель | Примечания |
|---|---|
| `ArticleSchema::fromModel($post)` | Даты, автор и издатель из модели и конфигурации |
| `ProductSchema` | Предложения, цена, наличие |
| `BreadcrumbSchema::fromArray([...])` | Упорядоченные пары имя/URL |
| `BreadcrumbSchema::fromModelAncestors($page)` | Проходит цепочку `parent` (с защитой от зацикливания) |
| `FAQSchema` | Пары вопрос/ответ |
| `LocalBusinessSchema` | Адрес, географические координаты, часы работы |
| `OrganizationSchema` | Отдельный узел организации |

Полная страница статьи:

```php
$article = ArticleSchema::fromModel($post)
    ->setPublisherOrganization(config('seo.schema.publisher.name'));

$schemas = SchemaCollection::make()
    ->add($graph->organization())
    ->add($graph->webSite())
    ->add($graph->webPage($seo))
    ->add($article->toArray())
    ->add(BreadcrumbSchema::fromArray([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog'],
        ['name' => $post->title, 'url' => "/blog/{$post->slug}"],
    ])->toArray());
```

## Прикреплённая разметка и `@seoSchema` {#attached-schema-and-seoschema}

Разметка schema.org, сохранённая в итоговом `SEOData` (например, вместе с явными метаданными), выводится директивой `@seoSchema` или через раздел `script` в `SEO::toArray()`:

```blade
@seoSchema($post)
```

Редакторы могут заполнять `seo_meta.schema_jsonld` без кода через необязательный раздел **Структурированные данные** в пакете [полей Filament](/ru/guide/filament#structured-data-schema-org): переключатель автоматических хлебных крошек и блоки FAQ / Товар, которые проверяются через `SchemaValidator` перед сохранением.

## Экранирование {#escaping}

Весь вывод JSON-LD — `SchemaCollection::toScript()`, `toJson()` и остальные пути рендеринга — кодируется с `JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP`. Последовательность `</script>` в заголовках или контенте не может закрыть элемент script. Не обходите эту защиту, самостоятельно применяя `json_encode` к массивам schema.org.


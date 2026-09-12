---
description: "Отдавайте ИИ-роботам чистое Markdown-представление страницы через согласование содержимого, сохраняя исходный HTML для обычных посетителей. Бесплатная функция ядра, по умолчанию выключена."
---

# Markdown для роботов {#markdown-for-bots}

HTML приложения окружает контент навигацией, скриптами и разметкой шаблона. Некоторые ИИ-роботы и системы ответов принимают более чистое представление, если оно доступно. Эта функция может отдавать **Markdown-представление** страницы клиентам, которые запрашивают его через согласование содержимого, тогда как обычные посетители продолжают получать неизменённый HTML. Это включаемая по желанию совместимость, а не обещание того, как конкретный клиент разберёт или использует результат.

Функция дополняет [управление ИИ-роботами](/ru/guide/ai-crawlers): оно задаёт политику доступа, а эта функция выбирает, *какое* содержимое отдать во время запроса.

Это бесплатная функция ядра, **по умолчанию выключенная**.

## Как это работает {#how-it-works}

При включении регистрируется middleware согласования содержимого. После формирования обычного ответа он заменяет его Markdown **только при выполнении обоих условий**:

1. **Запрос просит Markdown** — через явный заголовок `Accept: text/markdown`, параметр запроса `?format=md` или, при отдельном включении, через user-agent известного ИИ-робота.
2. **Для маршрута найден источник Markdown.**

Иначе ответ проходит без изменений: обычная работа браузера не затрагивается, а заменяется только успешный **HTML-ответ**, никогда JSON, перенаправление или скачиваемый файл.

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Откуда берётся Markdown {#where-the-markdown-comes-from}

Источники ниже предоставляют Markdown для найденного маршрута. Middleware **сначала пробует зарегистрированный источник маршрута**, затем привязанные к маршруту модели. Для каждой модели явный метод `toSeoMarkdown()` имеет приоритет над встроенным резервным вариантом; null или пустой результат этого метода отключает резервный вариант для этой модели.

### 1. Собственный Markdown модели {#_1-a-model-s-own-markdown}

Если ни один зарегистрированный источник маршрута не вернул содержимое, привязанная к маршруту модель с `toSeoMarkdown()` управляет своим выводом (реализуйте контракт `ProvidesSeoMarkdown` или просто добавьте метод):

```php
use Rankbeam\Seo\Contracts\ProvidesSeoMarkdown;

class Post extends Model implements ProvidesSeoMarkdown
{
    use HasSEO;

    public function toSeoMarkdown(): ?string
    {
        return $this->body_markdown; // your already-clean markdown
    }
}
```

### 2. Зарегистрированный источник маршрута {#_2-a-registered-route-source}

Для маршрутов без модели или переопределения вывода модели зарегистрируйте источник по имени маршрута:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. Встроенный резервный вариант {#_3-the-built-fallback}

Если у привязанной к маршруту модели `HasSEO` нет `toSeoMarkdown()`, middleware создаёт простой документ из итогового **заголовка** (как H1), **описания** и **`getContentForSEO()`** модели:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning Содержимое отдаётся как есть
Резервный вариант выводит `getContentForSEO()` без изменений. Если контент — HTML, а не Markdown, реализуйте `toSeoMarkdown()` для управления преобразованием. Полностью отключите резервный вариант через `seo.markdown_for_bots.build_from_content = false`.
:::

## Конфигурация {#configuration}

```php
// config/seo.php
'markdown_for_bots' => [
    'enabled'            => false,    // off by default; the middleware isn't registered until true
    'auto_register_middleware' => true,
    'serve_to_known_bots' => false,   // also serve to known AI crawlers by user-agent
    'query_param'        => 'format', // the ?format=md trigger
    'query_value'        => 'md',
    'build_from_content' => true,     // build from getContentForSEO() when no toSeoMarkdown()
],
```

Оставьте `serve_to_known_bots` выключенным, чтобы выбирать формат только по явным сигналам `Accept` / `?format`. Включите его, чтобы также отдавать Markdown GPTBot, ClaudeBot, PerplexityBot и другим роботам из [каталога ИИ-роботов](/ru/guide/ai-crawlers), даже когда они его не запрашивают.


---
description: "Формируйте управляемый robots.txt и необязательный ai.txt из политики разрешений и запретов для поддерживаемого каталога ИИ-роботов. Вы выбираете, какие боты могут загружать сайт. Бесплатная функция ядра."
---

# Управление ИИ-роботами (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

Крупные операторы ИИ обходят интернет с помощью именованных ботов; большинство читает **robots.txt**, чтобы определить, что разрешено загружать. Rankbeam содержит поддерживаемый каталог таких ботов и формирует управляемый `robots.txt` и необязательный `ai.txt` из простой политики разрешений и запретов. Так вы можете **разрешить доступ роботам ИИ-поиска и ассистентов, ограничив тех, кто использует ваш контент для обучения.**

Это бесплатная функция ядра. Пакет Pro добавляет вторую половину — [наблюдение: журнал обращений ИИ-ботов](/ru/pro/ai-bot-monitor), который показывает, какие ИИ-роботы действительно посещали сайт.

## Политика по умолчанию {#the-default-policy}

Каждому боту в каталоге назначена основная задача:

| Назначение | Что делает | По умолчанию |
| --- | --- | --- |
| `ai_search` | Загружает страницы для индексации и ответов **ИИ-поиска** (канал переходов из ИИ) | **разрешить** |
| `ai_assistant` | Загружает страницу в реальном времени от имени **пользователя** в чате | **разрешить** |
| `ai_training` | Собирает контент для **обучения** модели | **запретить** |

Это отражает подход большинства издателей к эпохе ИИ: оставаться доступными роботам ИИ-поиска и ассистентов, обслуживающим поиск ChatGPT, Perplexity и подобные инструменты, но отказываться от использования контента для обучения. Любую часть политики можно изменить в конфигурации.

::: warning Доступ не означает цитирование
Разрешение для робота делает загрузку *возможной*, но не гарантирует обнаружение, индексацию, позиции, включение в ответ, цитату или ссылку на источник. Эта политика управляет **доступом** — какие боты могут загружать страницы, — а не последующими результатами.
:::

## Быстрый старт {#quick-start}

Выведите блок ИИ-роботов, чтобы увидеть, что будет опубликовано:

```bash
php artisan seo:robots-txt --print
```

Использовать его можно двумя способами.

### Вариант A — вставить блок в существующий robots.txt {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Если вы уже поддерживаете `public/robots.txt`, возьмите только управляемый блок и вставьте его:

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### Вариант B — поручить Rankbeam весь файл {#option-b-—-let-rankbeam-manage-the-whole-file}

Создайте полный `robots.txt`: общий раздел, директивы ИИ, строку `Sitemap:` и указатель на ваш [llms.txt](/ru/guide/sitemaps):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Настройте расписание, чтобы файл соответствовал политике:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Или отдавайте его динамически: задайте `seo.ai_crawlers.route` значение `true`, и пакет будет отвечать на `/robots.txt` по текущей конфигурации без этапа генерации:

::: warning Статический файл имеет приоритет
В большинстве приложений уже есть `public/robots.txt`, который веб-сервер отдаёт до маршрутизации запроса Laravel. Динамический маршрут **по умолчанию выключен**, чтобы он не мог незаметно перекрыть забытый файл или быть перекрытым им. Используйте маршрут только при отсутствии статического `robots.txt`.
:::

## Реальные пределы применения запретов {#honesty-about-enforcement}

robots.txt — просьба, а не ограждение. Большинство ботов каталога документированно соблюдают его, но для некоторых агентов по запросу пользователя (`ChatGPT-User`, `Perplexity-User`) и роботов обучения (`Bytespider`) это **не** подтверждено. Rankbeam помечает такие строки как `advisory`, не создавая впечатления надёжной блокировки. Чтобы действительно остановить бота, который не соблюдает правила, нужна блокировка на сервере или edge-уровне: межсетевой экран, WAF, правила ботов Cloudflare. [Журнал ИИ-ботов Pro](/ru/pro/ai-bot-monitor) показывает, какие боты требуют внимания.

## Content signals (предпочтения использования) {#content-signals-usage-preferences}

`Allow` / `Disallow` управляют **доступом**: может ли бот загрузить страницу. [Content signals](https://contentsignals.org), стандарт, продвигаемый Cloudflare, задают другую ось: как разрешено **использовать** уже загруженный контент. Одна строка `Content-Signal:` в группе `User-agent: *` содержит три предпочтения:

| Сигнал | Определяется назначением политики | Значение |
| --- | --- | --- |
| `search` | `ai_search` | Построение поискового индекса (ссылки и короткие выдержки) |
| `ai-input` | `ai_assistant` | Передача страницы модели ИИ в реальном времени (RAG / grounding) |
| `ai-train` | `ai_training` | Обучение или дообучение модели ИИ |

Функция **по умолчанию выключена**: до её включения файл остаётся побайтово прежним. После включения Rankbeam формирует строку непосредственно из существующей `policy`: `allow` становится `yes`, а `disallow` — `no`:

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Если полностью удалить назначение из `policy`, соответствующий сигнал **не выводится**. В спецификации это означает «предпочтение не выражено», что отличается от явного `yes`/`no`.

::: warning Рекомендация, как и сам robots.txt
Content signals выражают предпочтение и **не** являются технической мерой контроля. Робот может их проигнорировать. Они дополняют, а не заменяют правила доступа выше и блокировки на edge-уровне.
:::

## Конфигурация {#configuration}

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose. A purpose left out is allowed.
    'policy' => [
        'ai_training'   => 'disallow',
        'ai_search'     => 'allow',
        'ai_assistant'  => 'allow',
        'search_engine' => 'allow',   // Yandex, Baidu, Naver, Seznam, … (3.15)
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
        'baiduspider' => 'disallow',  // e.g. keep a search engine you don't serve off your bandwidth
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

Переопределите поведение отдельного бота независимо от назначения через `overrides` с ключом **id** из каталога, например `gptbot`, `claudebot`, `perplexitybot`, `google-extended`.

## Каталог {#the-catalog}

`SEO::aiCrawlers()` — источник истины. Тот же каталог использует журнал Pro для распознавания посетителей, поэтому файл управления ботом и панель наблюдения за ним не расходятся.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Каталог охватывает крупных операторов: OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User), Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended), Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon, ByteDance и других. Для каждого указаны документированное назначение и токен robots.txt.

### Региональные поисковые системы {#regional-search-engines}

Начиная с 3.15 каталог также содержит классических поисковых роботов, важных за пределами Google и Bing. Они имеют назначение `search_engine` и **разрешены по умолчанию**:

| id | Токен | Оператор |
|---|---|---|
| `yandex` | `Yandex` | Yandex (Россия) — базовый токен охватывает всех его ботов |
| `baiduspider` | `Baiduspider` | Baidu (Китай) |
| `yeti` | `Yeti` | Naver (Корея) |
| `seznambot` | `SeznamBot` | Seznam (Чехия) |
| `sogou` | `Sogou web spider` | Sogou (Китай) |
| `360spider` | `360Spider` | Qihoo 360 (Китай) |
| `coccocbot` | `coccocbot-web` | Cốc Cốc (Вьетнам) |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Они участвуют в `policy` и `overrides` так же, как остальные боты. Поэтому `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` позволяет не расходовать пропускную способность на двух ненужных вам роботов, а `'list' => 'all'` создаёт для каждого явную строку. Они **не входят** в `all()` и `match()` без явного запроса — `searchEngines()`, `all(true)`, `match($ua, true)`, — чтобы журнал ИИ-ботов Pro и все счётчики «N ИИ-роботов» сохраняли смысл:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Распознавание робота не гарантирует видимость или позиции в этой поисковой системе. Соответствующие теги подтверждения сайта (`yandex-verification`, `baidu-site-verification`, `naver-site-verification`, `seznam-wmt`) находятся в `seo.verification`; см. [Многоязычный контент](/ru/guide/multilingual#site-verification).

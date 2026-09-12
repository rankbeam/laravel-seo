---
description: "Запустите php artisan seo:audit и получите таблицу pass/warn/fail по страницам с текущими проблемами SEO — внутри процесса, без очереди, лицензии и сети. Бесплатно, в ядре."
---

# Бесплатный SEO-аудит (`seo:audit`) {#free-seo-audit-seo-audit}

`php artisan seo:audit` бесплатно отвечает одной командой на вопрос: **что сейчас не так с моим SEO?** Он перебирает модели `HasSEO` внутри процесса — **без очереди, лицензии и сети** — и выводит таблицу **pass / warn / fail** по страницам со сводкой.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## Что проверяется {#what-it-checks}

Аудит выполняет только класс **metadata**: проверки, которым достаточно модели и [резолвера](/ru/concepts/resolver-precedence), без загрузки страницы:

| Проверка | Коды |
|---|---|
| Наличие заголовка / описания с учётом резервных значений | `missing_title`, `missing_description` |
| Наличие OG-изображения с учётом резервных значений | `missing_og_image` |
| Длина заголовка / описания | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Повторяющиеся заголовки / описания на сайте | `duplicate_title`, `duplicate_description` |
| Конфликты robots и подозрительный noindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Формат канонического URL / другой домен / общий URL / незащищённый URL | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Готовность к ответам (AEO): структурированные данные статьи | `aeo_missing_author`, `aeo_article_missing_date` |
| Наличие фокусного ключевого слова (по желанию) | `missing_focus_keyword` |
| Альтернативные версии hreflang (реестр ядра, если страница их объявляет) | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

Большинство кодов встречается и в сканировании Pro, но реестры раздельные. В частности, ядро использует `hreflang_missing_self`, а Pro — `hreflang_missing_self_reference`; `hreflang_duplicate_code` имеет уровень notice в ядре и warning в Pro. Одинаковое имя не означает одинаковый охват или серьёзность. `blank_explicit_override` принадлежит реестру ядра. Для длины применяется [лимит редактора по письменности](/ru/guide/multilingual#title-and-description-budgets-per-script): 60/160 символов для латиницы, около 30/80 для CJK, в графемах и по **итоговому** значению с суффиксом. Поэтому аудит не противоречит счётчикам [редактора Filament](/ru/guide/filament). Проверки hreflang работают со списком после политик `seo.hreflang` — тем же, который выводят теги и карта сайта. Проверка взаимности требует обхода и остаётся в Pro.

Проверки **готовности к ответам (AEO)** срабатывают только при объявленном JSON-LD типа статьи (`Article`, `BlogPosting`, `NewsArticle`, …), в котором отсутствует признак, делающий статью понятной через структурированные данные: сущность `author` (явное авторство / происхождение) либо `datePublished` / `dateModified` (явная временная история). Страница без статьи никогда не отмечается, поэтому аудит не создаёт замечаний там, где AEO неприменимо. Это рекомендации уровня notice, исключённые из оценки Pro 0–100.

## Что *не проверяется*: границы возможностей {#what-it-does-not-check-—-the-capability-boundary}

Бесплатный аудит внутри процесса не эквивалентен полному сканированию Pro, и команда сообщает об этом при каждом запуске. Он **не выполняет**:

- **Проверки отрендеренного HTML** — `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content`, `mixed_content`. Им нужен отдаваемый HTML страницы.
- **Сетевые проверки канонических целей** — `canonical_target_broken` / `_redirect` / `_noindex`. Им нужен исходящий защищённый запрос.
- **Числовую оценку 0–100.** Оценка — функция Pro, сохраняемая в результате сканирования вместе с версией методики; см. [SEO-оценку](/ru/pro/scoring).

Эти возможности входят в **сканирование Pro**; см. полный [реестр проблем](/ru/pro/scan-issues).

## Выбор объектов аудита {#choosing-what-to-audit}

По умолчанию команда проверяет модели из `seo.audit.models`, а при отсутствии использует `seo.sitemap.models`:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Или передайте модели явно:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Параметры {#options}

| Параметр | Действие |
|---|---|
| `--model=` | Класс модели с `HasSEO` для аудита (можно повторять). Переопределяет конфигурацию. |
| `--locale=` | Определяет итоговые данные SEO в этой локали (по умолчанию локаль приложения). |
| `--limit=` | Максимум записей на модель (`0` = все). |
| `--issues-only` | Показывает только страницы хотя бы с одной проблемой. |
| `--strict` | Завершает с ненулевым кодом при любой найденной проблеме — для CI. |
| `--json` | Выводит машиночитаемый JSON (страницы, сводка, охват) вместо таблицы. |

### Обязательная проверка CI {#ci-gate}

`--strict` превращает аудит в проверку сборки:

```bash
php artisan seo:audit --strict
```

Код завершения — `1`, если у любой страницы предупреждение или ошибка, и `0`, если все проверенные страницы прошли.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Фокусные ключевые слова {#focus-keywords}

Замечание `missing_focus_keyword` **по умолчанию выключено**. Оно появляется только после включения работы с фокусными ключевыми словами:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Сканирование Pro читает **тот же** флаг, поэтому аудит, сканирование и напоминание редактора Pro всегда согласованы. Задайте ключевые слова страницы через [поле Filament](/ru/guide/filament) или `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Если значение неожиданно: `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` говорит, *что не так*; [`seo:explain`](/ru/guide/explain) — *почему поле получило именно такое значение*: какой слой (конфигурация / значение по умолчанию / вычисляемое / явное) его задал, что переопределил и какая постобработка (суффикс заголовка, очистка канонического URL, защита от индексации) изменила его позже. Используйте команду, когда результат аудита или выведенный тег удивляет:

```bash
php artisan seo:explain "App\Models\Post" 42
```



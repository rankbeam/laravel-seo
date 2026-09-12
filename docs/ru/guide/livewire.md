---
description: "Используйте универсальные директивы @seo Rankbeam в приложениях Livewire: они выводят обычный HTML в head и работают в полностраничных компонентах и Blade-шаблонах так же, как в Blade."
---

# Livewire {#livewire}

Blade-директивы `@seo` не зависят от фронтенд-фреймворка: они выводят обычный HTML в `<head>`, поэтому в любом приложении Livewire работают так же, как в Blade.

## Первоначальный вывод полной страницы {#initial-full-page-render}

В **полностраничном компоненте Livewire** (когда маршрут возвращает компонент) или в любом Blade-шаблоне, содержащем компоненты Livewire, `@seo` работает точно так же, как в [руководстве по Blade](/ru/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

Первый HTTP-ответ содержит полный head, доступный роботам: заголовок, описание, каноническую ссылку, Open Graph, Twitter и JSON-LD. Именно этот ответ получают поисковые роботы и сборщики превью соцсетей, и в нём всё выводится корректно.

## Особенность `wire:navigate` {#the-wire-navigate-caveat}

[`wire:navigate`](https://livewire.laravel.com/docs/navigate) в Livewire превращает переходы по ссылкам в навигацию в стиле SPA. При таком переходе Livewire заменяет `<body>` и **объединяет `<head>`**, но для SEO-пакета важно одно различие:

- **`<title>` и `<meta>`/`<link>`** объединяются с head новой страницы, поэтому итоговые заголовок и метаданные обычно обновляются.
- **`<script>` считается неудаляемым ресурсом.** Livewire сохраняет каждый встреченный `<script>`, чтобы повторное выполнение не нарушило работу JavaScript. Поэтому **блоки JSON-LD `<script>` накапливаются**: после посещения трёх записей в head одновременно остаётся разметка всех трёх, а инструмент, читающий структурированные данные, видит неверные или несколько сущностей.

Чтобы их можно было очищать, средство вывода **помечает каждый создаваемый JSON-LD script**:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## Добавьте очистку JSON-LD {#ship-the-json-ld-cleanup}

Добавьте этот код один раз (например, в корневом шаблоне после `@livewireScripts`). При каждом `wire:navigate` он оставляет разметку только **текущей страницы** и удаляет устаревшую:

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        // The page we are now on. data-seo-url is the canonical (query-stripped),
        // so compare on the query-stripped location.
        const here = window.location.href.split('#')[0].split('?')[0]

        // Keep only the LAST schema for this page; remove every other-URL
        // (stale) script AND same-URL duplicates Livewire re-adds when a page is
        // revisited — including clearing a lone stale script when this page has
        // none. Iterate from the end so the freshest copy is the one kept.
        const scripts = [...document.querySelectorAll('script[data-seo-schema]')]
        let kept = false
        for (let i = scripts.length - 1; i >= 0; i--) {
            const url = (scripts[i].getAttribute('data-seo-url') || '').split('?')[0]
            if (url === here && !kept) { kept = true; continue }
            scripts[i].remove()
        }
    })
</script>
```

Используются только маркер `data-seo-schema` и идентификатор для каждого URL, которые средство вывода уже добавляет. Настраивать каждую страницу отдельно не требуется.

::: warning Сравнивайте с текущим URL, а не с последним добавленным script
Предыдущая версия этого примера завершалась, если скриптов schema было меньше двух, и считала *последний добавленный* скрипт текущей страницей. Из-за этого при переходе со страницы **с** JSON-LD на страницу **без** него в head оставалась устаревшая разметка (присутствовал только старый скрипт, который сохранялся из-за раннего возврата). Такой код также не удалял **дубликат для того же URL**, который Livewire добавляет при повторном посещении страницы. Сравнение каждого `data-seo-url` с `window.location` и сохранение только **последнего** совпадения во всех случаях удаляет и устаревшую разметку, и дубликаты. Именно это проверяют приложение Livewire `rankbeam-examples` и его браузерный тест.
:::

::: tip Одиночные метатеги при SPA-навигации
Объединение head в Livewire в большинстве случаев не даёт одиночным тегам `<meta>`/`<link>` устареть, но точное поведение зависит от версии Livewire и структуры шаблона. Для страниц, где корректные метаданные для роботов критичны, предпочитайте **полную перезагрузку страницы** (обычную ссылку без `wire:navigate`) или **серверный рендеринг**, чтобы первый HTTP-ответ был определяющим. Приложение Livewire [`rankbeam-examples`](https://github.com/rankbeam) проверяет реальный сценарий `wire:navigate` в браузере.
:::

## Filament {#filament}

В основе Filament лежит Livewire, но это **интерфейс администратора для редактирования**: он изменяет `seo_meta` и никогда не выводит head публичного фронтенда. См. [руководство по Filament](/ru/guide/filament); описанные здесь особенности к панели администратора не относятся.


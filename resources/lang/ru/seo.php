<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Русский
|--------------------------------------------------------------------------
|
| Первая версия: Claude (2026-09-05), автоматическая. Проверка носителем языка
| ещё не выполнена — см. TRANSLATING.md. Ключи — это коды, они не переводятся;
| плейсхолдеры (:length, :max, …) остаются без изменений.
|
*/

return [

    'audit' => [
        'missing_title' => 'На странице отсутствует тег title.',
        'missing_description' => 'На странице отсутствует meta description.',
        'missing_og_image' => 'На странице отсутствует изображение Open Graph.',
        'missing_focus_keyword' => 'Для этой страницы не задано ключевое слово.',
        'title_too_long' => 'Title содержит :length символов (рекомендуемый максимум :max); Google может его обрезать.',
        'title_too_short' => 'Title содержит всего :length символов (рекомендуемый минимум :min).',
        'description_too_long' => 'Description содержит :length символов (рекомендуемый максимум :max); она может быть обрезана.',
        'description_too_short' => 'Description содержит всего :length символов (рекомендуемый минимум :min).',
        'duplicate_title' => 'Title «:title» используется ещё на :count странице(ах).',
        'duplicate_description' => 'Meta description дублируется ещё на :count странице(ах).',
        'robots_conflict_indexing' => 'Мета-тег robots содержит противоречивые директивы index/noindex.',
        'robots_conflict_following' => 'Мета-тег robots содержит противоречивые директивы follow/nofollow.',
        'noindex_warning' => 'Страница закрыта noindex, но выглядит как важный контент.',
        'invalid_canonical' => 'Canonical-URL не является корректным URL.',
        'cross_domain_canonical' => 'Canonical-URL указывает на другой домен.',
        'insecure_canonical' => 'Canonical-URL использует http:// на https-сайте.',
        'shared_canonical' => ':count страниц используют один и тот же canonical-URL.',
        'aeo_missing_author' => 'У статьи на этой странице не указан автор в структурированных данных. Указание автора делает авторство и происхождение статьи явными в схеме.',
        'aeo_article_missing_date' => 'У статьи на этой странице не указана дата публикации в структурированных данных. datePublished или dateModified делает хронологию статьи явной в схеме.',
        'hreflang_invalid_code' => 'В hreflang указан код, который поисковые системы проигнорируют (:codes). Используйте формат язык[-Script][-РЕГИОН], например ru, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'В hreflang один и тот же код указан несколько раз (:codes).',
        'hreflang_missing_self' => 'В hreflang нет ссылки на саму эту страницу. Google требует, чтобы каждая языковая версия указывала и собственный URL.',
    ],

    'warnings' => [
        'title_too_long' => 'Длина title — :length символов (рекомендуемый максимум: :max). Google может его обрезать.',
        'title_is_fallback' => 'SEO-title не задан — будет использован заголовок контента.',
        'description_too_long' => 'Длина description — :length символов (рекомендуемый максимум: :max). Она может быть обрезана.',
        'description_is_fallback' => 'SEO-description не задана — она будет сформирована автоматически из контента.',
        'no_image' => 'Нет изображения для превью в соцсетях. Добавьте SEO-изображение или изображение в контент.',
        'image_is_fallback' => 'Отдельное SEO-изображение не задано — будет использовано изображение из контента.',
        'image_too_small' => 'Изображение слишком маленькое (:widthx:height). Соцсети требуют минимум :min_widthx:min_height px.',
        'image_not_ideal' => 'Размер изображения :widthx:height px. Идеальный размер для соцсетей — :ideal_widthx:ideal_height px.',
    ],

    'status' => [
        'pass' => 'Пройдено',
        'warn' => 'Предупреждение',
        'fail' => 'Ошибка',
        'skipped' => 'Пропущено',
    ],

    'severity' => [
        'critical' => 'Критично',
        'warning' => 'Предупреждение',
        'notice' => 'Замечание',
    ],
];

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Українська
|--------------------------------------------------------------------------
|
| Перша версія: Claude (2026-09-07), автоматичний переклад. Перевірку носієм
| мови ще не виконано — див. TRANSLATING.md. Ключі — це коди, вони не
| перекладаються; плейсхолдери (:length, :max, …) залишаються без змін.
|
*/

return [
    'cli' => [
        'audit' => [
            'no_models' => 'Немає моделей для аудиту.',
            'model_hint' => 'Використайте --model="App\\Models\\Post" або налаштуйте seo.audit.models чи seo.sitemap.models у config/seo.php.',
            'skipped' => 'Модель пропущено, :model: :reason',
            'no_pages' => 'Сторінки для аудиту не знайдено.',
            'page' => 'Сторінка',
            'status' => 'Стан',
            'findings' => 'Результати',
            'all_passed' => 'Проблем не знайдено: усі перевірені сторінки пройшли аудит.',
            'page_summary' => 'Сторінок: :pages · пройдено: :passed · із попередженнями: :warned · не пройдено: :failed',
            'issue_summary' => 'Проблем: :issues · критичних: :critical · попереджень: :warning · сповіщень: :notice',
            'guard_title' => 'ЗАХИСТ ВІД ІНДЕКСАЦІЇ УВІМКНЕНО',
            'guard_environment' => 'Середовища ":environment" немає в seo.indexing_guard.allowed_environments (:allowed).',
            'guard_explanation' => 'Кожна сторінка використовує :directive, а керований robots.txt блокує роботів. Використайте дозволене робоче середовище або задайте SEO_INDEXING_GUARD=false.',
            'coverage' => 'Охоплення',
            'coverage_core' => 'Перевіряється тут (модель і резолвер, без запитів): наявність/довжина заголовка й опису, зображення OG, конфлікти robots, формат/домен/спільне використання/безпека canonical URL та головне ключове слово.',
            'coverage_pro' => 'Потрібне сканування Pro (відображений HTML або зовнішні запити): H1, alt зображень, недостатній вміст, змішаний вміст і перевірка canonical URL у мережі. Оцінка 0–100 також є функцією Pro.',
        ],
    ],
    'audit' => [
        'missing_title' => 'На сторінці немає тега title.',
        'missing_description' => 'На сторінці немає мета-опису.',
        'missing_og_image' => 'На сторінці немає зображення Open Graph.',
        'missing_focus_keyword' => 'Для цієї сторінки не задано фокусне ключове слово.',
        'title_too_long' => 'Заголовок містить :length символів (рекомендований максимум — :max); Google може його обрізати.',
        'title_too_short' => 'Заголовок містить лише :length символів (рекомендований мінімум — :min).',
        'description_too_long' => 'Опис містить :length символів (рекомендований максимум — :max); його може бути обрізано.',
        'description_too_short' => 'Опис містить лише :length символів (рекомендований мінімум — :min).',
        'duplicate_title' => 'Заголовок ":title" використовується ще на :count інших сторінках.',
        'duplicate_description' => 'Мета-опис дублюється ще на :count інших сторінках.',
        'robots_conflict_indexing' => 'Мета-тег robots містить суперечливі директиви index/noindex.',
        'robots_conflict_following' => 'Мета-тег robots містить суперечливі директиви follow/nofollow.',
        'noindex_warning' => 'Сторінка має noindex, але, схоже, є важливим контентом.',
        'invalid_canonical' => 'Канонічний URL має недійсний формат URL.',
        'cross_domain_canonical' => 'Канонічний URL вказує на інший домен.',
        'insecure_canonical' => 'Канонічний URL використовує http:// на сайті з https.',
        'shared_canonical' => ':count сторінок мають той самий канонічний URL.',
        'aeo_missing_author' => 'Стаття на цій сторінці не має автора у структурованих даних. Указання автора робить авторство та походження статті явними у schema.',
        'aeo_article_missing_date' => 'Стаття на цій сторінці не має дати публікації у структурованих даних. Поле datePublished або dateModified робить хронологію статті явною у schema.',
        'hreflang_invalid_code' => 'Альтернативи hreflang містять код, який пошукові системи проігнорують (:codes). Використовуйте формат language[-Script][-REGION], наприклад en, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'Альтернативи hreflang містять той самий код більше ніж один раз (:codes).',
        'hreflang_missing_self' => 'Альтернативи hreflang не містять саму цю сторінку. Google вимагає, щоб кожна мовна версія вказувала і власний URL.',
    ],
    'warnings' => [
        'title_too_long' => 'Заголовок містить :length символів (рекомендований максимум: :max). Google може його обрізати.',
        'title_is_fallback' => 'SEO-заголовок не задано — як запасний варіант буде використано заголовок контенту.',
        'description_too_long' => 'Опис містить :length символів (рекомендований максимум: :max). Його може бути обрізано.',
        'description_is_fallback' => 'SEO-опис не задано — його буде згенеровано автоматично з контенту.',
        'no_image' => 'Немає зображення для прев\'ю в соцмережах. Додайте SEO-зображення або зображення до контенту.',
        'image_is_fallback' => 'Окремого SEO-зображення немає — як запасний варіант буде використано зображення з контенту.',
        'image_too_small' => 'Зображення замале (:widthx:height). Соціальні платформи вимагають щонайменше :min_widthx:min_height px.',
        'image_not_ideal' => 'Розмір зображення — :widthx:height px. Ідеальний розмір для соціальних платформ — :ideal_widthx:ideal_height px.',
    ],
    'status' => [
        'pass' => 'Пройдено',
        'warn' => 'Увага',
        'fail' => 'Помилка',
        'skipped' => 'Пропущено',
    ],
    'severity' => [
        'critical' => 'Критично',
        'warning' => 'Попередження',
        'notice' => 'Зауваження',
    ],
];

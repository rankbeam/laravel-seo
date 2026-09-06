<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Español
|--------------------------------------------------------------------------
|
| Primera versión: Claude (2026-09-05), automática. Pendiente de revisión por
| un hablante nativo — ver TRANSLATING.md. Las claves son códigos y no se
| traducen; los marcadores (:length, :max, …) se mantienen tal cual.
|
*/

return [

    'audit' => [
        'missing_title' => 'A la página le falta la etiqueta title.',
        'missing_description' => 'A la página le falta la meta description.',
        'missing_og_image' => 'A la página le falta una imagen Open Graph.',
        'missing_focus_keyword' => 'No hay palabra clave principal definida para esta página.',
        'title_too_long' => 'El title tiene :length caracteres (máximo recomendado :max); Google podría truncarlo.',
        'title_too_short' => 'El title solo tiene :length caracteres (mínimo recomendado :min).',
        'description_too_long' => 'La description tiene :length caracteres (máximo recomendado :max); podría truncarse.',
        'description_too_short' => 'La description solo tiene :length caracteres (mínimo recomendado :min).',
        'duplicate_title' => 'El title «:title» también se usa en :count página(s) más.',
        'duplicate_description' => 'La meta description está duplicada en :count página(s) más.',
        'robots_conflict_indexing' => 'La meta robots contiene directivas index/noindex contradictorias.',
        'robots_conflict_following' => 'La meta robots contiene directivas follow/nofollow contradictorias.',
        'noindex_warning' => 'La página está en noindex pero parece contenido importante.',
        'invalid_canonical' => 'La URL canonical no es una URL válida.',
        'cross_domain_canonical' => 'La URL canonical apunta a otro dominio.',
        'insecure_canonical' => 'La URL canonical usa http:// en un sitio https.',
        'shared_canonical' => ':count páginas comparten la misma URL canonical.',
        'aeo_missing_author' => 'Un artículo de esta página no declara autor en sus datos estructurados. Declararlo hace explícitas la autoría y la procedencia del artículo en el schema.',
        'aeo_article_missing_date' => 'Un artículo de esta página no declara fecha de publicación en sus datos estructurados. Un datePublished o dateModified hace explícita la cronología del artículo en el schema.',
        'hreflang_invalid_code' => 'Las alternativas hreflang contienen un código que los buscadores ignorarán (:codes). Usa idioma[-Script][-REGIÓN], p. ej. es, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'Las alternativas hreflang repiten el mismo código (:codes).',
        'hreflang_missing_self' => 'Las alternativas hreflang no incluyen esta misma página. Google exige que cada versión de idioma liste también su propia URL.',
    ],

    'warnings' => [
        'title_too_long' => 'El title tiene :length caracteres (máximo recomendado: :max). Google podría truncarlo.',
        'title_is_fallback' => 'No hay title SEO definido: se usará el título del contenido.',
        'description_too_long' => 'La description tiene :length caracteres (máximo recomendado: :max). Podría truncarse.',
        'description_is_fallback' => 'No hay description SEO definida: se generará automáticamente a partir del contenido.',
        'no_image' => 'No hay imagen disponible para las vistas previas sociales. Añade una imagen SEO o una imagen en el contenido.',
        'image_is_fallback' => 'No hay imagen SEO específica: se usará la imagen del contenido.',
        'image_too_small' => 'Imagen demasiado pequeña (:widthx:height). Las plataformas sociales exigen al menos :min_widthx:min_height px.',
        'image_not_ideal' => 'La imagen mide :widthx:height px. El tamaño ideal para las plataformas sociales es :ideal_widthx:ideal_height px.',
    ],

    'status' => [
        'pass' => 'Correcto',
        'warn' => 'Aviso',
        'fail' => 'Error',
        'skipped' => 'Omitido',
    ],

    'severity' => [
        'critical' => 'Crítico',
        'warning' => 'Aviso',
        'notice' => 'Nota',
    ],
];

<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Português (Brasil)
|--------------------------------------------------------------------------
|
| Primeira versão: Claude (2026-09-05), automática. Revisão por falante nativo
| pendente — veja TRANSLATING.md. As chaves são códigos e não se traduzem; os
| marcadores (:length, :max, …) ficam como estão.
|
*/

return [

    'audit' => [
        'missing_title' => 'A página não tem uma tag title.',
        'missing_description' => 'A página não tem uma meta description.',
        'missing_og_image' => 'A página não tem uma imagem Open Graph.',
        'missing_focus_keyword' => 'Nenhuma palavra-chave principal definida para esta página.',
        'title_too_long' => 'O title tem :length caracteres (máximo recomendado :max); o Google pode truncá-lo.',
        'title_too_short' => 'O title tem apenas :length caracteres (mínimo recomendado :min).',
        'description_too_long' => 'A description tem :length caracteres (máximo recomendado :max); ela pode ser truncada.',
        'description_too_short' => 'A description tem apenas :length caracteres (mínimo recomendado :min).',
        'duplicate_title' => 'O title ":title" também é usado em :count outra(s) página(s).',
        'duplicate_description' => 'A meta description está duplicada em :count outra(s) página(s).',
        'robots_conflict_indexing' => 'A meta robots tem diretivas index/noindex conflitantes.',
        'robots_conflict_following' => 'A meta robots tem diretivas follow/nofollow conflitantes.',
        'noindex_warning' => 'A página está com noindex, mas parece ser conteúdo importante.',
        'invalid_canonical' => 'A URL canonical não é uma URL válida.',
        'cross_domain_canonical' => 'A URL canonical aponta para outro domínio.',
        'insecure_canonical' => 'A URL canonical usa http:// em um site https.',
        'shared_canonical' => ':count páginas compartilham a mesma URL canonical.',
        'aeo_missing_author' => 'Um artigo nesta página não declara autor nos dados estruturados. Declarar o autor torna a autoria e a procedência do artigo explícitas no schema.',
        'aeo_article_missing_date' => 'Um artigo nesta página não declara data de publicação nos dados estruturados. Um datePublished ou dateModified torna a linha do tempo do artigo explícita no schema.',
        'hreflang_invalid_code' => 'As alternativas hreflang contêm um código que os buscadores vão ignorar (:codes). Use idioma[-Script][-REGIÃO], por ex. pt-BR, en, zh-Hant.',
        'hreflang_duplicate_code' => 'As alternativas hreflang repetem o mesmo código (:codes).',
        'hreflang_missing_self' => 'As alternativas hreflang não incluem esta própria página. O Google exige que cada versão de idioma liste também a sua própria URL.',
    ],

    'warnings' => [
        'title_too_long' => 'O title tem :length caracteres (máximo recomendado: :max). O Google pode truncá-lo.',
        'title_is_fallback' => 'Nenhum title SEO definido — o título do conteúdo será usado.',
        'description_too_long' => 'A description tem :length caracteres (máximo recomendado: :max). Ela pode ser truncada.',
        'description_is_fallback' => 'Nenhuma description SEO definida — ela será gerada automaticamente a partir do conteúdo.',
        'no_image' => 'Nenhuma imagem disponível para as prévias sociais. Adicione uma imagem SEO ou uma imagem no conteúdo.',
        'image_is_fallback' => 'Nenhuma imagem SEO específica — a imagem do conteúdo será usada.',
        'image_too_small' => 'Imagem pequena demais (:widthx:height). As plataformas sociais exigem pelo menos :min_widthx:min_height px.',
        'image_not_ideal' => 'A imagem tem :widthx:height px. O tamanho ideal para as plataformas sociais é :ideal_widthx:ideal_height px.',
    ],

    'status' => [
        'pass' => 'OK',
        'warn' => 'Atenção',
        'fail' => 'Falha',
        'skipped' => 'Ignorado',
    ],

    'severity' => [
        'critical' => 'Crítico',
        'warning' => 'Aviso',
        'notice' => 'Observação',
    ],
];

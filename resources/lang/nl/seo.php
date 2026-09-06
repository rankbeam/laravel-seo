<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Nederlands
|--------------------------------------------------------------------------
|
| Eerste versie: Claude (2026-09-05), automatisch. Controle door een
| moedertaalspreker staat nog open — zie TRANSLATING.md. Sleutels zijn codes en
| worden niet vertaald; plaatshouders (:length, :max, …) blijven ongewijzigd.
|
*/

return [

    'audit' => [
        'missing_title' => 'De pagina mist een title-tag.',
        'missing_description' => 'De pagina mist een meta description.',
        'missing_og_image' => 'De pagina mist een Open Graph-afbeelding.',
        'missing_focus_keyword' => 'Geen focus-keyword ingesteld voor deze pagina.',
        'title_too_long' => 'De title is :length tekens (aanbevolen maximum :max); Google kapt hem mogelijk af.',
        'title_too_short' => 'De title is maar :length tekens (aanbevolen minimum :min).',
        'description_too_long' => 'De description is :length tekens (aanbevolen maximum :max); ze wordt mogelijk afgekapt.',
        'description_too_short' => 'De description is maar :length tekens (aanbevolen minimum :min).',
        'duplicate_title' => 'De title ":title" wordt ook op :count andere pagina(\'s) gebruikt.',
        'duplicate_description' => 'De meta description is een duplicaat op :count andere pagina(\'s).',
        'robots_conflict_indexing' => 'De robots-meta bevat tegenstrijdige index/noindex-instructies.',
        'robots_conflict_following' => 'De robots-meta bevat tegenstrijdige follow/nofollow-instructies.',
        'noindex_warning' => 'De pagina staat op noindex maar lijkt belangrijke content.',
        'invalid_canonical' => 'De canonical-URL is geen geldige URL.',
        'cross_domain_canonical' => 'De canonical-URL verwijst naar een ander domein.',
        'insecure_canonical' => 'De canonical-URL gebruikt http:// op een https-site.',
        'shared_canonical' => ':count pagina\'s delen dezelfde canonical-URL.',
        'aeo_missing_author' => 'Een artikel op deze pagina noemt geen auteur in de gestructureerde data. Een auteur maakt het auteurschap en de herkomst van het artikel expliciet in het schema.',
        'aeo_article_missing_date' => 'Een artikel op deze pagina noemt geen publicatiedatum in de gestructureerde data. Een datePublished of dateModified maakt de tijdlijn van het artikel expliciet in het schema.',
        'hreflang_invalid_code' => 'De hreflang-alternatieven bevatten een code die zoekmachines negeren (:codes). Gebruik taal[-Script][-REGIO], bijv. nl, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'De hreflang-alternatieven noemen dezelfde code meer dan eens (:codes).',
        'hreflang_missing_self' => 'De hreflang-alternatieven bevatten deze pagina zelf niet. Google vereist dat elke taalversie ook haar eigen URL vermeldt.',
    ],

    'warnings' => [
        'title_too_long' => 'De title is :length tekens lang (aanbevolen maximum: :max). Google kapt hem mogelijk af.',
        'title_is_fallback' => 'Geen SEO-title ingesteld — de titel van de content wordt als terugval gebruikt.',
        'description_too_long' => 'De description is :length tekens lang (aanbevolen maximum: :max). Ze wordt mogelijk afgekapt.',
        'description_is_fallback' => 'Geen SEO-description ingesteld — ze wordt automatisch uit de content gegenereerd.',
        'no_image' => 'Geen afbeelding beschikbaar voor social previews. Voeg een SEO-afbeelding of een afbeelding in de content toe.',
        'image_is_fallback' => 'Geen specifieke SEO-afbeelding — de afbeelding uit de content wordt als terugval gebruikt.',
        'image_too_small' => 'Afbeelding te klein (:widthx:height). Sociale platforms vereisen minimaal :min_widthx:min_height px.',
        'image_not_ideal' => 'De afbeelding is :widthx:height px. De ideale grootte voor sociale platforms is :ideal_widthx:ideal_height px.',
    ],

    'status' => [
        'pass' => 'Geslaagd',
        'warn' => 'Waarschuwing',
        'fail' => 'Mislukt',
        'skipped' => 'Overgeslagen',
    ],

    'severity' => [
        'critical' => 'Kritiek',
        'warning' => 'Waarschuwing',
        'notice' => 'Opmerking',
    ],
];

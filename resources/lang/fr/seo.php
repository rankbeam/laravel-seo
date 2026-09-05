<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Français
|--------------------------------------------------------------------------
|
| Première version : Claude (2026-09-05), automatique. Relecture par un
| locuteur natif à faire — voir TRANSLATING.md. Les clés sont des codes et
| ne se traduisent pas ; les variables (:length, :max, …) restent telles quelles.
|
*/

return [

    'audit' => [
        'missing_title' => 'La page n\'a pas de balise title.',
        'missing_description' => 'La page n\'a pas de meta description.',
        'missing_og_image' => 'La page n\'a pas d\'image Open Graph.',
        'missing_focus_keyword' => 'Aucun mot-clé principal défini pour cette page.',
        'title_too_long' => 'Le title fait :length caractères (maximum recommandé :max) ; Google risque de le tronquer.',
        'title_too_short' => 'Le title ne fait que :length caractères (minimum recommandé :min).',
        'description_too_long' => 'La description fait :length caractères (maximum recommandé :max) ; elle risque d\'être tronquée.',
        'description_too_short' => 'La description ne fait que :length caractères (minimum recommandé :min).',
        'duplicate_title' => 'Le title « :title » est aussi utilisé sur :count autre(s) page(s).',
        'duplicate_description' => 'La meta description est dupliquée sur :count autre(s) page(s).',
        'robots_conflict_indexing' => 'La balise meta robots contient des directives index/noindex contradictoires.',
        'robots_conflict_following' => 'La balise meta robots contient des directives follow/nofollow contradictoires.',
        'noindex_warning' => 'La page est en noindex mais semble être un contenu important.',
        'invalid_canonical' => 'L\'URL canonical n\'est pas une URL valide.',
        'cross_domain_canonical' => 'L\'URL canonical pointe vers un autre domaine.',
        'insecure_canonical' => 'L\'URL canonical utilise http:// sur un site en https.',
        'shared_canonical' => ':count pages partagent la même URL canonical.',
        'aeo_missing_author' => 'Un article de cette page ne déclare aucun auteur dans ses données structurées. Déclarer un auteur rend la paternité et la provenance de l\'article explicites dans le schéma.',
        'aeo_article_missing_date' => 'Un article de cette page ne déclare aucune date de publication dans ses données structurées. Un datePublished ou dateModified rend la chronologie de l\'article explicite dans le schéma.',
    ],

    'warnings' => [
        'title_too_long' => 'Le title fait :length caractères (maximum recommandé : :max). Google risque de le tronquer.',
        'title_is_fallback' => 'Aucun title SEO défini — le titre du contenu sera utilisé par défaut.',
        'description_too_long' => 'La description fait :length caractères (maximum recommandé : :max). Elle risque d\'être tronquée.',
        'description_is_fallback' => 'Aucune description SEO définie — elle sera générée automatiquement à partir du contenu.',
        'no_image' => 'Aucune image disponible pour les aperçus sociaux. Ajoutez une image SEO ou une image dans le contenu.',
        'image_is_fallback' => 'Aucune image SEO spécifique — l\'image du contenu sera utilisée par défaut.',
        'image_too_small' => 'Image trop petite (:widthx:height). Les plateformes sociales exigent au moins :min_widthx:min_height px.',
        'image_not_ideal' => 'L\'image fait :widthx:height px. La taille idéale pour les plateformes sociales est :ideal_widthx:ideal_height px.',
    ],

    'status' => [
        'pass' => 'OK',
        'warn' => 'Avertissement',
        'fail' => 'Échec',
        'skipped' => 'Ignoré',
    ],

    'severity' => [
        'critical' => 'Critique',
        'warning' => 'Avertissement',
        'notice' => 'Remarque',
    ],
];

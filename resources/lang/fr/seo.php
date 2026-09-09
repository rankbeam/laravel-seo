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
    'cli' => [
        'audit' => [
            'no_models' => 'Aucun modèle à auditer.',
            'model_hint' => 'Utilisez --model="App\\Models\\Post" ou configurez seo.audit.models ou seo.sitemap.models dans config/seo.php.',
            'skipped' => 'Modèle ignoré, :model : :reason',
            'no_pages' => 'Aucune page à auditer.',
            'page' => 'Page',
            'status' => 'État',
            'findings' => 'Résultats',
            'all_passed' => 'Aucun problème trouvé : toutes les pages auditées ont réussi les vérifications.',
            'page_summary' => 'Pages : :pages · réussites : :passed · avertissements : :warned · échecs : :failed',
            'issue_summary' => 'Problèmes : :issues · critiques : :critical · avertissements : :warning · remarques : :notice',
            'guard_title' => 'PROTECTION DE L’INDEXATION ACTIVE',
            'guard_environment' => 'L’environnement ":environment" ne figure pas dans seo.indexing_guard.allowed_environments (:allowed).',
            'guard_explanation' => 'Chaque page utilise :directive et le fichier robots.txt géré bloque les robots. Utilisez un environnement de production autorisé ou définissez SEO_INDEXING_GUARD=false.',
            'coverage' => 'Couverture',
            'coverage_core' => 'Vérifié ici (modèle et résolveur, sans requête) : présence/longueur du titre et de la description, image OG, conflits robots, format/domaine/partage/sécurité de l’URL canonique et mot-clé principal.',
            'coverage_pro' => 'Nécessite le scan Pro (HTML rendu ou requêtes externes) : H1, textes alt, contenu insuffisant, contenu mixte et vérifications des URL canoniques en ligne. Le score de 0 à 100 est aussi une fonction Pro.',
        ],
    ],

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
        'hreflang_invalid_code' => 'Les alternates hreflang contiennent un code que les moteurs de recherche ignoreront (:codes). Utilisez langue[-Script][-RÉGION], par ex. fr, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'Les alternates hreflang répètent le même code (:codes).',
        'hreflang_missing_self' => 'Les alternates hreflang n\'incluent pas cette page elle-même. Google exige que chaque version linguistique liste aussi sa propre URL.',
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

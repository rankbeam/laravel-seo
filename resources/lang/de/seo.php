<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Deutsch
|--------------------------------------------------------------------------
|
| Erste Fassung: Claude (2026-09-05), maschinell. Muttersprachliche Prüfung
| steht noch aus — siehe TRANSLATING.md. Schlüssel sind Codes und werden
| nicht übersetzt; Platzhalter (:length, :max, …) bleiben unverändert.
|
*/

return [
    'cli' => [
        'audit' => [
            'no_models' => 'Keine Modelle zu prüfen.',
            'model_hint' => 'Verwende --model="App\\Models\\Post" oder konfiguriere seo.audit.models bzw. seo.sitemap.models in config/seo.php.',
            'skipped' => 'Übersprungen, :model: :reason',
            'no_pages' => 'Keine Seiten zur Prüfung gefunden.',
            'page' => 'Seite',
            'status' => 'Status',
            'findings' => 'Befunde',
            'all_passed' => 'Keine Probleme gefunden — alle geprüften Seiten haben bestanden.',
            'page_summary' => 'Seiten: :pages · bestanden: :passed · mit Warnungen: :warned · fehlgeschlagen: :failed',
            'issue_summary' => 'Probleme: :issues · kritisch: :critical · Warnungen: :warning · Hinweise: :notice',
            'guard_title' => 'INDEXIERUNGSSCHUTZ AKTIV',
            'guard_environment' => 'Die Umgebung ":environment" ist nicht in seo.indexing_guard.allowed_environments (:allowed) enthalten.',
            'guard_explanation' => 'Jede Seite verwendet :directive, und die verwaltete robots.txt blockiert Crawler. Verwende eine erlaubte Produktionsumgebung oder setze SEO_INDEXING_GUARD=false.',
            'coverage' => 'Prüfumfang',
            'coverage_core' => 'Hier geprüft (Modell und Resolver, ohne Abruf): Vorhandensein/Länge von Titel und Beschreibung, OG-Bild, robots-Konflikte, Format/Domain/Mehrfachnutzung/Sicherheit der Canonical-URL und Fokus-Keyword.',
            'coverage_pro' => 'Erfordert den Pro-Scan (gerendertes HTML oder externe Abrufe): H1, Bild-Alt-Texte, dünne Inhalte, gemischte Inhalte und Live-Canonical-Prüfungen. Auch der Wert von 0–100 ist eine Pro-Funktion.',
        ],
    ],

    'audit' => [
        'missing_title' => 'Der Seite fehlt ein Title-Tag.',
        'missing_description' => 'Der Seite fehlt eine Meta-Description.',
        'missing_og_image' => 'Der Seite fehlt ein Open-Graph-Bild.',
        'missing_focus_keyword' => 'Für diese Seite ist kein Fokus-Keyword gesetzt.',
        'title_too_long' => 'Der Title hat :length Zeichen (empfohlenes Maximum :max); Google kürzt ihn möglicherweise.',
        'title_too_short' => 'Der Title hat nur :length Zeichen (empfohlenes Minimum :min).',
        'description_too_long' => 'Die Description hat :length Zeichen (empfohlenes Maximum :max); sie wird möglicherweise gekürzt.',
        'description_too_short' => 'Die Description hat nur :length Zeichen (empfohlenes Minimum :min).',
        'duplicate_title' => 'Der Title ":title" wird auch auf :count weiteren Seite(n) verwendet.',
        'duplicate_description' => 'Die Meta-Description ist auf :count weiteren Seite(n) identisch.',
        'robots_conflict_indexing' => 'Das Robots-Meta enthält widersprüchliche index/noindex-Anweisungen.',
        'robots_conflict_following' => 'Das Robots-Meta enthält widersprüchliche follow/nofollow-Anweisungen.',
        'noindex_warning' => 'Die Seite ist noindex, scheint aber wichtiger Inhalt zu sein.',
        'invalid_canonical' => 'Die Canonical-URL ist keine gültige URL.',
        'cross_domain_canonical' => 'Die Canonical-URL zeigt auf eine andere Domain.',
        'insecure_canonical' => 'Die Canonical-URL verwendet http:// auf einer https-Site.',
        'shared_canonical' => ':count Seiten teilen dieselbe Canonical-URL.',
        'aeo_missing_author' => 'Ein Artikel auf dieser Seite nennt in den strukturierten Daten keinen Autor. Ein Autor macht Urheberschaft und Herkunft des Artikels im Schema explizit.',
        'aeo_article_missing_date' => 'Ein Artikel auf dieser Seite nennt in den strukturierten Daten kein Veröffentlichungsdatum. Ein datePublished oder dateModified macht den zeitlichen Verlauf des Artikels im Schema explizit.',
        'hreflang_invalid_code' => 'Die hreflang-Alternativen enthalten einen Code, den Suchmaschinen ignorieren (:codes). Verwende Sprache[-Schrift][-REGION], z. B. de, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'Die hreflang-Alternativen führen denselben Code mehrfach auf (:codes).',
        'hreflang_missing_self' => 'Die hreflang-Alternativen enthalten diese Seite selbst nicht. Google verlangt, dass jede Sprachversion auch ihre eigene URL auflistet.',
    ],

    'warnings' => [
        'title_too_long' => 'Der Title ist :length Zeichen lang (empfohlenes Maximum: :max). Google kürzt ihn möglicherweise.',
        'title_is_fallback' => 'Kein SEO-Title gesetzt — der Titel des Inhalts wird als Rückfall verwendet.',
        'description_too_long' => 'Die Description ist :length Zeichen lang (empfohlenes Maximum: :max). Sie wird möglicherweise gekürzt.',
        'description_is_fallback' => 'Keine SEO-Description gesetzt — sie wird automatisch aus dem Inhalt erzeugt.',
        'no_image' => 'Kein Bild für Social-Vorschauen verfügbar. Füge ein SEO-Bild oder ein Bild im Inhalt hinzu.',
        'image_is_fallback' => 'Kein eigenes SEO-Bild — das Bild aus dem Inhalt wird als Rückfall verwendet.',
        'image_too_small' => 'Bild zu klein (:widthx:height). Soziale Plattformen verlangen mindestens :min_widthx:min_height px.',
        'image_not_ideal' => 'Das Bild hat :widthx:height px. Die ideale Größe für soziale Plattformen ist :ideal_widthx:ideal_height px.',
    ],

    'status' => [
        'pass' => 'Bestanden',
        'warn' => 'Warnung',
        'fail' => 'Fehler',
        'skipped' => 'Übersprungen',
    ],

    'severity' => [
        'critical' => 'Kritisch',
        'warning' => 'Warnung',
        'notice' => 'Hinweis',
    ],
];

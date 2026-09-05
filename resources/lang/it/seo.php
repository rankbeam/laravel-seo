<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Italiano
|--------------------------------------------------------------------------
|
| Prima stesura: Claude (2026-09-05). Revisione madrelingua: Valentin Goxhaj.
| Le chiavi sono codici e non si traducono; i segnaposto (:length, :max, …)
| restano invariati. Termini tecnici (canonical, robots, noindex, Open Graph,
| meta description) restano come li usa un developer italiano.
|
*/

return [

    'audit' => [
        'missing_title' => 'La pagina non ha un tag title.',
        'missing_description' => 'La pagina non ha una meta description.',
        'missing_og_image' => 'La pagina non ha un\'immagine Open Graph.',
        'missing_focus_keyword' => 'Nessuna parola chiave principale impostata per questa pagina.',
        'title_too_long' => 'Il title è di :length caratteri (massimo consigliato :max); su Google potrebbe essere troncato.',
        'title_too_short' => 'Il title è di soli :length caratteri (minimo consigliato :min).',
        'description_too_long' => 'La description è di :length caratteri (massimo consigliato :max); potrebbe essere troncata.',
        'description_too_short' => 'La description è di soli :length caratteri (minimo consigliato :min).',
        'duplicate_title' => 'Il title ":title" è usato anche su :count altra/e pagina/e.',
        'duplicate_description' => 'La meta description è duplicata su :count altra/e pagina/e.',
        'robots_conflict_indexing' => 'Il meta robots contiene direttive index/noindex in conflitto.',
        'robots_conflict_following' => 'Il meta robots contiene direttive follow/nofollow in conflitto.',
        'noindex_warning' => 'La pagina è noindex ma sembra un contenuto importante.',
        'invalid_canonical' => 'L\'URL canonical non è un URL valido.',
        'cross_domain_canonical' => 'L\'URL canonical punta a un altro dominio.',
        'insecure_canonical' => 'L\'URL canonical usa http:// su un sito https.',
        'shared_canonical' => ':count pagine condividono lo stesso URL canonical.',
        'aeo_missing_author' => 'Un articolo in questa pagina non dichiara l\'autore nei dati strutturati. Dichiararlo rende esplicite la paternità e la provenienza dell\'articolo nello schema.',
        'aeo_article_missing_date' => 'Un articolo in questa pagina non dichiara la data di pubblicazione nei dati strutturati. Un datePublished o dateModified rende esplicita la cronologia dell\'articolo nello schema.',
    ],

    'warnings' => [
        'title_too_long' => 'Il title è lungo :length caratteri (massimo consigliato: :max). Su Google potrebbe essere troncato.',
        'title_is_fallback' => 'Nessun title SEO impostato: verrà usato il titolo del contenuto.',
        'description_too_long' => 'La description è lunga :length caratteri (massimo consigliato: :max). Potrebbe essere troncata.',
        'description_is_fallback' => 'Nessuna description SEO impostata: verrà generata automaticamente dal contenuto.',
        'no_image' => 'Nessuna immagine disponibile per le anteprime social. Aggiungi un\'immagine SEO o un\'immagine nel contenuto.',
        'image_is_fallback' => 'Nessuna immagine SEO specifica: verrà usata l\'immagine del contenuto.',
        'image_too_small' => 'Immagine troppo piccola (:widthx:height). Le piattaforme social richiedono almeno :min_widthx:min_height px.',
        'image_not_ideal' => 'L\'immagine è di :widthx:height px. La dimensione ideale per i social è :ideal_widthx:ideal_height px.',
    ],

    'status' => [
        'pass' => 'OK',
        'warn' => 'Attenzione',
        'fail' => 'Errore',
        'skipped' => 'Saltato',
    ],

    'severity' => [
        'critical' => 'Critico',
        'warning' => 'Avviso',
        'notice' => 'Nota',
    ],
];

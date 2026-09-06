<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Polski
|--------------------------------------------------------------------------
|
| Pierwsza wersja: Claude (2026-09-05), automatyczna. Weryfikacja przez
| native speakera w toku — zob. TRANSLATING.md. Klucze to kody i nie są
| tłumaczone; symbole zastępcze (:length, :max, …) pozostają bez zmian.
|
*/

return [

    'audit' => [
        'missing_title' => 'Strona nie ma tagu title.',
        'missing_description' => 'Strona nie ma meta description.',
        'missing_og_image' => 'Strona nie ma obrazu Open Graph.',
        'missing_focus_keyword' => 'Dla tej strony nie ustawiono słowa kluczowego.',
        'title_too_long' => 'Title ma :length znaków (zalecane maksimum :max); Google może go skrócić.',
        'title_too_short' => 'Title ma tylko :length znaków (zalecane minimum :min).',
        'description_too_long' => 'Description ma :length znaków (zalecane maksimum :max); może zostać skrócona.',
        'description_too_short' => 'Description ma tylko :length znaków (zalecane minimum :min).',
        'duplicate_title' => 'Title „:title” jest użyty także na :count innej/innych stronie/stronach.',
        'duplicate_description' => 'Meta description powtarza się na :count innej/innych stronie/stronach.',
        'robots_conflict_indexing' => 'Meta robots zawiera sprzeczne dyrektywy index/noindex.',
        'robots_conflict_following' => 'Meta robots zawiera sprzeczne dyrektywy follow/nofollow.',
        'noindex_warning' => 'Strona ma noindex, ale wygląda na ważną treść.',
        'invalid_canonical' => 'Adres canonical nie jest poprawnym URL-em.',
        'cross_domain_canonical' => 'Adres canonical wskazuje na inną domenę.',
        'insecure_canonical' => 'Adres canonical używa http:// w witrynie https.',
        'shared_canonical' => ':count stron ma ten sam adres canonical.',
        'aeo_missing_author' => 'Artykuł na tej stronie nie podaje autora w danych strukturalnych. Wskazanie autora czyni autorstwo i pochodzenie artykułu jawnymi w schemacie.',
        'aeo_article_missing_date' => 'Artykuł na tej stronie nie podaje daty publikacji w danych strukturalnych. datePublished lub dateModified czyni oś czasu artykułu jawną w schemacie.',
        'hreflang_invalid_code' => 'Alternatywy hreflang zawierają kod, który wyszukiwarki zignorują (:codes). Użyj formatu język[-Pismo][-REGION], np. pl, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'Alternatywy hreflang wymieniają ten sam kod więcej niż raz (:codes).',
        'hreflang_missing_self' => 'Alternatywy hreflang nie zawierają tej strony. Google wymaga, aby każda wersja językowa wymieniała także własny adres URL.',
    ],

    'warnings' => [
        'title_too_long' => 'Title ma :length znaków (zalecane maksimum: :max). Google może go skrócić.',
        'title_is_fallback' => 'Nie ustawiono title SEO — zostanie użyty tytuł treści.',
        'description_too_long' => 'Description ma :length znaków (zalecane maksimum: :max). Może zostać skrócona.',
        'description_is_fallback' => 'Nie ustawiono description SEO — zostanie wygenerowana automatycznie z treści.',
        'no_image' => 'Brak obrazu do podglądów w mediach społecznościowych. Dodaj obraz SEO lub obraz w treści.',
        'image_is_fallback' => 'Brak osobnego obrazu SEO — zostanie użyty obraz z treści.',
        'image_too_small' => 'Obraz jest za mały (:widthx:height). Platformy społecznościowe wymagają co najmniej :min_widthx:min_height px.',
        'image_not_ideal' => 'Obraz ma :widthx:height px. Idealny rozmiar dla platform społecznościowych to :ideal_widthx:ideal_height px.',
    ],

    'status' => [
        'pass' => 'OK',
        'warn' => 'Ostrzeżenie',
        'fail' => 'Błąd',
        'skipped' => 'Pominięto',
    ],

    'severity' => [
        'critical' => 'Krytyczne',
        'warning' => 'Ostrzeżenie',
        'notice' => 'Uwaga',
    ],
];

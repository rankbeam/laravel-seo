<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Čeština
|--------------------------------------------------------------------------
|
| První verze: Claude (2026-09-07), strojový překlad. Kontrola rodilým
| mluvčím zatím neproběhla — viz TRANSLATING.md. Klíče jsou kódy a nepřekládají
| se; zástupné symboly (:length, :max, …) zůstávají beze změny.
|
*/

return [
    'cli' => [
        'audit' => [
            'no_models' => 'Žádné modely k auditu.',
            'model_hint' => 'Použijte --model="App\\Models\\Post" nebo nastavte seo.audit.models či seo.sitemap.models v config/seo.php.',
            'skipped' => 'Přeskočený model :model: :reason',
            'no_pages' => 'Nebyly nalezeny stránky k auditu.',
            'page' => 'Stránka',
            'status' => 'Stav',
            'findings' => 'Nálezy',
            'all_passed' => 'Nebyly nalezeny problémy: všechny auditované stránky prošly kontrolou.',
            'page_summary' => 'Stránky: :pages · úspěšné: :passed · s varováním: :warned · neúspěšné: :failed',
            'issue_summary' => 'Problémy: :issues · kritické: :critical · varování: :warning · oznámení: :notice',
            'guard_title' => 'OCHRANA INDEXOVÁNÍ JE AKTIVNÍ',
            'guard_environment' => 'Prostředí ":environment" není v seo.indexing_guard.allowed_environments (:allowed).',
            'guard_explanation' => 'Každá stránka používá :directive a spravovaný robots.txt blokuje roboty. Použijte povolené produkční prostředí nebo nastavte SEO_INDEXING_GUARD=false.',
            'coverage' => 'Rozsah',
            'coverage_core' => 'Kontroluje se zde (model a resolver, bez požadavků): přítomnost/délka titulku a popisu, OG obrázek, konflikty robots, formát/doména/sdílení/zabezpečení canonical URL a hlavní klíčové slovo.',
            'coverage_pro' => 'Vyžaduje sken Pro (vykreslené HTML nebo externí požadavky): H1, alt obrázků, nedostatečný obsah, smíšený obsah a online kontroly canonical URL. Skóre 0–100 je také funkcí Pro.',
        ],
    ],
    'audit' => [
        'missing_title' => 'Stránce chybí tag title.',
        'missing_description' => 'Stránce chybí meta popis.',
        'missing_og_image' => 'Stránce chybí obrázek Open Graph.',
        'missing_focus_keyword' => 'Pro tuto stránku není nastaveno cílové klíčové slovo.',
        'title_too_long' => 'Titulek má :length znaků (doporučené maximum :max); Google jej může zkrátit.',
        'title_too_short' => 'Titulek má jen :length znaků (doporučené minimum :min).',
        'description_too_long' => 'Popis má :length znaků (doporučené maximum :max); může být zkrácen.',
        'description_too_short' => 'Popis má jen :length znaků (doporučené minimum :min).',
        'duplicate_title' => 'Titulek ":title" se používá i na dalších stránkách (:count).',
        'duplicate_description' => 'Meta popis se opakuje i na dalších stránkách (:count).',
        'robots_conflict_indexing' => 'Meta robots obsahuje protichůdné direktivy index/noindex.',
        'robots_conflict_following' => 'Meta robots obsahuje protichůdné direktivy follow/nofollow.',
        'noindex_warning' => 'Stránka má noindex, ale vypadá jako důležitý obsah.',
        'invalid_canonical' => 'Canonical URL nemá platný formát URL.',
        'cross_domain_canonical' => 'Canonical URL odkazuje na jinou doménu.',
        'insecure_canonical' => 'Canonical URL používá http:// na webu s https.',
        'shared_canonical' => 'Stejnou canonical URL sdílí více stránek (:count).',
        'aeo_missing_author' => 'Článek na této stránce nemá ve strukturovaných datech uvedeného autora. Když autora uvedete, bude autorství a původ článku ve schema explicitní.',
        'aeo_article_missing_date' => 'Článek na této stránce nemá ve strukturovaných datech datum publikace. Hodnota datePublished nebo dateModified dělá časovou osu článku ve schema explicitní.',
        'hreflang_invalid_code' => 'Alternativy hreflang obsahují kód, který vyhledávače ignorují (:codes). Použijte formát language[-Script][-REGION], např. en, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'Alternativy hreflang uvádějí stejný kód více než jednou (:codes).',
        'hreflang_missing_self' => 'Alternativy hreflang neobsahují tuto stránku samotnou. Google vyžaduje, aby každá jazyková verze uváděla i vlastní URL.',
    ],
    'warnings' => [
        'title_too_long' => 'Titulek má :length znaků (doporučené maximum: :max). Google jej může zkrátit.',
        'title_is_fallback' => 'SEO titulek není nastaven — jako náhrada se použije titulek obsahu.',
        'description_too_long' => 'Popis má :length znaků (doporučené maximum: :max). Může být zkrácen.',
        'description_is_fallback' => 'SEO popis není nastaven — vygeneruje se automaticky z obsahu.',
        'no_image' => 'Pro náhledy na sociálních sítích není k dispozici žádný obrázek. Přidejte SEO obrázek nebo obrázek obsahu.',
        'image_is_fallback' => 'Není nastaven konkrétní SEO obrázek — jako náhrada se použije obrázek obsahu.',
        'image_too_small' => 'Obrázek je příliš malý (:widthx:height). Sociální sítě vyžadují alespoň :min_widthx:min_height px.',
        'image_not_ideal' => 'Obrázek má :widthx:height px. Ideální velikost pro sociální sítě je :ideal_widthx:ideal_height px.',
    ],
    'status' => [
        'pass' => 'Splněno',
        'warn' => 'Varování',
        'fail' => 'Nesplněno',
        'skipped' => 'Přeskočeno',
    ],
    'severity' => [
        'critical' => 'Kritické',
        'warning' => 'Varování',
        'notice' => 'Upozornění',
    ],
];

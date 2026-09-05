<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Türkçe
|--------------------------------------------------------------------------
|
| İlk sürüm: Claude (2026-09-05), otomatik. Anadil konuşuru incelemesi
| bekleniyor — bkz. TRANSLATING.md. Anahtarlar koddur ve çevrilmez; yer
| tutucular (:length, :max, …) olduğu gibi kalır.
|
*/

return [

    'audit' => [
        'missing_title' => 'Sayfada title etiketi yok.',
        'missing_description' => 'Sayfada meta description yok.',
        'missing_og_image' => 'Sayfada Open Graph görseli yok.',
        'missing_focus_keyword' => 'Bu sayfa için odak anahtar kelime ayarlanmamış.',
        'title_too_long' => 'Title :length karakter (önerilen en fazla :max); Google tarafından kısaltılabilir.',
        'title_too_short' => 'Title yalnızca :length karakter (önerilen en az :min).',
        'description_too_long' => 'Description :length karakter (önerilen en fazla :max); kısaltılabilir.',
        'description_too_short' => 'Description yalnızca :length karakter (önerilen en az :min).',
        'duplicate_title' => '":title" title\'ı :count başka sayfada daha kullanılıyor.',
        'duplicate_description' => 'Meta description :count başka sayfada daha aynı.',
        'robots_conflict_indexing' => 'Robots meta etiketi çelişen index/noindex yönergeleri içeriyor.',
        'robots_conflict_following' => 'Robots meta etiketi çelişen follow/nofollow yönergeleri içeriyor.',
        'noindex_warning' => 'Sayfa noindex ama önemli bir içerik gibi görünüyor.',
        'invalid_canonical' => 'Canonical URL geçerli bir URL değil.',
        'cross_domain_canonical' => 'Canonical URL başka bir alan adına işaret ediyor.',
        'insecure_canonical' => 'Canonical URL, https bir sitede http:// kullanıyor.',
        'shared_canonical' => ':count sayfa aynı canonical URL\'yi paylaşıyor.',
        'aeo_missing_author' => 'Bu sayfadaki bir makalenin yapılandırılmış verisinde yazar belirtilmemiş. Yazar belirtmek, makalenin yazarlığını ve kaynağını şemada açık hâle getirir.',
        'aeo_article_missing_date' => 'Bu sayfadaki bir makalenin yapılandırılmış verisinde yayın tarihi belirtilmemiş. Bir datePublished veya dateModified, makalenin zaman çizelgesini şemada açık hâle getirir.',
    ],

    'warnings' => [
        'title_too_long' => 'Title :length karakter uzunluğunda (önerilen en fazla: :max). Google tarafından kısaltılabilir.',
        'title_is_fallback' => 'SEO title ayarlanmamış — içeriğin başlığı kullanılacak.',
        'description_too_long' => 'Description :length karakter uzunluğunda (önerilen en fazla: :max). Kısaltılabilir.',
        'description_is_fallback' => 'SEO description ayarlanmamış — içerikten otomatik oluşturulacak.',
        'no_image' => 'Sosyal önizlemeler için görsel yok. Bir SEO görseli veya içeriğe bir görsel ekleyin.',
        'image_is_fallback' => 'Özel bir SEO görseli yok — içerikteki görsel kullanılacak.',
        'image_too_small' => 'Görsel çok küçük (:widthx:height). Sosyal platformlar en az :min_widthx:min_height px ister.',
        'image_not_ideal' => 'Görsel :widthx:height px. Sosyal platformlar için ideal boyut :ideal_widthx:ideal_height px.',
    ],

    'status' => [
        'pass' => 'Geçti',
        'warn' => 'Uyarı',
        'fail' => 'Hata',
        'skipped' => 'Atlandı',
    ],

    'severity' => [
        'critical' => 'Kritik',
        'warning' => 'Uyarı',
        'notice' => 'Not',
    ],
];

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
    'cli' => [
        'audit' => [
            'no_models' => 'Denetlenecek model yok.',
            'model_hint' => '--model="App\\Models\\Post" kullanın veya config/seo.php içinde seo.audit.models ya da seo.sitemap.models ayarlayın.',
            'skipped' => 'Atlanan model, :model: :reason',
            'no_pages' => 'Denetlenecek sayfa bulunamadı.',
            'page' => 'Sayfa',
            'status' => 'Durum',
            'findings' => 'Bulgular',
            'all_passed' => 'Sorun bulunamadı; denetlenen tüm sayfalar kontrolleri geçti.',
            'page_summary' => 'Sayfa: :pages · geçen: :passed · uyarılı: :warned · başarısız: :failed',
            'issue_summary' => 'Sorun: :issues · kritik: :critical · uyarı: :warning · bildirim: :notice',
            'guard_title' => 'DİZİNE EKLEME KORUMASI ETKİN',
            'guard_environment' => '":environment" ortamı seo.indexing_guard.allowed_environments (:allowed) içinde değil.',
            'guard_explanation' => 'Her sayfa :directive kullanır ve yönetilen robots.txt tarayıcıları engeller. İzin verilen bir üretim ortamı kullanın veya SEO_INDEXING_GUARD=false ayarlayın.',
            'coverage' => 'Kapsam',
            'coverage_core' => 'Burada denetlenir (model ve çözümleyici, istek gönderilmez): başlık/açıklama varlığı ve uzunluğu, OG görseli, robots çakışmaları, canonical URL biçimi/alan adı/paylaşımı/güvenliği ve odak anahtar kelime.',
            'coverage_pro' => 'Pro taraması gerekir (işlenmiş HTML veya dış istekler): H1, görsel alt metni, yetersiz içerik, karma içerik ve canlı canonical kontrolleri. 0–100 puanı da bir Pro özelliğidir.',
        ],
    ],

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
        'hreflang_invalid_code' => 'hreflang alternatifleri arama motorlarının yok sayacağı bir kod içeriyor (:codes). dil[-Yazı][-BÖLGE] biçimini kullanın, örn. tr, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'hreflang alternatifleri aynı kodu birden fazla kez listeliyor (:codes).',
        'hreflang_missing_self' => 'hreflang alternatifleri bu sayfanın kendisini içermiyor. Google her dil sürümünün kendi URL\'sini de listelemesini ister.',
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

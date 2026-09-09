<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — 한국어
|--------------------------------------------------------------------------
|
| 초판: Claude (2026-09-07), 기계 번역. 원어민 검수는 아직 —
| TRANSLATING.md 참고. 키는 코드이므로 번역하지 않습니다.
| 플레이스홀더 (:length, :max, …) 는 그대로 둡니다.
|
*/

return [
    'cli' => [
        'audit' => [
            'no_models' => '감사할 모델이 없습니다.',
            'model_hint' => '--model="App\\Models\\Post"를 사용하거나 config/seo.php에서 seo.audit.models 또는 seo.sitemap.models를 설정하세요.',
            'skipped' => '건너뛴 모델 :model: :reason',
            'no_pages' => '감사할 페이지를 찾지 못했습니다.',
            'page' => '페이지',
            'status' => '상태',
            'findings' => '발견 사항',
            'all_passed' => '문제를 찾지 못했습니다. 감사한 모든 페이지가 검사를 통과했습니다.',
            'page_summary' => '페이지: :pages · 통과: :passed · 경고 있음: :warned · 실패: :failed',
            'issue_summary' => '문제: :issues · 심각: :critical · 경고: :warning · 알림: :notice',
            'guard_title' => '색인 생성 보호 활성화',
            'guard_environment' => '":environment" 환경이 seo.indexing_guard.allowed_environments (:allowed)에 없습니다.',
            'guard_explanation' => '모든 페이지에 :directive가 적용되고 관리되는 robots.txt가 크롤러를 차단합니다. 허용된 운영 환경을 사용하거나 SEO_INDEXING_GUARD=false를 설정하세요.',
            'coverage' => '검사 범위',
            'coverage_core' => '여기서 검사하는 항목(모델과 리졸버, 외부 요청 없음): 제목과 설명의 유무 및 길이, OG 이미지, robots 충돌, canonical URL의 형식·도메인·공유·보안, 포커스 키워드.',
            'coverage_pro' => 'Pro 스캔이 필요한 항목(렌더링된 HTML 또는 외부 요청): H1, 이미지 alt, 부족한 콘텐츠, 혼합 콘텐츠, 실제 canonical URL 검사. 0–100 점수도 Pro 기능입니다.',
        ],
    ],
    'audit' => [
        'missing_title' => '페이지에 title 태그가 없습니다.',
        'missing_description' => '페이지에 메타 설명이 없습니다.',
        'missing_og_image' => '페이지에 Open Graph 이미지가 없습니다.',
        'missing_focus_keyword' => '이 페이지에 포커스 키워드가 설정되어 있지 않습니다.',
        'title_too_long' => '제목이 :length자입니다(권장 최대 :max자). Google에서 잘릴 수 있습니다.',
        'title_too_short' => '제목이 :length자밖에 되지 않습니다(권장 최소 :min자).',
        'description_too_long' => '설명이 :length자입니다(권장 최대 :max자). 잘릴 수 있습니다.',
        'description_too_short' => '설명이 :length자밖에 되지 않습니다(권장 최소 :min자).',
        'duplicate_title' => '제목 ":title"이(가) 다른 :count개 페이지에서도 사용되고 있습니다.',
        'duplicate_description' => '메타 설명이 다른 :count개 페이지와 중복됩니다.',
        'robots_conflict_indexing' => 'robots 메타에 index/noindex 지시문이 서로 충돌합니다.',
        'robots_conflict_following' => 'robots 메타에 follow/nofollow 지시문이 서로 충돌합니다.',
        'noindex_warning' => '페이지에 noindex가 설정되어 있지만 중요한 콘텐츠로 보입니다.',
        'invalid_canonical' => 'Canonical URL이 올바른 URL 형식이 아닙니다.',
        'cross_domain_canonical' => 'Canonical URL이 다른 도메인을 가리키고 있습니다.',
        'insecure_canonical' => 'https 사이트에서 canonical URL이 http://를 사용하고 있습니다.',
        'shared_canonical' => ':count개 페이지가 같은 canonical URL을 공유하고 있습니다.',
        'aeo_missing_author' => '이 페이지의 아티클 구조화 데이터에 작성자(author)가 없습니다. 작성자를 선언하면 아티클의 저자와 출처가 schema에 명시적으로 드러납니다.',
        'aeo_article_missing_date' => '이 페이지의 아티클 구조화 데이터에 게시일이 없습니다. datePublished 또는 dateModified를 지정하면 아티클의 시간 정보가 schema에 명시적으로 드러납니다.',
        'hreflang_invalid_code' => 'hreflang 대체 링크에 검색 엔진이 무시하는 코드가 포함되어 있습니다(:codes). language[-Script][-REGION] 형식을 사용하세요(예: en, pt-BR, zh-Hant).',
        'hreflang_duplicate_code' => 'hreflang 대체 링크에 같은 코드가 두 번 이상 나열되어 있습니다(:codes).',
        'hreflang_missing_self' => 'hreflang 대체 링크에 이 페이지 자신이 포함되어 있지 않습니다. Google은 각 언어 버전이 자신의 URL도 나열하도록 요구합니다.',
    ],
    'warnings' => [
        'title_too_long' => '제목 길이가 :length자입니다(권장 최대: :max자). Google에서 잘릴 수 있습니다.',
        'title_is_fallback' => 'SEO 제목이 설정되지 않아 콘텐츠 제목이 대체값으로 사용됩니다.',
        'description_too_long' => '설명 길이가 :length자입니다(권장 최대: :max자). 잘릴 수 있습니다.',
        'description_is_fallback' => 'SEO 설명이 설정되지 않아 콘텐츠에서 자동으로 생성됩니다.',
        'no_image' => '소셜 미리보기에 사용할 이미지가 없습니다. SEO 이미지나 콘텐츠 이미지를 추가하세요.',
        'image_is_fallback' => '별도의 SEO 이미지가 없어 콘텐츠 이미지가 대체값으로 사용됩니다.',
        'image_too_small' => '이미지가 너무 작습니다(:widthx:height). 소셜 플랫폼은 최소 :min_widthx:min_height px를 요구합니다.',
        'image_not_ideal' => '이미지 크기가 :widthx:height px입니다. 소셜 플랫폼에 이상적인 크기는 :ideal_widthx:ideal_height px입니다.',
    ],
    'status' => [
        'pass' => '통과',
        'warn' => '경고',
        'fail' => '실패',
        'skipped' => '건너뜀',
    ],
    'severity' => [
        'critical' => '심각',
        'warning' => '경고',
        'notice' => '참고',
    ],
];

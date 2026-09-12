---
description: config/seo.php의 모든 옵션을 리졸버 계층별로 정리하고 각 값의 기본 설정을 설명합니다.
---

# 설정 {#configuration}

설정 파일을 게시하세요.

```bash
php artisan vendor:publish --tag=seo-config
```

아래 항목은 모두 `config/seo.php`에 있습니다. 표시한 값은 기본값입니다.

## 사이트 전체 기본값 (계층 1) {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

최종 제목이 이미 `title_suffix`로 끝나지 않는 한 이 값을 덧붙입니다.

`title_suffix_skip_when_contains`는 브랜드를 인식하는 접미사 생략 목록입니다. 최종 제목에 목록의 토큰이 **완전한 단어로** 포함되어 있으면 접미사를 생략해 브랜드 중복을 피합니다. 대소문자를 구분하지 않고 단어 경계를 인식하므로 `Acmestic` 문자열은 `Acme`와 일치하지 않습니다. 기본값 `[]`는 기존 동작을 유지합니다.

## Robots 렌더링 정책 {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

최종 지시문이 위의 `default_robots`와 같으면 렌더링된 `<head>`에서 `<meta name="robots">` 태그를 생략합니다. 불필요한 `index,follow`는 잡음이며 크롤러는 태그가 없는 것을 정확히 index,follow로 해석합니다. **다른** 지시문(`noindex`, `nofollow`, `max-snippet:-1` 등)은 항상 그대로 출력합니다. 항상 태그를 렌더링해 3.1 이전 동작을 복원하려면 `emit_default`를 `true`로 설정하세요. 개별 `@seoRobots` 지시문은 명시적으로 선택하는 기능이므로 영향받지 않고 항상 렌더링됩니다. 지원 지시문과 우선순위는 [렌더링 계약](/ko/contributing/rendering-contract)을 참고하세요.

## 색인 가드 (비운영 환경 안전장치) {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

활성화되어 있고 앱 환경이 `allowed_environments`에 **없으면**, 가드는 모든 페이지에 `noindex,nofollow`를 강제합니다. 전체 우선순위 체인보다 상위이므로 저장된 페이지별 값도 덮어씁니다. 같은 `X-Robots-Tag` 헤더를 보내고 전체 차단 `robots.txt`를 출력하며 `seo:audit`에 배너를 표시합니다. 기본값 `production`처럼 허용된 환경에서는 동작하지 않습니다.

기본적으로 **꺼져 있으며** 활성화 전에는 출력 바이트가 같습니다. `SEO_INDEXING_GUARD=true`로 켜고 `SEO_INDEXING_GUARD=false`로 끌 수 있습니다. 어느 쪽이든 한 줄입니다. `SEO_INDEXING_GUARD_ALLOWED`로 허용 목록을 바꿀 수 있습니다. 쉼표로 구분하며 `prod*` 같은 `Str::is()` 와일드카드를 지원합니다. 빈 목록은 모든 환경을 보호합니다.

가드 내부에서 기본 활성화되는 `send_header`는 앱을 통해 라우팅되는 모든 응답에 `X-Robots-Tag: noindex,nofollow`도 보냅니다. 따라서 `<meta robots>`가 없는 PDF, 피드, 이미지도 제한합니다. 미들웨어는 가드가 활성화될 때만 등록됩니다. 사용을 강력히 권장합니다. 전체 [색인 가드 가이드](/ko/guide/indexing-guard)를 참고하세요.

## 표준 URL {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

리졸버가 요청 URL 또는 모델의 `getUrlForSEO()`에서 **도출한** 표준 URL은 기본적으로 쿼리 문자열을 제거합니다. 추적, 필터, 정렬 매개변수는 같은 페이지에 중복 콘텐츠 표준 URL 대상을 만들기 때문입니다. `query_whitelist`에 키를 나열하면 도출된 표준 URL에 해당 순서로 **유지**하고 나머지는 제거합니다. 일반적인 예는 페이지가 나뉜 목록의 `page`입니다. `/blog?page=2`는 실제로 `/blog`와 다릅니다.

관리자가 입력했거나 상위 우선순위 계층에서 **명시적으로 설정한** 표준 URL은 쿼리 문자열을 포함해 항상 그대로 출력합니다. 허용 목록은 도출된 대체값에만 적용됩니다. 기본값 `[]`는 모두 제거하는 동작을 유지합니다.

## 기능 토글 {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

`auto_create_meta`는 `HasSEO` 모델을 생성할 때 빈 `seo_meta` 행을 만듭니다. `WithoutModelEvents`를 사용하는 시더는 이 동작을 건너뜁니다.

## 포커스 키워드 {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

포커스 키워드 **작업 흐름의 활성화 조건**입니다. 기본값 `false`일 때는 포커스 키워드가 없어도 어디에도 표시하지 않습니다. [`seo:audit`](/ko/guide/audit)와 Pro 스캔 모두 문제로 알리지 않으므로 도입하지 않은 기능의 알림을 받지 않습니다. [Filament 포커스 키워드 필드](/ko/guide/filament) 등으로 키워드를 설정하기 시작하면 켜세요. 무료 감사, Pro 스캔, Pro 편집기가 키워드 없는 페이지에 `missing_focus_keyword` 알림을 보고하기 시작합니다. 모두 같은 플래그를 읽으므로 항상 일치합니다.

## 무료 감사 (`seo:audit`) {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

`--model` 옵션을 전달하지 않았을 때 무료 [`seo:audit`](/ko/guide/audit) 명령이 감사할 모델입니다. 각 모델은 `HasSEO` 트레이트를 사용해야 합니다. 비어 있으면 `sitemap.models`에 등록된 모델을 사용합니다.

## 계산된 대체값 (계층 5) {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

선택적으로 활성화하는 `best` 전략에서는 빌더가 순서가 있는 후보 목록을 평가합니다. 가장 높은 우선순위를 유지하는 `getSEOImage()`, 모델의 `getSEOImages()` 훅, 일반 이미지 필드, 콘텐츠의 첫 이미지, 설정된 기본값 순서입니다. 각 이미지의 픽셀 크기가 이상적인 크기에 얼마나 가까운지 점수를 매기고 **최소 크기 미만은 건너뜁니다**. `public/` 아래의 상대 경로, public 디스크, 자신의 호스트 절대 URL 등 **로컬** 이미지만 측정합니다. 원격 URL은 요청하지 않고 대체값으로만 사용합니다. 최소 크기를 통과한 로컬 후보가 없으면 첫 일치 방식으로 돌아가므로 `best`가 `first`보다 적은 결과를 반환하지 않습니다. 모델에서 후보를 제공하세요.

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## 사이트맵 {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

코드로 소스를 등록하는 방법은 [사이트맵 레지스트리 가이드](/ko/guide/sitemaps)를 참고하세요.

## 스키마 (JSON-LD) {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

이 값들은 [스키마 그래프](/ko/guide/schema) 노드에 전달됩니다.

## 라우트 {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

앱이 자체 정적 `/sitemap.xml` 파일을 제공한다면 `enabled => false`를 설정하세요.

## 캐시 {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### 리졸버 결과 캐시 {#resolver-result-cache}

`SEOResolver`는 **모든** 프런트엔드 렌더링에서 전체 우선순위 체인을 실행합니다. 설정 → 전역 / 모델 유형 / 라우트 기본값 → 모델 계산값 → 명시적 `seo_meta` → 제목 접미사 / 표준 URL / 스키마 순서입니다. 참조 앱처럼 하루 약 20k 요청이 있는 고트래픽 사이트에서는 페이지마다 여러 DB 읽기가 발생합니다.

`cache.resolver.enabled`를 켜면 모델의 최종 SEO 데이터가 캐시되며, 캐시 **적중 시 우선순위 체인을 완전히 건너뜁니다**. 패키지 벤치마크에서 캐시가 준비된 적중은 데이터베이스 쿼리가 **0개**인 반면, 캐시 없는 값 결정은 매번 모델의 `seo_meta`를 다시 읽습니다. 캐시 페이로드는 일반 배열이며 `SEOData::fromArray()`로 복원합니다. 객체로 저장하지 않습니다. Laravel 13에는 `cache.serializable_classes = false`가 있어 캐시된 객체가 `__PHP_Incomplete_Class`로 반환되기 때문입니다.

위에서 설정한 `store`를 사용하므로 운영 환경에서는 **공유 영속 캐시**(`redis` / `memcached`)를 지정하세요. 모든 웹/큐 워커가 캐시와 무효화를 볼 수 있어야 합니다. 이런 캐시가 마련될 때까지 꺼 두세요.

**무효화는 자동이며 정확합니다**. 캐시를 켜도 끈 상태와 같은 값이 결정됩니다. 키는 `(model class, id, locale, route, request URL)`이며 다음 경우 지워집니다.

- 페이지의 `seo_meta` 행을 **저장 또는 삭제**할 때. `saveSEO()`, Filament, 직접적인 `SEOMeta` 쓰기 등 모든 경로가 해당합니다.
- 모델의 **콘텐츠 필드**, 즉 `getSEOContentFields()`의 열이 바뀔 때. 기본값은 모든 내장 계산 대체값 필드를 포함합니다. 제목/헤드라인, excerpt/summary/content/body/text/article 필드와 `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner`, `hero_image` 같은 일반 이미지 필드입니다. 모델이 추가 열에서 SEO를 계산한다면 재정의하세요.
- **어떤 `seo_defaults` 행이든** 바뀔 때. 기본값은 어떤 모델에든 쓰일 수 있으므로 전체 리졸버 캐시를 비웁니다.

**태그를 지원하는** 저장소(`redis`, `memcached`, `array`)에서는 캐시 **태그**로 모델 항목을 지웁니다. **태그를 지원하지 않는** 저장소(`file`, `database`)에서는 모델별 **버전 스탬프**를 사용합니다. 둘 다 키 탐색 없이 작동합니다.

::: tip
모델 기반 값 결정만 캐시합니다. 직접 구성한 `SEOData`에 대한 `SEO::render()`/`@seo()`와 모델 없는 라우트의 `@seoForRoute()`는 계속 실시간으로 값을 결정합니다.
:::

::: warning
캐시는 마지막 **콘텐츠 필드** 변경 시점의 모델 `updated_at`/계산된 `modified_time` 값을 반영하며 TTL이 만료될 때까지 유지될 수 있습니다. `getSEOContentFields()` 열을 바꾸지 않고 `updated_at`만 갱신하는 단순 `touch()`는 재결정을 강제하지 않습니다. `article:modified_time` 값은 최대 TTL만큼 늦게 반영될 수 있습니다. 앱 고유의 계산 열이 바뀔 때 즉시 무효화해야 한다면 `getSEOContentFields()`에 추가하세요.
:::

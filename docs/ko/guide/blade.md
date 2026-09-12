---
description: "패키지의 Blade 지시문로 서버 렌더링 Laravel 앱에 SEO를 출력하세요. 통합 @seo 지시문는 모델 값을 결정하고 메타 태그, Open Graph, Twitter Card, JSON-LD를 렌더링합니다."
---

# Blade 가이드 {#blade-guide}

일반적인 서버 렌더링 앱을 위해 일곱 가지 Blade 지시문를 제공합니다. 보통은 그중 `@seo` 하나면 충분합니다.

## 통합 지시문 {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo`는 [우선순위 체인](/ko/concepts/resolver-precedence)을 통해 모델 값을 결정하고 전체 head 블록을 렌더링합니다. 여기에는 `<title>`, 메타 설명, canonical 링크, robots, Open Graph 태그, Twitter Card 태그, 연결된 JSON-LD가 포함됩니다. robots 태그는 **사이트 기본값과 다를 때만** 출력합니다. 중복되는 `index,follow`는 생략하며, 태그가 없으면 이미 index,follow를 의미합니다. 항상 렌더링하려면 `seo.robots.emit_default`를 설정하세요. 자세한 내용은 [렌더링 계약](/ko/contributing/rendering-contract)을 참고하세요.

호출 형식:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

`@seo`에는 `Model`, 직접 구성한 `SEOData` 또는 `null`을 전달할 수 있습니다. 라우트와 로캘 인수는 `Model`/`null` 경로에만 적용됩니다. 직접 구성한 `SEOData`는 자체 값을 사용합니다.

## 라우트 페이지(모델 없음) {#route-pages-no-model}

정적 페이지, 아카이브 및 라우트를 기반으로 하는 다른 페이지에서는 다음과 같이 사용합니다.

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

라우트 값은 해당 라우트 이름으로 범위가 지정된 `seo_defaults` 행에서 가져옵니다.

## 모델 없는 페이지: 직접 구성한 `SEOData` {#model-less-pages-hand-built-seodata}

목록, 검색 결과처럼 컨트롤러에서 구성하는 페이지에는 하나의 기반 모델이 없는 경우가 많습니다. `SEOData`를 구성해 `@seo` 또는 `SEO` 파사드에 바로 전달하세요. `app(TagRenderer::class)->render(...)`를 사용할 필요는 없습니다.

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

직접 구성한 `SEOData`는 **명시적 의도**로 취급합니다. 설정한 모든 값은 보존하고, 렌더링 시 비어 있는 다음 값만 채웁니다.

- `canonical` / `og:url` 값이 없으면 현재 URL에서 유도합니다. 명시적 `canonical`은 쿼리 문자열까지 그대로 유지합니다.
- 제목에 `title_suffix`가 없을 때만 이를 덧붙입니다. 제목에 이미 브랜드 토큰이 있으면 접미사를 아예 추가하지 않습니다. [`title_suffix_skip_when_contains`](/ko/reference/configuration)를 참고하세요.
- 상대 경로인 `og:image` / `twitter:image` 값은 `url()`로 절대 URL로 변환합니다. 현재 프로토콜을 따르며 HTTPS를 강제하지 **않습니다**.
- `og:site_name` 및 `locale` 값은 설정 또는 앱 로캘에서 채웁니다.

데이터베이스의 우선순위 체인(전역 / 모델 유형 / 라우트 / `seo_meta` 기본값)은 직접 구성한 `SEOData`에 병합하지 **않습니다**. 전달한 값과 위에서 설명한 빈 값 보완만 렌더링에 반영됩니다.

같은 값을 파사드에서도 사용할 수 있습니다.

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## 확장 가능한 레이아웃 패턴 {#a-layout-pattern-that-scales}

하나의 레이아웃으로 모델 페이지, 라우트 페이지, 그 밖의 페이지를 모두 처리할 수 있습니다.

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

컨트롤러는 `'seoModel' => $post` 또는 `'seoRoute' => 'blog.index'`만 전달하며 마크업을 직접 다루지 않습니다.

## 개별 지시문 {#granular-directives}

다른 패키지의 출력과 함께 사용하는 경우처럼 개별 태그를 제어해야 할 때 사용하세요.

| 지시문 | 출력 |
|---|---|
| `@seoTitle($post)` | `<title>`만 출력 |
| `@seoMeta($post)` | 메타 설명만 출력 |
| `@seoCanonical($post)` | canonical 링크만 출력(값이 없으면 현재 URL 사용) |
| `@seoRobots($post)` | robots 메타만 항상 출력. 명시적으로 출력을 요청한 것이므로 `@seo`와 달리 기본값과 같을 때 생략하는 규칙을 적용하지 **않습니다**. |
| `@seoSchema($post)` | JSON-LD `<script>`만 출력. head와 body 어디에나 사용 가능 |

모두 `@seo`와 같은 `($model, $route, $locale)` 표현식을 받으며, 인수를 생략하면 현재 페이지를 사용합니다.

## Hreflang 대체 페이지 {#hreflang-alternates}

`HasSEO`를 사용하는 모델은 리졸버를 통해 hreflang 링크를 직접 제공할 수 있습니다.

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

절대 URL을 사용하세요. `@seo($post)`는 이 항목들의 값을 결정하고 각각 `<link rel="alternate" hreflang="..." href="...">`로 렌더링합니다. 코드는 먼저 BCP 47 형식으로 변환합니다(`it_IT` → `it-IT`). `seo.hreflang` 정책에 따라 페이지의 자기 참조와 `x-default`를 추가할 수 있습니다. 무료 감사는 잘못된 항목, 중복 항목, 자기 참조 누락을 표시합니다. [다국어 콘텐츠](/ko/guide/multilingual#hreflang)를 참고하세요.

## 이스케이프와 안전성 {#escaping-and-safety}

텍스트 값은 `e()`로 이스케이프합니다. JSON-LD는 `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`로 인코딩하므로 사용자 콘텐츠에 `</script>`가 있어도 script 요소 밖으로 빠져나올 수 없습니다.

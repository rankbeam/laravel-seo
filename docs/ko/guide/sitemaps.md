---
description: "소스별 파일과 색인으로 구성된 XML 사이트맵을 생성해 /sitemap.xml에서 제공하세요. 모델, 클로저, URL 목록을 소스로 등록하며 spatie/laravel-sitemap을 사용합니다."
---

# 사이트맵 레지스트리 {#sitemap-registry}

패키지는 소스별 파일과 색인으로 구성된 XML 사이트맵을 생성하고 `/sitemap.xml` 및 `/sitemap-{name}.xml`에서 제공합니다. 생성에는 [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap)을 사용합니다.

```bash
composer require spatie/laravel-sitemap
```

## 소스 등록 {#registering-sources}

서비스 프로바이더의 `boot()`에서 이름이 있는 소스를 등록하세요.

```php
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

// A model class — every (indexable) record's getUrlForSEO()
SEO::sitemaps()->register('posts', Post::class);

// A closure returning URLs
SEO::sitemaps()->register('pages', fn () => ['/about', '/contact']);

// Any iterable of URLs
SEO::sitemaps()->register('legal', ['/imprint', '/privacy']);
```

각 소스는 `sitemap-{name}.xml`로 렌더링되고, `sitemap.xml` 파일은 모든 소스를 나열하는 색인이 됩니다.

레지스트리 API는 `has($name)`, `names()`, `forget($name)`, `flush()`도 제공합니다.

## 설정 기반 소스 {#config-driven-sources}

설정으로 관리하고 싶다면 `config/seo.php`에 모델 소스와 정적 URL을 지정하세요.

```php
'sitemap' => [
    'models' => [
        \App\Models\Post::class => ['priority' => 0.8, 'changefreq' => 'weekly'],
    ],
    'static_urls' => [
        ['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily'],
    ],
],
```

::: info 자동 탐색은 레지스트리에 우선권을 줍니다
이름을 지정해 등록한 소스가 모델을 포함하면 자동 탐색은 그 모델을 건너뜁니다. `'posts'`를 등록해도 `sitemap-post.xml` 파일이 추가 생성되지 않습니다.
:::

## 생성 {#generating}

```bash
php artisan seo:sitemap
```

파일은 `seo.sitemap.disk`에서 설정한 디스크에 기록됩니다(기본값 `public`). 사이트맵을 최신 상태로 유지하도록 명령을 예약하세요.

```php
// routes/console.php or bootstrap/app.php scheduling
Schedule::command('seo:sitemap')->daily();
```

`seo.sitemap.max_urls_per_sitemap`(기본값 50,000, XML 규격 한도)를 넘는 사이트맵은 자동으로 나눕니다.

## 제공 {#serving}

패키지 라우트는 명령이 생성한 파일을 XML 헤더, 캐시 헤더, `X-Robots-Tag: noindex`와 함께 제공합니다.

- `/sitemap.xml` — 색인 또는 단일 사이트맵
- `/sitemap-posts.xml` — 이름이 지정된 소스

직접 정적으로 생성한 사이트맵을 제공하려면 라우트를 비활성화하세요.

```php
// config/seo.php
'routes' => ['enabled' => false],
```

## 브라우저의 스타일 적용 사이트맵 {#styled-sitemap-in-the-browser}

Spatie 엔진은 원시 XML을 출력합니다. Rankbeam 사이트맵을 브라우저에서 열면 읽기 쉬운 브랜드 페이지로 표시됩니다. 모든 URL을 `lastmod`, 변경 빈도, 우선순위, 이미지/대체 페이지 수와 함께 표에 보여 주고 검증 안내도 표시합니다.

![브라우저에서 읽기 쉬운 브랜드 표로 렌더링된 Rankbeam 사이트맵](/sitemap-styled.png)

생성된 각 사이트맵에서 XSL 스타일시트를 참조하는 방식입니다.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type="text/xsl" href="https://your-app.test/sitemap.xsl"?>
<urlset ...>
```

검색 엔진은 이 처리 지시문을 **무시**하므로 사이트맵은 일반적인 기계 판독용 XML 문서로 유지됩니다. *사람*에게 보이는 모습만 바꿉니다. 색인과 모든 하위 사이트맵에 같은 스타일이 적용됩니다.

**기본적으로 켜져 있습니다.** 이미지/hreflang 확장과 달리 데이터를 추가하거나 레코드별 작업을 수행하지 않습니다. 크롤러가 건너뛰는 지시문 한 줄뿐이므로 기본 활성화되어 있습니다. 일반 XML을 출력하려면 끄세요.

::: warning spatie/laravel-sitemap ≥ 8.1 필요
이 지시문은 `spatie/laravel-sitemap` **8.1**에서 추가된 Spatie의 `setStylesheet()`를 통해 기록됩니다. 일부 PHP/Laravel 조합처럼 앱이 이전 버전을 사용하면 스타일 없는 일반 XML이 생성되며 기능은 정상 동작합니다. 스타일 보기를 사용하려면 `composer update spatie/laravel-sitemap`를 실행하세요.
:::

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => ['enabled' => false],
],
```

### 검증 안내 {#validation-notes}

렌더링된 페이지는 브라우저 밖으로 요청하지 않고 확인할 수 있는 두 가지를 표시합니다.

- **`lastmod`가 없는 URL** — 누락을 표시하고 값을 만들어 넣지 않습니다. Google은 최신성을 허위로 표시하는 사이트맵을 신뢰하지 않으므로, 스타일시트는 빈 부분을 채우는 대신 알려 줍니다.
- **절대 URL이 아닌 항목** — 절대 `http(s)` URL이 아닌 `<loc>`.

### 스타일시트 직접 호스팅 {#self-hosting-the-stylesheet}

기본적으로 패키지는 자체 `/sitemap.xsl` 라우트에서 스타일시트를 제공하고 각 사이트맵이 이를 가리키게 합니다. 브라우저는 사이트맵과 **출처가 같은** XSLT만 적용합니다. 따라서 사이트맵이 CDN 등 다른 출처에 있다면 파일을 게시하고 설정에서 그 사본을 가리키세요.

```bash
php artisan vendor:publish --tag=seo-assets
```

```php
// config/seo.php
'sitemap' => [
    'stylesheet' => [
        'url' => 'https://cdn.example.com/vendor/seo/sitemap.xsl',
    ],
],
```

::: info 구조적으로 안전한 출력
URL을 포함해 스타일시트가 렌더링하는 모든 값에는 XSLT 출력 이스케이프가 적용됩니다. `<loc>`는 `http(s)` URL일 때만 클릭 가능한 링크가 되므로, 악의적인 URL 내용으로 마크업이나 `javascript:` 링크를 삽입할 수 없습니다. 게시한 `.xsl`를 수정할 때도 이 원칙을 유지하고 `disable-output-escaping`를 추가하지 마세요.
:::

## 포함 대상 {#what-gets-included}

모델 소스는 최종적으로 색인 가능하다고 결정된 레코드를 포함합니다. robots 값이 `noindex`로 결정되는 모델은 사이트맵에서 제외됩니다. URL은 표준 URL에도 사용되는 동일한 메서드 `getUrlForSEO()`에서 가져오므로 사이트맵과 표준 URL이 어긋나지 않습니다.

## 이미지 및 hreflang 확장 {#image-hreflang-extensions}

두 선택적 확장은 패키지가 해당 레코드에서 이미 결정하는 데이터를 각 모델 URL에 추가합니다. 둘 다 **기본적으로 꺼져 있습니다**. 필요한 항목을 `config/seo.php`에서 활성화하세요.

```php
'sitemap' => [
    'images' => true,      // <image:image> per URL
    'alternates' => true,  // <xhtml:link rel="alternate"> per URL
],
```

`HasSEO` 트레이트를 사용하는 모델에 적용되며 값은 모델의 최종 `seoData()`에서 가져옵니다.

- **`images`**는 최종 OG/콘텐츠 이미지로 [Google 이미지 사이트맵](https://developers.google.com/search/docs/crawling-indexing/sitemaps/image-sitemaps) 항목을 추가합니다. `og:image`로 렌더링되는 *동일한* 값이므로 사이트맵과 페이지가 어긋나지 않습니다. 레코드 자체 이미지가 없으면 사이트 전체의 `default_og_image`를 사용하므로, 콘텐츠에 URL별 이미지가 의미 있을 때만 활성화하세요.
- **`alternates`**는 모델의 `getSEOAlternates()`에서 `<xhtml:link rel="alternate" hreflang="…">` 항목을 추가합니다. 페이지 `<head>`에 렌더링되는 동일한 hreflang 링크입니다. 절대 URL을 반환하세요.

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', [$this, 'locale' => 'en'])],
        ['hreflang' => 'fr', 'href' => route('posts.show', [$this, 'locale' => 'fr'])],
        ['hreflang' => 'x-default', 'href' => route('posts.show', $this)],
    ];
}
```

::: warning hreflang은 상호 참조하고 자기 자신도 포함해야 합니다
Google은 모든 언어 버전이 **자기 자신과 나머지 전체**를 나열하고 참조가 **상호적일 때**(각 페이지가 다시 연결될 때)만 주석을 인정합니다. 따라서 `getSEOAlternates()`는 **완전한** 집합을 반환해야 하며 모든 언어 버전이 같은 전체 집합을 반환해야 합니다. 유효한 `language[-Script][-REGION]` 코드 또는 `x-default`와 절대 `http(s)` URL을 사용하세요. `hreflang` 또는 `href`가 없거나 비어 있는 항목은 건너뜁니다.

목록은 기록 전에 [`seo.hreflang` 정책](/ko/guide/multilingual#hreflang)을 거칩니다. 코드는 BCP 47로 정규화되며(`it_IT` → `it-IT`), `include_self` / `x_default`가 자기 참조와 `x-default`를 추가할 수 있습니다. 사이트맵에는 항상 페이지 `<head>`와 같은 목록이 들어갑니다. 무료 감사는 `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self`를 보고합니다. 상호 참조 검사에는 크롤링(Pro)이 필요합니다.
:::

::: info 대규모 처리 비용
**코어 3.20.1**부터 모델 포함 여부와 이미지/hreflang 확장은 각 모델 URL을 구성하는 동안 같은 최종 `seoData()`를 재사용합니다. 실패한 경우를 포함해 URL 처리가 끝나면 재사용도 끝나며, 이후 빌드나 로케일에서는 새 데이터를 결정합니다. 3.20.0에서는 기본값인 리졸버 캐시 비활성 상태에서 포함 여부와 확장이 우선순위 체인을 두 번 순회할 수 있었습니다. 값 결정마다 캐시/데이터베이스 작업이 발생할 수 있고 사용자 지정 `getSEO*()` getter가 쿼리를 추가할 수도 있습니다. 웹 요청 대신 **예약된** `seo:sitemap` 명령을 사용하세요. 50,000 URL 한도에 가까워지면 성능을 측정하고 불필요한 확장은 꺼 두세요.
:::

::: tip 이미 설정 파일을 게시했다면
`config/seo.php`는 **얕은 병합**을 수행합니다. 따라서 이 릴리스 전에 설정 파일을 게시한 앱은 `sitemap.images` / `sitemap.alternates` 키를 자동으로 받지 못하며, `SEO_SITEMAP_IMAGES` / `SEO_SITEMAP_ALTERNATES` 환경 변수만으로는 켤 수 없습니다. 위 코드처럼 게시한 `sitemap` 배열에 두 키를 추가하거나 설정을 다시 게시하세요.
:::

## 완전한 제어: 직접 구성한 Spatie 태그 {#full-control-hand-built-spatie-tags}

이미지 설명, **동영상**, **뉴스** 항목 또는 사용자 지정 `hreflang` 집합처럼 최종 데이터가 다루지 않는 내용은 등록된 소스에서 직접 구성한 [`Spatie\Sitemap\Tags\Url`](https://github.com/spatie/laravel-sitemap#adding-images)를 반환하세요. 빌더는 `Url` 태그를 그대로 전달하고 자체 확장을 덧붙이지 않으므로 완전히 제어할 수 있습니다.

```php
use Spatie\Sitemap\Tags\Url;

SEO::sitemaps()->register('videos', fn () => Video::query()
    ->get()
    ->map(fn (Video $video) => Url::create($video->url)
        ->addImage($video->thumbnail_url, caption: $video->title)
        ->addVideo(
            thumbnailLoc: $video->thumbnail_url,
            title: $video->title,
            description: $video->description,
            contentLoc: $video->file_url,
        )
        ->addAlternate($video->frenchUrl, 'fr')
    ));
```

레코드별로도 같은 방법을 사용할 수 있습니다. `Sitemapable`를 구현하는 모델의 `toSitemapTag()`가 `Url` 태그를 반환하면 반환한 그대로 출력됩니다.

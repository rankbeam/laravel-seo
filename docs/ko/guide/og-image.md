---
description: "헤드리스 브라우저로 Blade 템플릿을 렌더링해 페이지별 1200×630 Open Graph 이미지를 만드세요. 제목 줄바꿈과 잘림을 처리하는 무료 코어 기능이며 기본적으로 꺼져 있습니다."
---

# 생성된 OG 이미지 {#generated-og-images}

코어 3.20부터 Chrome 렌더링은 JavaScript를 끄고 HTTP(S), FTP, WebSocket 자산 요청을 차단합니다. 사용자 지정 템플릿도 기본 템플릿처럼 정적 HTML/CSS와 내장 자산을 사용해야 합니다.

소셜 카드가 없는 페이지는 공유 `default_og_image` 하나를 사용하므로 모든 공유에 같은 그림이 표시됩니다. 이 기능은 실제 헤드리스 브라우저([spatie/browsershot](https://github.com/spatie/browsershot))로 Blade 템플릿을 렌더링해 페이지마다 **자체** 1200×630 Open Graph / Twitter 카드를 제공합니다. 제목 줄바꿈, 악센트, CJK 글꼴 대체, 긴 제목의 깔끔한 잘림을 처리합니다. 직접 만든 이미지 라이브러리만으로는 이 모두를 올바르게 처리하기 어렵습니다.

무료 코어 기능이며 **기본적으로 꺼져 있습니다**. 꺼져 있으면 기존 `default_og_image`를 그대로 사용하고 추가 의존성이 필요하지 않습니다.

::: info 의도적인 정적 사전 생성
카드는 웹 요청 중 즉석에서 만들지 않고 artisan 명령으로 미리 생성합니다. 페이지는 디스크에 이미 있는 카드만 연결하므로 방문자 요청이 브라우저를 실행하거나 없는 404 이미지로 연결되지 않습니다. **실시간 렌더링 엔드포인트는 없습니다**. [주의 사항](#caveats)을 참고하세요.
:::

## 요구 사항 {#requirements}

브라우저 드라이버는 선택적 의존성이므로 무료 코어는 없이 설치됩니다. 기능을 켜려면 앱에 다음이 필요합니다.

```bash
composer require spatie/browsershot
```

Browsershot이 구동하는 런타임도 필요합니다.

- 호스트의 **Node.js**.
- Node가 찾을 수 있도록 **앱 루트**에 설치한 **Puppeteer**:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium** — Puppeteer는 기본적으로 자체 Chromium을 내려받습니다. 운영 환경에서는 보통 시스템 Chrome을 지정합니다([`chrome_path`](#configuration) 참고).

::: warning Windows에서는 앱 루트에 puppeteer 설치
Windows에서는 `npm_module_path`에 의존하지 말고 앱 루트에 `puppeteer`를 설치하세요. 이 설정 키는 Browsershot의 `setNodeModulePath()`에 대응해 POSIX `NODE_PATH=…` 접두사를 만들지만 **Windows에서는 효과가 없습니다**. Windows의 Node는 앱에서 상위 디렉터리로 올라가며 모듈을 찾으므로 루트 설치가 작동합니다. [주의 사항](#caveats)을 참고하세요.
:::

## 활성화 {#enabling}

아직 설정을 게시하지 않았다면 `php artisan vendor:publish --tag=seo-config`로 게시하고 스위치를 켜세요.

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

그런 다음 카드를 **사전 생성**하세요. 이 작업 전에는 렌더링되지 않습니다.

```bash
php artisan seo:og-images
```

## 값 결정 방식 {#how-resolution-works}

이미지 생성은 직접 설정한 이미지를 덮어쓰지 않습니다. 활성 상태에서 리졸버는 **페이지 자체 이미지가 없을 때만** `og:image`를 채웁니다. 최종 `og:image`가 비어 있거나 여전히 사이트 전체의 정적 `default_og_image`인 경우입니다. `getSEOImage()`, `seo_meta` 행, 콘텐츠 필드 등에서 지정한 모델별 이미지는 생성 카드보다 항상 우선합니다.

리졸버는 생성기의 **파일 존재 여부를 확인하는** 조회를 호출합니다. 카드 저장 경로를 계산하고 **설정된 디스크에 파일이 이미 있을 때만** 공개 URL을 반환하며 렌더링하지 않습니다. 안전성은 이 원칙에서 나옵니다.

- 웹 요청은 **브라우저를 실행하지 않습니다**. 최악의 경우 기능 도입 전처럼 정적 `default_og_image`를 연결합니다.
- 페이지는 **아직 생성되지 않은 이미지를 연결하지 않으므로** 공유가 404로 연결되는 구간이 없습니다.

콘텐츠 변경과 카드 생성 사이의 간격은 배포 시 또는 예약으로 [`seo:og-images`](#the-seo-og-images-command) 명령을 실행해 줄입니다.

## `seo:og-images` 명령 {#the-seo-og-images-command}

리졸버가 제공할 수 있도록 카드를 미리 만듭니다.

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*` — 준비할 모델 클래스 하나 이상이며 반복 지정 가능합니다. 생략하면 `seo.og_image.models`를 사용하고 없으면 [사이트맵 모델](/ko/guide/sitemaps)(`seo.sitemap.models`)을 사용합니다. `seo:llms-txt`처럼 사이트맵 소스를 공유합니다.
- `--force` — 이미 존재하는 카드도 다시 렌더링합니다. `cache_version` 값을 올리지 않고 템플릿이나 브랜드 색상을 바꿨을 때 사용하세요.
- `--prune` — 생성 후 설정 경로에서 현재 어떤 모델 콘텐츠와도 일치하지 않는 카드를 삭제합니다. 안전을 위해 생성된 콘텐츠 해시 이름의 파일만 제거하며 같은 디렉터리의 다른 자산은 건드리지 않습니다. 다른 모델까지 보존 목록에 포함하지 못하는 **범위 제한 `--model` 실행에서는 무시**되므로 `--model` 없이 실행하세요.

각 모델은 `HasSEO` 트레이트를 사용해야 합니다. 제목이 없는 레코드는 카드에 넣을 내용이 없으므로 건너뜁니다. 명령은 `generated`, `skipped`, `failed` 및 `--prune` 사용 시 `pruned` 수를 보고합니다.

### 예약 {#scheduling}

콘텐츠 변경을 반영하고 제목 변경 후 남은 고아 카드를 정리하도록 예약하세요.

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### 무효화 모델 {#the-invalidation-model}

카드 파일 이름은 **픽셀에 영향을 주는 모든 항목의 해시**입니다. 제목, 사이트 이름, 템플릿 이름, 드라이버, 크기, 브랜드 그라디언트 색상, `cache_version` 번호, **설치된 패키지 버전**이 포함됩니다.

이 해시가 캐시 키이므로 다음을 이해해야 합니다.

- **제목 변경 → 새 해시 → 새 파일.** 기존 카드는 디스크의 *고아 파일*이 되며 다시 생성하기 전까지 페이지는 정적 기본값을 사용합니다. 명령이 새 카드를 만들고 `--prune` 옵션이 고아 파일을 삭제합니다. 이것이 무효화 방식이며 별도의 “페이지 하나 무효화” 단계는 없습니다.
- **`cache_version` 값을 올리거나 패키지를 업그레이드하면 모든 해시가 바뀝니다.** 템플릿이나 브랜드 색상을 편집한 후 `cache_version` 값으로 모든 카드를 한 번에 무효화하세요. 패키지 업그레이드는 자동 반영되므로 기본 템플릿이 바뀐 새 릴리스에서 오래된 카드를 제공하지 않습니다.

## 기본 제공 템플릿 {#bundled-templates}

같은 브랜드 그라디언트의 1200×630 템플릿 세 개를 제공합니다.

| 템플릿 | 적합한 대상 | 표시 내용 |
| --- | --- | --- |
| `seo::og.default` | 모든 대상 | 제목 + 사이트 이름 |
| `seo::og.article` | 블로그 글, 뉴스 | 섹션 표시 + 제목 + 작성자 · 날짜 |
| `seo::og.product` | 제품, 목록 | 브랜드 표시 + 카테고리 칩 + 제목 + 설명 |

`seo.og_image.template`로 전체 템플릿을 선택하거나 **모델 유형별**로 매핑해 글과 제품에 다른 카드를 자동 적용하세요.

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

모델은 `getOgImageTemplate(): ?string`를 정의해 런타임에 자체 템플릿을 덮어쓸 수도 있습니다. 뷰 이름을 반환하고, 맵/기본값을 사용하려면 `null` 값을 반환하세요. 우선순위는 모델 훅, `templates` 맵, 전역 `template` 순서입니다.

## 템플릿 사용자 지정 {#customizing-the-template}

카드는 기본값 `seo::og.default`인 Blade 뷰를 독립 HTML 문서로 렌더링합니다. 기본 글꼴을 data URI로 넣으므로 브라우저에 네트워크가 필요하지 않습니다. 두 가지 방식으로 바꿀 수 있습니다.

**기본 뷰를 게시한 뒤 편집:**

```bash
php artisan vendor:publish --tag=seo-views
```

그런 다음 `resources/views/vendor/seo/og/default.blade.php`를 편집하세요.

**또는 자신의 뷰 지정:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

템플릿에 전달되는 변수:

| 변수 | 유형 | 참고 |
| --- | --- | --- |
| `$title` | `string` | 설정된 OG 제목, 없으면 페이지 제목. |
| `$siteName` | `?string` | 최종 `og:site_name`. |
| `$fontDataUri` | `string` | 기본 굵은 글꼴의 `data:` URI. 없으면 빈 문자열이며 브라우저의 sans-serif 사용. |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | 출력 너비(기본 `1200`). |
| `$height` | `int` | 출력 높이(기본 `630`). |
| `$locale` | `?string` | `<html lang>` 속성용 최종 페이지 로케일. |
| `$author` | `?string` | 글 작성자(`seo::og.article`에서 사용). |
| `$publishedDate` | `?string` | `seo::og.article`의 게시 날짜. 가능하면 페이지 로케일의 ICU 중간 형식, 아니면 Carbon이 `M j, Y` 순서로 월 이름 번역. 날짜가 없으면 null. |
| `$section` | `?string` | 콘텐츠 섹션 / 카테고리(글 상단 표시, 제품 칩). |
| `$description` | `?string` | OG 설명, 없으면 페이지 설명(`seo::og.product`에서 사용). |

::: info 템플릿 이름은 캐시 키의 일부입니다
템플릿 **이름**과 그라디언트 색상 모두 콘텐츠 해시에 들어가므로 템플릿 교체나 색상 변경은 기존 카드를 자동 무효화합니다. 같은 이름으로 템플릿 *내용만 편집*하면 무효화되지 않습니다. 편집 후 `cache_version` 값을 올리거나 `--force`를 실행하세요.
:::

## 설정 {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

대부분의 스칼라 값에는 대응 환경 변수(`SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH`, `SEO_OG_IMAGE_NO_SANDBOX` 등)가 있습니다. 전체 목록은 설정 파일을 참고하세요. 배열 키(`templates`, `models`, `browsershot_args`, `font_stack`)는 설정 파일에서 직접 편집합니다.

리졸버가 디스크의 `url()`를 `og:image` 값으로 사용하므로 디스크는 **공개 제공**되어야 합니다. `public` 디스크에서는 `php artisan storage:link`를 한 번 실행해 `public/storage`가 연결되도록 하세요.

## Linux에서 실행 (샌드박스) {#running-on-linux-the-sandbox}

Chrome 샌드박스 방식을 제한하는 호스트에서는 `php artisan seo:og-images`가 다음 오류로 실패할 수 있습니다.

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

가능한 원인 하나는 Ubuntu 23.10+의 사용자 네임스페이스 제한입니다. [Puppeteer 문제 해결 가이드](https://pptr.dev/troubleshooting)와 실제 브라우저 실행 오류를 확인하세요. Chrome 샌드박스를 유지할 수 있도록 호스트 설정을 고치는 방식을 우선하세요.

**1. 명시적 대안: `--no-sandbox`로 Chrome 실행.** 브라우저 격리를 끕니다. 배포에서 이 절충을 의도적으로 수용한 경우에만 사용하세요.

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Rankbeam은 정적으로 생성한 HTML을 렌더링하고 원격 자산 요청을 막지만, 이런 제어가 Chrome 샌드박스를 대체하지는 않습니다. 렌더링 프로세스는 권한을 낮추고 관련 없는 작업 및 비밀 정보와 격리하세요.

**2. 샌드박스 유지.** `no_sandbox`를 꺼 두세요. AppArmor가 원인이라면 정확한 Chrome 실행 파일용 프로필을 조정하세요. [Chromium 안내](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md)를 참고하세요. 예시:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

그런 다음 `sudo apparmor_parser -r /etc/apparmor.d/chrome-og`로 프로필을 로드하고 샌드박스가 켜진 상태로 Chrome이 시작되는지 확인하세요.

::: tip 다른 플래그
공유 메모리가 부족한 컨테이너에서는 렌더링 도중 Chrome이 중단되는 흔한 Linux 오류가 발생할 수 있습니다. `browsershot_args`로 플래그를 추가하세요.

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## 사용자 지정 드라이버 {#custom-drivers}

기본 드라이버는 `browsershot` 하나지만 렌더러는 `Rankbeam\Seo\Contracts\OgImageRenderer` 계약 뒤에 있습니다. 캔버스 또는 서비스 기반 등 자신의 렌더러를 등록하고 `seo.og_image.driver`로 선택하세요.

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

드라이버는 독립 HTML 문자열을 지정한 크기의 PNG 바이트로 바꾸기만 하며 레이아웃이나 템플릿은 관리하지 않습니다.

## 글꼴과 비라틴 문자 체계 {#fonts-and-non-latin-scripts}

기본 카드 글꼴(Noto Sans Bold, OFL)은 **라틴, 키릴, 그리스 문자**를 지원합니다. 중국어, 일본어, 한국어, 태국어, 아랍어, 히브리어, 데바나가리, 이모지 등 나머지는 **`seo:og-images`를 실행하는 컴퓨터에 설치된 글꼴**에서 가져옵니다. CJK 글꼴 하나가 16 MB 이상이고 호스트에 적절한 글꼴만 있으면 Chrome의 글자별 대체가 동작하므로 나머지는 의도적으로 포함하지 않습니다.

3.15의 세 가지 장치가 이를 뒷받침합니다.

1. **모든 기본 템플릿의 문자 체계별 `font-family` 스택.** body는 내장 글꼴 `'OGBrand'`, 다음으로 `seo.og_image.font_stack`, 마지막으로 `sans-serif`를 선언합니다. 중간 스택 기본값은 `Noto Sans`, 네 `Noto Sans CJK` 글꼴, `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`, `Noto Sans Devanagari`, `Noto Color Emoji`입니다. Chrome은 문자마다 설치된 첫 글꼴로 대체하며 없는 글꼴은 건너뜁니다. 같은 한자 코드 포인트도 국가별 글꼴에서 다르게 그려지므로(한자 통합) **페이지 언어의 CJK 글꼴을 앞으로 옮깁니다**. `ja` → JP, `zh-Hans` → SC, `zh-Hant` / `zh-TW` / `zh-HK` → TC, `ko` → KR입니다. `<html lang>` 속성에는 BCP 47 형태의 페이지 로케일을 넣습니다. 스택도 캐시 키에 포함되므로 바꾸면 모든 카드를 다시 렌더링합니다.

2. **`seo:og-images`의 사전 점검.** 렌더링 전에 fontconfig(`fc-list :lang=ja`, `th`, `ar` 등)에 제목, 사이트 이름, 설명의 문자 체계를 지원하는 글꼴이 있는지 묻습니다. 혼합 텍스트의 소수 문자 체계도 포함하며 설치할 패키지와 함께 **문자 체계당 한 번** 경고합니다.

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   Windows, macOS, 최소 컨테이너처럼 fontconfig가 없으면 추측하지 않고 조용히 넘어갑니다. 글꼴이 없어도 렌더링은 실패하지 않고 Chrome이 .notdef 네모를 그리므로 이 경고가 필요합니다.

3. **실제 스모크 테스트의 문자 체계별 글리프 픽스처.** `SEO_OG_IMAGE_LIVE_TEST=1` 설정을 사용하면 `tests/Feature/OgImage/BrowsershotSmokeTest.php`가 ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he, hi 제목과 같은 길이의 미할당 코드 포인트 대조군(확실히 네모로 표시)을 렌더링합니다. 두 PNG의 바이트가 같으면 문자 체계와 패키지 이름을 알리고 실패합니다. 이는 스모크 검사이며 모든 글리프의 증거는 아닙니다. 혼합 라틴 문자나 다른 줄바꿈 때문에 일부 글리프가 없어도 이미지가 달라질 수 있습니다. 배포 호스트의 실제 렌더링과 사용 글꼴을 확인하세요. 언어 수준 FontProbe 경고도 사전 점검이며 완전한 글꼴 범위 인증은 아닙니다. 코어에 `seo:doctor` 명령은 없습니다. 이 사전 점검에는 `seo:og-images`를 사용하세요.

Debian/Ubuntu:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

3.15 전에 `--tag=seo-views`로 게시한 자체 템플릿도 계속 동작합니다. 새로운 `$fontFamily`와 `$lang` 변수를 받지만 사용하지 않아도 됩니다.

## 주의 사항 {#caveats}

운영 환경에서 문제가 될 수 있어 명확히 설명합니다.

- **사전 생성 전용 — 실시간 렌더링 엔드포인트 없음(v1).** 요청 시 카드를 렌더링하는 라우트가 없습니다. 웹 요청에서 렌더링하지 않으므로 이 기능을 위한 **서명 URL / SSRF / DoS 인터페이스를 설정하거나 방어할 필요가 없습니다**. 대신 배포 시 또는 예약으로 [`seo:og-images`](#the-seo-og-images-command)를 실행해야 카드가 존재합니다.
- **`npm_module_path`는 Windows에서 효과가 없습니다.** Browsershot의 `setNodeModulePath()`로 매핑되어 명령 앞에 POSIX `NODE_PATH=…`를 붙이지만 Windows는 이를 무시합니다. Windows에서는 **앱 루트**에 `puppeteer`를 설치해 Node가 상위 디렉터리를 탐색하며 찾게 하세요. Linux/macOS에서는 설정이 정상 작동합니다.
- **비라틴 문자에는 호스트 글꼴이 필요합니다.** [글꼴과 비라틴 문자 체계](#fonts-and-non-latin-scripts)를 참고하세요. 내장 글꼴은 라틴, 키릴, 그리스 문자만 지원하며 나머지는 배포 이미지에 설치한 글꼴을 사용합니다. 명령은 누락을 알려 줍니다.
- **실패 시 기존 이미지 유지.** 패키지 누락, 브라우저 중단, 시간 초과 등으로 렌더링에 실패하면 명령이 보고하고 페이지는 정적 `default_og_image`를 계속 사용합니다. 브라우저 문제 때문에 페이지가 500 오류를 반환하지 않습니다.

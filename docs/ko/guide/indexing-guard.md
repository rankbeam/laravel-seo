---
description: "색인 가능 여부를 Laravel 환경에 연결해 스테이징이나 로컬 복제본이 검색에 노출되지 않도록 하세요. 허용 목록에 없는 환경에는 noindex를 강제하고 크롤러를 차단합니다."
---

# 색인 방지(비프로덕션 환경 안전장치) {#indexing-guard-non-production-safety-net}

스테이징이나 로컬 사이트 복제본이 Google에 노출되는 것은 흔하면서도 피해가 큰 SEO 실수입니다. 중복 콘텐츠가 실제 페이지와 경쟁하고, 비공개 환경이 검색 색인에 남으며, URL 삭제 도구로 정리하는 데 몇 주가 걸릴 수 있습니다. 대표적인 원인은 설정을 잊은 `.env`에만 있는 `noindex`나 배포 과정에서 덮어쓴 robots 규칙입니다.

**색인 방지** 기능은 이런 실수가 구조적으로 발생하기 어렵게 만듭니다. 누군가 기억해서 설정해야 하는 플래그 대신 Laravel *환경*에 색인 가능 여부를 연결합니다. 앱이 허용 목록에 없는 환경에서 실행되면 모든 페이지에 `noindex,nofollow`를 강제하고, 관리되는 `robots.txt`가 모든 크롤러를 차단하며, `seo:audit`가 이 상태를 명확하게 알립니다.

무료 코어 기능입니다.

## 활성 상태에서 하는 일 {#what-it-does-when-active}

색인 방지 기능이 켜져 있고 `app()->environment()`가 `seo.indexing_guard.allowed_environments`에 **없으면**, 다음 네 가지가 자동으로 적용됩니다.

1. **리졸버가 모든 페이지에 `noindex,nofollow`를 강제합니다.** 전체 [우선순위 체인](/ko/concepts/resolver-precedence)보다 *위에서* 적용하므로, `seo_meta`에 저장한 페이지별 명시적 `robots` 값도 덮어씁니다.
2. 앱을 거치는 모든 응답에 **`X-Robots-Tag: noindex,nofollow` HTTP 헤더**를 보냅니다. 아래의 [HTML이 아닌 응답](#non-html-responses-pdfs-feeds-images)을 참고하세요.
3. **`SEO::robotsTxt()->build()`가 모든 접근을 금지하는 `robots.txt`**(및 `ai.txt`)를 출력합니다. 단순한 `User-agent: *` / `Disallow: /`입니다. `seo:robots-txt` 명령과 선택적 [동적 라우트](/ko/guide/ai-crawlers)에 모두 적용됩니다.
4. **`seo:audit`가 눈에 잘 띄는 배너를 표시합니다.** 보고서를 읽을 때 “모든 페이지가 noindex”인 상태를 놓치지 않도록 합니다.

허용된 환경(기본값 `production`)에서는 이 기능이 완전히 **비활성 상태**이며, 출력이 바뀌지 않고 렌더링 결과도 바이트 단위로 동일합니다.

## HTML이 아닌 응답(PDF, 피드, 이미지) {#non-html-responses-pdfs-feeds-images}

강제된 `robots` **메타 태그**는 HTML을 해석하는 크롤러에만 전달됩니다. PDF, RSS/Atom 피드, 이미지 등 HTML이 아닌 응답에는 `<head>`가 없습니다. 따라서 색인 방지가 활성 상태일 때는 전역 미들웨어가 같은 지시문을 HTTP 헤더로도 보냅니다.

```http
X-Robots-Tag: noindex,nofollow
```

헤더와 메타 태그는 같은 출처에서 생성되므로 서로 다른 값을 가질 수 없습니다. 헤더는 **색인 방지 기능 안에서 기본적으로 켜져 있습니다**. 색인 방지 자체는 선택적으로 활성화하며, 허용된 환경에서는 작동하지 않습니다. 메타 태그만 사용하려면 헤더를 끄세요.

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

미들웨어는 **색인 방지 기능을 켰을 때만** 등록됩니다. 기능을 꺼 두면 미들웨어 스택에 아무것도 추가하지 않습니다.

::: warning 정적 파일은 PHP를 거치지 않습니다
웹 서버가 `public/`에서 직접 반환하는 파일은 Laravel에 들어오지 않으므로 이 헤더를 받을 수 없습니다. 이런 파일은 웹 서버 설정이나 CDN 등 엣지 계층에서 보호하세요. 이 기능은 앱을 거치는 모든 응답에 적용됩니다.
:::

## 명시적 robots 값도 덮어쓰는 이유 {#why-it-overrides-an-explicit-robots-value}

Rankbeam의 다른 기능에서는 저장된 명시적 값이 우선합니다. 이것이 우선순위 체인의 핵심입니다. 색인 방지는 의도적으로 둔 유일한 예외이며 명시적 계층보다 *위에* 있습니다. 여기서 위험은 한 방향으로만 발생하기 때문입니다.

- 스테이징 데이터베이스는 보통 프로덕션 복제본이므로, `index,follow`를 저장한 페이지는 스테이징에서도 같은 지시문으로 색인을 요청하게 됩니다.
- **스테이징을 잘못 색인하면 큰 문제가 생기지만, 스테이징에 잘못 `noindex`를 적용해도 실질적인 영향은 없습니다.** 따라서 애초에 색인을 원하지 않는 환경에서는 저장된 값이 넘을 수 없는 보호선을 적용합니다.

## 활성화 {#enabling-it}

이 기능은 **꺼진 상태**로 제공됩니다. 따라서 패키지를 설치하거나 업그레이드해도 동의 없이 비프로덕션 환경의 렌더링이 바뀌지 않습니다. 리졸버의 [`blank_is_unset`](/ko/concepts/resolver-precedence) 및 생성된 OG 이미지와 마찬가지로, 선택적으로 켜기 전까지 출력이 바이트 단위로 동일합니다. 한 줄로 활성화할 수 있습니다.

```dotenv
SEO_INDEXING_GUARD=true
```

기본 허용 목록에서는 `production`에 영향을 주지 않으므로 공유 설정에서 켜 두어도 됩니다. 색인을 허용하려는 모든 환경이 목록에 들어 있는지 확인하세요. 사용을 **강력히 권장**하며, Core 4에서 기본 활성화할 후보 기능입니다.

마찬가지로 한 줄로 끌 수 있습니다.

```dotenv
SEO_INDEXING_GUARD=false
```

## 색인을 허용할 환경 선택 {#choosing-which-environments-may-index}

기본적으로 `production`만 허용됩니다. 쉼표로 구분한 환경 변수로 목록을 재정의할 수 있습니다.

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

또는 `config/seo.php`에서 설정하세요.

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

항목은 `Str::is()`로 일치 여부를 확인하므로 **와일드카드**를 사용할 수 있습니다. `'prod*'`는 `production`와 `prod-eu`에 일치합니다.

```php
'allowed_environments' => ['prod*'],
```

**빈** 목록은 어떤 환경에서도 색인을 허용하지 않는다는 뜻이며, 모든 환경에서 색인 방지가 활성화됩니다. 이는 안전한 방향으로 동작하는 설정입니다. 다만 `SEO_INDEXING_GUARD_ALLOWED` 환경 변수 값이 비어 있거나 공백뿐이면 `['production']`로 폴백합니다. 오타 때문에 프로덕션이 조용히 색인 제외되는 일을 방지하기 위해서입니다. 정말 “모든 환경”을 원한다면 설정에 `[]`를 명시하세요.

## 확인 {#verifying-it}

`seo:audit`는 배너를 표시하고, `--json`에는 기계가 읽을 수 있는 상태를 포함합니다.

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

보호되는 환경에서 제공하거나 생성하는 `robots.txt`는 다음과 같습니다.

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## 범위 {#scope}

이 기능은 **색인 지시문**, 즉 `robots` 메타 태그, `X-Robots-Tag` 헤더, `robots.txt`를 제어합니다. 제목, 설명, canonical, 스키마는 건드리지 않습니다. 또한 [robots 렌더링 정책](/ko/concepts/resolver-precedence)(`seo.robots.emit_default`)과 독립적입니다. `noindex,nofollow`가 사이트 기본값과 다르므로 항상 태그로 출력됩니다.

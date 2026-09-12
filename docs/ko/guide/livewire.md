---
description: "Livewire 앱에서 프레임워크에 종속되지 않는 Rankbeam의 @seo 지시문를 사용하세요. head에 일반 HTML을 출력하므로 전체 페이지 컴포넌트와 Blade 레이아웃에서 동일하게 동작합니다."
---

# Livewire {#livewire}

`@seo` Blade 지시문는 특정 프레임워크에 종속되지 않습니다. `<head>`에 일반 HTML을 출력하므로 모든 Livewire 앱에서 Blade와 같은 방식으로 사용할 수 있습니다.

## 최초 전체 페이지 렌더링 {#initial-full-page-render}

**전체 페이지 Livewire 컴포넌트**(컴포넌트를 반환하는 라우트)나 Livewire 컴포넌트를 감싸는 Blade 레이아웃에서 `@seo`는 [Blade 가이드](/ko/guide/blade)와 동일하게 동작합니다.

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

첫 HTTP 응답에는 크롤러가 볼 수 있는 전체 head가 포함됩니다. 제목, 설명, canonical, Open Graph, Twitter, JSON-LD가 모두 들어갑니다. 크롤러와 소셜 스크레이퍼가 확인하는 경로이며, 이 경로의 출력은 완전하고 올바릅니다.

## `wire:navigate` 사용 시 주의점 {#the-wire-navigate-caveat}

Livewire의 [`wire:navigate`](https://livewire.laravel.com/docs/navigate)는 링크 클릭을 SPA 방식의 방문으로 바꿉니다. 이때 Livewire는 `<body>`를 교체하고 **`<head>`를 병합**합니다. 다만 SEO 패키지 관점에서는 중요한 비대칭 동작이 있습니다.

- **`<title>` 태그와 `<meta>`/`<link>`**는 새 페이지의 head에서 병합되므로, 결정된 제목과 메타데이터는 일반적으로 갱신됩니다.
- **`<script>`는 제거할 수 없는 리소스로 취급됩니다.** Livewire는 이전에 본 모든 `<script>`를 유지해, 다시 실행될 때 JavaScript가 망가지지 않도록 합니다. 따라서 **JSON-LD `<script>` 블록이 누적됩니다**. 게시물 세 개를 방문하면 세 게시물의 스키마가 head에 동시에 남고, 구조화된 데이터를 읽는 도구가 잘못된 엔티티 또는 여러 엔티티를 보게 됩니다.

이를 정리할 수 있도록 렌더러는 출력하는 **모든 JSON-LD 스크립트에 표시를 붙입니다**.

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## JSON-LD 정리 코드 추가 {#ship-the-json-ld-cleanup}

다음 코드를 한 번 추가하세요. 예를 들어 루트 레이아웃의 `@livewireScripts` 뒤에 넣을 수 있습니다. `wire:navigate`가 발생할 때마다 **현재 페이지의** 스키마만 남기고 오래된 스키마를 제거합니다.

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        // The page we are now on. data-seo-url is the canonical (query-stripped),
        // so compare on the query-stripped location.
        const here = window.location.href.split('#')[0].split('?')[0]

        // Keep only the LAST schema for this page; remove every other-URL
        // (stale) script AND same-URL duplicates Livewire re-adds when a page is
        // revisited — including clearing a lone stale script when this page has
        // none. Iterate from the end so the freshest copy is the one kept.
        const scripts = [...document.querySelectorAll('script[data-seo-schema]')]
        let kept = false
        for (let i = scripts.length - 1; i >= 0; i--) {
            const url = (scripts[i].getAttribute('data-seo-url') || '').split('?')[0]
            if (url === here && !kept) { kept = true; continue }
            scripts[i].remove()
        }
    })
</script>
```

렌더러가 이미 출력하는 `data-seo-schema` 표시와 URL별 ID만 사용하므로 페이지마다 따로 연결할 필요가 없습니다.

::: warning 마지막에 추가된 스크립트가 아니라 현재 URL과 비교하세요
이전 예제는 스키마 스크립트가 두 개 미만이면 종료하고, *마지막에 추가된* 스크립트를 현재 페이지의 것으로 취급했습니다. 이 방식은 JSON-LD가 **있는** 페이지에서 **없는** 페이지로 이동할 때 오래된 스키마를 head에 남깁니다. 이전 스크립트 하나만 있으므로 조기 종료해 그대로 유지하기 때문입니다. 또 페이지를 다시 방문할 때 Livewire가 추가하는 **같은 URL의 중복 스크립트**도 제거하지 못합니다. 각 `data-seo-url` 값을 `window.location` 값과 비교하고 일치하는 항목 중 **마지막 것**만 남기면 모든 경우에 오래된 스키마와 중복을 함께 제거할 수 있습니다. `rankbeam-examples` Livewire 앱과 브라우저 테스트가 이를 검증합니다.
:::

::: tip SPA 탐색의 단일 메타 태그
Livewire의 head 병합은 대부분의 경우 단일 `<meta>`/`<link>` 태그가 오래된 상태로 남지 않도록 처리하지만, 정확한 동작은 Livewire 버전과 레이아웃 구조에 따라 달라집니다. 크롤러 메타데이터의 정확성이 중요한 페이지에서는 **전체 페이지 새로고침**(`wire:navigate`가 없는 일반 링크)을 사용하거나 **서버에서 페이지를 렌더링**해 첫 HTTP 응답을 기준으로 삼으세요. [`rankbeam-examples`](https://github.com/rankbeam)의 Livewire 앱은 실제 `wire:navigate` 흐름을 브라우저에서 실행해 이를 확인합니다.
:::

## Filament {#filament}

Filament 내부에서도 Livewire를 사용하지만, Filament는 **관리자용 콘텐츠 작성 화면**입니다. `seo_meta`를 편집할 뿐 공개 프런트엔드의 head를 렌더링하지 않습니다. [Filament 가이드](/ko/guide/filament)를 참고하세요. 이 페이지의 내용은 관리 패널에는 적용되지 않습니다.

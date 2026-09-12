---
description: "모든 프런트엔드 스택의 head가 Rankbeam SEO 데이터를 렌더링할 때 충족해야 하는 기준 체크리스트입니다. 코어 렌더러 테스트와 참조 앱의 기준 문서입니다."
---

# 렌더링 계약 {#the-rendering-contract}

모든 프런트엔드 스택의 `<head>`가 Rankbeam SEO 데이터를 렌더링할 때 충족해야 하는 **유일한 기준 체크리스트**입니다. 다음 항목의 기준 문서입니다.

- 코어의 렌더러 출력 형태 단위 테스트(`tests/Unit/Services/RenderingContractTest.php`) — 패키지 CI가 담당하는 빠르고 프레임워크에 의존하지 않는 검증.
- `rankbeam-examples`의 스택별 참조 앱(Blade, Inertia + Vue / React / Svelte, Livewire) — 브라우저 및 SSR 테스트로 실제 DOM에서 같은 조건을 검증.
- 프레임워크 가이드(Blade, Inertia 및 JSON, Livewire) — 이 계약을 위반하는 구현 방법을 문서화해서는 안 됩니다.

스택이 어떤 조항을 충족하지 못한다면 **결함 또는 문서화된 제한 사항**이며, 계약을 완화할 이유가 아닙니다. 데이터 계층(`SEOResolver` → 불변 `SEOData` → `TagRenderer`)은 프레임워크에 종속되지 않습니다. 스택마다 다른 것은 *최종 데이터가 DOM에 도달하고 클라이언트 탐색 후에도 유지되며 크롤러에 보이는 방식*뿐이며, 이 계약은 바로 그 부분을 명시합니다.

> 이 규격은 독립적인 설계 검토를 거쳐 강화되었습니다.
> 실질적인 변경이 있을 때만 다시 검토하세요.

---

## 1. 값 — 계약을 충족하는 `<head>`의 내용 {#_1-values-—-what-a-compliant-head-contains}

### 제목, 설명, 표준 URL {#title-description-canonical}

- *최종 결정된* 제목을 담은 **`<title>`가 정확히 하나** 있어야 합니다. 접미사를 중복으로 붙이지 않습니다. 리졸버는 `seo.title_suffix`를 한 번만 붙이고 이미 그 접미사로 끝나는 제목은 보호합니다.
- 설명 값이 결정되었을 때만 **메타 설명 하나**를 출력합니다. 빈 태그는 출력하지 않습니다.
- **`<link rel="canonical">` 하나**.

### Robots {#robots}

- **지시문이 사이트 기본값과 다를 때만** `<meta name="robots">`를 출력합니다. 불필요한 `index,follow`는 잡음이며, 태그가 *없는 것*을 크롤러는 정확히 `index,follow`로 해석합니다. 비교할 때 공백은 무시하며(`index, follow` ≡ `index,follow`), 기본값과 다른 지시문은 **그대로** 출력합니다. `seo.robots.emit_default = true`는 태그 출력을 강제합니다.
- 결정론적인 **고급 지시문**을 지원합니다: `noindex`, `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`, `max-video-preview`, `notranslate`, `unavailable_after`. 결정된 문자열 값이며 **우선순위는 리졸버 체인**(전역 → 라우트 → 모델 → 명시적 값)을 따릅니다. 같은 입력 ⇒ 같은 출력.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`, `og:locale`.
- `article:*`(`published_time`, `modified_time`, `author`, `section`, `tag`)는 **`og:type === 'article'`이고 실제 값이 있을 때만** 출력합니다. 값을 만들거나 글이 아닌 페이지에 출력하지 않습니다.
- 크기를 **알 때** `og:image`와 함께 `og:image:width` / `og:image:height` / `og:image:alt` 및 `og:image:type`를 출력합니다. 여러 이미지는 **그룹화**하며 각 `og:image` 바로 뒤에 해당 이미지의 크기/대체 텍스트/유형 속성이 와야 합니다.

### Twitter 카드 {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`, 그리고 이미지 대체 텍스트를 알 때 `twitter:image:alt`.
- `twitter:site`와 `twitter:creator`는 **선택적이며 서로 독립적**입니다. 하나만 존재할 수 있으며 다른 하나에서 값을 만들어 내지 않습니다.

### hreflang 및 로케일 {#hreflang-locale}

- Hreflang은 모델의 `getSEOAlternates()` 훅을 통한 전용 리졸버 경로를 가집니다.
- hreflang 대체 페이지가 있다면 **절대 URL, 정규화된 값, 언어별 고유 항목**이어야 하며 데이터가 완전한 곳에서는 상호 참조해야 합니다. `x-default`는 설정된 경우에만 출력합니다.
- `og:locale:alternate`는 실제 소셜 버전이 있는 로케일**만** 반영합니다. `en-US` → `en_US`로 매핑한 형태를 비교하며 문자열 자체가 같아야 한다고 요구하지 않습니다.
- `<html lang>`는 최종 로케일과 일치해야 합니다. `<html>` 요소는 *앱*이 출력하지만 이 조항은 계약에 포함됩니다.

### 페이지별 JSON-LD {#per-page-json-ld}

- 파싱 가능하고 `</script>`에 안전해야 합니다. 페이로드를 `JSON_HEX_TAG`로 인코딩해 어떤 값도 script 요소를 조기 종료하지 못하게 하며 저장형 XSS를 방지합니다.
- **여러 `<script>` 블록 또는 통합된 `@graph`** 모두 허용합니다.
- 안정적인 `@id`는 **엔터티가 실제 연결되는 곳에만** 사용합니다(Organization ↔ WebSite ↔ WebPage). 독립 노드에는 안정적인 `@id`가 필수가 *아닙니다*.

---

## 2. 정규화 및 불변 조건 {#_2-normalization-invariants}

- `canonical`, `og:url`, `og:image`, `twitter:image`는 **절대 `http(s)` URL**입니다. **빈 태그나 null 태그는 DOM에 도달하지 않습니다**.
- **`canonical`와 `og:url`는 반드시 같은 정규화 URL로 결정되어야 합니다.** 불일치는 경고가 아닌 **명확한 실패**입니다.
- 전체 출력에서 **표준 URL 정규화 정책이 일관적**이어야 합니다. 스킴 / 호스트 / 포트 / 경로 대소문자 / 쿼리 허용 목록 / 끝 슬래시를 매번 같은 방식으로 처리합니다. 색인 가능한 페이지는 **자기 자신을 참조**하며, `noindex` 페이지가 다른 페이지의 표준 URL 전략을 상속하지 **않습니다**.
- **출력 위치별 이스케이프**: HTML 속성, 텍스트, JSON마다 알맞은 인코더를 사용합니다. 검증은 **바이트가 아닌 디코딩된 의미상의 값**을 비교합니다.
- **렌더러 간 일치는 바이트 단위가 아닌 의미상의 일치입니다.** *정규화 후* `render()`(HTML) ≡ `toArray()` ≡ `toInertiaHead()`여야 합니다. 세 표현은 태그 순서와 형태가 달라도 됩니다. 단일 및 반복 가능한 속성 규칙은 명시적입니다(`og:title` 하나, `article:tag` 여러 개).
- **태그 소유권**: 클라이언트 렌더러는 관계없는 앱 소유 태그를 삭제하지 않고 키가 있는 *패키지 소유* 태그를 교체합니다(§4 참고).

---

## 3. 동작 — 클라이언트 탐색 {#_3-behaviour-—-client-side-navigation}

Inertia 방문 또는 Livewire `wire:navigate` 이후마다 다음을 충족해야 합니다.

- **단일 항목은 각각 정확히 하나**(`<title>`, 설명, 표준 URL, 각 `og:*`/`twitter:*`)이며 **오래된 항목이 없어야 합니다**.
- **JSON-LD가 누적되지 않습니다**. 이전 페이지의 스키마 위에 쌓지 않고 제거합니다. Livewire는 `<script>`를 제거 불가능한 자산으로 다루므로 스키마 script에 `data-seo-schema`와 URL별 id를 붙이고 `livewire:navigated`에서 이전 페이지의 script를 제거합니다. Livewire 가이드를 참고하세요.
- **메타데이터가 많은 페이지에서 최소한의 페이지로 이동하면 추가 태그를 제거합니다**. 최소 페이지에 이전 페이지의 설명/OG/스키마가 남지 않습니다.
- **하이드레이션 경고가 없어야 하며**, 하이드레이션 전후 메타데이터가 의미상 같아야 합니다.

---

## 4. Inertia head-key (태그 소유권) {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()`는 모든 meta/link 항목에 안정적인 **`head-key`**를 지정합니다. Inertia는 이 속성으로 head 요소 중복을 제거합니다. 페이지의 `<Head>` 태그가 레이아웃 태그와 같은 `head-key`를 가지면 중복 추가 대신 기존 태그를 *교체*합니다.

- 기본 키 = meta는 `name ?? property`, link는 `rel`.
- 각 항목이 고유한 키를 유지하도록 **반복 가능한 태그를 구별합니다**: `article:tag` → `article:tag`, `article:tag:1` 등; hreflang → `alternate:en-US`, `alternate:fr-FR`.

템플릿에서는 Vue의 `:key`가 *아닌* **`:head-key`**로 바인딩하세요. Vue 키는 별개의 `v-for` 조정 키이며 Inertia head 중복 제거에는 아무 역할도 하지 않습니다.

---

## 5. 크롤러 가시성 (명시적 모드) {#_5-crawler-visibility-explicit-modes}

- **SSR / 사전 렌더링**은 **원시 HTTP HTML**에서 전체 계약을 충족해야 합니다. 하이드레이션된 DOM과 별도로 JS를 끈 상태에서 테스트합니다.
- **CSR만으로 크롤러 요구 사항을 충족한다고 주장할 수 없습니다.** 기본 비SSR Inertia는 *클라이언트에서* 메타 태그를 삽입하므로 크롤러가 가져오는 초기 HTML에는 SEO 메타 태그가 없습니다. 이 사실은 숨기지 않고 문서화합니다. **크롤러가 메타 태그를 보려면 Inertia SSR 또는 사전 렌더링이 필요하며**, 크롤러용 JSON-LD도 서버에서 렌더링해야 합니다.

---

## 6. 범위 밖 / 비목표 {#_6-out-of-scope-non-goals}

- **렌더러가 아닌 앱의 책임**: `charset`, `viewport`, 파비콘. `<meta charset>`는 비ASCII 메타데이터보다 먼저 와야 하므로 해당 head 요소의 순서는 앱이 관리합니다.
- **e2e는 출력 결과만 검증합니다.** Google 색인 생성, 표준 URL *선택*, 리치 결과 자격, 순위를 검증하지 **않으며**, 원격 이미지의 MIME/가용성도 검증하지 **않습니다**. 이 항목들은 선택적 연동/HTTP 테스트에서 다루며 브라우저 매트릭스에 넣지 않습니다.

---

## 7. 준수 상태 {#_7-conformance-status}

현재 각 조항을 검증하는 수단입니다. **단위** = `RenderingContractTest`(코어, 패키지 CI). **브라우저/SSR** = `rankbeam-examples`(예약된 매트릭스). **앱** = 호스트 애플리케이션의 책임. **계획됨** = 계약상 목표지만 `SEOData`에 아직 데이터가 모델링되지 않아 렌더러가 안전한 부분집합을 출력합니다.

| 조항 | 상태 |
|---|---|
| 최종 `<title>` 정확히 하나, 접미사 중복 없음 | **단위** + 브라우저 |
| 존재할 때만 메타 설명 출력 | **단위** + 브라우저 |
| `<link rel="canonical">` 하나, 빈 값 없음 | **단위** + 브라우저 |
| 기본값과 다를 때만 robots를 그대로 출력; `emit_default` 토글 | **단위** + 브라우저 |
| 리졸버 우선순위를 통한 고급 robots 지시문 | **단위**(리졸버) |
| `og:title/description/type/url/site_name/locale`; 로케일 `en-US`→`en_US` | **단위** + 브라우저 |
| `og:type=article`이고 실제 값일 때만 `article:*` | **단위** + 브라우저 |
| `og:image` 존재 및 절대 URL | **단위** + 브라우저 |
| `og:image:width/height/alt`, `og:image:type`, 여러 이미지 그룹화 | **계획됨** — `SEOData`는 단일 `ogImage` 문자열을 보유하며 크기/대체 텍스트/유형은 아직 모델링되지 않았습니다. 렌더러는 절대 `og:image` 하나를 출력합니다. |
| `twitter:card/title/description/image`; `site`/`creator` 독립성 | **단위** + 브라우저 |
| `twitter:image:alt` | **계획됨** — 이미지 대체 텍스트 필드가 아직 모델링되지 않았습니다. |
| hreflang 절대 URL, 언어별 고유성 | **단위** + 브라우저 |
| hreflang 상호 참조, 설정 시 `x-default` | 브라우저(데이터에 따라 다름) |
| `og:locale:alternate`가 실제 소셜 버전 반영 | **계획됨** — 로케일별 소셜 버전 맵이 아직 모델링되지 않았습니다. |
| `<html lang>` 일치 | **앱**(+ 브라우저 검증) |
| JSON-LD 파싱 가능 및 `</script>` 안전성 | **단위** + 브라우저 |
| 여러 script 또는 `@graph`; 엔터티 연결 시 안정적인 `@id` | **단위**(merchant 그래프) + 브라우저 |
| 절대 URL; 빈/null 태그 없음 | **단위** + 브라우저 |
| `canonical` ≡ `og:url`(불일치 시 명확한 실패) | **단위** + 브라우저 |
| 일관된 표준 URL 정규화; 자기 참조; noindex 격리 | 브라우저 |
| 출력 위치별 이스케이프; 디코딩 후 의미상의 일치 | **단위** |
| 렌더러 간 의미상의 일치(`render()` ≡ `toArray()` ≡ `toInertiaHead()`) | **단위** |
| 안정적인 Inertia `head-key` 및 반복 항목 구별 | **단위** + 브라우저 |
| 클라이언트 탐색: 단일 항목 하나, 오래된 항목 없음, JSON-LD 누적 없음, 제거 | 브라우저 — 렌더러가 정리에 필요한 `data-seo-schema` 훅 제공 |
| 하이드레이션 경고 없음; 전후 일치 | 브라우저 |
| SSR 원시 HTML이 전체 계약 충족; CSR만으로는 미준수임을 문서화 | 브라우저 + 문서 |

**계획된 조항**은 의도적으로 문서화된 미구현 부분입니다. 계약은 지속적인 목표이며, 이 항목들은 향후 작업에서 추가할 하위 호환 확장입니다. 새로운 `SEOData` 필드/열이 필요하므로 SemVer 마이너 변경입니다. 현재 렌더러는 안전한 부분집합을 출력하며 보유하지 않은 값을 만들어 내지 않습니다.

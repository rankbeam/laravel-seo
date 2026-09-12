---
description: "Rankbeam은 여섯 계층을 우선순위에 따라 병합해 각 SEO 값을 결정합니다. 상위 계층이 우선하며 null은 기존 값을 덮어쓰지 않으므로 모든 페이지에 적절한 값이 렌더링됩니다."
---

# 리졸버 우선순위 {#resolver-precedence}

최종 SEO 값인 제목, 설명, canonical, robots, 이미지는 모두 `SEOResolver`가 **여섯 계층**을 병합해 결정합니다. 상위 계층이 우선하고 `null`은 하위 계층의 값을 덮어쓰지 않으므로, 모든 페이지가 항상 적절한 값을 렌더링합니다.

## 여섯 계층 {#the-six-layers}

가장 낮은 계층(항상 존재)부터 가장 높은 계층(항상 우선)까지의 순서입니다.

| # | 계층 | 출처 | 일반적인 용도 |
|---|---|---|---|
| 1 | **사이트 설정** | `config/seo.php`(`site_name`, `title_suffix`, `default_og_image`, `default_robots` 등) | 브랜드 전체 기본값 |
| 2 | **전역 DB 기본값** | 모델 유형이 없는 `seo_defaults` 행 | 배포 없이 편집하는 사이트 전체 기본값 |
| 3 | **모델 유형 기본값** | 모델 클래스로 범위를 지정한 `seo_defaults` 행 | “모든 상품에 이 OG 이미지 사용” |
| 4 | **라우트 기본값** | 라우트 이름으로 범위를 지정한 `seo_defaults` 행 | 모델이 없는 정적 페이지(`home`, `contact`) |
| 5 | **계산된 값** | 모델 자체 속성에서 유도 | 제목은 `title`, 설명은 `excerpt`/`body` 등에서 대체값 계산 |
| 6 | **명시적 값** | 모델의 `seo_meta` 행(`saveSEO()`) | 편집자가 직접 설정한 값 |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

결과는 모든 렌더러(Blade, 배열, Inertia)가 사용하는 불변 `SEOData` 값 객체입니다.

## 계산된 대체값(계층 5) {#computed-fallbacks-layer-5}

명시적 값이 없으면 리졸버가 모델에서 값을 유도합니다.

- **제목** — 모델의 `title`/`name` 속성.
- **설명** — `seo.computed.description_fields`에 지정된 속성 중 의미 있는 텍스트를 가진 첫 번째 속성. 기본 탐색 순서는 `excerpt`, `summary`, `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`, `article`입니다. HTML을 제거하고 엔티티를 디코딩한 다음 단어 경계에서 텍스트를 자릅니다(`seo.computed.description_max_length`, 기본값 160, 말줄임표 없음).
- **Robots** — 모델의 `getSEORobots()` 훅 또는 `is_indexable` 속성에서 가져옵니다. [robots와 색인 가능 여부 제어](#controlling-robots-and-indexability)를 참고하세요.
- **URL에서 유도한 값** — `getUrlForSEO()`에서 canonical과 `og:url` 값을 가져옵니다.

## robots와 색인 가능 여부 제어 {#controlling-robots-and-indexability}

모델별 `noindex`는 기본 제공되며 별도 패키지나 번거로운 컬럼 설정이 필요하지 않습니다. `HasSEO` 트레이트는 선택 사항인 robots 메서드를 *선언하지* 않기 때문에 놓치기 쉽지만, 리졸버는 이미 다음 세 가지 출처를 우선순위 순으로 지원합니다.

| 우선순위 | 출처 | 예제 |
|---|---|---|
| 1 | **명시적 `seo_meta.robots`** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | 모델의 **`getSEORobots(): ?string` 훅** | `'noindex, nofollow'`를 반환하거나, 다음 출처를 사용하려면 `null` 반환 |
| 3 | **`is_indexable` 속성**(컬럼 또는 접근자) | falsy ⇒ `noindex, nofollow`; truthy ⇒ `index, follow` |

```php
class Page extends Model
{
    use HasSEO;

    // Option A: let the resolver derive robots from a boolean flag.
    //   Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));

    // Option B: compute it from your own state.
    public function getSEORobots(): ?string
    {
        return $this->status === 'draft' ? 'noindex, nofollow' : null;
    }
}

// Option C: set it explicitly per page (wins over A and B).
$page->saveSEO(['robots' => 'noindex, follow']);
```

### 실제 렌더링되는 내용 {#what-actually-renders}

결정된 지시문은 `<head>`에 도달하기 전에 **출력 정책**을 거칩니다. `<meta name="robots">` 태그는 **지시문이 `default_robots`와 다를 때만** 출력됩니다. 기본값은 `index,follow`입니다. 따라서 다음과 같이 동작합니다.

- **색인 가능한** 페이지(결정된 값이 `index, follow`)는 **robots 태그를 출력하지 않습니다**. 태그가 없으면 크롤러는 정확히 index,follow로 해석합니다.
- **색인 불가능한** 페이지는 `<meta name="robots" content="noindex, nofollow">`를 출력합니다.
- 기본값과 다른 지시문(`noindex`, `max-snippet:-1`, `unavailable_after` 등)은 입력한 공백까지 **그대로** 출력합니다.

태그를 항상 렌더링하려면 `seo.robots.emit_default = true`를 설정하세요. 자세한 내용은 [robots 렌더링 정책](/ko/reference/configuration#robots-rendering-policy)을 참고하세요.

## 값 결정 후 적용하는 정책 {#policies-applied-after-resolution}

어느 계층에서 값이 나왔는지와 관계없이 다음 정책을 적용합니다.

- **제목 접미사** — 최종 제목이 이미 `title_suffix`로 끝나지 않으면 이를 덧붙입니다. 라우트 기본값 템플릿에 브랜드가 이미 있다면 템플릿 끝에 접미사를 넣어 “Brand — X | Brand”와 같은 중복을 피하세요.
- **Canonical 쿼리 제거** — 모델 URL이나 현재 URL에서 *유도한* canonical은 쿼리 문자열을 제거합니다. 단, [`canonical.query_whitelist`](/ko/reference/configuration#canonical-urls)에 있는 키는 유지합니다. 예를 들어 페이지네이션 아카이브의 `page`가 이에 해당합니다. *명시적으로 설정한* canonical은 그대로 보존합니다.
- **소셜 이미지 절대 URL** — 저장된 값이 상대 경로여도 `og:image`와 `twitter:image`는 항상 절대 URL로 출력합니다. Open Graph 사양의 요구 사항입니다.

## 어떤 계층이 적용됐는지 확인 {#inspecting-which-layer-won}

[Filament 패키지](/ko/guide/filament)는 필드마다 출처를 표시합니다. 한국어 UI의 실제 표시는 수동 입력 / 콘텐츠 대체값 / 모델 유형 기본값 / 전역 기본값 / 사이트 설정 / URL에서 파생입니다. 코드에서는 `SEOWarningEvaluator`가 같은 수동 값과 대체값의 구분을 제공하므로, 자체 관리 화면에 표시기를 만들 수 있습니다.

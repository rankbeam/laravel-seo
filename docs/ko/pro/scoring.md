---
description: "Pro 스캔의 0–100 SEO 점수는 결정론적이며 전체 과정을 감사할 수 있습니다. 감점 하나하나가 스캔 문제 하나에 연결되므로 같은 문제는 항상 같은 수치를 만듭니다."
---

# SEO 점수 — 투명하고 버전이 지정된 Pro 기능 {#the-seo-score-—-transparent-versioned-pro-owned}

Pro 스캔은 각 페이지에 **0-100 SEO 점수**를 부여합니다. RankMath나 Yoast에서 옮겨 오는 사용자가 찾는 단일 수치입니다. 계산 과정을 알 수 없는 등급과 달리 **전체 과정을 감사할 수 있습니다**. 모든 감점이 정확히 하나의 [스캔 문제](/ko/pro/scan-issues)로 추적되며 같은 문제 집합은 항상 같은 수치를 만듭니다.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip 하나의 수치, 하나의 담당 패키지
수치 점수는 **Pro** 기능입니다. Pro의 `seo_scan_results` 레코드에 저장되며 코어의 `seo_meta`로 되돌아가지 않습니다(기존 `seo_score` 열은 Core 3에서 제거되었습니다). 무료 코어 [`seo:audit`](/ko/guide/audit)는 **수치 없이** 페이지별 **통과 / 경고 / 실패**를 보고합니다. 점수는 유료 추가 기능입니다.
:::

## 평가 기준 {#the-rubric}

점수는 **공개되고 버전이 지정된 평가 기준**인 `Rankbeam\Seo\Pro\Scanning\ScoreRubric`로 계산합니다. 반영할 문제 코드의 명시적 **허용 목록**과 고정된 **심각도별 감점**으로 정의됩니다.

| 심각도 | 감점 | 의미 |
|---|---|---|
| `critical` | **−40** | 이 평가 기준에서 영향이 큰 발견 사항. |
| `warning` | **−15** | 조만간 살펴볼 발견 사항. |
| `notice` | **−5** | 개선하면 좋은 항목. |

각 코드의 심각도는 단일 기준 데이터인 [문제 레지스트리](/ko/pro/scan-issues)에서 직접 읽습니다. 평가 기준이 다시 도출하지 않습니다. 코드마다 심각도가 1:1로 정해져 있어 점수의 결정론적 특성을 유지합니다.

### 점수에 반영하는 항목 {#what-the-score-counts}

Rankbeam의 제품 평가 기준이 선택한 결정론적 검사이며, 편집 관점의 해석이 필요한 휴리스틱도 포함합니다. critical은 40점, warning은 15점, notice는 5점을 차감합니다. 점수는 검색 성과 예측이 아닙니다.

| 코드 | 심각도 | 감점 |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

메타데이터 코드는 모델 스캔에서, 렌더링/네트워크 코드는 URL 스캔에서만 탐지하므로([실행 클래스](/ko/pro/scan-issues#execution-classes) 참고), **모델** 대상의 점수는 메타데이터 검사를, **URL** 대상의 점수는 렌더링된 페이지를 반영합니다. 모델 스캔의 100점은 “메타데이터 결함 없음”을 뜻하며 “렌더링된 페이지가 완벽함”을 뜻하지 않습니다. 후자는 URL을 스캔해 확인하세요.

### 의도적으로 점수에 반영하지 않는 항목 {#what-the-score-deliberately-does-not-count}

다음 레지스트리 코드는 의도적으로 제외합니다. 제외 목록도 계약의 일부이며, 모든 레지스트리 코드가 점수에 반영되거나 이 목록에 있는지 테스트로 확인합니다.

| 코드 | 제외 이유 |
|---|---|
| `missing_focus_keyword` | **권고.** 선택적으로 활성화하는 `seo.keywords.enabled` 흐름에 종속됩니다. 포커스 키워드를 도입하지 않았다는 이유로 점수가 낮아져서는 안 되며 점수가 설정 플래그에 의존해서도 안 됩니다. |
| `noindex_page` | **정보.** `noindex` 상태는 의도적 선택이며 메타데이터 품질 결함이 아닙니다. “자기 자신을 가리키는 표준 URL과 noindex의 조합” 휴리스틱은 대신 `noindex_warning`로 반영합니다. |
| `multiple_h1` | **정보.** Google은 여러 H1을 허용하므로 H1 여러 개에 대한 감점은 없습니다. |
| `blocked_url` | **증거 부족.** SsrfGuard가 요청을 거부해 페이지를 검사하지 못했습니다. 페이지 결함이 아닙니다. |
| `canonical_target_blocked` | **증거 부족.** 표준 URL 대상을 검증할 수 없었습니다. 페이지 결함이 아닙니다. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **현재는 권고.** 이 Pro hreflang 코드는 스캔에 표시됩니다. 무료 감사에는 자체 hreflang 코드가 있지만 아직 점수를 바꾸지 않습니다. 반영하려면 `VERSION`를 올려야 합니다. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **권고.** 언어 검사는 이 평가 기준에서 제외합니다. |
| `hreflang_not_reciprocal` | **권고.** 선택적 상호 참조 검사로 점수에 반영하지 않습니다. |
| `hreflang_target_unverified` | **증거 부족.** 상호 참조를 검증할 수 없었습니다. |
| `aeo_missing_author`, `aeo_article_missing_date` | **권고.** 답변 준비도(AEO) 신호입니다. 스캔과 무료 감사에서 글의 작성자 엔터티 또는 게시 날짜 누락을 표시하지만 점수는 바꾸지 않습니다(`VERSION`를 올려야 함). |

키워드 밀도, 파워 워드, 나머지 [온페이지 체크리스트](/ko/pro/on-page-checklist) 항목은 점수에 전혀 들어가지 않습니다. 레지스트리 코드가 아닌 별도의 통과/경고/실패 목록으로 제공하는 권고성 검사입니다.

## 버전 관리 — 과거 점수는 모르게 바뀌지 않습니다 {#versioning-—-historical-scores-never-silently-change}

저장된 모든 점수에는 계산에 사용한 `ScoreRubric::VERSION` 값이 기록됩니다(`rubric_version`). 따라서 다음 두 원칙이 적용됩니다.

- **새로운** 문제 코드는 의도적으로 허용 목록에 추가하기 전에는 점수에 **아무 영향도 주지 않습니다**. 새 검사를 출시해도 저장된 점수가 소급 변경되지 않습니다. 허용 목록이나 가중치 변경 자체가 평가 기준 변경이므로 버전을 올립니다.
- 점수는 **저장되며 조회할 때 다시 계산하지 않습니다**. 지난주에 본 수치를 오늘도 볼 수 있고, 그 수치를 설명하는 평가 기준도 함께 확인할 수 있습니다.

## 저장 위치 {#where-it-s-stored}

각 스캔은 `seo_scan_results`에 대상당 한 행을 upsert합니다.

| 열 | 저장 내용 |
|---|---|
| `scannable_type` / `scannable_id` | 점수를 계산한 모델(URL 대상이면 null). |
| `url` | 점수를 계산한 URL. |
| `score` | 0-100 수치. |
| `rubric_version` | 계산에 사용한 평가 기준. |
| `penalty_total` | 하한 0을 적용하기 **전**의 감점 합계. |
| `scored_issues` | 수치에 영향을 준 문제 수. |
| `breakdown` | `[{code, severity, penalty}, …]` — 전체 추적 정보. |
| `keywords_enabled` | 스캔 당시 `seo.keywords.enabled` 상태(투명성을 위해 기록하며 점수는 이에 의존하지 않음). |
| `scan_run_id` | 점수를 계산한 실행(실행 정리 시 삭제하지 않고 null로 설정 — 점수는 실행 이력이 아닌 현재 상태). |
| `scored_at` | 점수 계산 시각. |

## 점수 읽기 {#reading-the-score}

**헤드리스** — 모델의 최근 점수:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` 명령은 요약에 **사이트 평균 점수**를 출력합니다. Filament 대시보드는 등급에 따라 색상을 적용한 주요 “평균 SEO 점수” 통계로 표시합니다.

### 등급 구간 {#grade-bands}

수치에서 도출한 표시용 문자 등급입니다. 기준이 되는 것은 수치입니다.

| 점수 | 등급 |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## 출시 상태 검토 신호 (`noindex_warning`) {#the-shipping-signal-noindex-warning}

페이지가 `noindex`와 **자기 참조 표준 URL**(자신의 URL을 지정하는 표준 URL)을 함께 사용하면 `noindex_warning`가 발생합니다. Rankbeam은 이를 출시 상태를 검토할 신호로 다룹니다. 자기 참조 표준 URL은 색인 생성을 의도했다는 증거가 **아닙니다**. 이 조합은 의도적일 수 있습니다. 교차 도메인 표준 URL은 이 휴리스틱을 발생시키지 않습니다. 문제에는 `context.shipping_signal`(예: `self_canonical`)와 비교한 `canonical` 및 `page_url`가 포함됩니다.

두 스캐너 모두 이 검사를 적용합니다. 모델 스캔(`PageScanner`)은 저장된 표준 URL과 모델 URL을 비교합니다. 렌더링된 URL 스캔(`UrlScanner`)은 자기 참조 표준 URL을 가진 `noindex` 페이지를 정보성 `noindex_page`에서 점수에 반영되는 `noindex_warning`로 격상합니다. 그래서 `noindex_page` 자체는 제외합니다. 두 경로 모두 잠재적 충돌을 `noindex_warning`로 처리합니다. 색인 지시문을 바꾸기 전에 페이지의 실제 의도를 검토하세요.

## 설정 {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

허용 목록과 가중치는 설정으로 바꿀 수 **없습니다**. 동일한 `rubric_version`에서 모든 설치의 점수가 결정론적이어야 하므로 계산 방식 변경은 설정이 아닌 코드 수준의 평가 기준 변경입니다.

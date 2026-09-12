---
description: "SEO 점수와 별도로 제공되는 결정론적 AI 준비도 점수입니다. 크롤러가 페이지에 접근해 읽을 수 있는지를 Rankbeam이 정의한 기술 기준으로 측정합니다."
---

# AI 준비도 점수 — 별도의 결정론적 평가 축 {#the-ai-readiness-score-—-a-second-deterministic-axis}

Pro 스캔은 각 페이지에 [SEO 점수](/ko/pro/scoring)와 함께 **0-100 AI 준비도 점수**를 부여합니다. 이 점수는 *AI 크롤러와 답변 엔진이 콘텐츠에 접근하고 읽고 출처를 파악할 수 있는가?*라는 별개의 질문을 다루며, **자연 검색 SEO 점수에 합산되지 않습니다**. 두 축은 각자의 평가 기준, 버전, 열을 갖습니다.

::: warning 이 수치가 의미하는 것과 의미하지 않는 것
AI 준비도 점수는 **Rankbeam이 정의한, 기술적 호환성을 결정론적으로 측정하는 수치**입니다. 크롤링 기반 페이지 신호가 존재하며 올바른 형식인지를 평가합니다. 어떤 검색 또는 AI 시스템의 순위, 색인 생성, 답변 포함이나 인용을 예측하는 수치가 **아니며**, 어떤 점수도 그러한 결과를 보장하지 않습니다. `air_llms_txt` 검사는 사용하기로 선택한 도구를 위한 **선택적** 호환성 파일 `llms.txt`에 점수를 부여합니다. 이 파일은 Google 검색의 필수 요소가 아니며 순위 신호도 아닙니다.
:::

SEO 점수와 마찬가지로 **완전히 결정론적이며 재현 가능합니다**. 모든 점수는 이름이 있는 크롤링 기반 검사 하나로 추적할 수 있고, 같은 신호는 항상 같은 수치를 만듭니다. **점수 계산 과정에는 AI 호출이 전혀 없습니다.** “AI 가시성” SaaS 제품처럼 LLM을 샘플링하는 방식이 아니라 투명하고 감사 가능한 측정이라는 것이 핵심입니다.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip 합산하지 않는 두 평가 축
`AI-readiness: 74/100`는 `SEO: 82/100` 옆에 있으며 서로 영향을 주지 않습니다. AI 준비도 수치는 Pro `seo_scan_results` 행의 전용 `ai_readiness_*` 열에 저장됩니다. SEO 점수처럼 **수치 점수는 Pro 기능**입니다. 무료 코어 [`seo:audit`](/ko/guide/audit)는 수치를 출력하지 않습니다.
:::

## 감점이 아닌 가점 {#additive-credit-not-penalty}

[SEO 점수](/ko/pro/scoring)는 100에서 시작해 점수를 *차감*합니다. AI 준비도는 반대로 **0**에서 시작해 각 검사 가중치의 전부 또는 일부를 **더합니다**. 준비도는 사이트가 갖춰 나가는 것이므로, AI 신호가 없는 사이트는 “100에서 조금 감점”하는 대신 실제 상태에 맞게 0에 가까운 점수를 받습니다. 가중치의 합은 정확히 **100**입니다.

## 평가 기준 {#the-rubric}

점수는 **공개되고 버전이 지정된 평가 기준**인 `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric`로 계산합니다. 네 범주의 열 가지 검사로 구성됩니다.

### A · 봇 접근 및 제어 — 30점 {#a-·-bot-access-control-—-30-points}

AI 검색 및 어시스턴트 크롤러가 실제로 접근할 수 있나요? 사이트가 **제공하는 `/robots.txt`**를 **스캔한 페이지 자체의 경로**에 적용해 평가합니다. 루트가 열려 있어도 `Disallow: /section` 아래의 페이지는 차단될 수 있습니다. robots.txt는 이를 준수하는 크롤러에 대한 지시문이며 네트워크 접근 차단은 아닙니다. [AI 크롤러 목록](/ko/guide/ai-crawlers)의 목적 분류(학습 / 검색 / 어시스턴트)를 사용합니다.

| 검사 | 가중치 | 가점 기준 |
|---|---|---|
| `air_robots_reachable` — `robots.txt`가 제공되며 읽을 수 있음 | 6 | 있음 / 없음 |
| `air_ai_search_access` — AI **검색** 크롤러(유입 채널)의 사이트 접근 허용 | 10 | 허용 비율 |
| `air_ai_assistant_access` — AI **어시스턴트** 크롤러의 사이트 접근 허용 | 8 | 허용 비율 |
| `air_explicit_ai_policy` — 알려진 AI 봇에 대한 명시적 `robots.txt` 규칙 | 6 | 있음 / 없음 |

::: tip 학습 봇을 차단해도 준비도가 낮아지지 않습니다
학습 봇(GPTBot, CCBot 등)을 차단하는 것은 정당한 선택이므로 **절대로** 감점하지 않습니다. 학습 관련 점수는 의도적이고 명시적인 입장을 나타내는 `air_explicit_ai_policy`를 통해서만 부여합니다. 학습 봇을 차단하면서 검색 및 어시스턴트 크롤러를 허용하는 사이트도 이 범주에서 만점을 받을 수 있습니다.
:::

### B · 발견 가능성 — 20점 {#b-·-discoverability-—-20-points}

| 검사 | 가중치 | 가점 기준 |
|---|---|---|
| `air_sitemap_discoverable` — XML 사이트맵에 접근할 수 **있고**, `Sitemap:` 지시문이 이를 참조함 | 12 | 둘 다 / 하나 / 둘 다 아님 |
| `air_llms_txt` — 유효한 `/llms.txt`(제목 + 링크)가 제공됨 | 8 | 유효 / 존재 / 없음 |

### C · 기계가 읽을 수 있는 콘텐츠 — 22점 {#c-·-machine-readable-content-—-22-points}

| 검사 | 가중치 | 가점 기준 |
|---|---|---|
| `air_server_rendered_content` — 서버에서 렌더링한 HTML에 충분한 텍스트가 있음(JS를 실행하지 않아도 콘텐츠가 존재) | 14 | 단어 수 기준 |
| `air_markdown_twin` — 콘텐츠 협상을 통해 같은 페이지의 Markdown 버전을 제공함 | 8 | 있음 / 없음 |

### D · 구조화된 데이터 및 답변 준비도 — 28점 {#d-·-structured-data-answer-readiness-—-28-points}

| 검사 | 가중치 | 가점 기준 |
|---|---|---|
| `air_schema_completeness` — JSON-LD 존재, 기본 엔터티 유형 지정, 작성자 및 날짜 존재(글의 경우 작성자 + 날짜) | 18 | 완전 / 일부 / 없음 |
| `air_answer_structure` — 답변 추출에 도움이 되는 구조: FAQ/QA/HowTo 스키마, 제목 계층, 목록, 간결한 도입부 | 10 | 구조 요소 수 기준 |

각 검사는 **전체**, **일부**, **가점 없음**을 반환합니다. 필요한 신호를 수집하지 못했다면 **건너뜀**을 반환합니다(페이지를 가져오지 않고 스캔한 대상의 페이지 수준 검사 등). 건너뛴 검사는 0점이지만 별도로 표시하므로, *검사할 수 없었던* 신호를 확실히 없다고 표시하지 않습니다.

### 무료 감사의 범위 {#free-audit-reach}

스키마 완전성(`air_schema_completeness`)은 네트워크 요청 없이 모델의 구조화된 데이터에서 판단할 수 있습니다. 무료 감사가 답변 준비도 문제를 찾는 데 이미 사용하는 경로입니다. 나머지 아홉 검사는 크롤링이 필요하므로 전체 수치 계산은 **Pro 스캔**의 영역입니다.

## 정확한 범위 — 이 평가 축에서 제외하는 것 {#honest-scope-—-what-this-axis-excludes}

이 축은 **결정론적 콘텐츠 신호**를 평가합니다. 실행 중인 애플리케이션이나 DNS에 관한 에이전트 인프라 검사는 제외합니다.

| 제외 항목 | 이유 |
|---|---|
| **DNS-AID** (DNS 에이전트 발견 레코드) | 제공되는 페이지의 속성이 아닌 DNS / DNSSEC 인프라입니다. |
| **Web Bot Auth** (요청별 서명) | 정적 콘텐츠가 아닌 상호작용형 암호학적 핸드셰이크입니다. |
| **Protocol Discovery** (API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills, WebMCP 등) | 실행 중인 앱 / API / MCP 서버가 필요합니다. |
| **Commerce** (x402, MPP, UCP, ACP) | 에이전트 결제 기반입니다. 콘텐츠 사이트에는 청구할 대상이 없습니다. |

스키마 엔터티 완전성과 답변 블록 구조는 포함합니다. 이는 콘텐츠의 구성과 출처를 설명하는 신호이며, 검색 또는 답변 엔진이 이를 사용한다고 보장하지 않습니다.

## 버전 관리 — 과거 점수는 모르게 바뀌지 않습니다 {#versioning-—-historical-scores-never-silently-change}

저장된 모든 AI 준비도 점수에는 계산에 사용한 `AiReadinessRubric::VERSION` 값이 기록됩니다(`ai_readiness_version`). 검사 목록, 가중치, 가점 모델이 바뀌면 평가 기준 변경으로 간주해 **버전을 올립니다**. 따라서 저장된 수치는 어떤 평가 기준으로 설명되는지 항상 기록되어 과거 수치를 비교할 수 있습니다. 점수는 **저장되며 조회할 때 다시 계산하지 않습니다**. 점수에 영향을 주는 임계값(단어 수, 구조 요소 수)은 버전에 연결된 코드 수준 상수이며 설정 항목이 아닙니다. 설정 변경이 이미 발표한 수치를 모르게 바꿀 수 없습니다.

::: warning 버전이 고정하지 않는 입력 하나
봇 접근 검사는 코어의 **현재** [AI 크롤러 목록](/ko/guide/ai-crawlers)을 읽습니다. 새 봇 추가나 목적 재분류 같은 목록 갱신은 사실상 입력 변경이며, `AiReadinessRubric::VERSION` 값을 올리지 않고 두 봇 접근 하위 점수를 바꿀 수 있습니다. 버전은 목록이 아니라 *평가 기준*을 추적합니다. 고정된 목록보다 현재의 실제 봇 목록을 읽는 편이 검사에 더 유용하다는 의도적인 선택입니다. 과거와 정확하게 비교하려면 평가 기준 버전과 함께 코어 패키지 버전도 고정하세요.
:::

## 저장 위치 {#where-it-s-stored}

각 스캔은 SEO 점수가 있는 **동일한** `seo_scan_results` 행에 AI 준비도 열을 upsert합니다.

| 열 | 저장 내용 |
|---|---|
| `ai_readiness_score` | 0-100 수치(이 평가 축을 활성화한 상태에서 대상을 스캔하기 전까지 null). |
| `ai_readiness_version` | 계산에 사용한 평가 기준. |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]` — 전체 추적 정보. |

실행이 완료되면 실행별 평균을 `seo_scan_runs.avg_ai_readiness`에 기록합니다. `avg_score`에 대응하는 AI 준비도 추세입니다.

## 점수 읽기 {#reading-the-score}

**헤드리스** — 모델의 최근 결과에는 두 평가 축이 모두 있습니다.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament** — 리소스 표에서 SEO 점수 열 옆에 대응하는 열을 추가하세요.

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

SEO 제목 필드 위의 인라인 온페이지 점수 카드에도 별도 배지로 표시됩니다. [화이트라벨 보고서](/ko/pro/reports)의 PDF 및 이메일에는 수치, 등급, 이전 보고서 대비 변화, 스캔별 추세를 담은 전용 섹션이 있습니다. 항상 자연 검색 점수 옆에 표시하며 합산하지 않습니다.

### 등급 구간 {#grade-bands}

수치에서 도출한 표시용 문자 등급입니다. 기준이 되는 것은 수치이며, 일관성을 위해 SEO 점수와 같은 구간을 사용합니다.

| 점수 | 등급 |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## 설정 {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

검사와 가중치는 설정으로 바꿀 수 **없습니다**. 동일한 `ai_readiness_version`에서 모든 설치의 점수가 결정론적이어야 하므로 계산 방식 변경은 설정이 아닌 코드 수준의 평가 기준 변경입니다.

::: warning 사이트 신호 탐지는 프로세스 내 요청 경로를 사용합니다
동일 호스트 대상의 `/robots.txt`, `/llms.txt`, 페이지는 나머지 스캔과 같은 Laravel 프로세스 내 HTTP 커널을 통해 확인합니다. Laravel 라우팅을 거치지 않는 **정적 파일**로 제공되는 `robots.txt` 또는 `llms.txt`는 감지하지 못합니다. 점수에 반영하려면 권장 구성인 패키지 라우트로 제공하세요.
:::

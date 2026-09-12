---
description: "이미 보유한 Search Console 데이터로 검색어를 분석하세요. 게재순위 범위, CTR 검토 후보, 여러 페이지가 공유하는 검색어 등을 다루는 다섯 보고서입니다."
---

# Search Console 인사이트 {#search-console-insights}

자신의 Search Console 데이터로 계산하는 다섯 보고서입니다. 선택한 게재순위 범위의 검색어, CTR 검토 후보, 검색어/페이지 결과 중복, 검색어 군집, 기간별 변화를 다룹니다. 세 보고서는 동기화한 이력을 사용하고 두 보고서는 캐시된 실시간 요청 하나를 공유합니다. 이 특정 분석들을 제공하며 외부 키워드 플랫폼의 전체 데이터나 기능을 대신하지 않습니다.

[읽기 전용 Search Console 연동](/ko/pro/search-console)과 이력 동기화를 기반으로 합니다. 해당 페이지의 `seo-pro:gsc-sync`가 실행되고 있었다면 다섯 분석 중 세 가지는 **추가 API 비용 없이** 사용할 수 있습니다.

::: tip 사전 조건
세 *스냅샷* 분석은 저장된 `seo_gsc_metrics` 이력을 읽으므로 먼저 `seo-pro:gsc-sync`를 예약하세요([Search Console → 이력](/ko/pro/search-console) 참고). 동기화한 날짜가 많을수록 더 긴 기간의 추세를 비교할 수 있습니다.
:::

## 다섯 가지 분석 {#the-five-surfaces}

### 1. 상위권 접근 범위의 키워드 {#_1-striking-distance-keywords}

**노출 수로 가중한 평균 게재순위가 5–20위**인 검색어를 노출 수순으로 표시합니다. 관련성과 내부 링크를 검토하는 데 사용하세요. 이 범위에 있다고 작은 변경만으로 검색어가 첫 페이지에 진입한다는 뜻은 아닙니다.

### 2. CTR 기회 {#_2-ctr-opportunities}

**순위는 높지만 해당 순위의 예상 클릭률보다 실제 클릭률이 낮은** 검색어입니다. 각 검색어의 실제 CTR을 업계 자료를 종합한 순위별 CTR 곡선과 비교합니다. 실제 노출이 있으면서 예상 비율보다 크게 낮은 검색어를 **제목/설명 재작성 후보**로 표시하고, 추정 *놓친 클릭 수*순으로 정렬합니다. 이 목록은 [AI 메타 제안기](/ko/pro/ai-assist)에 사용할 자연스러운 입력이며, 재작성을 검토할 정확한 검색어를 알려 줍니다.

### 3. 검색어 중복 경쟁 {#_3-cannibalization}

같은 검색어에 **사이트 URL이 두 개 이상 나타나는** 경우입니다. 중복이 반드시 해롭지는 않습니다. 페이지를 통합하거나 차별화하기 전에 서로 다른 검색 의도를 충족하는지 검토하세요.

### 4. 검색어 군집 {#_4-query-clusters}

**각 페이지가 실제로 순위에 오르는 검색어**를 페이지별로 묶습니다. Google에서 해당 페이지가 어떤 주제와 연결되는지 보여 줍니다. 의도한 주제에서 벗어나는 페이지나, 목표로 삼지 않았던 가치 있는 검색어에 조용히 노출되는 페이지를 찾는 데 유용합니다.

### 5. 이전 기간 대비 추세 {#_5-trend-vs-previous-period}

현재 기간과 바로 앞의 같은 길이의 기간을 비교해 클릭, 노출, 게재순위, CTR의 **변화가 가장 큰 항목**을 보여 줍니다. 게재순위는 검색어가 두 기간 모두에 트래픽이 있을 때만 비교합니다. 새로 생기거나 완전히 사라진 검색어에는 비교할 상대가 없기 때문입니다.

## 수치의 출처: 실시간과 스냅샷 {#where-the-numbers-come-from-live-vs-snapshot}

각 분석은 최소 비용으로 정확하게 답할 수 있는 소스를 읽습니다. 저장된 이력은 각 차원을 따로 저장하므로 어떤 **검색어**가 어떤 **페이지**와 짝을 이뤘는지 복원할 수 없습니다. 그 쌍이 필요한 두 분석만 실시간으로 요청하며, **캐시된 요청 하나를 공유합니다**.

| 분석 | 소스 | 이유 |
|---|---|---|
| 상위권 접근 범위 | **로컬 스냅샷** | 검색어별 게재순위와 노출 수가 필요하며 이미 동기화한 이력에 있습니다. API 비용이 없습니다. |
| CTR 기회 | **로컬 스냅샷** | 같은 보유 데이터를 사용합니다. 예상 CTR 곡선은 조회 결과가 아닌 정적 비교 기준입니다. |
| 추세 변화 | **로컬 스냅샷** | 실제 일별 이력이 필요하며 동기화가 바로 그 데이터를 저장합니다. |
| 검색어 중복 경쟁 | **실시간**(검색어 × 페이지) | 검색어→페이지 쌍은 저장하지 않습니다. 모든 쌍을 저장하면 저장 공간이 크게 늘어납니다. |
| 검색어 군집 | **실시간** — *3번 분석의 요청 공유* | 같은 쌍 데이터를 검색어 대신 페이지별로 묶습니다. |

따라서 Insights 페이지 방문에는 **최대 한 번의** Search Analytics 요청이 필요하며, `search_console.cache_ttl`초 동안 캐시합니다. 중복 경쟁과 군집은 현재 모습을 확인하는 *특정 시점의* 분석이므로 의도적으로 실시간 데이터를 사용합니다. 공유 캐시는 반복 요청을 줄입니다. 토큰 갱신에는 추가 인증 요청이 필요할 수 있으며 Google 할당량은 계속 적용됩니다. 스냅샷 분석은 네트워크에 접근하지 않습니다.

## 대시보드 {#in-the-dashboard}

Filament 플러그인을 설치하면 연동이 활성화된 경우에만 *SEO* 탐색 그룹 아래에 **Search Console 인사이트**가 나타납니다. 엄격하게 읽기 전용입니다. 각 분석은 섹션으로 표시되며, 스냅샷 데이터가 비어 있으면 이력 동기화 안내를 보여 줍니다. 쌍 데이터의 실시간 요청이 실패하면 민감한 내용을 제거한 인라인 알림을 표시하며 페이지 전체를 막지 않습니다.

## 설정 {#configuration}

모든 설정은 `config/seo-pro.php`의 `search_console.insights` 아래에 있습니다. 기본값으로 시작하고 사이트 규모에 맞게 임계값을 조정하세요.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info 예상 CTR 곡선
CTR 기회 곡선은 공개된 자연 검색의 순위별 평균 CTR을 종합한 **휴리스틱**입니다. 비교 기준이며 개별 사이트에 대한 단정이 아닙니다. 표시된 검색어는 검증된 결함이 아닌 *검토 후보*입니다. 직접 측정한 곡선이 있다면 `insights.ctr_curve`에 `position => percent` 맵으로 넣으세요.
:::

## 함께 보기 {#see-also}

- [Search Console](/ko/pro/search-console) — 이 인사이트가 읽는 읽기 전용 연동과 이력 동기화
- [화이트라벨 보고서](/ko/pro/reports) — 브랜드 PDF에 표시되는 기간별 주요 변화
- [AI 지원](/ko/pro/ai-assist) — CTR 분석이 표시한 제목/설명 재작성

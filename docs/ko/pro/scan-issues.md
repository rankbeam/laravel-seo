---
description: "Pro 스캔의 모든 문제를 정의하는 안정적인 문제 코드 레지스트리입니다. 코드마다 심각도와 필드가 고정되어 대시보드와 내보내기는 메시지가 아닌 코드를 읽습니다."
---

# 스캔 문제 — 문제 코드 레지스트리 {#scan-issues-—-the-issue-code-registry}

Pro 스캔이 보고하는 모든 문제는 단일 레지스트리 `Rankbeam\Seo\Pro\Scanning\IssueRegistry`의 **안정적인 문제 코드**입니다. 스캐너는 즉석에서 코드를 만들지 않습니다. 각 문제를 `IssueRegistry::make()`로 구성하며, 레지스트리의 심각도와 필드를 지정하고 **정의되지 않은 코드는 거부합니다**. 따라서 아래 목록을 계약으로 삼아 구현할 수 있습니다. 대시보드, 내보내기, [Pro 점수](/ko/pro/scoring)는 메시지를 파싱하지 않고 코드를 읽습니다. 무료 [`seo:audit`](/ko/guide/audit)는 더 좁은 범위와 일부 다른 hreflang 코드를 가진 자체 코어 메타데이터 레지스트리를 사용합니다.

각 코드에는 다음이 있습니다.

- **id** — `seo_scan_issues.issue_type`로 저장되는 안정적인 문자열.
- **severity** — `critical`, `warning`, `notice` 중 하나이며 **코드별로 고정**됩니다. 심각도를 바꾸지 않고 코드를 나눕니다.
- **field** — 해당 `seo_meta` 필드 또는 페이지 수준 발견 사항의 _page_.
- **execution class** — 탐지에 필요한 실행 조건(아래 참고).
- **evidence** — 문제의 `context` 배열에 포함된 키.

## 실행 클래스 {#execution-classes}

검사는 필요한 실행 조건에 따라 정확히 세 클래스 중 하나에 속합니다.

| 클래스 | 필요 조건 | 실행 주체 |
|---|---|---|
| **metadata** | 모델 + 코어 리졸버, 페이지 요청 없음 | 모델 스캔(`PageScanner`); 무료 [`seo:audit`](/ko/guide/audit)는 일부 메타데이터 검사 지원 |
| **rendered** | 실제 제공되는 페이지 HTML(프로세스 내 커널 요청 또는 외부 요청) | URL 스캔(`UrlScanner`) |
| **network** | 다른 곳을 가리키는 표준 URL 같은 _별도_ 대상을 검증할 **외부** 요청 | URL 스캔, **항상 `SsrfGuard`를 통과** |

무료 프로세스 내 감사가 전체 Pro 스캔과 같을 수 없는 이유입니다. 페이지 렌더링 없이 계산할 수 있는 것은 **metadata** 코드뿐이며, 렌더링된 HTML을 가져오고 네트워크로 표준 URL 대상을 검증하는 것은 Pro 파이프라인뿐입니다. `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`로 클래스별 레지스트리를 필터링하세요.

## 메타데이터 코드 {#metadata-codes}

`PageScanner`가 모델과 리졸버에서 탐지합니다. `missing_title`, `missing_description`, 길이 코드들은 렌더링된 URL 스캔에서도 실제 `<head>`를 측정해 발생합니다. 코드는 같고 의미도 같습니다.

| 코드 | 심각도 | 필드 | 증거 | 의미 |
|---|---|---|---|---|
| `missing_title` | critical | title | — | 제목과 계산 가능한 대체값이 모두 없음. |
| `missing_description` | warning | description | — | 메타 설명과 계산 가능한 대체값이 모두 없음. |
| `missing_og_image` | notice | og_image | — | Open Graph 이미지와 계산 가능한 대체값이 모두 없음. |
| `missing_focus_keyword` | notice | focus_keywords | — | 포커스 키워드가 설정되지 않음. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | 같은 로케일의 다른 페이지에서 제목 재사용. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | 같은 로케일의 다른 페이지에서 설명 재사용. |
| `title_too_long` | warning | title | `length`, `max`, `script` | 최종 제목이 문자 체계의 권장 길이를 초과(라틴 60, CJK 약 30). |
| `title_too_short` | notice | title | `length`, `min`, `script` | 최종 제목이 문자 체계의 하한 미만(라틴 30, CJK 약 15). |
| `description_too_long` | warning | description | `length`, `max`, `script` | 최종 설명이 문자 체계의 권장 길이를 초과(160 / 약 80). |
| `description_too_short` | notice | description | `length`, `min`, `script` | 최종 설명이 문자 체계의 하한 미만(70 / 약 35). |
| `robots_conflict_indexing` | critical | robots | `robots` | Robots 지시문에 `index`와 `noindex`가 함께 있음. |
| `robots_conflict_following` | warning | robots | `robots` | Robots 지시문에 `follow`와 `nofollow`가 함께 있음. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | 자기 참조 표준 URL 페이지가 `noindex`임. 검토할 휴리스틱이며 반드시 색인되어야 한다는 증거는 아님. 모델 및 렌더링된 URL 스캔 모두에서 발생. |
| `invalid_canonical` | critical | canonical | `canonical` | 표준 URL 값이 유효한 URL이 아님. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | 표준 URL이 페이지와 다른 호스트를 가리킴. |
| `shared_canonical` | notice | canonical | `canonical` | 여러 페이지가 같은 표준 URL을 선언함. |
| `insecure_canonical` | warning | canonical | `canonical` | `https` 사이트의 `http://` 표준 URL. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | hreflang 대체 페이지 값이 `x-default`도 유효한 BCP-47 언어 코드도 아님. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | 대체 페이지를 선언했지만 페이지 자신의 로케일을 참조하는 자기 참조 hreflang이 없음. |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | 같은 hreflang 코드가 여러 URL에 대응함(모호한 군집). |
| `hreflang_missing_x_default` | notice | alternates | `languages` | 다국어 hreflang 군집에 `x-default` 대체값이 없음. |
| `aeo_missing_author` | notice | schema | — | 구조화 데이터의 글에 작성자 엔터티가 없음(작성자 / 출처를 스키마에 명시하지 않음). |
| `aeo_article_missing_date` | notice | schema | — | 구조화 데이터의 글에 게시/수정 날짜가 없음(시간 정보를 스키마에 명시하지 않음). |

길이 임계값은 코어의 문자 체계별 [길이 정책](/ko/guide/multilingual#title-and-description-budgets-per-script)에서 가져옵니다(Pro 2.33). 라틴 문자 텍스트는 60/160, CJK는 약 30/80이며 그래핌 클러스터(사용자가 한 글자로 인식하는 단위)로 세므로 편집기 글자 수 표시와 스캔이 어긋나지 않습니다. 하한(라틴 제목 30, 설명 70, CJK는 약 절반)은 최적화 부족을 표시하는 스캔 기준이며, `script` 문맥 키가 적용한 분류를 나타냅니다. 길이는 대체값과 제목 접미사를 포함해 실제 렌더링되는 **최종 제목/설명**을 측정합니다.

`hreflang_*` 코드는 리졸버의 `alternates`에서 읽은 페이지의 hreflang 선언을 검증합니다. 잘못되거나 중복된 코드, 자기 참조 누락, 다국어 군집의 `x-default` 누락을 검사합니다. 페이지가 대체 페이지를 선언할 때만 실행합니다. 페이지 간 **상호 참조**는 메타데이터 검사에서 확인하지 않습니다. 아래의 선택적 네트워크 검사가 다른 페이지를 가져옵니다.

`aeo_*` 코드는 **답변 준비도(AEO)** 신호로, 페이지의 글을 구조화 데이터에서 이해할 수 있는지 살핍니다. 최종 JSON-LD 그래프를 읽고 글 유형(`Article`, `BlogPosting`, `NewsArticle` 등)을 선언했지만 `author` 엔터티(명시적 작성자/출처) 또는 `datePublished` / `dateModified`(명시적 시간 정보)가 없을 때**만** 발생합니다. 글이 없는 페이지에는 표시하지 않습니다. 기본 활성화된 `seo-pro.scan.checks.aeo`가 제어하며 무료 [`seo:audit`](/ko/guide/audit)와 대응합니다.

::: tip `missing_focus_keyword`에는 활성화 조건이 있습니다
포커스 키워드 알림은 **코어**의 포커스 키워드 흐름이 활성화되었을 때만 발생합니다(`seo.keywords.enabled`, 기본값 `false`). 꺼져 있으면 키워드가 없다고 표시하지 않습니다. 무료 [`seo:audit`](/ko/guide/audit) 명령과 Filament 편집기도 **같은** 코어 플래그를 읽으므로 스캔, 감사, 편집기 알림이 항상 일치합니다. 활성화 조건은 하나뿐입니다.
:::

## 렌더링 코드 {#rendered-codes}

`UrlScanner`가 실제 제공 HTML에서 탐지합니다. 동일 호스트는 외부 트래픽 없는 프로세스 내 커널 요청, 외부 대상은 보호된 요청을 사용합니다.

| 코드 | 심각도 | 필드 | 증거 | 의미 |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | URL이 4xx/5xx 상태로 응답. |
| `empty_response` | critical | page | — | URL이 빈 본문 반환. |
| `missing_canonical` | notice | canonical | — | 렌더링된 head에 `<link rel="canonical">` 없음. |
| `noindex_page` | notice | robots | `robots` | 렌더링된 페이지가 `noindex`임(정보). `noindex`이면서 **자기 참조 표준 URL**이면 대신 점수에 반영되는 `noindex_warning` 코드로 격상. |
| `missing_h1` | notice | page | — | `<h1>` 제목 없음. |
| `multiple_h1` | notice | page | `count` | `<h1>` 태그가 여러 개(정보). |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | 콘텐츠 이미지에 `alt` 속성 없음(명시적 `alt=""`는 장식용으로 처리해 표시하지 않음). |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter` | 본문이 설정 단어 수 미만. 체크리스트 토크나이저가 띄어쓰기 문자 체계는 공백, 중국어·일본어·태국어는 ICU 사전 분할(`segmenter: intl`, ext-intl 필요)로 세므로 400단어 일본어 글을 한 “단어”로 세지 않음. |
| `mixed_content` | warning | page | `count`, `sample` | `https` 페이지에 `http://` 하위 자원. |
| `html_lang_missing` | notice | page | — | `<html lang>` 속성이 없거나 비어 있음. 보조 기술이 부적절한 음성을 선택할 수 있음. |
| `html_lang_invalid` | notice | page | `declared` | `lang` 값이 BCP-47 태그가 아님(`english`, 밑줄이 있는 `en_US`, `jp`). |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script` | 보이는 본문의 문자 체계가 선언 언어와 다름(일본어 페이지의 `lang="en"`, 라틴 문자 본문의 `lang="ru"`). 문자 체계 수준만 판단하며 라틴 언어 간 차이는 추측하지 않음. 본문에 글자 ≥ 40개 필요. |

## 네트워크 코드 {#network-codes}

`UrlScanner`는 해당 선택적 플래그가 켜져 있을 때만 탐지합니다. 표준 URL 대상은 `seo-pro.scan.url_checks.check_canonical_target`, hreflang 대체 페이지는 `check_hreflang_reciprocity`입니다. 모든 대상은 스킴 허용 목록, 호스트 범위, 사설 IP 거부, 리디렉션/시간/크기 한도를 적용하는 **`SsrfGuard`를 통해** 가져옵니다. 리디렉션을 따라가지 **않으므로** 리디렉션하는 표준 URL을 확인할 수 있습니다. 페이지 자체를 방금 가져왔으므로 자기 참조 표준 URL이나 대체 페이지는 건너뜁니다.

| 코드 | 심각도 | 필드 | 증거 | 의미 |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | HTTP 요청 전에 `SsrfGuard`가 대상을 거부. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | 표준 URL 대상이 HTTP 오류 반환. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | 표준 URL 대상이 리디렉션함. 최종 URL을 지정할 것. |
| `canonical_target_noindex` | warning | canonical | `canonical` | 표준 URL 대상 자체가 `noindex`임. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | 표준 URL 대상 검증 불가(가드 거부 / 해석 불가). |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status` | 선언한 대체 페이지가 원래 페이지를 다시 선언하지 않음. hreflang 쌍이 무시될 수 있으나 이것만으로 번역 페이지의 색인이 불가능해지는 것은 아님. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason` | 대체 페이지 요청 불가(가드 거부, 오류 상태, 리디렉션, 크기 초과)로 상호 참조를 검사하지 못함. 결함이 아닌 증거 부족. |

상호 참조는 페이지당 최대 `hreflang_max_alternates`개(기본 10) 대상을 요청하며 `x-default`를 포함하고 중복과 자기 자신은 건너뜁니다. 위 모델 수준 `hreflang_*` 메타데이터 코드는 *선언된* 목록을 검증합니다. 다른 페이지가 필요한 검사는 이 크롤링입니다.

모든 네트워크 경로는 공유 `SsrfGuard`를 재사용합니다. 위협 모델과 남아 있는 TOCTOU 주의 사항은 [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md)를 참고하세요.

## 코드와 점수의 관계 {#how-codes-feed-the-score}

[Pro SEO 점수](/ko/pro/scoring)는 위 심각도에 따른 문제별 고정 감점을 합산해 100에서 뺀 값입니다(`100 −`). 대부분의 코드는 반영하지만 일부는 의도적으로 제외합니다. `missing_focus_keyword`(권고), `noindex_page`와 `multiple_h1`(정보), `blocked_url` / `canonical_target_blocked` / `hreflang_target_unverified`(검사하지 못함 ≠ 결함), `hreflang_*`, `html_lang_*`, `aeo_*`(현재 점수에서 제외한 권고 신호)입니다. [점수 페이지](/ko/pro/scoring)에 전체 허용 목록과 코드별 감점이 있습니다.

## 문제 수명 주기 {#issue-lifecycle}

문제는 존재하는 동안만 남는 행이 아니라 수명 주기를 가집니다. 스캔은 대상 문제를 지우고 재생성하지 않고 **상태를 조정**합니다. 각 문제의 안정적인 식별자는 대상(모델은 `scannable_type` + `scannable_id`, 라우트/사이트맵 대상은 `url`)과 `issue_type`의 조합입니다. 코드 하나는 스캔당 대상별로 최대 한 번 발생합니다. “위반 항목 N개” 코드(`missing_image_alt`, `mixed_content`, `hreflang_*` 등)는 사례를 `count` / `sample` 값이 있는 행 하나로 합치므로 식별자가 고유합니다.

각 스캔에서 대상별로 다음을 수행합니다.

- **기존 행이 없는** 발견 사항은 `open` 상태로 생성하고 `detected_at` 값을 기록합니다.
- **기존 미해결 행과 일치하는** 발견 사항은 증거를 갱신하되 원래 `detected_at` 값을 유지합니다. 안정적인 *최초 발견 시점*이므로 매 스캔마다 초기화하지 않습니다.
- 완료된 검사에서 **더 이상 발견되지 않는** 미해결 문제는 **`fixed`**로 표시하고 `resolved_at` 값을 기록합니다. 실제 해결을 남기도록 행을 **삭제하지 않고 보존**합니다.
- **`fixed`** 문제가 **재발하면** 같은 행을 **다시 열고** `detected_at` 값을 다시 기록합니다.
- 사용자가 대시보드에서 **`ignored`**로 표시한 문제는 변경하지 않습니다.

| 상태 | 의미 | 설정 주체 |
|---|---|---|
| `open` | 현재 존재함. | 스캔(신규 또는 계속 발견) |
| `fixed` | 존재했으나 더 이상 발견되지 않음. | 다음 실행에서 발견되지 않으면 스캔이 자동 설정 |
| `ignored` | 사용자가 숨김; 미해결 수와 점수에서 제외. | 대시보드의 무시 동작 |

이제 해결을 버리지 않고 기록하므로 화이트라벨 [보고서](/ko/pro/reports)는 보고서 간 스냅샷 차이 대신 기간 내 **실제 해결/신규 수**를 보여 줄 수 있습니다. 대시보드, [`seo-pro:scan-status`](/ko/pro/headless) 명령, [점수](/ko/pro/scoring)는 모두 `open`만 필터링하므로 저장된 `fixed` 행이 수치를 부풀리지 않습니다. 해결된 행은 해결한 실행에 연결되며 일반 스캔 실행 [보존 기간](/ko/pro/production)에 따라 정리됩니다.

## 설정 {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

보호된 요청의 응답 크기 한도는 `seo-pro.http.max_response_bytes`(기본 2 MB)입니다. 프로세스 내 동일 호스트 스캔에는 크기 한도가 없습니다.

## 호환성 참고 (문제 코드 이름 변경) {#compatibility-note-issue-code-rename}

이전의 `robots_conflict` 코드 하나는 두 심각도를 가졌습니다. 코드마다 정확히 하나의 심각도에 대응하도록 나눴습니다.

| 이전 코드 | 새 코드 | 심각도 |
|---|---|---|
| `robots_conflict` (index + noindex) | `robots_conflict_indexing` | critical |
| `robots_conflict` (follow + nofollow) | `robots_conflict_following` | warning |

`robots_conflict`를 저장하거나 필터링했다면 두 새 코드로 갱신하세요.

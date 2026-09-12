---
description: "php artisan seo:audit로 현재 SEO 문제를 페이지별 통과·경고·실패 표로 확인하세요. 큐, 라이선스, 네트워크 없이 프로세스 내에서 실행되는 무료 코어 기능입니다."
---

# 무료 SEO 감사 (`seo:audit`) {#free-seo-audit-seo-audit}

`php artisan seo:audit` 명령은 **지금 내 SEO에 어떤 문제가 있는가?**라는 질문에 무료로 답합니다. `HasSEO` 모델을 프로세스 내에서 순회하며 **큐, 라이선스, 네트워크 없이** 페이지별 **통과 / 경고 / 실패** 표와 요약을 출력합니다.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## 검사 항목 {#what-it-checks}

감사는 **메타데이터** 실행 클래스만 실행합니다. 페이지를 가져오지 않고 모델과 [리졸버](/ko/concepts/resolver-precedence)만으로 판단할 수 있는 검사입니다.

| 검사 | 코드 |
|---|---|
| 제목 / 설명 존재 여부(대체값 반영) | `missing_title`, `missing_description` |
| OG 이미지 존재 여부(대체값 반영) | `missing_og_image` |
| 제목 / 설명 길이 | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| 사이트 전체의 제목 / 설명 중복 | `duplicate_title`, `duplicate_description` |
| Robots 충돌 및 의심스러운 noindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| 표준 URL 형식 / 교차 도메인 / 공유 / 보안되지 않은 URL | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| 답변 준비도(AEO) — 글의 구조화된 데이터 | `aeo_missing_author`, `aeo_article_missing_date` |
| 포커스 키워드 설정(선택적 활성화) | `missing_focus_keyword` |
| hreflang 대체 페이지(코어 레지스트리, 해당 페이지에 존재할 때) | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

대부분의 코드는 Pro 스캔에도 있지만 레지스트리는 서로 다릅니다. 특히 코어는 `hreflang_missing_self`를, Pro는 `hreflang_missing_self_reference`를 사용합니다. `hreflang_duplicate_code`의 심각도는 코어에서 알림, Pro에서 경고입니다. 이름이 같다고 검사 범위나 심각도까지 같다고 가정하지 마세요. `blank_explicit_override`는 코어 레지스트리에 속합니다. 길이는 편집기의 [문자 체계별 기준](/ko/guide/multilingual#title-and-description-budgets-per-script)을 사용합니다. 라틴 문자 텍스트는 60/160자, CJK는 약 30/80자이며, 그래핌 클러스터(사용자가 한 글자로 인식하는 단위)로 셉니다. 접미사를 포함한 **최종 결정된** 값을 측정하므로 [Filament 편집기](/ko/guide/filament)의 글자 수 표시와 감사 결과가 어긋나지 않습니다. hreflang 검사는 `seo.hreflang` 정책 적용 후의 목록, 즉 태그와 사이트맵이 출력하는 동일한 목록을 대상으로 실행합니다. 상호 참조 확인에는 크롤링이 필요하므로 Pro에서만 수행합니다.

**답변 준비도(AEO)** 검사는 페이지가 글 유형 JSON-LD(`Article`, `BlogPosting`, `NewsArticle` 등)를 선언했지만 구조화된 데이터에서 글을 이해하는 데 필요한 신호가 빠졌을 때만 발생합니다. 해당 신호는 명시적 작성자 / 출처를 나타내는 `author` 엔터티 또는 명시적 시간 정보를 나타내는 `datePublished` / `dateModified`입니다. 글이 없는 페이지에는 표시하지 않으므로 AEO가 해당하지 않는 곳에서는 알림을 발생시키지 않습니다. 권고성 알림이며, Pro의 0–100 점수에서는 제외됩니다.

## 검사하지 않는 항목 — 기능 범위 {#what-it-does-not-check-—-the-capability-boundary}

무료로 프로세스 내에서 실행하는 감사는 전체 Pro 스캔과 같을 수 없으며, 명령은 실행할 때마다 이를 알립니다. 다음은 실행하지 **않습니다**.

- **렌더링된 HTML 검사** — `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content`, `mixed_content`. 실제로 제공되는 페이지 HTML이 필요합니다.
- **실제 표준 URL 네트워크 검사** — `canonical_target_broken` / `_redirect` / `_noindex`. 보호 장치를 거치는 외부 요청이 필요합니다.
- **0–100 수치 점수.** 점수는 Pro 기능으로, 버전이 지정된 평가 기준에 따라 스캔 결과 레코드에 저장됩니다. [SEO 점수](/ko/pro/scoring)를 참고하세요.

이 기능들은 **Pro 스캔**에서 제공합니다. 전체 [문제 레지스트리](/ko/pro/scan-issues)를 참고하세요.

## 감사 대상 선택 {#choosing-what-to-audit}

기본적으로 명령은 `seo.audit.models`에 나열된 모델을 대상으로 감사를 수행하고, 없으면 `seo.sitemap.models`를 사용합니다.

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

모델을 명시적으로 전달할 수도 있습니다.

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## 옵션 {#options}

| 옵션 | 효과 |
|---|---|
| `--model=` | 감사할 `HasSEO` 모델 클래스입니다(반복 지정 가능). 설정보다 우선합니다. |
| `--locale=` | 이 로케일에서 SEO 값을 결정합니다(기본값은 앱 로케일). |
| `--limit=` | 모델당 감사할 최대 레코드 수입니다(`0` = 전체). |
| `--issues-only` | 문제가 하나 이상 있는 페이지만 나열합니다. |
| `--strict` | 문제가 발견되면 0이 아닌 상태 코드로 종료합니다. CI용입니다. |
| `--json` | 표 대신 기계가 읽을 수 있는 JSON(페이지, 요약, 검사 범위)을 출력합니다. |

### CI 검사 {#ci-gate}

`--strict` 옵션을 사용하면 감사를 빌드 검사로 활용할 수 있습니다.

```bash
php artisan seo:audit --strict
```

경고나 실패가 있는 페이지가 하나라도 있으면 `1`, 감사한 모든 페이지가 통과하면 `0`로 종료합니다.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## 포커스 키워드 {#focus-keywords}

`missing_focus_keyword` 알림은 **기본적으로 꺼져 있습니다**. 포커스 키워드 작업 흐름을 활성화해야 발생합니다.

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Pro 스캔도 **같은** 플래그를 읽으므로 감사, 스캔, Pro 편집기의 알림이 항상 일치합니다. 페이지의 키워드는 [Filament 포커스 키워드 필드](/ko/guide/filament) 또는 `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`로 설정하세요.

## 값이 예상과 다를 때: `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` 명령이 *어떤 문제가 있는지* 알려 준다면, [`seo:explain`](/ko/guide/explain)는 *왜 필드 값이 그렇게 결정되었는지* 설명합니다. 어떤 계층(설정 / 기본값 / 계산값 / 명시적 값)이 각 값을 설정했는지, 무엇을 덮어썼는지, 이후 어떤 후처리(제목 접미사, 표준 URL 정리, 색인 가드)가 값을 바꿨는지 보여 줍니다. 감사 결과나 렌더링된 태그가 예상과 다를 때 사용하세요.

```bash
php artisan seo:explain "App\Models\Post" 42
```


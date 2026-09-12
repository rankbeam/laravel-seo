---
description: "스캔, 리디렉션, 404 로깅 등 모든 Pro 기능은 Filament 없이 헤드리스로 실행됩니다. artisan만으로 Pro를 관리하는 명령 참조입니다."
---

# 헤드리스 사용 {#headless-usage}

스캔, 리디렉션, 404 로깅 등 모든 Pro 기능은 헤드리스로 실행됩니다. 엔진에 구현되어 있어 Filament가 필요하지 않습니다. 패널은 관리 UI일 뿐이며, 아래 명령으로 같은 기능을 헤드리스로 관리할 수 있습니다.

## 명령 참조 {#command-reference}

### 설정 및 상태 점검 {#setup-health-check}

| 명령 | 기능 |
|---|---|
| `seo-pro:install` | `config/seo-pro.php`와 Pro 마이그레이션을 게시하고 실행한 뒤 다음 단계(`--no-migrate`, `--force`)를 출력합니다. |
| `seo:doctor` | 앱 URL, 코어 + Pro 테이블, 스캔 대상, 사이트맵, 작업 유형별 큐, 선택적 기능, 운영 상태를 한 번에 점검하며 각 경고의 정확한 해결 방법을 보여 줍니다(모니터링에는 `--json` 사용). |

공식 설정 경로는 `seo-pro:install`입니다. Pro 마이그레이션은 게시해야만 사용할 수 있으며 패키지가 자동 로드하지 않습니다. 따라서 단순히 `composer require`를 실행한 상태에서 실제 스키마를 구성하는 것은 설치 프로그램입니다. 멱등성이 있으므로 언제든 다시 실행할 수 있습니다.

`seo:doctor` 명령은 네트워크 요청을 하지 않고 비밀 값을 출력하지 않습니다. AI 검사는 설정된 키 변수가 *지정되어 있는지*만 보고합니다. 설정과 최근 실행 이력을 검증하며, 외부 cron이나 워커가 실제 실행 중인지 증명할 수는 없습니다. 필수 테이블 누락과 같은 심각한 실패에만 0이 아닌 코드로 종료하므로 localhost 개발 환경에 경고가 있어도 정상 종료합니다. `--json` 옵션은 각 검사에 안정적인 `id`를 제공해 식별 기준으로 사용할 수 있게 합니다. [설치](/ko/pro/installation) 직후와 CI에서 실행하세요.

### 스캔 {#scanning}

| 명령 | 기능 |
|---|---|
| `seo-pro:scan` | 등록된 모든 대상의 전체 스캔을 큐에 넣습니다(인라인 실행은 `--sync`; **CI 검사** `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=json\|md\|html`에는 `--sync` 필요). |
| `seo-pro:scan-status` | 최근 실행 요약과 미해결 문제를 심각도가 높은 순서로 표시합니다(`--limit=20`, `--severity=critical\|warning\|notice`). |
| `seo-pro:scan-recover` | 중단된 큐 워커가 남긴 실행을 실패로 표시합니다. |
| `seo-pro:scan-prune` | 보존 기간이 지난 완료 실행과 해당 문제를 삭제합니다. |

### 깨진 링크 크롤러 {#broken-link-crawler}

기본적으로 꺼져 있습니다. `seo-pro.broken_links.enabled`를 활성화하고 두 테이블을 마이그레이션하세요(`seo-pro:install` 명령이 게시합니다). 크롤링은 범위가 제한된 큐 작업으로 나뉘어 실행되므로 해당 큐 전용 워커를 실행하세요. 조정 방법은 [운영 환경 설정](/ko/pro/production)을 참고하세요.

| 명령 | 기능 |
|---|---|
| `seo-pro:broken-links-scan` | 범위가 제한되고 재개 가능한 크롤링을 큐에 넣습니다(`--scope=internal_only\|internal_and_external`, 추가 시작 URL은 `--url=*`). |
| `seo-pro:broken-links-status` | 최근 크롤링 요약, 미해결 발견 사항, 이번 실행의 [유형별 링크 검사](/ko/pro/broken-links#typed-link-inspections)를 표시합니다. **CI 검사**(`--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=`)도 지원합니다. |
| `seo-pro:broken-links-cancel` | 실행 중이거나 대기 중인 크롤링을 취소합니다(`{run?}` — 기본값은 가장 최근의 활성 실행). |
| `seo-pro:broken-links-recover` | 중단된 워커가 남긴 크롤링을 실패 처리합니다(오래된 임대). |
| `seo-pro:broken-links-prune` | 크롤러 보존 정책을 적용합니다(오래된 실행과 해결된 발견 사항). |

### 리디렉션 및 404 {#redirects-404s}

| 명령 | 기능 |
|---|---|
| `seo-pro:redirect-create {source} {target}` | 리디렉션 규칙을 생성합니다(`--code=301`, `--regex`, `--no-preserve-query`, `--note=`). |
| `seo-pro:404-list` | 기록된 404를 접속 횟수가 많은 순서로 표시합니다(`--status=new\|ignored\|redirected\|all`, `--limit=20`). |
| `seo-pro:redirects-flush-hits` | `redirects.hits.flush_immediately=false`일 때 캐시에 일괄 집계한 리디렉션 접속 횟수를 데이터베이스에 기록합니다. |
| `seo-pro:404-prune` | 오래된 404 항목을 삭제하고 행 수 상한을 적용합니다. |

### 온페이지 체크리스트 {#on-page-checklist}

| 명령 | 기능 |
|---|---|
| `seo-pro:checklist {model} {id}` | 한 모델의 키워드를 반영한 통과/경고/실패 체크리스트입니다(`--json`, `--strict`, `--locale=`). [온페이지 체크리스트](/ko/pro/on-page-checklist)를 참고하세요. |

같은 체크리스트를 `SeoPro::checklistFor($model)`로도 사용할 수 있습니다. 키워드 배치, 길이, 이미지, 내부 링크를 살피는 편집 작업용이며 [SEO 점수](/ko/pro/scoring)와는 **다릅니다**.

### Search Console (읽기 전용) {#search-console-read-only}

| 명령 | 기능 |
|---|---|
| `seo-pro:search-console` | 미해결 문제가 **있고** 검색 트래픽도 있는 페이지를 기회 손실이 큰 순서로 표시합니다(기본값 `--view=attention`). |
| `seo-pro:search-console --view=pages` | 노출/클릭/CTR/게재순위 기준 상위 페이지를 표시합니다. |
| `seo-pro:search-console --view=queries` | 상위 검색어를 표시합니다(`--days=`, `--limit=`, `--json`). |

같은 지표를 `SeoPro::searchConsole()`로도 사용할 수 있습니다. [Search Console](/ko/pro/search-console)을 참고하세요. 기본적으로 꺼져 있으며 엄격하게 읽기 전용으로 동작합니다.

### AI 지원 {#ai-assist}

| 명령 | 기능 |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | 제목/설명 제안을 JSON으로 반환합니다(`--field=title\|description\|all`). [AI 지원](/ko/pro/ai-assist)을 참고하세요. |
| `seo-pro:ai-suggest --issue={id}` | 스캔 문제의 해결 방법을 쉬운 말로 설명하고 JSON으로 반환합니다. |

### 한 단계로 404 해결 {#resolving-a-404-in-one-step}

`--from-404={path}` 옵션은 404 모니터의 원클릭 *리디렉션 만들기* 동작에 대응하는 헤드리스 기능입니다. 규칙을 생성하는 **동시에** 일치하는 로그 항목을 리디렉션됨으로 표시하고 새 규칙과 연결합니다.

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

명령은 Filament 폼과 같은 검증기를 실행합니다. 잘못된 정규식 패턴, 길이 제한을 넘는 값, 허용 목록에 없는 외부 대상은 아무것도 기록하기 전에 거부됩니다.

## 권장 예약 일정 {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

위의 모든 반복 명령에 대한 권장 주기는 [운영 환경 설정](/ko/pro/production) 가이드에 있습니다. 큐 구성, 워커 설정, 재시도/복구 정책, 보존 정책과 함께, 완료된 각 실행이 내보내는 구조화된 **측정 데이터**(가져온 페이지 수, 검사한 링크 수, 차단한 URL 수, 소요 시간, 큐 지연)도 설명합니다.

## Filament UI가 필요한 기능은 무엇인가요? {#what-needs-the-filament-ui}

기능상 필요한 것은 없습니다. 스캔 파이프라인, 문제 추적, 리디렉션 일치, 404 로깅, 정리, 복구 등 전체 엔진은 Filament 유무와 관계없이 동일합니다. 패널은 *화면*을 제공합니다. 실시간 스캔 진행 상황과 심각도 통계가 있는 대시보드, 필터와 페이지별 모달을 통한 문제 탐색, 무시/다시 열기 버튼, 리디렉션 CRUD 폼, 원클릭 동작이 있는 404 표입니다. 문제 무시/다시 열기는 현재 전용 명령이 없습니다. 패널에서 처리하거나 tinker 또는 직접 작성한 코드에서 `SEOScanIssue` 모델의 `markIgnored()` / `reopen()`를 사용하세요.

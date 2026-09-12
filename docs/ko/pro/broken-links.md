---
description: "범위가 제한되고 재개 가능한 크롤러로 연결되지 않는 링크를 기록하세요. 자체 호스트의 끊긴 경로는 원클릭 리디렉션으로 처리하며 외부 링크 검사는 선택 사항입니다. 기본적으로 꺼져 있습니다."
---

# 깨진 링크 크롤러 {#broken-link-crawler}

**범위가 제한되고 재개 가능한 크롤러**가 사이트를 순회하고 각 페이지의 링크를 따라가 연결되지 않는 링크를 기록합니다. 자신의 호스트에서 끊긴 **내부** 경로는 클릭 한 번으로 리디렉션을 만들 수 있으며, **외부**의 깨진 링크 검사도 선택적으로 지원합니다. **기본적으로 꺼져 있습니다**.

세 가지 설계 원칙을 따릅니다.

- **범위 제한 및 재개 가능.** 크롤링은 각각 소수의 페이지만 처리하는 작은 큐 작업 여러 개로 실행됩니다. 실행이 완료되거나 한도에 도달할 때까지 다음 작업을 계속 예약합니다. 전체 실행에도 기본 2000페이지 한도가 있습니다. `null` 값은 명시적으로 무제한을 선택하는 값이며 기본값이 아닙니다. 이때도 나머지 배치/시간 한도는 적용됩니다. 사이트와 서버 처리 능력에 맞는 한도와 지연을 유지하세요.
- **안전한 기본값.** 기본 범위는 `internal_only`이며 자신의 호스트 링크만 검사하고 외부 서비스에는 요청하지 않습니다. 내부/외부의 모든 요청은 스킴 허용 목록, 호스트 범위, 사설 주소 거부를 적용하는 공유 **SsrfGuard**를 거칩니다. 외부 링크 검사는 선택적으로 켜야 하며 여전히 보호됩니다.
- **SEO 점수와 분리.** 발견 사항은 자체 테이블에 저장하며 `seo_scan_issues`나 0–100 점수에 기록하지 않습니다. 외부로 향하는 링크가 깨져도 페이지 점수는 바뀌지 않습니다. 깨진 링크는 별도로 추적하는 운영 항목입니다.

## 제공 내용 {#what-you-get}

활성화한 경우 Filament 대시보드에서 다음을 제공합니다.

- **깨진 링크 요약** — 미해결 수(내부/외부)와 마지막 크롤링을 보여 주고 발견 사항 표로 연결합니다.
- **깨진 링크 크롤링** — 실행 중인 크롤링의 실시간 진행 상황(크롤링한 페이지, 검사한 링크, 발견한 깨진 링크).
- **스캔별 깨진 링크** — 최근 크롤링의 추세.
- **발견 사항 리소스** — 모든 깨진 `source → target` 링크를 필터링할 수 있으며 내부 링크는 리디렉션으로 처리할 수 있습니다.

헤드리스에서는 `seo-pro:broken-links-*` 명령으로 같은 데이터를 얻습니다.

## 기본적으로 꺼져 있는 이유 {#why-it-s-off-by-default}

수동적인 렌더링 및 점수 계산 기능과 달리 크롤러는 **네트워크 요청을 수행**하며 인프라가 조금 필요합니다. 설치 후 모르게 시작하는 대신 의도적으로 활성화해야 합니다.

- 두 주요 테이블은 모든 Pro 마이그레이션처럼 **게시 후 사용**하므로 UI가 조회하기 전에 마이그레이션해야 합니다. 유형별 검사에는 `seo_broken_link_inspections`도 사용합니다.
- 크롤링은 **전용 큐에 등록**되며 실행할 **워커**가 필요합니다. 워커가 없으면 진행되지 않습니다.
- 확정은 아래 설명처럼 **여러 스캔에 걸쳐** 수행하므로 켜는 즉시 결과를 확정하는 대신 여러 주 동안 **예약 실행**하도록 설계되었습니다.

## 설정 {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

그런 다음 마이그레이션을 실행하세요. `seo-pro:install` 명령은 모든 Pro 마이그레이션을 게시하고 실행하며 멱등적이어서 다시 실행해도 안전합니다.

```bash
php artisan seo-pro:install
```

크롤링 큐에 **전용 워커**를 실행하세요. 별도 큐 `seo-broken-links`를 사용하는 이유는 긴 크롤링이 사용자 대상 작업 앞을 막지 않도록 하기 위해서입니다.

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

구성을 확인하세요. `seo:doctor` 명령은 플래그, 테이블, 크롤링 큐가 `sync`가 아닌 실제 연결인지 검사하고 각각 정확한 해결 방법을 보여 줍니다.

```bash
php artisan seo:doctor
```

Redis, Supervisor, 전용 연결을 포함한 전체 다중 큐 구성과 배치 조정은 [운영 환경 설정](/ko/pro/production)을 참고하세요.

## 크롤링 실행 {#running-a-crawl}

대시보드의 **지금 스캔** 동작 또는 헤드리스로 시작하세요.

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

두 명령 모두 크롤링을 **큐에 넣기만** 하며 실제 작업은 워커가 수행합니다.

## 깨진 링크로 표시되는 조건 {#how-a-link-gets-flagged}

링크는 `seo-pro.broken_links.mark_broken_after_failures`번 **연속 크롤링**에서 접근에 실패해야 깨진 것으로 보고됩니다. 기본값은 **3**이며 한 번이라도 성공하면 카운터를 초기화합니다. 일시적 장애 한 번으로는 표시하지 않으므로 일회성이 아닌 **예약 실행**을 위한 기능입니다. 주간 주기와 기본 임계값에서는 세 번 실패하면 확정합니다. 첫 관측 후 약 2주, 실제로 끊긴 시점부터 최대 약 3주입니다. 더 빠른 확정이 필요하면 주기를 줄이거나 임계값을 낮추세요.

## 유형별 링크 검사 {#typed-link-inspections}

접근 가능 여부 외에도 모든 크롤링 링크에 **유형별 검사**를 적용합니다. 끝 슬래시 불일치, 복잡한 인코딩, 리디렉션 체인, `javascript:` href, 깨진 페이지 내 앵커, 의미를 설명하지 않는 링크 텍스트 등 URL 관리 상태를 분류합니다. 각 검사는 고정 **심각도**(`critical` · `warning` · `notice`)를 가지며 크롤링별로 `seo_broken_link_inspections`에 기록됩니다. [스캔 문제](/ko/pro/scan-issues)와 같은 심각도를 사용하므로 CI 검사 하나로 두 기능을 다룰 수 있습니다. 여러 번 연속 크롤링한 뒤 확정하는 깨진 링크 *발견 사항*과 달리, 유형별 검사는 실행별 스냅샷으로 **첫 크롤링에서 즉시** 표시됩니다. CI에 필요한 동작입니다.

### 검사 참조 {#inspection-reference}

| 검사 | 심각도 | 표시 조건 | 적용 대상 |
| --- | --- | --- | --- |
| `broken_link` | critical | 대상이 HTTP ≥ 400 반환 | 모든 링크 |
| `redirect_chain` | notice · warning | 리디렉션을 거쳐야만 연결됨; `redirect_chain_warning_hops`를 넘으면 `warning` | 모든 링크 |
| `link_unreachable` | notice | 이번 크롤링에서 접근 불가(네트워크 오류, 시간 초과, 차단); 일시적일 수 있음 | 모든 링크 |
| `insecure_link` | warning | `https` 사이트의 `http://` 링크(전송 보안 저하) | 모든 링크 |
| `trailing_slash` | notice | 내부 경로가 선언한 끝 슬래시 규칙을 위반(**`trailing_slash` 설정 전에는 꺼짐**) | 내부 |
| `double_slash_url` | warning | 내부 경로에 `//`(빈 부분)가 있음 | 내부 |
| `duplicate_query_param` | notice | 쿼리 키 반복(`?a=1&a=2`); `key[]` 배열 문법은 제외 | 내부 |
| `non_ascii_url` | notice | 내부 경로에 인코딩되지 않은 비ASCII 문자 | 내부 |
| `uppercase_url` | notice | 내부 경로에 대문자 포함(대소문자별로 별도 제공되는지 검토) | 내부 |
| `underscore_in_url` | notice | 내부 경로에 밑줄 사용(SEO에서는 하이픈 구분자를 선호) | 내부 |
| `javascript_link` | warning | 앵커가 일반적인 크롤링 가능 대상이 아닌 `javascript:` href 사용 | 모든 앵커 |
| `missing_fragment` | warning | 같은 페이지의 `#fragment`와 일치하는 `id`/`name` 속성이 없음 | 같은 페이지 |
| `non_descriptive_anchor` | notice | 앵커 텍스트가 “click here”(여기를 클릭), “read more”(더 보기)처럼 일반적이거나 URL 자체임 | 모든 앵커 |
| `absolute_internal_link` | notice | 내부 링크가 루트 상대 경로 대신 절대 URL로 작성됨 | 내부 |

끝 슬래시, 대소문자, 인코딩, 이중 슬래시 등 관리 검사는 **내부** 링크에만 적용됩니다. 외부 사이트의 URL 스타일은 자신이 통제할 대상이 아닙니다. 리디렉션, 깨짐, 접근 불가, 보안되지 않은 링크 검사는 모든 링크에 적용합니다. 자체 프레임워크 라우트와 정적 자산 링크는 첫 실행의 불필요한 알림을 줄이도록 건너뜁니다. 아래 `exclude_paths` / `exclude_extensions`를 참고하세요.

각 링크는 정규화 형태 대신 **작성된 정확한 URL**로 요청하며 `#fragment`만 제거합니다. 따라서 `/about/ → /about` 같은 서버의 표준화 리디렉션을 정규화로 미리 없애지 않고 실제로 관측해 `redirect_chain` 코드로 표시합니다. 페이지에 작성된 서로 다른 링크 형태를 모두 검사하므로 `/page#ok`와 `/page#missing` 또는 `/a//b`와 `/a/b`도 첫 항목만이 아니라 각각 판단합니다. 기본 깨진 링크 *발견 사항*은 여전히 대상의 모든 별칭을 하나의 식별자로 합칩니다. 검사 행은 `(page, target, inspection)`별로 기록하므로 대상에 실패하는 페이지 내 앵커가 여러 개 있어도 앵커마다 행을 만들지 않고 예시를 담은 `missing_fragment` 행 하나로 표시합니다.

### 검사 분류 조정 {#tuning-the-taxonomy}

모든 설정은 `seo-pro.broken_links.inspections` 아래에 있습니다.

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

`rules`에서 클래스를 제거하면 **규칙 하나를 끄고**, `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`로 **분류 전체를 끌 수 있습니다**. 미리 알아둘 규칙 두 가지가 있습니다.

- `trailing_slash`는 **규칙을 선언하기 전까지 꺼져 있습니다**(`'always'` / `'never'`). `/x`와 `/x/`를 모두 `200`으로 제공하는 사이트에는 잘못된 스타일이 없기 때문입니다. 서버가 리디렉션으로 표준화하는 경우는 이미 `redirect_chain` 코드로 표시됩니다.
- `absolute_internal_link`는 절대 URL로 작성한 **모든** 내부 링크에 발생합니다. 사이트의 관례가 절대 내부 URL이라면 해롭지 않은 `notice` 수준 행이 많이 생깁니다. 알림을 끄려면 `rules`에서 제거하세요.

## 지속적 통합 {#continuous-integration}

링크 스캔과 [SEO 감사](/ko/pro/scan-issues)는 모두 **빌드를 실패 처리**하고 **보고서 산출물을 기록**할 수 있습니다. Rankbeam을 대시보드뿐 아니라 품질 통과 조건으로 사용할 수 있습니다. `--fail-on-error`는 깨진 링크나 심각한 문제인 `critical` 수준에 대응합니다. `--fail-on-warning` 옵션은 `critical` **또는** `warning`에서 실패하며 별도의 “error” 수준은 없습니다.

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` 옵션은 산출물을 기록하며 디렉터리를 지정하면 파일 이름을 도출합니다. `--format` 옵션은 `json`(기본), `md`, `html` 중 하나입니다. 파이프라인에서 파싱할 때는 JSON, 실행에 첨부할 독립 페이지에는 HTML을 사용하세요.

### GitHub Actions {#github-actions}

크롤러는 HTTP로 페이지를 가져오므로 CI에서 접근 가능한 콘텐츠를 지정해야 합니다. 아래의 로컬 앱 또는 `SEO_PRO_BROKEN_LINKS_BASE_URL`로 스테이징 URL을 사용하고, 시작 URL을 모을 수 있게 모델/사이트맵을 등록하세요.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## 예약 {#scheduling}

`routes/console.php`에 크롤링과 정리 작업을 등록하세요.

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## 명령 참조 {#command-reference}

| 명령 | 기능 |
| --- | --- |
| `seo-pro:broken-links-scan` | 범위가 제한되고 재개 가능한 크롤링을 큐에 등록(`--scope=internal_only\|internal_and_external`, 추가 시작 URL `--url=*`) |
| `seo-pro:broken-links-status` | 최근 크롤링 요약, 미해결 깨진 링크, 이번 실행의 검사 수; **CI 검사**(`--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html`) |
| `seo-pro:broken-links-cancel` | 실행/대기 중 크롤링 취소(`{run?}` — 기본값은 가장 최근의 활성 실행) |
| `seo-pro:broken-links-recover` | 중단된 워커가 남긴 크롤링 실패 처리(오래된 임대) |
| `seo-pro:broken-links-prune` | 크롤러 보존 정책 적용(오래된 실행 + 해결된 발견 사항) |

## 조정 {#tuning}

실행당 페이지 수, 페이지당 링크 수, 작업별 상한, 엄격한 시간 예산, 호스트별 요청 간 지연은 모두 `seo-pro.broken_links`에 있습니다. 기본값은 보수적이며 유한합니다. 늘리기 전에 [운영 환경 설정의 배치 조정 표](/ko/pro/production)를 참고하세요.

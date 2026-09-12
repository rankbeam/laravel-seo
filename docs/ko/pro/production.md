---
description: "전용 큐, 스케줄러, 재시도와 복구, 보존, 측정 데이터로 Pro를 대규모 운영하세요. 약 900페이지 운영 설치의 Filament 독립 구성을 설명합니다."
---

# 운영 환경 설정 {#production-setup}

사이트 스캔, 깨진 링크 크롤러, 선택적 리디렉션 접속 횟수 반영, 404 정리 등 Pro의 일상 작업은 Laravel 큐와 스케줄러에서 실행됩니다. 이 문서는 전용 큐, 스케줄러, 재시도와 복구 정책, 보존, 운영 상태를 관측할 측정 데이터를 다루는 대규모 운영의 기준 가이드입니다. 하루 약 20k 방문, 약 900페이지의 실제 운영 설치 구성을 재현할 수 있도록 설명합니다.

모든 내용은 **Filament와 독립적**입니다. 패널 유무와 관계없이 엔진, 명령, 큐, 측정 데이터가 동일합니다. Filament는 위에 화면을 추가하며 예약이나 처리 방식을 바꾸지 않습니다.

[[toc]]

## 안전한 도입 순서 {#safe-rollout-order}

다음 순서로 진행하세요. 각 단계는 다음 단계 전에 검증할 수 있습니다.

1. **설치** — 설정과 마이그레이션을 게시하고 실행합니다.

   ```bash
   php artisan seo-pro:install
   ```

   `seo-pro:install` 명령은 `config/seo-pro.php`와 Pro 마이그레이션을 게시한 뒤 `migrate`를 실행합니다. Pro 마이그레이션은 **게시 후에만 사용**하며 패키지가 자동 로드하지 않으므로, 단순 `composer require` 상태를 작동하는 스키마로 만드는 단계입니다. 멱등적이어서 언제든 다시 실행할 수 있습니다. 게시 파일을 덮어쓰려면 `--force`, 마이그레이션 없이 게시하려면 `--no-migrate`를 추가하세요.

2. 서비스 프로바이더(`AppServiceProvider::boot()`)에서 **스캔 대상을 등록**합니다.

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. 백그라운드 작업을 켜기 전에 구성을 **검증**합니다.

   ```bash
   php artisan seo:doctor
   ```

   모든 경고를 해결하세요. 각 경고에는 정확한 명령/설정 줄이 있습니다. CI에서는 `--json` 옵션을 추가하고 안정적인 검사 id를 기준으로 처리하세요.

4. 아래에 따라 **큐와 스케줄러를 설정**하고 큐 워커와 `schedule:run` cron 항목을 배포합니다.

5. **선택적 기능은 마지막에 활성화**합니다. 깨진 링크 크롤러, AI 지원, Search Console은 모두 기본적으로 꺼져 있습니다. 크롤러는 단계 1에서 게시한 테이블의 마이그레이션과 아래의 전용 워커가 필요합니다.

**Pro 2.41.0으로 업그레이드:** 스캔 워커를 일시 중지하고 `php artisan vendor:publish --tag=seo-pro-migrations --force`로 마이그레이션을 게시한 뒤 `php artisan migrate`를 실행하세요. 이후 워커를 재시작하고 `php artisan seo:doctor`를 실행하세요. 새 `seo_scan_target_completions` 테이블과 `seo_scan_runs.target_tracking` 열이 필수입니다. 실행/대상별 처리 기록이 중복 종료 결과로 카운터가 부풀지 않게 하며 처음 수락한 결과가 적용됩니다. 처리한 대상이 없는 기존 큐 실행은 계속됩니다. 업그레이드 전 일부 처리된 실행은 이력을 보존하지만 다음 전달 시 새 스캔 안내와 함께 종료합니다. 재시도를 소진한 대상은 새 실행에서 재시도하세요. 롤백하려면 워커를 중지하고 마이그레이션을 되돌리기 전에 코드를 복원하세요. 이후 스캔까지 되돌려야 한다면 업그레이드 전 데이터베이스 백업을 보관하세요.

## 작업 유형별 전용 큐 {#dedicated-queues-per-workload}

오래 걸리는 스캔이나 크롤링이 메일, 알림 등 사용자 대상 작업 앞을 막아서는 안 됩니다. SEO 작업 유형마다 전용 큐와 워커를 지정하세요.

스캔 파이프라인과 깨진 링크 크롤러는 각각 설정 가능한 큐를 읽습니다.

| 작업 유형 | 설정 | 환경 변수 | 기본 큐 |
|---|---|---|---|
| 온페이지 스캔 작업 | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | 기본 큐 |
| 깨진 링크 크롤링 작업 | `seo-pro.broken_links.queue.name` (+ `.connection`) | `SEO_PRO_BROKEN_LINKS_QUEUE` (+ `_CONNECTION`) | `seo-broken-links` |

### Redis 예시 (운영 구성) {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

큐마다 별도 프로세스 / Supervisor 프로그램으로 워커를 실행하세요.

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

크롤링 워커의 `--timeout` 값은 `seo-pro.broken_links.batch.hard_time_budget_seconds`(기본 180)와 HTTP 시간 제한의 합보다 커야 합니다. 배치가 상태 기록 도중 종료되지 않도록 하기 위해서입니다. 작업은 자체 `$timeout` 값을 그 합으로 설정하므로 워커 플래그도 맞추세요. 크롤링에는 `--tries=1` 옵션을 사용하세요. 중단된 작업은 다음 연속 작업 또는 `seo-pro:broken-links-recover`가 회수하므로 큐 수준 재시도가 필요하지 않습니다.

`seo:doctor` 명령은 작업 유형별 큐를 보고하고, 인라인 실행으로 차단을 일으키는 `sync`로 결정되면 경고합니다.

## 스케줄러 {#scheduler}

Laravel 11, 12, 13은 **`routes/console.php`**에서 예약합니다. `app/Console/Kernel.php`의 `schedule()` 메서드는 Laravel 10에서 업그레이드한 앱에만 있으며, 앱에 남아 있다면 같은 항목을 그곳에 넣으세요. 매분 스케줄러를 실행하도록 시스템 cron 하나를 추가하세요.

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

그런 다음 모든 반복 명령을 권장 주기로 등록하세요.

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

권장 주기 요약:

| 명령 | 주기 | 이유 |
|---|---|---|
| `seo:sitemap` | 매일 | 현재 콘텐츠에서 사이트맵 갱신 |
| `seo-pro:scan` | 매주(변경이 잦으면 매일) | 모든 대상 재감사 |
| `seo-pro:scan-recover` | 매시간 | 중단된 워커가 남긴 실행 회수 |
| `seo-pro:scan-prune` | 매일 | 스캔 실행 보존 기간 적용 |
| `seo-pro:redirects-flush-hits` | `redirects.hits.flush_immediately=false`일 때만 5분마다 | 캐시의 접속 횟수 일괄 집계를 DB에 반영 |
| `seo-pro:404-prune` | 매일 | 보존 기간 및 행 수 상한에 맞춰 404 로그 정리 |
| `seo-pro:404-recheck` | 매일 | 미해결 404 경로를 다시 요청하고 원본이 수정되어 200인 항목을 복구로 표시 |
| `seo-pro:broken-links-scan` | 매주 | 깨진 링크 재크롤링(확정은 여러 스캔에 걸쳐 수행) |
| `seo-pro:broken-links-recover` | 매시간 | 중단된 워커가 남긴 크롤링 회수 |
| `seo-pro:broken-links-prune` | 매일 | 크롤러 보존 기간 적용 |

`seo-pro:scan` 명령과 `seo-pro:broken-links-scan` 명령은 작업을 **큐에 넣기만** 하며 실제 처리는 워커가 합니다. 복구/정리 명령은 가볍게 인라인으로 실행됩니다.

::: tip 여러 스캔에 걸친 깨진 링크 확정
링크는 `seo-pro.broken_links.mark_broken_after_failures`번 **연속 스캔**에서 접근에 실패해야 깨진 것으로 표시됩니다. 한 번이라도 성공하면 카운터가 초기화됩니다. 그래서 일회성이 아닌 예약 크롤링을 사용합니다. 일시적인 장애 한 번으로 링크를 표시하지 않습니다. 기본값 3에서 주간 스캔은 첫 실패 관측 후 약 2주, 실제 고장 후 최대 약 3주에 확정합니다. 더 빨리 확정하려면 주기를 줄이거나 임계값을 낮추세요.
:::

## 배치 조정 (깨진 링크 크롤러) {#batch-tuning-broken-link-crawler}

크롤링은 범위가 제한되고 스스로 다음 작업을 예약하는 여러 작업으로 나뉘어 실행됩니다. 기본값은 유한합니다. 사이트와 검사 대상 호스트의 처리 능력에 맞게 `seo-pro.broken_links`에서 조정하세요.

| 키 | 기본값 | 제한 대상 |
|---|---|---|
| `max_pages_per_run` | `2000` | 전체 실행의 페이지 요청 수. `null` 값은 명시적 무제한 선택이며 기본값이 아님 |
| `max_links_per_page` | `200` | 페이지별 링크 검사 수 |
| `max_total_links` | `null` | 전체 실행의 선택적 링크 검사 상한 |
| `batch.max_pages_per_job` | `50` | 큐 작업당 페이지 수 |
| `batch.max_links_per_job` | `1500` | 큐 작업당 링크 검사 수 |
| `batch.hard_time_budget_seconds` | `180` | 이 시간 후 **새 요청을 시작하지 않고** 다음 작업 예약 |
| `batch.dispatch_delay_seconds` | `1` | 연속 작업 사이 지연 |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | 요청별 제한 |
| `http.max_response_bytes` | `seo-pro.http.max_response_bytes` 상속 | 페이지 및 대상 검사 응답 본문의 스트리밍 상한 |
| `seed.max_response_bytes` | 크롤러/공유 HTTP 상한 상속 | 시작 URL 수집 중 가져오는 원시 사이트맵 XML / `.gz` 바이트 |
| `seed.max_inflated_bytes` | 시작 수집/크롤러/공유 상한 상속 | `.gz` 사이트맵의 압축 해제 후 허용 바이트 |
| `http.per_host_delay_ms` | `0` | 서버 배려를 위한 검사 사이 지연(`internal_and_external`에서 늘릴 것) |

`batch.hard_time_budget_seconds`를 크롤링 워커의 `--timeout`보다 충분히 작게 유지하세요. 진행 중 요청을 중간에 멈출 수는 없으며 `http.timeout`로 제한됩니다. 따라서 워커 시간 제한은 작업 시간 예산 + HTTP 시간 제한 + 여유 시간입니다.

`internal_and_external` 크롤링에서는 SsrfGuard가 외부 검사를 허용하도록 `seo-pro.http.scope` 또는 `seo-pro.http.allowed_hosts` 범위를 넓히세요. 외부 호스트를 너무 빠르게 요청하지 않도록 `http.per_host_delay_ms`도 늘리세요. 크롤링 범위는 외부인데 가드가 모든 검사를 막는 구성이라면 `seo:doctor` 명령이 경고합니다.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

큐당 프로그램 하나를 사용합니다. `/etc/supervisor/conf.d/app-workers.conf` 예시:

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

`stopwaitsecs`는 워커의 `--timeout`보다 커야 정상 재시작 중 배치 작업을 강제 종료하지 않습니다.

### Horizon {#horizon}

Horizon을 사용한다면 `config/horizon.php`에 작업 유형별 supervisor를 정의하고 Supervisor 대신 프로세스를 관리하게 하세요.

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## 재시도 및 실패 처리 {#retry-failure-handling}

스캔 대상 작업은 설정의 자체 재시도 정책을 사용하며 워커의 `--tries`에 의존하지 **않습니다**.

| 키 | 기본값 | 의미 |
|---|---|---|
| `seo-pro.scan.tries` | `3` | 대상 작업별 시도 횟수 |
| `seo-pro.scan.backoff` | `30` | 시도 사이 간격(초) |
| `seo-pro.scan.timeout` | `300` | 대상 작업별 시간 제한(중복 방지 잠금은 시간 제한 + 60 후 만료) |

재시도를 소진한 작업은 대상을 **실패**로 기록하며 실행 자체는 `partial` 또는 `failed`로 완료됩니다. 처리된 대상 실패로 실행이 `running` 상태에 남지는 않습니다. 상태 기록 전에 워커가 종료된 경우에는 아래 복구 정리가 필요합니다. 실패는 표준 `failed_jobs` 테이블에 기록되므로 일반적인 방식으로 관리하세요.

```bash
php artisan queue:failed
php artisan queue:retry all
```

테이블 크기를 제한하도록 SEO 일정과 함께 `queue:prune-failed`를 예약하세요.

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

깨진 링크 크롤링은 `--tries=1` 옵션을 사용합니다. 중단된 작업은 임대 하트비트가 오래되면 다음 연속 작업 또는 `seo-pro:broken-links-recover`가 회수하므로 큐 재시도는 작업을 중복시킬 뿐입니다.

## 복구 {#recovery}

작업 도중 워커가 종료되면 진행 상황 집계가 스스로 복구되지 못합니다. 이를 처리하는 두 정리를 모두 **매시간** 예약하세요.

- `seo-pro:scan-recover` — `seo-pro.scan.recovery.stuck_scan_timeout_hours`(기본 2) 동안 진행이 없는 온페이지 스캔 실행을 실패로 표시합니다.
- `seo-pro:broken-links-recover` — 임대 하트비트가 오래된 크롤링 실행(`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, 기본 2)을 회수해 실패로 표시하고 범위별 활성 실행 하나의 자리를 비웁니다.

`seo:doctor`는 이를 **최근 하트비트 증거**로 보여 줍니다. 스캔을 사용하기 시작하면 멈춘 실행을 보고하고 복구 명령을 안내합니다. cron이 실제 실행 중인지 증명할 수는 없습니다. 어떤 명령도 그것을 증명하지 못하며 실행 이력이 보여 주는 사실을 보고합니다.

## 보존 {#retention}

테이블 크기를 제한하세요. 기본값은 모두 `seo-pro.*`에 있으며 `null` 값은 해당 정리를 끕니다.

| 데이터 | 설정 | 기본값 | 명령 |
|---|---|---|---|
| 스캔 실행(+ 문제) | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| 404 로그 | `monitor_404.retention_days` (+ `max_rows` `10000`) | `90` | `seo-pro:404-prune` |
| 크롤링 실행 | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| 해결된 발견 사항 | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## 운영 측정 데이터 {#operational-telemetry}

온페이지 스캔과 깨진 링크 크롤링의 **모든** 완료 실행은 로깅 스택을 통해 구조화된 완료 기록 한 줄을 내보냅니다. 패널 없이도 지표 이력을 얻을 수 있습니다. 페이로드에는 개수와 시간만 있으며 URL, 본문, 헤더, 방문자 데이터는 없습니다.

| 지표 | 스캔 | 크롤링 |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (SSRF가 거부한 대상) | — | ✓ |
| `transient_failures` (네트워크 실패, 다음 스캔에서 재검사) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (큐 등록 → 첫 배치) | ✓ | ✓ |

`seo-pro.telemetry`에서 설정하세요.

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

`channel` 값을 전용 로그 채널로 지정해 앱 로그와 섞지 않고 Loki / Datadog / CloudWatch 같은 저장소로 보내세요.

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

더 세밀하게 처리하려면 이벤트를 직접 구독하세요. 각 이벤트가 같은 `metrics()` 페이로드를 제공합니다.

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

측정 데이터 전송은 최선 노력 방식입니다. 채널 설정이 잘못되어도 스캔을 실패시키지 않습니다.

## Filament와 독립적인 배포 {#filament-independent-deployment}

이 페이지의 어떤 내용도 패널이 필요하지 않습니다. 엔진, 모든 명령, 큐, 스케줄러, 복구, 보존, 측정 데이터는 헤드리스에서도 같습니다. Filament 패널(`SeoProPlugin`)은 실시간 스캔 진행, 문제 표, 리디렉션 CRUD, 404 모니터, 깨진 링크 대시보드 같은 **화면**만 추가합니다. 엔진을 배포하고 CLI와 스케줄러로 운영한 뒤 필요할 때 패널을 추가해도 마이그레이션하거나 다시 작업할 필요가 없습니다. 패널을 추가하지 않아도 됩니다. 전체 명령 참조는 [헤드리스 사용](/ko/pro/headless)을 참고하세요.

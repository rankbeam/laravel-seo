---
description: "어떤 AI 크롤러가 사이트를 가져왔는지, 접속 빈도와 마지막으로 접근한 URL 및 상태를 기록하세요. AI 크롤러 제어의 관측 기능입니다."
---

# AI 봇 모니터 {#ai-bot-monitor}

요청은 검증된 봇 신원이 아니라 **user-agent 일치**를 기준으로 분류합니다. 모니터는 관측한 요청을 기록하며, user-agent는 위조될 수 있습니다.

코어의 [AI 크롤러 제어](/ko/guide/ai-crawlers)는 `robots.txt`가 AI 크롤러에 무엇을 *요청할지* 결정합니다. Pro의 **AI 봇 모니터**는 그에 대응하는 관측 기능입니다. 실제로 *무슨 일이 있었는지*, 즉 어떤 AI 크롤러가 사이트를 가져왔고 얼마나 자주 접속했으며 마지막으로 접근한 URL과 HTTP 상태는 무엇인지 기록합니다.

404 모니터의 기반 구조(응답 후 실행되는 전역 미들웨어, 접속 횟수를 집계하는 upsert 모델, 동일한 개인정보 보호 원칙)를 재사용합니다. 다만 경로 대신 **봇**을 키로 사용하며 **모든** 응답 상태에서 기록합니다. 404 모니터가 의도적으로 제외하는 바로 그 AI 크롤러가 대상입니다. 봇 식별에는 코어의 `AiCrawlerRegistry`를 재사용하므로 robots.txt 정책과 관측 트래픽이 같은 기준 데이터를 공유합니다.

::: tip 코어 ≥ 3.3 필요
모니터는 코어 AI 크롤러 목록([`SEO::aiCrawlers()`](/ko/guide/ai-crawlers))으로 봇을 식별합니다. 이전 코어 버전에서는 동작하지 않습니다.
:::

## 활성화 {#enabling-it}

기본적으로 꺼져 있습니다. 켜면 전역 미들웨어가 각 응답 후 일치하는 크롤러를 기록합니다. 페이지 응답을 지연시키지 않습니다.

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

이것으로 설정이 끝납니다. 미들웨어는 자동 등록되며 `ai_bots.auto_register_middleware`로 등록을 끌 수 있습니다. 알려진 봇당 한 행을 upsert하므로 테이블 크기는 목록의 봇 수로 제한됩니다.

## 로그 읽기 {#reading-the-log}

### 헤드리스 {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

각 행은 `bot`, `label`, `operator`, `purpose`, `hit_count`, `last_path`, `last_status`, `first_seen_at`, `last_seen_at`를 제공합니다.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Pro 플러그인을 등록하면 SEO 탐색 그룹 아래에 **AI 봇** 표가 나타납니다. 봇, 운영사, 목적, 접속 횟수, 마지막 상태, 마지막 경로, 마지막 접속 시간을 보여 주며 목적별 필터를 제공하는 읽기 전용 표입니다.

## 개인정보 보호 {#privacy}

404 모니터와 같은 원칙으로, **기본적으로 IP를 저장하지 않습니다.** `ai_bots.hash_ip`를 활성화해도 키를 사용하는 sha256(`ip_hash`)만 저장하며 원시 IP는 기록하지 않습니다.

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## 기간별 지표 (일별 버킷) {#period-metrics-daily-buckets}

누적 테이블은 봇당 한 행을 유지합니다. 순위표에는 적합하지만 **특정 기간에** 봇이 *몇 번 접속했는지*, *서로 다른 URL을 몇 개 가져왔는지*는 알 수 없습니다. 기본값인 `daily_enabled` 활성 상태에서는 각 접속을 경로별 일 단위 버킷(`seo_ai_bot_daily`)에도 기록합니다. 따라서 [화이트라벨 보고서](/ko/pro/reports)에 누적 값의 차이가 아닌 **실제** 기간별 수치, 즉 지난 보고서 이후 접속 횟수와 이번 기간의 고유 URL 수가 표시됩니다.

누적 로그를 봇당 한 행으로 설계한 이유인 크기 제한도 유지합니다.

- 봇별·일별 **고유 경로 수 상한**(`daily_max_paths`): 이를 넘으면 해당 봇의 새로운 경로를 하나의 초과 버킷으로 합칩니다. 하루 총 접속 횟수는 정확하게 유지하면서 행 수가 무제한 늘어나는 것을 막습니다. 상한에 도달한 고유 URL 수는 “N+”로 표시합니다.
- `seo-pro:ai-bots-prune`로 정리하는 **보존 기간**(`daily_retention_days`).

누적 순위표만 유지하려면 `daily_enabled`를 `false`로 지정하세요. 그러면 보고서의 “지난 보고서 이후” 값은 이전 보고서 스냅샷과의 차이로 대체됩니다. 기존 버킷은 무시하므로 오래된 테이블을 읽지 않습니다.

기간별 수치는 **일 단위 정밀도**입니다. “지난 보고서 이후”는 이전 보고서가 작성된 날짜부터 하루 전체를 세므로, 그날의 접속은 정확한 생성 시각보다 앞이나 뒤에 있을 수 있습니다. 일반적인 일간/주간/월간 주기에서는 이 경계 오차가 미미합니다.

## 관측에서 제어로 {#turning-observation-into-control}

모니터는 *누가* 크롤링하는지 알려 주고, 코어 [AI 크롤러 제어](/ko/guide/ai-crawlers)는 *무엇을 가져가도 되는지* 결정합니다. 제한하고 싶은 학습 봇이 나타났다면 다음과 같이 설정하세요.

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

일부 봇은 `robots.txt`를 준수하지 않는다고 문서에 명시되어 있습니다. 모니터로 이를 발견하고 엣지(방화벽 / WAF / Cloudflare)에서 차단할지 결정할 수 있습니다.

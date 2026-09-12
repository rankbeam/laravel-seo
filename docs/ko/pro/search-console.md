---
description: "엄격한 읽기 전용 Google Search Console 패널입니다. 스캐너가 아는 페이지에 상위 검색어와 페이지의 노출, 클릭, CTR, 게재순위를 연결합니다. 기본적으로 꺼져 있습니다."
---

# Search Console (읽기 전용) {#search-console-read-only}

**읽기 전용** Google Search Console 패널입니다. 상위 검색어와 페이지의 **노출, 클릭, CTR, 평균 게재순위**를 스캐너가 이미 아는 페이지와 연결해, *“이 페이지에는 문제가 **있고** 노출도 줄고 있다”*는 사실을 한곳에서 볼 수 있습니다. **기본적으로 꺼져 있습니다**.

세 가지 설계 원칙을 따릅니다.

- **엄격한 읽기 전용.** 패키지에 고정된 OAuth 범위 `webmasters.readonly` 하나만 요청합니다. Search Analytics 읽기만 가능하며 사이트맵 제출, 색인 요청, Search Console 변경은 하지 않습니다. 범위를 넓히는 설정도 없습니다.
- **자신의 속성, 자신의 자격 증명.** 요청은 *자신의 서버*에서 Google로 직접 전송하며 *자신의* 서비스 계정 또는 OAuth 자격 증명으로 인증합니다. 프록시, 사용량 집계 과금, 재판매를 거치지 않으며 패키지는 원격 측정 데이터를 보내지 않습니다.
- **오류는 해당 문맥에 표시.** 자격 증명 누락, 403, 할당량 오류, 시간 초과는 패널 렌더링을 중단하지 않고 인라인 메시지로 표시합니다. 아래에 설명한 이력 동기화 명령은 실패를 보고하고 이후 날짜의 수집을 중단합니다.

## 제공 내용 {#what-you-get}

- **확인이 필요한 페이지** — **미해결 스캔 문제**가 있으면서 **검색 트래픽을 계속 받는** 페이지를 연결합니다. 문제가 있는 페이지 중 노출 수가 많은 순서로, 기회 손실이 큰 항목을 먼저 보여 줍니다. 우선 수정할 대상입니다.
- **상위 페이지**와 **상위 검색어** — 일반적인 Search Analytics 표입니다.

Filament 대시보드에서는 *SEO* 탐색 그룹의 **Search Console** 페이지로 표시되며 연동이 켜져 있을 때만 나타납니다. 헤드리스에서는 `seo-pro:search-console` 명령과 `SeoPro::searchConsole()`로 같은 지표를 가져옵니다.

## 설정 {#setup}

Search Console 속성을 읽을 수 있는 Google 자격 증명이 필요합니다. 두 모드를 지원하며 서버에서는 **서비스 계정**이 가장 간단합니다.

### 서비스 계정 (권장) {#service-account-recommended}

1. Google Cloud에서 **Search Console API**를 활성화하고 **서비스 계정**을 만든 뒤 JSON 키를 내려받으세요.
2. Search Console → *설정 → 사용자 및 권한*에서 서비스 계정 이메일(`…@….iam.gserviceaccount.com`)을 사용자로 추가하세요. 읽기 전용에는 제한된 권한이면 충분합니다.
3. 패키지에 키와 속성을 지정하세요.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

`SEO_PRO_GSC_SITE_URL` 값을 생략하면 `app.url`에서 URL 접두사 속성을 도출합니다.

### OAuth (오프라인 갱신 토큰) {#oauth-offline-refresh-token}

OAuth 클라이언트와 장기 **갱신 토큰**이 있다면 아래처럼 설정하세요. `webmasters.readonly`만 허용한 토큰을 권장합니다. 갱신할 때마다 해당 범위를 요청합니다. 응답이 정확히 읽기 전용 범위를 명시적으로 확인하지 않으면 반환된 토큰을 거부합니다. Google이 더 넓은 권한을 항상 좁혀 준다고 가정하지 않습니다.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### 토큰 마이그레이션 게시 {#publish-the-token-migration}

암호화된 액세스 토큰 캐시는 `seo_gsc_tokens` 테이블에 있습니다. 한 번 게시하고 마이그레이션하세요.

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

그런 다음 `php artisan seo:doctor`로 연결 구성을 확인하세요. 네트워크 요청이나 비밀 값 출력 없이 Search Console의 활성화 및 설정 여부를 보고합니다.

## 헤드리스 사용 {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## 과거 지표 {#historical-metrics}

위의 패널과 명령은 **실시간 이동 기간**을 읽으며 Search Console 자체가 유일한 저장소입니다. 과거 기간을 조회할 수 있는 **일 단위 이력**을 얻으려면 동기화 명령을 실행하세요. 날짜별 검색어 및 페이지 지표를 `seo_gsc_metrics` 테이블에 저장합니다.

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **첫 실행은 `sync.backfill_days`만큼 과거 데이터를 채웁니다**. 기본값은 90일이며 Search Console이 약 16개월을 보존하므로 더 가져오려면 늘리세요. 이후 실행은 **마지막 저장 날짜부터 재개**하고, 최근 데이터의 늦은 확정을 반영하도록 끝부분의 `sync.overlap_days`를 다시 가져옵니다. 기간 끝은 데이터 지연을 고려해 항상 3일 전입니다.
- **멱등적.** `(date, dimension, key)`를 기준으로 upsert하므로 다시 실행해도 안전합니다. 특정 날짜에서 할당량 오류 등으로 실패하면 실행을 정상적으로 중단하고 저장한 행 수를 보고합니다. 다음 실행은 중단한 곳부터 재개합니다.
- **활용 기능.** 테이블이 두 기간을 모두 포함하면 화이트라벨 [보고서](/ko/pro/reports)의 Search Console **주요 변화**가 이전 보고서 스냅샷 차이 대신 실제 기간별 이력(이번 기간과 바로 앞의 같은 길이 기간) 비교로 전환됩니다. 더 풍부한 검색어 분석의 기반이기도 합니다.

집계 지표만 저장합니다. 날짜별 검색어 텍스트, 페이지 URL, 네 지표(클릭, 노출, CTR, 게재순위)입니다. 사용자별 또는 요청별 데이터는 가져오거나 기록하지 않습니다.

## 데이터 처리 및 보안 {#data-handling-security}

- **읽기 전용 범위 확인.** 서비스 계정 JWT는 `webmasters.readonly`만 요청합니다. OAuth 갱신도 같으며, 범위가 없거나 더 넓은 응답은 거부합니다. 읽기 권한만 승인된 자격 증명을 사용하세요. 패키지에는 Search Console을 변경하는 호출이 없습니다.
- **자격 증명은 환경에 유지.** 서비스 계정 키 또는 OAuth 비밀 + 갱신 토큰은 AI 키와 마찬가지로 호출 시 **이름이 지정된** 환경 변수에서 읽습니다. 따라서 `php artisan config:cache`가 이를 `bootstrap/cache/config.php`에 쓰지 않습니다. 설정 캐시로 `.env` 로드가 막히면 프로세스 환경에서 사용할 수 있도록 하세요.
- **저장 토큰 암호화.** 자격 증명으로 발급받은 단기 액세스 토큰은 앱 키로 **암호화**해 `seo_gsc_tokens`에 저장하며 만료가 가까워질 때까지 재사용합니다. 화면을 볼 때마다 토큰 교환이 실행되지 않습니다. 장기 자격 증명은 데이터베이스에 저장하지 않고 환경에만 둡니다.
- **모든 요청에 SSRF 보호 적용.** 토큰 교환과 Search Analytics 호출은 공유 `SsrfGuard`를 거칩니다. HTTPS만 허용하고 호스트가 공용 주소로 해석되어야 하며 리디렉션은 꺼져 있어 내부 서비스로 요청을 돌릴 수 없습니다.
- **비밀 값은 로그에 남기지 않음.** 액세스 토큰, 키, 인증 헤더는 로그에 기록하지 않습니다. API 오류는 민감한 내용을 제거하고 길이를 제한한 Google 자체 오류 메시지만 표시합니다.
- **지표 로컬 캐시.** `seo-pro.search_console.cache_ttl`초(기본 30분) 동안 캐시해 패널 렌더링마다 API를 재호출하지 않습니다. 실시간 패널/명령은 캐시와 암호화된 액세스 토큰 외에는 영구 저장하지 않습니다. 선택적으로 실행하는 `seo-pro:gsc-sync` 명령만 `seo_gsc_metrics`에 날짜별 검색어/페이지 집계 지표를 영구 저장하며 사용자별 데이터는 없습니다.

## 설정 참조 {#configuration-reference}

모든 키는 `config/seo-pro.php` → `search_console` 아래에 있습니다.

| 키 | 기본값 | 목적 |
| --- | --- | --- |
| `enabled` | `false` | 전체 스위치(`SEO_PRO_GSC_ENABLED`). |
| `connection` | `service_account` | `service_account` 또는 `oauth`. |
| `site_url` | `app.url`에서 도출 | 속성(`https://example.com/` 또는 `sc-domain:example.com`). |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | 키 JSON 또는 경로를 담은 환경 변수의 **이름**. |
| `oauth.client_id` | — | OAuth 클라이언트 id(비밀 아님). |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | 클라이언트 비밀을 담은 환경 변수의 **이름**. |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | 갱신 토큰을 담은 환경 변수의 **이름**. |
| `default_days` | `28` | 보고 기간(GSC 데이터 지연으로 3일 전에 끝남). |
| `row_limit` | `100` | 보고서별 상위 N행(API 최대 25000). |
| `cache_ttl` | `1800` | 가져온 보고서의 캐시 시간(초). |
| `sync.backfill_days` | `90` | 빈 테이블에서 첫 `gsc-sync`가 가져올 일수. |
| `sync.overlap_days` | `2` | 늦은 확정을 반영해 매 실행 다시 가져올 마지막 일수. |
| `sync.row_limit` | `5000` | 동기화가 요청하는 날짜별·차원별 최대 행 수. |

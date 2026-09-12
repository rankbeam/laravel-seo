---
description: "URL을 게시하거나 갱신하는 즉시 검색 엔진에 알리세요. Pro는 참여 엔진으로 전달되는 공용 api.indexnow.org 엔드포인트에 제출합니다. 기본적으로 꺼져 있습니다."
---

# IndexNow — 게시 시 색인 알림 전송 {#indexnow-—-push-on-publish-indexing}

크롤러가 변경된 페이지를 찾을 때까지 기다리는 대신, **IndexNow**로 URL을 게시하거나 갱신하는 즉시 검색 엔진에 *알릴* 수 있습니다. Pro는 공용 `api.indexnow.org` 엔드포인트에 제출하며, 한 번의 호출이 **모든 참여 엔진으로 전달됩니다**. 엔진별로 요청을 나누어 보내지 않습니다. [공식 FAQ](https://www.indexnow.org/faq)는 Amazon, Bing, Naver, Seznam, Yandex, Yep을 나열합니다. 알림은 색인 생성을 보장하지 않습니다.

**기본적으로 꺼져 있습니다**. 활성화한 뒤 URL을 제출하기 전에는 네트워크 요청이 발생하지 않습니다.

## 설정 {#setup}

### 1. 키 생성 {#_1-generate-a-key}

IndexNow는 호스트 제어 권한을 확인하기 위해 **키**를 사용합니다. Pro는 `[a-f0-9-]` 문자로 구성된 8–128자 키를 허용합니다. 32자 16진수 문자열이 적합합니다. 한 번 생성한 키를 유지하고 환경 변수를 통해 제공하세요.

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip 키를 설정 계층에서 읽으므로 `config:cache` 이후에도 사용할 수 있습니다
Search Console 자격 증명과 달리 IndexNow 키는 **비밀이 아닙니다**. 호스트 소유권을 증명하기 위해 `/{key}.txt`에 공개됩니다. 따라서 Pro는 기본값이 `env('SEO_PRO_INDEXNOW_KEY')`인 설정 계층 `indexnow.key`를 통해 읽습니다. 이는 의도된 동작입니다. `config:cache` 이후 Laravel은 `.env` 파일을 더 이상 로드하지 않으므로 **그 파일에만 정의된** 값은 `env()`에서 사용할 수 없습니다. 실제 프로세스 환경 변수는 계속 사용할 수 있습니다. 설정을 통해 읽으면 `config:cache`가 값을 보관하므로 항상 사용할 수 있습니다. 대신 **키를 교체하면 `php artisan config:cache`를 다시 실행해야 합니다**. 키는 로그에 기록하지 않습니다. 운영 환경에서 키 파일이 404를 반환하면 [설정을 캐시한 서버](#config-cached-servers)를 참고하세요.
:::

### 2. 키 파일 제공 {#_2-serve-the-key-file}

IndexNow는 키만 담긴 `https://{host}/{key}.txt`를 가져와 소유권을 확인합니다. 기본값인 `route` 활성 상태에서는 **Pro가 대신 제공합니다**.

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

설정된 키 경로 하나만 제공됩니다. 이 라우트가 가로채는 다른 경로는 404를 반환하며, IndexNow가 비활성화되어 있으면 라우트 전체가 404를 반환합니다. 직접 또는 CDN에서 파일을 호스팅하려면 `route`를 끄고 `key_location`에 해당 URL을 지정하세요.

## URL 제출 {#submitting-urls}

### 저장 시 자동 제출 (게시 시 전송 경로) {#automatically-on-save-the-push-on-publish-path}

모델에 트레이트를 추가하고 `auto_submit`를 켜세요. 저장할 때마다 모델의 `getUrlForSEO()` 제출이 큐에 등록됩니다.

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

트레이트는 게시 조건을 따릅니다. `shouldSubmitToIndexNow(): bool`를 구현하면 완전히 제어할 수 있습니다. 없으면 `is_published` 속성을 사용하고, 그것도 없으면 저장할 때마다 제출합니다. 제출은 항상 **큐를 통해** 실행되므로 모델 저장이 네트워크 응답을 기다리지 않습니다.

### 수동 제출 {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()`는 기본적으로 큐를 사용합니다. 인라인으로 실행하려면 `queue: false`를 전달하세요.

### 명령줄에서 제출 {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning 동일 호스트만 허용
모든 URL이 `http(s)`인지, **그리고** 설정된 `host`에 속하는지 검증합니다. 다른 URL은 **제외**하며 개수만 세고 보내지 않습니다. 소유한 URL만 제출할 수 있고, 호스트가 다르면 엔드포인트도 거부합니다. `max_urls_per_request`(프로토콜 상한 10000)를 넘는 목록은 자동으로 나눠 처리합니다.
:::

## 설정 {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## 재시도 방식 {#how-retries-work}

큐의 `SubmitToIndexNowJob` 작업은 *재시도해야 하는* 실패를 구분합니다. `429`(요청 빈도 제한), `5xx` 또는 시간 초과는 `backoff`를 적용해 최대 `tries`회 재시도합니다. `400`/`403`/`422`는 잘못된 키나 호스트 불일치 같은 영구적인 클라이언트 오류이므로 로그를 남기고 **중단**하여 불필요한 재시도를 피합니다. `200`와 `202`(수신 완료 / 키 확인 대기)는 모두 성공으로 처리합니다.

운영 환경에서는 작업에 **전용 큐**를 지정해 느린 엔드포인트가 사용자 대상 작업을 지연시키지 않도록 하세요.

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## 문제 해결 {#troubleshooting}

### 설정을 캐시한 서버 {#config-cached-servers}

`indexnow.enabled`가 분명히 `true`인데도 운영 환경에서 `/{key}.txt`가 404를 반환하거나 제출이 아무 동작도 하지 않는다면, 대부분 `php artisan config:cache`를 실행한 서버에서 키가 **`.env`에만 존재하기 때문**입니다. 설정을 캐시하면 Laravel은 `.env`를 읽지 않으므로 `env('SEO_PRO_INDEXNOW_KEY')`가 `null`를 반환합니다. 키 파일 라우트가 등록되지 않고 모든 제출이 “설정되지 않음”으로 거부됩니다.

기본 설정은 `env(...)`에서 `indexnow.key` 값을 읽으므로 일반적인 구성은 캐시 생성 시 저장되어 정상 작동합니다. **설정을 게시한 뒤 `env(...)` 기본값을 제거했거나**, **`.env`에만 존재하는 사용자 지정 `key_env` 이름**으로 키를 설정한 경우에만 문제가 생깁니다. 해결 방법은 두 가지입니다.

1. **키를 설정에 유지**(권장) — `indexnow.key`를 `env('SEO_PRO_INDEXNOW_KEY')`로 두거나 리터럴 값을 지정한 뒤 `php artisan config:cache`를 다시 실행하세요. 나중에 키를 교체할 때도 캐시를 다시 만들어야 합니다.
2. **실제 환경 변수 주입** — `SEO_PRO_INDEXNOW_KEY`를 실제 OS/프로세스 환경 변수로 설정하세요(PHP-FPM 풀의 `env[...]`, systemd의 `Environment=` 또는 플랫폼의 환경 변수 설정). **`.env`에만 두면 안 됩니다**. 실제 OS 환경 변수는 설정 캐시 이후에도 읽을 수 있습니다.

`php artisan seo:doctor`를 실행해 확인하세요. 이 상태를 감지하면 **“IndexNow is enabled but no valid key resolves”**와 정확한 해결 방법을 보고합니다. 또한 설정이 캐시된 앱이 읽을 수 없는 키로 부팅되면 Pro가 프로세스당 한 번 경고를 기록합니다.

::: tip Google
Google은 IndexNow에 참여하지 **않습니다**. Google에는 [Search Console](/ko/pro/search-console) 연동과 최신 사이트맵을 사용하세요.
:::

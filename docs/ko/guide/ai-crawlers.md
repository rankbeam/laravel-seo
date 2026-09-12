---
description: "엄선된 AI 크롤러 목록에 허용·차단 정책을 적용해 관리형 robots.txt와 선택적 ai.txt를 생성하고, 사이트를 가져올 수 있는 봇을 직접 선택하세요. 무료 코어 기능입니다."
---

# AI 크롤러 제어 (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

주요 AI 운영사는 이름이 지정된 봇으로 웹을 크롤링하며, 대부분 **robots.txt**를 읽어 가져올 수 있는 콘텐츠를 판단합니다. Rankbeam은 엄선된 봇 목록을 제공하고, 간단한 허용·차단 정책으로 관리형 `robots.txt`와 선택적 `ai.txt`를 생성합니다. **AI 검색 및 어시스턴트 크롤러는 허용하고, 콘텐츠를 학습에 사용하는 크롤러는 제한할 수 있습니다.**

무료 코어 기능입니다. Pro 패키지는 여기에 [관측 기능인 AI 봇 접속 로그](/ko/pro/ai-bot-monitor)를 더해 실제로 방문한 AI 크롤러를 보여 줍니다.

## 기본 정책 {#the-default-policy}

목록의 각 봇에는 주된 목적이 지정되어 있습니다.

| 목적 | 하는 일 | 기본값 |
| --- | --- | --- |
| `ai_search` | **AI 검색** 답변을 위한 색인을 만들려고 페이지를 가져옵니다(AI 유입 채널). | **허용** |
| `ai_assistant` | 채팅에서 **사용자**를 대신해 실시간으로 페이지를 가져옵니다. | **허용** |
| `ai_training` | 모델 **학습**을 위해 콘텐츠를 수집합니다. | **차단** |

ChatGPT 검색, Perplexity 등의 AI 검색 및 어시스턴트 크롤러에는 접근을 허용하면서 학습 데이터 사용은 거부하는, 많은 콘텐츠 발행자의 방식을 따릅니다. 모든 항목은 설정에서 바꿀 수 있습니다.

::: warning 접근 허용은 인용을 뜻하지 않습니다
크롤러를 허용하면 검색·수집이 *가능*해질 뿐, 발견, 색인 생성, 순위, 답변 포함, 인용문 사용이나 출처 인용을 보장하지 않습니다. 이 정책이 제어하는 것은 어떤 봇이 페이지를 가져올 수 있는지, 즉 **접근**뿐입니다. 그 이후의 결과는 제어하지 않습니다.
:::

## 빠른 시작 {#quick-start}

게시할 내용을 확인하려면 AI 크롤러 블록을 출력하세요.

```bash
php artisan seo:robots-txt --print
```

두 가지 방식으로 사용할 수 있습니다.

### 방법 A — 기존 robots.txt에 블록 붙여 넣기 {#option-a-—-paste-the-block-into-your-existing-robots-txt}

이미 `public/robots.txt`를 관리하고 있다면 관리형 블록만 가져와 붙여 넣으세요.

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### 방법 B — 전체 파일을 Rankbeam으로 관리하기 {#option-b-—-let-rankbeam-manage-the-whole-file}

일반 섹션, AI 지시문, `Sitemap:` 줄, [llms.txt](/ko/guide/sitemaps) 참조가 포함된 완전한 `robots.txt`를 생성하세요.

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

파일에 정책 변경이 반영되도록 예약하세요.

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

또는 동적으로 제공할 수 있습니다. `seo.ai_crawlers.route` 설정을 `true`로 지정하면 패키지가 현재 설정으로 `/robots.txt` 요청에 응답합니다. 별도 생성 단계는 필요하지 않습니다.

::: warning 정적 파일이 우선합니다
대부분의 앱에는 이미 `public/robots.txt`가 있으며, 웹 서버는 요청이 Laravel 라우팅에 도달하기 전에 이 파일을 제공합니다. 동적 라우트는 **기본적으로 꺼져 있습니다**. 잊고 있던 파일을 모르게 가리거나 그 파일에 가려지지 않도록 하기 위해서입니다. 정적 `robots.txt`가 없을 때만 라우트를 사용하세요.
:::

## 실제 강제력의 한계 {#honesty-about-enforcement}

robots.txt는 요청이지 방벽이 아닙니다. 목록에 있는 대부분의 봇은 이를 준수한다고 문서에 명시하지만, 일부 사용자 실행 에이전트(`ChatGPT-User`, `Perplexity-User`)와 일부 학습 크롤러(`Bytespider`)는 **그렇지 않습니다**. Rankbeam은 작동하지 않을 차단이 적용되는 듯한 인상을 주지 않도록 해당 줄을 `advisory`로 표시합니다. 이를 무시하는 봇을 실제로 막으려면 서버 또는 엣지 수준의 차단(방화벽, WAF, Cloudflare 봇 규칙)이 필요합니다. [Pro AI 봇 접속 로그](/ko/pro/ai-bot-monitor)에서 주의해야 할 봇을 확인할 수 있습니다.

## 콘텐츠 신호 (사용 선호 설정) {#content-signals-usage-preferences}

`Allow` / `Disallow`는 봇이 페이지를 가져올 수 있는지, 즉 **접근**을 제어합니다. Cloudflare가 지지하는 표준인 [콘텐츠 신호](https://contentsignals.org)는 다른 측면을 다룹니다. 가져온 콘텐츠를 어떻게 **사용**할 수 있는지 명시합니다. `User-agent: *` 그룹의 `Content-Signal:` 한 줄에 세 가지 선호 설정을 담습니다.

| 신호 | 정책에서 대응하는 목적 | 의미 |
| --- | --- | --- |
| `search` | `ai_search` | 검색 색인 생성(링크와 짧은 발췌문) |
| `ai-input` | `ai_assistant` | 실시간으로 AI 모델에 페이지 입력(RAG / 그라운딩) |
| `ai-train` | `ai_training` | AI 모델 학습 또는 미세 조정 |

**기본적으로 꺼져 있습니다**. 활성화하기 전에는 파일의 바이트가 그대로 유지됩니다. 켜면 Rankbeam은 기존 `policy`에서 해당 줄을 바로 도출합니다. `allow`는 `yes`로, `disallow`는 `no`로 변환됩니다.

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

`policy`에서 목적을 완전히 제거하면 해당 신호는 **생략**됩니다. 이는 명시적인 `yes`/`no`와 다른, 규격상의 “선호를 표시하지 않음”입니다.

::: warning robots.txt 자체와 마찬가지로 권고입니다
콘텐츠 신호는 선호를 표현할 뿐 **기술적 제어 수단은 아닙니다**. 크롤러는 이를 무시할 수 있습니다. 위의 접근 규칙이나 엣지 수준 차단을 대체하지 않고 함께 사용합니다.
:::

## 설정 {#configuration}

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose. A purpose left out is allowed.
    'policy' => [
        'ai_training'   => 'disallow',
        'ai_search'     => 'allow',
        'ai_assistant'  => 'allow',
        'search_engine' => 'allow',   // Yandex, Baidu, Naver, Seznam, … (3.15)
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
        'baiduspider' => 'disallow',  // e.g. keep a search engine you don't serve off your bandwidth
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

목적과 관계없이 개별 봇의 정책을 덮어쓰려면 `overrides`를 사용하세요. 키는 목록의 **id**입니다(예: `gptbot`, `claudebot`, `perplexitybot`, `google-extended`).

## 크롤러 목록 {#the-catalog}

`SEO::aiCrawlers()`가 기준 데이터입니다. Pro 접속 로그도 같은 목록으로 방문자를 식별하므로, 봇을 제어하는 파일과 관측 패널 사이에 불일치가 생기지 않습니다.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

OpenAI(GPTBot, OAI-SearchBot, ChatGPT-User), Anthropic(ClaudeBot, Claude-SearchBot, Claude-User), Google(Google-Extended), Perplexity, Apple(Applebot-Extended), Common Crawl(CCBot), Meta, Amazon, ByteDance 등 주요 운영사를 다룹니다. 각 항목에는 문서화된 목적과 robots.txt 토큰이 있습니다.

### 지역별 검색 엔진 {#regional-search-engines}

3.15부터는 Google/Bing 외의 시장에서 중요한 일반 웹 검색 크롤러도 목록에 포함됩니다. 목적은 `search_engine`이며 **기본적으로 허용됩니다**.

| id | 토큰 | 운영사 |
|---|---|---|
| `yandex` | `Yandex` | Yandex(러시아) — 기본 토큰 하나가 모든 봇을 포함합니다. |
| `baiduspider` | `Baiduspider` | Baidu(중국) |
| `yeti` | `Yeti` | Naver(한국) |
| `seznambot` | `SeznamBot` | Seznam(체코) |
| `sogou` | `Sogou web spider` | Sogou(중국) |
| `360spider` | `360Spider` | Qihoo 360(중국) |
| `coccocbot` | `coccocbot-web` | Cốc Cốc(베트남) |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

다른 봇과 마찬가지로 `policy`와 `overrides`에 참여합니다. 따라서 `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']`로 서비스를 제공하지 않는 두 크롤러의 대역폭 사용을 제한하고, `'list' => 'all'`로 각각의 명시적인 줄을 생성할 수 있습니다. 별도로 요청하지 않으면 `all()`와 `match()`에는 **포함되지 않습니다**. `searchEngines()`, `all(true)`, `match($ua, true)`를 사용해 포함할 수 있습니다. 이를 통해 Pro AI 봇 로그와 모든 “AI 크롤러 N개” 집계가 원래 의미를 유지합니다.

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

크롤러를 인식한다고 해서 해당 검색 엔진의 노출이나 순위가 보장되지는 않습니다. 대응하는 사이트 소유권 확인 태그(`yandex-verification`, `baidu-site-verification`, `naver-site-verification`, `seznam-wmt`)는 `seo.verification` 아래에 있습니다. [다국어 콘텐츠](/ko/guide/multilingual#site-verification)를 참고하세요.

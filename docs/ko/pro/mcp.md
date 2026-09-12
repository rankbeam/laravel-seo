---
description: "AI 어시스턴트가 Model Context Protocol로 Laravel 사이트의 SEO를 읽고 허용 시 편집하는 외부 의존성 없는 stdio MCP 서버입니다. Laravel 11: PHP 8.2–8.4, Laravel 12: PHP 8.2–8.5, Laravel 13: PHP 8.3–8.5를 지원합니다."
---

# MCP 서버 {#mcp-server}

Rankbeam MCP 서버는 AI 어시스턴트가 [Model Context Protocol](https://modelcontextprotocol.io)을 통해 **사이트 SEO를 읽고 선택적으로 편집**하도록 합니다. Claude Code / Claude Desktop, Cursor, Codex 등의 MCP 클라이언트를 Laravel 앱에 연결하면 페이지 메타데이터 결정, 감사 실행, Pro 점수 읽기, AI 크롤러 정책 확인, 허용한 경우 SEO 기록을 수행할 수 있습니다.

SDK나 새 패키지가 필요 없는 **외부 의존성 없는** 독립 stdio 서버이며 PHP 8.2–8.4(Laravel 11), PHP 8.2–8.5(Laravel 12), PHP 8.3–8.5(Laravel 13)에서 실행됩니다.

::: tip Pro 기능
MCP 서버는 `rankbeam/laravel-seo-pro`에 포함됩니다. **기본적으로 읽기 전용**이며 편집은 설정 플래그와 모델 허용 목록으로 명시적으로 활성화해야 합니다.
:::

## 어시스턴트가 할 수 있는 작업 {#what-the-assistant-can-do}

### 분석 도구 (항상 사용 가능) {#analysis-tools-always-available}

| 도구 | 기능 |
| --- | --- |
| `seo_resolve` | 모델 레코드의 최종 SEO 메타데이터(제목, 설명, 표준 URL, robots, Open Graph, JSON-LD), 즉 페이지가 실제 렌더링할 값. |
| `seo_audit` | 모델 레코드 또는 첫 N개 레코드의 프로세스 내 [메타데이터 감사](/ko/guide/audit). 같은 `seo:audit` 검사를 큐 없이 실시간 실행. |
| `seo_score` | 모델 레코드에 최근 저장된 [Pro SEO 점수](/ko/pro/scoring)(0–100 + 등급). |
| `seo_robots_directives` | 관리형 [AI 크롤러 robots.txt 지시문](/ko/guide/ai-crawlers)과 최종 봇별 허용/차단 정책. |
| `validate_schema` | JSON-LD 객체 또는 허용된 모델의 최종 스키마 그래프를 코어 구조화 데이터 검증기로 검증(`@type`별 Google 리치 결과 요구 사항). |
| `analyze_robots` | **알려진 AI 크롤러별** 최종 허용/차단 판정과 판정 근거(봇별 덮어쓰기, 목적 정책, 기본값). 정책은 사이트 전체에 적용. |
| `debug_social_share` | 대체값 적용 후 소셜 크롤러가 모델 레코드에서 실제로 볼 Open Graph + Twitter 카드와 권고성 카드 상태 안내. |
| `check_meta` | 모델 레코드 하나의 최종 제목/설명/표준 URL/robots/og:image, 길이와 존재 여부, 감사 문제를 모은 메타 상태 보기. |

### 사이트 콘텐츠 도구 (항상 사용 가능) {#site-content-tools-always-available}

페이지를 나열하고 검색하는 “사이트와 대화하기” 도구입니다. 서버를 콘텐츠를 이해하는 어시스턴트로 확장합니다.

| 도구 | 기능 |
| --- | --- |
| `list_pages` | **허용 목록에 있는** 모델의 SEO 관리 페이지(레코드)를 URL과 최종 제목으로 나열. `limit`/`offset` 페이지 나누기 지원. |
| `search_pages` | **허용 목록에 있는** 모델 페이지의 전체 텍스트 검색. 모델이 검색 가능하면 Laravel [Scout](https://laravel.com/docs/scout), 아니면 title/name/headline과 연결된 SEO 메타데이터를 대상으로 안전한 SQL `LIKE` 사용. 결과마다 URL, 제목, 스니펫 반환. |

### 운영 도구 (선택적 활성화) {#ops-tools-opt-in}

스캔 상태를 읽고 사이트 설정을 바꾸는 도구입니다. 편집 도구처럼 **`allow_edits`로 제한**되며 기본 읽기 전용 서버에서는 보이지도 동작하지도 않습니다.

| 도구 | 기능 |
| --- | --- |
| `list_issues` | 실행 간 지속되는 현재 미해결 SEO 문제 집합과 최근 [스캔](/ko/pro/scan-issues) 실행 헤더. `severity` / `type` 필터 지원. |
| `trigger_scan` | 허용된 레코드 하나의 대상 스캔(실행 반환) 또는 전체 대상 스캔 시작. 기본은 큐, `sync: true`로 인라인 실행. |
| `create_redirect` | 리디렉션 모델의 검증기를 재사용해 출발 경로 또는 정규식 → 대상 규칙 생성. 상태 `301`/`302`/`307`/`308`/`410` 지원. |

### 편집 도구 (선택적 활성화) {#edit-tool-opt-in}

| 도구 | 기능 |
| --- | --- |
| `seo_save_meta` | `saveSEO()`로 **허용된** 모델 레코드에 SEO 메타데이터(제목, 설명, 표준 URL, robots, OG, Twitter, JSON-LD) 기록. |

편집을 켜지 않으면 운영 도구와 `seo_save_meta`는 **`tools/list`에 표시되지 않으며 실행할 수도 없습니다**. [보안](#security)을 참고하세요. 읽기 전용 서버는 어시스턴트에게 해당 도구의 존재조차 알리지 않습니다.

## AI 클라이언트 연결 {#wiring-an-ai-client}

서버는 **stdio**로 JSON-RPC를 주고받습니다. 클라이언트가 Artisan 명령을 시작하고 파이프로 통신합니다. 사용하는 클라이언트마다 등록하면 같은 서버를 모두 사용할 수 있습니다.

::: tip 하나의 명령으로 모든 클라이언트 연결
아래 클라이언트는 모두 같은 `php artisan seo-pro:mcp` 명령을 **앱 루트에서** 실행합니다. Artisan이 앱을 부트스트랩할 수 있어야 하기 때문입니다. Windows나 셸 환경을 상속하지 않는 GUI 앱처럼 클라이언트의 `PATH`에 `php`가 없다면 **`php`와 `artisan` 모두 절대 경로**로 지정하세요. Artisan은 `artisan` 스크립트 자체의 디렉터리에서 부팅하므로 `cwd`가 필요하지 않습니다.
:::

### Claude Code (CLI) {#claude-code-cli}

**앱 루트에서** 명령 하나로 등록하세요.

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

연결을 확인하세요.

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

Windows / Laravel Herd에서는 실행 디렉터리와 관계없이 동작하도록 절대 경로를 고정하세요.

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

**Settings → Developer → Edit Config**에서 설정 파일을 편집하거나 직접 여세요.

- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

**Windows**에서는 `php.exe`의 절대 경로를 사용하고 JSON의 모든 역슬래시를 이중 이스케이프하세요.

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "C:\\Users\\you\\.config\\herd\\bin\\php84\\php.exe",
      "args": ["C:\\path\\to\\app\\artisan", "seo-pro:mcp"]
    }
  }
}
```

Claude Desktop을 완전히 종료한 뒤 다시 여세요. 메시지 입력줄의 도구/플러그 아이콘에서 도구를 볼 수 있습니다.

### Cursor {#cursor}

프로젝트에 `.cursor/mcp.json` 파일을 만들거나 모든 프로젝트용으로 `~/.cursor/mcp.json` 파일을 만드세요. 어디서든 시작하도록 `artisan` 절대 경로를 지정하세요.

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

Windows에서는 위 Claude Desktop 예시처럼 `php.exe` 절대 경로와 이중 이스케이프 역슬래시를 사용하세요. **Settings → MCP**에서 서버를 켜세요.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Claude Code처럼 Windows/Herd에서는 `php`와 `artisan` 절대 경로를 전달하세요.

이제 어시스턴트가 도구를 호출할 수 있습니다.

## 실제 대화 예시 {#a-worked-conversation}

900페이지 의료기관 사이트인 데모 앱과의 실제 대화이며 도구의 실제 출력을 줄여 표시했습니다. 읽기 허용 목록에는 `App\Models\Page`와 `App\Models\PublicPage`가 있고 `allow_edits`가 켜져 있습니다.

> **사용자:** “Accoglienza” 페이지(Page id 1)의 SEO는 어떤가요? 명확한 문제는 수정해 주세요.

어시스턴트는 먼저 `seo_audit { model: "App\\Models\\Page", id: 1 }`로 **감사를 수행**합니다.

```json
[
  {
    "id": 1,
    "url": "http://localhost/accoglienza",
    "issue_count": 2,
    "issues": [
      { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 17 characters (recommended min 30).", "context": { "length": 17, "min": 30 } },
      { "code": "description_too_short", "severity": "notice", "field": "description", "message": "Description is only 11 characters (recommended min 70).", "context": { "length": 11, "min": 70 } }
    ]
  }
]
```

실제로 렌더링되는 내용을 확인하려고 `seo_resolve { model: "App\\Models\\Page", id: 1 }`로 현재 메타 값을 **결정**합니다. 제목은 17자, 설명은 11자로 둘 다 권장 최소 길이보다 짧다는 것을 확인하고 `seo_save_meta`로 **수정값을 기록**합니다.

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

도구는 기록을 확인하고 새로 결정된 레코드를 반환합니다.

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

마지막으로 **감사를 다시 수행**해 수정을 검증합니다. `issue_count`가 이제 `0`입니다.

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **어시스턴트:** 완료했습니다. 제목(17자)과 설명(11자)이 모두 권장 최소 길이보다 짧아 더 충분하고 내용을 설명하는 제목과 설명을 설정했습니다. 재감사 결과 문제는 0개입니다.

기록은 일반적인 코드 저장과 같은 `saveSEO()`를 거쳤으므로 모델의 fillable 필드와 캐스트를 통한 대량 할당으로 코드에서 설정한 것과 똑같이 저장되고 결정됩니다. Filament 폼의 길이 검증기를 거치지는 **않습니다**. 따라서 재감사는 자동 검사만 검증합니다. 편집 정확성은 여전히 검토해야 하며, 감사 통과가 문장 품질 인증은 아닙니다.

### 사이트 콘텐츠와 대화하기 {#talking-to-your-site-s-content}

사이트 콘텐츠 도구는 지정한 레코드 하나뿐 아니라 **사이트 자체에 관한** 질문에 답하도록 합니다. “사이트와 대화하기” 데모입니다.

> **사용자:** “immunoterapia”를 언급하는 페이지는 무엇이고 제목 상태는 어떤가요?

어시스턴트가 `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`로 **검색**합니다.

```json
{
  "model": "App\\Models\\Page",
  "query": "immunoterapia",
  "driver": "like",
  "count": 3,
  "results": [
    { "id": 42, "url": "http://localhost/ricerca/immunoterapia-oncologica", "title": "Immunoterapia oncologica", "snippet": "Il programma di immunoterapia dell'IDI: terapie, sperimentazioni e accesso…" },
    { "id": 87, "url": "http://localhost/reparti/dermatologia-oncologica", "title": "Dermatologia oncologica", "snippet": "…include protocolli di immunoterapia per i tumori cutanei avanzati…" },
    { "id": 91, "url": "http://localhost/news/nuova-immunoterapia-2026", "title": "Nuova", "snippet": "Al via una nuova immunoterapia…" }
  ]
}
```

세 번째 제목이 “Nuova”뿐이어서 `check_meta { model: "App\\Models\\Page", id: 91 }`로 **해당 페이지 메타데이터를 확인**합니다.

```json
{
  "url": "http://localhost/news/nuova-immunoterapia-2026",
  "title": { "value": "Nuova", "length": 5, "present": true },
  "description": { "value": null, "length": 0, "present": false },
  "og_image": { "value": null, "present": false },
  "issue_count": 2,
  "issues": [
    { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 5 characters (recommended min 30)." },
    { "code": "missing_description", "severity": "warning", "field": "description", "message": "No meta description is set." }
  ]
}
```

> **어시스턴트:** “immunoterapia”를 언급하는 페이지가 세 개입니다. 두 개는 괜찮아 보이지만 **/news/nuova-immunoterapia-2026**은 제목이 5자(“Nuova”)이고 메타 설명이 없습니다. 더 충분한 제목과 설명을 작성할까요?

`allow_edits`가 켜져 있으면 `seo_save_meta`로 바로 수정할 수 있습니다. 읽기 전용이라면 제안에서 멈춥니다. 여기서는 모델이 Scout 색인 대상이 아니므로 `search_pages`가 SQL `LIKE` 대체 경로(`"driver": "like"`)를 사용했습니다. [Laravel Scout](https://laravel.com/docs/scout)를 추가하면 같은 도구가 투명하게 자신의 검색 엔진을 사용합니다.

## 보안 {#security}

세 계층이 기본 안전성을 지킵니다. 기본 읽기 전용 상태에서는 모두 적용되며 의도적으로 완화해야 합니다.

### 1. 편집 제한 (기본적으로 꺼짐) {#_1-edits-are-gated-off-by-default}

플래그를 바꾸기 전까지 쓰기 도구는 보이지도 동작하지도 않습니다.

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

기본값인 `allow_edits` 비활성 상태에서는 `seo_save_meta`가 **`tools/list`에 반환되지 않으며**, 이를 대상으로 `tools/call` 메서드를 호출하면 JSON-RPC `-32602`로 실패합니다. 어시스턴트는 기록할 수 없으며 기록 기능의 존재도 발견할 수 없습니다. 신뢰하는 클라이언트와 데이터베이스에만 켜세요.

### 2. 모델 허용 목록 {#_2-the-model-allowlist}

모델 범위의 모든 읽기/쓰기 도구는 허용 목록의 `HasSEO` 모델에만 접근합니다. AI 클라이언트가 임의 클래스(`User`, 청구 모델 등)를 도구에 지정할 수 없습니다.

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

허용되지 않은 클래스를 요청하면 어시스턴트가 읽을 오류 결과 `Model [App\Models\User] is not in the MCP allowlist`를 반환하며 클래스에는 접근하지 않습니다. `models`가 비어 있으면 설정된 `seo.audit.models` / `seo.sitemap.models`를 사용합니다. 따라서 MCP의 접근 범위는 패키지 나머지 기능과 같으며 더 넓어지지 않습니다.

### 3. stdio 전용 — 네트워크에 노출하지 않음 {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

서버는 **stdio로만** 통신합니다. 클라이언트가 프로세스를 시작하고 파이프로 JSON-RPC를 입출력합니다. **HTTP 리스너, 포트, 소켓이 없으므로** 다른 컴퓨터가 접근할 곳이 없으며 인증할 원격 인터페이스 자체가 없어 인증도 필요하지 않습니다. STDOUT에는 프로토콜 트래픽만 담고 진단은 모두 STDERR로 보냅니다. 클라이언트가 이를 기록하며, 예를 들어 Claude Desktop은 `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`에 기록합니다. 다른 로그 한 줄이 프로토콜 스트림을 깨뜨리지 않도록 합니다.

::: warning 편집 서버는 DB 쓰기 권한처럼 다루세요
`allow_edits`는 연결된 어시스턴트가 명령 대상 데이터베이스의 SEO 행을 바꾸도록 허용합니다. 실험할 때는 로컬/스테이징을 지정하고 허용 목록을 좁게 유지하며 끝나면 편집을 다시 끄세요. 전체 스위치 `'enabled' => false`는 명령 시작 자체를 거부합니다. 로컬 stdio 전송이 AI 클라이언트의 자체 제공업체로 도구 결과를 보내는 것까지 막지는 않습니다. 클라이언트의 데이터 설정도 고려하세요.
:::

## 설정 {#configuration}

```php
// config/seo-pro.php
'mcp' => [
    'enabled'     => true,           // master switch; the command refuses to run when false
    'allow_edits' => false,          // expose + permit the ops tools + seo_save_meta
    'models'      => [],             // allowlist; [] = fall back to audit/sitemap models
    'server_name' => 'rankbeam-seo', // reported in the MCP initialize handshake

    // Optional Server Card discovery route (off by default) — see below.
    'server_card' => [
        'enabled'     => false,      // serve GET {path} with the discovery card
        'path'        => '.well-known/mcp/server-card.json',
        'name'        => null,       // reverse-DNS server name (null = derived from app.url)
        'schema_url'  => 'https://modelcontextprotocol.io/schemas/draft/server-card.json',
        'website_url' => null,       // optional homepage/docs URL stamped on the card
    ],
],
```

## Server Card (발견) — 실험적 초안 규격 {#server-card-discovery-—-experimental-draft-spec}

MCP **Server Card**는 잘 알려진 URL에 있는 작은 JSON 문서로, 클라이언트가 연결 전에 서버 이름, 버전, 제공 기능을 발견하게 합니다. Rankbeam은 사이트에 이를 제공해 에이전트 도구에 *“이 사이트에는 대화할 수 있는 MCP 서버가 있습니다”*라고 알릴 수 있습니다. **기본적으로 꺼져 있으며** 켜도 다른 동작은 바뀌지 않는 추가 기능입니다.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

켜면 `GET /.well-known/mcp/server-card.json` 요청이 다음과 같은 카드를 반환합니다.

```json
{
  "$schema": "https://modelcontextprotocol.io/schemas/draft/server-card.json",
  "name": "com.example/rankbeam-seo",
  "version": "1.0.0",
  "title": "Rankbeam SEO MCP server",
  "description": "Read — and optionally edit — this site's SEO over the Model Context Protocol…",
  "_meta": {
    "io.rankbeam.seo/transport": "stdio",
    "io.rankbeam.seo/launch": "php artisan seo-pro:mcp",
    "io.rankbeam.seo/tool_count": 10,
    "io.rankbeam.seo/tools": [ { "name": "seo_resolve", "description": "…" } ]
  }
}
```

카드는 **현재 활성화된** 도구만 나열하므로 읽기 전용 서버는 제한된 운영/편집 도구를 카드에도 공개하지 않습니다.

::: warning 초안 규격을 따릅니다
MCP Server Card **초안** 제안([SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127), 2026년 9월 10일 기준 미병합 공개 제안)을 따릅니다. well-known 경로, `$schema` URL, 정확한 필드 구성은 **확정되지 않았으므로** 각각 `path`, `schema_url`, `name`, `website_url`로 설정할 수 있습니다. 이 서버는 **stdio**(`php artisan seo-pro:mcp`)로 실행하므로 카드에 HTTP `remotes` 블록이 없습니다. 연결할 HTTP 엔드포인트가 아닌 발견 힌트입니다. 의존하기 전에 클라이언트의 경로와 형태를 확인하고 필요 없으면 꺼 두세요.
:::

## 프로토콜 참고 {#protocol-notes}

도구 전용 MCP 서버는 작은 JSON-RPC 2.0 인터페이스이며 이 서버는 이를 직접 구현합니다. `initialize`(버전 협상 + 기능 핸드셰이크), `tools/list`, `tools/call`, `ping` 메서드를 지원합니다. `2025-06-18` 프로토콜 버전을 알리고 `2025-03-26` 버전과 `2024-11-05`도 이해합니다. 알 수 없는 메서드는 `-32601`, 잘못된 줄은 `-32700` 코드를 반환합니다. **도구** 실패는 전송 오류 대신 어시스턴트가 읽을 수 있는 `isError` 결과로 반환합니다. `notifications/initialized`처럼 `id`가 없는 알림 메시지에는 올바르게 응답하지 않습니다.

## 헤드리스 / 확장 {#headless-extending}

`SeoPro::mcp()`는 도구 레지스트리를 반환하므로 공개된 도구를 검사하거나 자신의 도구를 등록할 수 있습니다.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

사용자 지정 도구는 `McpTool`(`name`, `description`, `inputSchema`, `isEnabled`, `handle`)을 구현합니다. `AbstractTool` 클래스를 확장해 모델 허용 목록 처리를 재사용하면 내장 도구와 같은 안전 보장을 상속할 수 있습니다.

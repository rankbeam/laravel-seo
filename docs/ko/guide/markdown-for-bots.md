---
description: "콘텐츠 협상을 통해 AI 크롤러에 정리된 Markdown 페이지를 제공하고, 일반 방문자에게는 기존 HTML을 그대로 전달하세요. 무료 코어 기능이며 기본적으로 꺼져 있습니다."
---

# 봇용 Markdown {#markdown-for-bots}

애플리케이션의 HTML 페이지는 본문을 탐색 메뉴, 스크립트, 레이아웃 마크업으로 감쌉니다. 일부 AI 크롤러와 답변 엔진은 더 간결한 표현이 제공되면 이를 받아들입니다. 이 기능은 콘텐츠 협상으로 요청하는 클라이언트에 페이지의 **Markdown 표현**을 제공하고, 일반 방문자에게는 기존 HTML을 그대로 전달할 수 있습니다. 선택적으로 활성화하는 호환성 기능이며, 특정 클라이언트가 결과를 어떻게 해석하거나 사용하는지 보장하지 않습니다.

[AI 크롤러 제어](/ko/guide/ai-crawlers)와 함께 사용할 수 있습니다. AI 크롤러 제어는 접근 정책을 설정하고, 이 기능은 요청 중 *어떤* 콘텐츠를 제공할지 결정합니다.

무료 코어 기능이며 **기본적으로 꺼져 있습니다**.

## 동작 방식 {#how-it-works}

활성화하면 콘텐츠 협상 미들웨어가 등록됩니다. 일반 응답을 생성한 다음, 다음 **두 조건을 모두 만족할 때만** Markdown으로 교체합니다.

1. **요청이 Markdown을 요구합니다.** 명시적 `Accept: text/markdown` 헤더, `?format=md` 쿼리, 또는 선택적으로 user-agent로 식별한 알려진 AI 크롤러 요청입니다.
2. **해당 라우트에서 Markdown 소스를 찾을 수 있습니다.**

그 외에는 응답을 변경하지 않고 통과시킵니다. 일반 브라우저 응답에는 영향을 주지 않으며, 성공한 **HTML** 응답만 교체합니다. JSON, 리디렉션, 다운로드는 교체하지 않습니다.

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Markdown의 출처 {#where-the-markdown-comes-from}

아래 소스들이 일치하는 라우트에 Markdown을 제공할 수 있습니다. 미들웨어는 **등록된 라우트 소스를 먼저** 확인한 다음 라우트에 바인딩된 모델을 확인합니다. 각 모델에서는 명시적 `toSeoMarkdown()` 메서드가 내장 대체값보다 우선합니다. 이 메서드가 null이나 빈 문자열을 반환하면 해당 모델의 대체값은 사용하지 않습니다.

### 1. 모델 자체의 Markdown {#_1-a-model-s-own-markdown}

등록된 라우트 소스가 콘텐츠를 반환하지 않으면, `toSeoMarkdown()` 메서드를 구현한 라우트 바인딩 모델이 출력을 결정합니다. `ProvidesSeoMarkdown` 계약을 구현하거나 메서드만 추가하면 됩니다.

```php
use Rankbeam\Seo\Contracts\ProvidesSeoMarkdown;

class Post extends Model implements ProvidesSeoMarkdown
{
    use HasSEO;

    public function toSeoMarkdown(): ?string
    {
        return $this->body_markdown; // your already-clean markdown
    }
}
```

### 2. 등록된 라우트 소스 {#_2-a-registered-route-source}

모델이 없는 라우트나 모델 출력을 재정의하려는 경우에는 라우트 이름으로 소스를 등록하세요.

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. 내장 대체값 {#_3-the-built-fallback}

라우트에 바인딩된 `HasSEO` 모델에 `toSeoMarkdown()` 메서드가 없으면, 미들웨어가 결정된 **제목**(H1), **설명**, 모델의 **`getContentForSEO()`**로 기본 문서를 만듭니다.

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning 콘텐츠는 그대로 제공됩니다
대체값은 `getContentForSEO()`를 그대로 출력합니다. 콘텐츠가 Markdown이 아니라 HTML이면 `toSeoMarkdown()` 메서드를 구현해 변환을 제어하세요. `seo.markdown_for_bots.build_from_content = false`로 대체값 기능을 완전히 끌 수 있습니다.
:::

## 설정 {#configuration}

```php
// config/seo.php
'markdown_for_bots' => [
    'enabled'            => false,    // off by default; the middleware isn't registered until true
    'auto_register_middleware' => true,
    'serve_to_known_bots' => false,   // also serve to known AI crawlers by user-agent
    'query_param'        => 'format', // the ?format=md trigger
    'query_value'        => 'md',
    'build_from_content' => true,     // build from getContentForSEO() when no toSeoMarkdown()
],
```

명시적인 `Accept` / `?format` 신호만으로 콘텐츠를 협상하려면 `serve_to_known_bots`를 꺼 두세요. 켜면 GPTBot, ClaudeBot, PerplexityBot 등 [AI 크롤러 카탈로그](/ko/guide/ai-crawlers)로 식별한 크롤러가 요청하지 않아도 Markdown을 제공합니다.

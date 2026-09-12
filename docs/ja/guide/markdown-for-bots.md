---
description: "コンテントネゴシエーションでAIクローラーにページの簡潔なMarkdown表現を配信し、通常の訪問者には従来のHTMLをそのまま返します。無料のCore機能で、デフォルトでは無効です。"
---

# ボット向けMarkdown {#markdown-for-bots}

アプリのHTMLページには、コンテンツの周囲にナビゲーション、スクリプト、レイアウト用のマークアップがあります。一部のAIクローラーや回答エンジンは、より簡潔な表現が用意されていれば受け入れます。この機能は、コンテントネゴシエーションで要求したクライアントにページの**Markdown表現**を配信し、通常の訪問者にはHTMLをそのまま返します。明示的に有効にする互換性のための選択肢であり、特定のクライアントが結果をどう解析・利用するかを保証するものではありません。

[AIクローラーの制御](/ja/guide/ai-crawlers)と組み合わせて使えます。そちらはアクセスポリシーを設定し、この機能はリクエスト時に*どのようなコンテンツ*を配信するかを決めます。

無料のCore機能で、**デフォルトでは無効**です。

## 仕組み {#how-it-works}

有効にすると、コンテントネゴシエーション用のミドルウェアが登録されます。通常のレスポンスが生成された後、次の**両方を満たす場合だけ**Markdownに置き換えます。

1. **リクエストがMarkdownを要求している。** 明示的な`Accept: text/markdown`ヘッダー、`?format=md`クエリ、または任意で有効にするUser-Agentによる既知のAIクローラーの判定が該当します。
2. **そのルートのMarkdownソースを解決できる。**

それ以外ではレスポンスを変更せずに通します。通常のブラウザー閲覧には影響せず、置換されるのは成功した**HTML**レスポンスだけです。JSON、リダイレクト、ダウンロードは置換しません。

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Markdownの取得元 {#where-the-markdown-comes-from}

一致したルートのMarkdownは、以下のソースから取得できます。ミドルウェアは**登録済みのルートソースを先に**試し、その後でルートにバインドされたモデルを確認します。各モデルでは、明示的な`toSeoMarkdown()`メソッドが組み込みフォールバックより優先されます。このメソッドがnullまたは空白を返すと、そのモデルのフォールバックは無効になります。

### 1. モデル自身のMarkdown {#_1-a-model-s-own-markdown}

登録済みのルートソースがコンテンツを返さない場合、`toSeoMarkdown()`を実装したルートバインドモデルが出力を制御します。`ProvidesSeoMarkdown`インターフェースを実装するか、メソッドだけを追加してください。

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

### 2. 登録済みのルートソース {#_2-a-registered-route-source}

モデルがないルートや、モデルの出力を上書きしたい場合は、ルート名でソースを登録します。

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. 組み込みフォールバック {#_3-the-built-fallback}

ルートにバインドされた`HasSEO`モデルに`toSeoMarkdown()`がない場合、ミドルウェアは解決済みの**タイトル**（H1として）、**ディスクリプション**、モデルの**`getContentForSEO()`**から基本的なドキュメントを生成します。

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning コンテンツはそのまま配信されます
フォールバックは`getContentForSEO()`を変更せずに出力します。コンテンツがMarkdownではなくHTMLの場合は、`toSeoMarkdown()`を実装して変換を制御してください。`seo.markdown_for_bots.build_from_content = false`でフォールバック全体を無効にできます。
:::

## 設定 {#configuration}

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

`serve_to_known_bots`を無効にしておくと、明示的な`Accept` / `?format`だけで配信形式を決めます。有効にすると、GPTBot、ClaudeBot、PerplexityBotなど、[AIクローラーカタログ](/ja/guide/ai-crawlers)で識別されるボットには、要求がなくてもMarkdownを返します。

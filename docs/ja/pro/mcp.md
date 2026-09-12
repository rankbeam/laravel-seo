---
description: "追加依存なしのstdio MCPサーバーで、AIアシスタントがModel Context Protocol経由でLaravelサイトのSEOを読み取り、許可時には編集できます。Laravel 11はPHP 8.2–8.4、Laravel 12はPHP 8.2–8.5、Laravel 13はPHP 8.3–8.5に対応。"
---

# MCPサーバー {#mcp-server}

RankbeamのMCPサーバーを使うと、AIアシスタントが[Model Context Protocol](https://modelcontextprotocol.io)経由で**サイトのSEOを読み取り、任意で編集**できます。MCPクライアント（Claude Code / Claude Desktop、Cursor、Codexなど）をLaravelアプリに接続すると、ページのメタデータの解決、監査、Proスコアの読み取り、AIクローラーポリシーの確認、そして許可した場合はSEOの書き戻しができます。

**追加依存なし**の自己完結したstdioサーバーで、SDKも新しいパッケージも不要です。PHP 8.2–8.4（Laravel 11）、PHP 8.2–8.5（Laravel 12）、PHP 8.3–8.5（Laravel 13）で動作します。

::: tip Proの機能
MCPサーバーは`rankbeam/laravel-seo-pro`に含まれます。**デフォルトは読み取り専用**です。編集は設定フラグとモデル許可リストによって明示的に有効化します。
:::

## アシスタントができること {#what-the-assistant-can-do}

### 解析ツール（常に利用可能） {#analysis-tools-always-available}

| ツール | 処理内容 |
| --- | --- |
| `seo_resolve` | モデルレコードの完全に解決済みのSEOメタデータ（タイトル、ディスクリプション、正規URL、robots、Open Graph、JSON-LD）。ページが実際に描画する値。 |
| `seo_audit` | モデルレコードまたは先頭N件に対する同一プロセス内の[メタデータ監査](/ja/guide/audit)。`seo:audit`と同じチェックをキューなしで即時実行。 |
| `seo_score` | モデルレコードの保存済み最新[Pro SEOスコア](/ja/pro/scoring)（0–100 + 評価）。 |
| `seo_robots_directives` | 管理対象の[AIクローラー用robots.txt指示](/ja/guide/ai-crawlers)と、ボットごとの解決済み許可・拒否方針。 |
| `validate_schema` | JSON-LDオブジェクト、または許可モデルの解決済みスキーマグラフを、コアの構造化データバリデーターで検証（`@type`ごとのGoogleリッチリザルト要件）。 |
| `analyze_robots` | **既知のAIクローラーごと**の最終的な許可・拒否判断と、その根拠（ボット別上書き、目的別ポリシー、デフォルト）。ポリシーはサイト全体に適用。 |
| `debug_social_share` | ソーシャルクローラーがモデルレコードで実際に見る、フォールバック後のOpen Graph + Twitterカードと、参考用のカード状態の注意点。 |
| `check_meta` | 1モデルレコードのメタ情報に絞った状態表示。解決済みtitle/description/canonical/robots/og:imageの長さ・有無と監査結果。 |

### サイトコンテンツのツール（常に利用可能） {#site-content-tools-always-available}

「サイトに質問する」ためのツールです。サーバーをコンテンツに対応したアシスタントにし、ページの列挙や検索を可能にします。

| ツール | 処理内容 |
| --- | --- |
| `list_pages` | **許可リスト内**のモデルについて、SEO管理対象ページ（レコード）をURLと有効なタイトル付きで一覧表示。`limit`/`offset`のページングに対応。 |
| `search_pages` | **許可リスト内**のモデルのページを全文検索。検索対応モデルではLaravel [Scout](https://laravel.com/docs/scout)、それ以外は安全なSQLの`LIKE`（title/name/headline + 結合したSEOメタ情報）を使用。各結果にURL、タイトル、抜粋を返す。 |

### 運用ツール（任意で有効化） {#ops-tools-opt-in}

スキャン状態の読み取りやサイト設定の変更を行う運用ツールです。編集ツールと同じく**`allow_edits`で制御**し、読み取り専用サーバー（デフォルト）では表示も動作もしません。

| ツール | 処理内容 |
| --- | --- |
| `list_issues` | 現在の未解決SEO問題（実行をまたいで保持する未解決集合）と、最新[スキャン](/ja/pro/scan-issues)実行のヘッダー。`severity` / `type`で絞り込み。 |
| `trigger_scan` | 許可した1レコードの対象限定スキャン（実行を返す）、または全対象の完全スキャンを開始。デフォルトはキュー、`sync: true`でその場で実行。 |
| `create_redirect` | リダイレクトルールを作成（元パスまたは正規表現 → 宛先、ステータス`301`/`302`/`307`/`308`/`410`）。リダイレクトモデル自身のバリデーターを再利用。 |

### 編集ツール（任意で有効化） {#edit-tool-opt-in}

| ツール | 処理内容 |
| --- | --- |
| `seo_save_meta` | `saveSEO()`経由で、**許可リスト内**のモデルレコードにSEOメタデータ（タイトル、ディスクリプション、正規URL、robots、OG、Twitter、JSON-LD）を書き込む。 |

編集を有効にしない限り、運用ツールと`seo_save_meta`は**`tools/list`で公開されず、実行もできません**。[セキュリティ](#security)を参照してください。読み取り専用サーバーは、それらの存在をアシスタントに知らせることもありません。

## AIクライアントの接続 {#wiring-an-ai-client}

サーバーは**stdio**上のJSON-RPCで通信します。クライアントがArtisanコマンドを起動し、パイプ経由でやり取りします。使うクライアントに登録してください。同じサーバーがすべてに対応します。

::: tip どのクライアントでも同じコマンド
以下のクライアントは、すべて同じ起動コマンド`php artisan seo-pro:mcp`を使います。Artisanがアプリを初期化できるよう、**アプリのルートから**起動します。クライアントの`PATH`に`php`がないマシン（Windowsやシェル環境を引き継がないGUIアプリでよくあります）では、**`php`と`artisan`の両方に絶対パス**を指定してください。Artisanは`artisan`スクリプト自身のディレクトリから起動するため、`cwd`は不要です。
:::

### Claude Code（CLI） {#claude-code-cli}

1つのコマンドで登録できます。**アプリのルートから**実行してください。

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

接続を確認します。

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

Windows / Laravel Herdでは、起動ディレクトリに依存しないよう絶対パスを固定します。

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

設定ファイルを編集（**Settings → Developer → Edit Config**）するか、直接開きます。

- **Windows**：`%APPDATA%\Claude\claude_desktop_config.json`
- **macOS**：`~/Library/Application Support/Claude/claude_desktop_config.json`

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

**Windows**では`php.exe`の絶対パスを使い、JSON内の各バックスラッシュを二重にエスケープします。

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

Claude Desktopを完全に終了して再度開きます。ツールはメッセージバーのツール・プラグのアイコンから表示されます。

### Cursor {#cursor}

プロジェクトに`.cursor/mcp.json`を作成します。全プロジェクト共通なら`~/.cursor/mcp.json`を使います。どこからでも起動できるよう`artisan`の絶対パスを指定してください。

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

Windowsでは、上のClaude Desktopの例と同じく、`php.exe`の絶対パスと二重エスケープしたバックスラッシュを使います。**Settings → MCP**でサーバーを有効にします。

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Claude Codeと同様、Windows/Herdでは`php`と`artisan`を絶対パスで渡します。

これでアシスタントがツールを呼び出せます。

## 実際の会話例 {#a-worked-conversation}

以下はデモアプリ（900ページの医療機関サイト）に対する実際のやり取りです。ツールの実際の出力は短縮しています。読み取り許可モデルは`App\Models\Page`と`App\Models\PublicPage`の2つで、`allow_edits`は有効です。

> **利用者：** 「Accoglienza」ページ（Page id 1）のSEOはどうなっていますか。明らかな問題があれば直してください。

アシスタントは最初に**監査**します。`seo_audit { model: "App\\Models\\Page", id: 1 }`：

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

次に`seo_resolve { model: "App\\Models\\Page", id: 1 }`で現在のメタデータを**解決**し、実際の出力を確認します。タイトルが17文字、ディスクリプションが11文字で、どちらも推奨下限を下回っています。そこで`seo_save_meta`で**修正を書き込み**ます。

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

ツールは書き込みを確認し、改めて解決したレコードを返します。

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

最後に**再監査**して修正を確認します。`issue_count`は`0`になっています。

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **アシスタント：** 完了しました。タイトル17文字とディスクリプション11文字は、どちらも推奨下限より短かったため、内容をより詳しく示すタイトルとディスクリプションにしました。再監査の結果、問題は0件です。

書き込みは通常のプログラム保存と同じ`saveSEO()`を通るため、コードで設定した値と同じ方法で保存・解決します。モデルのfillableフィールドとキャストによる一括代入です。Filamentフォームの長さバリデーターは**通しません**。そのため再監査が確認するのは自動チェックだけです。内容の正確さには編集レビューが必要で、監査に問題がないことは文章品質の証明ではありません。

### サイトのコンテンツに質問する {#talking-to-your-site-s-content}

サイトコンテンツのツールを使うと、指定した1レコードだけでなく、**サイト自体について**の質問にも答えられます。これが「サイトに質問する」デモです。

> **利用者：** 「immunoterapia」に触れているのはどのページですか。タイトルは適切ですか。

アシスタントが`search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`で**検索**します。

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

3件目のタイトルが「Nuova」だけなので、`check_meta { model: "App\\Models\\Page", id: 91 }`で**そのページのメタデータを確認**します。

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

> **アシスタント：** 「immunoterapia」に触れているページは3件あります。2件は問題なさそうですが、**/news/nuova-immunoterapia-2026**はタイトルが5文字の「Nuova」で、メタディスクリプションもありません。より詳しいタイトルとディスクリプションを作成しますか。

`allow_edits`が有効なら`seo_save_meta`でその場で修正できます。読み取り専用なら提案で止めます。この例ではモデルがScoutのインデックスにないため、`search_pages`はSQLの`LIKE`フォールバック（`"driver": "like"`）を使いました。[Laravel Scout](https://laravel.com/docs/scout)を追加すれば、同じツールが透過的にその検索エンジンを使います。

## セキュリティ {#security}

デフォルトの安全性を3つの層で維持します。読み取り専用のデフォルトでは3つとも有効で、緩和する場合は意図的に行います。

### 1. 編集はフラグで制御（デフォルト無効） {#_1-edits-are-gated-off-by-default}

フラグを切り替えるまで、書き込みツールは表示も動作もしません。

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

`allow_edits`が無効（デフォルト）なら、`seo_save_meta`は**`tools/list`から返されず**、対象への`tools/call`はJSON-RPCの`-32602`で失敗します。アシスタントは書き込めず、書き込み機能があることも発見できません。信頼するクライアントとデータベースに対してのみ有効にしてください。

### 2. モデル許可リスト {#_2-the-model-allowlist}

モデルを対象とするツールは、読み取り*でも*書き込みでも、許可リスト内の`HasSEO`モデルだけにアクセスできます。AIクライアントが任意のクラス（`User`、課金モデルなど）を指定することはできません。

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

許可リスト外のクラスを要求すると、アシスタントが読めるエラー結果（`Model [App\Models\User] is not in the MCP allowlist`）を返し、そのクラスには触れません。`models`が空なら設定済みの`seo.audit.models` / `seo.sitemap.models`にフォールバックするため、MCPの対象範囲はパッケージの他機能がすでに扱う範囲と同じで、それより広がりません。

### 3. stdioのみ：ネットワークに公開しない {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

サーバーは**stdioだけ**で通信します。クライアントがプロセスを起動し、JSON-RPCをパイプで入出力します。**HTTPリスナーもポートもソケットもありません**。別マシンから到達するものも、認証すべきリモート公開面もないため、リモート認証もありません。STDOUTにはプロトコル通信だけを流し、診断はすべてSTDERRへ送ります。クライアントがログに記録し、たとえばClaude Desktopは`%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`に保存します。余計なログ行でストリームが壊れることはありません。

::: warning 編集可能なサーバーはDBの書き込み権限として扱う
`allow_edits`を有効にすると、接続したアシスタントがコマンドの接続先データベースのSEO行を変更できます。試す間はローカル・ステージングに接続し、許可リストを絞り、終わったら編集を無効にしてください。全体のスイッチ`'enabled' => false`はコマンドの起動自体を拒否します。ローカルstdioの通信方式は、AIクライアントがツール結果を自分のプロバイダーへ送るのを防ぐものではありません。クライアントのデータ設定も確認してください。
:::

## 設定 {#configuration}

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

## Server Card（検出用）：実験的な草案仕様 {#server-card-discovery-—-experimental-draft-spec}

MCPの**Server Card**は、既知のURLに配置する小さなJSON文書です。接続前にサーバーの名前、バージョン、提供機能をクライアントが検出できます。Rankbeamはサイト用のカードを配信し、*「このサイトには対話できるMCPサーバーがある」*とエージェント用ツールに知らせられます。**デフォルトは無効**で、純粋な追加機能です。有効にしても他の挙動は変わりません。

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

有効時、`GET /.well-known/mcp/server-card.json`は次のようなカードを返します。

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

カードが列挙するのは**現在有効な**ツールだけです。読み取り専用サーバーが、制限中の運用・編集ツールをカード経由で告知することはありません。

::: warning 草案仕様に追従
これはMCP Server Cardの**草案**提案に従います（[SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127)。2026年9月10日時点で未マージの公開提案）。既知のパス、`$schema` URL、フィールドの正確な構成は**未確定**なので、それぞれ設定可能です（`path`、`schema_url`、`name`、`website_url`）。このサーバーは**stdio**（`php artisan seo-pro:mcp`）で動くため、カードにHTTPの`remotes`ブロックはありません。検出の手がかりであり、接続可能なHTTPエンドポイントではありません。利用前にクライアントとパス・形式の互換性を確認し、不要なら無効のままにしてください。
:::

## プロトコルの補足 {#protocol-notes}

ツールだけを提供するMCPサーバーのJSON-RPC 2.0インターフェースは小さく、この実装では`initialize`（バージョン交渉と機能ハンドシェイク）、`tools/list`、`tools/call`、`ping`を直接実装しています。`2025-06-18`のプロトコルバージョンを告知し、`2025-03-26`と`2024-11-05`も理解します。未知のメソッドは`-32601`、不正な行は`-32700`を返します。**ツール**の失敗は通信エラーではなく、アシスタントが読める`isError`結果として返します。`notifications/initialized`など`id`のない通知には、仕様どおり応答しません。

## ヘッドレス利用と拡張 {#headless-extending}

`SeoPro::mcp()`はツールレジストリを返すため、公開ツールの調査や独自ツールの登録に使えます。

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

独自ツールは`McpTool`（`name`、`description`、`inputSchema`、`isEnabled`、`handle`）を実装します。`AbstractTool`を継承すればモデル許可リストの解決を再利用でき、標準ツールと同じ安全策を引き継げます。

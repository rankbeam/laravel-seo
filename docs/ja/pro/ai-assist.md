---
description: "自分のAPIキーで使う任意のAI支援です。タイトルとメタ情報の提案、スキャン問題の分かりやすい説明、ワンクリックの書き直し、schema.orgの提案に対応します。デフォルトは無効です。"
---

# AI支援 {#ai-assist}

**自分のAPIキー**で利用する、任意のAI支援です。タイトルとメタディスクリプションの提案、スキャン問題の分かりやすい説明、ワンクリックの**ディスクリプション書き直し**、**構造化データ（schema.org）の提案**を提供します。**デフォルトは無効**で、フラグが無効ならAIの処理経路は一切実行されません。

設計の特徴は3つあります。

- **自分のキーとプロバイダー。** リクエストは*自分のサーバー*から、*自分で*設定したAnthropic、OpenAI、Google、またはローカル・OpenAI互換サーバーへ直接送信します。料金が発生する場合は自分のアカウントに請求されます。Rankbeamが中継、従量課金、再販売することはなく、パッケージがテレメトリを外部へ送ることもありません。
- **対話的な提案には明示的な受け入れが必要。** 提案を選ぶとフォームに入力され、ダッシュボードの修正には適用操作が必要です。Pro 2.42以降、一括CLIはデフォルトで非公開の下書きを保存します。`--auto-apply`は、即時書き込みを意図的に選ぶ場合だけ使ってください。
- **失敗しても本来の処理を妨げない。** キーの不足・不正、残高不足、レート制限、タイムアウトは画面内のメッセージとして表示します。保存、描画、スキャンを妨げることはありません。

## プロバイダーの一覧 {#providers-at-a-glance}

アカウントの利用条件、データ要件、費用で選んでください。4つの連携先は同じタスクを提供しますが、対応モデル、出力形式、速度、品質は異なる場合があります。

| プロバイダー | 出荷時のデフォルトモデル | 構造化出力 | 参考費用 | 用途 |
|---|---|---|---|---|
| **Local**（Ollama / LM Studio / vLLM） | `llama3.1`（自分で設定） | ベストエフォート（`response_format`） | 自己ホスト推論の**API料金は$0**。インフラ費用は必要 | データの送信先を管理 |
| **OpenAI** | `gpt-5.5` | 選択モデルが対応する場合にStructured Outputsを使用 | 下記の例の仮定で約$0.005 / 提案 | 既存のOpenAIアカウント |
| **Anthropic** | `claude-opus-4-8` | 対応時に`output_config.format` | 同じ仮定で約$0.015 / 提案 | 既存のAnthropicアカウント |
| **Google** | `gemini-2.5-flash` | 対応時に`responseSchema` | 同じ仮定で約$0.0005 / 提案 | Googleアカウント。モデルの割り当てと料金を確認 |

これらの名前は出荷時の設定を示すもので、現在自分のアカウントで使える保証ではありません。費用はパッケージの例の仮定を使い、現在の料金を検証した値ではありません。連携の挙動と、公表した試行での観測は次のとおりです。

- **構造化出力。** 対応するOpenAI、Google、Anthropicの経路にはJSONスキーマを渡し、不正な応答は適切に失敗させます。ローカルサーバーにはベストエフォートで`response_format`を渡します。無視された場合は寛容な解析により、有効な一覧または失敗を返し、部分的な出力を適用しません。
- **推論はトークン消費を変える。** 紹介したGeminiの試行では、ディスクリプションに非表示の推論約500トークンと可視出力約100トークンを使いました。試したAnthropicの呼び出しでは非表示推論トークンは報告されませんでした。これは各モデル系列に普遍的な性質ではありません。非表示トークンが出力として課金される場合があるため、後述の推論用下限があります。
- **モデルは設定可能。** アダプターのAPIとパラメーターに対応する利用可能なモデルを`SEO_PRO_AI_MODEL`に設定します。例は`claude-haiku-4-5`、`gpt-5.4-mini`、`gemma-3-12b-it`です。コレクション全体に使う前に、対応状況と出力品質を確認してください。

## 設定 {#setup}

機能を有効にし、プロバイダーのキーを環境変数に設定します。クラウドプロバイダーを変える場合はプロバイダーとキーを更新し、モデルの上書き設定も確認します。ローカルアダプターにはサーバーURLも必要です。

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip プロバイダーのAPIキーはClaude / ChatGPTのサブスクリプションと別
Claude Code、Claude.ai、ChatGPTの**サブスクリプション**は**API**の料金を賄いません。`SEO_PRO_AI_API_KEY`には、プロバイダーの開発者コンソールで発行した*従量課金のAPIキー*（またはGoogle AI Studioキー）と、独立した残高が必要です。サブスクリプションだけのアカウントや残高のないアカウントは、認証できても**残高・割り当て不足**を返します。[トラブルシューティング](#troubleshooting)を参照してください。
:::

設定ファイル（`config/seo-pro.php`の`ai`ブロック）には、`timeout`、`max_input_chars`、`max_output_tokens`、`token_budgets`、`reasoning_models` + `reasoning_min_output_tokens`、`suggestion_count`、`bulk_model`（一括補完用の低価格モデル。[費用](#cheaper-bulk-generation)を参照）、`retry`、`pricing`表、`local`サブブロックがあります。[上限と調整](#limits-and-tuning)で説明します。

::: warning 設定キャッシュ使用時のキー管理
設定に保存するのは環境変数の**名前**（`api_key_env`）だけで、キーそのものは保存しません。そのため`php artisan config:cache`がキーを`bootstrap/cache/config.php`に書き出すことはありません。一方、設定をキャッシュすると`.env`を読み込まなくなるため、サーバーの実際の環境変数として`SEO_PRO_AI_API_KEY`を設定してください。
:::

## ローカル推論とクラウドの選択肢 {#running-at-0-and-the-cheapest-paid-option}

- **自己ホスト推論にはプロバイダーのトークン単位API料金がかかりません。** ハードウェア、電力、運用には費用がかかります。コンテンツが自分のネットワーク内にとどまるのは、設定した推論サーバーとその依存先もネットワーク内にある場合だけです。
- **Googleの割り当てと料金はモデル・利用枠ごとに異なります。** AI Studioキー（`aistudio.google.com/apikey`、形式`AIza…`）で無料枠の試行ができる場合があります。必要に応じて課金を有効にする前に、その制限が処理量に合うか確認してください。出荷時のデフォルトは`gemini-2.5-flash`です。GeminiとGemmaのすべてのモデルが同じ推論機能を持つわけではありません。`reasoning_models`は設定した名前パターンを適用するもので、機能を検査しません。

推論の実行先を制御するには、次を使います。

- **Local / OpenAI互換。** `provider=local`で、**Ollama**、**LM Studio**、**vLLM**、**LocalAI**などのOpenAI Chat Completions互換サーバー、または**OpenRouter**などのリモートゲートウェイを使います。`SEO_PRO_AI_LOCAL_BASE_URL`にAPIルートを設定し（`/chat/completions`を付加します）、利用可能な`SEO_PRO_AI_MODEL`を選びます。リモートゲートウェイはネットワーク外でデータを受け取り、課金する場合があります。アダプター名が`local`でも、推論がローカルとは限りません。

::: warning Localの`base_url`は検証対象：localhostは明示的に許可
`base_url`は強い権限を持つ設定で、他の外向き取得と同じ`SsrfGuard`で検証します。http/httpsのみ、userinfoなしで、デフォルトでは**公開**アドレスに解決される必要があります。誤った、または悪意のある`base_url`を内部サービス探索に転用させないためです。本当にローカルのサーバーは`127.0.0.1`（プライベートアドレス）にあるので、`seo-pro.ai.local.allow_local_addresses`（`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`）の明示的な有効化が必要です。公開ゲートウェイ（OpenRouter）では無効のままにしてください。リクエストパスは固定でリダイレクトを追跡しないため、キーを別ホストへ転送させることはできません。
:::

::: tip 対応するOllamaモデルで推論を制御
ローカルの推論モデルはデフォルトのタイムアウトを超える場合があります。モデルとサーバーバージョンが対応していれば、`seo-pro.ai.local.extra_body`内の`['think' => false]`で提案時の推論を無効にできます。対応は異なるため、[Ollamaの文書](https://docs.ollama.com/capabilities/thinking)を確認してください。必要なら`seo-pro.ai.timeout`を長くします。一部モデルが拒否するため、パッケージは`temperature`を送信しません。
:::

## 費用 {#cost}

パッケージによる上乗せ料金はなく、プロバイダーへ直接支払います。自己ホスト推論にはプロバイダーAPI料金はありませんが、インフラ費用は必要です。考える数値は、対話的利用の**提案単位**の費用と、コレクション全体の**一括補完**の費用です。

`seo-pro.ai.pricing`表（1,000,000トークンあたりのUSD）は、推定トークン数を一括補完の確認画面に示すドル額へ変換します。これは**出荷時の見積もり用仮定**で、現在の公開価格を検証した値ではありません。正確な見積もりには、**プロバイダーの最新の公開価格で上書きしてください**。

| モデルパターン | 入力 $/1M | 出力 $/1M |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

公開した例は、テストしたページのトークン消費（タイトル1つとディスクリプション1つ）に上記の仮定を適用したものです。最後の列では対応アダプターに例示の50%バッチ割引を適用します。現在価格の提示ではありません。

| プロバイダー / モデル | 提案1組あたりの概算 | 1,000レコードあたりの概算（一括補完） | 1,000レコードあたりの概算（`--batch`） |
|---|---|---|---|
| Local `gemma`/`llama`（Ollama） | **API料金$0** | **API料金$0** | 対象外（アダプター未実装） |
| Google `gemini-2.5-flash`（有料） | 約$0.001 | 約$0.40 | 対象外（Rankbeamアダプター未実装） |
| OpenAI `gpt-5.5` | 約$0.008 | 約$5.25 | **約$2.63**（50%割引） |
| Anthropic `claude-opus-4-8` | 約$0.03 | 約$20 | **約$10**（50%割引） |

コマンドは推定を約±50%と表示しますが、**支出上限でも、保証された誤差範囲でもありません**。実際の入力・出力・価格で合計は変わります。見積もりは可視出力をモデル化し、課金される非表示の推論で費用が超過する場合があります。価格エントリのないモデルは、推定トークン数だけを表示します。

### 一括生成の費用を抑える {#cheaper-bulk-generation}

`seo-pro.ai.bulk_model`（`SEO_PRO_AI_BULK_MODEL`）を設定すると、**一括補完だけ**（`seo-pro:ai-fill` / `SeoPro::aiFill()`）別モデルを使えます。Filamentと`seo-pro:ai-suggest`は`model`を維持します。nullなら一括補完も`model`を使います。見積もりには選択モデルの価格パターンを使います。処理量を増やす前に代表的な出力を評価してください。安いモデルが自動的に適しているわけではありません。

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

パッケージの例では、**anthropic**の`claude-haiku-4-5`、**openai**の`gpt-5.5-mini`、**google**の`gemini-2.5-flash`、または小規模な**local**モデルを使います。プロバイダーが提供していない名前にも価格パターンが一致する場合があるため、設定前に実際のモデルID、API互換性、価格を確認してください。

**100ページの補完**では、各ページでタイトルとディスクリプションが両方欠けていると、プロバイダー呼び出しは200回です。出荷時の`pricing`デフォルトと、1回あたり入力600 + 出力150トークンの見積もりモデルに基づく、品質重視モデルと`bulk_model`低価格モデルの比較です。

| プロバイダー | 品質重視モデル：100ページ | `bulk_model`低価格モデル：100ページ |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **$4.05** | `claude-haiku-4-5` ≈ **$0.27** |
| **OpenAI** | `gpt-5.5` ≈ **$1.05** | `gpt-5.5-mini` ≈ **$0.11** |
| **Google** | `gemini-2.5-pro` ≈ **$0.45** | `gemini-2.5-flash` ≈ **$0.04** |
| **Local**（Ollama / vLLM） | 任意のモデル：**API料金$0** | 任意のモデル：**API料金$0** |

これらは説明用の推定で、±50%の範囲を保証せず、非表示推論の分も含みません。見積もりを使う前に、`seo-pro.ai.pricing`を選択したプロバイダーの公開価格に更新してください。

## 出力言語 {#output-language}

すべてのプロンプトにページの言語とBCP-47コードを明記します。たとえば*「抜粋に他の言語が含まれていても、ページの言語であるブラジルポルトガル語（pt-BR）で」*と指定し、モデルに送るページコンテキストには`Language:`行を含めます（Pro 2.34）。以前は「ソースコンテンツと同じ言語で」としており、短い抜粋や複数言語の混在からモデルに推測させていました。そのため、抜粋に英語ブランド名のあるトルコ語ページで英語が返ることがありました。使用するロケールはページのメタデータを解決したロケール（ページに指定がなければアプリのロケール）で、[文字数上限](/ja/guide/multilingual#title-and-description-budgets-per-script)を選ぶものと同じです。日本語ページなら、*日本語で*約30文字のタイトルを求めます。

Pro 2.36以降、明示的なコンテンツロケールが、メタデータ行、コンテンツのフック、プロンプト言語を一緒に制御します。操作者のインターフェース言語は変えません。

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

既存の位置引数は変わりません。`locale:`を省略すると、モデルの`seoData()`のデフォルトを使い、個別の翻訳モデルが自分の言語を宣言できます。抜粋は`getContentForSEO()`が空でない内容を返せばそれを使い、なければ設定したコンテンツフィールドへフォールバックします。Filament 1.11は、単一ロケールモードやページ切り替えモードも含め、選択タブのロケールを自動的に渡します。

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

一括の`plan()`、`fill()`、`submitBatchFill()`も末尾に`locale:`を受け取れます。`FillProgress(..., locale: 'it')`の作成と実行の送信では同じロケールを使ってください。各バッチ項目がコンテンツロケールを記録し、回収時は保存したロケールで書き込み前にメタデータ行を再確認します。明示ロケールの実行は別のチェックポイントファイルを持ち、処理済みマーカーも言語を区別します。同じコマンドを再実行して回収してください。独自ジョブではコンテンツロケールをシリアライズして渡す必要があります。

Pro 2.36より前のチェックポイントにはコンテンツロケールがありません。未完了の旧バッチは保持され、自動回収は拒否されます。`--fresh`で破棄する前に、プロバイダーの結果と意図したロケールを照合してください。そうしないと、置き換えの再送信で同じ処理が再び課金される場合があります。処理済みレコードのある旧逐次チェックポイントも、リセット前に照合が必要です。

### 言語ごとの評価 {#per-language-evaluation}

Proのソースリポジトリには、17ロケールにわたる170の入力ページと、任意で有効にする評価ハーネスがあります。入力ページには構造チェックと基本言語のヒューリスティックチェックがあり、独立したネイティブの承認は未完了です。

ハーネスは**タイトルとディスクリプションの両方**を確認し、グラフェム長と言語・文字体系の証拠を記録し、アサーションの前にプロバイダー応答を保持します。短いタイトル、混在テキスト、中国語・日本語で共用する漢字には不確実性が残る場合があります。基本言語がポルトガル語という推測だけではブラジルの用法を証明できず、中国語の部分的な文字チェックも地域別の文章品質を証明しません。

実際の呼び出しを伴う実行には、`SEO_PRO_AI_EVAL=1`、明示的な`SEO_PRO_AI_EVAL_LOCALES`選択、`SEO_PRO_AI_EVAL_RUN` IDが必要です。プロバイダー料金が発生する場合があり、デフォルトでは実行しません。各実行は証拠をプロバイダー、要求・返却されたモデル、フィクスチャ・リクエスト・コードのハッシュ、日時に結び付けます。失敗した試行も保持します。再開時は保存済み応答を再利用しますが、中断したリクエストはすでにプロバイダーに届いている可能性があるため、明示的な再試行が必要です。

証拠はソースのテスト環境の`storage/app/seo-ai-evals/<run-id>/`配下に保存します。フィクスチャの`README.md`が、バージョン付きスキーマとコマンドを説明しています。ネイティブのレビュアーは、別のレビュー記録で正確な出力ハッシュに対して評価します。自動チェックの合格はネイティブの承認でも、公開可能な文章の保証でもありません。

## 上限と調整 {#limits-and-tuning}

すべての調整項目は`config/seo-pro.php`の`ai`ブロックにあります。

- **`timeout`**（デフォルト`15`秒、環境変数`SEO_PRO_AI_TIMEOUT`）：Filamentの提案モーダルを開く際の同期呼び出しの上限でもあり、操作性のため短くしています。**遅い推論モデルやローカルモデルは15秒を超える**場合があります。使う場合は`SEO_PRO_AI_TIMEOUT`で長くし、上記のOllama `think => false`の補足も確認してください。タイムアウトは致命的ではなく、画面内にエラーを出すだけで保存を妨げません。
- **`max_input_chars`**（デフォルト`6000`）：1リクエストで送るページ内容（HTMLを除いたプレーンテキスト）の、費用とプライバシーのための上限。
- **`max_output_tokens`**（デフォルト`1000`）：生成トークンの基本上限。到達した応答は明確な`truncated`失敗となり、黙って半分の回答を返しません。
- **`token_budgets`**：タスク別の出力上限（`suggestions` 800、`explanation` 600、`rewrite` 300、`schema_suggestion` 700）。いずれもデフォルト全量は不要ですが、さらに推論用下限を適用します。
- **`reasoning_models`** + **`reasoning_min_output_tokens`**（デフォルト`2000`）：名前がパターン（`*gemma*`、`gemini-2.5-*`、`o1*`/`o3*`/`o4*`）に一致するモデルは、出力枠をこの下限まで増やします。推論モデルは可視出力の前に非表示トークンを使い、小さい枠では途中で切れるためです。
- **`suggestion_count`**（デフォルト`3`）：タイトル・ディスクリプションの候補数。
- **`retry`**：*一時的な*失敗だけを自動再試行します。[応答の処理方法](#how-replies-are-handled)を参照してください。

## Filamentでの利用 {#in-filament}

任意のFilamentパッケージ（`rankbeam/laravel-seo-filament` >= 1.1）がある状態でAI支援を有効にすると、次が追加されます。

- SEOセクションを使う全リソースの編集ページで、SEOタイトル・ディスクリプションのフィールドに**AI で提案**を表示します。モーダルには生成候補と文字数が並び、1つ選ぶとレビュー用にフィールドへ入力します。
- ダッシュボードの問題一覧に**説明（AI）**を表示します。問題と具体的な修正を、短く分かりやすく説明します。
- ダッシュボードの問題一覧で説明の隣に**ディスクリプションを書き換え（AI）**を表示します。ページのディスクリプション上限（ラテン文字160、CJK約80。コアの[文字数ポリシー](/ja/guide/multilingual#title-and-description-budgets-per-script)）内で、改善案を1つ提案します。モーダルで確認し、**書き換えを適用**するとページの`seo_meta`レコードに保存します。適用するまで何も書き込みません。
- ダッシュボードの問題一覧に**構造化データを提案（AI）**を表示します。適したschema.orgリッチリザルト型（Product、Article、Breadcrumb）を提案し、組み立てたJSON-LDを示します。**構造化データを適用**すると、任意の[構造化データエディター](/ja/guide/filament#structured-data-schema-org)が管理するのと同じ`seo_meta.schema_jsonld`カラムに追加するため、エディターとの往復と再編集ができます。不完全な提案（著者や画像のないArticleなど）は不足フィールドを表示し、適用**しません**。

最後の2つは制約付きの修正です。[制約付きの修正](#bounded-fixes-propose-never-auto-apply)を参照してください。

## 制約付きの修正（提案のみ、自動適用なし） {#bounded-fixes-propose-never-auto-apply}

2つの支援アクションは一般的な提案より一歩進み、ワンクリックで適用できる、*制約内の*単一値を生成します。どちらも**提案だけ**で、明示的に受け入れるまで保存しません。

- **ディスクリプションの書き直し**（`SeoSuggestionService::rewriteDescription($model, $issue?)`）は、**ページの文字体系に対するコアの文字数上限（ラテン文字160、CJK約80）以内**のメタディスクリプションを1つ返します。タイトル・ディスクリプションの提案プロンプトと同じ上限で、ページ自身の解決済み値から選びます。モデルが超過した場合は文境界、次に単語境界で決定的に切り詰めるため、受け入れた書き直し自体が`description_too_long`警告を起こすことはありません。スキャン問題を渡すと、「長すぎる」「欠落」などの内容に沿って書き直します。
- **構造化データの提案**（`SeoSuggestionService::suggestSchemaType($model)`）は、モデルに**推奨する型と末端フィールドの値だけ**を求め、生のJSON-LDは求めません。コアのスキーマビルダー（`ProductSchema` / `ArticleSchema` / `BreadcrumbSchema`）で決定的なコードが文書を組み立て、コアの`SchemaValidator`で検証します。そのため、架空の`@type`、`@context`、構造がページに届くことはありません。必須フィールドが欠ける場合は*不完全*と表示して適用を止めます。実際のテストでは、あるプロバイダーが内容の少ないページに`Article`を提案しましたが、著者と画像がなく正しく保留され、他のプロバイダーは型の提案自体を見送りました。

## ヘッドレス {#headless}

スクリプトやFilament以外のアプリでも、同じ機能をJSONとして使えます。

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

出力には、提案（または推奨型、生成したJSON-LD、検証結果）、使用モデル、**リクエストごとのトークン消費**（入力 / 出力 / 推論）が含まれます。プロバイダーの単価で費用を計算するための情報で、トークン数は請求書ではありません。失敗時はJSONエンベロープにエラーを入れ、非ゼロで終了します。支援機能の他の操作と同様、`seo-pro:suggest-schema`は**提案のみ**で、文書を出力するだけで書き込みません。

## 不足メタデータの一括補完 {#bulk-fill-missing-metadata}

**Pro 2.42でCLIのデフォルトが変わりました。** `seo-pro:ai-fill`は生成フィールドごとに非公開の下書きを1つ保存します。承認するまで公開SEOメタデータは変わりません。既存値と算出値によるフォールバックはスキップします。現在のソースに合う保留中の下書きがあれば、再生成せず再利用します。

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review`は保留中の下書きの先頭100件をJSONで一覧表示します。IDを指定して、値と非公開の証拠を確認してください。承認・却下はAI無効時も動作し、プロバイダー呼び出しは行いません。承認には操作者ラベルが必要で、元レコードや対象メタデータが変わった下書き、またはレコードが削除された下書きは拒否します。ラベルは操作者が名乗った名前の記録であり、実質的な人間のレビューを証明しません。設定した非デフォルトのデータベースには`--connection=NAME`を使います。

`--auto-apply`は、まだ不足しているフィールドを即時公開する挙動を明示的に戻します。`--force`が省略するのは確認であり、**レビューではありません**。`--dry-run`は値を生成・表示し、下書きもメタデータも保存しませんが、プロバイダーを呼び出すので費用がかかる場合があります。`--field`、`--limit`、`--locale`で生成範囲を絞ります。更新後は定期コマンドを意図的に見直してください。

### 大規模運用：実行間隔、費用見積もり、中断からの再開 {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms`のデフォルトは200ミリ秒です。`confirm_over`（デフォルト100レコード）で、`seo-pro.ai.pricing`による見積もりを表示し、生成前に確認します。チェックポイントは中断しても完了済みフィールドを保持します。プロバイダーが受理した後のタイムアウトでは重複課金が起こり得るため、`--fresh`の前に不確実な処理を照合してください。同じ条件の一括ジョブは同時に1つだけ実行します。

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

独自連携では、下書きを保存するため`review: true`を渡します。低水準のPHP APIは互換性のため`apply: true, review: false`を維持するので、既存の呼び出しは引き続き即時書き込みます。`apply: false`は保存せずプレビューします。概要のキー`filled`は処理したレコード数で、レビューモードで下書きにしたレコードも含みます。CLIはそれらを`staged`と表示します。

### バッチモード（50%割引） {#batch-mode-50-cheaper}

`--batch`は対応するAnthropicまたはOpenAIの非同期エンドポイントを使います。文書化された割引を見積もりに反映しますが、モデルの現在の価格を確認してください。Googleとlocalアダプターは逐次生成にフォールバックします。送信してから、後で同じコマンドを実行して下書きを回収します。

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

送信から回収まで、プロバイダー、ロケール、公開モードを変えないでください。レビューと`--auto-apply`は別のチェックポイントを使い、同じ条件のバッチが未完了の間は他方のモードの開始を拒否します。送信結果が不確実なら、照合のため停止します。部分的な成功は保持し、一時的な失敗は再試行できます。回収時は不足フィールドを再確認し、承認時は送信前のソーススナップショットも確認します。`seo-pro.ai.fill.batch.request_timeout`のデフォルトは120秒です。定期実行による回収も、デフォルトでは下書きを保存します。

## 生成元の記録、マイグレーション、データフィルタリング {#origin-review-and-filtering}

**コア3.21とPro 2.42**が必要です。コアはマイグレーションを自動ロードします。保存する提案を生成する前に、Proのマイグレーションを公開し、SEOモデルが使う各データベースへ適用してください。

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

非公開の`seo_ai_proposals`テーブルは、生成値とプロバイダー・モデル・リクエストの証拠をLaravelの暗号化キャストで保存します。`APP_KEY`とそのバックアップを安全に保持してください。失うと値が読めなくなります。レコード識別子、状態、判断のメタデータは通常のDBカラムのままです。フォームを放棄した場合、候補は`offered`または`selected`のまま残ることがあります。自動削除はありません。アプリケーションの保持方針を定め、保留中の下書きと`seo_meta.ai_provenance`から参照される証拠を残し、DBエクスポートとレビューコマンドの出力へのアクセスを制限してください。

受け入れたフォーム提案、ダッシュボードの修正、一括値は、フィールドの生成元を持ちます。後でEloquent編集すると`origin: ai`を保持し、`edited: true`を設定します。これは値が変わったという意味で、人間による検証ではありません。フィールドを空にするとマーカーも削除します。コアのHTML、配列、JSON、Inertia出力が公開するのはフィールド名、生成元、編集状態だけで、該当する出力では独自の`rankbeam:ai-origin`メタタグを使います。生成IDとプロバイダー詳細は非公開です。生の`SEOMeta`モデルを公開APIに出さないでください。

これは今後の対応する保存経路を記録するもので、過去のコンテンツやすべての編集履歴を記録するものではありません。直接SQL、クエリビルダーの更新、独自レンダラーでは制御を経由しない場合があります。独立して執筆した内容への置き換えで必要なら、生成元を明示的にリセットしてください。通常の編集では保持します。この独自マーカーは標準化された透かし、改ざん防止の帰属情報、Article 50への準拠の主張ではありません。実際のプロバイダー出力品質とプロバイダー固有のマーキングには別の評価が必要です。

任意で`AiPromptFilter`を実装し、`seo-pro.ai.context_filter`に設定できます。同期またはバッチ送信前に、組み立て済みのユーザープロンプトをフィルターします。失敗すると送信を止め、安全化したエラーを返します。システム指示は変えません。デフォルトは`null`で、**機密データを自動マスキングしません**。この例は既知の1つの値だけを置き換えます。アプリに合ったルールを実装・テストしてください。

```php
namespace App\Support;

use Rankbeam\Seo\Pro\Ai\AiPromptFilter;

final class RedactAiContext implements AiPromptFilter
{
    public function filter(string $prompt): string
    {
        return str_replace('internal@example.com', '[redacted]', $prompt);
    }
}

// Configure seo-pro.ai.context_filter with this class in config/seo-pro.php.
// Runtime equivalent:
config(['seo-pro.ai.context_filter' => RedactAiContext::class]);
```

## 応答の処理方法 {#how-replies-are-handled}

すべての呼び出しが共通のプロバイダー非依存エンベロープを返し、プロバイダー間でも、後から加わるプロバイダーでも、同じ挙動になります。

- **対応するプロバイダーでは構造化出力。** OpenAI（標準のStructured Outputs）、Google（Gemini `responseSchema`）、Anthropic（`output_config.format`）はAPIがJSONの形を強制します。有効なJSONでない応答は失敗にし、テキストから拾い集めることはありません。ローカル・OpenAI互換サーバーにもベストエフォートで要求します（`response_format`）。フィールドを無視するサーバーがテキストを返した場合は、代替として寛容な解析を使います。どちらの場合も、完全な一覧か明確な失敗を返し、中途半端に解析した応答は返しません。
- **出力の打ち切りは明示的で対処可能なエラー。** 出力トークン上限で応答が切れた場合、黙って短いタイトルを返さず、`seo-pro.ai.max_output_tokens`を上げるよう案内する`truncated`エラーを返します。**推論・思考モデル**で特に起こりやすく、これらには高い`reasoning_min_output_tokens`下限を自動適用します。
- **一時的な失敗を自動再試行。** `429`のレート制限や`5xx`は、上限付きの指数バックオフで再試行します。`Retry-After`ヘッダーがあれば尊重しますが、悪意ある値で停止し続けないよう制限します。**決定的な**失敗、つまり不正キー、不正リクエスト、過大なペイロード、**タイムアウト**、**残高・割り当て不足**は再試行**しません**。残高のないアカウントを繰り返しても待ち時間が増えるだけです。`retry`ブロックで調整し、`max_attempts`を`0`にすると無効になります。
- **エラーは型付きで安全化。** 各失敗は固定コード（`unauthorized`、`quota_exceeded`、`rate_limited`、`timeout`、`content_too_large`、`bad_request`、`truncated`、`content_filtered`、`provider_error`など）を持ち、一時的なものは`retryable`フラグを持ちます。メッセージは短い安全化済み文字列です。**通常のアプリ処理ではプロバイダーの生レスポンス本文を表示・ログ記録しません。一方、上記の任意の評価ハーネスは証拠として応答を保存します。** Filamentではスタイル付きモーダルの部分ビューに失敗を表示し、一般的な失敗には個別の次の対処を案内します（[トラブルシューティング](#troubleshooting)を参照）。

## トラブルシューティング {#troubleshooting}

すべての失敗は画面内に表示され、致命的ではありません。型付きコードと安全化したメッセージを持ちます。よくある原因と実際の対処は次のとおりです。

| 症状（エラーコード） | 意味 | 対処 |
|---|---|---|
| **`quota_exceeded`**：*プロバイダーアカウントの残高または割り当てが不足しているという内容* | キーは有効だが、**APIアカウントに残高・割り当てがない**。レート制限ではなく、再試行しても改善しない。Anthropicは残高不足、OpenAIは現在の割り当て超過とプラン・請求の確認（`insufficient_quota`）、Googleは前払い残高の枯渇を示す。 | プロバイダーのコンソールで残高追加・課金有効化を行うか、プロバイダーAPI料金のない**ローカル**モデルへ切り替える。Claude/ChatGPTの**サブスクリプション**は**API**の料金を賄わない。 |
| **`unauthorized`**：*認証に失敗したという内容* | キーがない、不正、または設定したプロバイダーで無効。 | `seo-pro.ai.api_key_env`が指定する環境変数（デフォルト`SEO_PRO_AI_API_KEY`）のキーを確認する。設定済み・有効で、`SEO_PRO_AI_PROVIDER`と一致する必要がある。 |
| **`rate_limited`**：*プロバイダーのレート制限に達したという内容* | 実際の**一時的な**レート制限。先に自動再試行を行う。 | 待って再試行するか、サーバー容量の範囲で**ローカル**モデルへ移る。低い利用枠での一括実行では`seo-pro.ai.fill.throttle_ms`を増やす。 |
| **`timeout`**：*リクエストがタイムアウトしたという内容* | `seo-pro.ai.timeout`（デフォルト15秒）以内に応答がなかった。**遅いローカル推論モデル**でよく起こる。 | `SEO_PRO_AI_TIMEOUT`を長くする。Ollamaでは`seo-pro.ai.local.extra_body`内に`['think' => false]`も設定する。 |
| **`truncated`**：*max_output_tokens上限に達したという内容* | 非表示推論を含めて出力枠に達した可能性がある。 | `seo-pro.ai.max_output_tokens`を増やす（推論モデルは2000以上必要な場合あり）か、モデルが`reasoning_models`パターンに一致して下限が適用されることを確認する。 |
| **`content_too_large`**（HTTP 413） | 送信したページ内容がプロバイダー上限を超えた。 | `seo-pro.ai.max_input_chars`を下げ、短い抜粋を送る。 |
| **`bad_request`** | 不正なリクエスト。通常はアカウントが使えない**モデル名**や非対応パラメーター。 | `SEO_PRO_AI_MODEL`が、設定したプロバイダーでキー・サーバーからアクセスできるモデルか確認する。 |
| **`content_filtered`** | プロバイダーの安全フィルターが回答を拒否した。 | 内容とプロバイダーの案内を確認し、拒否されたリクエストを自動反復しない。 |

::: tip ローカル推論にも動作するサーバーが必要
`SEO_PRO_AI_PROVIDER=local`を自己ホスト推論に接続すればクラウドプロバイダーの残高問題は避けられますが、ハードウェア、モデル、API互換性、タイムアウト、容量は引き続き重要です。このアダプターで設定したリモートゲートウェイにはキーと支払いが必要な場合があります。
:::

## サーバーから送信するもの {#what-leaves-your-server}

明示的な操作（アクションのクリックまたはコマンド実行）時に限り、設定したプロバイダーだけへ次を送信します。

- *提案*：モデルのクラス短縮名とキー（例：「Post #3」）、現在の解決済みタイトルとディスクリプション、正規URL、`max_input_chars`（デフォルト6000文字）で制限したプレーンテキストの抜粋（HTML除去済み）。
- *問題の説明*：問題の種別、重大度、フィールド、メッセージ、対象URL。モデルがあれば、そのクラス・キー、解決済みタイトル・ディスクリプション、正規URL、上限付きのプレーンテキスト抜粋も送る。
- *ディスクリプションの書き直し*：提案と同じ最小限のページコンテキストと、指定されていればスキャン問題の種別・メッセージ。
- *構造化データの提案*：提案と同じ最小限のページコンテキスト。モデルは型と末端フィールド値だけを返し、JSON-LDはローカルで組み立てる。

パッケージが意図的に訪問者データ、IPアドレス、リクエストヘッダー、認証情報をプロンプトへ集めることはなく、HTML全体も送りません。ただし、**コンテンツフィールドや抜粋自体に機密情報が含まれる場合があります**。アプリが公開する内容を確認してください。プロバイダーの認証情報はリクエストの認証に使います。データ処理のリファレンスはProリポジトリのSECURITY.mdです。

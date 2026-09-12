---
description: "スキャン、リダイレクト、404ログなど、すべてのPro機能はFilamentなしでヘッドレス実行できます。ArtisanからProを管理するためのコマンドリファレンスです。"
---

# ヘッドレスでの利用 {#headless-usage}

スキャン、リダイレクト、404ログなど、すべてのPro機能はヘッドレスで実行できます。機能はエンジンにあり、Filamentは不要です。パネルは管理UIであり、以下のコマンドがヘッドレスでの操作手段になります。

## コマンドリファレンス {#command-reference}

### セットアップとヘルスチェック {#setup-health-check}

| コマンド | 動作 |
|---|---|
| `seo-pro:install` | `config/seo-pro.php`とProのマイグレーションを公開して実行し、次の手順を表示します（`--no-migrate`、`--force`）。 |
| `seo:doctor` | アプリURL、CoreとProのテーブル、スキャン対象、サイトマップ、処理別のキュー、任意の機能、運用状態を一括確認します。警告には具体的な修正方法を表示します（監視には`--json`）。 |

`seo-pro:install`が、このドキュメントで案内するセットアップ手順です。Proのマイグレーションは公開して使う方式で、パッケージが自動ロードすることはありません。そのため、`composer require`だけで終わらず、インストーラーを使って動作するスキーマを用意します。冪等なので、いつでも再実行できます。

`seo:doctor`はネットワーク通信を行わず、シークレットの値も表示しません。AIのチェックでは、設定したキー変数が*設定済みかどうか*だけを報告します。設定と最近の実行履歴を検証しますが、外部のcronやワーカーが実際に動いていることは証明できません。必須テーブルの欠落という致命的な失敗の場合だけ、0以外で終了します。そのため、警告のあるlocalhost開発環境でも正常終了します。`--json`では、各チェックに安定した`id`が付くので、識別に使えます。[インストール](/ja/pro/installation)直後とCIで実行してください。

### スキャン {#scanning}

| コマンド | 動作 |
|---|---|
| `seo-pro:scan` | 登録済みの全対象の完全なスキャンをキューに投入します。`--sync`で同期実行できます。**CIチェック**用の`--fail-on-error`、`--fail-on-warning`、`--report=`、`--format=json\|md\|html`には`--sync`が必要です。 |
| `seo-pro:scan-status` | 最新実行の概要と未解決の問題を、重大度の高い順に表示します（`--limit=20`、`--severity=critical\|warning\|notice`）。 |
| `seo-pro:scan-recover` | キューワーカーの停止で放置された実行を失敗として記録します。 |
| `seo-pro:scan-prune` | 保持期間を過ぎた完了済みの実行と、その問題を削除します。 |

### リンク切れクローラー {#broken-link-crawler}

デフォルトでは無効です。`seo-pro.broken_links.enabled`を有効にし、2つのテーブルをマイグレーションしてください。`seo-pro:install`で公開できます。クロールは処理量に上限を設けた複数のキュージョブで実行されるため、そのキュー専用のワーカーを動かしてください。調整方法は[本番環境の設定](/ja/pro/production)を参照してください。

| コマンド | 動作 |
|---|---|
| `seo-pro:broken-links-scan` | 範囲を制限した再開可能なクロールをキューに投入します（`--scope=internal_only\|internal_and_external`、追加の開始URLは`--url=*`）。 |
| `seo-pro:broken-links-status` | 最新クロールの概要、未解決の検出事項、今回の[種別付き検査](/ja/pro/broken-links#typed-link-inspections)を表示します。**CIチェック**にも使えます（`--fail-on-error`、`--fail-on-warning`、`--report=`、`--format=`）。 |
| `seo-pro:broken-links-cancel` | 実行中または待機中のクロールをキャンセルします（`{run?}`。デフォルトは最新のアクティブな実行）。 |
| `seo-pro:broken-links-recover` | ワーカー停止で放置されたクロールを、期限切れリースに基づいて失敗にします。 |
| `seo-pro:broken-links-prune` | クローラーの保持ポリシーを適用します（古い実行と解決済みの検出事項）。 |

### リダイレクトと404 {#redirects-404s}

| コマンド | 動作 |
|---|---|
| `seo-pro:redirect-create {source} {target}` | リダイレクトルールを作成します（`--code=301`、`--regex`、`--no-preserve-query`、`--note=`）。 |
| `seo-pro:404-list` | 記録された404をアクセス数の多い順に表示します（`--status=new\|ignored\|redirected\|all`、`--limit=20`）。 |
| `seo-pro:redirects-flush-hits` | `redirects.hits.flush_immediately=false`の場合、キャッシュにまとめたリダイレクトのアクセス数をデータベースに書き込みます。 |
| `seo-pro:404-prune` | 古い404項目を削除し、行数の上限を適用します。 |

### ページ内チェックリスト {#on-page-checklist}

| コマンド | 動作 |
|---|---|
| `seo-pro:checklist {model} {id}` | 1つのモデルについて、キーワードを考慮したpass/warn/failチェックリストを表示します（`--json`、`--strict`、`--locale=`）。[ページ内チェックリスト](/ja/pro/on-page-checklist)を参照してください。 |

同じチェックリストを`SeoPro::checklistFor($model)`でも利用できます。キーワードの配置、長さ、画像、内部リンクを見直す編集作業向けであり、[SEOスコア](/ja/pro/scoring)では**ありません**。

### Search Console（読み取り専用） {#search-console-read-only}

| コマンド | 動作 |
|---|---|
| `seo-pro:search-console` | 未解決の問題**と**検索流入の両方があるページを、改善機会の優先度が高い順に表示します（`--view=attention`がデフォルト）。 |
| `seo-pro:search-console --view=pages` | 表示回数、クリック数、CTR、掲載順位による上位ページを表示します。 |
| `seo-pro:search-console --view=queries` | 上位クエリを表示します（`--days=`、`--limit=`、`--json`）。 |

同じ指標を`SeoPro::searchConsole()`でも利用できます。[Search Console](/ja/pro/search-console)を参照してください。デフォルトでは無効で、読み取り専用です。

### AIアシスト {#ai-assist}

| コマンド | 動作 |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | タイトル・ディスクリプションの提案をJSONで出力します（`--field=title\|description\|all`）。[AIアシスト](/ja/pro/ai-assist)を参照してください。 |
| `seo-pro:ai-suggest --issue={id}` | スキャンで検出した問題の修正方法を、分かりやすい文章を含むJSONで出力します。 |

### 404を1つの手順で解決する {#resolving-a-404-in-one-step}

`--from-404={path}`は、404監視のワンクリック操作*リダイレクトを作成*のヘッドレス版です。ルールを作成すると**同時に**、一致するログ項目をリダイレクト済みにし、新しいルールと関連付けます。

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

Filamentのフォームと同じバリデーターを使い、不正な正規表現、大きすぎる値、許可リスト外の外部転送先は、何も書き込む前に拒否します。

## 推奨スケジュール {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

上記の各定期コマンドの推奨頻度は、[本番環境の設定](/ja/pro/production)ガイドに記載しています。キュー構成、ワーカー設定、再試行・復旧ポリシー、保持期間、各実行の完了時に出力する構造化**テレメトリー**（取得ページ数、検査リンク数、拒否URL数、所要時間、キュー待ち時間）も扱っています。

## Filament UIが必要な機能は何ですか？ {#what-needs-the-filament-ui}

機能面では何もありません。スキャンパイプライン、問題追跡、リダイレクト照合、404ログ、削除、復旧を含むエンジン全体は、Filamentの有無にかかわらず同じです。パネルが追加するのは*表示と操作の画面*です。スキャンの進捗や重大度別の統計を表示するダッシュボード、フィルターとページ別モーダルでの問題閲覧、無視・再開ボタン、リダイレクトの作成・閲覧・更新・削除フォーム、ワンクリック操作付きの404表が含まれます。問題の無視・再開には現在、専用コマンドがありません。パネルを使うか、tinkerまたは独自コードで`SEOScanIssue`モデルの`markIgnored()` / `reopen()`を呼び出してください。

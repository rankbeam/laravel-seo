---
description: "laravel-seo-proをインストールします。Coreに、課題追跡付きのキュースキャン、リダイレクト管理、404モニターを追加します。Laravel 11〜13で動作し、Filamentは任意です。"
---

# Proのインストール {#installing-pro}

`rankbeam/laravel-seo-pro`は、Coreパッケージに、課題追跡付きのキュー経由のサイトスキャン、リダイレクト管理、404モニターを追加します。エンジンは、Blade、Inertia、API専用を問わず、**Laravel 11〜13のアプリ**で動作します。Filamentは任意のUI層です。インストールすると、SEOダッシュボード、リダイレクト管理、404モニターをパネルのページとして使えます。使わない場合は、[Artisanコマンド](/ja/pro/headless)ですべて管理します。

## 動作要件 {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4（Laravel 11）、8.2–8.5（Laravel 12）、8.3–8.5（Laravel 13） |
| Laravel | 11、12、13 |
| `rankbeam/laravel-seo` | ^3.20（Pro 2.40以降で自動インストール） |
| `filament/filament` | **任意** — 4.xまたは5.x。管理UIにのみ必要です。 |
| `rankbeam/laravel-seo-filament` | **任意** — Pro 2.36以降でSEOエディターを使う場合は^1.11。 |

既存のLaravelアプリと、設定済みのデータベースから始めます。まず[Coreのクイックスタート](/ja/guide/quickstart)を完了し、モデルからメタデータを出力でき、Coreのテーブルが存在する状態にしてください。以下のComposer認証情報はProライセンスに付属します。

結果の画面例は、[スキャン → 修正 → レポート](/ja/pro/walkthrough)を参照してください。

## パッケージをインストール {#install-the-package}

Proは、ライセンスに紐づく非公開のComposerリポジトリで配布します。リポジトリを一度追加し、パッケージをrequireしてください。Composerがライセンスのメールアドレス（ユーザー名）とライセンスキー（パスワード）を要求します。

Lemon Squeezyが販売事業者（merchant of record）として決済を処理します。決済後、非公開の領収書ページにダウンロードキーとComposerの手順が表示されます。ユーザー名には購入時のメールアドレスを使ってください。パッケージリポジトリはRankbeamがホストするため、Anystackアカウントは不要です。領収書リンクと`auth.json`は公開しないでください。全額返金後は将来のダウンロードと更新が失効しますが、インストール済みアプリの動作は中断されません。

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details 非対話環境でのComposer認証
CIや非対話環境では、事前に認証情報を保存します。

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

続いてインストーラーを実行します。

```bash
php artisan seo-pro:install
```

インストーラーは`config/seo-pro.php`とProのマイグレーションを公開し、`migrate`を実行して、次の手順を表示します。これでアプリのデータベースにCoreとProのテーブルが揃います。

::: details 手動インストールとインストーラーのフラグ
Proのマイグレーションはアプリに公開され、パッケージから自動では読み込まれません。同じ処理を手動で行う手順は次のとおりです。

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

インストーラーは再実行できます。`--no-migrate`はマイグレーションせずにファイルを公開します。`--force`は、設定を含む公開済みファイルを上書きする意図がある場合だけ使ってください。
:::

## スキャン対象を登録 {#register-scan-targets}

サービスプロバイダーで、モデルクラス、名前付きルート、または[サイトマップレジストリ](/ja/guide/sitemaps)全体をスキャナーに登録します。

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

`Post`は、`HasSEO`を使う自身のモデルに置き換えてください。モデルのスキャン結果を見るには、レコードが1件以上必要です。ルートの対象には、実在するルート名を指定します。モデルだけをスキャンする場合は、ルートの登録を省いてください。

## インストールを確認 {#verify-your-install}

設定チェックを実行します。

```bash
php artisan seo:doctor
```

CoreとProのテーブルが存在し、アプリのURLが正しく、スキャン対象が表示されていることを確認します。指摘された修正を適用してください。以下のインライン実行コマンドを試す間は、`sync`キューの警告が出ても想定どおりです。本番のスキャンを定期実行する前に、ワーカーを設定してください。

::: details ヘルスチェック出力の例
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor`は、ネットワーク呼び出しやシークレットの表示をせずに、設定と最近の実行履歴を確認します。外部のcronやワーカーが動いていることは証明できません。重大な失敗は0以外の終了コードを返します。警告だけでは終了コードは0以外になりません。機械可読の結果には`--json`を使います。
:::

## 最初のスキャンを実行 {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

最初のコマンドはスキャンをインラインで完了するため、この初回確認にキューワーカーは不要です。2つ目は最新の実行と結果を表示します。登録した対象が処理され、実行が完了していることを確認してください。失敗した対象があれば、スキャン完了とみなす前に調べます。

指摘されたフィールドを1つ修正して保存し、もう一度スキャンします。[操作例](/ja/pro/walkthrough)では、欠けているディスクリプションの修正と、その変化のレポートを示しています。[技術スコア](/ja/pro/scoring)は診断結果であり、検索順位の予測ではありません。

## ヘッドレスでの利用 {#path-b-headless}

エンジンはパネルなしで利用できます。[Artisanコマンド](/ja/pro/headless)でスキャン、課題の確認、リダイレクト作成、レポート生成を行えます。リダイレクトと404のミドルウェアは、デフォルトで自動登録されます。設定は`config/seo-pro.php`にあります。

定期実行では、[本番環境の設定](/ja/pro/production)に従い、キュー、ワーカー、スケジューラー、保存期間を設定してください。

## Filamentパネルを追加（任意） {#path-a-with-a-filament-panel}

既存のFilament 4または5のパネルに、以下のProプラグインを登録します。パネルがまだない場合は、先にUIパッケージをインストールして作成してください。

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

これにより、**SEOダッシュボード**（全件スキャン、進捗のライブ表示、ワンクリックで再スキャンできる課題一覧）、**リダイレクト管理**、ワンクリックの*リダイレクト作成*アクションを持つ**404モニター**が追加されます。`rankbeam/laravel-seo-filament`を加えると、リソースのフォームに[SEOフィールドのセクション](/ja/guide/filament)も追加できます。

## トラブルシューティング {#troubleshooting}

| 結果 | 次の手順 |
|---|---|
| Composerが認証情報を拒否する | `blog.rankbeam.dev`のライセンスメールアドレスとキーを確認します。認証情報をバージョン管理に入れないでください。 |
| Doctorがテーブル不足を報告する | Coreのクイックスタートを完了し、アプリと同じデータベースに対して`seo-pro:install`と`migrate`を実行します。 |
| スキャンで対象が処理されない | プロバイダーへの登録と、モデルにレコードがあるかを確認します。 |
| キューに入れたスキャンが待機中のまま | 設定済みのキューワーカーを起動するか、`--sync`でインラインの確認を行います。 |
| 対象の処理が失敗する | 再スキャン前に、実行の詳細、ルート名、アプリのURLを確認します。 |
| ダッシュボードが表示されない | 実際に使うパネルに`SeoProPlugin`を登録し、アクセス制御のゲートを確認します。 |

ワーカーの復旧と継続運用については、[本番環境の設定](/ja/pro/production)を参照してください。

## ライセンスと返金 {#license}

初期購入者向けライセンスは買い切り€179で、クライアント案件を含む最大5つの本番プロジェクトと、期限のない更新を対象にします。それらのプロジェクトの開発・ステージング用コピーは別に数えません。インストール・移行支援、60分のインストール相談、案内している公開準備キットが含まれます。30日以内であれば、領収書ページまたは[valentinogoxhaj@gmail.com](mailto:valentinogoxhaj@gmail.com)へのメールで、無条件の全額返金を申請できます。全額返金後はProの使用を停止してください。ライセンス対象プロジェクト向けにProを変更できますが、ソースの公開や、単独パッケージ・スターターキットとしての再販売はできません。完全なライセンス条項はパッケージに含まれます。

Proはクライアント案件を含む最大5つの本番プロジェクトで利用できます。それらの開発・ステージング・テスト用コピーは別に数えません。期限のない更新には将来のProリリースが含まれますが、継続的な個別の実装作業は含まれません。

60分のインストール・設定相談1回と、最初の1プロジェクトのメタデータ移行が含まれます。移行は対応済みのソースを対象とし、開始前に範囲を確認します。独自のアプリ変更は別途見積もります。公開準備の設定では、同じプロジェクトに対して、無料のCoreの機能を使い、llms.txt、robots.txtのAIクローラールール、ボット向けMarkdownレスポンスを確認・設定します。付属の支援を手配するには、hello@rankbeam.devにメールしてください。

注文には、購入時に表示されていたオファーが適用されます。

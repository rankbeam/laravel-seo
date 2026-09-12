---
description: "スコア、問題の推移、修正済みと新規の比較、復旧した404、Search Consoleの変動、AIボットの活動を、自社ブランドのPDFレポートにまとめます。1つのコマンドで生成し、定期メール送信も選べます。"
---

# ホワイトラベルレポート {#white-label-reports}

1サイト分の総合スコア、検出した問題数の推移、**前回レポート以降の修正済みと新規の問題**、復旧した404とリンク切れ、Search Consoleの変動、AIボットの活動を、自社ブランドの**PDFレポート**にまとめます。1つのコマンドで生成でき、任意で**定期メール送信**も設定できます。代理店向けに、ロゴ、色、「{client}向けに作成」の表記を入れて顧客に渡せます。

[英語の生成済みサンプルレポートをダウンロード（PDF、98 KB）](/pro-walkthrough/merchant-demo-report.pdf)するか、[スキャン → 修正 → レポートの実例](/ja/pro/walkthrough)をご覧ください。サンプルはMerchantのシードコンテンツと新たな2回のスキャンを使い、修正済み1件、未解決19件、Search Consoleデータなしの状態を示しています。

[![生成したMerchantデモレポートの1ページ目。](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## 含まれる内容 {#what-s-in-it}

- **総合スコア**：各ページの最新スコアの平均（公開の[採点基準](/ja/pro/scoring)：A ≥ 90 … F）、前回レポートとの差、最近のスキャンにわたる**総合スコアの推移**を表示します。各スキャンがサイトのスコアを実行記録に保存するため、推移は実際のスキャン別履歴です。更新後の最初のスキャンから蓄積され、スコアを持たない古い実行は省略されます。
- **スキャンごとの検出問題数**：最近完了したスキャンの実際の推移です。少ない方が良い値です。
- **修正済みと新規の問題**：前回レポート以降に解消した不具合と新たに発生した不具合の数です。問題には修正済み・再オープンの[ライフサイクル](/ja/pro/scan-issues#issue-lifecycle)が記録されます。この仕組みで全期間をカバーできる場合は実際の問題履歴を使い、それ以外は前回のスナップショットを使います。
- **復旧**：前回以降に解消したリンク切れ、**復旧した**404（元のパス自体が再び200を返すもの。[`seo-pro:404-recheck`](/ja/pro/production#scheduler)を参照）、**リダイレクトした**404、および未解決分を表示します。404の復旧は元ページ側の実際の修正であり、リダイレクトとは別に数えます。
- **Search Console**：上位クエリ・ページと、前回からクリック数が最も大きく変わった**変動項目**を表示します。GSCが未設定なら、この項目は省略します。
- **AIボットの活動**：User-Agentによって帰属させたリクエスト（ボットの本人確認ではありません）、累計、そして日単位の[バケット履歴](/ja/pro/ai-bot-monitor#period-metrics-daily-buckets)が期間をカバーする場合は、**期間内の実際のヒット数とボットごとの巡回先URLの種類数**を表示します。カバーできない場合はスナップショットの累計差分を使います。

## 「前回レポート以降」の意味 {#since-the-last-report}

レポートは任意の日付ではなく、**前回レポートを基準に期間同士を比較**します。生成するたびに軽量なスナップショット（`seo_report_runs`）を保存します。内容はスコア、未解決問題の識別情報、Search Consoleの行、各ボットのヒットカウンターです。次回はそのスナップショットと現在の状態を比較します。

これは独自の履歴を持たない指標のための代替手段です。ページ別スコアは最新値だけを保持するため、レポート時のスナップショットで正確な比較を可能にします。一部の指標には現在、**実際の**履歴があり、レポートはそれを優先してスナップショットを補助に使います。問題の修正済み・再オープンの[ライフサイクル](/ja/pro/scan-issues#issue-lifecycle)は、全期間を記録できれば実際の修正・新規件数を提供します。Search Consoleは[日単位の指標](/ja/pro/search-console#historical-metrics)、AIボットは[日単位のバケット](/ja/pro/ai-bot-monitor#period-metrics-daily-buckets)による実際の期間内ヒット数とURLの種類数を保持します。更新後最初のレポートや、履歴が対象期間をカバーしない場合は、それぞれスナップショット差分にフォールバックします。

このため、次の2点に注意してください。

- **最初のレポートは基準です。** 現在の状態を示し、「修正済み」「新規」、変動項目、「前回以降」の数値は*2回目*から表示されます。
- **生成間隔は自分で決めます。** 月次なら1か月、週次なら1週間の差分になります。基準を更新したくない臨時プレビューには`--no-store`を使います。

## レポートの生成 {#generate-a-report}

```bash
php artisan seo-pro:report
```

オプションなしではPDFを`storage/app/seo-reports/`に書き込みます。保存先の指定やメール送信も可能です。

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### オプション {#options}

| オプション | 効果 |
| --- | --- |
| `--client=` | 「作成先」の顧客ラベルを上書き |
| `--agency=` | レポートの代理店名を上書き |
| `--accent=` | アクセントカラーを上書き（16進数、例：`#3D5AFE`） |
| `--logo=` | ロゴ画像のパスを上書き |
| `--email=` | 宛先アドレス（繰り返し指定可能）。レポートをメール送信 |
| `--send` | 設定済みの宛先にメール送信 |
| `--output=` | PDFを指定したファイルまたはディレクトリに保存 |
| `--no-store` | スナップショットを保存しない（期間差分の基準を進めない） |
| `--json` | 機械可読の概要を出力 |

## メールの定期送信 {#schedule-the-e-mail}

パッケージが自分でスケジュールを登録することはありません。頻度は利用者が決めます。アプリのコンソールスケジュール（`routes/console.php`または`app/Console/Kernel.php`）に登録します。

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

デフォルトの宛先を設定ファイルまたは`.env`に一度設定します。

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send`はこの宛先を使い、明示的な`--email`オプションがあればそちらを優先します。

## ブランド設定 {#branding}

ブランド情報は秘密情報ではないため、設定ファイルに保存します。一度設定すればすべてのレポートに反映されます。各フィールドは上記のコマンドオプションでレポートごとに上書きできます。1つのインストールから複数の顧客向けにレポートを作る際に便利です。

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

補足：

- **ロゴ**：`PNG`/`JPG`/`GIF`/`WEBP`/`SVG`ファイルへの絶対パスです。アプリがファイルを読み込んだ後、data URIとしてPDFに埋め込むため、レンダラーがネットワーク経由で画像を取得する必要はありません。`PNG`または`JPG`が最も安全です。
- **アクセントカラー**：16進数リテラルとして検証し、不正な値はデフォルトに戻します。色としてのみ使い、生のCSSとして扱うことはありません。
- **代理店名**：デフォルトはアプリ名（`config('app.name')`）です。

設定全体は`config/seo-pro.php`の`reports`配下にあります。`paper`（デフォルト`a4`）、`include_gsc`、推移に含める実行数・GSC行数・ボット数もここで指定します。

## 1インストールにつき1サイト {#one-site-per-install}

Proがスキャンするのはインストール先の1アプリケーションなので、レポートも**そのインストール**を対象とします。複数の顧客サイトを運用する代理店は、インストールごとにレポートを生成します。`--client`やブランド設定の上書きでそれぞれを表示できます。マルチテナントの「sites」モデルはありません。

## 生成の仕組み {#how-it-s-built}

PDFのデフォルトレンダラーは**dompdf**です。PHPだけで動作し、NodeやヘッドレスChromiumは不要なので、キューワーカーやcron内でシステムバイナリなしに定期レポートを生成でき、Proのヘッドレス性を維持します。レンダラーのリモート取得は無効で、唯一の画像であるロゴは埋め込みます。描画対象フィールドの内容が外部取得を引き起こすことはありません。

### 各文字体系のレポート（Browsershotレンダラー） {#reports-in-every-script-browsershot-renderer}

コア3.20 / Pro 2.40以降、ChromeレンダラーはJavaScriptを無効にし、HTTP(S)、FTP、WebSocketのアセットリクエストを遮断します。公開したテンプレートは静的なHTML/CSSと埋め込みアセットを使う必要があります。これはページアセットに対する制御であり、Chromeには引き続き正しく設定されたホストとサンドボックスが必要です。Fontconfigが文字体系に対応するフォントの不足を報告すると、PDFレンダラーはインストール方法を含む警告をログに記録します。混在テキスト中に少数含まれる文字体系も対象です。フォントがなくてもChromeはPDFを生成するため、送信前に出力を確認してください。

dompdfは埋め込んだフォント（DejaVu Sans：ラテン文字、キリル文字、ギリシャ文字）だけで描画するため、日本語、タイ語、アラビア語の顧客向けレポートでは文字が四角になります。Pro 2.34以降は、代わりに`spatie/browsershot`経由の**ヘッドレスChrome**で描画できます。コアのOG画像と同じ依存関係なので、マシン側の設定は一度で済みます。

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chromeはサーバーにインストールされたフォントを使います。テンプレートにはコアの文字体系別スタック（`Noto Sans`、ページの言語に対応する`Noto Sans CJK`ファミリーを優先し、タイ語、アラビア語、ヘブライ語、デーヴァナーガリー文字、カラー絵文字、ラテン文字の基準となるDejaVu Sans）を使います。[OG画像](/ja/guide/multilingual#og-images-in-every-script)と同じように必要なファミリーをインストールしてください。Debian/Ubuntuでは`apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`です。`seo:og-images`はページの文字体系に対応するファミリーがなければ実行中に警告し、レポートにも同じ対処が適用されます。どちらのエンジンでもBladeテンプレート、データ、スナップショットは同一で、変わるのはラスタライザーだけです。`ReportGenerator::renderer()`で、どちらがバインドされているか確認できます。

### 読み手のロケールに合わせた日付と数値 {#dates-and-numbers-in-the-reader-s-locale}

レポート生成時に`seo-pro.reports.locale`を取り込みます。nullならアプリケーションのロケールを使います。解決した翻訳言語がPDFとメールのラベル、デフォルト件名、フォント選択、HTMLの`lang`を決めます。専用の翻訳ファイルがない地域ロケールは、同梱の基本言語、次に英語へフォールバックします。中国語の簡体字（`zh_CN`）と繁体字（`zh_TW`）は区別を維持します。

`ext-intl`がある場合、日付と数値はICUを通じて要求したロケールに従います。別の地域形式を意図的に使う場合は`seo-pro.reports.format_locale`を設定します。`locale=it`と`format_locale=en_US`を指定すると、ラベルはイタリア語、日付と数値は米国形式になります。`ext-intl`がない場合は、英語の日付とカンマ区切りの数値へのフォールバックを維持します。

キュー内のメールは、ワーカーの設定が変わっても取り込み済みの言語、書式、件名を保持します。PDFを生成する前に言語を選んでください。後からMailableのロケールを変えても、添付ファイルは翻訳されません。Pro 2.39より前の古いキューペイロードには取り込んだ設定がないため、ワーカーの設定を使います。独自の件名、ブランド情報、保存済み問題メッセージは元のデータのままです。

CLI表示は別です。`php artisan seo-pro:report --display-locale=it`はコマンドの概要を翻訳し、顧客向けPDF・メールの言語はレポート設定で選びます。CLIはデフォルトで英語ですが、`SEO_PRO_CLI_LOCALE`で設定できます。JSONのキーとコードは変わらず、人が読むラベルは翻訳される場合があります。`seo-pro-lang`を公開すると、`lang/vendor/seo-pro/{locale}/seo-pro.php`内のレポート・ワークフローメッセージを上書きできます。

プログラムから使う場合は、コンテナから`ReportGenerator`を解決します。

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```


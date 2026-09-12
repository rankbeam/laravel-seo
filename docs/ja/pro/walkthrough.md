---
description: "実際のRankbeam Proスキャンから、ディスクリプションの欠落を確認し、Filamentで修正を保存して再スキャンするまでをたどります。生成したサンプルPDFレポートもダウンロードできます。"
---

# スキャンから修正の検証まで {#from-a-scan-to-a-verified-fix}

スキャンでデモ記事のディスクリプション欠落が見つかりました。Filamentでディスクリプションを追加して再スキャンし、修正を示すレポートを生成しました。

ここに掲載するのは、2026年9月9日にローカルで動かしたMerchantデモの画面です。コンテンツは初期投入したサンプルデータで、2回のスキャンとレポートはこの手順紹介のために生成しました。過去の推移を事前に作り込んではいません。アプリはLaravel 12とFilament 4を使い、RankbeamのCore、無料エディター、Proエンジンを組み込んでいます。

**[生成したレポートをダウンロード（PDF、98 KB）](/pro-walkthrough/merchant-demo-report.pdf)**

## 登録済みのページをスキャンする {#scan-the-registered-pages}

[Proをインストール](/ja/pro/installation)し、スキャン対象を登録した後で実行します。

```bash
php artisan seo-pro:scan --sync
```

デモには18件のコンテンツレコードと3つのルートを登録しています。最初のスキャンは21件の対象をすべて失敗なく完了し、20件の問題（warning 6件、notice 14件）を検出しました。

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="最初に完了したスキャン。対象21件、問題20件、warning 6件、notice 14件。" width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*スクリーンショットは2倍の解像度で撮影しています。画像を開くと、原寸で確認できます。*

## 1件の問題を確認する {#inspect-one-issue}

**SEO Dashboard**で、該当行の横にある**Page issues**を開きます。「Behind the Scenes: Our Product Photography」では、欠落した`description`、ページURL、検出したスキャンを確認できます。

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Page issuesダイアログで、Post 5、そのURL、欠落したdescriptionフィールドを確認できます。" width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## ディスクリプションを保存する {#save-the-description}

**Posts**で記事を開き、**SEO description**を入力して保存します。[無料のFilamentエディター](/ja/guide/filament)は入力文を検索プレビューに表示し、取得元を**Manual**と示します。この例のディスクリプションは142文字です。タイトルは引き続き記事から取得しています。

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="保存済みのSEOディスクリプションと、142文字を示すカウンター。" width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="リアルタイムプレビューは入力したディスクリプションを使い、Manualと表示しています。" width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

フィールドの保存と、修正の検証は別の手順です。スキャンのスコアは次のスキャン後に更新されます。Filamentを使わない場合は、モデルの`saveSEO()`メソッドで同じ値を保存してください。

## 再スキャンして変更を確認する {#rescan-and-check-what-changed}

同じコマンドをもう一度実行します。

```bash
php artisan seo-pro:scan --sync
```

ダッシュボードで、この問題が**Fixed**と表示されるようになりました。他の19件は未解決のままです。

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="記録されたスキャン比較。新規0件、再発0件、修正済み1件、未解決19件。" width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| 確認項目 | 修正前 | 修正後 |
|---|---|---|
| 完了した対象 | 21 | 21 |
| 未解決の問題 | 20 | 19 |
| warning | 6 | 5 |
| notice | 14 | 14 |
| テクニカルSEOスコアの平均 | 92 | 93 |

[スコア](/ja/pro/scoring)はRankbeamの技術的な検査を反映します。流入数、検索順位、AIの回答への採用を測定するものではありません。また、ディスクリプションの検査に合格しても、検索エンジンがその文を表示するとは限りません。

## レポートを生成する {#generate-the-report}

このデモでは、記事を編集する**前に**比較基準となるレポートを生成し、再スキャン後に2つ目のレポートを生成しました。

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

2つ目のPDFは、**修正済み1件**、**新規0件**、**未解決19件**を示しています。推移に含まれるのは、上記2回のスキャンだけです。Search ConsoleとAIボットログは無効にしていたため、それらのセクションにはデータが利用できないと表示されます。

[![生成したサンプルレポートの1ページ目。スコア93、修正済み1件、未解決19件を表示。](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

最初のレポートが比較基準を設定します。ページを修正した後に1つだけレポートを生成しても、以前のレポートとの差分は表示できません。比較基準を更新せずにプレビューしたい場合は、`--no-store`を使ってください。

このサンプルはBrowsershotレンダラーを使っています。レンダラーの要件、ブランド設定、定期配信については、[ホワイトラベルレポート](/ja/pro/reports)を参照してください。

## 自分のアプリで試す {#run-it-on-your-own-app}

[Proのインストール](/ja/pro/installation)から始め、出力を確認できるページをスキャンしてください。Proは[Filamentなし](/ja/pro/headless)でも動きます。まず無料のメタデータレンダラーを試す場合は、[Dockerデモ](/ja/guide/demo)を使ってください。

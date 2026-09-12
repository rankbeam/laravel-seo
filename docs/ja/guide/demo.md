---
description: "初期データ入りのRankbeamデモアプリを1つのコマンドで起動します。pathリポジトリを使わずリリース済みパッケージを導入し、実際のページでメタデータ、JSON-LDスキーマグラフ、サイトマップを確認できます。"
---

# デモを起動する {#run-the-demo}

自分のアプリに組み込む前に、実際のページでRankbeamの動作を見る最短の方法は、実行可能なデモを使うことです。初期データ入りのLaravelアプリで、**リリース済み**パッケージをインストールします。pathリポジトリや隣接ディレクトリのチェックアウトは不要です。いくつかのページに完全なSEOメタデータ、JSON-LDスキーマグラフ、サイトマップを出力します。ライセンスを追加すると、Proの[テクニカルSEO監査](/ja/pro/scan-issues)も実行できます。

## 1つのコマンドで起動（無料のCore） {#one-command-free-core}

デモは、[`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples)リポジトリのDockerイメージとして提供しています。

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

`http://localhost:8080`を開いてください。各ページのソースを表示すると、解決済みの`<head>`を確認できます。生成されたサイトマップは`/sitemap.xml`で確認できます。ここで使うのはすべて、Packagistからインストールした無料のMITライセンスのCoreです。

## Proを使う場合（監査） {#with-pro-the-audit}

Proはプロジェクト単位のライセンスで、専用の非公開Composerリポジトリからインストールします。ライセンスを`COMPOSER_AUTH`で渡し（イメージレイヤーには書き込まれないビルドシークレットです）、Proフラグ付きでビルドします。

```bash
export COMPOSER_AUTH='{"http-basic":{"blog.rankbeam.dev":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

起動時にデモは[`seo:doctor`](/ja/pro/headless#setup-health-check)と、初期データのページを対象とした最初の`seo-pro:scan`を実行します。ヘルスレポート、スキャンの概要、[0〜100のスコア](/ja/pro/scoring)がcomposeのログに表示されます。

## Proのワークフローを見る {#see-the-pro-workflow}

[スキャン → 修正 → レポートの手順](/ja/pro/walkthrough)では、稼働中のMerchantデモを紹介しています。実際のスキャン、問題の詳細、Filamentでのディスクリプション保存、再スキャン、ダウンロード可能なPDFを確認できます。コンテンツにはサンプルデータと明記し、修正前後の結果にはそれぞれ新しく実行したスキャンを使っています。

公開された操作可能なホスト型デモは、まだありません。Dockerでエンジンをローカル実行してください。[デモのREADME](https://github.com/rankbeam/rankbeam-examples/tree/main/demo)に、セットアップとリリース済みパッケージ・ローカルパッケージの切り替え方法を記載しています。

::: tip すでにアプリがある場合
デモを省略して[クイックスタート](/ja/guide/quickstart)に進めます。インストールから完全な`<head>`の出力まで、5分で試せます。
:::

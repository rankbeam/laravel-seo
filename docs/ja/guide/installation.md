---
description: "Composerでrankbeam/laravel-seoをインストールし、設定ファイルを公開してマイグレーションを実行します。Laravel 11、12、13の動作要件とセットアップ手順を説明します。"
---

# インストール {#installation}

## 動作要件 {#requirements}

- Laravel 11：PHP 8.2–8.4、Laravel 12：PHP 8.2–8.5、Laravel 13：PHP 8.3–8.5
- Laravel 11、12、13のいずれか
- `spatie/laravel-sitemap` ^7.0または^8.0 — 任意。サイトマップを生成する場合にのみ必要です

## パッケージをインストールする {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

インストール作業はこれで完了です。サービスプロバイダーと`SEO`ファサードは自動検出されます。2つのマイグレーションで、このパッケージが使用する次のテーブルだけが作成されます。

| テーブル | 用途 |
|---|---|
| `seo_meta` | モデルごとに明示的に設定する値（morph + ロケール） |
| `seo_defaults` | グローバル、モデルタイプ、ルートのデフォルト値 |

## 任意：サイトマップ {#optional-sitemaps}

サイトマップの生成機能は、[spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap)を利用しています。

```bash
composer require spatie/laravel-sitemap
```

データソースの登録と生成については、[サイトマップ登録のガイド](/ja/guide/sitemaps)を参照してください。

## v1からアップグレードする場合 {#upgrading-from-v1}

アプリケーションで`fibonoir/laravel-seo` v1を使用していた場合は、まず[v1からのアップグレード](/ja/guide/upgrade-from-v1)を読んでください。ベンダー名、名前空間、提供されるAPIはいずれも変更されています。また、v1で公開したファイルがv2の設定と競合する可能性があります。

## 関連パッケージ {#companion-packages}

| パッケージ | 追加される機能 | ライセンス |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Filament 4/5のリソースフォームにSEOセクションを追加 | MIT |
| [`rankbeam/laravel-seo-pro`](/ja/pro/installation) | あらゆるLaravelアプリで使える、キューで実行するサイトスキャン、リダイレクト管理、404監視。Filamentダッシュボードは任意で追加可能 | 商用 |

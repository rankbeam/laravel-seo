---
description: "インデックス登録の可否をLaravelの環境に連動させ、ステージングやローカルのコピーが検索結果に混入するのを防ぎます。許可リスト外の環境ではnoindexを強制し、クローラーを拒否します。"
---

# インデックス登録保護（本番以外の環境の安全策） {#indexing-guard-non-production-safety-net}

サイトのステージングやローカルのコピーがGoogleに登録されるのは、よくあるSEO上のミスであり、被害も大きくなりがちです。重複コンテンツが本来のページと競合し、非公開の環境がインデックスに残り、URL削除ツールでの後処理に何週間もかかることがあります。典型的な原因は、設定し忘れた`.env`だけに依存する`noindex`や、デプロイ時に上書きされたrobotsルールです。

**インデックス登録保護**は、このミスが起きにくい仕組みを設けます。誰かが覚えておく必要のあるフラグではなく、Laravelの*環境*にインデックス登録の可否を連動させます。アプリが許可リスト外の環境で動くと、すべてのページに`noindex,nofollow`を強制し、管理対象の`robots.txt`ですべてのクローラーを拒否し、`seo:audit`でもその状態を明示します。

無料のCore機能です。

## 保護が有効なときの動作 {#what-it-does-when-active}

保護機能が有効で、`app()->environment()`が`seo.indexing_guard.allowed_environments`に**含まれない**場合、次の4つが自動的に実行されます。

1. **リゾルバーがすべてのページに`noindex,nofollow`を強制します。** [優先順位](/ja/concepts/resolver-precedence)の全体よりも*上位*で適用されるため、`seo_meta`にページ単位で明示的に保存した`robots`の値も上書きします。
2. **`X-Robots-Tag: noindex,nofollow` HTTPヘッダー**を、アプリを経由するすべてのレスポンスで送信します。下の[HTML以外のレスポンス](#non-html-responses-pdfs-feeds-images)を参照してください。
3. **`SEO::robotsTxt()->build()`が、すべてを拒否する`robots.txt`**と`ai.txt`を出力します。内容は単純な`User-agent: *` / `Disallow: /`です。`seo:robots-txt`コマンドと、任意で有効にする[動的ルート](/ja/guide/ai-crawlers)の両方が対象です。
4. **`seo:audit`が目立つバナーを表示します。** レポートを読んだときに「すべてがnoindexになっている」状態を見落とさずに済みます。

許可された環境（デフォルトでは`production`）では、保護機能は完全に**何もしません**。出力は変化せず、レンダリング結果もバイト単位で一致します。

## HTML以外のレスポンス（PDF、フィード、画像） {#non-html-responses-pdfs-feeds-images}

強制された`robots`の**metaタグ**が届くのは、HTMLを解析するクローラーだけです。PDF、RSS/Atomフィード、画像など、HTML以外のレスポンスには`<head>`がありません。そのため、保護が有効な間はグローバルミドルウェアを通じて、同じ指示をHTTPヘッダーでも送信します。

```http
X-Robots-Tag: noindex,nofollow
```

ヘッダーとmetaタグは同じ情報源から生成されるので、矛盾しません。**保護機能の中ではデフォルトで有効**です（保護機能自体は明示的に有効化する方式で、許可された環境では何もしません）。metaタグだけの動作にするには、ヘッダー送信を無効にします。

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

ミドルウェアが登録されるのは**保護機能を有効にした場合だけ**です。無効の場合、パッケージはミドルウェアスタックに何も追加しません。

::: warning 静的ファイルはPHPを経由しません
Webサーバーが`public/`から直接返すファイルはLaravelを通らないため、このヘッダーを付与できません。Webサーバーの設定やCDNなどのエッジ側で保護してください。この機能は、アプリを経由するすべてのレスポンスを対象とします。
:::

## 明示的なrobotsの値より優先される理由 {#why-it-overrides-an-explicit-robots-value}

Rankbeamの他の場所では、明示的に保存した値が優先されます。それが優先順位の仕組みの目的です。この保護機能は意図的に設けた唯一の例外で、明示的な値の層よりも*上位*に位置します。ここでのリスクは一方向だからです。

- ステージングのデータベースは通常、本番のコピーです。そのため、`index,follow`を保存したページは、その指示をステージングに持ち込み、インデックス登録を求めてしまいます。
- **ステージングを誤ってインデックス登録すると大きな問題になりますが、誤って`noindex`にしても、本来の目的には影響しません。** そのため、もともとインデックス登録を望まない環境では、保存した値でも覆せない最低限の安全策として保護機能を適用します。

## 有効にする {#enabling-it}

保護機能は**無効**の状態で提供されます。パッケージをインストールまたはアップグレードしても、利用者が明示的に有効にしない限り、本番以外の環境の出力は変わりません。リゾルバーの[`blank_is_unset`](/ja/concepts/resolver-precedence)や生成OG画像と同じく、有効化するまでは出力がバイト単位で変わらない方針です。次の1行で有効にできます。

```dotenv
SEO_INDEXING_GUARD=true
```

デフォルトの許可リストでは`production`に影響しないため、共有設定で保護機能を有効にしたままにできます。インデックス登録したい環境がすべて許可リストに含まれていることを確認してください。利用を**強く推奨**しており、Core 4ではデフォルトで有効にする候補となっています。

無効にする場合も1行です。

```dotenv
SEO_INDEXING_GUARD=false
```

## インデックス登録を許可する環境を選ぶ {#choosing-which-environments-may-index}

デフォルトでは`production`だけを許可します。カンマ区切りの環境変数でリストを変更できます。

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

または、`config/seo.php`で設定します。

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

各項目は`Str::is()`で照合されるため、**ワイルドカード**が使えます。たとえば`'prod*'`は`production`と`prod-eu`に一致します。

```php
'allowed_environments' => ['prod*'],
```

**空の**リストは、インデックス登録を許可する環境が*ない*ことを意味します。保護機能はすべての環境で有効になり、安全側に倒れます。ただし、環境変数`SEO_INDEXING_GUARD_ALLOWED`の値が空または空白の場合は`['production']`にフォールバックします。入力ミスで本番が知らないうちにインデックス登録対象から外れないようにするためです。本当に「すべての環境」を保護したい場合は、設定ファイルに`[]`を明示してください。

## 動作を確認する {#verifying-it}

`seo:audit`はバナーを表示し、`--json`では機械可読の状態も返します。

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

保護対象の環境で配信または生成される`robots.txt`は、次のようになります。

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## 対象範囲 {#scope}

この機能が制御するのは、`robots` metaタグ、`X-Robots-Tag`ヘッダー、`robots.txt`という**インデックス登録に関する指示**です。タイトル、ディスクリプション、正規URL、スキーマには触れません。また、[robotsの出力方針](/ja/concepts/resolver-precedence)（`seo.robots.emit_default`）とは独立しています。`noindex,nofollow`はサイトのデフォルトと異なるため、常にタグとして出力されます。

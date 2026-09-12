---
description: "LivewireアプリでRankbeamの@seoディレクティブを使います。head内に通常のHTMLを出力するため、フルページコンポーネントやBladeレイアウトでも同じように利用できます。"
---

# Livewire {#livewire}

`@seo`などのBladeディレクティブは、特定のフロントエンドフレームワークに依存しません。`<head>`内に通常のHTMLを出力するため、どのLivewireアプリでもBladeと同じように動作します。

## 初回のフルページ描画 {#initial-full-page-render}

**Livewireのフルページコンポーネント**（ルートがコンポーネントを返す形式）や、Livewireコンポーネントを囲むBladeレイアウトでは、`@seo`は[Bladeのガイド](/ja/guide/blade)とまったく同じように動作します。

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

最初のHTTPレスポンスには、クローラーが読み取れる完全なheadが含まれます。title、description、canonical、Open Graph、Twitter、JSON-LDがすべて出力されます。クローラーやSNSのスクレイパーが取得するこのレスポンスでは、必要な内容が正しく揃います。

## `wire:navigate`の注意点 {#the-wire-navigate-caveat}

Livewireの[`wire:navigate`](https://livewire.laravel.com/docs/navigate)は、リンクのクリックをSPA形式のページ遷移に変えます。この遷移では、Livewireが`<body>`を置き換え、**`<head>`をマージ**します。ただし、SEOパッケージにとって重要な扱いの違いがあります。

- **`<title>`と`<meta>`、`<link>`**は新しいページのheadからマージされるため、解決済みのtitleやmetaは通常どおり更新されます。
- **`<script>`は削除できないアセットとして扱われます。** Livewireは、一度読み込んだ`<script>`をすべて保持し、再実行によってJavaScriptが壊れることを防ぎます。そのため、**JSON-LDの`<script>`ブロックが蓄積します**。3つの記事を閲覧すると、3記事分のスキーマが同時にhead内へ残り、構造化データを読み取るツールには、誤ったエンティティや複数のエンティティが見えてしまいます。

不要なものを削除できるように、レンダラーは**出力するすべてのJSON-LDスクリプトにマーカーを付けます**。

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## JSON-LDの削除処理を追加する {#ship-the-json-ld-cleanup}

次のコードを1回だけ追加してください。例えば、ルートレイアウトの`@livewireScripts`の後に置きます。`wire:navigate`による遷移のたびに、**現在のページの**スキーマだけを残し、古いスキーマを削除します。

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        // The page we are now on. data-seo-url is the canonical (query-stripped),
        // so compare on the query-stripped location.
        const here = window.location.href.split('#')[0].split('?')[0]

        // Keep only the LAST schema for this page; remove every other-URL
        // (stale) script AND same-URL duplicates Livewire re-adds when a page is
        // revisited — including clearing a lone stale script when this page has
        // none. Iterate from the end so the freshest copy is the one kept.
        const scripts = [...document.querySelectorAll('script[data-seo-schema]')]
        let kept = false
        for (let i = scripts.length - 1; i >= 0; i--) {
            const url = (scripts[i].getAttribute('data-seo-url') || '').split('?')[0]
            if (url === here && !kept) { kept = true; continue }
            scripts[i].remove()
        }
    })
</script>
```

レンダラーがすでに出力する`data-seo-schema`マーカーとURLごとの識別子だけを利用するため、ページごとの設定は不要です。

::: warning 最後に追加されたスクリプトではなく、現在のURLと比較する
以前のサンプルは、スキーマのscriptが2つ未満の場合に処理を終了し、*最後に追加された*scriptを現在のページのものと見なしていました。この方法では、JSON-LDが**ある**ページから**ない**ページへ移動したときに、古いスキーマがheadに残ります。古いscriptが1つだけ残った状態で処理を終了してしまうためです。また、再訪問時にLivewireが追加する**同じURLの重複**も削除できません。それぞれの`data-seo-url`を`window.location`と比較し、一致するもののうち**最後の1つだけ**を保持すれば、いずれの場合も古いスキーマ*と*重複を取り除けます。`rankbeam-examples`のLivewireアプリとブラウザーテストでは、この動作を検証しています。
:::

::: tip SPA形式の遷移と単一のmetaタグ
Livewireのheadマージにより、単一の`<meta>`や`<link>`タグが古いまま残ることは、多くの場合避けられます。ただし、正確な動作はLivewireのバージョンとレイアウトの構造に依存します。クローラー向けの正しいmetaが特に重要なページでは、**ページ全体の再読み込み**（`wire:navigate`のない通常のリンク）を優先するか、**サーバー側で描画**し、最初のHTTPレスポンスに正しい内容を含めてください。[`rankbeam-examples`](https://github.com/rankbeam)のLivewireアプリでは、ブラウザー上で実際に`wire:navigate`による遷移を行い、この動作を確認しています。
:::

## Filament {#filament}

Filamentは内部でLivewireを使っていますが、**管理画面でコンテンツを編集するためのもの**です。`seo_meta`を編集するだけで、公開フロントエンドのheadを出力することはありません。[Filamentのガイド](/ja/guide/filament)を参照してください。このページで説明した処理は、管理パネルには適用されません。

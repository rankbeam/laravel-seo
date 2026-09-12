---
description: "Rankbeamは6つの層を優先順位に従ってマージし、SEOの各値を決定します。上位の値が優先され、nullは下位の値を上書きしないため、各ページに適切な値を出力できます。"
---

# リゾルバーの優先順位 {#resolver-precedence}

title、description、canonical、robots、画像など、実際に使われるSEOの値はすべて、`SEOResolver`が**6つの層**をマージして決定します。上位の層が優先され、`null`が下位の層の値を上書きすることはないため、各ページに適切な値を出力できます。

## 6つの層 {#the-six-layers}

最下位（常に存在）から最上位（常に優先）までの順序は、次のとおりです。

| # | 層 | 取得元 | 主な用途 |
|---|---|---|---|
| 1 | **サイト設定** | `config/seo.php`（`site_name`、`title_suffix`、`default_og_image`、`default_robots`など） | ブランド全体のデフォルト値 |
| 2 | **DBのグローバルデフォルト** | モデルタイプを指定しない`seo_defaults`のレコード | デプロイせずに編集できる、サイト全体のデフォルト値 |
| 3 | **モデルタイプのデフォルト** | モデルクラスを対象とする`seo_defaults`のレコード | 「すべての商品にこのOG画像を使う」など |
| 4 | **ルートのデフォルト** | ルート名を対象とする`seo_defaults`のレコード | モデルのない静的ページ（`home`、`contact`） |
| 5 | **計算された値** | モデル自身の属性から取得 | `title`からタイトル、`excerpt`や`body`からディスクリプションを取得するなどのフォールバック |
| 6 | **明示的に設定した値** | モデルの`seo_meta`レコード（`saveSEO()`） | 編集者が手動で設定する値 |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

結果は不変の`SEOData`値オブジェクトとなり、すべてのレンダラー（Blade、配列、Inertia）が利用します。

## 計算によるフォールバック（第5層） {#computed-fallbacks-layer-5}

明示的な値がない場合、リゾルバーはモデルから値を取得します。

- **タイトル** — モデルの`title`または`name`属性。
- **ディスクリプション** — `seo.computed.description_fields`に指定された属性を順に調べ、有効なテキストを含む最初のものを使います。デフォルトの順序は、`excerpt`、`summary`、`description`、`intro`、`lead`、`teaser`、`content`、`body`、`text`、`article`です。HTMLを除去し、エンティティをデコードして、単語の境界でテキストを切り詰めます。長さは`seo.computed.description_max_length`で指定し、デフォルトは160です。省略記号は追加しません。
- **Robots** — モデルの`getSEORobots()`フック、または`is_indexable`属性から取得します。[robotsとインデックス登録可否の制御](#controlling-robots-and-indexability)を参照してください。
- **URLから取得する値** — `getUrlForSEO()`からcanonicalと`og:url`を取得します。

## robotsとインデックス登録可否の制御 {#controlling-robots-and-indexability}

モデルごとの`noindex`は標準で利用でき、追加パッケージや特別なカラムの準備は不要です。robots用のメソッドは任意なので、`HasSEO`トレイト自体には*宣言されていません*。見落としやすい点ですが、リゾルバーはすでに次の3つの取得元に対応しています。優先順位の高い順に示します。

| 優先順位 | 取得元 | 例 |
|---|---|---|
| 1 | **明示的に設定した`seo_meta.robots`** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | モデルの**`getSEORobots(): ?string`フック** | `'noindex, nofollow'`を返すか、次の取得元へ進む場合は`null`を返す |
| 3 | **`is_indexable`属性**（カラムまたはアクセサー） | falsyの場合は`noindex, nofollow`、truthyの場合は`index, follow` |

```php
class Page extends Model
{
    use HasSEO;

    // Option A: let the resolver derive robots from a boolean flag.
    //   Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));

    // Option B: compute it from your own state.
    public function getSEORobots(): ?string
    {
        return $this->status === 'draft' ? 'noindex, nofollow' : null;
    }
}

// Option C: set it explicitly per page (wins over A and B).
$page->saveSEO(['robots' => 'noindex, follow']);
```

### 実際に出力される内容 {#what-actually-renders}

解決されたディレクティブは、`<head>`に出力される前に**出力ポリシー**で判定されます。`<meta name="robots">`タグが出力されるのは、**ディレクティブが`default_robots`と異なる場合だけ**です。デフォルトは`index,follow`なので、次のようになります。

- **インデックス登録可能な**ページ（`index, follow`に解決されるページ）では、**robotsタグを出力しません**。タグがない状態を、クローラーはindex,followとして読み取ります。
- **インデックス登録不可の**ページでは、`<meta name="robots" content="noindex, nofollow">`を出力します。
- デフォルトと異なるディレクティブ（`noindex`、`max-snippet:-1`、`unavailable_after`など）は、入力した空白も保持して**そのまま**出力します。

常にタグを出力するには、`seo.robots.emit_default = true`を設定してください。詳細は[robotsの出力ポリシー](/ja/reference/configuration#robots-rendering-policy)を参照してください。

## 値の解決後に適用されるポリシー {#policies-applied-after-resolution}

これらの処理は、どの層から値を取得したかにかかわらず実行されます。

- **タイトルの接尾辞** — 解決済みのタイトルがすでに`title_suffix`で終わっている場合を除き、接尾辞を追加します。ルートのデフォルトテンプレートにブランド名がすでに含まれる場合は、その接尾辞でテンプレートを終えると、「Brand — X | Brand」のような重複を防げます。
- **正規URLからのクエリ除去** — モデルのURLや現在のURLから*導出した*正規URLでは、クエリ文字列を除去します。ただし、[`canonical.query_whitelist`](/ja/reference/configuration#canonical-urls)に指定されたキー（ページ分割されたアーカイブの`page`など）は保持されます。*明示的に設定した*正規URLは、そのまま保持されます。
- **SNS用画像の絶対URL化** — 保存した値が相対パスでも、`og:image`と`twitter:image`は常に絶対URLとして出力されます。Open Graphの仕様で必要とされる形式です。

## どの層の値が採用されたかを確認する {#inspecting-which-layer-won}

[Filamentパッケージ](/ja/guide/filament)では、フィールドごとに取得元を表示します（手動／コンテンツからのフォールバック／モデル種別のデフォルト／グローバルデフォルト／サイト設定／URL から導出）。コードでは`SEOWarningEvaluator`が同じ手動値とフォールバック値の区別を提供するため、独自の管理画面インジケーターを作成できます。

---
description: "RankbeamのSEOデータを出力する際に、すべてのフロントエンド構成のheadが満たすべき共通チェックリストです。Coreのレンダラーテストと参照アプリの基準になります。"
---

# 出力の共通仕様 {#the-rendering-contract}

これは、RankbeamのSEOデータを出力する際に、すべてのフロントエンド構成の`<head>`が満たすべき**唯一の共通チェックリスト**です。次の基準となります。

- Coreのレンダラー出力構造の単体テスト（`tests/Unit/Services/RenderingContractTest.php`）。パッケージCIが実行する、高速でフレームワーク非依存のテストです。
- `rankbeam-examples`の構成別参照アプリ（Blade、Inertia + Vue / React / Svelte、Livewire）。ブラウザーとSSRのテストで、同じ条件を実際のDOMに対して検証します。
- フレームワーク別ガイド（Blade、InertiaとJSON、Livewire）。この仕様に反する実装手順を記載してはいけません。

構成がある条項を満たせない場合は、**不具合または明示された制約**として扱います。仕様を緩める理由にはなりません。データ層（`SEOResolver` → 不変の`SEOData` → `TagRenderer`）はフレームワーク非依存です。構成ごとに異なるのは、*解決済みデータをDOMへ届け、クライアント側の画面遷移後も保ち、クローラーから見える状態にする方法*だけです。この仕様は、その点を定めます。

> この仕様は、独立した設計レビューを経て強化されています。
> 内容に大きな変更があった場合だけ、再レビューしてください。

---

## 1. 値 — 仕様に準拠する`<head>`の内容 {#_1-values-—-what-a-compliant-head-contains}

### タイトル、ディスクリプション、正規URL {#title-description-canonical}

- *解決済み*のタイトルを持つ**`<title>`が、ちょうど1つ**あります。サフィックスは二重に付けません。リゾルバーは`seo.title_suffix`を1回だけ追加し、すでに同じサフィックスで終わるタイトルへの重複追加を防ぎます。
- ディスクリプションが解決された場合だけ、**meta descriptionを1つ**出力します。空のタグは出しません。
- **`<link rel="canonical">`を1つ**出力します。

### Robots {#robots}

- `<meta name="robots">`は、**指示がサイトのデフォルトと異なる場合だけ**出力します。冗長な`index,follow`は不要であり、そのタグが*ない*状態をクローラーは`index,follow`として扱います。比較では空白を区別せず（`index, follow` ≡ `index,follow`）、異なる指示は**そのまま**出力します。`seo.robots.emit_default = true`でタグを強制出力できます。
- 決定的な**詳細指示**に対応します。`noindex`、`nofollow`、`noarchive`、`nosnippet`、`max-snippet`、`max-image-preview`、`max-video-preview`、`notranslate`、`unavailable_after`です。これらは解決済みの文字列値であり、**優先順位はリゾルバーの連鎖**（グローバル → ルート → モデル → 明示的な値）に従います。同じ入力なら同じ出力になります。

### Open Graph {#open-graph}

- `og:title`、`og:description`、`og:type`、`og:url`、`og:site_name`、`og:locale`。
- `article:*`（`published_time`、`modified_time`、`author`、`section`、`tag`）は、**`og:type === 'article'`で、値が実在する場合だけ**出力します。値を作り上げたり、記事でないページに出力したりしません。
- `og:image`には、**値が分かる場合**、`og:image:width` / `og:image:height` / `og:image:alt`と`og:image:type`を付けます。複数の画像は**グループ化**し、各`og:image`の直後に、その画像自身の寸法・代替テキスト・型を置きます。

### Twitter Cards {#twitter-cards}

- `twitter:card`、`twitter:title`、`twitter:description`、`twitter:image`、および画像の代替テキストが分かる場合の`twitter:image:alt`。
- `twitter:site`と`twitter:creator`は**任意かつ独立**です。片方だけが存在してもよく、一方から他方を作り上げることはありません。

### hreflangとロケール {#hreflang-locale}

- hreflangには、モデルの`getSEOAlternates()`フックを通る正式な解決経路があります。
- hreflangの代替ページがある場合は、**絶対URL、正規化済み、言語ごとに一意**で、データが揃っていれば相互参照します。`x-default`は設定した場合だけ出力します。
- `og:locale:alternate`は、実際のソーシャル向け言語版があるロケール**だけ**を反映します。`en-US` → `en_US`に変換し、変換後の形式で比較します。文字列の完全一致は要求しません。
- `<html lang>`は解決済みロケールと一致させます。`<html>`要素を出力するのは*アプリ*ですが、この条項も共通仕様に含みます。

### ページ別のJSON-LD {#per-page-json-ld}

- 解析可能で、`</script>`に対して安全であること。ペイロードを`JSON_HEX_TAG`でエンコードし、値がscript要素を途中で終了させないようにします。保存型XSSへの保護です。
- **複数の`<script>`ブロックでも、結合した`@graph`でも**構いません。
- 安定した`@id`を使うのは、**エンティティが実際に結び付く場所だけ**です（Organization ↔ WebSite ↔ WebPage）。独立したノードに安定した`@id`は必須ではありません。

---

## 2. 正規化と不変条件 {#_2-normalization-invariants}

- `canonical`、`og:url`、`og:image`、`twitter:image`には、**絶対`http(s)` URL**を使います。**空またはnullのタグをDOMに出してはいけません**。
- **`canonical`と`og:url`は、同じ正規化済みURLに解決されなければなりません。** 不一致は警告ではなく、**必ず失敗とする条件**です。
- **正規URLの正規化方針を全体で統一**します。スキーム、ホスト、ポート、パスの大文字・小文字、クエリ許可リスト、末尾スラッシュを常に同じように扱います。インデックス登録可能なページは**自己参照**とし、`noindex`ページは他のページの正規URL方針を引き継ぎ**ません**。
- **出力先ごとにエスケープ**します。HTML属性、テキスト、JSONで、それぞれ適切なエンコーダーを使います。アサーションはバイトではなく、**デコード後の意味上の値**を比較します。
- **レンダラー間の同等性は意味の一致であり、バイト単位の一致ではありません。** *正規化後*に`render()`（HTML）≡ `toArray()` ≡ `toInertiaHead()`となります。3つの表現は、タグの順序や形式が異なっていても正当です。単一値と繰り返し可能なプロパティのルールを明示します。`og:title`は1つ、`article:tag`は複数です。
- **タグの所有者**を区別します。クライアントレンダラーは、キー付きの*パッケージ所有タグ*（第4節参照）を置き換え、無関係なアプリ所有タグを削除しません。

---

## 3. 動作 — クライアント側の画面遷移 {#_3-behaviour-—-client-side-navigation}

Inertiaの訪問、またはLivewireの`wire:navigate`の後は、毎回以下を満たします。

- **単一であるべきタグが、それぞれちょうど1つ**存在し、**古い値が残りません**。対象は`<title>`、description、canonical、各`og:*` / `twitter:*`です。
- **JSON-LDが蓄積しません**。前ページのスキーマは削除し、重ねません。Livewireは`<script>`を削除しないアセットとして扱うため、スキーマのscriptに`data-seo-schema`とURLごとのIDを付け、`livewire:navigated`で前ページ分を削除します。Livewireガイドを参照してください。
- **メタデータが豊富なページから少ないページへ移ると、余分なタグを削除**します。遷移先に、前ページのdescription、OG、スキーマを残しません。
- **ハイドレーション警告は0件**で、ハイドレーションの前後でメタデータの意味が一致します。

---

## 4. Inertiaのhead-key（タグの所有者） {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()`は、すべてのmeta・link項目に安定した**`head-key`**を付けます。Inertiaはこの属性でhead要素を重複排除します。ページの`<Head>`内のタグがレイアウト側と同じ`head-key`を持つ場合、重複を追加せず*置き換えます*。

- 基本キーは、metaでは`name ?? property`、linkでは`rel`です。
- **繰り返し可能なタグには識別子を付け**、キーを一意に保ちます。`article:tag` → `article:tag`、`article:tag:1`など。hreflangでは`alternate:en-US`、`alternate:fr-FR`です。

テンプレートでは**`:head-key`**としてバインドしてください。Vueの`:key`では*ありません*。そちらは`v-for`の差分更新に使う別のキーで、Inertiaのhead重複排除には作用しません。

---

## 5. クローラーからの可視性（明示的なモード） {#_5-crawler-visibility-explicit-modes}

- **SSR / プリレンダリング**は、**生のHTTP HTML**に共通仕様の出力をすべて含めなければなりません。これは、ハイドレーション後のDOMとは別に、JSを無効にして検証します。
- **CSRだけでは、クローラー対応を主張できません。** デフォルトのSSRなしInertiaは、メタデータを*クライアント側*で挿入します。クローラーが取得する最初のHTMLにはSEOメタデータがありません。この制約は隠さず記載します。**クローラーに見えるメタデータにはInertia SSRまたはプリレンダリングが必要**であり、クローラー向けのJSON-LDもサーバーで出力すべきです。

---

## 6. 対象外・目的としないこと {#_6-out-of-scope-non-goals}

- **レンダラーではなくアプリの担当**なのは、`charset`、`viewport`、faviconです。`<meta charset>`は非ASCIIのメタデータより前に置く必要があるため、これらのhead要素の順序はアプリが管理します。
- **E2Eテストが検証するのは出力だけ**です。Googleのインデックス登録、正規URLの*選択*、リッチリザルトの対象になるか、順位は検証**しません**。リモート画像のMIMEや取得可能性も検証**しません**。これらは任意の統合・HTTPテストで扱い、ブラウザーマトリックスには入れません。

---

## 7. 準拠状況 {#_7-conformance-status}

現時点で各条項を何が検証するかを示します。**単体**は`RenderingContractTest`（Core、パッケージCI）、**ブラウザー/SSR**は`rankbeam-examples`（定期実行マトリックス）、**アプリ**はホストアプリが担当することを意味します。**計画中**は、仕様上の目標ではあるものの、まだ`SEOData`にデータがモデル化されておらず、レンダラーは安全な部分集合を出力する状態です。

| 条項 | 状況 |
|---|---|
| 解決済みの`<title>`がちょうど1つ、サフィックスの重複なし | **単体** + ブラウザー |
| 存在する場合だけmeta descriptionを出力 | **単体** + ブラウザー |
| `<link rel="canonical">`が1つ、空ではない | **単体** + ブラウザー |
| デフォルトと異なる場合だけrobotsをそのまま出力。`emit_default`で切り替え | **単体** + ブラウザー |
| 詳細なrobots指示をリゾルバーの優先順位で解決 | **単体**（リゾルバー） |
| `og:title/description/type/url/site_name/locale`。ロケール`en-US`→`en_US` | **単体** + ブラウザー |
| `og:type=article`で実在する場合だけ`article:*`を出力 | **単体** + ブラウザー |
| `og:image`が存在し、絶対URLである | **単体** + ブラウザー |
| `og:image:width/height/alt`、`og:image:type`、複数画像のグループ化 | **計画中** — `SEOData`は単一の`ogImage`文字列を持ち、寸法・代替テキスト・型は未モデル化です。レンダラーは絶対URLの`og:image`を1つ出力します。 |
| `twitter:card/title/description/image`。`site` / `creator`は独立 | **単体** + ブラウザー |
| `twitter:image:alt` | **計画中** — 画像の代替テキスト用フィールドは未モデル化です。 |
| hreflangが絶対URLで、言語ごとに一意 | **単体** + ブラウザー |
| hreflangの相互参照、設定時の`x-default` | ブラウザー（データ依存） |
| `og:locale:alternate`が実際のソーシャル向け言語版を反映 | **計画中** — ロケール別のソーシャル向け言語版マップは未モデル化です。 |
| `<html lang>`の一致 | **アプリ**（ブラウザーでも検証） |
| JSON-LDが解析可能で、`</script>`に対して安全 | **単体** + ブラウザー |
| 複数scriptまたは`@graph`。エンティティが結び付く場所に安定した`@id` | **単体**（Merchantのグラフ）+ ブラウザー |
| 絶対URL、空・nullのタグなし | **単体** + ブラウザー |
| `canonical` ≡ `og:url`。不一致は必ず失敗 | **単体** + ブラウザー |
| 一貫した正規URLの正規化、自己参照、noindexの分離 | ブラウザー |
| 出力先別エスケープ、デコード後の意味の一致 | **単体** |
| レンダラー間の意味の一致（`render()` ≡ `toArray()` ≡ `toInertiaHead()`） | **単体** |
| Inertiaの`head-key`が安定し、繰り返しタグを区別 | **単体** + ブラウザー |
| クライアント遷移：単一タグの一意性、古い値なし、JSON-LD非蓄積、不要タグの削除 | ブラウザー — レンダラーが削除に必要な`data-seo-schema`フックを提供 |
| ハイドレーション警告0件、前後の一致 | ブラウザー |
| SSRの生HTMLが仕様全体を出力。CSRのみは非準拠と明記 | ブラウザー + ドキュメント |

**計画中の条項**は、意図的に記録された未対応部分です。共通仕様は長期的な目標であり、これらは今後の作業で追加する後方互換の拡張です。新しい`SEOData`フィールド・カラムと、SemVerのマイナーバージョン更新が必要です。現在のレンダラーは安全な部分集合を出力し、持っていない値を作り上げることはありません。

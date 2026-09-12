---
description: "フォーカスキーワードに対応した合格・警告・不合格のページ内チェックリストです。タイトル、URL、冒頭段落、メタ情報、長さ、画像、読みやすさを信号色で確認できます。"
---

# ページ内チェックリスト：キーワードに対応した合格・警告・不合格 {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

ページ内チェックリストは、RankMathやYoastの利用者が期待する、編集と確認を繰り返す仕組みです。フォーカスキーワードを選ぶと、そのキーワードにページが最適化されているかを信号色の一覧で示します。タイトル、URL、冒頭段落、メタディスクリプション内のキーワードに加え、長さ、画像、内部リンク、**読みやすさ**を確認します。

モデル、[リゾルバー](/ja/concepts/resolver-precedence)、ページ自身の本文から、**リクエスト内で**実行します。キューもネットワークも使わず、意図的に**数値スコアにはしていません**。

::: tip チェックリストとスコアは別
チェックリストは**合格 / 警告 / 不合格だけ**で、[Pro SEOスコア](/ja/pro/scoring)とは完全に別です。採点基準とコードを共用せず、スコアを変えることもありません。編集上のヒントは数値の採点基準から分離しています。特にキーワード密度と読みやすさは**参考情報**です（後述）。
:::

## 確認する項目 {#what-it-checks}

| チェック | グループ | 確認内容 |
|---|---|---|
| `keyword_in_title` | keyword | SEOタイトルにフォーカスキーワードがある。 |
| `keyword_in_description` | keyword | メタディスクリプションにフォーカスキーワードがある。 |
| `keyword_in_url` | keyword | URLスラッグにフォーカスキーワードがある。 |
| `keyword_in_first_paragraph` | keyword | 冒頭段落にフォーカスキーワードがある。 |
| `keyword_density` | keyword | **参考情報。** キーワードの頻度が自然か確認する（目標値はない。後述）。 |
| `title_length` | meta | タイトルがエディター・スキャンと同じ範囲にある。コアの[文字数ポリシー](/ja/guide/multilingual#title-and-description-budgets-per-script)に基づき、ラテン文字30–60、CJK約15–30（Pro 2.33）。 |
| `description_length` | meta | ディスクリプションが同じ範囲にある。ラテン文字70–160、CJK約35–80。 |
| `content_length` | content | 本文の量が十分（設定による単語数の範囲）。 |
| `readability` | content | **参考情報。** 選択した式（10言語）、LIXフォールバック、または日本語・中国語・韓国語向けの明示的な非採点ヒューリスティックによる推定難易度。 |
| `has_image` | media | コンテンツに少なくとも1枚の画像がある。 |
| `internal_links` | links | 関連する内部ページへリンクしている。 |

フォーカスキーワードが未設定の場合、キーワードのチェックは**スキップ**し、合格にも不合格にもしません。チェックリストは追加を案内します。[フォーカスキーワードフィールド](/ja/guide/filament)または`saveSEO(['focus_keywords' => …])`で設定できます。

### キーワードの照合 {#keyword-matching}

キーワードと本文は**ケースフォールディングと語幹化**の後に比較します。そのため「espresso grinder」は「espresso grinders」にも一致します。解析ロケールを指定すれば、トルコ語の「İstanbul」と「istanbul」、ギリシャ語の「ΟΔΟΣ」と「οδος」、ドイツ語の「Straße」と「STRASSE」も一致します（コアの`CaseFolder`）。解析するロケールは`SeoPro::checklistFor($post, 'it')`または`--locale=it`で渡してください。

Pro 2.36.1以降、キーワード、同義語、フィールドのテキストは語幹化前に同じトークナイザーを使います。一致には連続する**トークン全体**が必要で、`cat`は`education`には一致しません。日本語の語句も本文と同じICUの単語境界を使います。アポストロフィとハイフンはトークンを分けるため、`meta-tag`は`meta tag`に一致し、直線型・曲線型のアポストロフィも同じ扱いです。結合文字は元の文字に付いたままです。ケースフォールディングはアクセントを保持しますが、言語別の語幹化処理がさらに簡約する場合があります。

出現回数は各位置で最長のキーワード・同義語一致を選び、その範囲を1回と数えます。重複した同義語や重なる短い候補で密度が増えることはありません。たとえばキーワード`seo`と同義語`seo tools`の場合、`seo tools seo`での出現は2回です。空白のない文字体系の辞書に基づく単語境界には引き続きICUが必要で、正規表現のフォールバックでは代替できません。

Pro 2.37以降、語幹化には**同梱のSnowball 3.1.1のサブセット**を使います。追加のComposerパッケージは不要で、実行時にダウンロードすることもありません。PHP 8.2も引き続き対応します。

| エンジン | 使用条件 | 言語 |
| --- | --- | --- |
| `snowball` | デフォルト。既存の`auto`設定も同じ同梱エンジンを選択 | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | 明示的な`seo-pro.checklist.analysis.stemmer = builtin` | 英語のみ。従来の軽量な屈折語尾処理を使用し、他言語は語幹化しない |
| `identity` | 非対応言語、または明示的な`none`モード | ウクライナ語、日本語、中国語、韓国語、タイ語、および同梱サブセット外の言語 |

比較する両側で同じエンジンを使います。語幹化は接尾辞を簡約するアルゴリズムであり、同義語辞書でも言語的な同等性の保証でもありません。たとえばギリシャ語のアルゴリズムでは、語幹化しない照合が区別するアクセントの有無を同一視することがあります。トークン全体の境界は維持されるため、`cat`が`education`に一致することはありません。

#### Pro 2.36からの更新 {#upgrading-from-pro-2-36}

既存の`auto`設定は、`wamania/php-stemmer`のインストール有無にかかわらず、同梱アルゴリズムを一貫して使うようになりました。更新後は編集上の提案を再確認してください。アルゴリズムの変更で一致結果が変わる場合があり、トルコ語、ギリシャ語、ポーランド語、チェコ語にも語幹化が加わっています。任意のラッパーが追加提供していたカタルーニャ語、デンマーク語、フィンランド語、ノルウェー語、ルーマニア語、スウェーデン語のアルゴリズムはこのサブセット外で、現在は語幹化しない照合になります。

従来の英語のみのフォールバックには`SEO_PRO_CHECKLIST_STEMMER=builtin`、すべての言語でケースフォールディングのみの照合を使うには`none`を設定します。変更後は設定キャッシュを再構築してください。これらの設定は古い任意ラッパーの多言語アルゴリズムを再現するものではありません。その結果を完全に維持するには、以前のProリリースを維持する必要があります。保存済みSEOメタデータは書き換えません。

同梱アダプターは、PHP 8.2、8.3、8.4で、固定した公式の語彙・出力ペア600,395組に合格しています。これはアルゴリズム準拠の証拠であり、ネイティブによる編集承認ではありません。ソースハッシュ、構文だけを調整したPHP 8.2対応、上流のライセンスを同梱しています。配布ソースの`THIRD-PARTY-NOTICES.md`を参照してください。

### 単語の分割 {#word-segmentation}

単語数、キーワード密度、読みやすさの統計には単語が必要です。空白を使う文字体系では、正規表現で安定した文字・数字のトークン境界を使います。中国語、日本語、タイ語には辞書分割が必要で、正規表現では段落全体を1「語」と捉える場合があります。**ext-intl**が読み込まれていれば、該当する連続文字をICUの辞書ベースの境界イテレーター（`IntlBreakIterator::createWordInstance`）に渡し、「東京タワーは東京のランドマークです」を単語に分けます。ICUがない、無効、初期化できない場合、Proは影響する本文の長さ、読みやすさ、キーワードのチェックをスキップし、インストール・設定の案内を出します。信頼できない数を不合格にはしません。タイトル長や空白区切りの照合など、無関係なチェックは続けます。`seo-pro.checklist.analysis.segmenter = regex`は辞書分割を必要とするテキストを同じ利用不可状態にします。

`analysis`ブロックには`word_count_status`（`available`または`unavailable`）と、`segmentation_reason`（`null`、`missing_intl`、`disabled`、`initialization_failed`）が入ります。低水準のトークナイザーは互換性のためフォールバックトークンを残します。単語と解釈する前に、この状態を確認してください。

描画ページのスキャンは、内容が薄いという判定の代わりに、採点しない`word_segmentation_unavailable`通知を出します。以前に確認済みの薄いコンテンツの問題は、再確認できるまで未解決のままです。この不完全なスキャンではページスコアを更新しません。既存のスコアは元の`scored_at`を保持し、初回スキャンは分割が動くまでスコアを持ちません。PHPの`ext-intl`をインストールし、`auto`分割を有効にして再スキャンすれば、該当チェックを再開できます。

### ページを解析したエンジン {#which-engines-analysed-the-page}

各チェックリストには`analysis`ブロックがあります。本文の主要な文字体系、トークナイザー（`intl` / `regex`）、語幹化エンジン（`snowball` / `builtin` / `identity`）、読みやすさの手法（`formula` / `heuristic` / `lix`）を、`toArray()` / `--json`、Filamentモーダルのフッター、`seo-pro:checklist`の最終行に示します。

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

フッターは実際に使ったエンジンを示します。ext-intlがない場合の正規表現分割や、語幹化を無効にした場合の同一形照合も含みます。

### キーワード密度は参考情報 {#keyword-density-is-advisory}

チェックリストは順位向上のための理想的なキーワード密度を定義しません。このチェックは**参考情報**で、把握のために回数を示すだけです。不合格にせず、**ページ全体の状態を左右しません**。割合を目指すのではなく、繰り返しが自然かを確認してください。

### 読みやすさは参考情報 {#readability-is-advisory}

チェックリストは、解析ロケールに応じて選んだ手法で読みやすさを推定します。現在実装している式とフォールバックは次のとおりです。

| ロケール | 式 | 出典 |
| --- | --- | --- |
| 英語（`en`） | Flesch Reading Ease | Flesch 1948 |
| イタリア語（`it`） | Gulpease Index | Lucisano & Piemontese 1988 |
| スペイン語（`es`） | Fernández-Huerta | Fernández Huerta 1959 |
| フランス語（`fr`） | Kandel-Moles | Kandel & Moles 1958 |
| ドイツ語（`de`） | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| ポルトガル語（`pt`、`pt_BR`） | ブラジルポルトガル語向けに調整したFlesch | Martins et al. 1996 |
| オランダ語（`nl`） | Flesch-Douma | Douma 1960 |
| ロシア語（`ru`） | ObornevaによるFleschの調整 | Оборнева 2006 |
| トルコ語（`tr`） | Ateşman | Ateşman 1997 |
| ポーランド語（`pl`） | Pisarek（教育年数の指標を正規化） | Pisarek 1969 |
| 日本語、中国語、韓国語（`ja`、`zh`、`ko`） | **ヒューリスティック、スコアなし**。後述 | — |
| ギリシャ語、ウクライナ語、チェコ語（`el`、`uk`、`cs`） | LIX。専用式を実装していないためのフォールバックで、これらの言語向けに校正していない | Björnsson 1968 |
| その他 | LIX（Läsbarhetsindex）。未校正のフォールバック | Björnsson 1968 |

表示する**0–100のスコア（高いほど読みやすい）**はパッケージの規約です。Flesch系とGulpeaseは結果をこの範囲に収め、Wienerの学年、Pisarek、LIXはこの尺度へ変換します。異なる言語で同じスコアでも、読解難易度が同じとは**限りません**。係数を含む式は公刊研究に基づきますが、Rankbeamのトークン・文・音節の推定を組み合わせた測定手法全体は検証されていません。理解度や検索順位を予測するものではありません。

Pro 2.37.1以降、トルコ語とロシア語の音節推定では隣接する母音を別々に数えます（`saat`：2、`поэт`：2）。他の音節推定にも制約があり、母音群として数える方法では一部の母音連続や黙字を捉えられません。英語には小規模な例外表がありますが、発音辞書ではありません。たとえばスペイン語の`país`やフランス語の`monde`は誤算する場合があります。なじみのない単語や固有名詞は手作業で確認してください。

#### テキスト統計とAPIの制約 {#text-statistics-and-api-limits}

HTMLのブロックタグと`br`要素はテキストを区切り、インラインの強調は単語につながったままです。通常のHTMLのソース内改行は空白にまとめますが、プレーンテキストと`pre`は行境界を保持します。script、style、noscriptの内容は除外します。抽出はCSSの表示状態や描画ページを評価しません。エンティティは1回だけデコードします。式の統計では、文字・数字の連続を単語とし、句読点だけは数えません。ハイフンとアポストロフィは単語を区切ります。数字はトークンに数えますが、音節は推定しません。文字数は元のテキストから数え、語幹化やドイツ語の`ß` → `ss`のケースフォールディングで長さを変えません。

文の推定は、末尾の`. ! ? 。 ！ ？`とブロック・行境界で分け、終止符のない最後の断片も含みます。小数と、`Dr.`、`Prof.`、`e.g.`など少数の一般的な英語の略語は分割しません。そのため、見出しやリスト項目も文として数える場合があります。他の略語、引用、数値、文字体系の混在、句読点の少ないテキストには特に注意が必要です。ロケールは手法を選ぶもので、すべての文がその言語かどうかを検出するものではありません。

直接計算するAPIの`toArray()`は、`assessment`ブロックを追加します。

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method`は`formula`、`lix`、`heuristic`、`unavailable`を区別します。式のメタデータなしで手動構築した結果は`unspecified`です。空または句読点だけの入力は`insufficient`で、`isValid()`はfalseです。従来の`score: 0`は利用不可を示す特別値であり、難易度スコアではありません。既存の英語・イタリア語の学年ラベルは概算です。他言語やヒューリスティック・LIXの結果には、これらの学年ラベルを付けなくなりました。`calculateFleschKincaid()`は互換性のため公開メソッド名を維持しますが、計算するのはFlesch-Kincaidの学年ではなく**Flesch Reading Ease**です。

式のテストでは、独立に数えた入力と期待する演算結果を、名称のある10式とLIXについて固定しています。検証するのは計算の挙動で、ネイティブの編集品質ではありません。読みやすさはPro SEOスコアと別です。

::: warning 日本語・中国語・韓国語は、明示したヒューリスティックであり数値ではない
これらの言語には採点しない方法を実装しています。計算器はパッケージ固有の目安から**レベル**を返します。文の平均長を文字数（ja ≤ 40/60/80、zh ≤ 30/45/60）または単語数（ko ≤ 12/18/25）で見て、日本語では漢字比率が約45 %を超えると、パッケージ内の難易度区分を1段階上げます。`heuristic: true`を付け、**スコアはnull**です。チェックリストのメッセージは「ヒューリスティック」と明示し、**`readability.advisory`の値にかかわらず、これらの言語では参考情報のまま**です。目安は判断材料を示すだけで、チェックリスト全体の状態を決めません。`ja`/`zh`の単語数には動作するICU分割が必要で、使えない場合は該当チェックを省略します。
:::

キーワード密度と同様、読みやすさも**デフォルトでは参考情報**です。書き手への情報であり、ページ全体の状態は**決めません**。Yoastが読みやすさ解析とSEO解析を分けるのと同じ考え方です。最小単語数に満たない場合は**スキップ**します。本文が少ないかを扱うのは`content_length`で、読みやすさの役割ではありません。読みづらいページを不合格にしたい場合は、判定に使うよう切り替えます。

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## チェックリストの確認方法 {#reading-the-checklist}

### ヘッドレス {#headless}

Pro 2.36は、要求したコンテンツロケールで解決済みメタデータ、`getContentForSEO()`、フォーカスキーワードを読み取り、チェックリストのラベルは操作者の言語のままにします。明示的なロケールがなければ、翻訳モデルの`seoData()`のデフォルトを尊重します。Filamentアクションはフィールドの言語タブまたはページのロケール切り替えに従います。

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

各`CheckResult`には`id`、`group`、`label`、`status`、`message`、任意の`recommendation`、`advisory`フラグがあります。

### コマンド {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### エディター内（Filament、任意） {#in-the-editor-filament-optional}

[`rankbeam/laravel-seo-filament`](/ja/guide/filament)をインストールすると、フォーカスキーワードフィールドに**ページ内チェックリスト**のアクションが表示されます。クリックすると、レコードの保存済みコンテンツに対する同じ合格・警告・不合格のチェックをモーダルで表示します。FilamentパッケージがProに依存することはありません。AI提案と同じ一方向の拡張フックで追加するため、ヘッドレス構成には影響しません。

## 設定 {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### 独自チェックの作成 {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

クラスを`seo-pro.checklist.rules`に追加して登録します。[スキャンの問題コード](/ja/pro/scan-issues)のIDを再利用しては**いけません**。チェックリストは採点しない別の名前空間です。

## コンテンツの読み取り方 {#how-the-content-is-read}

`SeoPro::checklistFor($model)`が解析するものは次のとおりです。

- **タイトル・ディスクリプション**：*解決済み*の有効値です。エディターのカウンターとスキャンが測るものと同じなので、チェックリストと矛盾しません。
- **本文**：`$model->getContentForSEO()`（コアの`HasSEO`アクセサー。デフォルトは`content` / `body` / `text`）。モデルで上書きし、実際の本文を指定してください。
- **URL**：`$model->getUrlForSEO()`。
- **フォーカスキーワード**：保存済みの`seo_meta.focus_keywords`。

純粋な解析であり、ページ取得も書き込みも行いません。

---
description: "無料のlaravel-seo-filamentパッケージで、FilamentリソースのフォームにSEOセクションを2行で追加できます。HasSEOトレイトを使うFilament 4.xと5.xに対応しています。"
---

# Filamentの管理画面フィールド {#filament-admin-fields}

無料の[`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament)パッケージは、FilamentリソースのフォームにSEOセクションを追加します。**リソースごとに2行**で組み込めます。Filament **4.xと5.x**（Livewire 3と4）に対応しています。メタデータの編集は無料です。Proを追加すると、スキャンと下の例にあるスコアを利用できます。

## 前提条件 {#prerequisites}

既存のFilament 4または5のパネルと、Coreの`HasSEO`トレイトを使うモデルが必要です。エディターを追加する前に、マイグレーションと出力設定を含めて[Coreのクイックスタート](/ja/guide/quickstart)を完了してください。

## インストール {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

リソースが扱うモデルは、Coreの`HasSEO`トレイトを使う必要があります。

## リソースにセクションを追加 {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## 保存結果を確認 {#check-the-saved-result}

既存のレコードを開き、SEOディスクリプションを入力して保存し、フォームを再読み込みします。値が保存され、プレビューに表示され、値の取得元が**手動**（英語の画面では**Manual**）になっていることを確認してください。出力されたページの`<head>`も確認し、同じディスクリプションが訪問者に届いていることを確かめます。

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="MerchantデモのSEOフィールド：タイトル、ディスクリプション、正規URL、ソーシャル画像、検索プレビュー、解決済みの値の取得元。" width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Merchantデモの例です。フィールドにはパネルのテーマが適用されます。利用できる操作と文字数の目安は、インストール済みのバージョンと設定によって異なります。*

セクションには次の項目があります。

- **タイトルとディスクリプション**には、入力に合わせて更新される文字数カウンターがあります。目安は、入力中の文字体系に応じたCoreの[文字数ポリシー](/ja/guide/multilingual#title-and-description-budgets-per-script)に従います。ラテン文字は60/160、CJKは約30/80で、書記素単位で数えます。
- **フォーカスキーワード**はタグ入力です。通常のキーワードを入力すると、Coreの構造化された`[{keyword, is_primary}]`形式で保存されます。先頭が主キーワードで、`getPrimaryKeyword()`と`SEOData`はそのまま読み取れます。`seo.keywords.enabled`を有効にすると、[`seo:audit`](/ja/guide/audit)コマンドとProスキャンが、キーワードのないページを指摘します。デフォルトでは無効で、両方を1つの設定で制御します。[設定](/ja/reference/configuration#focus-keywords)を参照してください。
- **正規URL**。空欄なら自動で決まり、クエリ文字列は除去されます。
- **Robots**の選択。空欄ならサイトのデフォルトを使います。
- **ソーシャル共有画像**のアップロード（og:image / twitter:image）。Filamentのデフォルトディスクの`seo/`に保存されます。
- **検索スニペットのプレビュー**。入力中も、リゾルバーのフォールバック連鎖を反映します。
- **取得元の表示**。各フィールドの実効値を決めたリゾルバーの層を示します。*手動*、*コンテンツからのフォールバック*、*モデル種別のデフォルト*、*グローバルデフォルト*、*サイト設定*、*URLから導出*です。

## 表示フィールドを限定 {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

`title`、`description`、`focus_keywords`、`canonical`、`robots`、`og_image`から任意の組み合わせを指定できます。

トレイトを使わない場合は、`SEOFields::make(?array $only)`が同じセクションを直接返します。

## 値の保存方法 {#how-values-persist}

セクションは`seo_meta`の状態グループにバインドされ、Coreの`seoMeta()`リレーションを通して更新または作成します。アプリ自身のテーブルにカラムを追加する必要はありません。保存値は直ちに[リゾルバー](/ja/concepts/resolver-precedence)の第6層（明示的な値）になります。

## 複数の言語 {#several-languages}

Coreは[モデルとロケールの組み合わせごとに`seo_meta`行を1つ](/ja/guide/multilingual)保持します。ページを公開するロケールを渡すと、セクションは**言語ごとに1つのタブ**を表示します（Filament 1.9）。

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

すべてのリソースに一括で適用する場合は、パッケージ設定で指定します。

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

各タブは自分の行を編集し、次の表示も個別に持ちます。

- **カウンター**は、その言語の文字体系に応じた[文字数ポリシー](/ja/guide/multilingual#title-and-description-budgets-per-script)を使います。同じページでも、空の日本語タイトルは`0 / 30`、英語タブは`0 / 60`と表示されます。
- **プレビュー**（SERP / ソーシャルカード）は、そのロケールの解決済みの値から出力します。
- **フォールバックの表示**は、そのロケールの行について説明します。
- **バッジ**は、その言語版で設定済みのフィールド数を示し、未入力の翻訳を見つけやすくします。

`ext-intl`が読み込まれていれば、タブにはパネルの表示言語で言語名（`Italiano` / `Italian`）を表示します。それ以外はコードを表示します。すべてのタブをまとめて検証し、保存します。何も入力していない言語に、空の仮行を作ることはありません。

::: details フォームの状態を独自にバインドする場合
複数ロケールの状態パスは`seo_meta.{locale}.title`です。1ロケールでは`seo_meta.title`のままです。独自のフォームアクションでは、対応するパスを使ってください。
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Merchantデモの英語・イタリア語・日本語タブ。日本語のタイトルとディスクリプションの目安は30文字と80文字で、ディスクリプションは未設定です。" width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*2026年9月9日のMerchantデモ。`locales: ['en', 'it', 'ja']`を指定しています。空の日本語タブは専用のカウンターを使います。ここに表示される英語タイトルは、デモモデルのコンテンツからのフォールバックです。言語タブを追加しても、コンテンツは翻訳されません。フィールド上部のProスコアはレコードの最後のスキャン結果であり、言語タブごとの個別スコアではありません。*

### 翻訳対応プラグインとの併用 {#with-a-translatable-plugin}

`lara-zeus/spatie-translatable`の**1.xをFilament 4で**、または**2.xをFilament 5で**使う場合は、編集・作成ページにRankbeamのページアダプターを使ってください。置き換えるのはページのトレイトのインポートだけです。プラグインのリソース・一覧用トレイト、パネルプラグイン、`LocaleSwitcher`アクションは保持します。

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

各ページのクラス内には、引き続き`use Translatable;`を宣言します。プラグインは任意のアプリ依存関係です。最新の修正版を使ってください。ローカルの統合テスト用構成では、プラグイン1.0.4 / Filament 4.13.1と、プラグイン2.0.1 / Filament 5.8.1を検証しています。

言語を切り替えても、未保存の親コンテンツ、SEOメタデータ、構造化データの下書きをエディター内に保持します。保存時は、編集で開いたすべての言語を検証し、データベーストランザクション内でまとめて保存します。検証エラーがあれば、対応が必要な言語を開きます。アップロードは保存時に格納されます。ページを離れたり再読み込みしたりすると、未保存の下書きは失われます。下書きを保存しても、欠けているコンテンツは翻訳されません。

アダプターは、通常の前後フックとフォームデータのミューテーターを保持します。ページで`handleRecordCreation()`、`handleRecordUpdate()`、`callHook()`またはトランザクション関連メソッドを上書きしている場合は、そのカスタマイズにアダプターの動作を組み込み、保存の流れをテストしてください。データベーストランザクションはファイルシステムへの書き込みを巻き戻しません。アプリでは、従来どおり参照されなくなったファイルを削除する処理を維持してください。

Livewire 3で独自のライブテキストフィールドを使う場合は、明示的なdebounce指定より`->live()`または`->live(onBlur: true)`を推奨します。明示的な指定はローカルのモデル状態の更新を遅らせ、素早く言語を切り替えると末尾の入力が失われることがあります。Rankbeamのタイトルとディスクリプションは、リクエストのデフォルトのdebounceを使います。

上流プラグインのページトレイトだけでは、言語切り替え時にフォームを再入力します。Rankbeamは、その処理による意図しないメタデータの書き込みを防ぎますが、それらのトレイトはSEOの下書きを保持しません。編集・作成ページをアダプターへ移行してください。明示的な`locales:`タブは引き続き共有エディターとなり、ページの言語切り替えより優先されます。

明示的なロケール一覧もページのロケールもない場合、セクションはアプリのロケールを編集します。

## 構造化データ（schema.org） {#structured-data-schema-org}

任意の**構造化データ**セクションでは、編集者がコードを触らずにリッチリザルト用のJSON-LDスキーマを付けられます。SEOセクションの横に追加します。

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

トレイトなしで、`SEOSchemaFields::make()`を直接使うこともできます。

このセクションはCoreの`seo_meta.schema_jsonld`カラムに書き込みます。[スキーマレンダラー](/ja/guide/schema)が出力するものと同じ値です。役割は**UIのバインドだけ**で、各ドキュメントはCoreのスキーマビルダーが生成し、保存前にCoreの`SchemaValidator`が検証します。独自のスキーマロジックは追加しません。

セクションには次の機能があります。

- **自動パンくずリスト**は、設定不要で始められる先頭の切り替え項目です。`BreadcrumbSchema::fromModelAncestors()`を通して、レコードの親の連鎖から`BreadcrumbList`を導出します。入力する項目はなく、モデルの祖先をたどります。
- **スキーマブロック**は繰り返し入力です。各ブロックは**FAQ**（質問と回答の組 → `FAQPage`）または**Product**（名前、説明、画像、ブランド、SKU、価格と通貨、在庫状況 → `Product`）で、Coreの`FAQSchema` / `ProductSchema`ビルダーが生成します。

### 検証 {#validation}

不正なJSON-LDになるブロックは、Coreのバリデーターのメッセージとともに**保存時に拒否**されます。たとえば、回答のないFAQ項目、画像やオファーのないProductです。このビルダーではそれらのフィールドが必須ですが、GoogleのすべてのProduct検索機能の要件を網羅した説明ではありません。空のままのブロックは無視します。

### 保存される内容 {#what-it-stores}

`schema_jsonld`には、生成したドキュメントを保持します。1つなら単一オブジェクト、複数ならJSON配列で、パンくずリスト、その後に指定したブロックの順です。どちらも有効なJSON-LDであり、`@seo` / `renderSchema()`を通してそのまま出力されます。

### このエディターで管理しないスキーマ {#schema-it-doesn-t-manage}

コードで作成した、このエディターでは表現できないスキーマは**そのまま保持**します。手書きの`@graph`、特殊な`@type`、フォームにないフィールド（レビュー、評価、GTIN/MPN）を持つProductなどです。フォームを開いて保存しても、これらを上書きして壊すことはありません。

## トラブルシューティング {#troubleshooting}

- **保存したフィールドがページにない：**同じレコードとロケールに対して、テンプレートが`@seo($model)`を出力しているか確認します。
- **フィールドが引き続きフォールバックを使う：**現在の言語に、そのフィールドの上書き値が保存されているか確認します。取得元の表示で、解決された層を特定できます。
- **言語タブがない：**明示的な`locales:`引数、パッケージ設定、ページ単位の翻訳切り替えを確認します。優先順位は上記のとおりです。

::: details Testbenchで独自のパネルをテストする場合

orchestra/testbench内でFilamentを起動する場合は、Filamentの`SupportServiceProvider`を`LivewireServiceProvider`**より前に**登録してください。FilamentはLivewireの`DataStore`を再バインドするため、順序が逆だとすべてのLivewireテストが`ViewErrorBag::put(): ... null given`で失敗します。実際のアプリには影響しません。パッケージ検出がプロバイダーを正しく並べます。
:::

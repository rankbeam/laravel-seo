---
description: "Rankbeamをインストールし、既存のモデルにHasSEOトレイトを追加してSEOフィールドを保存し、Bladeが出力するタグを確認します。"
---

# クイックスタート {#quickstart}

既存のLaravel 11、12、13アプリケーションと、動作するデータベースを用意してください。
Laravel 11はPHP 8.2–8.4、Laravel 12はPHP 8.2–8.5、Laravel 13はPHP 8.3–8.5に対応しています。コアパッケージはMITライセンスで無料で利用でき、アカウントやProライセンスは不要です。

## インストール {#install}

アプリケーションのディレクトリで、次のコマンドを実行します。

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

サービスプロバイダーは自動検出されます。マイグレーションで作成されるのはSEO用のテーブルであり、アプリケーションのコンテンツモデルは作成されません。

## サンプルを試す前に {#before-the-example}

以下の手順では、`Post`モデル、保存済みの記事、その記事を`$post`としてBladeビューに渡す`posts.show`ルートがすでに存在することを前提としています。これらの名前はアプリケーションに合わせて変更してください。このガイドでは既存のページにSEOを追加します。ブログ自体を作成する手順は含みません。

`.env`の`APP_URL`には、サイトの公開オリジンを設定してください。別の描画方式を使う場合は、[InertiaとJSONのガイド](/ja/guide/inertia-json)または[Livewireのガイド](/ja/guide/livewire)を参照してください。

## 1. モデルにトレイトを追加する {#_1-add-the-trait-to-a-model}

```php
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

`getUrlForSEO()`は、モデルの正規URLをリゾルバーに伝えます。このURLはcanonicalタグ、`og:url`、サイトマップのエントリーに使われます。

## 2. head内にタグを出力する {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)`は、title、meta description、canonical、robots、Open Graph、Twitter Cardの各タグと、解決済みのデータに付随するJSON-LDを出力します。明示的な値をまだ保存していない場合は、記事自身の属性から計算されるフォールバック値と、設定済みのデフォルト値が使われます。詳しくは[リゾルバーの優先順位](/ja/concepts/resolver-precedence)を参照してください。

## 3. 値を明示的に設定する {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

明示的に設定した値は、すべてのフォールバック層より優先されます。翻訳したメタデータを保存するには、ロケールを渡します。例：`$post->saveSEO(['title' => 'Titre'], 'fr')`。

::: tip シーダーでモデルを作成する場合
Laravelのデフォルトの`DatabaseSeeder`は`WithoutModelEvents`トレイトを使用するため、`HasSEO`の自動作成フックが警告なしに無効になります。このトレイトを外すか、シーダー内で`saveSEO()`を明示的に呼び出してください。
:::

## 4. 結果を確認する {#_4-verify-the-result}

記事の公開ページを開き、**ページのソースを表示**します。`<head>`内で、タイトルに`Custom SEO Title`が含まれていること、descriptionが`Custom meta description`であること、canonicalが記事の公開URLを指していることを確認してください。設定したタイトルの接尾辞が、タイトルの後ろに付く場合もあります。

`@seo($post)`は1ページにつき1回だけ出力してください。レイアウトがすでにtitleタグやmetaタグを出力している場合は、それらを置き換えて重複を防ぎます。想定と異なる値が出力された場合は、[値の解決過程を調べるガイド](/ja/guide/explain)で、その値がどこから取得されたかを確認してください。

## 5. サイトマップを追加する（任意） {#_5-add-a-sitemap-optional}

```php
// e.g. in AppServiceProvider::boot()
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

SEO::sitemaps()->register('posts', Post::class);
```

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

これで、`/sitemap.xml`から生成済みのインデックスが配信されます。すべてのオプションは[サイトマップ登録のガイド](/ja/guide/sitemaps)を参照してください。

## 次に読むガイド {#where-to-go-next}

- [リゾルバーの優先順位](/ja/concepts/resolver-precedence) — 値の選択方法
- [Bladeのガイド](/ja/guide/blade) — 全7種類のディレクティブ
- [InertiaとJSON](/ja/guide/inertia-json) — ヘッドレス環境での出力
- [スキーマグラフ](/ja/guide/schema) — 相互にリンクしたJSON-LD
- [Filamentフィールド](/ja/guide/filament) — 2行で追加できる管理画面UI

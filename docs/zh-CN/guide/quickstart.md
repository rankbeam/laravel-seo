---
description: "安装 Rankbeam，为现有模型添加 HasSEO trait，保存 SEO 字段，并在 Blade 中验证渲染的标签。"
---

# 快速入门 {#quickstart}

从一个已有的 Laravel 11、12 或 13 应用开始，并确保数据库可以正常使用。Laravel 11 支持 PHP 8.2–8.4，Laravel 12 支持 PHP 8.2–8.5，Laravel 13 支持 PHP 8.3–8.5。核心采用 MIT 许可证，免费使用，无需账户或 Pro 许可证。

## 安装 {#install}

在应用目录中运行以下命令：

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

服务提供者会被自动发现。迁移创建 SEO 数据表，不会创建应用的内容模型。

## 示例的前提 {#before-the-example}

下面的步骤假定你已经有一个 `Post` 模型、一篇已保存的文章，以及一个 `posts.show` 路由，该路由的 Blade 视图通过 `$post` 接收文章。请根据应用调整这些名称。本指南为该页面添加 SEO，不负责构建博客。

在 `.env` 中，将 `APP_URL` 设为网站公开访问的源地址。使用其他渲染方式时，请参阅 [Inertia 与 JSON 指南](/zh-CN/guide/inertia-json)或 [Livewire 指南](/zh-CN/guide/livewire)。

## 1. 为模型添加 trait {#_1-add-the-trait-to-a-model}

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

`getUrlForSEO()` 告诉解析器模型所在的规范网址，为规范标签、`og:url` 和网站地图条目提供地址。

## 2. 渲染 head {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` 输出标题、元描述、规范网址、robots、Open Graph、Twitter Card 标签，以及附加到解析数据上的 JSON-LD。如果尚未保存显式值，所有内容都来自计算得出的回退值，也就是文章自身的属性，以及你配置的默认值。详见[解析器优先级](/zh-CN/concepts/resolver-precedence)。

## 3. 设置显式值 {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

显式值优先于所有回退层。保存翻译后的元数据时传入语言：`$post->saveSEO(['title' => 'Titre'], 'fr')`。

::: tip 正在填充模型数据？
Laravel 默认的 `DatabaseSeeder` 使用 `WithoutModelEvents` trait，它会静默禁用 `HasSEO` 的自动创建钩子。请移除该 trait，或在 seeder 中显式调用 `saveSEO()`。
:::

## 4. 验证结果 {#_4-verify-the-result}

打开文章的公开页面，选择**查看网页源代码**。在 `<head>` 中检查：标题包含 `Custom SEO Title`，描述为 `Custom meta description`，规范网址指向文章的公开网址。配置的网站标题后缀可能追加在标题之后。

每页只渲染一次 `@seo($post)`。如果布局已经输出标题或元标签，应替换这些标签，避免重复。如果某个值不符合预期，使用[解析说明指南](/zh-CN/guide/explain)检查来源。

## 5. 添加网站地图，可选 {#_5-add-a-sitemap-optional}

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

现在，`/sitemap.xml` 会提供生成的索引。完整选项见[网站地图注册表指南](/zh-CN/guide/sitemaps)。

## 下一步 {#where-to-go-next}

- [解析器优先级](/zh-CN/concepts/resolver-precedence)：如何选择生效值
- [Blade 指南](/zh-CN/guide/blade)：全部七条指令
- [Inertia 与 JSON](/zh-CN/guide/inertia-json)：无头渲染
- [结构化数据图](/zh-CN/guide/schema)：相互关联的 JSON-LD
- [Filament 字段](/zh-CN/guide/filament)：两行代码接入管理界面

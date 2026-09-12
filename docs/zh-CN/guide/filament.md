---
description: "使用免费的 laravel-seo-filament 包，只需两行即可为任何 Filament 资源表单添加完整 SEO 区域，支持基于 HasSEO trait 的 Filament 4.x 和 5.x。"
---

# Filament 管理字段 {#filament-admin-fields}

免费的 [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) 包为任何 Filament 资源表单添加完整 SEO 区域，**每个资源只需两行**。支持 Filament **4.x 和 5.x**，对应 Livewire 3 和 4。元数据编辑免费，Pro 则提供扫描和下例显示的评分。

## 前提 {#prerequisites}

使用已有的 Filament 4 或 5 面板，以及带有核心 `HasSEO` trait 的模型。添加编辑器之前，先完成[核心快速入门](/zh-CN/guide/quickstart)，包括迁移和渲染。

## 安装 {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

资源对应的模型必须使用核心 `HasSEO` trait。

## 为资源添加区域 {#add-the-section-to-a-resource}

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

## 检查保存结果 {#check-the-saved-result}

打开一条现有记录，输入 SEO 描述，保存并重新加载表单。值应当被保留，预览应显示它，来源应显示为**手动设置**。检查渲染页面的 `<head>`，确认访客获得的是同一个描述。

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="Merchant 演示中的 SEO 字段：标题、描述、规范网址、社交图片、搜索预览和解析值来源。" width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Merchant 演示中的示例。字段使用面板主题，可用控件和字符预算取决于所安装的版本及配置。*

该区域包含：

- **标题和描述**，带实时字符计数器。预算来自核心针对正在输入的文字系统使用的[长度策略](/zh-CN/guide/multilingual#title-and-description-budgets-per-script)：拉丁文字为 60/160，CJK 约为 30/80，按字素计数。
- **焦点关键词**，以标签输入框呈现。输入普通关键词后，按核心的结构化 `[{keyword, is_primary}]` 格式持久化，第一个为主关键词，因此 `getPrimaryKeyword()` 和 `SEOData` 可以原样读取。启用 `seo.keywords.enabled` 后，[`seo:audit`](/zh-CN/guide/audit) 命令和 Pro 扫描会标记仍缺少关键词的页面。默认关闭，由同一个开关控制，见[配置](/zh-CN/reference/configuration#focus-keywords)。
- **规范网址**，留空表示自动生成并移除查询字符串。
- **Robots** 选择框，留空表示使用网站默认值。
- **社交分享图片**上传，供 og:image / twitter:image 使用，存储在 Filament 默认磁盘的 `seo/` 下。
- **搜索摘要预览**，输入时实时反映解析器的回退链。
- **来源指示器**，逐字段显示哪个解析层产生了实际生效值：*手动设置*、*内容回退*、*模型类型默认值*、*全局默认值*、*站点配置*或*由 URL 推导*。

## 限定字段 {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

接受 `title`、`description`、`focus_keywords`、`canonical`、`robots`、`og_image` 的任意子集。

不使用 trait 时，`SEOFields::make(?array $only)` 可以直接返回同一个区域。

## 值如何持久化 {#how-values-persist}

该区域绑定到 `seo_meta` 状态组，通过核心 `seoMeta()` 关系执行更新或创建。无需向自己的数据表添加字段，保存的值会立即成为[解析器](/zh-CN/concepts/resolver-precedence)的第 6 层，即显式值。

## 多种语言 {#several-languages}

核心为[每个模型与语言组合保存一行 `seo_meta`](/zh-CN/guide/multilingual)。传入页面发布的语言后，该区域为**每种语言渲染一个标签页**，此功能从 Filament 1.9 提供：

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

也可以在包配置中统一为所有资源设置一次：

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

每个标签页编辑自己的数据行，并拥有独立的：

- **计数器**，使用对应语言文字系统的[长度策略](/zh-CN/guide/multilingual#title-and-description-budgets-per-script)。因此，同一页面中，空的日语标题显示 `0 / 30`，英语标签页则显示 `0 / 60`。
- **预览**，包括搜索结果和社交卡片，按该语言解析后的值渲染。
- **回退来源指示器**，描述该语言的数据行。
- **徽标**，显示该版本已设置的字段数量，让空翻译更醒目。

加载 `ext-intl` 时，标签页以面板语言显示对应语言名称，例如 `Italiano` / `Italian`，否则显示代码。所有标签页一起验证并保存；完全没有输入内容的语言不会创建占位数据行。

::: details 自定义表单状态绑定
使用多种语言时，状态路径为 `seo_meta.{locale}.title`；只有一种语言时仍为 `seo_meta.title`。自定义表单操作应使用相应路径。
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Merchant 演示中的英语、意大利语和日语标签页，日语标题与描述的预算为 30 和 80，描述尚未设置。" width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Merchant 演示，2026年9月9日，使用 `locales: ['en', 'it', 'ja']`。空的日语标签页使用自己的计数器。这里的英文标题来自演示模型的内容回退：添加语言标签页不会翻译内容。字段上方的 Pro 评分是该记录最后一次扫描的结果，不是每个语言标签页各自的评分。*

### 配合翻译插件 {#with-a-translatable-plugin}

在 **Filament 4 上使用 `lara-zeus/spatie-translatable` 1.x**，或在 **Filament 5 上使用 2.x** 时，请为 Edit 和 Create 使用 Rankbeam 页面适配器。只替换页面 trait 的导入，保留插件的资源和列表 trait、面板插件，以及 `LocaleSwitcher` 操作：

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

每个页面仍需在类中声明 `use Translatable;`。插件仍是应用的可选依赖，请使用其最新修补版本。本地集成测试样例覆盖插件 1.0.4 / Filament 4.13.1，以及插件 2.0.1 / Filament 5.8.1。

切换语言时，编辑器会保留未保存的父级内容、SEO 元数据和结构化数据草稿。保存会验证所有访问过的语言，并在一个数据库事务中一起写入。如果验证失败，会打开需要处理的语言。上传文件在保存时才存储；离开或重新加载页面会丢弃未保存的草稿。保存草稿不会自动翻译缺失内容。

适配器保留正常的前后钩子和表单数据修改器。如果页面覆盖了 `handleRecordCreation()`、`handleRecordUpdate()`、`callHook()` 或事务方法，请将适配器行为整合进自定义逻辑，并测试保存流程。数据库事务不会回滚文件系统写入，应用应保留现有的孤立文件清理机制。

对于 Livewire 3 中自定义的实时文本字段，优先使用 `->live()` 或 `->live(onBlur: true)`，而不是显式防抖。后者会延迟本地模型状态，快速切换语言时可能丢失最后几次按键。Rankbeam 的标题和描述字段使用默认的请求防抖。

只使用上游页面 trait 时，切换会重新填充表单。Rankbeam 会防止由此产生的意外元数据写入，但这些 trait 不保留 SEO 草稿；请将 Edit/Create 页面迁移到适配器。显式 `locales:` 标签页仍然作为共享编辑器，并优先于页面语言切换器。

如果没有显式语言列表或页面语言，该区域编辑应用语言。

## 结构化数据（schema.org） {#structured-data-schema-org}

可选的**结构化数据**区域让编辑无需编写代码，就能附加用于富媒体搜索结果的 JSON-LD。将它放在 SEO 区域旁：

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

也可以不使用 trait，直接调用 `SEOSchemaFields::make()`。

它写入核心 `seo_meta.schema_jsonld` 列，也就是[结构化数据渲染器](/zh-CN/guide/schema)输出的同一个值。这是**纯界面绑定**：每份文档都由核心结构化数据构建器生成，保存前必须通过核心 `SchemaValidator` 验证，不添加自己的结构化数据逻辑。

该区域提供：

- **自动面包屑**，一个开关即可使用，作为无需配置的首选功能。通过 `BreadcrumbSchema::fromModelAncestors()` 从记录的父级链推导 `BreadcrumbList`，无需填写，它会沿用模型的祖先关系。
- **结构化数据块**，使用可重复添加的输入组。每个块可以是 **FAQ**，将问答对转换为 `FAQPage`，或 **Product**，将名称、描述、图片、品牌、SKU、价格及货币、供应情况转换为 `Product`。它们由核心 `FAQSchema` / `ProductSchema` 构建器生成。

### 验证 {#validation}

会产生格式错误 JSON-LD 的块，在**保存时会被拒绝**，并显示核心验证器的消息。例如 FAQ 条目没有答案，或 Product 没有图片或报价。这个构建器要求这些字段，但这并不是对 Google 所有 Product 搜索功能要求的完整说明。完全留空的块会直接忽略。

### 存储内容 {#what-it-stores}

`schema_jsonld` 保存构建后的文档。一份文档时是单个对象，多份时是 JSON 数组，先放面包屑，再放自定义块。两种形式都是有效的 JSON-LD，并通过 `@seo` / `renderSchema()` 原样渲染。

### 不由该编辑器管理的结构化数据 {#schema-it-doesn-t-manage}

对于代码中编写、但编辑器无法表示的结构化数据，例如手写的 `@graph`、特殊的 `@type`，或包含表单未提供字段的 Product，例如评论、评分、GTIN/MPN，都会**原样保留**。打开和保存表单不会覆盖它们。

## 故障排查 {#troubleshooting}

- **保存的字段没有出现在页面上**：确认模板针对同一记录和语言渲染 `@seo($model)`。
- **字段仍使用回退值**：检查当前语言是否保存了该字段的覆盖值，来源指示器会指出解析采用的层。
- **缺少某个语言标签页**：检查显式 `locales:` 参数、包配置及页面级翻译切换器，优先级见上文。

::: details 在 Testbench 中测试自定义面板
如果在 orchestra/testbench 内启动 Filament，请在 `LivewireServiceProvider` **之前**注册 Filament 的 `SupportServiceProvider`。Filament 会重新绑定 Livewire 的 `DataStore`，顺序错误会让每个 Livewire 测试都因 `ViewErrorBag::put(): ... null given` 失败。真实应用不受影响，包自动发现会正确排列提供者顺序。
:::

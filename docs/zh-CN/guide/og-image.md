---
description: "为每个页面生成专属的 1200×630 Open Graph 图片，使用无头浏览器渲染 Blade 模板，妥善处理标题换行和截断。免费核心功能，默认关闭。"
---

# 生成 OG 图片 {#generated-og-images}

从核心包 3.20 起，Chrome 渲染禁用 JavaScript，并阻止 HTTP(S)、FTP 和 WebSocket 资源请求。自定义模板必须像内置模板一样，使用静态 HTML/CSS 和内嵌资源。

没有社交卡片的页面会回退到一个共享的 `default_og_image`，每次分享都显示同一张图片。此功能为每个页面提供**专属的** 1200×630 Open Graph / Twitter 卡片，通过真正的无头浏览器（使用 [spatie/browsershot](https://github.com/spatie/browsershot)）渲染 Blade 模板，从而让标题自然换行、正确显示重音字符、为中日韩文字回退到适当字体，并整齐截断过长标题。这些都是手工编写的图片库无法单独妥善处理的问题。

这是免费的核心功能，**默认关闭**。关闭时，`default_og_image` 的使用方式不变，包也保持零依赖。

::: info 按设计进行静态预生成
卡片由 Artisan 命令提前生成，不会在 Web 请求期间即时渲染。页面只会链接到磁盘上已经存在的卡片，因此访问者的请求绝不会启动浏览器，也不会链接到不存在的（404）图片。**没有实时渲染端点**，参见[注意事项](#caveats)。
:::

## 要求 {#requirements}

浏览器驱动是可选依赖，免费核心包安装时不包含它。启用此功能需要在应用中安装：

```bash
composer require spatie/browsershot
```

还需要 Browsershot 所驱动的运行环境：

- 主机上的 **Node.js**。
- **Puppeteer**，安装在**应用根目录**，以便 Node 解析：
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium**：Puppeteer 默认下载自己的 Chromium；在生产环境通常会指定系统 Chrome，参见[`chrome_path`](#configuration)。

::: warning Windows 上请在应用根目录安装 puppeteer
在 Windows 上，将 `puppeteer` 安装到应用根目录，而不要依赖 `npm_module_path`。该配置键映射到 Browsershot 的 `setNodeModulePath()`，它会生成 POSIX `NODE_PATH=…` 前缀，在 **Windows 上没有作用**。Windows 上的 Node 会从应用目录逐级向上查找模块，因此在根目录安装才有效。参见[注意事项](#caveats)。
:::

## 启用 {#enabling}

如果尚未发布配置，请先发布（`php artisan vendor:publish --tag=seo-config`），再开启开关：

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

接着**预生成**卡片；执行之前不会渲染任何卡片：

```bash
php artisan seo:og-images
```

## 解析方式 {#how-resolution-works}

生成图片绝不会覆盖你设置的图片。启用功能后，解析器**仅在页面没有自身图片时**填入 `og:image`，即解析后的 `og:image` 为空，或仍是全站静态 `default_og_image`。显式设置的模型专属图片（来自 `getSEOImage()`、`seo_meta` 行、内容字段等）始终优先于生成卡片。

为确定该值，解析器调用生成器**以文件存在为前提**的查询：计算卡片的存储路径，**仅在配置的存储磁盘上已经存在该文件时**返回公开 URL。它绝不负责渲染。这就是完整的安全机制：

- Web 请求**绝不会启动浏览器**，最差也只是链接静态 `default_og_image`，与添加此功能之前完全相同。
- 页面**绝不会链接到尚未生成的图片**，因此不会出现分享指向 404 的空档。

要补上“内容已改变”和“卡片已存在”之间的空档，请在部署时和 / 或按计划运行 [`seo:og-images`](#the-seo-og-images-command) 命令。

## `seo:og-images` 命令 {#the-seo-og-images-command}

预生成卡片，让解析器有可提供的文件。

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*`：需要预生成卡片的一个或多个模型类，可重复指定。省略时，命令使用 `seo.og_image.models`，并回退到[网站地图模型](/zh-CN/guide/sitemaps)（`seo.sitemap.models`），与 `seo:llms-txt` 采用相同的共享网站地图数据源方式。
- `--force`：重新渲染已经存在的卡片。修改模板或品牌颜色但未提升 `cache_version` 时可使用。
- `--prune`：预生成后，删除配置路径下不再匹配任何当前模型内容的存储卡片，见下文。为保证安全，它只删除文件名为生成内容哈希的文件，绝不会删除同目录中的其他资源；在限定 `--model` 的运行中**会被忽略**，因为该运行的保留集合不包含其他模型。请在不指定 `--model` 时运行。

每个模型都必须使用 `HasSEO` trait。没有标题的记录会跳过，因为卡片没有可显示的标题。命令报告 `generated`、`skipped`、`failed` 的数量；使用 `--prune` 时还会报告 `pruned` 数量。

### 定时运行 {#scheduling}

按计划预生成，让卡片跟随内容更新，并清理标题变化后遗留的孤立文件：

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### 失效模型 {#the-invalidation-model}

卡片文件名是**所有影响像素的因素的哈希**，包括标题、网站名称、模板名称、驱动、尺寸、品牌渐变颜色、`cache_version` 数值，以及**已安装的包版本**。

该哈希就是缓存键，带来两个需要理解的结果：

- **更改标题 → 新哈希 → 新文件。** 旧卡片成为磁盘上的*孤立文件*，页面在重新预生成前会回退到静态默认图片。运行命令生成新卡片，`--prune` 删除孤立文件。这就是失效模型，无需另做“清除某个页面缓存”的操作。
- **提升 `cache_version`，或升级包 → 所有哈希都会改变。** 修改模板或品牌颜色后，使用 `cache_version` 一次性使所有卡片失效。包升级会自动纳入计算，所以新版本修改内置模板时，不会继续提供旧卡片。

## 内置模板 {#bundled-templates}

包内提供三个模板，均为 1200×630，使用相同的品牌渐变：

| 模板 | 适用内容 | 显示内容 |
| --- | --- | --- |
| `seo::og.default` | 通用 | 标题 + 网站名称 |
| `seo::og.article` | 博客文章、新闻 | 栏目上标 + 标题 + 作者 · 日期署名行 |
| `seo::og.product` | 产品、列表条目 | 品牌标识组合 + 分类标签 + 标题 + 描述 |

通过 `seo.og_image.template` 全局选择模板，或**按模型类型**映射模板，让文章和产品自动使用不同卡片：

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

模型也可以定义 `getOgImageTemplate(): ?string` 在运行时覆盖自身模板，返回视图名称，或返回 `null` 以回退到映射 / 默认值。优先级为：模型钩子，其次 `templates` 映射，最后全局 `template`。

## 自定义模板 {#customizing-the-template}

卡片是一个 Blade 视图（默认为 `seo::og.default`），渲染为自包含的 HTML 文档。内置字体以 data URI 内联，因此浏览器不需要网络。可以用两种方式修改：

**发布并编辑内置视图：**

```bash
php artisan vendor:publish --tag=seo-views
```

然后编辑 `resources/views/vendor/seo/og/default.blade.php`。

**或指定自己的视图：**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

模板接收以下变量：

| 变量 | 类型 | 说明 |
| --- | --- | --- |
| `$title` | `string` | 已设置时使用 OG 标题，否则使用页面标题。 |
| `$siteName` | `?string` | 解析后的 `og:site_name`。 |
| `$fontDataUri` | `string` | 以 `data:` URI 提供的内置粗体字体（不可用时为空字符串，浏览器改用自身的无衬线字体）。 |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`。 |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`。 |
| `$width` | `int` | 输出宽度（默认为 `1200`）。 |
| `$height` | `int` | 输出高度（默认为 `630`）。 |
| `$locale` | `?string` | 解析后的页面语言区域，用于 `<html lang>` 属性。 |
| `$author` | `?string` | 文章作者（由 `seo::og.article` 使用）。 |
| `$publishedDate` | `?string` | `seo::og.article` 的发布日期：可用时使用页面语言区域的 ICU 中等日期格式；否则由 Carbon 按 `M j, Y` 顺序翻译月份。未提供日期时为 null。 |
| `$section` | `?string` | 内容栏目 / 分类（文章上标、产品标签）。 |
| `$description` | `?string` | OG 描述，否则使用页面描述（由 `seo::og.product` 使用）。 |

::: info 模板名称是缓存键的一部分
模板**名称**和渐变颜色都参与内容哈希计算，因此切换模板或更改颜色会自动使现有卡片失效。*原地*编辑模板则不会，因为名称没有变化；编辑后请提升 `cache_version`，或运行 `--force`。
:::

## 配置 {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

多数标量值都有对应的环境变量（`SEO_OG_IMAGE_ENABLED`、`SEO_OG_IMAGE_DISK`、`SEO_OG_IMAGE_CHROME_PATH`、`SEO_OG_IMAGE_NO_SANDBOX` 等），完整列表请查看配置文件。数组类型的键（`templates`、`models`、`browsershot_args`、`font_stack`）直接在配置文件中编辑。

存储磁盘必须**可公开访问**，因为解析器将其 `url()` 用作 `og:image` 值。使用 `public` 磁盘时，运行一次 `php artisan storage:link`，让 `public/storage` 指向它。

## 在 Linux 上运行（沙箱） {#running-on-linux-the-sandbox}

在限制 Chrome 沙箱机制的主机上，`php artisan seo:og-images` 可能出现以下错误：

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

一个可能原因是 Ubuntu 23.10+ 对用户命名空间的限制。请查看 [Puppeteer 故障排查指南](https://pptr.dev/troubleshooting)和浏览器实际的启动错误。优先修复主机配置，让 Chrome 保留沙箱。

**1. 显式回退：使用 `--no-sandbox` 运行 Chrome。** 这会关闭浏览器隔离。只有部署明确接受这一取舍时才使用：

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Rankbeam 渲染静态生成的 HTML，并阻止远程资源请求，但这些措施不能替代 Chrome 沙箱。请让渲染进程以非特权身份运行，并与无关工作负载和密钥隔离。

**2. 保留沙箱。** 保持 `no_sandbox` 关闭。如果原因是 AppArmor，请为实际的 Chrome 可执行文件调整配置文件，参见 [Chromium 指南](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md)。例如：

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

然后使用 `sudo apparmor_parser -r /etc/apparmor.d/chrome-og` 加载配置文件，并验证 Chrome 已启用沙箱启动。

::: tip 其他参数
如果容器共享内存不足（另一种常见的 Linux 故障，会导致 Chrome 在渲染中崩溃），可通过 `browsershot_args` 添加参数：

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## 自定义驱动 {#custom-drivers}

`browsershot` 是唯一的内置驱动，但渲染器由契约（`Rankbeam\Seo\Contracts\OgImageRenderer`）抽象。可以注册自己的驱动，例如基于 canvas 或服务的渲染器，再用 `seo.og_image.driver` 选择：

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

驱动只负责把自包含的 HTML 字符串按指定尺寸转成 PNG 字节，不负责布局或模板。

## 字体与非拉丁文字 {#fonts-and-non-latin-scripts}

内置卡片字体（Noto Sans Bold，OFL）覆盖**拉丁、西里尔和希腊文字**。其他文字，包括中文、日文、韩文、泰文、阿拉伯文、希伯来文、天城文和 emoji，都来自**运行 `seo:og-images` 的机器上已安装的字体**。这是有意不内置其他字体：一个中日韩字体就有 16 MB 以上，而主机上只要存在合适字体，Chrome 自带的逐字符回退就能正确处理。

以下三项措施提高其可靠性（3.15）：

1. **每个内置模板都提供按文字系统区分的 `font-family` 字体栈。** body 首先声明 `'OGBrand'`（内置字体），然后是 `seo.og_image.font_stack`，默认包含 `Noto Sans`、四个 `Noto Sans CJK` 字体族、`Noto Sans Thai`、`Noto Sans Arabic`、`Noto Sans Hebrew`、`Noto Sans Devanagari`、`Noto Color Emoji`，最后为 `sans-serif`。Chrome 对每个字符回退到第一个已安装的字体族，所以该列表只会提供帮助，未安装的字体族会被跳过。**页面语言对应的中日韩字体族会移到最前面**（`ja` → JP，`zh-Hans` → SC，`zh-Hant` / `zh-TW` / `zh-HK` → TC，`ko` → KR），因为同一汉字码点在各地区字体中的字形不同（汉字统一编码）；`<html lang>` 属性则以 BCP 47 格式携带页面语言区域。字体栈属于缓存键的一部分，更改它会重新渲染所有卡片。

2. **`seo:og-images` 中的预检。** 渲染前，命令会查询 fontconfig（`fc-list :lang=ja`、`th`、`ar` 等），检查是否有字体覆盖标题、网站名称和描述中的文字系统，包括混合文本中占比较小的文字，并对**每种文字系统只警告一次**，指出需要安装的包：

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   如果没有 fontconfig（Windows、macOS、精简容器），则保持静默，不作猜测。缺少字体不会让渲染本身失败，Chrome 会绘制 .notdef 方框；这正是需要警告的原因。

3. **实际冒烟测试为每种文字系统提供字形样例。** 启用 `SEO_OG_IMAGE_LIVE_TEST=1` 时，`tests/Feature/OgImage/BrowsershotSmokeTest.php` 会分别渲染 ja、zh-Hans、zh-Hant、ko、el、ru、tr、th、ar、he 和 hi 的标题，同时渲染由未分配码点组成的等长对照文本（保证显示方框）。如果两张 PNG 的字节完全相同，测试会失败并指出文字系统和所需包。这只是冒烟检查，不能证明每个字形都完整：即使部分字形缺失，混合的拉丁文字或不同换行仍可能让图片不同。请检查部署主机上的实际渲染结果及所用字体。语言级 FontProbe 警告也只是预检，不是完整字体覆盖认证。核心包没有 `seo:doctor` 命令；请使用 `seo:og-images` 进行此预检。

在 Debian/Ubuntu 上：

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

3.15 之前通过 `--tag=seo-views` 发布的自定义模板仍然可用。它们会收到新的 `$fontFamily` 和 `$lang` 变量，也可以忽略这些变量。

## 注意事项 {#caveats}

以下限制在生产中可能造成实际问题，因此明确说明：

- **仅预生成，没有实时渲染端点（v1）。** 没有收到请求后渲染卡片的路由。由于 Web 请求不会触发渲染，因此**没有需要配置或防护的签名 URL / SSRF / DoS 接口**；取舍是必须在部署时和 / 或按计划运行 [`seo:og-images`](#the-seo-og-images-command)，卡片才会存在。
- **`npm_module_path` 在 Windows 上没有作用。** 它映射到 Browsershot 的 `setNodeModulePath()`，后者在命令前添加 POSIX `NODE_PATH=…`，Windows 会忽略它。在 Windows 上，请在**应用根目录**安装 `puppeteer`，让 Node 能逐级向上查找解析。（Linux/macOS 上该设置按预期工作。）
- **非拉丁文字需要主机安装相应字体。** 参见[字体与非拉丁文字](#fonts-and-non-latin-scripts)：内置字体覆盖拉丁、西里尔和希腊文字，其他文字依靠部署镜像上安装的字体，缺少时命令会提示。
- **失败时回退。** 如果渲染失败（缺包、浏览器崩溃、超时），命令会报告，页面继续使用静态 `default_og_image`；浏览器故障绝不会让页面返回 500。

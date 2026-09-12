---
description: "安装 laravel-seo-pro，在核心之上添加带问题跟踪的队列扫描、重定向管理器和 404 监测器。支持任何 Laravel 11–13 应用，Filament 可选。"
---

# 安装 Pro {#installing-pro}

`rankbeam/laravel-seo-pro` 在核心包之上添加带问题跟踪的队列式网站扫描、重定向管理器和 404 监测器。引擎可在**任何 Laravel 11–13 应用**中运行，包括 Blade、Inertia 或纯 API。Filament 是可选界面层，安装后可在面板页面中使用 SEO 仪表板、重定向管理器和 404 监测器；不安装时，通过 [Artisan 命令](/zh-CN/pro/headless)管理全部功能。

## 要求 {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4（Laravel 11）；8.2–8.5（Laravel 12）；8.3–8.5（Laravel 13） |
| Laravel | 11、12 或 13 |
| `rankbeam/laravel-seo` | ^3.20，由 Pro 2.40+ 自动安装 |
| `filament/filament` | **可选**，4.x 或 5.x，仅管理界面需要 |
| `rankbeam/laravel-seo-filament` | **可选**，Pro 2.36+ 配合 SEO 编辑器时使用 ^1.11 |

从已有 Laravel 应用和配置好的数据库开始。先完成[核心快速入门](/zh-CN/guide/quickstart)，确保模型能渲染元数据且核心数据表已存在。Pro 许可证提供下面需要的 Composer 凭据。

想直观看到结果，可以查看[扫描 → 修复 → 报告](/zh-CN/pro/walkthrough)。

## 安装包 {#install-the-package}

Pro 通过与许可证关联的私有 Composer 仓库分发。添加仓库一次，再安装包。Composer 会要求输入许可证邮箱作为用户名，以及许可证密钥作为密码：

Lemon Squeezy 作为名义商户处理付款。付款后，私有收据页面提供下载密钥和 Composer 说明，用户名使用购买邮箱。包仓库由 Rankbeam 托管，无需 Anystack 账户。请勿公开收据链接和 `auth.json`。全额退款会撤销今后的下载和更新权限，但不会中断已安装的应用。

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details 非交互式 Composer 身份验证
在 CI 或非交互式环境中，提前存储凭据：

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

然后运行安装器：

```bash
php artisan seo-pro:install
```

安装器发布 `config/seo-pro.php` 和 Pro 迁移，运行 `migrate`，再显示后续步骤。此时核心和 Pro 数据表应已存在于应用数据库中。

::: details 手动安装和安装器标志
Pro 迁移发布到应用中，不会从包内自动加载。等效的手动步骤是：

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

安装器可以重跑。`--no-migrate` 只发布文件，不执行迁移。只有确实希望覆盖已发布文件，包括配置时，才使用 `--force`。
:::

## 注册扫描目标 {#register-scan-targets}

在服务提供者中告诉扫描器需要扫描什么，可以是模型类、具名路由，或[网站地图注册表](/zh-CN/guide/sitemaps)中的全部内容：

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

将 `Post` 换成使用 `HasSEO` 的自有模型。至少需要一条记录才能看到模型扫描结果。路由目标必须使用已有路由名称；只扫描模型时，可以省略路由注册。

## 验证安装 {#verify-your-install}

运行配置检查：

```bash
php artisan seo:doctor
```

确认核心和 Pro 表存在，应用网址正确，并列出了扫描目标。按照报告修复问题。尝试下面的内联命令时，出现 `sync` 队列警告是预期情况；安排生产扫描之前，应配置工作进程。

::: details 健康检查输出示例
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor` 检查配置和近期运行历史，不发起网络请求，也不输出密钥。它无法证明外部 cron 或工作进程实际在运行。关键失败返回非零退出码，警告不会。使用 `--json` 获取机器可读结果。
:::

## 运行第一次扫描 {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

第一条命令在当前进程内完成扫描，因此初次检查无需队列工作进程。第二条显示最新运行及结果。预期应看到已完成的运行，注册目标均已处理；在将扫描视为完整完成之前，应调查任何失败目标。

修复一个报告中的字段，保存，再次运行扫描。[演练](/zh-CN/pro/walkthrough)以缺失描述为例，展示修复及变化报告。[技术评分](/zh-CN/pro/scoring)是诊断结果，不是排名预测。

## 无头模式使用 {#path-b-headless}

引擎无需面板即可使用。[Artisan 命令](/zh-CN/pro/headless)支持扫描、查看问题、创建重定向和生成报告。重定向与 404 中间件默认自动注册，设置位于 `config/seo-pro.php`。

需要定时工作时，按[生产环境设置](/zh-CN/pro/production)配置队列、工作进程、调度器和保留策略。

## 添加 Filament 面板，可选 {#path-a-with-a-filament-panel}

已有 Filament 4 或 5 面板时，注册下面的 Pro 插件。如果应用尚无面板，先安装界面包并创建一个：

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

这会添加 **SEO 仪表板**，包含扫描全部操作、实时进度及支持一键重新扫描的问题列表；还有**重定向管理器**，以及支持一键*创建重定向*的 **404 监测器**。`rankbeam/laravel-seo-filament` 另外为资源表单提供 [SEO 字段区域](/zh-CN/guide/filament)。

## 故障排查 {#troubleshooting}

| 结果 | 下一步 |
|---|---|
| Composer 拒绝凭据 | 检查 `blog.rankbeam.dev` 使用的许可证邮箱和密钥，不要将凭据放入版本控制。 |
| Doctor 报告缺少表 | 完成核心快速入门，再针对应用使用的同一个数据库运行 `seo-pro:install` 和 `migrate`。 |
| 扫描没有处理任何目标 | 检查提供者注册，并确认模型中有记录。 |
| 队列扫描一直等待 | 启动配置的队列工作进程，或使用 `--sync` 进行内联检查。 |
| 某个目标失败 | 重新扫描前检查运行详情、路由名称和应用网址。 |
| 没有仪表板 | 在实际使用的面板上注册 `SeoProPlugin`，并检查访问控制。 |

工作进程恢复和持续运维见[生产环境设置](/zh-CN/pro/production)。

## 许可证与退款 {#license}

早期用户许可证一次性 €179，最多覆盖五个生产项目，包括客户项目，并提供终身更新。这些项目的开发和预发布副本不单独计数。包含安装和迁移协助、一次 60 分钟安装通话，以及公布的上线配置服务。你可以在 30 天内通过收据或发送邮件至 valentinogoxhaj@gmail.com，申请无条件全额退款。全额退款后应停止使用 Pro。你可以为已授权项目修改 Pro，但不得公开其源码，也不得将其作为独立包或启动套件转售。包内包含完整许可证条款。

Pro 最多可用于五个生产项目，包括客户项目。这些项目的开发、预发布和测试副本不单独计数。终身更新包括未来 Pro 版本，不包含持续的个人实施服务。

包含一次 60 分钟安装与配置通话，以及一个初始项目的元数据迁移。迁移覆盖受支持来源，开始前会确认范围；应用定制修改另行报价。上线配置服务针对同一项目，使用免费核心已有功能，检查并配置 llms.txt、robots.txt 中的 AI 爬虫规则，以及面向机器人的 Markdown 响应。请发送邮件至 hello@rankbeam.dev 安排已包含的协助。

你的订单适用购买时显示的优惠条款。

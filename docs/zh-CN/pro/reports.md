---
description: "一条命令生成带有自有品牌的白标 PDF 报告，包含评分、问题趋势、已修复与新增问题、已恢复的 404、Search Console 变化和 AI 爬虫活动，也可定期通过邮件发送。"
---

# 白标报告 {#white-label-reports}

为单个网站生成带有自有品牌的 **PDF 报告**，涵盖整体评分、发现问题的趋势、**相较上次报告已修复与新增的问题**、已恢复的 404 和失效链接、Search Console 变化以及 AI 爬虫活动。一条命令即可生成，也可选择**定期通过邮件发送**。专为代理机构设计：添加你的标志、品牌色和“为 {client} 编制”标签，即可交付客户。

[下载已生成的英文示例报告（PDF，98 KB）](/pro-walkthrough/merchant-demo-report.pdf)，或跟随[扫描 → 修复 → 报告演练](/zh-CN/pro/walkthrough)。示例使用预置的 Merchant 内容和两次新扫描，显示一个已修复问题、19 个尚未解决的问题，且不包含 Search Console 数据。

[![已生成的 Merchant 演示报告第一页。](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## 报告包含什么 {#what-s-in-it}

- **整体评分**：最新逐页评分的平均值（采用已公布的[评分规则](/zh-CN/pro/scoring)：A ≥ 90 … F），以及相较上次报告的变化，同时显示近期扫描的**整体评分趋势**。每次扫描现在都会将网站评分记录在该次运行中，因此趋势来自真实的逐次扫描历史。升级后的第一次扫描开始积累这些数据，较早的运行没有评分，会直接跳过。
- **每次扫描发现的问题**：基于近期已完成扫描的真实趋势，问题越少越好。
- **已修复与新增问题**：自上次报告以来消除的缺陷数量，以及新出现的缺陷数量。问题现在具有已修复、重新打开的[生命周期](/zh-CN/pro/scan-issues#issue-lifecycle)；当这套记录覆盖一个完整周期后，报告从真实问题历史中读取，否则使用上次报告的快照。
- **已恢复**：自上次报告以来已解决的失效链接、**已恢复**的 404（路径自身再次返回 200，见 [`seo-pro:404-recheck`](/zh-CN/pro/production#scheduler)）、**已重定向**的 404，以及仍未解决的数量。已恢复的 404 代表源页面真正修复，与重定向分开计数。
- **Search Console**：热门查询和页面，以及**变化最大的项目**，即相较上次报告点击量波动最大的项目。未配置 GSC 时会直接跳过这一部分。
- **AI 爬虫活动**：按 user-agent 归属的请求（并非经过验证的爬虫身份）、累计总量，以及在按日记录的[分桶历史](/zh-CN/pro/ai-bot-monitor#period-metrics-daily-buckets)覆盖报告窗口时，**本周期的实际命中数和每个爬虫抓取的不同 URL 数量**；否则回退到累计值的快照差值。

## “自上次报告以来” {#since-the-last-report}

报告按**相对于上一份报告的周期变化**进行比较，而不是相对于任意日期。每次生成报告时，都会存储一份轻量快照（`seo_report_runs`），包含评分、未解决问题的身份、Search Console 数据行，以及每个爬虫的命中计数。下一份报告将当前状态与该快照比较。

对于不单独保留历史的数据，这是回退方式：例如逐页评分只保留最新值，在生成报告时创建快照，才能据实比较。现在已有多种信号保留**真实**历史，报告会优先使用这些历史，只有必要时才回退到快照：问题具有已修复、重新打开的[生命周期](/zh-CN/pro/scan-issues#issue-lifecycle)（覆盖完整周期后提供真实的已修复与新增计数），Search Console 保留[按日指标](/zh-CN/pro/search-console#historical-metrics)，AI 爬虫命中保留[按日分桶](/zh-CN/pro/ai-bot-monitor#period-metrics-daily-buckets)（提供实际周期命中数和不同 URL 数量）。升级后的第一份报告，或历史未覆盖报告窗口时，各项都会回退到快照差值。

这意味着：

- **第一份报告是基线。** 它显示当前状态；“已修复”“新增”、变化项目以及“自上次报告以来”的数字，从*第二份*报告开始填充。
- **频率由你决定。** 每月生成，差值就覆盖一个月；每周生成，就覆盖一周。临时预览且不应推进基线时，请使用 `--no-store`。

## 生成报告 {#generate-a-report}

```bash
php artisan seo-pro:report
```

不传入选项时，会将 PDF 写入 `storage/app/seo-reports/`。也可以指定位置或通过邮件发送：

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### 选项 {#options}

| 选项 | 作用 |
| --- | --- |
| `--client=` | 覆盖“为谁编制”的客户标签 |
| `--agency=` | 覆盖报告上的代理机构名称 |
| `--accent=` | 覆盖强调色（十六进制，例如 `#3D5AFE`） |
| `--logo=` | 覆盖标志图片路径 |
| `--email=` | 收件人地址（可重复指定）；通过邮件发送报告 |
| `--send` | 发送给已配置的收件人 |
| `--output=` | 将 PDF 写入指定文件或目录 |
| `--no-store` | 不保存快照（周期差值的基线不会推进） |
| `--json` | 输出机器可读的摘要 |

## 定期发送邮件 {#schedule-the-e-mail}

包不会自行安排调度，频率由你掌控。在应用的控制台调度配置（`routes/console.php` 或 `app/Console/Kernel.php`）中添加：

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

在配置或 `.env` 中一次性设置默认收件人：

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` 会使用这些默认收件人；明确传入的 `--email` 选项会覆盖它们。

## 品牌设置 {#branding}

品牌信息不属于机密，因此保存在配置中。设置一次，每份报告都会采用。所有字段都可以通过上述命令选项逐份覆盖，适合一个安装实例为多个客户制作报告标签的情况。

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

注意事项：

- **标志**：指向 `PNG`/`JPG`/`GIF`/`WEBP`/`SVG` 文件的绝对路径。应用读取文件后，将其作为 data URI 嵌入 PDF，因此渲染器不需要通过网络获取图片。`PNG` 或 `JPG` 最稳妥。
- **强调色**：验证为十六进制字面值；无效值会回退到默认值。它始终仅作为颜色使用，不会作为原始 CSS 注入。
- **代理机构名称**：默认使用应用名称（`config('app.name')`）。

完整配置块位于 `config/seo-pro.php` 的 `reports` 下，包括 `paper`（默认 `a4`）、`include_gsc`，以及纳入多少次趋势运行、GSC 数据行和爬虫。

## 每个安装实例对应一个网站 {#one-site-per-install}

Pro 扫描的是安装它的那个应用，因此报告描述的是**该安装实例**。代理机构若运营多个客户网站，需要为每个安装实例分别生成报告，再通过 `--client` 和品牌覆盖项加以标注。这里没有多租户“网站”模型。

## 实现方式 {#how-it-s-built}

默认使用 **dompdf** 渲染 PDF：纯 PHP，无需 Node 或无头 Chromium。因此，定期报告可以在队列 worker 或 cron 中渲染，不需要任何系统二进制程序，Pro 也保持无界面可用。渲染器禁用了远程获取，唯一的图片（你的标志）采用嵌入方式，因此渲染字段中的内容无法触发网络请求。

### 支持各种文字的报告（Browsershot 渲染器） {#reports-in-every-script-browsershot-renderer}

从 core 3.20 / Pro 2.40 开始，Chrome 渲染器禁用 JavaScript，并阻止 HTTP(S)、FTP 和 WebSocket 资源请求。发布后的模板必须使用静态 HTML/CSS，并嵌入资源。这些控制针对页面资源；Chrome 仍需要正确配置的主机和沙箱。当 Fontconfig 报告某种文字缺少字体时，包括混合文本中只占少量的文字，PDF 渲染器会记录可操作的字体安装警告。缺少字体并不会阻止 Chrome 生成 PDF，因此发送报告前应检查输出。

dompdf 只会绘制其嵌入字体支持的字符（DejaVu Sans：拉丁、西里尔和希腊文字），因此为日语、泰语或阿拉伯语客户生成的报告会出现缺字方框。从 Pro 2.34 开始，也可以通过 `spatie/browsershot` 使用**无头 Chrome** 渲染报告；这与 core 生成 OG 图片所用的依赖相同，所以一台机器只需配置一次：

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chrome 可以使用服务器上安装的字体，模板会采用 core 按文字类型选择的字体栈（`Noto Sans`，优先采用页面语言对应的 `Noto Sans CJK` 字族，再包括泰文、阿拉伯文、希伯来文、天城文、彩色 emoji，并以 DejaVu Sans 作为拉丁文字的基础字体）。安装所需字族即可：Debian 和 Ubuntu 上使用 `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`，方法与[OG 图片](/zh-CN/guide/multilingual#og-images-in-every-script)相同。`seo:og-images` 会在运行时发现页面文字没有对应已安装字族时发出警告，这同样适用于报告。两个引擎使用相同的 Blade 模板、数据和快照，只有栅格化器不同；`ReportGenerator::renderer()` 会显示当前绑定的是哪一个。

### 按读者区域设置显示日期和数字 {#dates-and-numbers-in-the-reader-s-locale}

报告在构建时捕获 `seo-pro.reports.locale`。设为 null 时使用应用的区域设置。最终解析出的翻译语言控制 PDF 和邮件标签、默认主题、字体选择以及 HTML `lang`。没有独立翻译文件的区域变体会先回退到包内对应的基础语言，再回退到英语。简体中文（`zh_CN`）和繁体中文（`zh_TW`）保持区分。

安装 `ext-intl` 时，日期和数字通过 ICU 按所请求的区域设置格式化。若希望明确使用不同的区域格式，可设置 `seo-pro.reports.format_locale`：`locale=it` 和 `format_locale=en_US` 会生成意大利语标签，同时采用美国日期与数字格式。没有 `ext-intl` 时，仍保留英语日期和逗号分组数字的回退格式。

即使 worker 配置改变，排队邮件仍会保留已捕获的语言、格式和主题。请在生成 PDF 之前选择语言；之后修改 mailable 的区域设置，无法翻译其附件。Pro 2.39 之前的旧排队载荷没有捕获这些设置，因此使用 worker 配置。自定义主题、品牌信息和已存储的问题消息仍保持源数据内容。

CLI 显示是独立的：`php artisan seo-pro:report --display-locale=it` 翻译命令摘要；报告配置则选择客户收到的 PDF 和邮件语言。CLI 默认使用英语，可通过 `SEO_PRO_CLI_LOCALE` 配置。JSON 键和代码保持稳定，人类可读的标签可以翻译。发布 `seo-pro-lang` 后，可在 `lang/vendor/seo-pro/{locale}/seo-pro.php` 中覆盖报告和工作流消息。

通过代码使用时，从容器解析 `ReportGenerator`：

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```


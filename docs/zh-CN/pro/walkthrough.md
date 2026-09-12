---
description: "跟随真实的 Rankbeam Pro 扫描，检查缺失描述，在 Filament 中保存修复，重新扫描，并下载生成的示例 PDF 报告。"
---

# 从扫描到验证修复 {#from-a-scan-to-a-verified-fix}

一次扫描发现演示文章缺少描述。我们在 Filament 中添加描述，再次扫描，并生成显示修复结果的报告。

这些画面来自 2026 年 9 月 9 日运行中的本地 Merchant 演示。内容是预置示例数据，两次扫描和报告都专门为本演练生成，没有预填历史趋势。应用使用 Laravel 12、Filament 4，以及 Rankbeam 核心、免费编辑器和 Pro 引擎。

**[下载生成的报告，PDF，98 KB](/pro-walkthrough/merchant-demo-report.pdf)**

## 扫描已注册页面 {#scan-the-registered-pages}

[安装 Pro](/zh-CN/pro/installation)并注册扫描目标后，运行：

```bash
php artisan seo-pro:scan --sync
```

演示注册了 18 条内容记录和三个路由。第一次扫描完成全部 21 个目标，没有失败，发现 20 个问题：六个警告和 14 个提示。

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="第一次完成的扫描：21 个目标、20 个问题、六个警告和 14 个提示。" width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*截图以 2× 分辨率捕获，打开即可按完整尺寸查看。*

## 检查一个问题 {#inspect-one-issue}

在 **SEO Dashboard** 中，点击受影响行旁的 **Page issues**。对于“Behind the Scenes: Our Product Photography”，问题详情指出缺失的 `description`、页面网址，以及检测到它的扫描。

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Page issues 对话框指出 Post 5、其网址和缺失的描述字段。" width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## 保存描述 {#save-the-description}

在 **Posts** 中打开文章，填写 **SEO description** 并保存。[免费 Filament 编辑器](/zh-CN/guide/filament)在搜索预览中显示输入文本，并将来源标为 **Manual**。本例的描述为 142 个字符，标题仍来自文章。

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="已保存的 SEO 描述及其 142 字符计数器。" width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="实时预览使用输入的描述，来源标为 Manual。" width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>
保存字段与验证修复是两个步骤，扫描评分在下次扫描后才更新。不使用 Filament 时，可以通过模型的 `saveSEO()` 方法保存同一个值。

## 重新扫描并检查变化 {#rescan-and-check-what-changed}

再次运行同一命令：

```bash
php artisan seo-pro:scan --sync
```

现在仪表板将这个具体问题标为 **Fixed**，其他 19 个问题仍未解决。

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="已记录的扫描对比：零个新增问题、零个回归问题、一个已修复问题，以及 19 个仍未解决的问题。" width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| 检查项 | 修复前 | 修复后 |
|---|---|---|
| 已完成目标 | 21 | 21 |
| 未解决问题 | 20 | 19 |
| 警告 | 6 | 5 |
| 提示 | 14 | 14 |
| 平均技术 SEO 评分 | 92 | 93 |

[评分](/zh-CN/pro/scoring)反映 Rankbeam 的技术检查，不测量流量、搜索排名或是否出现在 AI 回答中。描述检查通过，也不保证搜索引擎会显示该描述。

## 生成报告 {#generate-the-report}

本次演示在编辑文章**之前**生成了一份基线报告，然后在重新扫描后生成第二份报告：

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

第二份 PDF 显示**一个已修复**、**零个新增**和 **19 个未解决**问题。趋势只包含上面两次扫描。Search Console 和 AI 机器人日志均已关闭，因此对应部分说明数据不可用。

[![生成的示例报告首页：评分 93，一个已修复问题，19 个未解决问题。](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

第一份报告建立比较基线。如果只在修复页面后生成一份报告，就无法展示相对于更早报告的变化。需要不推进基线的预览时，使用 `--no-store`。

示例使用 Browsershot 渲染器。渲染器要求、品牌设置和定时发送见[白标报告](/zh-CN/pro/reports)。

## 在自己的应用中运行 {#run-it-on-your-own-app}

从[安装 Pro](/zh-CN/pro/installation)开始，再扫描一个你可以检查输出的页面。Pro 也能[不依赖 Filament](/zh-CN/pro/headless)运行。若想先尝试免费元数据渲染器，可以使用 [Docker 演示](/zh-CN/guide/demo)。

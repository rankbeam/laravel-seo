---
description: "用一条命令运行预置数据的 Rankbeam 演示应用，使用已发布的包而非路径仓库，在真实页面中查看元数据、JSON-LD 结构化数据图和网站地图。"
---

# 运行演示 {#run-the-demo}

想先看看 Rankbeam 在真实页面上的效果，而不立即接入自己的应用，最快的方法是运行演示。这是一个预置了数据的 Laravel 应用，安装的是**已发布的**包，不使用路径仓库，也不需要检出相邻仓库。它渲染少量页面，包含完整 SEO 元数据、JSON-LD 结构化数据图和网站地图。添加许可证后，还可以运行 Pro [技术 SEO 审计](/zh-CN/pro/scan-issues)。

## 一条命令运行免费核心 {#one-command-free-core}

演示以 Docker 镜像形式提供，位于 [`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples) 仓库：

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

打开 `http://localhost:8080`。查看任意页面的源代码，即可看到解析后的 `<head>`；访问 `/sitemap.xml` 可查看生成的网站地图。这里的全部功能都来自免费 MIT 核心，通过 Packagist 安装。

## 使用 Pro 运行审计 {#with-pro-the-audit}

Pro 按项目授权，通过私有 Composer 仓库安装。通过 `COMPOSER_AUTH` 传入许可证，它是构建密钥，不会写入镜像层，再使用 Pro 标志构建：

```bash
export COMPOSER_AUTH='{"http-basic":{"blog.rankbeam.dev":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

启动时，演示会运行 [`seo:doctor`](/zh-CN/pro/headless#setup-health-check)，并对预置页面执行第一次 `seo-pro:scan`。健康报告、扫描摘要和 [0–100 分评分](/zh-CN/pro/scoring)都会输出到 Compose 日志中。

## 查看 Pro 工作流 {#see-the-pro-workflow}

[扫描 → 修复 → 报告演练](/zh-CN/pro/walkthrough)展示运行中的 Merchant 演示：实际扫描、问题详情、在 Filament 中保存描述、重新扫描，以及可下载的 PDF。内容明确标为示例数据，前后结果来自两次新运行的扫描。

目前还没有公开托管的交互式演示。请使用 Docker 在本地运行引擎。[演示 README](https://github.com/rankbeam/rankbeam-examples/tree/main/demo)介绍设置方式，以及如何在已发布包和本地包之间切换。

::: tip 已经有应用？
可以跳过演示，直接阅读[快速入门](/zh-CN/guide/quickstart)，五分钟内从安装走到完整渲染 `<head>`。
:::

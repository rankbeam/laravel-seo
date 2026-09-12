---
description: 通过 Composer 安装 rankbeam/laravel-seo，发布配置并运行迁移，了解 Laravel 11、12 和 13 的要求与设置步骤。
---

# 安装 {#installation}

## 要求 {#requirements}

- Laravel 11：PHP 8.2–8.4；Laravel 12：PHP 8.2–8.5；Laravel 13：PHP 8.3–8.5
- Laravel 11、12 或 13
- `spatie/laravel-sitemap` ^7.0 或 ^8.0：可选，仅生成网站地图时需要

## 安装包 {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

安装就这些步骤。服务提供者和 `SEO` facade 会被自动发现；两项迁移创建这个包仅有的两张数据表：

| 数据表 | 用途 |
|---|---|
| `seo_meta` | 各模型的显式值，按多态关系和语言区分 |
| `seo_defaults` | 全局、模型类型和路由默认值 |

## 可选：网站地图 {#optional-sitemaps}

网站地图生成功能封装了 [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap)：

```bash
composer require spatie/laravel-sitemap
```

数据源和生成方式见[网站地图注册表指南](/zh-CN/guide/sitemaps)。

## 从 v1 升级？ {#upgrading-from-v1}

如果应用使用的是 `fibonoir/laravel-seo` v1，请先阅读[从 v1 升级](/zh-CN/guide/upgrade-from-v1)。供应商名称、命名空间和包的接口范围均已变化，而且 v1 可能发布过与 v2 配置冲突的文件。

## 配套包 {#companion-packages}

| 包 | 新增功能 | 许可证 |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | 为 Filament 4/5 资源表单添加 SEO 区域 | MIT |
| [`rankbeam/laravel-seo-pro`](/zh-CN/pro/installation) | 队列驱动的网站扫描、重定向管理器、404 监测器，可用于任何 Laravel 应用，并提供可选的 Filament 仪表板 | 商业许可证 |

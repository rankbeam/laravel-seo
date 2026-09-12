---
description: "网址发布或更新时通知搜索引擎。Pro 提交到共享的 api.indexnow.org 端点，由其传播给参与引擎。默认关闭。"
---

# IndexNow：发布时推送索引通知 {#indexnow-—-push-on-publish-indexing}

无需等待爬虫发现变化，**IndexNow** 让你在网址发布或更新时立即*通知*搜索引擎。Pro 提交到共享 `api.indexnow.org` 端点，一次调用就会**传播到所有参与引擎**，无需逐引擎派发。[官方 FAQ](https://www.indexnow.org/faq)列出 Amazon、Bing、Naver、Seznam、Yandex 和 Yep。通知不保证建立索引。

该功能**默认关闭**。只有启用并提交网址后，才会访问网络。

## 设置 {#setup}

### 1. 生成密钥 {#_1-generate-a-key}

IndexNow 使用**密钥**验证主机控制权。Pro 接受 8–128 个 `[a-f0-9-]` 字符，32 字符十六进制字符串是合适选择。生成一次并保持稳定，再通过环境设置提供：

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip 密钥通过配置读取，因此兼容 `config:cache`
与 Search Console 凭据不同，IndexNow 密钥**不是秘密**，它会公开在 `/{key}.txt`，用于证明主机所有权。因此，Pro 通过配置层 `indexnow.key` 解析它，默认来自 `env('SEO_PRO_INDEXNOW_KEY')`。这是有意的设计：执行 `config:cache` 后，Laravel 不再加载 `.env`，所以**仅在该文件中定义**的值无法由 `env()` 读取；真实进程环境变量仍然可用。通过配置读取，值会被 `config:cache` 捕获并持续可用。代价是**轮换密钥后需要重新运行 `php artisan config:cache`**。密钥不会写入日志。生产环境密钥文件返回 404 时，见[启用配置缓存的服务器](#config-cached-servers)。
:::

### 2. 提供密钥文件 {#_2-serve-the-key-file}

IndexNow 获取只包含密钥的 `https://{host}/{key}.txt` 来验证所有权。`route` 开关默认启用，**Pro 会代为提供文件**：

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

只有配置的那个密钥路径会提供内容。被此路由捕获的其他路径返回 404，IndexNow 关闭时整条路由也返回 404。如果希望自行托管文件或放在 CDN 上，关闭 `route`，并让 `key_location` 指向自己的网址。

## 提交网址 {#submitting-urls}

### 保存时自动提交：发布时推送路径 {#automatically-on-save-the-push-on-publish-path}

为模型添加 trait，并启用 `auto_submit`。每次保存都会将模型的 `getUrlForSEO()` 提交任务加入队列：

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

trait 遵循发布条件。实现 `shouldSubmitToIndexNow(): bool` 可以完全控制，否则回退到 `is_published` 属性，再没有则每次保存都提交。提交始终**加入队列**，因此保存模型不会阻塞等待网络。

### 手动提交 {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` 默认加入队列，传入 `queue: false` 可在当前进程内运行。

### 从命令行提交 {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning 仅限同一主机
每个网址都必须是 `http(s)`，**并且**属于配置的 `host`。其他网址都会被**丢弃**，计数但不发送。只能提交自己拥有的网址，端点也会拒绝主机不匹配。超过 `max_urls_per_request` 的列表会自动分块，协议上限为 10000。
:::

## 配置 {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## 重试方式 {#how-retries-work}

队列中的 `SubmitToIndexNowJob` 只重试适合重试的情况：`429`，即限流、`5xx` 或超时，会按 `backoff` 最多重试 `tries` 次；`400`/`403`/`422` 属于密钥错误或主机不匹配等永久客户端错误，会记录日志并**停止**，不浪费重试。`200` 和 `202`，即已接收或等待密钥验证，都视为成功。

生产环境中应为任务配置**专用队列**，避免缓慢端点延迟面向用户的工作：

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## 故障排查 {#troubleshooting}

### 启用配置缓存的服务器 {#config-cached-servers}

生产环境中，如果 `/{key}.txt` 返回 404，或提交悄无声息地没有作用，而 `indexnow.enabled` 明明为 `true`，原因通常是服务器运行了 `php artisan config:cache`，但密钥**只存在于 `.env`**。配置缓存后，Laravel 不再解析 `.env`，因此 `env('SEO_PRO_INDEXNOW_KEY')` 返回 `null`，密钥文件路由未注册，所有提交都被以“未配置”拒绝。

默认配置从 `env(...)` 解析 `indexnow.key`，因此正常设置会在构建配置缓存时被捕获，无需额外处理。只有**发布配置后移除了 `env(...)` 默认值**，或者使用了**只存在于 `.env` 的自定义 `key_env` 名称**时，才会遇到这个问题。两种修复方式：

1. **将密钥保留在配置中**，推荐。让 `indexnow.key` 保持为 `env('SEO_PRO_INDEXNOW_KEY')`，或设置字面值，然后重新运行 `php artisan config:cache`。之后轮换密钥需要重建缓存。
2. **注入真实环境变量**：将 `SEO_PRO_INDEXNOW_KEY` 设置为实际操作系统或进程环境变量，例如 PHP-FPM 池的 `env[...]`、systemd 的 `Environment=`，或平台环境变量设置，**不要只写在 `.env` 中**。即使配置已缓存，真实操作系统环境变量仍可读取。

运行 `php artisan seo:doctor` 确认。检测到该状态时，它会报告 **“IndexNow 已启用，但无法解析出有效密钥”**，并提供具体修复方式。应用在配置缓存启用且密钥无法读取时启动，Pro 也会每个进程记录一次警告。

::: tip Google
Google **不参与** IndexNow。对于 Google，请使用 [Search Console](/zh-CN/pro/search-console) 集成，并保持网站地图更新。
:::

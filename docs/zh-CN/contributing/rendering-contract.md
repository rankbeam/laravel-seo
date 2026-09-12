---
description: "每种前端技术栈的 head 渲染 Rankbeam SEO 数据时都必须满足的统一检查清单，是核心渲染器测试和参考应用的可信依据。"
---

# 渲染约定 {#the-rendering-contract}

这是每种前端技术栈的 `<head>` 渲染 Rankbeam SEO 数据时都必须满足的**唯一权威检查清单**，也是以下内容的可信依据：

- 核心中的渲染器数据结构单元测试，即 `tests/Unit/Services/RenderingContractTest.php`，属于包 CI 覆盖的快速、无框架测试部分。
- `rankbeam-examples` 中各技术栈的参考应用，包括 Blade、Inertia + Vue / React / Svelte、Livewire。其浏览器和 SSR 测试在真实 DOM 中验证相同断言。
- Blade、Inertia 与 JSON、Livewire 等框架指南，不得提供违反约定的实现方式。

如果某个技术栈无法满足条款，应视为**缺陷或有文档说明的限制**，不能以此为由削弱约定。数据层，即 `SEOResolver` → 不可变的 `SEOData` → `TagRenderer`，与框架无关。不同技术栈的区别仅在于*解析后的数据如何进入 DOM、在客户端导航后正确保留，并且仍可被爬虫读取*，而这正是本约定固定的内容。

> 本规范曾经过独立设计审查并据此加固。
> 只有发生实质变更时，才需要重新审查。

---

## 1. 值：符合约定的 `<head>` 包含什么 {#_1-values-—-what-a-compliant-head-contains}

### 标题、描述和规范网址 {#title-description-canonical}

- **恰好一个 `<title>`**，内容为*解析后的*标题，不得重复追加后缀。解析器仅追加一次 `seo.title_suffix`，并检查标题是否已以该后缀结尾。
- **一个元描述**，仅在解析出描述时输出，不生成空标签。
- **一个 `<link rel="canonical">`**。

### Robots {#robots}

- **仅当指令不同于网站默认值时**输出 `<meta name="robots">`。多余的 `index,follow` 只会增加噪声；*没有*该标签时，爬虫本来就按 `index,follow` 处理。比较时忽略空白差异，`index, follow` ≡ `index,follow`；与默认值不同的指令则**原样输出**。`seo.robots.emit_default = true` 会强制输出标签。
- 支持结果确定的**高级指令**：`noindex`、`nofollow`、`noarchive`、`nosnippet`、`max-snippet`、`max-image-preview`、`max-video-preview`、`notranslate`、`unavailable_after`。它们是解析后的字符串值，**优先级由解析器链决定**，即全局 → 路由 → 模型 → 显式值。输入相同，输出就相同。

### Open Graph {#open-graph}

- `og:title`、`og:description`、`og:type`、`og:url`、`og:site_name`、`og:locale`。
- `article:*`，包括 `published_time`、`modified_time`、`author`、`section`、`tag`，**仅在 `og:type === 'article'` 且值真实存在时**输出；绝不编造，也不在非文章页面上输出。
- `og:image` 附带**已知的** `og:image:width` / `og:image:height` / `og:image:alt` 和 `og:image:type`。多张图片需要**分组**：每个 `og:image` 后面立即跟随该图片自己的尺寸、alt 和类型属性。

### Twitter Cards {#twitter-cards}

- `twitter:card`、`twitter:title`、`twitter:description`、`twitter:image`，以及已知图片替代文本时的 `twitter:image:alt`。
- `twitter:site` 和 `twitter:creator` **可选且相互独立**，可以只有其中一个，也不能从其中一个编造另一个。

### hreflang 与语言 {#hreflang-locale}

- Hreflang 通过模型的 `getSEOAlternates()` 钩子获得正式的解析器支持。
- 有 hreflang 替代版本时，网址必须**绝对、规范化，并按语言唯一**；数据完整时应双向关联。只有配置了 `x-default` 才输出它。
- `og:locale:alternate` **仅**反映真实存在社交版本的语言。将 `en-US` 映射为 `en_US`，按映射后的形式比较，不要求字面完全相等。
- `<html lang>` 与解析后的语言保持一致。虽然 `<html>` 元素由*应用*输出，这一条仍属于约定。

### 逐页 JSON-LD {#per-page-json-ld}

- 可以解析，并对 `</script>` 安全。数据载荷使用 `JSON_HEX_TAG` 编码，防止任何值提前终止 script 元素，这是存储型 XSS 防护。
- **多个 `<script>` 块或一个合并的 `@graph`** 都可以接受。
- **仅在实体实际相互关联时**使用稳定的 `@id`，例如 Organization ↔ WebSite ↔ WebPage；独立节点*不强制要求*稳定的 `@id`。

---

## 2. 规范化与不变量 {#_2-normalization-invariants}

- `canonical`、`og:url`、`og:image`、`twitter:image` 使用**绝对 `http(s)` 网址**。**空值或 null 标签不得进入 DOM**。
- **`canonical` 与 `og:url` 必须解析为相同的规范化网址。** 不一致属于**硬性失败**，不是警告。
- **规范网址的规范化策略在所有输出中保持一致**：协议、主机、端口、路径大小写、查询参数允许列表和尾斜杠，每次都以相同方式处理。允许索引的页面**引用自身**；`noindex` 页面**不继承**其他页面的规范网址策略。
- **按输出上下文转义**：HTML 属性、文本和 JSON 分别使用正确编码器。断言比较的是**解码后的语义值，不是原始字节**。
- **不同渲染器之间要求语义一致，不要求逐字节一致。** `render()`（HTML）≡ `toArray()` ≡ `toInertiaHead()`，在*规范化后*成立；三种表示形式的标签顺序和形式可以合理不同。单实例与可重复属性的规则必须明确，例如一个 `og:title`，多个 `article:tag`。
- **标签归属**：客户端渲染器替换*包拥有的*标签，通过键识别，见第 4 节，不删除无关的应用自有标签。

---

## 3. 行为：客户端导航 {#_3-behaviour-—-client-side-navigation}

每次 Inertia 访问或 Livewire `wire:navigate` 后：

- **每种单实例标签恰好一个**，包括 `<title>`、描述、规范链接及各个 `og:*`/`twitter:*`，**没有陈旧值**。
- **JSON-LD 不会累积**。移除前一个页面的结构化数据，而不是继续叠加。Livewire 将 `<script>` 视为不可移除资源，因此结构化数据脚本带有 `data-seo-schema` 和逐网址 ID，并在 `livewire:navigated` 时移除前页脚本，详见 Livewire 指南。
- 从**元数据丰富的页面导航到简单页面时，移除多余标签**，后者不保留前者的描述、OG 或结构化数据。
- **没有水合警告**，并且水合前后元数据语义一致。

---

## 4. Inertia head-key：标签归属 {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` 为每个 meta/link 条目添加稳定的 **`head-key`**。Inertia 按这个属性对 head 元素去重：页面中的 `<Head>` 标签如果与布局标签使用相同的 `head-key`，会*替换*它，而不是叠加一个重复项。

- 基础键：meta 使用 `name ?? property`，link 使用 `rel`。
- **为可重复标签消除歧义**，确保每个键仍然唯一：`article:tag` → `article:tag`、`article:tag:1` 等；hreflang → `alternate:en-US`、`alternate:fr-FR`。

在模板中绑定为 **`:head-key`**，*不要*使用 Vue 的 `:key`。后者是用于 `v-for` 协调的另一个键，对 Inertia 的 head 去重不起作用。

---

## 5. 爬虫可见性：明确区分模式 {#_5-crawler-visibility-explicit-modes}

- **SSR / 预渲染**必须在**原始 HTTP HTML** 中输出完整约定要求的内容。这项测试与水合后 DOM 的测试分开进行，并禁用 JavaScript。
- **仅 CSR 不能声称满足爬虫要求。** 默认不使用 SSR 的 Inertia 在*客户端*注入元数据，爬虫取得的初始 HTML 没有 SEO 元数据。必须公开说明这个限制：**爬虫可见的元数据需要 Inertia SSR 或预渲染**，供爬虫读取的 JSON-LD 也应在服务器端渲染。

---

## 6. 范围之外与非目标 {#_6-out-of-scope-non-goals}

- **属于应用而非渲染器的职责**：`charset`、`viewport`、favicon。注意，`<meta charset>` 必须先于任何非 ASCII 元数据，因此这些 head 元素的顺序由应用负责。
- **端到端测试只断言输出内容。** 它**不**断言 Google 是否索引、选择哪个规范版本、是否符合富媒体搜索结果资格或排名，也**不**断言远程图片的 MIME 类型或可用性。这些属于可选集成或 HTTP 测试，不属于浏览器矩阵。

---

## 7. 符合情况 {#_7-conformance-status}

下面说明目前每条约定由什么证据支持。**单元测试**指 `RenderingContractTest`，位于核心和包 CI 中。**浏览器/SSR**指 `rankbeam-examples`，通过定时矩阵运行。**应用**表示由宿主应用负责。**计划中**表示已作为目标写入约定，但 `SEOData` 尚未建模相应数据，因此渲染器只输出安全的子集。

| 条款 | 状态 |
|---|---|
| 恰好一个解析后的 `<title>`，无重复后缀 | **单元测试** + 浏览器 |
| 仅在描述存在时输出元描述 | **单元测试** + 浏览器 |
| 一个 `<link rel="canonical">`，永不为空 | **单元测试** + 浏览器 |
| Robots 仅在不同于默认值时原样输出；`emit_default` 开关 | **单元测试** + 浏览器 |
| 高级 robots 指令遵循解析器优先级 | **单元测试**，解析器 |
| `og:title/description/type/url/site_name/locale`；语言 `en-US`→`en_US` | **单元测试** + 浏览器 |
| `article:*` 仅在 `og:type=article` 且值真实时输出 | **单元测试** + 浏览器 |
| `og:image` 存在且为绝对网址 | **单元测试** + 浏览器 |
| `og:image:width/height/alt`、`og:image:type`、多图片分组 | **计划中**：`SEOData` 仅携带单个 `ogImage` 字符串，尚未建模尺寸、alt 或类型。渲染器输出一个绝对 `og:image`。 |
| `twitter:card/title/description/image`；`site`/`creator` 相互独立 | **单元测试** + 浏览器 |
| `twitter:image:alt` | **计划中**：尚未建模图片 alt 字段。 |
| hreflang 为绝对网址，并按语言唯一 | **单元测试** + 浏览器 |
| hreflang 双向关联，以及配置时输出 `x-default` | 浏览器，取决于数据 |
| `og:locale:alternate` 反映真实社交版本 | **计划中**：尚未建模逐语言社交版本映射。 |
| `<html lang>` 一致性 | **应用**，浏览器也对其断言 |
| JSON-LD 可解析并对 `</script>` 安全 | **单元测试** + 浏览器 |
| 多脚本或 `@graph`；实体关联处使用稳定 `@id` | **单元测试**，Merchant 数据图 + 浏览器 |
| 绝对网址；没有空或 null 标签 | **单元测试** + 浏览器 |
| `canonical` ≡ `og:url`，不一致时硬性失败 | **单元测试** + 浏览器 |
| 一致的规范网址规范化、自引用、noindex 隔离 | 浏览器 |
| 按上下文转义；解码后的语义一致 | **单元测试** |
| 不同渲染器语义一致，`render()` ≡ `toArray()` ≡ `toInertiaHead()` | **单元测试** |
| Inertia `head-key` 稳定，可重复项无歧义 | **单元测试** + 浏览器 |
| 客户端导航：单实例、无陈旧值、JSON-LD 不累积、清除多余标签 | 浏览器；渲染器提供清理所需的 `data-seo-schema` 钩子 |
| 没有水合警告；水合前后一致 | 浏览器 |
| SSR 在原始 HTML 中输出完整约定内容；文档明确仅 CSR 不符合要求 | 浏览器 + 文档 |

**计划中的条款**是有意记录、明确说明的缺口。约定是长期目标，这些内容作为未来任务中的新增、向后兼容扩展，需要新的 `SEOData` 字段或数据列，属于 SemVer 次版本变更。当前渲染器输出安全子集，绝不编造没有的数据。

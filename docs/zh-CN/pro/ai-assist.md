---
description: "可选的自带密钥 AI 辅助：标题和元数据建议、通俗的扫描问题解释、一键改写与 schema.org 建议。默认关闭。"
---

# AI 辅助 {#ai-assist}

可选的**自带密钥** AI 辅助功能，包括标题和元描述建议、用通俗语言解释扫描问题、一键**改写描述**，以及**结构化数据（schema.org）建议**。它**默认关闭**；关闭开关时，任何 AI 代码路径都不会运行。

设计遵循三点：

- **你的密钥，你的提供商。** 请求从*你的服务器*直接发送到*你*配置的提供商：Anthropic、OpenAI、Google，或本地 / OpenAI 兼容服务器。适用时费用计入你的账户。包不代理、不按用量计费，也不转售服务，不向任何地方发送遥测数据。
- **交互式建议必须明确接受。** 选择建议会填入表单；仪表盘修复需要点击应用（Apply）。从 Pro 2.42 起，批量 CLI 默认保存私有草稿。只有明确希望立即写入时才使用 `--auto-apply`。
- **失败始终不影响主要功能。** 缺少密钥、密钥无效、账户额度耗尽、速率限制或超时只会产生内联消息，绝不会阻止保存、渲染或扫描。

## 提供商概览 {#providers-at-a-glance}

按账户访问权限、数据要求和成本选择。四种集成都提供相同任务，但模型支持、输出格式、速度和质量可能不同。

| 提供商 | 内置默认模型 | 结构化输出 | 示例成本 | 适用情况 |
|---|---|---|---|---|
| **Local**（Ollama / LM Studio / vLLM） | `llama3.1`（自行设置） | 尽力而为（`response_format`） | 自托管推理的 **API 费用为 $0**，仍有基础设施成本 | 控制数据发送目的地 |
| **OpenAI** | `gpt-5.5` | 所选模型支持时使用 Structured Outputs | 按下方示例假设，每次建议约 $0.005 | 已有 OpenAI 账户 |
| **Anthropic** | `claude-opus-4-8` | 支持时使用 `output_config.format` | 按相同假设，每次建议约 $0.015 | 已有 Anthropic 账户 |
| **Google** | `gemini-2.5-flash` | 支持时使用 `responseSchema` | 按相同假设，每次建议约 $0.0005 | 有 Google 账户；请核实模型配额和价格 |

这些名称描述的是内置配置，并不保证你的账户当前可用。成本采用包中的示例假设，不是已核实的当前价格。以下是集成行为及已发表试验中的观察：

- **结构化输出。** 受支持的 OpenAI、Google 和 Anthropic 路径会收到 JSON schema；无效响应会明确失败。本地服务器收到 `response_format`，以尽力而为的方式处理。如果服务器忽略它，容错解析只会返回有效列表或失败，不会应用不完整输出。
- **推理会改变 token 消耗。** 所述 Gemini 试验生成一条描述时，使用了约 500 个隐藏推理 token 和约 100 个可见 token；测试中的 Anthropic 调用报告没有隐藏推理 token。这不是这些模型系列的普遍属性。隐藏 token 可能按输出计费，这也是下文设置推理预算下限的原因。
- **模型可配置。** 将 `SEO_PRO_AI_MODEL` 设置为可用且兼容适配器 API 与参数的模型。示例包括 `claude-haiku-4-5`、`gpt-5.4-mini` 和 `gemma-3-12b-it`；在整个内容集合中使用前，请验证支持情况和输出质量。

## 设置 {#setup}

启用功能，并将提供商密钥放入环境变量。切换云提供商时，更新提供商及密钥，同时检查模型覆盖设置。本地适配器还需要服务器 URL。

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip 提供商 API 密钥与 Claude / ChatGPT 订阅分开
Claude Code、Claude.ai 或 ChatGPT **订阅**并不支付 **API** 费用。`SEO_PRO_AI_API_KEY` 必须是来自提供商开发者控制台的*按用量付费 API 密钥*（或 Google AI Studio 密钥），有独立的余额。仅有订阅或没有充值的账户可以通过认证，但会返回**余额 / 配额不足**错误，参见[故障排查](#troubleshooting)。
:::

配置文件（`config/seo-pro.php` 的 `ai` 块）提供 `timeout`、`max_input_chars`、`max_output_tokens`、`token_budgets`、`reasoning_models` + `reasoning_min_output_tokens`、`suggestion_count`、`bulk_model`（批量填充的低成本档位，参见[成本](#cheaper-bulk-generation)）、`retry`、`pricing` 表和 `local` 子块，均在[限制与调优](#limits-and-tuning)中说明。

::: warning 配置缓存时的密钥处理
配置只存储环境变量的**名称**（`api_key_env`），绝不存储密钥，因此 `php artisan config:cache` 绝不会将密钥写入 `bootstrap/cache/config.php`。另一方面，使用缓存配置时不会加载 `.env`，因此需要在服务器上将 `SEO_PRO_AI_API_KEY` 设置为真正的环境变量。
:::

## 本地推理与云端选项 {#running-at-0-and-the-cheapest-paid-option}

- **自托管推理免去提供商按 token 收取的 API 费用。** 硬件、电力和运维仍有成本。只有配置的推理服务器及其依赖都位于你的网络内，内容才会留在该网络中。
- **Google 的配额和价格因模型及档位而异。** AI Studio 密钥（`aistudio.google.com/apikey`，格式为 `AIza…`）可能允许免费档试用；按需开启计费前，请检查其限制是否适合工作负载。`gemini-2.5-flash` 是内置默认值。Gemini 和 Gemma 模型并非都具有相同的思考能力：`reasoning_models` 应用配置的名称模式，不执行能力检测。

如需控制推理运行位置：

- **本地 / OpenAI 兼容。** 将 `provider=local` 与兼容 OpenAI Chat Completions 的服务器一起使用，例如 **Ollama**、**LM Studio**、**vLLM** 或 **LocalAI**，也可使用 **OpenRouter** 等远程网关。将 `SEO_PRO_AI_LOCAL_BASE_URL` 设为其 API 根地址（会追加 `/chat/completions`），并选择可用的 `SEO_PRO_AI_MODEL`。远程网关会在你的网络之外接收数据，也可能收费；适配器名称 `local` 不代表推理一定在本地运行。

::: warning 本地 `base_url` 会验证——localhost 需要主动启用
`base_url` 是特权设置，会通过其他所有出站抓取也使用的 `SsrfGuard` 验证：只允许 http/https，不允许 userinfo，默认必须解析到**公网**地址，避免错误或恶意的 `base_url` 被用于探测内部服务。真正的本地服务器位于 `127.0.0.1`（私有地址），因此本地提供商需要显式启用 `seo-pro.ai.local.allow_local_addresses`（`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`）。公网网关（OpenRouter）应保持该选项关闭。请求路径固定且绝不跟随重定向，因此密钥不会因跳转发送给其他主机。
:::

::: tip 控制受支持 Ollama 模型的思考
本地思考模型可能超过默认超时。如果模型及服务器版本支持，可在 `seo-pro.ai.local.extra_body` 中用 `['think' => false]` 为建议关闭思考。支持情况不一，请查看 [Ollama 文档](https://docs.ollama.com/capabilities/thinking)。必要时提高 `seo-pro.ai.timeout`。包不会发送 `temperature`，因为某些模型会拒绝它。
:::

## 成本 {#cost}

包不加价，你直接向提供商付费。自托管推理没有提供商 API 费用，但仍有基础设施成本。需要关注两个数字：交互使用的**每次建议**成本，以及整个集合的**批量填充**成本。

`seo-pro.ai.pricing` 表（每 1,000,000 token 的美元价格）将 token 估计转为批量填充确认提示中的美元金额。这些是**内置的估算假设**，不是已核实的当前公开价格。要获得准确估计，请**用提供商当前公布的价格覆盖它们**：

| 模型模式 | 输入 $/1M | 输出 $/1M |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

已发表的示例将一个测试页面的 token 消耗（一条标题和一条描述）代入这些假设。最后一列对受支持的适配器应用示例中的 50% 批处理折扣。这些不是当前价格报价。

| 提供商 / 模型 | 每对建议约需 | 每 1,000 条记录约需（批量填充） | 每 1,000 条记录约需（`--batch`） |
|---|---|---|---|
| Local `gemma`/`llama`（Ollama） | **API 费用 $0** | **API 费用 $0** | 不适用（适配器未实现） |
| Google `gemini-2.5-flash`（付费） | 约 $0.001 | 约 $0.40 | 不适用（Rankbeam 适配器未实现） |
| OpenAI `gpt-5.5` | 约 $0.008 | 约 $5.25 | **约 $2.63**（减免 50%） |
| Anthropic `claude-opus-4-8` | 约 $0.03 | 约 $20 | **约 $10**（减免 50%） |

命令将估计标注为约 ±50%，但**这既不是支出上限，也不是保证的误差范围**。实际输入、输出及价格都会改变总额。估算模型只考虑可见输出，计费的隐藏推理可能进一步增加成本。没有价格条目的模型只显示 token 估计。

### 更低成本的批量生成 {#cheaper-bulk-generation}

设置 `seo-pro.ai.bulk_model`（`SEO_PRO_AI_BULK_MODEL`）可**只为批量填充**（`seo-pro:ai-fill` / `SeoPro::aiFill()`）使用不同模型。Filament 和 `seo-pro:ai-suggest` 继续使用 `model`。该值为 null 时，批量填充也使用 `model`。估计采用所选模型的价格模式。扩大数量前，请评估有代表性的输出；更便宜不代表自动适用。

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

包内示例使用 **anthropic** `claude-haiku-4-5`、**openai** `gpt-5.5-mini`、**google** `gemini-2.5-flash`，或较小的 **local** 模型。即使提供商没有某个模型，价格模式也可能匹配该名称；配置前请确认实际模型 ID、API 兼容性和价格。

一次 **100 页填充**，每页均缺标题和描述，也就是 200 次提供商调用，按内置 `pricing` 默认值及估算器的单次调用 token 模型（每次 600 输入 + 150 输出 token）计算，质量档模型与其 `bulk_model` 低成本档对比如下：

| 提供商 | 质量档模型——100 页 | `bulk_model` 低成本档——100 页 |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **$4.05** | `claude-haiku-4-5` ≈ **$0.27** |
| **OpenAI** | `gpt-5.5` ≈ **$1.05** | `gpt-5.5-mini` ≈ **$0.11** |
| **Google** | `gemini-2.5-pro` ≈ **$0.45** | `gemini-2.5-flash` ≈ **$0.04** |
| **Local**（Ollama / vLLM） | 任意模型，**API 费用 $0** | 任意模型，**API 费用 $0** |

这些只是示例估计，不保证 ±50% 范围，也未预留隐藏推理费用。依赖估计之前，请用所选提供商公布的价格更新 `seo-pro.ai.pricing`。

## 输出语言 {#output-language}

每个提示都会指明页面语言和 BCP-47 代码，例如*“使用页面语言巴西葡萄牙语（pt-BR），无论摘录中是否出现其他语言”*；发送给模型的页面上下文也包含 `Language:` 行（Pro 2.34）。此前提示只说“与源内容使用相同语言”，让模型从简短或混有代码的摘录中猜测：土耳其语页面的摘录若包含英语品牌名，可能返回英语。所用语言区域与页面元数据解析时一致（页面未指定时使用应用语言区域），也与选择[长度预算](/zh-CN/guide/multilingual#title-and-description-budgets-per-script)时一致，因此日语页面会要求*用日语*生成约 30 个字符的标题。

从 Pro 2.36 起，显式内容语言区域同时控制元数据行、内容钩子和提示语言。操作人员的界面语言保持不变。

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

现有位置参数不变。省略 `locale:` 时，使用模型的 `seoData()` 默认值，让独立翻译模型可以声明自身语言。摘录优先使用 `getContentForSEO()` 返回的非空内容，然后回退到配置的内容字段。Filament 1.11 自动传递所选选项卡的语言区域，也包含单语言区域及页面切换器模式。

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

批量 `plan()`、`fill()` 和 `submitBatchFill()` 也接受末尾的 `locale:`。创建 `FillProgress(..., locale: 'it')` 和提交运行时，请使用相同语言区域。每个批处理项都记录其内容语言区域；收集结果时使用该已保存值，并在写入前重新检查对应元数据行。显式指定语言区域的运行有独立检查点文件，已处理标记也区分语言。重新运行相同命令即可收集结果。自定义任务应序列化并传递内容语言区域。

Pro 2.36 之前创建的检查点没有记录内容语言区域。尚未完成的旧批次会保留，但拒绝自动收集。通过 `--fresh` 丢弃前，请先核对提供商结果和预期语言区域，否则重新提交可能让同一工作再次计费。包含已处理记录的旧顺序检查点，同样需要在重置前核对。

### 按语言评估 {#per-language-evaluation}

Pro 源码仓库包含覆盖 17 个语言区域的 170 个输入页面，以及需主动启用的评估工具。输入页面经过结构及基础语言启发式检查，独立母语审核仍待完成。

评估工具**同时检查标题和描述**，记录字素长度及语言 / 文字系统证据，并在断言前保存每个提供商响应。短标题、混合文本及中日共享汉字仍可能无法确定。判断基础语言为葡萄牙语，并不能证明符合巴西用法；部分汉字检查也不能认证地区写作质量。

实际运行需要 `SEO_PRO_AI_EVAL=1`、显式的 `SEO_PRO_AI_EVAL_LOCALES` 选择，以及 `SEO_PRO_AI_EVAL_RUN` ID，可能产生提供商费用，默认不会运行。每次运行的证据与提供商、请求 / 返回模型、样例 / 请求 / 代码哈希及时间戳绑定。失败尝试仍会保留。恢复时复用已保存响应；中断的请求需要显式重试，因为它可能已经到达提供商。

证据存储在源码测试环境的 `storage/app/seo-ai-evals/<run-id>/` 下。样例中的 `README.md` 记录了有版本的格式和命令。母语审核者在独立审核记录中对确切输出哈希评分。自动检查通过并不等于母语认可，也不保证文案可直接发布。

## 限制与调优 {#limits-and-tuning}

所有调节项都位于 `config/seo-pro.php` 的 `ai` 块中：

- **`timeout`**（默认 `15` 秒，环境变量 `SEO_PRO_AI_TIMEOUT`）：也是 Filament 建议弹窗打开时同步调用的上限，为用户体验而保持较短。**较慢的推理或本地模型可能超过 15 秒**并超时；使用此类模型时，可通过 `SEO_PRO_AI_TIMEOUT` 提高上限，并参阅上方 Ollama `think => false` 提示。超时始终不会影响主要功能，只产生内联错误，绝不阻止保存。
- **`max_input_chars`**（默认 `6000`）：限制每次请求发送的页面内容量（去除 HTML 的纯文本），控制成本与隐私暴露范围。
- **`max_output_tokens`**（默认 `1000`）：生成 token 的基础上限。达到上限的响应会返回独立的 `truncated` 失败，绝不会悄悄返回半个答案。
- **`token_budgets`**：按任务设置输出上限（`suggestions` 800、`explanation` 600、`rewrite` 300、`schema_suggestion` 700）。这些任务都不需要完整默认预算，但之后仍会应用推理下限。
- **`reasoning_models`** + **`reasoning_min_output_tokens`**（默认 `2000`）：名称匹配模式（`*gemma*`、`gemini-2.5-*`、`o1*`/`o3*`/`o4*`）的模型会将输出预算提高到此下限，因为思考模型会先消耗隐藏 token，再产生可见输出，较小预算会导致截断。
- **`suggestion_count`**（默认 `3`）：请求多少个标题 / 描述备选项。
- **`retry`**：只对*暂时性*失败自动重试，参见[响应处理方式](#how-replies-are-handled)。

## 在 Filament 中使用 {#in-filament}

安装可选 Filament 包（`rankbeam/laravel-seo-filament` >= 1.1）后，启用 AI 辅助会添加：

- **AI 建议（Suggest with AI）**：出现在使用 SEO 区块的所有资源的 SEO 标题和描述字段上（编辑页面）。弹窗展示生成的备选项和字符数，选择一项后填入字段，供复核。
- **解释（AI）**：出现在仪表盘问题表格中，用通俗语言简短解释问题及具体修复方式。
- **改写描述（AI）**：出现在仪表盘问题表格中，位于解释操作旁边，提出一条改进的元描述，始终处于页面的描述预算内（拉丁文字 160 个字符，中日韩文字约 80，来自核心包的[长度策略](/zh-CN/guide/multilingual#title-and-description-budgets-per-script)）。在弹窗中复核后，点击 **应用改写** 才会写入页面的 `seo_meta` 记录。应用之前不写入任何内容。
- **建议结构化数据（AI）**：出现在仪表盘问题表格中，建议最合适的 schema.org 富媒体搜索结果类型（Product、Article 或 Breadcrumb），并显示为其构建的 JSON-LD。点击 **应用结构化数据** 后，将其加入页面的 `seo_meta.schema_jsonld`。这与可选的[结构化数据编辑器](/zh-CN/guide/filament#structured-data-schema-org)管理的列相同，因此可以往返编辑，并继续在那里修改。不完整建议（例如缺少作者或图片的 Article）会显示缺失字段，**不会**应用。

最后两项属于范围受限的修复，参见[范围受限的修复](#bounded-fixes-propose-never-auto-apply)。

## 范围受限的修复（只提议，绝不自动应用） {#bounded-fixes-propose-never-auto-apply}

两项辅助操作比普通建议更进一步：生成一个可一键应用、*受约束*的值。但两者仍然**只提出建议**，明确接受前不持久化任何内容。

- **改写描述**（`SeoSuggestionService::rewriteDescription($model, $issue?)`）返回一条元描述，**始终符合核心长度策略针对页面文字系统的预算（拉丁文字 160，中日韩文字约 80）**。标题和描述建议提示也使用相同预算，按页面自身解析后的值选择。如果模型超出预算，文本会按确定性规则先在句子边界、再在词边界裁剪，所以被接受的改写本身绝不会触发 `description_too_long` 警告。传入扫描问题可以引导改写，例如区分*过长*与*缺失*。
- **建议结构化数据**（`SeoSuggestionService::suggestSchemaType($model)`）只向模型请求**类型建议和叶子字段值**，绝不请求原始 JSON-LD。随后，确定性代码使用核心 schema 构建器（`ProductSchema` / `ArticleSchema` / `BreadcrumbSchema`）组装文档，并通过核心 `SchemaValidator` 验证，因此虚构的 `@type`、`@context` 或结构不会进入页面。如果组装后的文档缺少必填字段，会标为*不完整*并停止应用。在一次实际测试中，一个提供商为内容较少的页面建议了 `Article`，由于缺少作者和图片被正确阻止应用；其他提供商则拒绝建议类型。

## 无界面使用 {#headless}

通过 JSON 提供相同能力，适用于脚本和非 Filament 应用：

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

输出包含建议（或推荐类型、构建的 JSON-LD 及其是否通过验证）、所用模型，以及**每次请求的 token 用量**（输入 / 输出 / 推理），便于按提供商费率计算成本；token 计数不是账单。任何失败都会让命令以非零状态退出，并在 JSON 包装结构中包含错误。和所有辅助入口一样，`seo-pro:suggest-schema` **只提出建议**，打印文档，不写入任何内容。

## 批量填充缺失元数据 {#bulk-fill-missing-metadata}

**Pro 2.42 改变了 CLI 默认行为：**`seo-pro:ai-fill` 会为每个生成字段保存一份私有草稿。批准之前，已发布的 SEO 元数据保持不变。现有值和计算所得回退值会跳过。已有仍然有效的待审核草稿时会复用，不会再次生成。

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review` 以 JSON 列出前 100 份待审核草稿；可检查某个 ID，读取其值和私有证据。批准和拒绝在 AI 关闭时仍可执行，不调用提供商。批准需要操作人员标签，并拒绝源记录或目标元数据已改变、或记录已被删除的草稿。该标签记录操作人员自报的身份，不证明进行了实质性人工审核。使用配置的非默认数据库时，请使用 `--connection=NAME`。

`--auto-apply` 显式恢复对仍缺失字段的立即发布。`--force` 跳过确认，**并非跳过审核**。`--dry-run` 生成并打印值，不保存草稿或元数据，但仍会调用提供商，也可能收费。`--field`、`--limit` 和 `--locale` 限定生成范围。升级后，请有意识地更新计划运行的命令。

### 大规模使用：节奏控制、成本估计与中断恢复 {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms` 默认为 200 毫秒。达到 `confirm_over`（默认 100 条记录）时，命令显示来自 `seo-pro.ai.pricing` 的估计，并在生成前询问。检查点会在中断后保留已完成字段。提供商接受请求后发生的超时仍可能造成重复计费；执行 `--fresh` 前，请先核对结果不明的工作。同一匹配范围一次只运行一个批量任务。

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

自定义集成可传入 `review: true` 来暂存草稿。低层 PHP API 为兼容性保留 `apply: true, review: false`，所以现有调用仍会立即写入。`apply: false` 预览但不持久化。摘要键 `filled` 统计已处理记录，包括审核模式下已暂存的记录；CLI 将其标注为 `staged`。

### 批处理模式（便宜 50%） {#batch-mode-50-cheaper}

`--batch` 使用受支持的 Anthropic 或 OpenAI 异步端点；估计反映其文档中的折扣，但请核实当前模型价格。Google 和本地适配器回退为顺序生成。现在提交，稍后重新运行相同命令收集草稿：

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

提交和收集之间，请保持提供商、语言区域和发布模式不变。审核与 `--auto-apply` 使用独立检查点；存在匹配的未完成批次时，CLI 会拒绝启动另一种模式。结果不确定的提交会停止，等待核对。部分成功结果会保留，暂时性失败可重试。收集时重新检查字段是否缺失；批准时还检查提交前保存的源快照。`seo-pro.ai.fill.batch.request_timeout` 默认为 120 秒。计划任务中的收集默认保存草稿。

## 来源记录、迁移与数据过滤 {#origin-review-and-filtering}

需要 **Core 3.21 和 Pro 2.42**。核心包自动加载其迁移；生成要保存的建议之前，请发布 Pro 迁移，并迁移 SEO 模型使用的每个数据库：

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

私有 `seo_ai_proposals` 表通过 Laravel 加密类型转换，存储生成值及提供商 / 模型 / 请求证据。请妥善保护 `APP_KEY` 及其备份，丢失后将无法读取这些值。记录标识、状态和决定元数据仍是普通数据库列。表单被放弃时，备选建议可能保持为 `offered` 或 `selected`。没有自动清理机制：请制定应用保留策略，保留待审核草稿及仍由 `seo_meta.ai_provenance` 引用的证据，并限制数据库导出和审核命令输出的访问权限。

已接受的表单建议、仪表盘修复和批量值会携带字段来源。后续 Eloquent 编辑保留 `origin: ai` 并设置 `edited: true`，这表示值已改变，并不代表有人验证过它。清空字段会移除其标记。核心包的 HTML、数组、JSON 和 Inertia 输出只暴露字段名、来源和已编辑状态，适用时使用自定义 `rankbeam:ai-origin` 元标签。生成 ID 和提供商详情保持私有。不要在公开 API 中暴露原始 `SEOMeta` 模型。

此机制记录之后通过受支持保存路径产生的操作，不追溯历史内容，也不记录每个编辑版本。直接 SQL、查询构建器更新和自定义渲染器可能绕过这些控制。如果独立创作的替代内容确实应重置来源，请显式重置；普通编辑会保留来源。该自定义标记不是标准化水印、不可篡改归属证明，也不构成符合第 50 条规定的声明。实际提供商输出质量及提供商原生标记需要单独评估。

可选地实现 `AiPromptFilter`，并配置 `seo-pro.ai.context_filter`。它在同步或批量提交前过滤已组装的用户提示；失败时阻止提交，并返回脱敏错误。系统指令不变。默认值是 `null`，**不会自动遮蔽敏感数据**。下面的示例只替换一个已知值，请实现并测试适合应用的规则：

```php
namespace App\Support;

use Rankbeam\Seo\Pro\Ai\AiPromptFilter;

final class RedactAiContext implements AiPromptFilter
{
    public function filter(string $prompt): string
    {
        return str_replace('internal@example.com', '[redacted]', $prompt);
    }
}

// Configure seo-pro.ai.context_filter with this class in config/seo-pro.php.
// Runtime equivalent:
config(['seo-pro.ai.context_filter' => RedactAiContext::class]);
```

## 响应处理方式 {#how-replies-are-handled}

每次调用都返回统一、不依赖提供商的包装结构，因此不同提供商及之后新增的提供商行为一致：

- **提供商支持时使用结构化输出。** OpenAI（原生 Structured Outputs）、Google（Gemini `responseSchema`）和 Anthropic（`output_config.format`）都由 API 约束 JSON 结构。不是有效 JSON 的响应会明确失败，绝不从文本中强行提取。本地 / OpenAI 兼容服务器也会收到此请求（`response_format`），但只是尽力而为；忽略该字段的服务器仍可能返回可用文本，再用容错解析作为回退。无论哪条路径，都只会得到完整列表或明确失败，绝不会得到解析了一半的响应。
- **截断是明确、可采取行动的错误。** 如果响应因输出 token 上限而被截断，会返回 `truncated` 错误并提示提高 `seo-pro.ai.max_output_tokens`，不会悄悄给出缩短的标题。这最常见于**推理 / 思考模型**；这些模型会自动获得更高的 `reasoning_min_output_tokens` 下限。
- **暂时性失败会自动重试。** `429` 速率限制或 `5xx` 会按有上限的指数退避重试；存在 `Retry-After` 响应头时会遵循，但仍有边界，避免恶意值使请求无限停滞。**确定性**失败**不会**重试，包括密钥错误、请求格式错误、载荷过大、**超时**或账户**余额 / 配额不足**（重试未充值账户只会浪费退避等待）。通过 `retry` 块调整或禁用重试；将 `max_attempts` 设为 `0` 即可关闭。
- **错误有类型并经过脱敏。** 每次失败都有稳定代码（`unauthorized`、`quota_exceeded`、`rate_limited`、`timeout`、`content_too_large`、`bad_request`、`truncated`、`content_filtered`、`provider_error` 等），暂时性错误还带有 `retryable` 标记。消息是简短的脱敏字符串；**正常应用路径不会展示或记录原始提供商响应体，但上文主动启用的评估工具会保存响应作为证据**。Filament 中的失败会显示在有样式的弹窗局部视图中，并针对常见情况给出下一步提示，参见[故障排查](#troubleshooting)。

## 故障排查 {#troubleshooting}

所有失败都以内联方式显示，不影响主要功能，并带有类型化代码和脱敏消息。常见情况及相应修复如下：

| 现象（错误代码） | 含义 | 修复方式 |
|---|---|---|
| **`quota_exceeded`**——*“提供商账户余额或配额不足”* | 密钥有效，但 **API 账户没有余额 / 配额**。这不是速率限制，重试没有帮助。不同提供商表述不同：Anthropic 返回 *“credit balance is too low”*，OpenAI 返回 *“exceeded your current quota… check your plan and billing”*（`insufficient_quota`），Google 返回 *“prepayment credits are depleted”*。 | 在提供商控制台充值 / 启用计费，或切换到没有提供商 API 费用的**本地**模型。请记住 Claude/ChatGPT **订阅**不支付 **API** 费用。 |
| **`unauthorized`**——*“认证失败”* | 密钥缺失、错误，或不适用于配置的提供商。 | 检查由 `seo-pro.ai.api_key_env` 命名的环境变量（默认 `SEO_PRO_AI_API_KEY`）中的密钥：已设置、仍有效，并与 `SEO_PRO_AI_PROVIDER` 匹配。 |
| **`rate_limited`**——*“已达到提供商速率限制”* | 实际的**暂时性**速率限制，已先自动重试。 | 等待后重试，或改用**本地**模型（仍受服务器容量限制）以避免此类限制。低档位批量运行时提高 `seo-pro.ai.fill.throttle_ms`。 |
| **`timeout`**——*“请求超时”* | 提供商未在 `seo-pro.ai.timeout`（默认 15 秒）内响应，常见于**较慢的本地推理模型**。 | 通过 `SEO_PRO_AI_TIMEOUT` 提高上限；Ollama 还可在 `seo-pro.ai.local.extra_body` 中设置 `['think' => false]`。 |
| **`truncated`**——*“达到 max_output_tokens 限制”* | 响应达到输出预算，可能包含隐藏推理。 | 提高 `seo-pro.ai.max_output_tokens`（推理模型可能需要 2000 以上），或确认模型匹配 `reasoning_models` 模式，使下限生效。 |
| **`content_too_large`**（HTTP 413） | 发送的页面内容超过提供商限制。 | 降低 `seo-pro.ai.max_input_chars`，发送更短摘录。 |
| **`bad_request`** | 请求格式有误，通常是账户无法访问的**模型名称**或不支持的参数。 | 检查 `SEO_PRO_AI_MODEL` 是否为密钥 / 服务器在所配置提供商上可访问的模型。 |
| **`content_filtered`** | 提供商安全过滤器拒绝回答。 | 检查内容和提供商指南，不要自动重复被拒绝的请求。 |

::: tip 本地推理仍需要正常工作的服务器
用 `SEO_PRO_AI_PROVIDER=local` 连接自托管推理，可避免云提供商余额问题。硬件、模型、API 兼容性、超时和容量仍然重要。使用此适配器配置的远程网关可能需要密钥和付款。
:::

## 哪些内容会离开服务器 {#what-leaves-your-server}

只有明确操作（点击操作或调用命令）时，才会向配置的提供商发送以下确切内容：

- *建议*：模型类的短名称及键（例如“Post #3”）、当前解析后的标题和描述、规范网址，以及受 `max_input_chars` 限制的纯文本内容摘录（去除 HTML，默认 6000 个字符）。
- *问题解释*：问题类型、严重程度、字段、消息和目标 URL；有模型时，还包括受影响模型的类 / 键、解析后的标题 / 描述、规范网址及有长度限制的纯文本内容摘录。
- *描述改写*：与建议相同的最小页面上下文，以及传入时的扫描问题类型和消息。
- *结构化数据建议*：与建议相同的最小页面上下文。模型只返回类型和叶子字段值，JSON-LD 在本地组装。

包不会有意将访客数据、IP 地址、请求头或凭据收集到提示中，也不会发送完整 HTML。**你的内容字段和摘录本身可能包含敏感信息**，请检查应用暴露了什么。提供商凭据用于请求认证。数据处理以 Pro 仓库的 SECURITY.md 为参考。

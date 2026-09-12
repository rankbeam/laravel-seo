---
description: "感知关键词的页面检查清单：选定焦点关键词，即可获得标题、URL、首段、元数据、长度、图片和可读性的通过 / 警告 / 失败提示。"
---

# 页面检查清单——感知关键词，提示通过 / 警告 / 失败 {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

页面检查清单提供 RankMath 或 Yoast 用户熟悉的实时编辑反馈：选定焦点关键词，即可用信号灯式列表检查“此页面是否围绕它进行了优化”，包括标题、URL、首段和元描述中的关键词，以及长度、图片、内链和**可读性**。

它**在请求内**运行，不经过队列，也不使用网络，依据模型、[解析器](/zh-CN/concepts/resolver-precedence)和页面自身文案进行分析，并且有意**不提供数值评分**。

::: tip 检查清单不等于评分
清单**只提供通过 / 警告 / 失败**，与 [Pro SEO 评分](/zh-CN/pro/scoring)完全分开。它与评分规则不共享代码，也绝不会改变分数；编辑提示始终与数值评分规则独立。其中关键词密度和可读性尤其属于**建议项**，见下文。
:::

## 检查内容 {#what-it-checks}

| 检查 | 分组 | 检查内容 |
|---|---|---|
| `keyword_in_title` | keyword | SEO 标题中出现焦点关键词。 |
| `keyword_in_description` | keyword | 元描述中出现焦点关键词。 |
| `keyword_in_url` | keyword | URL slug 中出现焦点关键词。 |
| `keyword_in_first_paragraph` | keyword | 首段中出现焦点关键词。 |
| `keyword_density` | keyword | **建议项。** 重复密度是否读起来自然，没有目标值，见下文。 |
| `title_length` | meta | 标题处于与编辑器和扫描相同的区间：拉丁文字 30–60，中日韩文字约 15–30，来自核心包的[长度策略](/zh-CN/guide/multilingual#title-and-description-budgets-per-script)（Pro 2.33）。 |
| `description_length` | meta | 描述处于相应区间：拉丁文字 70–160，中日韩文字约 35–80。 |
| `content_length` | content | 正文足够充实，词数区间由配置决定。 |
| `readability` | content | **建议项。** 使用选定公式（十种语言）、LIX 回退，或针对日文、中文、韩文的明确标注且不计分的启发式方法，估计可读性水平。 |
| `has_image` | media | 内容至少包含一张图片。 |
| `internal_links` | links | 内容链接到相关的站内页面。 |

未设置焦点关键词时，关键词检查会**跳过**，既不通过也不失败，清单会提示添加关键词。可通过[焦点关键词字段](/zh-CN/guide/filament)或 `saveSEO(['focus_keywords' => …])` 添加。

### 关键词匹配 {#keyword-matching}

关键词和文案在**大小写折叠和词干提取**之后比较，因此“espresso grinder”仍能匹配“espresso grinders”。指定分析语言区域后，土耳其语“İstanbul”匹配“istanbul”，希腊语“ΟΔΟΣ”匹配“οδος”，德语“Straße”匹配“STRASSE”（核心包的 `CaseFolder`）。通过 `SeoPro::checklistFor($post, 'it')` 或 `--locale=it` 传入分析所用的语言区域。

从 Pro 2.36.1 起，关键词、同义词和字段文本在提取词干前使用同一个分词器。匹配要求连续的**完整词元**：`cat` 不匹配 `education`；日语短语采用与正文相同的 ICU 词边界。撇号和连字符会分隔词元，因此 `meta-tag` 匹配 `meta tag`，直撇号与弯撇号行为相同。组合附加符号仍附着在对应字母上。大小写折叠保留重音，某种语言的词干提取器可能进一步简化。

出现次数统计会在每个位置选择最长的匹配关键词 / 同义词，并将该范围只计一次。重复同义词和重叠的较短替代项不会虚增密度。例如，关键词 `seo` 配合同义词 `seo tools`，在 `seo tools seo` 中出现两次。没有词间空格的文字仍需要 ICU 提供词典词边界；正则回退无法提供这些边界。

从 Pro 2.37 起，词干提取使用**内置的 Snowball 3.1.1 子集**，无需额外 Composer 包，运行时也不会下载任何内容。仍支持 PHP 8.2。

| 引擎 | 使用条件 | 语言 |
| --- | --- | --- |
| `snowball` | 默认；现有 `auto` 设置选择同一个内置引擎 | en、it、de、fr、es、pt、nl、ru、tr、el、pl、cs |
| `builtin` | 显式设置 `seo-pro.checklist.analysis.stemmer = builtin` | 仅英语使用旧的轻量词形变化词干提取器；其他语言不提取词干 |
| `identity` | 不支持的语言，或显式的 `none` 模式 | 乌克兰语、日语、中文、韩语、泰语及其他不在内置子集中的语言 |

比较两侧使用相同的引擎。词干提取是后缀简化算法，不是同义词词典，也不保证语言学上的等价。例如，希腊语算法可以匹配带重音和不带重音的形式，而不提取词干的匹配会区分它们。完整词元边界仍会阻止 `cat` 匹配 `education`。

#### 从 Pro 2.36 升级 {#upgrading-from-pro-2-36}

现有 `auto` 配置现在统一使用内置算法，无论是否安装 `wamania/php-stemmer`。升级后请重新检查编辑建议：算法更新可能改变匹配结果，土耳其语、希腊语、波兰语和捷克语现在也支持词干提取。可选封装包额外提供的加泰罗尼亚语、丹麦语、芬兰语、挪威语、罗马尼亚语和瑞典语算法不在此子集中，现在使用不提取词干的匹配。

设置 `SEO_PRO_CHECKLIST_STEMMER=builtin` 可使用之前仅支持英语的回退方式；设置 `none` 则让所有语言仅做大小写折叠后直接匹配。修改后请重建缓存配置。这些控制无法复现旧可选封装包的多语言算法；要保留那些完全相同的结果，必须保留之前的 Pro 版本。已存储的 SEO 元数据不会被重写。

内置适配器在 PHP 8.2、8.3 和 8.4 上通过了 600,395 对固定的官方词汇 / 输出用例。这证明算法符合预期，并不代表母语编辑认可。包中附带源文件哈希、仅调整语法的 PHP 8.2 适配以及上游许可证，见源码分发中的 `THIRD-PARTY-NOTICES.md`。

### 分词 {#word-segmentation}

词数、关键词密度和可读性统计都需要词。对有词间空格的文字，正则表达式使用稳定的字母 / 数字词元边界。中文、日文和泰文需要词典分词，否则正则可能把整段看成一个“词”。加载 **ext-intl** 后，分词器把这些文本段交给 ICU 的词典分词迭代器（`IntlBreakIterator::createWordInstance`），将 東京タワーは東京のランドマークです 分成词。如果 ICU 缺失、禁用或无法初始化，Pro 会跳过受影响的内容长度、可读性和关键词检查，并提示安装 / 配置。它不会将不可靠的词数判为失败。无关检查，包括标题长度和有空格文字的匹配，仍会运行。`seo-pro.checklist.analysis.segmenter = regex` 会对需要词典分词的文本强制使用同样的不可用状态。

`analysis` 块包含 `word_count_status`（`available` 或 `unavailable`）和 `segmentation_reason`（`null`、`missing_intl`、`disabled` 或 `initialization_failed`）。低层分词器为兼容性保留回退词元；将它们解释为词之前，请先检查该状态。

渲染页面扫描会发出不计分的 `word_segmentation_unavailable` 提示，而不是作出内容过少的判定。之前已确认的内容过少问题会保持未解决，直到能够再次检查。这种不完整扫描不会刷新页面评分：已有评分保留原来的 `scored_at`，首次扫描则在分词正常工作前不产生评分。安装 PHP `ext-intl`、启用 `auto` 分词器并重新扫描，才能恢复这些检查。

### 哪些引擎分析了页面 {#which-engines-analysed-the-page}

每份清单都携带 `analysis` 块，记录文案的主要文字系统、分词器（`intl` / `regex`）、词干提取器（`snowball` / `builtin` / `identity`）及可读性方法（`formula` / `heuristic` / `lix`）。它会出现在 `toArray()` / `--json` 中、Filament 弹窗底部，以及 `seo-pro:checklist` 的最后一行：

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

底部说明标识实际使用的引擎，包括 ext-intl 缺失时的正则分词，以及禁用词干提取时的直接匹配。

### 关键词密度是建议项 {#keyword-density-is-advisory}

清单不会为排名定义理想关键词密度。此检查属于**建议项**，显示次数供参考，绝不会失败，也**绝不影响页面总体状态**。请判断重复是否自然，而不是追求某个百分比。

### 可读性是建议项 {#readability-is-advisory}

清单使用分析语言区域所选的方法估计可读性。目前实现的公式及回退如下：

| 语言区域 | 公式 | 来源 |
| --- | --- | --- |
| 英语（`en`） | Flesch Reading Ease | Flesch 1948 |
| 意大利语（`it`） | Gulpease Index | Lucisano & Piemontese 1988 |
| 西班牙语（`es`） | Fernández-Huerta | Fernández Huerta 1959 |
| 法语（`fr`） | Kandel-Moles | Kandel & Moles 1958 |
| 德语（`de`） | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| 葡萄牙语（`pt`、`pt_BR`） | 巴西葡萄牙语改编版 Flesch | Martins et al. 1996 |
| 荷兰语（`nl`） | Flesch-Douma | Douma 1960 |
| 俄语（`ru`） | Oborneva 的 Flesch 改编版 | Оборнева 2006 |
| 土耳其语（`tr`） | Ateşman | Ateşman 1997 |
| 波兰语（`pl`） | Pisarek（受教育年限指数，已归一化） | Pisarek 1969 |
| 日语、中文、韩语（`ja`、`zh`、`ko`） | **启发式，不提供分数**，见下文 | — |
| 希腊语、乌克兰语、捷克语（`el`、`uk`、`cs`） | LIX：此处未实现专用公式，故作为回退；未针对这些语言校准 | Björnsson 1968 |
| 其他语言 | LIX（Läsbarhetsindex），未经校准的回退 | Björnsson 1968 |

显示的 **0–100 分数（越高越容易）**是包内约定。Flesch 系列和 Gulpease 结果被限制在该区间内；Wiener 年级、Pisarek 和 LIX 指数则映射到这个刻度。不同语言的同分**不代表**阅读难度相同。系数公式来自已发表研究，但 Rankbeam 的词元、句子和音节估计尚未作为完整测量工具获得验证，不预测理解程度或搜索排名。

从 Pro 2.37.1 起，土耳其语和俄语的相邻元音分别计算音节（`saat`：2；`поэт`：2）。其他音节估计器仍有限制：元音组会漏掉部分元音分读和不发音元音。英语有一小份例外映射，但不是发音词典。例如西班牙语 `país` 和法语 `monde` 可能计数错误。不熟悉的词和专有名称请人工复核。

#### 文本统计与 API 限制 {#text-statistics-and-api-limits}

HTML 块级标签和 `br` 元素分隔文本，行内强调仍与所在词相连。普通 HTML 源码中的换行折叠为空格，而纯文本和 `pre` 保留行边界。script、style 和 noscript 内容被排除。提取过程不评估 CSS 可见性，也不检查渲染页面。实体只解码一次。公式统计将连续字母 / 数字视为词，单独的标点不算词。连字符和撇号分隔词。数字计为词元，但不推断音节。字母按原始文本计数，不受词干提取或德语 `ß` → `ss` 大小写折叠造成的长度变化影响。

句子估计按句末 `. ! ? 。 ！ ？` 及块 / 行边界拆分，包含末尾没有结束标点的片段，并保护小数和少量常见缩写（`Dr.`、`Prof.`、`e.g.` 及类似英语形式）。因此，标题和列表项可能被算作句子。其他缩写、引语、数字、混合文字系统和标点稀少的文本需要特别留意。所选语言区域决定使用哪种方法，但不会检测每句话是否都是该语言。

直接计算器的 `toArray()` 新增了 `assessment` 块：

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` 区分 `formula`、`lix`、`heuristic` 和 `unavailable`（手动构造且没有公式元数据的结果为 `unspecified`）。空输入或只有标点的输入为 `insufficient`，且 `isValid()` 为 false；其旧版 `score: 0` 是表示不可用的哨兵值，不是难度评分。现有英语 / 意大利语年级标签只是近似值；其他语言以及启发式 / LIX 结果不再显示这些学校年级标签。`calculateFleschKincaid()` 为兼容性保留公开方法名，但计算的是 **Flesch Reading Ease**，不是 Flesch-Kincaid 年级。

公式测试为全部十种具名公式及 LIX 固定了独立计数的输入和预期算术结果。它们验证计算行为，不验证母语编辑质量。可读性仍与 Pro SEO 评分分开。

::: warning 日语、中文和韩语：明确标注的启发式方法，绝不提供数值
Rankbeam 为这些语言实现了不计分的方法。计算器依据包特有的经验规则返回一个**水平**：平均每句字符数（日语 ≤ 40/60/80，中文 ≤ 30/45/60）或词数（韩语 ≤ 12/18/25），日语还考虑汉字占比（超过约 45% 时，在包的难度区间中提高一级）。结果标记为 `heuristic: true`，**分数为 null**。清单消息会说明“启发式”；**对这些语言，无论 `readability.advisory` 如何设置，该检查都保持建议性质**。经验规则只提供参考，绝不控制清单总体状态。`ja`/`zh` 的清单词数依赖正常工作的 ICU 分词，不可用时会跳过这些检查。
:::

与关键词密度一样，可读性**默认属于建议项**：为作者提供信息，但**不**控制页面总体状态，这与 Yoast 分开处理可读性和 SEO 分析的方式相同。低于最低词数时会**跳过**；内容过少由 `content_length` 检查负责，不属于可读性检查的职责。如果希望难读的页面判为失败，可以让其具有决定性：

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## 读取检查清单 {#reading-the-checklist}

### 无界面使用 {#headless}

Pro 2.36 按请求的内容语言区域读取解析后的元数据、`getContentForSEO()` 和焦点关键词，而清单标签仍使用操作人员的语言。未显式指定语言区域时，会遵循翻译模型的 `seoData()` 默认值。Filament 操作跟随字段的语言选项卡或页面语言切换器。

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

每个 `CheckResult` 包含 `id`、`group`、`label`、`status`、`message`、可选的 `recommendation`，以及 `advisory` 标记。

### 命令 {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### 在编辑器中使用（Filament，可选） {#in-the-editor-filament-optional}

安装 [`rankbeam/laravel-seo-filament`](/zh-CN/guide/filament) 后，焦点关键词字段上会出现 **页面 SEO 清单（On-page checklist）**操作。点击后打开弹窗，对记录已保存的内容展示相同的通过 / 警告 / 失败检查。Filament 包绝不依赖 Pro；该操作通过与 AI 建议相同的单向扩展钩子附加，因此不会影响无界面安装。

## 配置 {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### 编写自定义检查 {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

将类加入 `seo-pro.checklist.rules` 即可注册。检查**不得**复用[扫描问题代码](/zh-CN/pro/scan-issues)的 id；清单使用独立、不计分的命名空间。

## 如何读取内容 {#how-the-content-is-read}

`SeoPro::checklistFor($model)` 分析以下内容：

- **标题 / 描述**：*解析后*实际生效的值，与编辑器计数器和扫描测量的值相同，因此清单不会与它们矛盾。
- **内容**：`$model->getContentForSEO()`（核心包的 `HasSEO` 访问器，默认使用 `content` / `body` / `text`）。在模型上覆盖它，使其指向实际正文。
- **URL**：`$model->getUrlForSEO()`。
- **焦点关键词**：已存储的 `seo_meta.focus_keywords`。

这是纯分析，不抓取页面，也不写入任何内容。

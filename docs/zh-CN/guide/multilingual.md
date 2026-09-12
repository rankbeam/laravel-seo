---
description: "了解 Rankbeam 如何处理非英语内容：按文字系统设置标题和描述预算、安全截断字素、按语言处理大小写、hreflang 规范化与策略、inLanguage、区域搜索引擎、网站验证、OG 图片字体和 Unicode 网址。"
---

# 多语言内容 {#multilingual-content}

[翻译](/zh-CN/guide/translations)让*包*使用你的语言。本页介绍另一部分：让包**理解内容的语言**。60 个字符的标题限制不适合日语，按词语边界截断会破坏泰语，`İstanbul` 和 `istanbul` 在土耳其语中是同一个词，`it_IT` 作为 hreflang 无效，而韩语网站除了 Google，也需要关注 Naver 的爬虫。这些不是翻译问题，而是正确性问题，应该在核心中统一决定，让所有界面和输出保持一致。

默认值和策略覆盖位于 `config/seo.php`。部分能力需要运行时依赖，例如 ICU 分词和已安装的字体；翻译后的内容必须由应用提供。

## 内容语言与界面语言 {#content-locale-and-interface-locale}

Core 3.17、Filament 1.11 和 Pro 2.36 会将选定的内容语言传递到元数据、计算钩子、预览网址、检查清单关键词和 AI 请求中。英语面板可以编辑意大利语和日语，而不改变界面标签。

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

这些读取会选择该语言的元数据行，并在临时语言作用域中执行 `getSEOTitle()`、`getSEODescription()`、`getUrlForSEO()` 和 `getSEOSchema()` 等模型钩子。调用方的模型和应用语言保持不变，即使钩子抛出异常也一样。实现 Spatie `setLocale()` 和 `getTranslatableAttributes()` 的模型也会获得隔离的实例语言。钩子仍需返回翻译后的内容，Rankbeam 不会自动翻译普通数据库属性。

Pro 在基于模型的 AI 方法和批量填充中接受显式 `locale:`。没有它时，由翻译模型覆盖后的 `seoData()` 默认值决定内容语言，再回退到应用语言。Filament 操作接收自身字段的语言，包括单语言编辑器和跟随模式。自定义队列任务中，应序列化选定语言，并在任务运行时显式传入，不要依赖工作进程当前语言。

对于自定义同步内容读取器，`ModelLocale::run($model, $locale, $callback)` 将隔离模型传给回调，并在 `finally` 中恢复应用语言。所有依赖语言的读取都应在回调内完成；返回惰性迭代器或闭包，不会延长作用域。

## 按文字系统划分标题和描述预算 {#title-and-description-budgets-per-script}

Rankbeam 的编辑预算为拉丁文字标题和描述 60/160 个字素，CJK 为 30/80。这些是可配置的近似预算，不是像素测量，也不保证搜索引擎会完整显示。Google 没有为[标题链接](https://developers.google.com/search/docs/appearance/title-link)或[元描述](https://developers.google.com/search/docs/appearance/snippet)规定固定字符限制，显示文本可能因设备宽度而截断。

`Rankbeam\Seo\I18n\LengthPolicy` 根据实际文本决定预算：

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

编辑器警告 `SEOWarningEvaluator`、免费的 `seo:audit`、计算描述截断、Pro 扫描和 Filament 计数器都读取它，因而使用同一策略。警告评估解析后的值，包括标题后缀；编辑器也可以显示未保存文本。长度计算的是**字素簇**，不是字节或码点。簇边界遵循已安装的 Unicode 实现，并不等于音节计数或搜索结果像素测量。

各行位于 `seo.length_policy`，以文字系统组为键，包括 `latin`、`cyrillic`、`greek`、`cjk`、`thai`、`arabic`、`hebrew`、`devanagari`；未列出的组使用 `default`。每行可以只设置部分键，其余继承：

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

默认只有 `cjk` 不同。使用旧版已发布配置的升级安装，无需修改任何内容，也会获得内置的 `cjk` 行。

::: tip 混合标题
检测使用加权字母计数，CJK 字形计两倍。因此，“Laravel SEO の完全ガイド”归为 CJK，而“Laravel SEO for the 東京 developer”仍归为拉丁文字。完全没有字母的值，例如年份或价格，使用页面语言对应的文字系统。
:::

`SEOWarningEvaluator::TITLE_MAX_LENGTH` / `DESCRIPTION_MAX_LENGTH` 常量仍作为拉丁文字默认值存在，供读取它们的代码使用。

## 字素安全、识别文字系统的截断 {#grapheme-safe-script-aware-truncation}

计算描述的 `seo.computed.description_max_length` 是拉丁文字预算，会按策略缩放，CJK 描述使用一半，再由 `Rankbeam\Seo\I18n\Truncator` 截断：

- 有词间空格的文本保留历史规则：使用限制内最后一个词语边界，前提是至少达到限制的 60%；不添加省略号，移除末尾标点。拉丁文字结果与之前逐字节一致。
- 汉字、假名和泰语没有词间空格，因此优先在限制内最后一个句子或分句标点处截断，例如 。！？、，；其次使用文本中存在的空格，例如韩语；最后才直接截断。
- 切片以字素簇为单位，不会落在组合序列内部，泰语元音符号或 emoji 修饰符不会与其基础字符分离。

## 按语言处理大小写 {#locale-aware-casing}

`mb_strtolower()` 不识别语言，而 `Rankbeam\Seo\I18n\CaseFolder` 会：

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` 用于显示形式；`fold()`、`equals()`、`contains()` 和 `containsWord()` 用于比较。核心在识别品牌后跳过标题后缀的逻辑 `seo.title_suffix_skip_when_contains` 中使用它，让土耳其语品牌以任意 `i` 形式都能匹配，也让带重音的品牌获得正确词语边界。Pro 关键词检查使用同一个辅助工具。

大小写折叠保留重音，不会让所有带重音和不带重音的拼写都相等。语言专用词干提取器可能进行自身的简化处理，但那与 `CaseFolder` 和原形精确匹配是不同的步骤。

## hreflang {#hreflang}

Google 读取 `language[-Script][-REGION]`，即 ISO 639-1 两字母语言代码，可选 ISO 15924 文字代码和 ISO 3166-1 alpha-2 地区代码，另支持 `x-default`。`es-419` 这样的数字地区代码是有效 BCP47，但不在 [Google 的 hreflang 约定](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes)范围内。Laravel 应用经常直接传入自身的*语言设置*，例如 `it_IT`、`pt_br`，但这里的下划线无效。`seo.hreflang` 中的三项策略会在模型的 `getSEOAlternates()` 列表成为 `<link rel="alternate">` 标签、网站地图 `<xhtml:link>` 条目、`llms.txt` 链接和审计输入**之前**应用。所有输出使用同一策略；`llms.txt` 会在“其他语言版本”链接中省略页面自身和 `x-default`：

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`** 默认启用，调整分隔符、大小写和已注册别名，例如 `iw_IL` → `he-IL`。重复分隔符会保留，例如 `en__US` → `en--US`，以便审计标记。关闭后保留传入的原始字节。
- **`include_self`** 在列表既不包含页面网址也不包含页面代码时，追加页面自身语言和规范网址。Google 要求每个语言版本列出自身；钩子只返回*其他*语言时，可以启用。
- **`x_default`** 指定一种语言，当列表没有 `x-default` 时，将该语言的替代版本复制为此回退项。

空列表保持为空，没有翻译的页面不会添加自引用，也不会添加 `x-default`。

免费审计对应用策略后的列表增加三项检查：

| 代码 | 严重程度 | 含义 |
|---|---|---|
| `hreflang_invalid_code` | 警告 | 代码不在 Google 约定范围内，例如 `en-UK`、`jp`、`english`、`es-419`、`fil`。 |
| `hreflang_duplicate_code` | 提示 | 同一个代码列出两次。 |
| `hreflang_missing_self` | 警告 | 页面自身网址不在列表中。 |

双向关联，也就是另一页是否指回，需要爬取，由 Pro 扫描负责。主动启用的 `check_hreflang_reciprocity` 通过 SsrfGuard 获取每个替代版本，如果对方没有**使用本页语言代码**声明本页网址，则报告 `hreflang_not_reciprocal`。该功能从 Pro 2.38+ 提供，见[扫描问题](/zh-CN/pro/scan-issues#network-codes)。需要时也可以直接使用公开辅助方法：

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### 三种语言代码约定 {#three-language-code-contracts}

Core **3.18+** 将应用设置与 HTML 中实际提供的值区分开：

| 输入 | 应用规范化 | HTML 语言 | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | 原样输出时无效 | 原样输出时无效 |
| `de-CH-1901` | 保留 | 有效的已注册变体 | 不支持的变体 |
| `es-419` | 保留 | 有效的数字地区 | 不支持的数字地区 |
| `zh-Hant-TW` | 保留 | 有效 | 有效 |
| `fil` | 保留 | 有效的已注册语言 | 不在两字母约定内 |
| `iw_IL` | `he-IL` | 下划线无效；`iw-IL` 仍是有效但已弃用的标签 | 使用规范化的 `he-IL` |
| `en__US` | `en--US` | 无效 | 无效 |
| `x-default` | 保留 | Rankbeam 内容语言策略拒绝 | 有效的回退标记 |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**从 core 3.17 或更早版本迁移：** `Hreflang::isValid()` 和 `parse()` 严格验证实际输出的代码。如果调用方传入 Laravel 语言设置，先调用 `fromLocale()`。如果检查 HTML `lang` 属性，直接使用 `LanguageTag::isValidHtml()`，不要先修剪或规范化。已弃用的注册标签对 HTML 仍然有效；规范化只采用 IANA 明确的首选别名，不会猜测 `en-UK` 意味着 `en-GB`。格式错误的条目不会在审计有机会报告前被过滤。

验证器内置日期为 **2026-08-08** 的 IANA 注册表事实，附有源哈希和可复现生成器。它检查 RFC 5646 结构、已注册子标签、扩展语言前缀以及重复变体和扩展，支持祖父标签和私用范围。变体前缀建议不是强制有效性规则；会检查扩展命名空间和结构，但 CLDR 选项语义及私用含义不在 API 范围内。无需 ICU 或运行时下载。参见 [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) 和 [HTML 语言定义](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes)。

Pro **2.38+** 将缺失或空的 `lang` 报告为未知或缺失，格式错误的实际输出字节则产生 `html_lang_invalid`。文字系统不匹配检查使用实际文字子标签，或 IANA 注册的默认文字系统；私用、扩展载荷和不熟悉的语言不会被推定为拉丁文字。不支持的文字组保持未判断状态。这些检查不是完整的语言检测器。

双向关联检查使用源页面有效的自引用代码；没有自引用代码时，使用有效且与 Google 兼容的 HTML 语言。返回网址出现在另一个语言代码下，不能算通过。无法确定源代码时，结果保持 `hreflang_target_unverified`。重复目标网址在既有替代版本数量和响应体限制内只抓取一次；SSRF 防护、拒绝重定向和失败时标为未验证的处理保持有效。

## 结构化数据图中的 `inLanguage` {#inlanguage-in-the-schema-graph}

`WebPage` 节点从页面解析后的语言取得 `inLanguage`，例如 `it_IT` → `it-IT`；`ArticleSchema::fromModel()` 则从存储的 `seo_meta` 语言取得。`WebSite` 节点从配置取得语言：

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## 区域搜索引擎 {#regional-search-engines}

`seo:robots-txt` 背后的爬虫目录现在也包含 Google/Bing 之外重要的传统网页搜索爬虫：Yandex、Baidu、Naver（`Yeti`）、Seznam、Sogou、360、Cốc Cốc 和 DuckDuckGo。它们标注为 `search_engine` 用途，默认允许，并参与策略和逐机器人覆盖。因此，不服务中国市场的商店可以限制两个爬虫占用带宽：

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` 和 `match()` 仍只包含 AI 机器人，因此 Pro AI 机器人日志和所有“N 个 AI 爬虫”计数不变。通过 `searchEngines()`、`all(true)` 或 `match($ua, true)` 才会请求包含这些搜索引擎。参见 [AI 爬虫控制](/zh-CN/guide/ai-crawlers#regional-search-engines)。

::: warning Baidu
支持爬虫和验证标签，不保证在 Baidu 中被发现、索引或获得排名。
:::

## 网站验证 {#site-verification}

每个已配置引擎的所有权验证值，都会在每个页面输出为一个元标签。Google 接受标签出现在任意位置；Yandex、Baidu 和 Naver 检查根页面，这里同样覆盖。留空的键不输出内容：

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

值可以是验证 token 列表，Google 会为每个资源所有者发放一个。

## 各种文字系统的 OG 图片 {#og-images-in-every-script}

内置卡片字体覆盖拉丁、西里尔和希腊文字。其他文字系统依赖运行 `seo:og-images` 的机器上安装的字体，不额外打包，因为一个 CJK 字体就有 16 MB 以上。模板现在包含按文字系统设置的回退字体栈，即 `seo.og_image.font_stack`，并将页面语言对应的 Noto CJK 字体族放到前面，确保汉字使用对应地区的正确字形。如果主机缺少即将渲染的标题所需字体，命令会按文字系统各警告一次：

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

在 Debian/Ubuntu 上：`apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`。详见[生成 OG 图片](/zh-CN/guide/og-image#fonts-and-non-latin-scripts)。

## 多语言 `llms.txt` {#llms-txt-in-several-languages}

启用 `seo.llms_txt.alternates` 后，有其他语言版本的页面会在列表项末尾附加 `Also in: [it](…), [de](…)`，即应用策略后的替代版本，去掉 `x-default` 和页面自身。默认关闭。

## Unicode 网址 {#unicode-urls}

Rankbeam 不会生成 slug 或重写网址，因此 `/città/` 或 `/検索` 这样的路径会在所有产物中原样保留。带有 IDN 主机名，例如 `https://münchen.example/`，或 Unicode、百分号编码路径的规范网址，都能通过审计；`Rankbeam\Seo\I18n\Url::isValid()` 替代了 PHP 只接受 ASCII 的 `FILTER_VALIDATE_URL`。每个网址保持一种表示形式，原始 Unicode *或*百分号编码，不要混用，让规范网址、hreflang 和网站地图条目逐字节一致。

## 支持哪些语言，以及支持意味着什么 {#which-languages-are-supported-and-what-that-means}

包提供下面十七种语言的文本和分析路由。本表描述工程覆盖范围，不代表母语编辑批准，也不保证在未配置的主机上正确渲染。日语和中文词语分析需要可用的 ICU；不可用时，相关的词语检查会跳过。非拉丁文字渲染需要合适字体。两个仓库都有路由断言测试：核心中的 `tests/Feature/I18n/SupportedLanguagesTest.php` 固定语言列表、hreflang 代码和预算；Pro 中的 `tests/Feature/OnPage/LanguageSupportMatrixTest.php` 固定分析引擎，任何一行不再成立都会让 CI 失败。

| 语言 | 语言代码 | 标题 / 描述 | 词数统计 | 关键词匹配 | 可读性 |
|---|---|---|---|---|---|
| 英语 | `en` | 60 / 160 | 空格分词 | Snowball | Flesch Reading Ease |
| 意大利语 | `it` | 60 / 160 | 空格分词 | Snowball | Gulpease |
| 德语 | `de` | 60 / 160 | 空格分词 | Snowball | Wiener Sachtextformel |
| 法语 | `fr` | 60 / 160 | 空格分词 | Snowball | Kandel-Moles |
| 西班牙语 | `es` | 60 / 160 | 空格分词 | Snowball | Fernández-Huerta |
| 葡萄牙语，巴西 | `pt_BR` | 60 / 160 | 空格分词 | Snowball | Martins |
| 荷兰语 | `nl` | 60 / 160 | 空格分词 | Snowball | Flesch-Douma |
| 土耳其语 | `tr` | 60 / 160 | 空格分词 | Snowball | Ateşman |
| 俄语 | `ru` | 60 / 160 | 空格分词 | Snowball | Oborneva |
| 波兰语 | `pl` | 60 / 160 | 空格分词 | Snowball | Pisarek |
| 日语 | `ja` | 30 / 80 | ICU 词典 | 大小写折叠后精确匹配 | 启发式，**无分数** |
| 简体中文 | `zh_CN` | 30 / 80 | ICU 词典 | 大小写折叠后精确匹配 | 启发式，**无分数** |
| 繁体中文 | `zh_TW` | 30 / 80 | ICU 词典 | 大小写折叠后精确匹配 | 启发式，**无分数** |
| 韩语 | `ko` | 30 / 80 | 空格分词 | 大小写折叠后精确匹配 | 启发式，**无分数** |
| 希腊语 | `el` | 60 / 160 | 空格分词 | Snowball | LIX |
| 乌克兰语 | `uk` | 60 / 160 | 空格分词 | 大小写折叠后精确匹配 | LIX |
| 捷克语 | `cs` | 60 / 160 | 空格分词 | Snowball | LIX |

本表有意明确三点：

- **从 Pro 2.37 开始内置 Snowball。** 十二种语言使用固定的 3.1.1 算法，不依赖可选包。乌克兰语和 CJK 使用原形精确匹配，不编造后缀规则。原形匹配可能漏掉屈折变化形式，词干提取则可能将不同词混为一类。参见[引擎控制和迁移说明](/zh-CN/pro/on-page-checklist#upgrading-from-pro-2-36)。
- **“启发式，无分数”与“LIX”不同。** 日语、中文和韩语在本包中使用不评分的方法，检查清单根据句长和汉字占比报告一个*等级*，分数为 `null`，无论如何配置都保持建议性质。希腊语、乌克兰语和捷克语使用 LIX，因为本包没有实现专用公式。LIX 不需要音节数，但其阈值并未针对所有语言校准。所有公式输入都包含估算，见[统计约定](/zh-CN/pro/on-page-checklist#text-statistics-and-api-limits)。
- **翻译属于第一版草稿**，除非 `TRANSLATING.md` 明确说明已经过母语审阅。意大利语已审阅，其余语言仍需要审阅者；参与审阅，是让自己作为该语言贡献者被列入包中的低成本方式。

未列出的语言可能回退到英语文本、文字系统或默认预算、原形关键词匹配，以及 LIX 或启发式可读性。这种回退不等于经过验证的语言支持。检查清单的 `analysis` 块标明文字系统、分词器、词干提取器和可读性方法；除了标签，还应检查可用状态与跳过的判断。

### 接入当地重要的搜索引擎 {#reaching-the-search-engines-that-matter-locally}

提供一种语言，不只是翻译文本。爬虫目录在 Google 和 Bing 之外，还包含 Yandex、Baidu、Naver 的 Yeti、Seznam、Sogou、360 和 Cốc Cốc。`seo.verification` 渲染对应网站验证标签，例如韩语网站的 Naver、捷克语网站的 Seznam，以及乌克兰语或俄语网站的 Yandex。参见[区域搜索引擎](#regional-search-engines)和[网站验证](#site-verification)。

## 其他包补充的功能 {#what-the-other-packages-add}

- **laravel-seo-filament** 为实时计数器和搜索结果预览读取同一个长度策略，并从 1.9 开始编辑[每种语言一行 `seo_meta`](/zh-CN/guide/filament#several-languages)。每种语言有独立标签页、计数器、预览和回退来源指示器，也可以跟随翻译插件的语言切换器。
- **laravel-seo-pro** 为扫描的 `title_length` / `description_length` 检查和 AI 辅助提示词读取同一策略，并从 2.34 开始按页面自身语言分析：中文、日语和泰语使用 ICU 分词，支持 Snowball 词干提取，通过 `CaseFolder` 按语言匹配关键词，为十种语言使用公开的可读性公式和估算输入，为 CJK 使用明确标注的启发式方法，为希腊语、乌克兰语和捷克语明确使用 LIX；此外有十六种语言的停用词、`html lang` 和 hreflang 双向关联扫描检查、明确指定页面语言的 AI 提示词，以及为 dompdf 无法绘制的文字系统提供 Chrome 渲染报告。参见[页面检查清单](/zh-CN/pro/on-page-checklist#keyword-matching)、[扫描问题](/zh-CN/pro/scan-issues)、[AI 辅助](/zh-CN/pro/ai-assist#output-language)和[报告](/zh-CN/pro/reports#reports-in-every-script-browsershot-renderer)。

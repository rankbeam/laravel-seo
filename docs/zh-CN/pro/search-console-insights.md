---
description: "利用自己已有的 Search Console 数据分析关键词：五种报告覆盖排名范围、CTR 待审阅项，以及多个页面共享的查询。"
---

# Search Console 洞察 {#search-console-insights}

根据自己的 Search Console 数据计算五种报告：指定排名范围内的查询、CTR 待审阅项、查询与页面重叠结果、查询分组，以及时间段变化。其中三种使用已同步历史，另外两种共享一个缓存的实时请求。它们覆盖这些具体分析，不提供第三方关键词平台的完整数据集或全部能力。

功能基于[只读 Search Console 集成](/zh-CN/pro/search-console)及其历史同步。如果该页的 `seo-pro:gsc-sync` 已持续运行，五种功能中的三种就可以**不增加任何 API 调用成本**。

::: tip 前提
三种*快照*功能读取持久化的 `seo_gsc_metrics` 历史，因此应先安排 `seo-pro:gsc-sync` 定时运行，见 [Search Console → 历史](/zh-CN/pro/search-console)。同步的天数越多，可比较的趋势越长。
:::

## 五种分析 {#the-five-surfaces}

### 1. 接近前列的关键词 {#_1-striking-distance-keywords}

选择**按展示次数加权的平均排名位于 5–20** 的查询，再按展示次数排序。可以据此检查相关性和内部链接，但这个范围不能证明小幅修改就能让查询进入第一页。

### 2. CTR 机会 {#_2-ctr-opportunities}

找出**排名不错，但点击率低于该位置预期水平**的查询。每个查询的实际 CTR 与综合行业排名点击率曲线比较。那些有真实展示、但远低于预期点击率的查询，会成为**标题和描述改写候选**，按估算的*错失点击数*排序。这份列表可以直接作为 [AI 元数据建议器](/zh-CN/pro/ai-assist)的输入，指出值得针对哪些具体查询改写。

### 3. 关键词内部竞争 {#_3-cannibalization}

找出同一查询词下，**两个或更多自有网址同时出现**的情况。重叠不一定有害，在合并页面或区分定位前，先检查它们是否服务不同意图。

### 4. 查询分组 {#_4-query-clusters}

将**每个页面实际获得排名的查询**按页面分组，展示 Google 结果中该页真实覆盖的主题。可以用来发现偏离原定主题的页面，或悄悄为原本未针对的高价值词获得排名的页面。

### 5. 与前一时间段比较趋势 {#_5-trend-vs-previous-period}

比较当前窗口与紧接之前、长度相同的窗口，找出点击、展示、排名和 CTR 的**最大变化项**。只有查询在两个时期都有流量时才比较排名，全新或完全消失的查询没有可比的前后值。

## 数据来源：实时与快照 {#where-the-numbers-come-from-live-vs-snapshot}

每种分析都选择能正确回答问题、成本最低的数据源。持久化历史分别存储各维度，无法重建哪个**查询**对应哪个**页面**。因此，只有需要这组配对的两种分析访问实时数据，而且**共享一个缓存请求**。

| 分析 | 来源 | 原因 |
|---|---|---|
| 接近前列的关键词 | **本地快照** | 需要逐查询排名和展示次数，已在同步历史中，无 API 调用成本 |
| CTR 机会 | **本地快照** | 使用同一份自有数据；预期 CTR 曲线是静态基准，不需要查询外部数据 |
| 趋势变化 | **本地快照** | 需要真实的逐日历史，同步存储的正是这些数据 |
| 关键词内部竞争 | **实时**，查询 × 页面 | 未存储查询与页面的配对，持久化每个配对会显著增加存储量 |
| 查询分组 | **实时**，*共享第 3 项的抓取* | 使用相同配对数据，改为按页面而不是查询分组 |

因此，访问一次洞察页面**最多发起一个** Search Analytics 请求，缓存 `search_console.cache_ttl` 秒。配对分析有意使用实时数据，因为内部竞争和分组是需要当前状态的*时间点*问题，共享缓存限制重复请求。令牌刷新可能额外发起身份验证请求，Google 配额仍然适用。快照分析不访问网络。

## 仪表板中 {#in-the-dashboard}

安装 Filament 插件后，*SEO* 导航组下会出现 **Search Console 洞察**，仅在集成启用时显示。它严格只读，每种分析各占一个区域。快照分析为空时提示同步历史；配对分析实时获取失败时，显示经过清理的行内提示，不会阻塞整个页面。

## 配置 {#configuration}

所有设置位于 `config/seo-pro.php` 的 `search_console.insights` 下。默认值可作为起点，再按网站规模调整阈值。

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info 预期 CTR 曲线
CTR 机会曲线是根据公开的自然搜索排名点击率平均值综合得到的**启发式基准**，只是衡量参照，不代表对你的网站做出具体判断。被标记的查询是*待审阅候选*，不是已证实的缺陷。如果已有自己测得的曲线，可以按 `position => percent` 映射写入 `insights.ctr_curve`。
:::

## 另请参阅 {#see-also}

- [Search Console](/zh-CN/pro/search-console)：这些洞察读取的只读集成和历史同步
- [白标报告](/zh-CN/pro/reports)：品牌 PDF 中的跨期变化项
- [AI 辅助](/zh-CN/pro/ai-assist)：改写 CTR 分析标记的标题和描述

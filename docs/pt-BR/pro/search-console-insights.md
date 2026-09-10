---
description: "Cinco análises dos seus dados de Search Console: faixas de posição, consultas para revisar CTR, consultas compartilhadas por páginas, agrupamentos e mudanças entre períodos."
---

# Análises de Search Console {#search-console-insights}

Cinco relatórios calculados com seus próprios dados: consultas em uma faixa de posição, candidatas à revisão de CTR, consultas compartilhadas por páginas, agrupamentos por página e mudanças entre períodos. Três usam histórico sincronizado; dois compartilham uma chamada em tempo real com cache. Cobrem essas análises específicas, não todos os dados e recursos de uma plataforma externa de palavras-chave.

A função usa a [integração de Search Console somente leitura](/pt-BR/pro/search-console) e sua sincronização de histórico. Se `seo-pro:gsc-sync` já estiver executando, três dos cinco relatórios não exigem **nenhuma chamada adicional à API**.

::: tip Pré-requisito
Os três relatórios baseados em snapshots leem `seo_gsc_metrics`. Agende primeiro `seo-pro:gsc-sync`, conforme o [histórico de Search Console](/pt-BR/pro/search-console). Mais dias sincronizados permitem comparar períodos maiores.
:::

## Os cinco relatórios {#the-five-surfaces}

### 1. Palavras-chave em posições próximas {#_1-striking-distance-keywords}

Consultas cuja **posição média ponderada por impressões fica entre 5 e 20**, ordenadas por impressões. Use a lista para revisar relevância e links internos. Estar nessa faixa não demonstra que uma pequena alteração levará a consulta à primeira página.

### 2. Oportunidades de CTR {#_2-ctr-opportunities}

Consultas com boa posição, mas **CTR abaixo de uma referência para essa posição**. O CTR real é comparado a uma curva agregada de CTR por posição. Consultas com impressões e uma diferença significativa aparecem como **candidatas à revisão de título e descrição**, ordenadas por uma estimativa de cliques não obtidos. Você pode usar a lista com as [sugestões de metadados por IA](/pt-BR/pro/ai-assist); a estimativa não comprova que uma reescrita recuperará esses cliques.

### 3. Canibalização {#_3-cannibalization}

Consultas em que **duas ou mais URLs do site aparecem** para o mesmo termo. Essa sobreposição não é necessariamente prejudicial. Antes de consolidar ou diferenciar páginas, confira se atendem a intenções distintas.

### 4. Agrupamentos de consultas {#_4-query-clusters}

As **consultas pelas quais cada página aparece nos resultados**, agrupadas por página. Isso ajuda a identificar uma página que se afastou do assunto pretendido ou que aparece para um termo relevante não previsto na estratégia de conteúdo.

### 5. Tendência em relação ao período anterior {#_5-trend-vs-previous-period}

As **maiores mudanças** de cliques, impressões, posição e CTR na janela atual comparada ao intervalo de igual duração imediatamente anterior. A posição só é comparada quando a consulta teve tráfego nos dois períodos; uma consulta nova ou que desapareceu não oferece essa base.

## Origem dos dados: consulta em tempo real ou snapshot {#where-the-numbers-come-from-live-vs-snapshot}

Cada relatório usa a fonte que atende à análise com menos chamadas. O histórico salva consultas e páginas como dimensões separadas, então não consegue reconstruir qual **consulta** corresponde a qual **página**. Os dois relatórios que exigem esse par usam uma consulta em tempo real e **compartilham uma única resposta em cache**.

| Relatório | Fonte | Motivo |
|---|---|---|
| Palavras-chave em posições próximas | **Snapshot local** | Usa posição e impressões por consulta já sincronizadas, sem chamada de API |
| Oportunidades de CTR | **Snapshot local** | Usa o mesmo histórico; a curva de referência é estática |
| Mudanças de tendência | **Snapshot local** | Exige o histórico diário mantido pela sincronização |
| Canibalização | **Tempo real**, consulta × página | O par não é salvo; persistir todos os pares multiplicaria o armazenamento |
| Agrupamentos de consultas | **Tempo real**, compartilhando a chamada do relatório 3 | Usa os mesmos pares, agrupados por página em vez de consulta |

Uma visita à página de análises faz **no máximo uma** chamada de Search Analytics, armazenada por `search_console.cache_ttl` segundos. Os relatórios de pares usam a visão disponível naquele momento e a cache limita chamadas repetidas. Renovar um token pode exigir uma chamada adicional de autenticação, e as quotas do Google continuam valendo. Os relatórios de snapshot não acessam a rede.

## No painel {#in-the-dashboard}

Com o plugin Filament instalado e a integração ativada, **Search Console Insights** aparece no grupo SEO. É somente leitura. Cada relatório ocupa uma seção; os que dependem de histórico mostram uma indicação para sincronizá-lo quando vazio. Uma falha na consulta dos pares mostra um aviso depurado na própria seção, sem bloquear a página.

## Configuração {#configuration}

Os ajustes ficam em `search_console.insights` dentro de `config/seo-pro.php`. Adapte os limites à escala do site.

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

::: info Curva de CTR esperado
A curva é uma **heurística** que combina médias publicadas de CTR orgânico por posição. Serve de referência, sem representar necessariamente seu site. Uma consulta destacada é candidata à revisão, não um defeito comprovado. Se você tem uma curva medida com seus dados, configure `insights.ctr_curve` como um mapa `position => percent`.
:::

## Veja também {#see-also}

- [Search Console](/pt-BR/pro/search-console): integração somente leitura e sincronização do histórico.
- [Relatórios com sua marca](/pt-BR/pro/reports): mudanças entre períodos no PDF.
- [Assistência de IA](/pt-BR/pro/ai-assist): revisar títulos e descrições apontados pelo relatório de CTR.

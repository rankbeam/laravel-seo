---
description: "Uma pontuação determinística separada do SEO: a medida de compatibilidade técnica com rastreadores de IA definida pelo Rankbeam, baseada no acesso e na leitura da página."
---

# Pontuação AI-Readiness: uma segunda medida determinística {#the-ai-readiness-score-—-a-second-deterministic-axis}

A varredura Pro atribui a cada página uma **pontuação AI-Readiness de 0 a 100**, ao lado da [pontuação de SEO](/pt-BR/pro/scoring). Ela responde a outra pergunta: *os rastreadores de IA e mecanismos de respostas conseguem acessar, ler e identificar a autoria deste conteúdo?* Seu valor **nunca é incorporado à pontuação de SEO orgânico**. São duas medidas separadas, cada uma com seus critérios, versão e coluna.

::: warning O que esse número significa
A pontuação AI-Readiness é uma **medida determinística de compatibilidade técnica definida pelo Rankbeam**. Ela avalia se determinados sinais observados no rastreamento estão presentes e bem formados. **Não prevê** posicionamento, indexação, inclusão ou citações em mecanismos de busca ou sistemas de IA, nem garante esses resultados. A verificação `air_llms_txt` atribui pontos a um arquivo **opcional** de compatibilidade `llms.txt`, destinado às ferramentas que decidem consumi-lo. Ele não é exigido pelo Google Search nem é um sinal de posicionamento.
:::

Como a pontuação de SEO, ela é **determinística e reproduzível**: cada ponto corresponde a uma verificação identificada, baseada nos sinais coletados, e as mesmas entradas produzem o mesmo número. **O cálculo não faz nenhuma chamada de IA.** É uma medição auditável; não usa amostragens de respostas de LLMs para estimar visibilidade.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip Duas medidas, sem mistura
`AI-readiness: 74/100` aparece ao lado de `SEO: 82/100`, sem que uma altere a outra. AI-Readiness usa suas próprias colunas `ai_readiness_*` em `seo_scan_results`. Assim como no SEO, a **pontuação numérica é um recurso Pro**; o comando gratuito [`seo:audit`](/pt-BR/guide/audit) não exibe um número.
:::

## Soma de pontos {#additive-credit-not-penalty}

A [pontuação de SEO](/pt-BR/pro/scoring) começa em 100 e **desconta** penalidades. AI-Readiness começa em **0** e **soma** o peso de cada verificação, integral ou parcialmente. Um site acumula os sinais de compatibilidade; se não apresentar nenhum, sua pontuação fica próxima de 0. Os pesos totalizam exatamente **100**.

## Critérios de pontuação {#the-rubric}

O cálculo segue critérios **publicados e versionados** em `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric`, com dez verificações divididas em quatro categorias:

### A · Acesso e controle de bots: 30 pontos {#a-·-bot-access-control-—-30-points}

Os rastreadores de busca com IA e de assistentes têm permissão para acessar a página? A análise usa o **`/robots.txt` servido pelo site**, aplicado ao **caminho da página examinada**. Uma página sob `Disallow: /section` é considerada bloqueada mesmo que a raiz esteja liberada. O arquivo orienta rastreadores que respeitam suas regras; não bloqueia o acesso na rede. A classificação por finalidade — treinamento, busca ou assistente — vem do [catálogo de rastreadores de IA](/pt-BR/guide/ai-crawlers).

| Verificação | Peso | Pontos atribuídos |
|---|---|---|
| `air_robots_reachable` — um `robots.txt` é servido e pode ser lido | 6 | presente / ausente |
| `air_ai_search_access` — rastreadores de **busca** com IA, capazes de encaminhar visitas, têm permissão de acesso | 10 | proporção permitida |
| `air_ai_assistant_access` — rastreadores de **assistentes** de IA têm permissão de acesso | 8 | proporção permitida |
| `air_explicit_ai_policy` — existe uma regra explícita em `robots.txt` para um bot de IA conhecido | 6 | presente / ausente |

::: tip Bloquear treinamento não reduz a compatibilidade
Bloquear bots de treinamento, como GPTBot e CCBot, é uma escolha válida e **nunca** gera penalidade. O treinamento só participa de `air_explicit_ai_policy`, que reconhece a existência de uma política explícita. Um site que bloqueia treinamento e permite rastreadores de busca e de assistentes pode obter todos os pontos desta categoria.
:::

### B · Descoberta: 20 pontos {#b-·-discoverability-—-20-points}

| Verificação | Peso | Pontos atribuídos |
|---|---|---|
| `air_sitemap_discoverable` — um sitemap XML está acessível **e** é indicado por uma diretiva `Sitemap:` | 12 | ambas as condições / uma / nenhuma |
| `air_llms_txt` — um `/llms.txt` válido, com título e links, é servido | 8 | válido / presente / ausente |

### C · Conteúdo legível por máquinas: 22 pontos {#c-·-machine-readable-content-—-22-points}

| Verificação | Peso | Pontos atribuídos |
|---|---|---|
| `air_server_rendered_content` — o HTML renderizado no servidor contém texto substancial, disponível sem executar JavaScript | 14 | conforme a contagem de palavras |
| `air_markdown_twin` — uma versão Markdown da página é servida por negociação de conteúdo | 8 | presente / ausente |

### D · Dados estruturados e organização de respostas: 28 pontos {#d-·-structured-data-answer-readiness-—-28-points}

| Verificação | Peso | Pontos atribuídos |
|---|---|---|
| `air_schema_completeness` — JSON-LD presente, entidade principal tipada, autoria e data presentes; artigos precisam de autor e data | 18 | completo / parcial / ausente |
| `air_answer_structure` — recursos que facilitam extrair respostas: schema FAQ/QA/HowTo, hierarquia de títulos, listas e introdução concisa | 10 | conforme a quantidade de recursos |

Cada verificação recebe pontuação **integral**, **parcial** ou **zero**. Ela também pode ser **ignorada** quando o sinal necessário não foi coletado, como uma verificação de página sem busca do HTML. Uma verificação ignorada vale 0, mas fica identificada; um sinal *não verificado* não é apresentado como ausência confirmada.

### Alcance da auditoria gratuita {#free-audit-reach}

`air_schema_completeness` pode ser avaliada pelos dados estruturados de um modelo sem buscar a página, pelo mesmo caminho usado pela auditoria gratuita para os avisos sobre organização de respostas. As outras nove verificações exigem rastreamento, por isso a pontuação completa pertence à **varredura Pro**.

## Limites desta medida {#honest-scope-—-what-this-axis-excludes}

Esta medida avalia **sinais determinísticos do conteúdo**. Ela exclui verificações de infraestrutura de agentes que dependem de uma aplicação em execução ou de DNS:

| Fora do escopo | Motivo |
|---|---|
| **DNS-AID** (registros DNS para descoberta de agentes) | Depende de infraestrutura DNS/DNSSEC, não de uma propriedade da página servida. |
| **Web Bot Auth** (assinatura por requisição) | Exige uma troca criptográfica interativa, não conteúdo estático. |
| **Protocol Discovery** (API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills, WebMCP…) | Depende de uma aplicação, API ou servidor MCP em execução. |
| **Commerce** (x402, MPP, UCP, ACP) | São mecanismos de pagamento para agentes; um site de conteúdo pode não ter transações a cobrar. |

A medida inclui a completude das entidades de schema e a estrutura dos blocos de resposta. Esses sinais descrevem organização e atribuição, sem garantir que um mecanismo de busca ou de respostas os utilize.

## Versionamento: pontuações históricas preservadas {#versioning-—-historical-scores-never-silently-change}

Cada pontuação salva registra a `AiReadinessRubric::VERSION` que a produziu em `ai_readiness_version`. Alterações nas verificações, pesos ou regras de atribuição de pontos **incrementam a versão**, permitindo identificar quais critérios explicam um resultado histórico. A pontuação é **armazenada, sem recálculo durante a leitura**. Limites que afetam o cálculo, como contagens de palavras e de recursos estruturais, são constantes no código vinculadas à versão; não são opções de configuração capazes de alterar silenciosamente um número publicado.

::: warning Uma entrada não fixada pela versão
As verificações de acesso leem o [catálogo atual de rastreadores de IA](/pt-BR/guide/ai-crawlers) do Core. Um novo bot ou uma mudança de finalidade altera as entradas e pode mudar as duas subpontuações de acesso sem incrementar `AiReadinessRubric::VERSION`, que versiona os **critérios**, não o catálogo. Essa escolha mantém a análise alinhada à lista atual de bots. Para comparações históricas exatas, registre e fixe também a versão do pacote Core.
:::

## Onde os dados são armazenados {#where-it-s-stored}

Cada varredura insere ou atualiza as colunas AI-Readiness na **mesma** linha de `seo_scan_results` usada pela pontuação de SEO:

| Coluna | Conteúdo |
|---|---|
| `ai_readiness_score` | Número de 0 a 100; `null` até o alvo ser examinado com esta medida ativada. |
| `ai_readiness_version` | Versão dos critérios usados no cálculo. |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]`, com o detalhamento completo. |

A média de cada execução é salva em `seo_scan_runs.avg_ai_readiness` quando ela termina, assim como `avg_score`, e alimenta a tendência de AI-Readiness.

## Como ler a pontuação {#reading-the-score}

**Sem painel**: o resultado mais recente de um modelo contém as duas medidas:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament**: coloque a coluna correspondente ao lado da pontuação de SEO em qualquer tabela de recurso:

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

O valor também aparece como um selo ao lado da pontuação no cartão on-page, acima do campo de título de SEO. No [relatório com sua marca](/pt-BR/pro/reports), em PDF ou e-mail, ganha uma seção com número, classificação, diferença em relação ao relatório anterior e tendência por varredura. Ele sempre aparece separado da pontuação orgânica.

### Faixas de classificação {#grade-bands}

A letra é uma apresentação derivada do número, que continua sendo o valor de referência. As faixas são as mesmas da pontuação de SEO:

| Pontuação | Classificação |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Configuração {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

As verificações e os pesos **não são configuráveis**. Uma mesma `ai_readiness_version` deve produzir um cálculo determinístico em qualquer instalação. Alterar o cálculo exige mudar os critérios no código e sua versão.

::: warning Detecção de sinais pelo fluxo interno de requisições
Para alvos do mesmo servidor, a varredura resolve `/robots.txt`, `/llms.txt` e a página pelo kernel HTTP do Laravel dentro do processo, como nas demais verificações. Um `robots.txt` ou `llms.txt` servido como **arquivo estático**, sem passar pelas rotas Laravel, não é visto. Para que esses arquivos sejam avaliados, sirva-os pelas rotas do pacote, que são a configuração recomendada.
:::

---
title: "O que é Rankbeam? Infraestrutura de SEO para Laravel"
description: "Rankbeam é infraestrutura de SEO open-core para Laravel: Core gratuito sob MIT para metadados, URLs canônicas, JSON-LD, sitemaps e controles de rastreadores, com Pro comercial e interface Filament opcional."
---

# O que é Rankbeam? {#what-is-rankbeam}

**Rankbeam é infraestrutura de SEO open-core para Laravel: um Core gratuito sob MIT para metadados, URLs canônicas, cartões sociais, JSON-LD interligado, sitemaps e controles de rastreadores, com monitoramento e fluxos Pro comerciais opcionais.** Ele resolve o SEO a partir dos seus modelos e da configuração, renderiza os mesmos dados tipados em Blade, no head do Inertia ou em uma API JSON e, com Pro, continua monitorando o site após a implantação.

## A família de pacotes {#the-package-family}

Os três pacotes compartilham a mesma matriz de suporte:

| Pacote | Licença | Função |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, gratuito** | Core: resolução de metadados, grafo JSON-LD interligado, sitemaps XML, controles de rastreadores, `seo:audit` gratuito e importadores |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, gratuito** | Campos de formulário e prévias ao vivo para Filament 4/5, com gravação em `seo_meta` do Core |
| `rankbeam/laravel-seo-pro` | **Comercial** | Operação: varreduras em fila com pontuação de 0 a 100, redirecionamentos, monitor de 404 sem IPs, rastreador de links quebrados, análises do Search Console e assistência de IA com sua chave |

A separação é explícita: a saída da página renderizada pertence ao Core MIT e permanece gratuita. A camada comercial cuida da **auditoria e do monitoramento em produção**. Pro é um pacote próprio e não é distribuído dentro do Core gratuito.

## Para quem ele serve {#who-it-s-for}

O Rankbeam faz mais sentido quando o SEO é **armazenado, ligado a modelos, multilíngue, usado sem painel e auditado**: uma aplicação Laravel em produção com conteúdo dinâmico. Para poucas páginas estáticas que precisam apenas de título e descrição, um pequeno gerador de tags pode bastar. A seção sobre [quando manter uma combinação de pacotes](#what-is-honestly-not-in-the-free-core) descreve esse limite.

## Versões compatíveis {#supported-versions}

Uma matriz para toda a família:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11, 12 e 13; Laravel 13 exige PHP 8.3 ou superior.
- **Filament** 4 e 5, opcional.

## O que o Rankbeam não substitui {#what-rankbeam-doesn-t-replace}

O Rankbeam coordena a saída de SEO da sua aplicação Laravel. Ele não é um serviço hospedado de acompanhamento de posições, pesquisa de palavras-chave ou analytics, nem promete posicionamento, indexação ou citações de IA. Para gerar sitemaps XML, usa [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap). Conteúdo, rotas e analytics continuam na sua aplicação.

Comece pela [instalação do Core gratuito](/pt-BR/guide/installation) ou veja abaixo a experiência de uma troca em produção. Pro e a oferta de lançamento estão em [rankbeam.dev](https://rankbeam.dev/pt-BR/).

## Por que não combinar três pacotes com código próprio? {#why-not-three-packages-glue}

Muitas aplicações Laravel têm uma **combinação de componentes de SEO**: um pacote armazena metadados por modelo, outro fornece campos Filament, um terceiro examina páginas e o código da aplicação faz todos concordarem. Cada componente pode funcionar bem. O custo está na integração que a equipe precisa manter.

O caso abaixo descreve uma troca real dessa combinação pela família Rankbeam. Os números pertencem à instalação de referência; não são uma previsão de resultado para qualquer aplicação.

## A aplicação de referência {#the-reference-app}

Um site Laravel real, em produção, com identidade omitida:

- Site de conteúdo **hospitalar e institucional**, com cerca de três meses em produção.
- **Migrado do WordPress**, com aproximadamente 900 páginas no sitemap.
- Cerca de **20 mil visitas por dia**.
- **Laravel 12**, administração **Filament 4**, frontend Blade e MySQL.


A estrutura de SEO anterior:

| Camada | Pacote |
|---|---|
| Metadados por modelo na tabela `seo` | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Campos SEO no Filament | `ralphjsmit/laravel-filament-seo` |
| Varredura de páginas | `backstage/laravel-seo-scanner` |
| Integração entre componentes | **Cerca de 30 classes próprias** |

Na troca relatada, os três pacotes foram removidos, o Rankbeam **Core + Pro + Filament** foi instalado e a suíte SEO da aplicação passou **sem regressões detectadas por esses testes**. As próximas seções mostram qual código de integração foi removido.

## O que a troca removeu {#what-the-swap-deleted}

A substituição eliminou **12 classes próprias**, cujas funções passaram aos pacotes:

| Classe removida | Função anterior | Fornecida agora por |
|---|---|---|
| `Services/SeoService.php` | Ponto de entrada SEO da aplicação | Resolvedor Core e facade `SEO` |
| `Services/SeoWarningEvaluator.php` | Limites de título, descrição e dimensões de imagem | `SEOWarningEvaluator` do Core, compartilhado entre auditoria, prévia e varredura |
| `Services/Seo/SeoAssetInspector.php` | Inspeção de dimensões de imagens locais | `LocalImageInspector` do Core |
| `Jobs/ScanAllPagesSeo.php` | Enfileiramento de varredura do site | [Varredura Pro](/pt-BR/pro/scan-issues) em fila |
| `Jobs/ScanPageSeo.php` | Varredura por página | `PageScanner` do Pro |
| `Jobs/ScanPublicPageSeo.php` | Varredura por página pública | Fluxo de varredura Pro |
| `Models/SeoScanBatch.php` | Registro das execuções | `seo_scan_runs` do Pro |
| `Filament/Pages/SeoDashboard.php` | Painel administrativo de SEO | Plugin `SeoDashboard` do Pro |
| `Filament/Widgets/SeoScanProgressWidget.php` | Progresso das varreduras | Widgets de varredura Pro |
| `Filament/Widgets/SeoTrendChartWidget.php` | Tendência das varreduras | Widgets de varredura Pro |
| `Facades/Seo.php` | Facade sobre o pacote de armazenamento | Facade `SEO` do Core |
| `Console/Commands/RecoverLegacySeoMetadata.php` | Recuperação pontual de metadados | [Importadores](/pt-BR/guide/migrate-from-wordpress) do Core, `seo:import-from` |

::: info O código que permaneceu
A troca inicial **manteve** o rastreador de links quebrados da aplicação: cerca de 17 classes entre tarefa, verificador, gerador de URLs iniciais, resolvedor de origem, dois modelos, dois enums, dois eventos, recurso Filament com três widgets e dois comandos. Também ficaram auxiliares como `CustomSEO`, `EntitySeoSection`, `DynamicSeoDataResolver`, `SitewideSchema` e `SeoKeywords`, totalizando aproximadamente **22 classes adicionais**. As alternativas Rankbeam chegaram depois: [rastreador Pro](/pt-BR/pro/production), edição de **modelo relacionado** e **prévias SERP/social** no Filament, além do **grafo de schema** do Core. Esses recursos permitem substituir também essa superfície, chegando a aproximadamente **três dúzias de classes** no total; isso não significa que todas tenham sido removidas na troca inicial.
:::

O custo identificado está na **integração**: classes próprias que fazem uma alteração de metadados aparecer de forma consistente na varredura, no painel e no head renderizado. Esse código depende dos testes e da manutenção da própria aplicação.

## Comparação lado a lado {#side-by-side}

| Recurso | Combinação de três pacotes com integração própria | Família Rankbeam |
|---|---|---|
| Metadados por modelo | Pacote de metadados | **Core**, `seo_meta`, MIT |
| Armazenamento **por locale** | Geralmente exige integração | **Core**, com coluna de locale em `seo_meta` |
| Campos SEO no Filament | Pacote Filament-SEO | **`laravel-seo-filament`**, MIT |
| Edição SEO de **modelo relacionado** | Adaptar o componente do campo | Resolvedor `target:` próprio para isso |
| Prévia ao vivo de **SERP e redes sociais** | Blade/Alpine próprio | Prévia editorial com abas |
| Renderização sem painel em Inertia, Livewire e JSON | A aplicação de referência usava Blade; outras combinações precisam de integração | **Um resolvedor** para Blade, Inertia, Livewire e JSON, com [contrato testado](/pt-BR/contributing/rendering-contract) |
| Varredura de páginas e ocorrências priorizadas | Pacote de varredura | [Varredura Pro](/pt-BR/pro/scan-issues) e `IssueRegistry` |
| Pontuação de 0 a 100 | Código próprio ou ausente | **Pro**, com [critérios transparentes e versionados](/pt-BR/pro/scoring) |
| Redirecionamentos e recuperação de 404 | Outro pacote ou código próprio | **Pro**, com gerenciador de redirecionamentos e monitor de 404 sem IPs |
| Rastreador de links quebrados | Próprio na aplicação de referência | **Pro**, limitado e retomável |
| **Grafo** JSON-LD | Builder com ligações `@id` próprias | **Core**, com Organization/WebSite/WebPage interligados |
| Sitemaps XML | Pacote de sitemap | **Core**, com registro baseado em `spatie/laravel-sitemap` |
| Importação WordPress, Yoast e Rank Math | Scripts pontuais | **Core**, `seo:import-from` e [roteiro](/pt-BR/guide/wordpress-migration-runbook) |
| **Quem mantém a integração** | **Sua equipe** | Família de pacotes com lançamentos coordenados |

## Três custos da integração própria {#the-three-things-glue-can-t-do-well}

**1 — Uma família com lançamentos coordenados.** Pacotes de mantenedores diferentes têm changelogs e ritmos próprios. A integração absorve as divergências entre eles. Core, Pro e Filament seguem uma [matriz de suporte](#tested-where-it-runs) comum e [limites de atualização](/pt-BR/reference/configuration) documentados, permitindo comunicar mudanças de comportamento de forma coordenada.

**2 — Locale no armazenamento.** `seo_meta` é polimórfico **e** limitado por locale no banco. O SEO multilíngue usa uma linha por `(modelo, locale)`. A [cadeia de resolução](/pt-BR/concepts/resolver-precedence) lê o locale ativo sem exigir uma tabela de integração adicional.

**3 — Um resolvedor para diferentes frontends.** O Rankbeam resolve `SEOData` tipado e transforma os **mesmos dados** em HTML, conteúdo de `Head` do Inertia ou array JSON. O [contrato de renderização](/pt-BR/contributing/rendering-contract) cobre [Blade](/pt-BR/guide/blade), [Inertia](/pt-BR/guide/inertia-json) com Vue, React ou Svelte e [Livewire](/pt-BR/guide/livewire). O painel administrativo é opcional; os recursos Pro também funcionam [pela CLI](/pt-BR/pro/headless).

## O que fica fora do Core gratuito {#what-is-honestly-not-in-the-free-core}

A divisão open-core permite saber o que cada pacote oferece antes de instalá-lo:

| Pacote | Licença | Conteúdo |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, gratuito** | Resolução de metadados, grafo JSON-LD, sitemaps, `seo:audit` gratuito e importadores |
| `rankbeam/laravel-seo-filament` | **MIT, gratuito** | Campos e seções Filament com gravação em `seo_meta` |
| `rankbeam/laravel-seo-pro` | **Comercial** | Varreduras em fila, ocorrências priorizadas, pontuação de 0 a 100, redirecionamentos, monitor de 404, rastreador de links, Search Console, assistência de IA e painel Filament |

A camada paga reúne **auditoria técnica e monitoramento do site**. O mecanismo de metadados, grafo de schema, sitemaps e auditoria gratuita dentro da aplicação continuam sob MIT.

Duas propriedades dessa divisão:

- **Sem verificação de licença durante a execução.** Pro é licenciado por projeto na instalação. Não consulta um servidor de licença nem possui um mecanismo remoto de desativação. A telemetria operacional é **local**, destinada aos seus logs, e pode ser desativada; não é enviada ao Rankbeam.
- **IA com sua chave.** A [assistência opcional](/pt-BR/pro/ai-assist) usa sua conta Anthropic, OpenAI, Google ou servidor de modelo local. O Rankbeam não intermedeia nem revende as chamadas. O recurso vem desativado.

::: tip Quando a combinação atual continua suficiente
Para poucas páginas estáticas com um `<title>` e uma descrição, um gerador de tags pode bastar. O Rankbeam passa a justificar a integração quando o SEO é **armazenado, multilíngue, associado a modelos, usado sem painel e auditado**, e a ligação entre pacotes já exige código próprio relevante.
:::

## Migração do WordPress com verificação antes da troca {#the-lowest-risk-switch-off-wordpress}

A aplicação de referência veio de uma migração WordPress com cerca de 900 páginas e metadados acumulados no Yoast ou Rank Math. O procedimento preserva a origem até a verificação:

1. **Mantenha os sistemas lado a lado.** Prepare o Rankbeam sem remover o site ativo.
2. **Importe, primeiro em simulação.** `seo:import-from yoast`, `rank-math` ou `wordpress-csv` lê títulos, descrições, canonicals, robots, palavras-chave e valores sociais. Os importadores são **idempotentes** e **preenchem apenas campos vazios por padrão**. Sem `--overwrite`, preservam os valores já definidos; `--dry-run` não grava dados.
3. **Transfira os redirecionamentos.** O Core gera um CSV versionado. `seo-pro:redirects-import` valida cada linha e rejeita loops, destinos inseguros e duplicatas antes de gravar.
4. **Verifique antes de excluir.** `seo:audit --strict` retorna erro se houver qualquer ocorrência e pode servir como condição de CI ou troca. O banco WordPress permanece intacto até você decidir removê-lo.

O procedimento completo está no [roteiro de migração do WordPress](/pt-BR/guide/wordpress-migration-runbook); campos e tokens estão em [Migração do WordPress](/pt-BR/guide/migrate-from-wordpress). Se a origem for um pacote **Laravel**, como ralphjsmit, artesaos ou Spatie, consulte [a migração entre pacotes](/pt-BR/guide/migrate-from-other-packages).

## Como funciona em escala? {#does-it-hold-up-at-scale}

As duas exigências da aplicação de referência — resolver metadados em cerca de 20 mil requisições diárias e rastrear aproximadamente 900 páginas — têm benchmarks na suíte. Eles verificam propriedades **determinísticas**, como quantidade de consultas e limites de tarefas, sem prometer tempos absolutos em qualquer servidor.

**Cache do resolvedor: um acerto com cache aquecido não consulta o banco.** Com o cache opcional ativado, um resultado armazenado evita toda a cadeia de resolução. O benchmark executa 25 resoluções do mesmo modelo:

| | Consultas ao banco |
|---|---|
| Sem cache, com nova leitura de `seo_meta` a cada resolução | **≥ 25** |
| Acerto no cache aquecido | **0** |

O cache vem **desativado**. A invalidação acompanha alterações em `seo_meta`, campos de conteúdo e padrões. Consulte [Configuração: cache](/pt-BR/reference/configuration).

**Rastreador de links: tarefas limitadas em 900 páginas.** O benchmark processa um conjunto gerado de aproximadamente 900 páginas pela tarefa real:

- Conclusão em **pelo menos 18 tarefas limitadas**, com 50 páginas por tarefa.
- **Nenhuma tarefa** ultrapassa seu limite de 50 páginas.
- **1.800 links** verificados, com os alvos mortos transformados em ocorrências persistentes de links quebrados confirmados nas condições do teste.

O rastreamento tem limites por execução e orçamento de tempo por tarefa, validação SSRF na busca inicial **e em cada salto de redirecionamento**, além de uma concessão no banco que mantém uma execução ativa por escopo. Veja a operação no [guia de produção](/pt-BR/pro/production).

## Testado nas versões compatíveis {#tested-where-it-runs}

Uma matriz comum para a família:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11, 12 e 13.
- **Filament** 4 e 5.

## Vale manter a integração entre três pacotes? {#so-—-why-glue-three-packages-together}

Se a combinação atual exige mais de uma dúzia de classes próprias, adaptação por frontend e tratamento manual de locales, compare esse custo com os recursos compartilhados do Rankbeam. O caso de 900 páginas e cerca de 20 mil visitas diárias mostra uma substituição concreta; a decisão na sua aplicação depende do código que ela poderá remover e das verificações que passar.

Comece pelo [início rápido](/pt-BR/guide/quickstart), que vai do `composer require` ao `<head>` renderizado em um exemplo de cinco minutos.

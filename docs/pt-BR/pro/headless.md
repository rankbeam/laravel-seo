---
description: "Gerencie Pro inteiramente por artisan: varreduras, redirecionamentos, registros de 404, diagnósticos e manutenção, sem depender de Filament."
---

# Uso sem painel {#headless-usage}

As funções de Pro, incluindo varreduras, redirecionamentos e registros de 404, pertencem ao mecanismo e não exigem Filament. O painel é uma interface de gerenciamento; estes comandos oferecem o acesso sem essa interface.

## Referência de comandos {#command-reference}

### Instalação e diagnóstico {#setup-health-check}

| Comando | Função |
|---|---|
| `seo-pro:install` | Publica `config/seo-pro.php` e as migrações de Pro, executa-as e mostra os próximos passos; aceita `--no-migrate` e `--force` |
| `seo:doctor` | Verifica URL da aplicação, tabelas do núcleo e de Pro, alvos, sitemap, filas por tarefa, funções opcionais e estado operacional; indica correções e aceita `--json` para monitoramento |

`seo-pro:install` é o caminho documentado de instalação. As migrações de Pro precisam ser publicadas e nunca são carregadas automaticamente pelo pacote. O instalador transforma um simples `composer require` em um esquema utilizável e pode ser executado novamente. Use `--force` apenas se quiser sobrescrever arquivos publicados.

`seo:doctor` não faz chamadas de rede nem mostra segredos: a verificação de IA só informa se a variável de chave está definida. Ele valida configuração e histórico recente, sem comprovar que um cron ou worker externo esteja executando. Retorna código diferente de zero apenas para falhas críticas, como uma tabela obrigatória ausente; avisos em desenvolvimento local não causam falha. `--json` fornece um `id` estável por verificação. Execute após [instalar](/pt-BR/pro/installation) e em CI.

### Varreduras {#scanning}

| Comando | Função |
|---|---|
| `seo-pro:scan` | Enfileira a varredura de todos os alvos; `--sync` executa no processo atual. As opções de **verificação em CI** `--fail-on-error`, `--fail-on-warning`, `--report=` e `--format=json\|md\|html` exigem `--sync` |
| `seo-pro:scan-status` | Resumo da última execução e ocorrências abertas, da maior para a menor gravidade; `--limit=20`, `--severity=critical\|warning\|notice` |
| `seo-pro:scan-recover` | Marca como falhas as execuções abandonadas por um worker encerrado |
| `seo-pro:scan-prune` | Exclui execuções concluídas e suas ocorrências após o prazo de retenção |

### Rastreador de links quebrados {#broken-link-crawler}

Desativado por padrão. Ative `seo-pro.broken_links.enabled` e aplique as migrações das duas tabelas, publicadas por `seo-pro:install`. O rastreamento é dividido em trabalhos de fila com limites definidos; use um worker dedicado. Consulte os ajustes na [configuração de produção](/pt-BR/pro/production).

| Comando | Função |
|---|---|
| `seo-pro:broken-links-scan` | Enfileira um rastreamento limitado e retomável; `--scope=internal_only\|internal_and_external` e `--url=*` para URLs iniciais adicionais |
| `seo-pro:broken-links-status` | Resumo do último rastreamento, ocorrências abertas e [inspeções tipadas](/pt-BR/pro/broken-links#typed-link-inspections); aceita `--fail-on-error`, `--fail-on-warning`, `--report=` e `--format=` para CI |
| `seo-pro:broken-links-cancel` | Cancela um rastreamento em execução ou na fila; `{run?}` usa o ativo mais recente por padrão |
| `seo-pro:broken-links-recover` | Marca como falhos rastreamentos abandonados por um worker, com concessão de execução expirada |
| `seo-pro:broken-links-prune` | Aplica a retenção a execuções antigas e ocorrências resolvidas |

### Redirecionamentos e 404 {#redirects-404s}

| Comando | Função |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Cria uma regra; opções `--code=301`, `--regex`, `--no-preserve-query` e `--note=` |
| `seo-pro:404-list` | Lista 404 por frequência; `--status=new\|ignored\|redirected\|all`, `--limit=20` |
| `seo-pro:redirects-flush-hits` | Grava no banco os contadores de redirecionamento acumulados em cache quando `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Remove registros antigos de 404 e aplica o limite de linhas |

### Lista de verificação da página {#on-page-checklist}

| Comando | Função |
|---|---|
| `seo-pro:checklist {model} {id}` | Lista pass/warn/fail com análise de palavras-chave para um modelo; `--json`, `--strict`, `--locale=`. Consulte a [lista de verificação](/pt-BR/pro/on-page-checklist) |

A mesma análise está disponível em `SeoPro::checklistFor($model)`. Ela orienta a edição de palavras-chave, comprimento, imagens e links internos e é separada da [pontuação SEO](/pt-BR/pro/scoring).

### Search Console, somente leitura {#search-console-read-only}

| Comando | Função |
|---|---|
| `seo-pro:search-console` | Páginas com ocorrências abertas **e** tráfego de busca, começando pelas oportunidades problemáticas; `--view=attention` é o padrão |
| `seo-pro:search-console --view=pages` | Principais páginas por impressões, cliques, CTR e posição |
| `seo-pro:search-console --view=queries` | Principais consultas; `--days=`, `--limit=`, `--json` |

As métricas também estão disponíveis em `SeoPro::searchConsole()`. Consulte [Search Console](/pt-BR/pro/search-console). A integração é desativada por padrão e estritamente de leitura.

### Assistência de IA {#ai-assist}

| Comando | Função |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Sugestões de título e descrição em JSON; `--field=title\|description\|all`. Consulte [assistência de IA](/pt-BR/pro/ai-assist) |
| `seo-pro:ai-suggest --issue={id}` | Explicação de uma correção em linguagem simples, como JSON |

### Resolver um 404 em uma etapa {#resolving-a-404-in-one-step}

`--from-404={path}` equivale à ação de criar redirecionamento do monitor: cria a regra **e** marca o registro correspondente como redirecionado, vinculando-o à regra nova.

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

O comando usa os mesmos validadores do formulário Filament. Expressões regulares inválidas, valores grandes demais e destinos externos fora da lista permitida são rejeitados antes de qualquer gravação.

## Agendamento recomendado {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

A [configuração de produção](/pt-BR/pro/production) recomenda a frequência de cada comando recorrente e descreve filas, workers, reintentos, recuperação e retenção. Também documenta a **telemetria** estruturada das execuções concluídas: páginas recuperadas, links verificados, URLs bloqueadas, duração e atraso da fila.

## O que exige a interface Filament? {#what-needs-the-filament-ui}

O mecanismo completo — processamento de varreduras, ocorrências, correspondência de redirecionamentos, registros de 404, limpeza e recuperação — é o mesmo com ou sem Filament. O painel acrescenta visualizações: progresso e estatísticas por gravidade, filtros e detalhes por página, botões para ignorar e reabrir ocorrências, formulários de redirecionamentos e tabela de 404 com sua ação de correção. Ignorar e reabrir ocorrências ainda não têm um comando específico. Use o painel ou `SEOScanIssue::markIgnored()` / `reopen()` no tinker ou no seu código.

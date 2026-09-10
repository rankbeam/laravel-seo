---
description: "Agende varreduras SEO completas e acompanhe ocorrências novas, reabertas e resolvidas, ordenadas por impacto, no painel e em um e-mail opcional."
---

# Agendamento e comparação de varreduras {#scan-scheduling-delta}

Execute uma varredura SEO completa **em um horário programado** e veja **o que mudou desde a anterior**: ocorrências novas, que voltaram ou que foram resolvidas. Os resultados aparecem por impacto no painel e, opcionalmente, em um resumo por e-mail.

O agendamento e a comparação trabalham juntos: o resumo das mudanças dá contexto ao resultado periódico.

## O que mudou desde a última varredura {#what-changed-since-the-last-scan}

Cada execução concluída registra um snapshot do **conjunto de ocorrências abertas** em `seo_scan_run_issues`. Comparar os snapshots de duas execuções produz três grupos:

- **Novas:** não estavam abertas antes, estão abertas agora e nunca estiveram abertas em uma execução anterior.
- **Reabertas:** tinham sido resolvidas e **voltaram**. Não significa aumento de gravidade, que é fixa por tipo; corresponde à reabertura do [ciclo de vida da ocorrência](/pt-BR/pro/scan-issues#issue-lifecycle).
- **Resolvidas:** estavam abertas na execução anterior e deixaram de estar.

Cada grupo é [ordenado por impacto](#impact-ordering).

### Por que registrar snapshots {#why-a-snapshot-not-the-issues-table}

As ocorrências seguem um [ciclo de resolução e reabertura](/pt-BR/pro/scan-issues#issue-lifecycle). A mesma linha é atualizada entre varreduras, e `scan_run_id` passa a indicar a execução mais recente em que ela permaneceu aberta. Isso preserva uma identidade durável, mas a tabela atual não informa quais ocorrências estavam abertas ao final de uma execução antiga.

Por isso, cada execução registra o conjunto aberto por uma **identidade estável**, `issue_type | target | field`, também usada pelo [relatório com sua marca](/pt-BR/pro/reports). A comparação opera sobre dois conjuntos congelados e funciona entre **quaisquer duas execuções**, não apenas consecutivas.

### Casos que exigem interpretação {#edge-cases-handled-honestly}

- **Uma página sai dos alvos.** Suas ocorrências não são verificadas novamente, continuam abertas e entram nos snapshots seguintes como **ainda abertas**. Parar de observar uma página não é tratado como correção.
- **Uma verificação é desativada entre execuções.** Suas ocorrências deixam de ser emitidas, o ciclo de vida as marca como resolvidas e elas saem do conjunto aberto. Aparecem como **resolvidas**, refletindo a avaliação atual do scanner. Não há, no nível da ocorrência, uma distinção entre corrigir o problema e desativar a verificação.
- **Primeira execução após atualizar.** Execuções antigas sem snapshot não são escolhidas como referência. O primeiro snapshot vira uma **linha de base**, com estado atual e sem diferenças; o site inteiro não aparece falsamente como novo. A comparação começa no segundo snapshot.

### No painel {#on-the-dashboard}

O widget **What changed since the last scan**, no [painel SEO](/pt-BR/pro/installation), mostra contagens e principais ocorrências novas, reabertas e resolvidas, por impacto, comparando as duas últimas execuções concluídas. Antes de existirem dois snapshots, exibe uma nota de linha de base.

## Ordenação por impacto {#impact-ordering}

Cada grupo usa uma pontuação de **impacto** para ordenar as ocorrências:

```
impact = severity_weight × page_importance
```

- **severity_weight** usa os pesos dos [critérios de pontuação](/pt-BR/pro/scoring): `critical` vale 40, `warning` 15 e `notice` 5. São pesos definidos pelo produto para representar a importância de cada tipo de ocorrência.
- **page_importance** utiliza as **impressões de busca observadas** em [Search Console](/pt-BR/pro/search-console) para diferenciar páginas:

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  As impressões usam escala logarítmica e são normalizadas pela página com mais impressões. Uma página com dez vezes mais impressões não recebe dez vezes mais importância. Isso adapta a fórmula a blogs pequenos e catálogos grandes. O `<priority>` do sitemap é um sinal secundário fraco: costuma estar ausente ou ser uniforme. Quando há prioridades por tipo em `seo.sitemap.models`, elas ajustam o resultado.

**Sem histórico de Search Console nem prioridades configuradas**, `page_importance` vale 1 para todas as páginas, e a ordenação considera apenas a **gravidade**. Não inventa demanda. Sincronize o [histórico GSC](/pt-BR/pro/search-console#historical-metrics) com `seo-pro:gsc-sync` para incluir a ponderação por impressões.

Ajuste pesos e janela em `seo-pro.scan.delta.impact`.

## Agendar uma varredura {#scheduling-a-scan}

O pacote **não agenda nada por padrão**. Para ativar:

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

Ou defina uma expressão cron completa, que tem prioridade sobre `frequency`:

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

O pacote registra `seo-pro:scan` no agendador Laravel com `withoutOverlapping`, impedindo sobreposição da execução do **comando agendado**. Esse bloqueio não cobre todo o tempo de vida dos trabalhos enfileirados. O registro só acontece no contexto de console ou agendador e não acrescenta trabalho às requisições web.

::: warning O agendador precisa estar executando
O agendamento do pacote depende do agendador Laravel, normalmente o cron `* * * * * php artisan schedule:run` ou `php artisan schedule:work` em desenvolvimento. Consulte [produção](/pt-BR/pro/production#scheduler).
:::

Para configurar por conta própria, mantenha `schedule.enabled` desativado e agende no seu console kernel. A comparação e o resumo continuam funcionando:

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` executa a varredura no processo atual em vez de enfileirar um trabalho por alvo. É útil em um site pequeno sem worker; mantenha a execução em fila em produção.

## Resumo por e-mail {#summary-e-mail}

Ative o resumo de **mudanças desde a última varredura** para receber um e-mail HTML com sua marca e ocorrências novas, reabertas e resolvidas, ordenadas por impacto:

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

O resumo reutiliza marca e configuração de envio dos [relatórios](/pt-BR/pro/reports): nome, logo e cor. Se não houver destinatários próprios, usa os destinatários dos relatórios. `only_on_change` omite o e-mail quando nada mudou; o primeiro resumo de linha de base sempre é enviado.

O envio só acontece em execuções iniciadas com `--notify`, adicionado automaticamente pelo agendador quando `notify.enabled` está ativo. Um `seo-pro:scan` manual **sem `--notify`** não envia e-mail.

::: tip Outros canais
Para Slack, webhook ou um resumo próprio, escute `Rankbeam\Seo\Pro\Events\SeoScanCompleted`. O evento é emitido uma vez por execução concluída e contém a execução. Construa a comparação com `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` e encaminhe-a pelo canal escolhido.
:::

## Retenção {#retention}

Os snapshots são excluídos em cascata com suas execuções. [`seo-pro:scan-prune`](/pt-BR/pro/production#scheduler) já aplica a retenção, sem exigir outro agendamento. Uma execução só é removida quando não tem ocorrências abertas, mantendo a referência recente disponível para comparação.

Para desativar snapshots, comparação e resumo, use `seo-pro.scan.delta.snapshot => false`.

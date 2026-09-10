---
description: "Rastreador limitado e retomável para links internos quebrados, corrigíveis por redirecionamento, e links externos opcionais. Desativado por padrão."
---

# Rastreador de links quebrados {#broken-link-crawler}

Um **rastreador limitado e retomável** percorre o site, segue os links de cada página e registra os que não resolvem. Ele cobre links **internos**, como rotas inexistentes que podem ser corrigidas criando um redirecionamento, e opcionalmente links **externos**. Vem **desativado por padrão**.

Três características orientam seu funcionamento:

- **Execução limitada e retomável.** O rastreamento usa pequenas tarefas em fila, cada uma com limite de páginas, que enfileiram a continuação até terminar ou atingir os limites. A execução completa também é limitada a 2.000 páginas por padrão. `null` remove explicitamente esse limite, mas preserva os limites de lote e tempo. Ajuste limites e intervalos à capacidade do site e dos servidores.
- **Escopo restrito por padrão.** `internal_only` verifica apenas links no próprio servidor, sem requisições a terceiros. Toda busca, interna ou externa, passa pelo **SsrfGuard**, com protocolos permitidos, escopo de servidores e rejeição de endereços privados. Links externos exigem ativação explícita e continuam sujeitos à proteção.
- **Independente da pontuação de SEO.** As ocorrências ficam em tabelas próprias, sem gravar em `seo_scan_issues` nem alterar a pontuação de 0 a 100. Links quebrados são acompanhados separadamente como um problema operacional.

## Recursos disponíveis {#what-you-get}

No painel Filament, quando o rastreador está ativado:

- **Resumo de links quebrados**: contagens abertas, separadas entre internos e externos, e último rastreamento, com acesso à tabela de ocorrências.
- **Progresso do rastreamento**: páginas visitadas, links verificados e links quebrados encontrados.
- **Links quebrados por varredura**: tendência das execuções recentes.
- **Recurso de ocorrências**: cada ligação `origem → destino` quebrada, com filtros e criação de redirecionamentos para as internas.

Sem painel, os mesmos dados estão disponíveis pelos comandos `seo-pro:broken-links-*`.

## Por que vem desativado {#why-it-s-off-by-default}

O rastreador **faz requisições de rede** e precisa de infraestrutura. Sua ativação é explícita:

- As duas tabelas principais usam migrações **apenas publicadas**, como todas as migrações Pro. Execute-as antes de a interface consultar os dados. As inspeções tipadas também usam `seo_broken_link_inspections`.
- O rastreamento entra em uma **fila dedicada** e exige um **worker**. Sem worker, não há progresso.
- A confirmação depende de **rastreamentos consecutivos**, conforme a seção abaixo. O recurso foi projetado para execução **agendada**, não para confirmar todos os links quebrados no instante em que é ativado.

## Configuração {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Execute as migrações. `seo-pro:install` publica e executa todas as migrações Pro; o comando é idempotente e pode ser repetido:

```bash
php artisan seo-pro:install
```

Inicie um **worker dedicado** para `seo-broken-links`, evitando que um rastreamento demorado atrase tarefas voltadas ao usuário:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Confira a configuração. `seo:doctor` verifica a ativação, as tabelas e se a fila usa uma conexão real, diferente de `sync`, e informa as correções necessárias:

```bash
php artisan seo:doctor
```

O [guia de produção](/pt-BR/pro/production) descreve Redis, Supervisor, conexões dedicadas e ajuste de lotes.

## Executar um rastreamento {#running-a-crawl}

Use **Scan now** no painel ou execute pela CLI:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Os comandos apenas **enfileiram** o rastreamento; o worker executa o trabalho.

## Quando um link é marcado como quebrado {#how-a-link-gets-flagged}

O link só é confirmado como quebrado depois de falhar em `seo-pro.broken_links.mark_broken_after_failures` **rastreamentos consecutivos**, **3** por padrão. Qualquer sucesso zera o contador. Com esse padrão, uma indisponibilidade isolada não confirma o problema, por isso a execução deve ser **recorrente**. Com frequência semanal, três falhas confirmam o link cerca de duas semanas após a primeira observação, ou até cerca de três semanas após ele quebrar. Aumente a frequência ou diminua o limite para confirmar mais rápido.

## Inspeções de links por tipo {#typed-link-inspections}

Além da disponibilidade, cada link passa por **inspeções tipadas** de sua URL e marcação: barra final inconsistente, codificação, cadeias de redirecionamento, `javascript:`, âncoras inexistentes, textos pouco descritivos e outros casos. Cada inspeção tem uma **gravidade** fixa — `critical`, `warning` ou `notice`, como nas [ocorrências de varredura](/pt-BR/pro/scan-issues) — e é registrada por execução em `seo_broken_link_inspections`. Diferentemente da ocorrência persistente de link quebrado, que exige confirmação em várias execuções, a inspeção é um retrato da execução atual e aparece **já no primeiro rastreamento**, permitindo usá-la como condição de CI.

### Referência das inspeções {#inspection-reference}

| Inspeção | Gravidade | O que sinaliza | Aplicação |
| --- | --- | --- | --- |
| `broken_link` | critical | Destino retornou HTTP ≥ 400 | Qualquer link |
| `redirect_chain` | notice · warning | Destino resolve por redirecionamento; vira `warning` acima de `redirect_chain_warning_hops` | Qualquer link |
| `link_unreachable` | notice | Destino inacessível nesta execução por rede, timeout ou bloqueio; pode ser temporário | Qualquer link |
| `insecure_link` | warning | Link `http://` em site `https`, reduzindo a proteção do transporte | Qualquer link |
| `trailing_slash` | notice | Caminho interno não segue a convenção de barra final; **desativada até definir `trailing_slash`** | Internos |
| `double_slash_url` | warning | Caminho interno contém `//`, um segmento vazio | Internos |
| `duplicate_query_param` | notice | Chave repetida, como `?a=1&a=2`; sintaxe de array `key[]` é aceita | Internos |
| `non_ascii_url` | notice | Caminho interno contém caracteres não ASCII sem codificação | Internos |
| `uppercase_url` | notice | Caminho interno tem maiúsculas; revisar variantes servidas separadamente | Internos |
| `underscore_in_url` | notice | Caminho interno usa sublinhados; hífens são o separador preferido para palavras em URLs | Internos |
| `javascript_link` | warning | Âncora usa `javascript:`, sem um destino normalmente rastreável | Qualquer âncora |
| `missing_fragment` | warning | `#fragment` da própria página não tem `id` ou `name` correspondente | Mesma página |
| `non_descriptive_anchor` | notice | Texto genérico, como “clique aqui” ou “leia mais”, ou uma URL sem descrição | Qualquer âncora |
| `absolute_internal_link` | notice | Link interno usa URL absoluta em vez de caminho relativo à raiz | Internos |

As inspeções de convenções da URL, como barra final, caixa, codificação e barra dupla, aplicam-se apenas a links **internos**. Redirecionamento, falha HTTP, indisponibilidade e transporte inseguro são verificados em qualquer link incluído no escopo. Rotas internas do framework e arquivos estáticos configurados são ignorados para reduzir ruído; veja `exclude_paths` e `exclude_extensions` abaixo.

Cada link é buscado pela **URL exata escrita no conteúdo**, removendo apenas `#fragment`. Assim, um redirecionamento de `/about/` para `/about` é observado como `redirect_chain`, em vez de desaparecer numa normalização antecipada. Cada forma distinta na página é inspecionada: `/page#ok` e `/page#missing`, ou `/a//b` e `/a/b`, são avaliadas. A ocorrência persistente de link quebrado ainda reúne aliases do destino em uma identidade. As inspeções usam `(página, destino, inspeção)` como chave; várias âncoras inválidas para o mesmo destino produzem uma linha `missing_fragment` com um exemplo, não uma linha por âncora.

### Ajustar as inspeções {#tuning-the-taxonomy}

As opções ficam em `seo-pro.broken_links.inspections`:

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

Para **desativar uma regra**, remova sua classe de `rules`. Para **desativar todas as inspeções tipadas**, use `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`. Duas regras merecem atenção:

- `trailing_slash` fica **desativada até você declarar uma convenção**, `'always'` ou `'never'`. Se `/x` e `/x/` retornam 200, o pacote não escolhe uma convenção por você. Quando o servidor redireciona uma forma para a outra, `redirect_chain` já registra isso.
- `absolute_internal_link` sinaliza **todo** link interno absoluto. Se essa é a convenção do site, pode gerar muitas ocorrências inofensivas de nível `notice`; remova a regra para silenciá-las.

## Integração contínua {#continuous-integration}

O rastreamento de links e a [varredura SEO](/pt-BR/pro/scan-issues) podem **reprovar o build** e **gravar um relatório**. `--fail-on-error` corresponde a `critical`; `--fail-on-warning` reprova com `critical` **ou** `warning`. Não existe uma gravidade separada chamada “error”.

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

`--report=<file|dir>` grava o artefato; se o destino for um diretório, o nome é gerado. `--format` aceita `json`, o padrão, `md` ou `html`. Use JSON para processamento na pipeline e HTML para anexar uma página independente à execução.

### GitHub Actions {#github-actions}

O rastreador consulta páginas por HTTP. A CI precisa apontar para conteúdo acessível, seja uma aplicação servida localmente como no exemplo, seja uma URL de staging em `SEO_PRO_BROKEN_LINKS_BASE_URL`. Registre modelos ou sitemap para fornecer as URLs iniciais.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## Agendamento {#scheduling}

Registre o rastreamento e a manutenção em `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Referência dos comandos {#command-reference}

| Comando | Função |
| --- | --- |
| `seo-pro:broken-links-scan` | Enfileira rastreamento limitado e retomável; `--scope=internal_only\|internal_and_external` e URLs iniciais adicionais com `--url=*` |
| `seo-pro:broken-links-status` | Resumo mais recente, ocorrências abertas e contagens de inspeções desta execução; **condição de CI** com `--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>` e `--format=json\|md\|html` |
| `seo-pro:broken-links-cancel` | Cancela execução em andamento ou enfileirada; `{run?}` usa a ativa mais recente por padrão |
| `seo-pro:broken-links-recover` | Marca como falhos rastreamentos abandonados por um worker, com concessão desatualizada |
| `seo-pro:broken-links-prune` | Aplica a retenção de execuções antigas e ocorrências resolvidas |

## Ajustes {#tuning}

Limites de páginas por execução, links por página, trabalho por tarefa, orçamento de tempo e intervalos por servidor ficam em `seo-pro.broken_links`. Os padrões são finitos e conservadores. Consulte a [tabela de ajuste de lotes](/pt-BR/pro/production) antes de aumentá-los.

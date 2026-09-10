---
description: "Pro em produção: filas dedicadas, agendamento, novas tentativas, recuperação, retenção e telemetria, com uma configuração independente do Filament para cerca de 900 páginas."
---

# Configuração de produção {#production-setup}

As tarefas rotineiras do Pro — varreduras do site, rastreamento de links quebrados, gravação opcional de contadores de redirecionamento e limpeza de registros 404 — usam as filas e o agendador do Laravel. Este guia reúne a configuração para operar em escala: filas dedicadas, agendamento, políticas de novas tentativas e recuperação, retenção e telemetria. A instalação de referência tem cerca de 20 mil visitas por dia e 900 páginas; a configuração está descrita para que você possa reproduzi-la.

Tudo aqui é **independente do Filament**. O mecanismo, os comandos, as filas e a telemetria funcionam da mesma forma com ou sem painel. O Filament acrescenta as telas, sem alterar o agendamento nem o processamento.

[[toc]]

## Ordem segura de implantação {#safe-rollout-order}

1. **Instale** o pacote e publique os arquivos:

   ```bash
   php artisan seo-pro:install
   ```

   O instalador publica a configuração e as migrações do Pro e executa as migrações, salvo quando você passa `--no-migrate`. As migrações do Pro são **apenas publicadas**, sem carregamento automático: este é o passo que transforma um simples `composer require` em um esquema funcional. O comando é idempotente e pode ser executado novamente. Use `--force` para sobrescrever os arquivos publicados ou `--no-migrate` para publicar sem migrar.

2. **Registre os alvos da varredura** em um service provider (`AppServiceProvider::boot()`):

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Verifique** a configuração antes de ativar o processamento em segundo plano:

   ```bash
   php artisan seo:doctor
   ```

   Corrija cada aviso; a saída informa o comando ou a linha de configuração correspondente. Em CI, acrescente `--json` e use os identificadores estáveis das verificações.

4. **Configure as filas e o agendador** conforme as seções abaixo. Implante um worker de fila e uma entrada cron para `schedule:run`.

5. **Ative os recursos opcionais por último**. O rastreador de links quebrados, a assistência de IA e o Search Console vêm desativados. O rastreador precisa das próprias tabelas migradas — o passo 1 já publicou as migrações — e de um worker dedicado.

## Filas dedicadas por tipo de trabalho {#dedicated-queues-per-workload}

Uma varredura ou um rastreamento demorado não deve atrasar tarefas voltadas ao usuário, como e-mails e notificações. Use uma fila e um worker próprios para cada tipo de trabalho de SEO.

A varredura e o rastreador de links quebrados leem suas respectivas configurações de fila:

| Trabalho | Configuração | Variável de ambiente | Fila padrão |
|---|---|---|---|
| Tarefas de varredura on-page | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | a fila padrão da aplicação |
| Tarefas de rastreamento de links quebrados | `seo-pro.broken_links.queue.name` (+ `.connection`) | `SEO_PRO_BROKEN_LINKS_QUEUE` (+ `_CONNECTION`) | `seo-broken-links` |

### Exemplo com Redis (configuração de produção) {#redis-example-the-production-topology}

No `.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Execute um worker por fila, cada um em um processo ou programa do Supervisor separado:

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

O `--timeout` do worker de rastreamento deve ser maior que `seo-pro.broken_links.batch.hard_time_budget_seconds` (180 por padrão) somado ao timeout HTTP, para evitar que o lote seja interrompido durante a atualização do seu estado. A tarefa define seu próprio `$timeout` como essa soma; alinhe a opção do worker a esse valor. Use `--tries=1` no rastreamento: a próxima continuação, ou `seo-pro:broken-links-recover`, recupera uma tarefa interrompida, dispensando novas tentativas no nível da fila.

`seo:doctor` informa a fila de cada tipo de trabalho e avisa quando alguma usa `sync`, que executaria o trabalho na mesma chamada e bloquearia a resposta.

## Agendador {#scheduler}

No Laravel 11, 12 e 13, configure o agendamento em **`routes/console.php`**. O método `schedule()` de `app/Console/Kernel.php` continua presente em aplicações atualizadas a partir do Laravel 10; se for o seu caso, coloque as mesmas entradas nele. Adicione uma única entrada ao cron do sistema para acionar o agendador a cada minuto:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Depois registre os comandos recorrentes com a frequência recomendada:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Referência das frequências:

| Comando | Frequência | Motivo |
|---|---|---|
| `seo:sitemap` | diária | Atualizar o sitemap com o conteúdo atual |
| `seo-pro:scan` | semanal; diária se o conteúdo muda com frequência | Auditar novamente todos os alvos |
| `seo-pro:scan-recover` | a cada hora | Recuperar execuções interrompidas pela falha de um worker |
| `seo-pro:scan-prune` | diária | Aplicar a retenção das execuções de varredura |
| `seo-pro:redirects-flush-hits` | a cada 5 minutos, somente com `redirects.hits.flush_immediately=false` | Gravar no banco os contadores acumulados no cache |
| `seo-pro:404-prune` | diária | Limitar o registro de 404 por idade e quantidade de linhas |
| `seo-pro:404-recheck` | diária | Consultar novamente os caminhos 404 abertos e marcar como recuperados os que agora retornam 200 |
| `seo-pro:broken-links-scan` | semanal | Rastrear novamente os links; a confirmação depende de varreduras consecutivas |
| `seo-pro:broken-links-recover` | a cada hora | Recuperar rastreamentos interrompidos pela falha de um worker |
| `seo-pro:broken-links-prune` | diária | Aplicar as janelas de retenção do rastreador |

`seo-pro:scan` e `seo-pro:broken-links-scan` apenas **enfileiram** o trabalho; o worker o executa. Os comandos de recuperação e limpeza são executados na própria chamada e têm baixo custo.

::: tip Confirmação de links quebrados entre varreduras
Um link só é marcado como quebrado depois de falhar em `seo-pro.broken_links.mark_broken_after_failures` **varreduras consecutivas**. Qualquer sucesso zera o contador. Por isso, o rastreamento deve ser recorrente: com o limite padrão, uma única indisponibilidade temporária não marca o link. O padrão de 3 falhas, com varreduras semanais, confirma o problema cerca de duas semanas após a primeira falha observada, ou até cerca de três semanas após o link quebrar. Aumente a frequência ou diminua o limite se precisar de confirmação mais rápida.
:::

## Ajuste dos lotes (rastreador de links quebrados) {#batch-tuning-broken-link-crawler}

O rastreamento é dividido em tarefas limitadas que enfileiram a própria continuação. Os padrões impõem limites; ajuste-os à capacidade do site e dos servidores consultados em `seo-pro.broken_links`:

| Chave | Padrão | O que limita |
|---|---|---|
| `max_pages_per_run` | `2000` | Páginas buscadas em toda a execução. `null` ativa explicitamente a execução sem limite; nunca é o padrão |
| `max_links_per_page` | `200` | Links verificados por página |
| `max_total_links` | `null` | Limite global opcional de verificações de links por execução |
| `batch.max_pages_per_job` | `50` | Páginas por tarefa enfileirada |
| `batch.max_links_per_job` | `1500` | Verificações de links por tarefa enfileirada |
| `batch.hard_time_budget_seconds` | `180` | Depois desse tempo, a tarefa **não inicia novas buscas** e enfileira uma continuação |
| `batch.dispatch_delay_seconds` | `1` | Espera entre tarefas de continuação |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Limites por requisição |
| `http.max_response_bytes` | herda `seo-pro.http.max_response_bytes` | Limite aplicado durante a leitura dos corpos de resposta de páginas e alvos |
| `seed.max_response_bytes` | herda o limite HTTP do rastreador ou compartilhado | Bytes brutos de XML ou `.gz` buscados ao carregar os sitemaps iniciais |
| `seed.max_inflated_bytes` | herda o limite inicial, do rastreador ou compartilhado | Bytes descompactados aceitos de um sitemap `.gz` |
| `http.per_host_delay_ms` | `0` | Intervalo entre consultas ao mesmo servidor; aumente com `internal_and_external` |

Mantenha `batch.hard_time_budget_seconds` abaixo do `--timeout` do worker, com uma margem confortável. Uma requisição em andamento não é interrompida quando o orçamento acaba; ela continua limitada por `http.timeout`. Por isso, o timeout do worker deve cobrir o orçamento do lote, o timeout HTTP e uma margem adicional.

Para rastreamentos `internal_and_external`, amplie `seo-pro.http.scope` ou `seo-pro.http.allowed_hosts` para que o SsrfGuard permita as consultas externas. Aumente também `http.per_host_delay_ms` para não consultar servidores de terceiros em sequência rápida. `seo:doctor` avisa quando o rastreamento inclui alvos externos, mas o escopo da proteção bloquearia todas essas consultas.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Use um programa por fila. Exemplo de `/etc/supervisor/conf.d/app-workers.conf`:

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

`stopwaitsecs` deve ser maior que o `--timeout` do worker para que uma reinicialização normal não interrompa uma tarefa no meio do lote.

### Horizon {#horizon}

Se você usa Horizon, defina um supervisor por tipo de trabalho em `config/horizon.php` e deixe que ele gerencie os processos no lugar do Supervisor:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Novas tentativas e tratamento de falhas {#retry-failure-handling}


A tarefa de cada alvo de varredura usa a política de novas tentativas da configuração; ela **não depende** do `--tries` do worker:

| Chave | Padrão | Significado |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Tentativas por tarefa de alvo |
| `seo-pro.scan.backoff` | `30` | Segundos entre tentativas |
| `seo-pro.scan.timeout` | `300` | Timeout por tarefa de alvo; o bloqueio contra sobreposição expira após esse tempo mais 60 segundos |

Quando esgota as tentativas, a tarefa registra o alvo como **failed** e a execução pode terminar como `partial` ou `failed`. Falhas de alvo tratadas não deixam a execução em `running`. Um worker encerrado antes de registrar o estado ainda precisa da recuperação descrita abaixo. As falhas ficam na tabela padrão `failed_jobs`; gerencie-as normalmente:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Agende também `queue:prune-failed` para limitar o tamanho dessa tabela:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

O rastreamento de links quebrados usa `--tries=1`. A próxima continuação recupera uma tarefa interrompida quando o heartbeat da concessão de execução fica desatualizado; `seo-pro:broken-links-recover` também pode recuperá-la. Novas tentativas da fila apenas duplicariam esse trabalho.

## Recuperação {#recovery}

Se um worker morrer durante uma tarefa, o registro do progresso pode ficar incompleto. Agende as duas rotinas de recuperação **a cada hora**:

- `seo-pro:scan-recover` marca como falhas as varreduras on-page sem progresso há `seo-pro.scan.recovery.stuck_scan_timeout_hours` horas, 2 por padrão.
- `seo-pro:broken-links-recover` recupera rastreamentos cujo heartbeat da concessão ficou desatualizado por `seo-pro.broken_links.recovery.stuck_scan_timeout_hours` horas, 2 por padrão. Marca a execução como falha e libera a vaga de uma execução ativa por escopo.

`seo:doctor` apresenta **evidência recente de heartbeat**. Depois que as varreduras entram em uso, ele informa execuções paradas e indica o comando de recuperação. Isso não comprova que o cron externo esteja funcionando; o diagnóstico relata o que o histórico das execuções permite observar.

## Retenção {#retention}

Limite o crescimento das tabelas. Estes são os padrões em `seo-pro.*`; `null` desativa a limpeza correspondente:

| Dados | Configuração | Padrão | Comando |
|---|---|---|---|
| Execuções de varredura e ocorrências | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| Registro de 404 | `monitor_404.retention_days` (+ `max_rows` `10000`) | `90` | `seo-pro:404-prune` |
| Execuções de rastreamento | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Ocorrências resolvidas | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Telemetria operacional {#operational-telemetry}

Cada execução concluída, tanto de varredura on-page quanto de rastreamento de links, emite uma linha estruturada pelo sistema de logs. Você acompanha as métricas mesmo sem painel. O conteúdo inclui apenas contagens e tempos, sem URLs, corpos de resposta, cabeçalhos nem dados de visitantes:

| Métrica | Varredura | Rastreamento |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (alvos recusados pela proteção SSRF) | — | ✓ |
| `transient_failures` (falhas de rede verificadas novamente na próxima varredura) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (do enfileiramento ao primeiro lote) | ✓ | ✓ |

Configure em `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Aponte `channel` para um canal dedicado e envie as linhas ao destino usado pela sua operação, como Loki, Datadog ou CloudWatch, sem misturá-las aos logs da aplicação:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Para outros tratamentos, assine os eventos diretamente. Todos expõem o mesmo conteúdo em `metrics()`:

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

A telemetria é enviada quando possível; um canal mal configurado não faz a varredura falhar.

## Implantação independente do Filament {#filament-independent-deployment}

Nada nesta página exige um painel. O mecanismo, os comandos, as filas, o agendador, a recuperação, a retenção e a telemetria funcionam igualmente sem ele. O painel Filament (`SeoProPlugin`) acrescenta **telas**: progresso da varredura, tabela de ocorrências, cadastro e edição de redirecionamentos, monitor de 404 e painel de links quebrados. Você pode operar pela CLI e pelo agendador e adicionar o painel depois, ou nunca, sem refazer a implantação nem as migrações por causa dessa escolha. Veja a referência completa de comandos em [Uso sem painel](/pt-BR/pro/headless).

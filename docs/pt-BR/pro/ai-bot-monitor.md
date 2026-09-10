---
description: "Registre requisições atribuídas a rastreadores de IA, com frequência, última URL e status HTTP observado. Complementa a política de acesso dos rastreadores."
---

# Monitor de bots de IA {#ai-bot-monitor}

As requisições são atribuídas pela **correspondência do user-agent**, não por verificação da identidade do bot. O monitor registra requisições observadas; um user-agent pode ser falsificado.

O [controle de rastreadores de IA](/pt-BR/guide/ai-crawlers) do núcleo define o que `robots.txt` comunica aos bots. O **monitor de bots de IA** de Pro registra o tráfego observado: quais rastreadores foram identificados, quantas vezes acessaram o site e a última URL e status HTTP de cada um.

Ele reutiliza a estrutura do monitor de 404: middleware global terminável, modelo com atualização ou inserção e contagem de acessos, e os mesmos padrões de privacidade. A chave é o **bot**, não o caminho, e o registro aceita **qualquer** status HTTP. São os rastreadores de IA que o monitor de 404 exclui intencionalmente. A identificação usa `AiCrawlerRegistry` do núcleo, mantendo a política robots e o tráfego observado ligados ao mesmo catálogo.

::: tip Requer Core 3.3 ou superior
O monitor usa o catálogo de rastreadores do núcleo, disponível em [`SEO::aiCrawlers()`](/pt-BR/guide/ai-crawlers). Com uma versão anterior do núcleo, permanece inativo.
:::

## Ativação {#enabling-it}

Desativado por padrão. Quando ativado, o middleware registra os rastreadores identificados depois da resposta, sem atrasar seu envio:

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

O middleware é registrado automaticamente; desative esse registro por `ai_bots.auto_register_middleware` se precisar controlá-lo. Uma linha por bot conhecido é inserida ou atualizada, limitando essa tabela ao tamanho do catálogo.

## Consultar o registro {#reading-the-log}

### Sem painel {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Cada linha expõe `bot`, `label`, `operator`, `purpose`, `hit_count`, `last_path`, `last_status`, `first_seen_at` e `last_seen_at`.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Com o plugin Pro registrado, a tabela **AI Bots** aparece no grupo de navegação SEO. Mostra bot, operador, finalidade, acessos, último status, último caminho e última visita. É somente leitura e permite filtrar por finalidade.

## Privacidade {#privacy}

Como no monitor de 404, **nenhum IP é armazenado por padrão**. A opção `ai_bots.hash_ip` guarda apenas um SHA-256 com chave em `ip_hash`; o IP original nunca é gravado.

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## Métricas por período, agrupadas por dia {#period-metrics-daily-buckets}

A tabela acumulada mantém uma linha por bot, mas não permite determinar quantos acessos ou URLs distintos ocorreram em um intervalo específico. Com `daily_enabled` ativado, o padrão, cada acesso também é registrado por dia e caminho em `seo_ai_bot_daily`. Assim, o [relatório com sua marca](/pt-BR/pro/reports) mostra acessos desde o relatório anterior e URLs distintos no período, em vez de apenas diferenças entre totais acumulados.

O tamanho dos dados continua limitado:

- **Limite de caminhos distintos por bot e dia**, `daily_max_paths`: novos caminhos além do limite são agrupados em uma única linha de excedentes. O total de acessos permanece exato, mas o número de linhas não cresce sem controle. Quando o limite de URLs distintos é atingido, o relatório mostra «N+».
- **Prazo de retenção**, `daily_retention_days`, aplicado por `seo-pro:ai-bots-prune`.

Defina `daily_enabled` como `false` para manter apenas os totais acumulados. O relatório passa a calcular «desde o último» pela diferença entre snapshots e ignora agrupamentos diários existentes, evitando ler dados desatualizados dessa tabela.

As métricas têm **resolução diária**. «Desde o último relatório» conta dias inteiros a partir do dia do relatório anterior; um acesso naquele dia pode ter ocorrido antes ou depois do horário exato de geração. Considere essa diferença de limite ao interpretar períodos diários, semanais ou mensais.

## Usar a observação para ajustar o acesso {#turning-observation-into-control}

O monitor mostra requisições atribuídas aos bots; o [controle de rastreadores](/pt-BR/guide/ai-crawlers) expressa a política de acesso. Para pedir que um rastreador de treinamento deixe de acessar o site:

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Alguns bots não garantem respeito a `robots.txt`. O monitor ajuda a identificar acessos e decidir se você precisa aplicar bloqueios no firewall, WAF ou Cloudflare.

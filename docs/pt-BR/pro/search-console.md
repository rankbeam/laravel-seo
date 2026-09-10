---
description: "Painel Google Search Console somente leitura: consultas e páginas com impressões, cliques, CTR e posição, associadas aos alvos do scanner. Desativado por padrão."
---

# Search Console, somente leitura {#search-console-read-only}

Um painel **somente leitura** com as principais consultas e páginas de Google Search Console, incluindo **impressões, cliques, CTR e posição média**. Os dados são associados às páginas conhecidas pelo scanner, para consultar ocorrências e desempenho de busca no mesmo lugar. Está **desativado por padrão**.

O funcionamento segue três critérios:

- **Acesso estritamente de leitura.** A integração solicita apenas o escopo OAuth `webmasters.readonly`, fixado no código. Lê Search Analytics sem enviar sitemaps, solicitar indexação ou alterar dados de Search Console. Não há configuração para ampliar o escopo.
- **Sua propriedade e suas credenciais.** As chamadas vão diretamente do seu servidor ao Google, autenticadas pela sua conta de serviço ou credencial OAuth. O pacote não atua como intermediário, não mede nem revende o acesso e não envia telemetria.
- **Erros aparecem no contexto.** Credenciais ausentes, erros 403, quotas e tempos esgotados produzem mensagens na interface sem interromper o painel. A sincronização histórica informa falhas e para de buscar os dias seguintes, conforme descrito abaixo.

## O que está disponível {#what-you-get}

- **Páginas que precisam de atenção:** páginas com **ocorrências abertas** que **ainda recebem tráfego de busca**, ordenadas pelas impressões entre as páginas com problemas. Use-as para priorizar a revisão.
- **Principais páginas** e **principais consultas:** as tabelas habituais de Search Analytics.

No Filament, a página **Search Console** aparece no grupo SEO apenas quando a integração está ativada. Sem painel, use `seo-pro:search-console` ou `SeoPro::searchConsole()` para as mesmas métricas.

## Configuração inicial {#setup}

Você precisa de uma credencial Google com acesso de leitura à propriedade. Há dois modos; uma **conta de serviço** costuma ser a opção mais simples no servidor.

### Conta de serviço, recomendada {#service-account-recommended}

1. No Google Cloud, ative a **Search Console API**, crie uma **conta de serviço** e baixe sua chave JSON.
2. Em Search Console, vá a *Configurações → Usuários e permissões* e adicione o e-mail da conta, `…@….iam.gserviceaccount.com`. A permissão restrita é suficiente para leitura.
3. Configure a chave e a propriedade:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

Se `SEO_PRO_GSC_SITE_URL` for omitido, uma propriedade de prefixo de URL é derivada de `app.url`.

### OAuth com refresh token {#oauth-offline-refresh-token}

Se você já tem um cliente OAuth e um **refresh token** de longa duração, preferencialmente autorizado apenas para `webmasters.readonly`, configure-os abaixo. Cada renovação solicita esse escopo. O pacote rejeita o token retornado se a resposta não confirmar explicitamente o escopo exato de leitura; não presume que o Google sempre restrinja uma autorização mais ampla.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### Publicar a migração de tokens {#publish-the-token-migration}

A cache criptografada de tokens de acesso fica na tabela `seo_gsc_tokens`. Publique e aplique a migração uma vez:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Confirme a configuração com `php artisan seo:doctor`. Ele informa se Search Console está ativado e configurado, sem chamada de rede nem exibição de segredos.

## Uso sem painel {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## Métricas históricas {#historical-metrics}

O painel e os comandos acima leem uma **janela móvel em tempo real**, tendo Search Console como fonte. Para manter **histórico diário** consultável por período, execute a sincronização. Ela salva métricas por dia e por consulta ou página em `seo_gsc_metrics`:

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **A primeira execução busca o histórico anterior** de `sync.backfill_days`, 90 dias por padrão. Search Console mantém aproximadamente 16 meses; aumente a janela para buscar mais dados disponíveis. As execuções seguintes **retomam da última data armazenada** e repetem `sync.overlap_days` no final para captar a consolidação tardia de dados recentes. A janela sempre termina três dias atrás devido ao atraso da fonte.
- **É idempotente.** As linhas são inseridas ou atualizadas por `(date, dimension, key)`. Um dia com falha, como quota esgotada, encerra a execução e informa quantas linhas foram salvas. A próxima execução retoma do progresso existente.
- **Uso nos relatórios.** Quando a tabela cobre os dois períodos, as mudanças de Search Console no [relatório](/pt-BR/pro/reports) usam o histórico do período atual e do intervalo equivalente anterior, substituindo a diferença entre snapshots de relatórios. O histórico também sustenta as análises de consultas.

Somente métricas agregadas são armazenadas: texto da consulta, URL da página e cliques, impressões, CTR e posição por dia. Não são buscados nem gravados dados por usuário ou requisição.

## Tratamento de dados e segurança {#data-handling-security}

- **Escopo de leitura verificado.** O JWT da conta de serviço solicita apenas `webmasters.readonly`. A renovação OAuth faz o mesmo e rejeita respostas com escopo ausente ou mais amplo. Use uma credencial autorizada apenas para leitura. O pacote não contém chamadas que alteram Search Console.
- **Credenciais permanecem no ambiente.** A chave da conta de serviço, o segredo OAuth e o refresh token são lidos das variáveis cujo **nome** está configurado, no momento da chamada, como a chave de IA. `php artisan config:cache` não os escreve em `bootstrap/cache/config.php`. Disponibilize-os no ambiente real do processo quando a cache impedir a leitura de `.env`.
- **Tokens criptografados em repouso.** O token de acesso de curta duração é salvo com criptografia pela chave da aplicação em `seo_gsc_tokens` e reutilizado até próximo do vencimento. A troca de token não ocorre em toda visualização. A credencial de longa duração permanece no ambiente, nunca no banco.
- **Chamadas protegidas contra SSRF.** A troca de tokens e as chamadas de Search Analytics usam `SsrfGuard`, apenas HTTPS, host com endereço público e redirecionamentos desativados. A requisição não pode ser redirecionada para um serviço interno.
- **Segredos não aparecem nos logs.** Tokens, chaves e cabeçalhos de autenticação não são registrados. Os erros mostram apenas a mensagem depurada do Google, com limite de comprimento.
- **Métricas em cache local** por `seo-pro.search_console.cache_ttl` segundos, 30 minutos por padrão. O painel e o comando em tempo real não persistem nada além dessa cache e do token criptografado. Apenas `seo-pro:gsc-sync`, executado explicitamente, grava métricas permanentes em `seo_gsc_metrics`: agregados diários por consulta ou página, sem dados individuais de usuários.

## Referência de configuração {#configuration-reference}

Todas as opções ficam em `config/seo-pro.php`, no bloco `search_console`:

| Chave | Padrão | Finalidade |
| --- | --- | --- |
| `enabled` | `false` | Ativação geral, `SEO_PRO_GSC_ENABLED` |
| `connection` | `service_account` | `service_account` ou `oauth` |
| `site_url` | Derivado de `app.url` | Propriedade, como `https://example.com/` ou `sc-domain:example.com` |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Nome** da variável com o JSON da chave ou seu caminho |
| `oauth.client_id` | — | Identificador do cliente OAuth, não secreto |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Nome** da variável com o segredo do cliente |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Nome** da variável com o refresh token |
| `default_days` | `28` | Janela de relatório, terminando três dias atrás pelo atraso dos dados |
| `row_limit` | `100` | Máximo de linhas por relatório; limite da API de 25.000 |
| `cache_ttl` | `1800` | Segundos de cache de um relatório recuperado |
| `sync.backfill_days` | `90` | Dias buscados no primeiro `gsc-sync`, com tabela vazia |
| `sync.overlap_days` | `2` | Dias finais buscados novamente para captar a consolidação tardia |
| `sync.row_limit` | `5000` | Máximo de linhas solicitado por dia e dimensão na sincronização |

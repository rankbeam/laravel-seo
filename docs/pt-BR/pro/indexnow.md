---
description: "Notifique buscadores quando uma URL for publicada ou atualizada. Pro envia ao endpoint compartilhado api.indexnow.org, que distribui aos participantes. Desativado por padrão."
---

# IndexNow: notificar ao publicar {#indexnow-—-push-on-publish-indexing}

**IndexNow** permite avisar aos buscadores quando uma URL é publicada ou alterada, sem esperar que o rastreador descubra a mudança. Pro envia ao endpoint compartilhado `api.indexnow.org`, que **distribui a notificação aos buscadores participantes** em uma chamada, sem envios individuais. A [FAQ oficial](https://www.indexnow.org/faq) lista Amazon, Bing, Naver, Seznam, Yandex e Yep. Notificar não garante indexação.

Está **desativado por padrão**. Nenhuma chamada de rede acontece até você ativar a integração e enviar uma URL.

## Configuração inicial {#setup}

### 1. Gerar uma chave {#_1-generate-a-key}

IndexNow usa uma **chave** para verificar o controle do host. Pro aceita de 8 a 128 caracteres de `[a-f0-9-]`; uma sequência hexadecimal de 32 caracteres é adequada. Gere uma vez, mantenha-a estável e disponibilize-a pelo ambiente:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip A chave é lida pela configuração e funciona com `config:cache`
Ao contrário das credenciais de Search Console, a chave IndexNow **não é secreta**: ela é servida publicamente em `/{key}.txt` para comprovar o controle do host. Pro a resolve por `indexnow.key`, cujo padrão é `env('SEO_PRO_INDEXNOW_KEY')`. Valores definidos **apenas em `.env`** deixam de estar disponíveis para chamadas diretas a `env()` depois de `config:cache`, porque o Laravel não carrega mais esse arquivo. Variáveis reais do processo continuam disponíveis. Ao ler pela configuração, a chave é capturada na criação da cache. **Trocar a chave exige executar `php artisan config:cache` novamente.** A chave não é registrada em logs. Se o arquivo retornar 404 em produção, consulte [servidores com configuração em cache](#config-cached-servers).
:::

### 2. Servir o arquivo da chave {#_2-serve-the-key-file}

IndexNow consulta `https://{host}/{key}.txt`, contendo apenas a chave, para verificar a propriedade. Com `route` ativado, o padrão, **Pro serve o arquivo**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Apenas o caminho da chave configurada responde. Outros caminhos interceptados pela rota retornam 404, assim como toda a rota quando IndexNow está desativado. Para hospedar o arquivo por conta própria ou em um CDN, desative `route` e configure `key_location` com sua URL.

## Enviar URLs {#submitting-urls}

### Automaticamente ao salvar {#automatically-on-save-the-push-on-publish-path}

Adicione o trait ao modelo e ative `auto_submit`. Cada salvamento enfileira o envio de `getUrlForSEO()`:

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

O trait respeita o estado de publicação. Implemente `shouldSubmitToIndexNow(): bool` para controle completo; na ausência do método, usa `is_published` e, se esse atributo também não existir, envia a cada salvamento. O envio é **enfileirado**. Com um backend de fila assíncrono e worker, o salvamento não espera pela rede; uma conexão `sync` executa o trabalho no processo atual.

### Manualmente {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` usa a fila por padrão. Passe `queue: false` para executar diretamente.

### Pela linha de comando {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Apenas URLs do mesmo host
Toda URL deve usar `http(s)` **e** pertencer ao `host` configurado. As demais são descartadas e contadas, mas nunca enviadas. O protocolo verifica o host e rejeita divergências. Listas maiores que `max_urls_per_request`, 10.000 por padrão e limite do protocolo, são divididas automaticamente.
:::

## Configuração {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## Reintentos {#how-retries-work}

O trabalho `SubmitToIndexNowJob` repete erros `429`, `5xx` e tempos de espera esgotados, com `backoff` e o limite `tries`. Erros permanentes `400`, `403` e `422`, como chave inválida ou host divergente, são registrados e encerram o processamento. `200` e `202`, recebido ou aguardando verificação da chave, são tratados como sucesso.

Em produção, use uma **fila dedicada** para que um endpoint lento não atrase tarefas dos usuários:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Solução de problemas {#troubleshooting}

### Servidores com configuração em cache {#config-cached-servers}

Se `/{key}.txt` retorna 404 ou os envios não acontecem em produção, mesmo com `indexnow.enabled = true`, confira como a chave é resolvida. Com `config:cache`, o Laravel não lê `.env`; uma chamada direta a `env('SEO_PRO_INDEXNOW_KEY')` pode retornar `null`, impedindo o registro da rota e fazendo os envios serem rejeitados como não configurados.

A configuração padrão lê `indexnow.key` de `env(...)` durante a criação da cache, então funciona normalmente. O problema surge quando a **configuração publicada perdeu o padrão `env(...)`** ou quando um **nome personalizado `key_env` existe apenas em `.env`**. Há duas correções:

1. **Manter a chave na configuração**, recomendado: deixe `indexnow.key` como `env('SEO_PRO_INDEXNOW_KEY')` ou defina um valor literal e execute `php artisan config:cache` novamente. Trocar a chave depois exige recriar a cache.
2. **Injetar uma variável de ambiente real:** configure `SEO_PRO_INDEXNOW_KEY` no processo ou sistema operacional, como `env[...]` do pool PHP-FPM, `Environment=` do systemd ou os ajustes da plataforma. Variáveis reais continuam acessíveis com a configuração em cache.

Execute `php artisan seo:doctor` para confirmar. Ele informa que IndexNow está ativado, mas nenhuma chave válida foi resolvida, e indica a correção. Pro também registra um aviso uma vez por processo quando inicia com configuração em cache e chave ilegível.

::: tip Google
Google **não participa** do IndexNow. Para Google, use a integração de [Search Console](/pt-BR/pro/search-console) e mantenha o sitemap atualizado.
:::

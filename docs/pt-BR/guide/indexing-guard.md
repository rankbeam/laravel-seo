---
description: "Vincule as diretivas de indexação ao ambiente Laravel: fora da lista de ambientes permitidos, o Rankbeam impõe noindex e gera regras de bloqueio para rastreadores."
---

# Proteção de indexação fora de produção {#indexing-guard-non-production-safety-net}

Uma cópia de homologação ou desenvolvimento aparecendo no Google pode competir com as páginas reais, expor um ambiente privado nos resultados e exigir trabalho de remoção. Isso pode acontecer quando `noindex` depende de uma variável esquecida no `.env` ou de uma regra robots sobrescrita no deploy.

A **proteção de indexação** vincula esse comportamento ao *ambiente Laravel*. Quando a aplicação executa fora da lista permitida, todas as páginas recebem `noindex,nofollow`, o `robots.txt` gerenciado bloqueia todos os rastreadores e `seo:audit` informa esse estado.

É uma função gratuita do núcleo.

## O que acontece quando está ativa {#what-it-does-when-active}

Com a proteção habilitada e `app()->environment()` **fora** de `seo.indexing_guard.allowed_environments`, quatro ações acontecem:

1. **O resolvedor impõe `noindex,nofollow` em todas as páginas.** A regra fica acima de toda a [cadeia de prioridade](/pt-BR/concepts/resolver-precedence), inclusive de um valor robots explícito salvo em `seo_meta`.
2. **O cabeçalho HTTP `X-Robots-Tag: noindex,nofollow`** é enviado nas respostas que passam pela aplicação. Veja [respostas não HTML](#non-html-responses-pdfs-feeds-images).
3. **`SEO::robotsTxt()->build()` gera um `robots.txt` e um `ai.txt` que bloqueiam tudo**, com `User-agent: *` e `Disallow: /`. Isso vale para `seo:robots-txt` e para a [rota dinâmica opcional](/pt-BR/guide/ai-crawlers).
4. **`seo:audit` exibe um aviso destacado**, tornando explícito o estado de não indexação no relatório.

Nos ambientes permitidos, `production` por padrão, a proteção fica **inativa**, sem alterar a saída: a renderização permanece idêntica byte a byte.

## Respostas não HTML: PDFs, feeds e imagens {#non-html-responses-pdfs-feeds-images}

A metatag robots só alcança rastreadores que leem HTML. PDFs, feeds RSS/Atom, imagens e outras respostas não têm `<head>`. Por isso, a proteção ativa também envia a diretiva como cabeçalho HTTP por um middleware global:

```http
X-Robots-Tag: noindex,nofollow
```

O cabeçalho e a metatag vêm da mesma fonte. O cabeçalho está **ativado por padrão dentro da proteção**; a própria proteção exige ativação e não interfere nos ambientes permitidos. Para manter apenas a metatag:

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

O middleware só é registrado quando a proteção está habilitada. Com ela desativada, nada é acrescentado à pilha de middleware.

::: warning Arquivos estáticos não passam pelo PHP
Um arquivo servido diretamente de `public/` pelo servidor web não entra no Laravel e não recebe esse cabeçalho. Proteja esses arquivos na configuração do servidor ou CDN. A proteção cobre as respostas que passam pela aplicação.
:::

## Por que substitui um valor robots explícito {#why-it-overrides-an-explicit-robots-value}

Nas demais resoluções do Rankbeam, um valor explícito salvo tem prioridade. A proteção é uma exceção intencional acima dessa camada:

- O banco de homologação costuma ser uma cópia de produção. Sem a proteção, uma página com `index,follow` manteria a diretiva e pediria indexação no ambiente de teste.
- **Em um ambiente que não deve ser indexado, impor `noindex` preserva a intenção; permitir indexação por engano a contraria.** Por isso, o valor salvo não pode ultrapassar a proteção nesses ambientes.

## Ativação {#enabling-it}

A proteção vem **desativada**. Instalar ou atualizar o pacote não muda a saída de ambientes fora de produção sem uma escolha explícita, seguindo a mesma política de ativação opcional de [`blank_is_unset`](/pt-BR/concepts/resolver-precedence) e das imagens OG geradas. Ative com uma linha:

```dotenv
SEO_INDEXING_GUARD=true
```

Com a lista padrão, `production` permanece inalterado, então você pode manter a proteção habilitada na configuração compartilhada. Confira se a lista inclui todos os ambientes que devem ser indexados. Seu uso é recomendado; ativá-la por padrão é uma possibilidade para Core 4, não o comportamento atual.

Para desativar:

```dotenv
SEO_INDEXING_GUARD=false
```

## Escolher os ambientes permitidos {#choosing-which-environments-may-index}

Por padrão, apenas `production` é permitido. Substitua a lista por uma variável de ambiente com valores separados por vírgula:

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

Ou em `config/seo.php`:

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

As entradas usam `Str::is()`, portanto aceitam **curingas**: `'prod*'` corresponde a `production` e `prod-eu`.

```php
'allowed_environments' => ['prod*'],
```

Uma lista **vazia** significa que nenhum ambiente pode ser indexado: a proteção fica ativa em todos. Porém, uma variável `SEO_INDEXING_GUARD_ALLOWED` vazia ou só com espaços retorna ao padrão `['production']`, evitando que um erro de preenchimento desative silenciosamente a indexação em produção. Use `[]` explícito na configuração se quiser proteger todos os ambientes.

## Verificação {#verifying-it}

`seo:audit` mostra o aviso e inclui o estado legível por máquina com `--json`:

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

O `robots.txt` servido ou gerado em um ambiente protegido:

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Escopo {#scope}

A proteção controla **diretivas de indexação**: metatag robots, cabeçalho `X-Robots-Tag` e `robots.txt`. Ela não altera títulos, descrições, URLs canônicas nem esquemas. É independente da [política de emissão de robots](/pt-BR/concepts/resolver-precedence), `seo.robots.emit_default`: como `noindex,nofollow` difere do padrão do site, a tag é emitida.

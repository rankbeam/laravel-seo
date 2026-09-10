---
description: "seo:explain mostra qual camada definiu cada campo SEO e quais valores substituiu. Somente leitura, sem rede ou licença, para investigar títulos e diretivas robots inesperados."
---

# Explicar a resolução com `seo:explain` {#explain-the-resolution-seo-explain}

O Rankbeam resolve o SEO pela [ordem de prioridade das camadas](/pt-BR/concepts/resolver-precedence): configuração, padrões do banco de dados globais, por tipo de modelo e por rota, valores calculados do modelo e valores explícitos de `seo_meta`. Depois aplica o pós-processamento de sufixo, URL canônica e imagens absolutas, além da [proteção de indexação](/pt-BR/guide/indexing-guard). Quando `<title>` ou robots não correspondem ao esperado, **`seo:explain` mostra qual camada definiu cada campo e o que ela substituiu**.

O comando é somente leitura, não exige rede nem licença e não reimplementa a mesclagem. A atribuição vem das contribuições das próprias camadas e os valores finais vêm do resolvedor real, mantendo a explicação alinhada à saída.

## Uso {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

O modelo precisa usar o trait [`HasSEO`](/pt-BR/guide/quickstart).

## Como ler a saída {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by:** a camada de maior prioridade que definiu um valor não nulo. Mostra `post-processing` quando nenhuma camada definiu o campo e o valor foi derivado, como a URL canônica da requisição ou do modelo, `og:url` da canônica ou uma imagem convertida em URL absoluta.
- **Overrode:** todas as camadas de menor prioridade que ofereceram um valor e foram substituídas, em ordem.
- **↳ notes:** pós-processamentos que alteraram o valor após a mesclagem, como sufixo do título, remoção de parâmetros da URL canônica, derivação de `og:url`, imagens absolutas e proteção de indexação impondo `noindex` acima de todas as camadas.

::: tip og:type e twitter:card
Esses campos têm os padrões não nulos `website` e `summary_large_image`. A camada mais alta que os define, normalmente `computed`, prevalece sobre `config`. Uma página sem linha `seo_meta` não contribui com valores para esses campos; assim, um `og:type` calculado como `article` não é substituído por um simples `website`. A explicação segue a mesclagem real.
:::

## Resolução no nível do site {#site-level-resolution}

Conforme o [registro de origem dos valores do site](/pt-BR/concepts/resolver-precedence), `seo:explain` também informa **qual fonte definiu o host canônico, o nome do site e o idioma padrão**:

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Confira especialmente o host canônico. Um `localhost` que chegou à produção, `http://` em um site HTTPS ou uma URL da aplicação diferente da URL do modelo podem produzir uma referência canônica incorreta à própria página.

## Saída JSON {#json-output}

`--json` emite o rastreamento completo para ferramentas ou CI: `target`, `winner`, `losers`, `final` e `notes` por campo, além do registro `site_level`:

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## Veja também {#see-also}

- [Prioridade do resolvedor](/pt-BR/concepts/resolver-precedence): a cadeia completa rastreada por `seo:explain`.
- [Auditoria SEO gratuita](/pt-BR/guide/audit): `seo:audit` identifica problemas; `seo:explain` mostra como o valor foi obtido.

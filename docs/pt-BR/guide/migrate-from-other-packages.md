---
description: "Migre de outros pacotes SEO Laravel para HasSEO e saveSEO(), com importação dos dados de ralphjsmit/laravel-seo."
---

# Migrar de outros pacotes SEO para Laravel {#migrating-from-other-laravel-seo-packages}

Este guia relaciona APIs e formas de armazenamento comuns às duas peças do Rankbeam: a trait [`HasSEO`](/pt-BR/guide/quickstart) e `saveSEO()`. Para o pacote que guarda SEO por modelo, há um comando de importação. O esforço depende das suas personalizações.

::: tip Vindo do WordPress?
O guia [Migrar do WordPress](/pt-BR/guide/migrate-from-wordpress) descreve o importador CSV e os leitores de bancos existentes para Yoast e Rank Math.
:::

| Origem | Armazenamento | Caminho de migração |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | Tabela polimórfica `seo` | **`php artisan seo:import-from ralphjsmit`** e troca de trait |
| [`artesaos/seotools`](#from-artesaos-seotools) | Sem banco; chamadas em execução e configuração | Substituir chamadas por `saveSEO()` ou getters calculados |
| [`spatie/*`](#from-spatie-packages) | Builders de schemas e sitemaps, sem tabela de metadados | Manter funções complementares e migrar as demais |

Só **ralphjsmit** tem aqui uma tabela SEO para importação em lote. Nos geradores de tags em execução, substitua chamadas por requisição por dados salvos em `seo_meta` ou valores calculados.

---

## De `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

O pacote salva uma linha polimórfica por modelo em `seo`, com estrutura próxima de `seo_meta`. Isso permite uma importação idempotente.

### 1. Instalar o Rankbeam junto ao pacote atual {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Ambos podem coexistir durante a migração: usam tabelas (`seo` e `seo_meta`) e namespaces de traits diferentes.

::: warning Uma chave de configuração compartilhada
Um `config/seo.php` antigo do ralphjsmit encobre a configuração Rankbeam, pois ambos usam a chave `seo`. Faça uma cópia, remova o arquivo antigo e publique o do Rankbeam com `php artisan vendor:publish --tag=seo-config`.
:::

### 2. Executar o importador {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

O importador lê `seo`, resolve cada linha para o modelo Eloquent real e grava em `seo_meta`.

| Opção | Efeito |
|---|---|
| `--dry-run` | Mostrar a importação prevista sem gravar. |
| `--model="App\Models\Post"` | Limitar a uma ou mais classes; repetível. |
| `--locale=fr` | Idioma de destino; padrão da aplicação se omitido. |
| `--table=legacy_seo` | Ler uma tabela de origem renomeada. |
| `--connection=legacy` | Ler por outra conexão de banco. |
| `--limit=100` | Importar no máximo N linhas, para avançar por etapas. |
| `--overwrite` | Substituir valores preenchidos; por padrão, completar apenas campos vazios. |
| `--json` | Relatório legível por máquina. |
| `--force` | Pular a confirmação em scripts ou CI. |

Executar novamente atualiza as mesmas linhas sem duplicá-las. Por padrão, os dados já definidos no Rankbeam são preservados. Use `--overwrite` apenas para substituí-los pelos valores importados.

### 3. Trocar a trait dos modelos {#_3-swap-the-trait-on-your-models}

Substitua a trait do ralphjsmit pela do Rankbeam. Alguns métodos mudam de nome; a tabela lida passa a ser `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Leve a lógica de `getDynamicSEOData()` para `getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()` e `getSEOAlternates()`. Veja o [início rápido](/pt-BR/guide/quickstart). Valores explícitos passam por `saveSEO()`:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Mapeamento dos campos {#field-mapping}

O mapeamento é explícito: colunas ausentes do schema Core 3 não são copiadas indiscriminadamente.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Observações |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | Obtidos novamente do modelo atual, não copiados literalmente. |
| `title` | `title` | Limitado aos 70 caracteres da coluna; cortes são relatados. |
| `description` | `description` | Limitado a 160 caracteres; cortes são relatados. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Limitado a 50 caracteres. |
| `image` | `og_image` | `twitter:image` herda o valor pelo resolvedor. |
| `author` | Não importado | Core 3 não tem coluna de autor em `seo_meta`; autoria do artigo pertence aos dados resolvidos. Linhas afetadas são contadas e relatadas para você escolher armazenamento ou cálculo adequado. |
| `id`, `created_at`, `updated_at` | Não importados | Campos estruturais da origem. |

**Por que o tipo polimórfico é recalculado:** cada linha é resolvida para o modelo real, e as chaves `seoable` vêm de `getMorphClass()`. Isso respeita o [morph map atual](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types), mesmo que o pacote antigo tenha usado outra convenção. Modelos excluídos são relatados como ignorados, sem gerar relações órfãs.

### Entender o relatório {#what-the-report-tells-you}

Sem `--json`, a saída traz uma tabela e três seções de revisão:

- **Truncated:** valores cortados para caber nas colunas de `seo_meta`.
- **Not imported:** colunas preenchidas, como `author`, sem destino no Core 3.
- **Skipped rows by reason:** linhas vazias, modelos excluídos ou tipos não resolvidos.

### Verificar {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Depois de conferir os dados, você pode remover `ralphjsmit/laravel-seo` e excluir sua tabela antiga `seo`.

---

## De `artesaos/seotools` {#from-artesaos-seotools}

Esse pacote gera tags **em execução** com `SEOMeta`, `OpenGraph`, `TwitterCard` e `JsonLd`, normalmente no controller, com padrões em `config/seotools.php`. Não há tabela por modelo a importar. Mova as chamadas para valores salvos ou calculados.

| Chamada artesaos/seotools | Equivalente Rankbeam |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` ou `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` ou `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` ou `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | Não há uma metatag keywords equivalente: as palavras-chave de foco servem para verificações editoriais internas. `saveSEO(['focus_keywords' => [...]])`, veja [auditoria](/pt-BR/guide/audit) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [Grafo JSON-LD](/pt-BR/guide/schema) |
| Padrões de `config/seotools.php` | `config/seo.php` e [prioridade do resolvedor](/pt-BR/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` no layout | `@seo($model)`, veja [Blade](/pt-BR/guide/blade) |

Em vez de definir tags em cada controller, salve os dados por modelo em `seo_meta` e deixe o resolvedor renderizá-los. Padrões globais passam à [configuração](/pt-BR/reference/configuration). Páginas estáticas por rota usam `@seoForRoute()`.

---

## De pacotes Spatie {#from-spatie-packages}

Não existe um pacote de armazenamento de metadados `spatie/laravel-seo`. Os pacotes Spatie comuns são builders complementares que podem ser mantidos ou substituídos individualmente:

- **`spatie/schema-org`:** builder fluent de JSON-LD. O [grafo Rankbeam](/pt-BR/guide/schema) oferece builders tipados `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` e `Organization`, salvos em `seo_meta.schema_jsonld` e emitidos sem duplicatas. Passe a saída `->toArray()` dos objetos atuais para `saveSEO(['schema_jsonld' => $array])` ou reescreva com os builders do Rankbeam.
- **`spatie/laravel-sitemap`:** o [registro Rankbeam](/pt-BR/guide/sitemaps) se baseia nele. Registre seus modelos como fontes de um sitemap combinado ou preserve o sitemap atual e desative a rota Rankbeam.

Para [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO), outro gerador em execução, siga o padrão do artesaos: troque `setTitle` e `addMeta` por `saveSEO()` ou getters calculados.

---

## Estender o importador {#extending-the-importer}

`seo:import-from` usa um registro de implementações de `Rankbeam\Seo\Importing\Contracts\Importer`. Adicionar uma fonte não exige alterar o comando. As fontes incluídas são `ralphjsmit` e os [importadores WordPress](/pt-BR/guide/migrate-from-wordpress) `wordpress-csv`, `yoast` e `rank-math`. Registre uma fonte própria em um service provider:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```

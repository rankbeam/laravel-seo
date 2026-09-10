---
description: "Importe para seus modelos Laravel os títulos, descrições, URLs canônicas, diretivas robots e palavras-chave escritos no Yoast ou Rank Math. Referência do mapeamento de campos."
---

# Migração do WordPress {#migrating-from-wordpress}

Ao migrar um site de conteúdo do WordPress, você pode trazer para os modelos Laravel os metadados que sua equipe escreveu no Yoast ou Rank Math: títulos, descrições, URLs canônicas, diretivas robots, palavras-chave de foco e valores específicos para redes sociais. O importador ajuda a preservar esse trabalho durante a mudança.

::: tip Vai colocar a migração em produção?
Esta página é a **referência** de campos, tokens e chaves de origem. Para o **procedimento** em ordem — manter os sites lado a lado, importar, verificar e só depois desativar o antigo — siga o [roteiro de migração do WordPress](/pt-BR/guide/wordpress-migration-runbook).
:::

Há dois caminhos, ambos pelo comando `seo:import-from`:

| Caminho | Origem | Uso indicado |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | Planilha exportada do WordPress | Maioria das migrações de agência, com controle exato das URLs |
| [**Banco de dados**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | Banco do WordPress | Importação que inclui valores Open Graph/Twitter e redirecionamentos do Rank Math |

Ambos são **idempotentes**: uma nova execução atualiza as mesmas linhas, sem duplicá-las. Aceitam **`--dry-run`** e, por padrão, apenas **preenchem campos vazios**, preservando o SEO já definido no Rankbeam. Use **`--overwrite`** para substituir valores existentes pelos importados.

## Como as linhas do WordPress viram linhas de `seo_meta` {#how-wordpress-rows-become-seo-meta-rows}

Uma linha do WordPress é identificada por uma **URL** ou um **ID de post**. Já `seo_meta` é polimórfico: cada linha pertence a um modelo Eloquent real. O importador tenta associar cada registro do WordPress a um dos seus modelos e distingue no relatório os registros associados dos que ficaram apenas com URL:

- **Associado a modelo**: informe `--model="App\Models\Post"`. O **slug** de cada linha, obtido do último segmento da URL ou de `post_name`, é procurado no modelo pela chave de rota ou pela coluna definida em `--match-by=`. Registros correspondentes são gravados em `seo_meta`.
- **Apenas URL**: sem modelo correspondente, ou sem `--model`, a linha não pode virar `seo_meta`, pois não há modelo ao qual vinculá-la. Ela aparece como ignorada pelo motivo `url-only`. Sua URL canônica ainda pode gerar um [candidato a redirecionamento](#redirects).

Posts e páginas do WordPress costumam corresponder a modelos Laravel **diferentes**. Execute uma importação por tipo de conteúdo, limitando as linhas:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Tipos de post personalizados não são lidos por padrão
Os leitores do banco percorrem apenas **`post`** e **`page`**. Se o site usa tipos personalizados, como `product`, `event` ou `pathology`, informe cada um explicitamente, repetindo `--post-type=`:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. Importação por CSV {#_1-csv-import}

O CSV atende à maioria das migrações de agência. Exporte uma linha por URL com este cabeçalho. A ordem das colunas é livre; colunas desconhecidas são ignoradas e informadas no relatório:

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Execute:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Coluna | Destino em `seo_meta` | Observações |
|---|---|---|
| `url` | *chave de correspondência* | Obrigatória. O slug, último segmento do caminho, é associado ao modelo. |
| `title` | `title` | Limitado a 70 caracteres; valores truncados são informados. |
| `description` | `description` | Limitada a 160 caracteres. |
| `canonical` | `canonical` | Também gera [candidatos a redirecionamento](#redirects). |
| `robots` | `robots` | Preservado como informado, por exemplo `noindex, nofollow`, com limite de 50 caracteres. |
| `focus_keyword` | `focus_keywords` | Palavras-chave separadas por vírgula; a primeira é a principal. |

Linhas sem `url` ou com quantidade de colunas diferente do cabeçalho são ignoradas e contabilizadas como inválidas.

---

## 2. Importação pelo banco (Yoast / Rank Math) {#_2-database-import-yoast-rank-math}

Se o banco do WordPress ainda estiver disponível, o importador pode ler os metadados diretamente, incluindo valores Open Graph/Twitter e redirecionamentos do Rank Math, que costumam ficar de fora de exportações CSV.

### Configure uma conexão com o WordPress {#point-a-connection-at-wordpress}

Adicione a conexão em `config/database.php`:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Depois importe. O prefixo padrão é `wp_`; altere-o com `--table=`:

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

O leitor percorre posts e páginas publicados em `{prefix}posts`, busca os metadados do plugin em `{prefix}postmeta` e associa o slug `post_name` ao modelo Laravel.

::: tip Prefixo de tabela diferente do padrão
Hospedagens gerenciadas frequentemente usam prefixos aleatórios, como `wppg_`. Confira os nomes em `CREATE TABLE` no dump e informe o prefixo real, por exemplo `--table=wppg_`, para localizar `{prefix}posts` e `{prefix}postmeta`.
:::

::: tip Leitura de um dump restaurado no MySQL 8
Ao restaurar localmente um dump do WordPress no MySQL 8 ou superior, ajuste o modo SQL da sessão antes de carregar o `.sql`. Datas padrão como `'0000-00-00'` são rejeitadas por `STRICT` e `NO_ZERO_DATE`, fazendo a restauração falhar com `Invalid default value for 'post_date'` antes mesmo da importação de SEO:

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Mapeamento dos campos {#field-mapping}

Os dois importadores usam mapeamentos **explícitos**. Chaves sem coluna correspondente no Core 3 são informadas como **não mapeadas**; nenhum destino é inventado.

| Chave Yoast | Chave Rank Math | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Só são armazenadas diferenças em relação aos padrões do WordPress. Uma página indexável comum mantém `robots` nulo e herda o padrão do site. Os indicadores separados do Yoast para `noindex`, `nofollow` e diretivas avançadas (`noarchive`, `nosnippet`, `noimageindex`) são combinados em uma string. O array serializado `robots` do Rank Math recebe o mesmo tratamento, removendo os padrões `index` e `follow`.

**Chaves não mapeadas**, informadas mas não copiadas: IDs de anexos de imagem (`*-image-id`), pontuações de palavra-chave e SEO (`linkdex`, `content_score`, `rank_math_seo_score`), escolhas de categoria principal e marcadores de rich snippets do Rank Math. Para dados estruturados, configure o [grafo de schema](/pt-BR/guide/schema) tipado do Rankbeam.

::: warning URLs canônicas são importadas sem alterações
Uma canonical explícita em `rank_math_canonical_url` ou `_yoast_wpseo_canonical` é copiada **exatamente como está**. Se apontar para o domínio antigo, como `https://oldsite-staging.example.com/page/`, continuará apontando para lá. O importador não troca o servidor. `--site-url` extrai **caminhos** de URLs absolutas para a correspondência de linhas CSV e os [candidatos a redirecionamento](#redirects), mas **não** reescreve canonicals salvas. Após mudar de domínio, revise esses valores e atualize o servidor ou remova o valor explícito para usar a canonical autorreferente do resolvedor. Páginas sem canonical explícita não são afetadas; Yoast e Rank Math calculam a canonical automática durante a renderização.
:::

### Tokens de template {#template-tokens}

Yoast e Rank Math podem armazenar títulos e descrições como **templates**: o primeiro usa tokens como `%%title%%`, e o segundo, `%title%`. O importador **resolve os tokens cujos dados consegue obter** e **remove os demais**, evitando gravar strings brutas como `%%token%%`:

| Token | Valor resultante |
|---|---|
| `%%title%%` / `%title%` | Título do post no WordPress |
| `%%sitename%%` / `%sitename%` | Nome do blog em `wp_options`, na importação pelo banco |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *Removidos*, com limpeza dos separadores ao redor |

O relatório informa quando houve resolução de tokens. **Revise os títulos importados** para confirmar a leitura desejada e ajustar os que dependiam de tokens indisponíveis.

---

## Redirecionamentos {#redirects}

`seo_redirects` pertence ao [Rankbeam **Pro**](/pt-BR/pro/installation); o importador Core nunca grava diretamente nessa tabela. Com `--redirects-csv=`, ele **gera um CSV** com `source_path,target_url,status_code,note`, que pode ser importado no Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Os candidatos vêm destas fontes:

- **CSV**: quando `canonical` aponta para um **caminho diferente** de `url`, a linha gera um candidato `301` do caminho antigo para a canonical. Uma canonical com o mesmo caminho não é emitida, pois criaria um loop.
- **Banco Rank Math**: regras ativas de `{prefix}rank_math_redirections`. Apenas regras de **correspondência exata** são exportadas; regex e regras de contém, início ou fim são informadas como ignoradas, pois não representam um único caminho.
- **Yoast gratuito**: não possui tabela de redirecionamentos. A tabela do Yoast Premium não faz parte do escopo do pacote gratuito; use CSV para esses redirecionamentos.

Os candidatos são **sugestões para revisão**. Confira o CSV e importe com [`seo-pro:redirects-import`](/pt-BR/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro), que valida as linhas e rejeita loops, destinos inseguros e duplicatas. O formato é um contrato estável, **redirect CSV format v1**: `source_path,target_url,status_code,note`.

---

## Como interpretar o relatório {#what-the-report-tells-you}

Sem `--json`, a execução exibe uma tabela de resultados — created, updated, unchanged, skipped e scanned —, um **Verification report** e seções para revisão:

- **Verification report**: separa **matched**, linhas associadas a modelos, de **url-only**, sem modelo correspondente, e apresenta as contagens de valores truncados e não mapeados.
- **Truncated**: valores encurtados para caber em uma coluna de `seo_meta`.
- **Not imported**: chaves com dados na origem, mas sem destino no Core 3, **incluindo cada valor distinto de `author`**. Autor não é uma coluna armazenada; ele é fornecido por [`getSEOAuthor()`](/pt-BR/concepts/resolver-precedence). O relatório permite transferir esses dados deliberadamente.
- **Redirect candidates**: quantidade de candidatos exportados e arquivo de destino.
- **Skipped rows by reason**: linhas apenas com URL, posts sem metadados de SEO e regras de redirecionamento sem correspondência exata.
- **Warnings**: avisos, como a resolução de tokens de template.

Use `--json` para obter todos esses dados em formato legível por máquina. O bloco `verification` inclui as contagens matched/url-only e todos os valores de autor.

### Verificação {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict` retorna um código diferente de zero se qualquer página apresentar uma ocorrência. Consulte a [auditoria gratuita de SEO](/pt-BR/guide/audit). O procedimento completo, em ordem, está no [roteiro de migração do WordPress](/pt-BR/guide/wordpress-migration-runbook).

---

Você está migrando de um pacote SEO **Laravel**, como ralphjsmit, artesaos ou Spatie? Consulte [Migração de outros pacotes Laravel](/pt-BR/guide/migrate-from-other-packages).

---
description: "Roteiro para substituir Yoast ou Rank Math em um site ativo: os importadores preenchem campos vazios por padrão, dry-runs não gravam dados e o WordPress permanece intacto."
---

# Roteiro de migração WordPress → Rankbeam {#wordpress-→-rankbeam-migration-runbook}

Este procedimento substitui a estrutura de SEO do WordPress, com Yoast ou Rank Math, pelo Rankbeam. Os importadores preenchem campos vazios por padrão; `--overwrite` permite substituições explicitamente. Simulações não gravam dados, e o banco de origem do WordPress permanece intacto. Faça backup da origem e do destino antes de importar.

O guia [Migração do WordPress](/pt-BR/guide/migrate-from-wordpress) detalha os campos, tokens de template e chaves de origem. Aqui está a sequência operacional.

::: tip O que você precisa
- **Core** (`rankbeam/laravel-seo`) para importar metadados e executar `seo:audit`.
- **Pro** (`rankbeam/laravel-seo-pro`) somente se também importar **redirecionamentos** para a tabela Pro `seo_redirects`.
- Conteúdo já representado por modelos Laravel, como `App\Models\Post`, com o trait [`HasSEO`](/pt-BR/guide/quickstart) e uma forma de associar o slug do WordPress ao modelo: chave de rota ou coluna informada em `--match-by`.
:::

## Como a migração associa os dados {#the-shape-of-the-migration}

As linhas do WordPress são identificadas por **URL ou post**; as de `seo_meta` são **polimórficas**, vinculadas a modelos Eloquent. O importador tenta fazer essa associação e informa três resultados possíveis:

| Resultado | Significado | Ação |
|---|---|---|
| **matched** | Linha associada a um modelo, com gravação em `seo_meta` | Nenhuma |
| **url-only** | Sem modelo correspondente ou sem `--model` informado | Decidir se a página precisa de modelo ou redirecionamento |
| **unmapped** | Dados sem destino no Core 3, especialmente **author** | Transferir para o destino adequado, como `getSEOAuthor()` |

---

## Passo 0: mantenha os dois sites lado a lado {#step-0-—-coexist-no-cutover-yet}

Prepare o Rankbeam **ao lado** do site ativo. Adicione `HasSEO` aos modelos e renderize as tags pela facade ou diretiva, mantendo a instalação WordPress e seu plugin de SEO. Nenhum dado foi importado ainda; esta etapa verifica apenas se a nova aplicação inicia e funciona.

Se os dois sites usarem o mesmo servidor durante a transição, mantenha-os em caminhos separados até o passo 5.

## Passo 1: importe os metadados, começando pela simulação {#step-1-—-import-the-metadata-dry-run-first}

Comece sempre com `--dry-run`. Ele **não grava dados** e apresenta o relatório completo do que aconteceria.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

Opções úteis; veja a lista completa com `php artisan seo:import-from --help`:

| Opção | Finalidade |
|---|---|
| `--model=` | Nome completo da classe do modelo de destino. A opção é repetível, mas os importadores WordPress associam **um** modelo por execução; execute uma vez por tipo de conteúdo |
| `--match-by=` | Coluna do modelo usada para comparar o slug; padrão: chave de rota |
| `--post-type=` | Tipos de post lidos do banco; padrão: `post` e `page` |
| `--connection=` | Conexão do banco que contém as tabelas WordPress |
| `--table=` | **Prefixo** das tabelas WordPress; padrão: `wp_` |
| `--locale=` | Locale das linhas gravadas em `seo_meta` |
| `--redirects-csv=` | Arquivo de candidatos a redirecionamento para o passo 3 |
| `--site-url=` | URL do site antigo, usada para extrair caminhos de URLs absolutas |
| `--overwrite` | Substituir valores existentes; o padrão é **preencher apenas campos vazios** |
| `--limit=` | Limitar a quantidade de linhas da origem, útil no primeiro teste |
| `--json` | Relatório legível por máquina |

Quando a simulação estiver correta, retire `--dry-run` para aplicar:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

A importação é **idempotente** e **preenche apenas campos vazios** por padrão. Sem `--overwrite`, você pode executá-la novamente preservando os metadados já editados no Rankbeam.

## Passo 2: leia e arquive o relatório de verificação {#step-2-—-read-and-archive-the-verification-report}

Cada execução exibe um **Verification report**. Revise os números antes de remover qualquer componente e salve o relatório:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

Confira:

- **matched** deve corresponder à quantidade esperada de páginas com metadados de SEO.
- **url-only** lista páginas sem modelo correspondente. Decida se cada uma precisa de um modelo, de redirecionamento no passo 3 ou de nenhuma ação.
- **truncated** mostra campos encurtados para caber em `seo_meta`. Revise os títulos e descrições afetados.
- **unmapped** mostra dados sem coluna correspondente no Core 3, **com cada valor distinto de `author`**. Autores são fornecidos por `getSEOAuthor()`, não por uma coluna de `seo_meta`; transfira os valores que precisar preservar.

## Passo 3: importe os redirecionamentos no Pro {#step-3-—-import-the-redirects-into-pro}

O importador Core **nunca grava em `seo_redirects`**. Ele entrega um CSV de formato fixo e versionado, **redirect CSV format v1**: `source_path,target_url,status_code,note`. Importe-o no Pro, começando pela simulação:

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Cada linha passa pelas mesmas validações do formulário de redirecionamento do Filament. Linhas malformadas, códigos de status inválidos, **destinos externos inseguros**, **origens duplicadas** e regras que criariam **loops** são ignorados com o motivo informado. A simulação valida o arquivo inteiro, incluindo loops e duplicatas, sem gravar nada. Use `--overwrite` para substituir o destino de uma regra existente.

## Passo 4: verifique com `seo:audit --strict` {#step-4-—-verify-with-seo-audit-strict}

Use a auditoria gratuita, executada dentro da aplicação, como condição para a migração. `--strict` retorna um código diferente de zero se **qualquer** página apresentar uma ocorrência, permitindo usá-lo em CI ou antes da troca:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

A auditoria cobre o modelo e o resolvedor: presença e tamanho de título e descrição, imagem OG, conflitos robots e formato da canonical. HTML renderizado, consultas a canonicals e pontuação de 0 a 100 pertencem à [varredura Pro](/pt-BR/pro/scan-issues). Execute-a também se você usa Pro. Consulte a [auditoria gratuita de SEO](/pt-BR/guide/audit).

Depois confira algumas páginas reais no navegador. Abra o código-fonte e verifique se `<title>`, `<meta name="description">`, canonical, robots e Open Graph apresentam os valores importados.

## Passo 5: verifique antes de remover o sistema antigo {#step-5-—-verify-before-removing-the-legacy-package-table}

Mantenha o banco WordPress, o plugin de SEO e os pacotes antigos até cumprir **todos** os itens aplicáveis:

- [ ] Importação executada para **cada** tipo de conteúdo, com um `--model` por execução.
- [ ] Relatório arquivado com a quantidade esperada de **matched** e sem linhas **url-only** inesperadas.
- [ ] Valores de **autor não mapeados** que precisam ser preservados transferidos para o novo modelo.
- [ ] Redirecionamentos importados no Pro por `seo-pro:redirects-import`, com algumas URLs antigas verificadas retornando 301 para as novas.
- [ ] `php artisan seo:audit --strict` retorna `0`.
- [ ] Com Pro, `php artisan seo:doctor` não informa tabela `seo` antiga remanescente nem colisão em `config/seo.php`.
- [ ] Páginas renderizadas conferidas no navegador.

Sem `--overwrite`, você pode repetir o passo 1 antes dessa verificação: os importadores são idempotentes, preservam campos preenchidos e mantêm os dados de origem no WordPress.

## Passo 6: desative o sistema antigo {#step-6-—-decommission}

Somente depois de concluir o passo 5, retire o WordPress do ar e remova seu banco, tabelas e pacote SEO antigo. Mantenha um backup até confirmar que a nova aplicação está servindo corretamente em produção.

::: tip Reversão
No fluxo padrão dos passos 1 a 4, `seo_meta` recebe dados adicionais, os redirecionamentos são validados e podem ser removidos, e o WordPress fica intacto. Antes do passo 6, a reversão é continuar servindo o WordPress. Depois dele, é restaurar o backup do WordPress. Se você optou por `--overwrite`, o backup do destino também é necessário para recuperar os valores substituídos.
:::

---

Você está migrando de um pacote SEO **Laravel**, como ralphjsmit, artesaos ou Spatie? Consulte [Migração de outros pacotes Laravel](/pt-BR/guide/migrate-from-other-packages).

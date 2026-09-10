---
description: "Migre de fibonoir/laravel-seo v1 para rankbeam/laravel-seo v2, que renomeia o pacote e concentra o núcleo em resolução de metadados, renderização, JSON-LD e sitemaps."
---

# Atualização de fibonoir/laravel-seo v1 {#upgrading-from-fibonoir-laravel-seo-v1}

A versão 2.0.0 renomeia o pacote para `rankbeam/laravel-seo` e concentra o núcleo em resolução de metadados, renderização, JSON-LD e sitemaps. Analisador, varredura, redirecionamentos, monitoramento de 404 e interface administrativa passam para pacotes separados.

## 1. Trocar o pacote {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Atualizar namespaces {#_2-update-namespaces}

Os nomes das classes permanecem iguais; apenas o namespace raiz muda de `Fibonoir\LaravelSEO\*` para `Rankbeam\Seo\*`. Uma substituição no projeto cobre essa mudança. O alias da facade `SEO` e as diretivas Blade `@seo` não mudam.

## 3. Remover arquivos publicados antigos {#_3-delete-stale-published-files}

Antes de remover arquivos ou tabelas, faça backup da configuração publicada, exporte os dados afetados e verifique se consegue restaurá-los. Este guia não migra o histórico de redirecionamentos, 404 ou varreduras de v1 para o esquema diferente de Pro. A compatibilidade abaixo vale somente para `seo_meta` e `seo_defaults`.

::: warning Conflitos que não geram mensagens de erro
O `seo:install` de v1 publicou arquivos na aplicação que podem entrar em conflito com v2 sem produzir uma mensagem de erro.
:::

- **`config/seo.php`:** se foi publicado por v1 ou por `ralphjsmit/laravel-seo`, cujo arquivo o instalador de v1 podia manter, ele se sobrepõe à configuração do pacote e pode tornar `site_name` e os modelos `{site_name}` nulos. Depois do backup, remova-o e publique novamente com `php artisan vendor:publish --tag=seo-config`.
- **Migrações de v1** para tabelas que não pertencem mais ao núcleo: `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache` e `seo_internal_links_index`. Remova os arquivos de migração. Se as tabelas existem em produção, exporte e preserve os dados antes de excluí-las **e antes de instalar** `rankbeam/laravel-seo-pro`, que as recria com outro esquema.
- **Arquivos de exemplo publicados** em `app/` e `resources/js` pelos fluxos de Filament 3, Livewire, Vue ou React de v1: eles referenciam classes que não existem mais.


As duas tabelas do núcleo, `seo_meta` e `seo_defaults`, têm esquema compatível; seus dados são preservados na atualização.

## 4. Funções removidas e seus destinos {#_4-removed-features-and-where-they-went}

| Função de v1 | Onde fica agora |
|---|---|
| Seção SEO de formulários Filament | [`rankbeam/laravel-seo-filament`](/pt-BR/guide/filament), gratuito, MIT |
| Analisador de conteúdo, 32 regras | O analisador antigo não é preservado por esta migração. A detecção de ocorrências de SEO técnico está na varredura de `rankbeam/laravel-seo-pro`; a pontuação numérica de Pro é derivada das ocorrências. |
| Varredura do site | `rankbeam/laravel-seo-pro`, processamento em fila e painel |
| Gerenciamento de redirecionamentos | `rankbeam/laravel-seo-pro`, com validação de regex e proteção contra redirecionamentos abertos |
| Monitoramento de 404 | `rankbeam/laravel-seo-pro`, sem armazenamento de IPs por padrão |
| Análises GA4 e links internos | Backlog de `rankbeam/laravel-seo-pro` |
| Instalador `seo:install` | Removido; a instalação usa require, publicação de configuração e migrações |

## 5. Mudanças de comportamento para revisar {#_5-behavior-changes-to-review}

- **`og:image` e `twitter:image` sempre usam URLs absolutas.** v1 emitia caminhos relativos definidos manualmente sem alterá-los.
- **URLs canônicas derivadas perdem os parâmetros de consulta.** URLs canônicas explícitas permanecem literais.
- **A descoberta automática de sitemaps dá prioridade às fontes registradas.** Não cria um `sitemap-post.xml` duplicado ao lado de um `sitemap-posts.xml` registrado.
- **JSON-LD usa escape `JSON_HEX_*`.** Se você pós-processa o script bruto, considere os escapes de caracteres como `<`.

## 6. Particularidades conhecidas {#_6-known-gotchas}

- O `DatabaseSeeder` padrão do Laravel usa `WithoutModelEvents`, que desativa a criação automática de `HasSEO` nos seeders.
- Se o modelo de título de uma rota já contém a marca, termine-o com o `title_suffix` configurado. O resolvedor então evita adicionar o mesmo sufixo novamente.

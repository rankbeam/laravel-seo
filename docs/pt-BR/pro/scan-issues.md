---
description: "Registro estável dos códigos de ocorrências da varredura Pro, cada um com gravidade e campo fixos para painéis, exportações e pontuação."
---

# Ocorrências de varredura: registro de códigos {#scan-issues-—-the-issue-code-registry}

Cada problema informado pela varredura Pro usa um **código estável** de `Rankbeam\Seo\Pro\Scanning\IssueRegistry`. Os scanners criam ocorrências por `IssueRegistry::make()`, que aplica a gravidade e o campo definidos e **rejeita códigos não registrados**. Painéis, exportações e a [pontuação Pro](/pt-BR/pro/scoring) podem usar esses códigos em vez de interpretar mensagens. O [`seo:audit`](/pt-BR/guide/audit) gratuito usa seu próprio registro Core, com cobertura menor e alguns códigos hreflang diferentes.

Cada código define:

- **id**: string estável salva em `seo_scan_issues.issue_type`.
- **severity**: `critical`, `warning` ou `notice`, **fixa por código**. Gravidades diferentes exigem códigos separados.
- **field**: campo de `seo_meta` afetado, ou *page* para ocorrências da página.
- **execution class**: o que a verificação precisa para executar.
- **evidence**: chaves presentes no array `context` da ocorrência.

## Classes de execução {#execution-classes}

Cada verificação pertence a uma destas três classes:

| Classe | Requisitos | Quem executa |
|---|---|---|
| **metadata** | Modelo e resolvedor Core, sem buscar a página | `PageScanner`; o [`seo:audit`](/pt-BR/guide/audit) gratuito cobre parte dessas verificações |
| **rendered** | HTML servido, por requisição interna ao kernel ou busca externa | `UrlScanner` |
| **network** | Busca **externa** para verificar outro alvo, como uma canonical que aponta para outra página | `UrlScanner`, sempre pelo **SsrfGuard** |

A auditoria gratuita dentro da aplicação não cobre toda a varredura Pro: apenas as verificações **metadata** dispensam a renderização da página. O Pro também busca HTML e verifica destinos pela rede. Filtre o registro com `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`.

## Códigos de metadados {#metadata-codes}

Detectados por `PageScanner` a partir do modelo e do resolvedor. `missing_title`, `missing_description` e os códigos de tamanho também são emitidos pela varredura de URL, medindo o `<head>` servido, com o mesmo significado.

| Código | Gravidade | Campo | Evidência | Significado |
|---|---|---|---|---|
| `missing_title` | critical | title | — | Sem título nem fallback calculável. |
| `missing_description` | warning | description | — | Sem meta description nem fallback calculável. |
| `missing_og_image` | notice | og_image | — | Sem imagem Open Graph nem fallback calculável. |
| `missing_focus_keyword` | notice | focus_keywords | — | Nenhuma palavra-chave de foco definida. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | Título repetido em outras páginas do mesmo locale. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | Descrição repetida em outras páginas do mesmo locale. |
| `title_too_long` | warning | title | `length`, `max`, `script` | Título resolvido acima da recomendação da escrita: 60 para latina, cerca de 30 para CJK. |
| `title_too_short` | notice | title | `length`, `min`, `script` | Título resolvido abaixo do mínimo: 30 para latina, cerca de 15 para CJK. |
| `description_too_long` | warning | description | `length`, `max`, `script` | Descrição resolvida acima da recomendação: 160 / cerca de 80. |
| `description_too_short` | notice | description | `length`, `min`, `script` | Descrição resolvida abaixo do mínimo: 70 / cerca de 35. |
| `robots_conflict_indexing` | critical | robots | `robots` | Diretiva robots contém `index` e `noindex`. |
| `robots_conflict_following` | warning | robots | `robots` | Diretiva robots contém `follow` e `nofollow`. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | Página com canonical autorreferente e `noindex`: heurística para revisão, não prova de que deve ser indexada. Emitida em varreduras de modelo e URL. |
| `invalid_canonical` | critical | canonical | `canonical` | Canonical não é uma URL válida. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | Canonical aponta para outro servidor. |
| `shared_canonical` | notice | canonical | `canonical` | Várias páginas declaram a mesma canonical. |
| `insecure_canonical` | warning | canonical | `canonical` | Canonical `http://` em um site `https`. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | Alternativa hreflang não usa `x-default` nem um código BCP-47 válido. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | Há alternativas, mas nenhuma representa o locale da própria página. |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | O mesmo código hreflang aponta para mais de uma URL, criando um grupo ambíguo. |
| `hreflang_missing_x_default` | notice | alternates | `languages` | Grupo hreflang multilíngue sem fallback `x-default`. |
| `aeo_missing_author` | notice | schema | — | Artigo nos dados estruturados sem entidade de autor, deixando a autoria implícita. |
| `aeo_article_missing_date` | notice | schema | — | Artigo nos dados estruturados sem data de publicação ou modificação. |

Os limites vêm da [política por escrita](/pt-BR/guide/multilingual#title-and-description-budgets-per-script) do Core, usada desde o Pro 2.33: 60/160 para texto latino e cerca de 30/80 para CJK, contados em grafemas como no editor. Os mínimos recomendados são 30/70 para escrita latina e aproximadamente metade para CJK. A chave `script` identifica a faixa aplicada. O tamanho é medido no valor **resolvido**, incluindo fallback e sufixo de título.

Os códigos `hreflang_*` validam as alternativas **declaradas** pelo resolvedor: códigos inválidos ou repetidos, falta de autorreferência e ausência de `x-default` em grupos multilíngues. Só executam quando há alternativas. A **reciprocidade entre páginas** não é verificada por esses metadados; a verificação opcional de rede abaixo busca a outra página.

Os códigos `aeo_*` avaliam sinais de **organização para respostas (AEO)** no grafo JSON-LD. Só são emitidos quando há dados de artigo, como `Article`, `BlogPosting` ou `NewsArticle`, sem `author` ou sem `datePublished`/`dateModified`. Páginas sem artigo não recebem esses avisos. A opção `seo-pro.scan.checks.aeo`, ativada por padrão, controla as verificações, também presentes no [`seo:audit`](/pt-BR/guide/audit) gratuito.

::: tip `missing_focus_keyword` depende da ativação do recurso
O aviso só aparece quando o fluxo de palavras-chave do **Core** está ativado por `seo.keywords.enabled`, cujo padrão é `false`. Desativado, a ausência de palavra-chave não gera aviso. Varredura Pro, [`seo:audit`](/pt-BR/guide/audit) e editor Filament leem a **mesma opção**.
:::

## Códigos de HTML renderizado {#rendered-codes}

`UrlScanner` analisa o HTML servido. Para o mesmo servidor, usa uma requisição ao kernel dentro do processo, sem tráfego externo; para destinos externos, faz uma busca protegida.

| Código | Gravidade | Campo | Evidência | Significado |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | URL respondeu com status 4xx ou 5xx. |
| `empty_response` | critical | page | — | URL retornou corpo vazio. |
| `missing_canonical` | notice | canonical | — | Nenhum `<link rel="canonical">` no head renderizado. |
| `noindex_page` | notice | robots | `robots` | Página `noindex`, informativo. Se também tiver canonical **autorreferente**, usa `noindex_warning`, que afeta a pontuação. |
| `missing_h1` | notice | page | — | Nenhum título `<h1>`. |
| `multiple_h1` | notice | page | `count` | Mais de um `<h1>`, informativo. |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | Imagens do conteúdo sem atributo `alt`. `alt=""` explícito indica imagem decorativa e não gera aviso. |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter` | Texto abaixo da contagem configurada. Usa o tokenizador do checklist: limites de palavras para escritas com espaços e dicionário ICU, `segmenter: intl` com ext-intl, para chinês, japonês e tailandês. |
| `mixed_content` | warning | page | `count`, `sample` | Recursos `http://` em página `https`. |
| `html_lang_missing` | notice | page | — | `<html lang>` ausente ou vazio; tecnologias assistivas podem selecionar uma voz inadequada. |
| `html_lang_invalid` | notice | page | `declared` | `lang` não é uma tag BCP-47 válida, como `english`, `en_US` ou `jp`. |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script` | Escrita do texto visível não corresponde à do idioma declarado, como `lang="en"` em japonês. Requer pelo menos 40 letras; não tenta distinguir idiomas que compartilham a escrita latina. |

## Códigos de rede {#network-codes}

`UrlScanner` executa essas verificações somente com as opções correspondentes ativadas: `seo-pro.scan.url_checks.check_canonical_target` para canonicals e `check_hreflang_reciprocity` para alternativas. Toda busca passa pelo **SsrfGuard**, com protocolos e servidores permitidos, rejeição de IPs privados e limites de tempo e tamanho. Redirecionamentos **não são seguidos**, permitindo identificar destinos que redirecionam. Canonicals e alternativas autorreferentes são ignoradas porque a própria página já foi buscada.

| Código | Gravidade | Campo | Evidência | Significado |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | SsrfGuard recusou o alvo antes de qualquer requisição HTTP. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | Destino da canonical retorna erro HTTP. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | Canonical aponta para redirecionamento; revise para usar a URL final. |
| `canonical_target_noindex` | warning | canonical | `canonical` | Destino da canonical está marcado como `noindex`. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | Destino não pôde ser verificado por bloqueio ou falha de resolução. |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status` | Alternativa declarada não declara o link de volta. O par pode ser ignorado, mas isso não torna a tradução não indexável por si só. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason` | Alternativa não pôde ser buscada por bloqueio, erro, redirecionamento ou limite de tamanho. Reciprocidade não verificada; ausência de evidência não é defeito confirmado. |

A reciprocidade busca no máximo `hreflang_max_alternates` alvos por página, **10** por padrão, incluindo `x-default` e ignorando duplicatas e a própria página. Os códigos de metadados verificam a lista declarada; esta verificação precisa consultar o outro destino.

Todos os caminhos de rede reutilizam o SsrfGuard. Consulte [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md) para o modelo de ameaças e a limitação residual de TOCTOU.

## Como os códigos alimentam a pontuação {#how-codes-feed-the-score}

A [pontuação Pro](/pt-BR/pro/scoring) parte de `100` e desconta penalidades fixas por ocorrência pontuada, com pesos por gravidade. Algumas ficam fora: `missing_focus_keyword`, consultiva; `noindex_page` e `multiple_h1`, informativas; `blocked_url`, `canonical_target_blocked` e `hreflang_target_unverified`, que indicam impossibilidade de verificar; e os sinais consultivos `hreflang_*`, `html_lang_*` e `aeo_*`. A [referência de pontuação](/pt-BR/pro/scoring) lista todos os códigos incluídos e suas penalidades.

## Ciclo de vida das ocorrências {#issue-lifecycle}

A varredura **reconcilia** ocorrências, preservando seu histórico. A identidade combina o alvo — `scannable_type` e `scannable_id` para modelos, ou `url` para rotas e sitemaps — com `issue_type`. Cada código é emitido no máximo uma vez por alvo em cada análise. Casos com várias instâncias, como `missing_image_alt`, `mixed_content` e `hreflang_*`, são agrupados em uma linha com `count` ou `sample`.

Para cada alvo analisado:

- uma ocorrência **sem linha anterior** é criada como `open`, com `detected_at`;
- uma ocorrência que **já está aberta** recebe evidências atualizadas, preservando o primeiro `detected_at`;
- uma ocorrência aberta **não encontrada por uma verificação concluída** passa a **`fixed`**, com `resolved_at`; a linha permanece no histórico;
- uma ocorrência **`fixed` que reaparece** é **reaberta** na mesma linha, com novo `detected_at`;
- uma ocorrência marcada pelo usuário como **`ignored`** permanece intacta.

| Estado | Significado | Definido por |
|---|---|---|
| `open` | Presente atualmente. | Varredura, ao detectar ou confirmar novamente |
| `fixed` | Existia, mas não foi encontrada novamente. | Varredura automática seguinte que conclui a verificação sem encontrá-la |
| `ignored` | Silenciada pelo usuário; fora das contagens abertas e da pontuação. | Ação Ignore do painel |

O histórico permite que o [relatório com sua marca](/pt-BR/pro/reports) apresente **contagens de ocorrências corrigidas e novas** por período, em vez de apenas comparar duas fotografias do estado. Painel, [`seo-pro:scan-status`](/pt-BR/pro/headless) e [pontuação](/pt-BR/pro/scoring) filtram `open`, sem contar linhas `fixed`. Ocorrências corrigidas são atribuídas à execução que as resolveu e seguem a [retenção das varreduras](/pt-BR/pro/production).

## Configuração {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

Buscas protegidas usam `seo-pro.http.max_response_bytes`, **2 MB** por padrão. A varredura do mesmo servidor dentro do processo não tem esse limite.

## Compatibilidade: renomeação de códigos {#compatibility-note-issue-code-rename}

O antigo `robots_conflict`, que tinha duas gravidades, foi dividido para manter uma gravidade por código:

| Código antigo | Código novo | Gravidade |
|---|---|---|
| `robots_conflict` (index + noindex) | `robots_conflict_indexing` | critical |
| `robots_conflict` (follow + nofollow) | `robots_conflict_following` | warning |

Atualize filtros e dados que dependem de `robots_conflict` para os dois códigos novos.

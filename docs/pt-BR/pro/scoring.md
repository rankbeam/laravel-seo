---
description: "A pontuação SEO de Pro, de 0 a 100, é determinística e auditável: cada desconto corresponde a uma ocorrência da varredura e o mesmo conjunto produz o mesmo resultado."
---

# Pontuação SEO: critérios públicos e versionados de Pro {#the-seo-score-—-transparent-versioned-pro-owned}

Pro atribui a cada página uma **pontuação SEO de 0 a 100**. O cálculo é **auditável**: cada desconto corresponde a uma [ocorrência da varredura](/pt-BR/pro/scan-issues), e o mesmo conjunto de ocorrências produz o mesmo número.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip A pontuação pertence a Pro
A pontuação numérica é uma função de **Pro**, salva em `seo_scan_results`. Não é gravada em `seo_meta` do núcleo; a antiga coluna `seo_score` foi removida em Core 3. A auditoria gratuita [`seo:audit`](/pt-BR/guide/audit) mostra **pass / warn / fail**, sem número.
:::

## Os critérios {#the-rubric}

O cálculo usa critérios **públicos e versionados** em `Rankbeam\Seo\Pro\Scanning\ScoreRubric`: uma **lista explícita de códigos** que participam da pontuação e um **desconto fixo por gravidade**.

| Gravidade | Desconto | Significado |
|---|---|---|
| `critical` | **−40** | Ocorrência de alto impacto nesses critérios |
| `warning` | **−15** | Ocorrência que merece investigação em breve |
| `notice` | **−5** | Melhoria complementar |

A gravidade de cada código vem diretamente do [registro de ocorrências](/pt-BR/pro/scan-issues); o cálculo não a redefine. Cada código tem uma gravidade fixa para manter o resultado determinístico.

### O que entra na pontuação {#what-the-score-counts}

São verificações determinísticas selecionadas pelo Rankbeam, incluindo heurísticas que exigem interpretação editorial. Uma ocorrência crítica desconta 40, um aviso 15 e uma nota 5. A pontuação não prevê desempenho nos buscadores.

| Código | Gravidade | Desconto |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

Os códigos de metadados são detectados na varredura de modelo; os de HTML e rede, na varredura de URL. Consulte as [classes de execução](/pt-BR/pro/scan-issues#execution-classes). Assim, a pontuação de um **modelo** representa seus metadados, enquanto a de uma **URL** representa as verificações da página servida. Um modelo com 100 significa ausência de defeitos de metadados detectados, não que o HTML esteja perfeito. Verifique também a URL.

### O que fica fora da pontuação {#what-the-score-deliberately-does-not-count}

Estes códigos são excluídos intencionalmente. Uma verificação automatizada exige que todo código do registro esteja na lista pontuada ou nas exclusões:

| Código | Motivo da exclusão |
|---|---|
| `missing_focus_keyword` | **Orientação editorial.** Depende da ativação de `seo.keywords.enabled`. Não adotar palavras-chave de foco não deve reduzir a pontuação, que não depende dessa opção. |
| `noindex_page` | **Informativo.** `noindex` pode ser intencional e não representa, por si só, um defeito dos metadados. A heurística de noindex com URL canônica para a própria página é pontuada por `noindex_warning`. |
| `multiple_h1` | **Informativo.** Google tolera vários H1; não há desconto por isso. |
| `blocked_url` | **Ausência de evidência.** `SsrfGuard` recusou a chamada e a página não foi verificada; isso não comprova um defeito nela. |
| `canonical_target_blocked` | **Ausência de evidência.** O destino canônico não pôde ser verificado. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Orientativos por enquanto.** Estes são códigos de Pro; a auditoria gratuita tem seu próprio registro hreflang. Incluí-los na pontuação exige alterar `VERSION`. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **Orientativos.** Verificações de idioma ficam fora destes critérios. |
| `hreflang_not_reciprocal` | **Orientativo.** Verificação opcional de reciprocidade, sem desconto. |
| `hreflang_target_unverified` | **Ausência de evidência.** Não foi possível verificar a reciprocidade. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Orientativos.** Apontam um artigo sem entidade de autor ou data na varredura e na auditoria gratuita, mas não alteram a pontuação. A inclusão exigiria mudar `VERSION`. |

Densidade de palavras-chave, palavras de impacto e outras orientações da [lista de verificação da página](/pt-BR/pro/on-page-checklist) não entram nesse cálculo. Pertencem a uma lista pass/warn/fail independente e não são códigos desse registro.

## Versionamento: pontuações antigas não mudam silenciosamente {#versioning-—-historical-scores-never-silently-change}

Cada pontuação salva registra a `ScoreRubric::VERSION` que a produziu em `rubric_version`. Isso tem duas consequências:

- Um código **novo** não desconta nada até ser adicionado explicitamente à lista. Publicar uma nova verificação não altera retroativamente uma pontuação armazenada. Alterar a lista ou os pesos muda os critérios e sua versão.
- O número é **armazenado, não recalculado na leitura**. A pontuação vista na semana passada permanece acompanhada da versão que explica seu cálculo.

## Armazenamento {#where-it-s-stored}

Cada varredura insere ou atualiza uma linha por alvo em `seo_scan_results`:

| Coluna | Conteúdo |
|---|---|
| `scannable_type` / `scannable_id` | Modelo avaliado; nulos para alvos de URL |
| `url` | URL avaliada |
| `score` | Número de 0 a 100 |
| `rubric_version` | Versão dos critérios usados |
| `penalty_total` | Soma bruta dos descontos **antes** do limite mínimo de zero |
| `scored_issues` | Número de ocorrências que alteraram a pontuação |
| `breakdown` | `[{code, severity, penalty}, …]`, com todos os descontos |
| `keywords_enabled` | Estado de `seo.keywords.enabled` durante a execução, registrado por transparência; não influencia a pontuação |
| `scan_run_id` | Execução responsável; vira nulo quando ela é removida, sem excluir a pontuação, que representa estado atual e não histórico de execuções |
| `scored_at` | Momento do cálculo |

## Consultar a pontuação {#reading-the-score}

**Sem painel**, a pontuação mais recente de um modelo:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` mostra a **média do site** no resumo. O painel Filament a apresenta em **Avg. SEO score**, com cor por faixa.

### Faixas de classificação {#grade-bands}

A letra é uma apresentação derivada do número; o valor numérico é o contrato:

| Pontuação | Classificação |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Sinal para revisão antes de publicar: `noindex_warning` {#the-shipping-signal-noindex-warning}

`noindex_warning` aparece quando a página combina `noindex` com uma **URL canônica que aponta para ela mesma**. O Rankbeam trata isso como um sinal para revisão. A autorreferência **não prova** intenção de indexação: a combinação pode ser deliberada. Uma URL canônica em outro domínio não ativa essa heurística. A ocorrência inclui `context.shipping_signal`, como `self_canonical`, além dos valores comparados `canonical` e `page_url`.

Os dois scanners aplicam a verificação. `PageScanner` compara a URL canônica armazenada com a URL do modelo. `UrlScanner` transforma uma página noindex com autorreferência canônica, inicialmente informativa em `noindex_page`, em `noindex_warning`, que afeta a pontuação. Por isso, `noindex_page` fica excluído: o possível conflito é tratado pelo outro código nas duas rotas. Confira a intenção real antes de alterar a diretiva.

## Configuração {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

A lista de códigos e os pesos **não são configuráveis**. O cálculo deve ser igual para uma mesma `rubric_version` em todas as instalações. Alterá-lo exige uma mudança de código e versão dos critérios, não um ajuste de configuração.

---
description: "Verifique metadados com seo:audit: uma tabela por página, gratuitamente, sem fila, licença ou acesso à rede."
---

# Auditoria SEO gratuita (`seo:audit`)

`php artisan seo:audit` responde: **quais problemas existem agora nos metadados das minhas páginas?** O comando percorre os models `HasSEO` no processo atual, **sem fila, licença ou rede**, e mostra **pass / warn / fail** por página, seguido de um resumo.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## O que é verificado {#what-it-checks}

A auditoria executa apenas a classe **metadata**: verificações derivadas do model e do [resolvedor](/pt-BR/concepts/resolver-precedence), sem buscar a página.

| Verificação | Códigos |
|---|---|
| Título e descrição presentes, considerando fallbacks | `missing_title`, `missing_description` |
| Imagem OG presente, considerando fallbacks | `missing_og_image` |
| Tamanho do título e da descrição | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Títulos e descrições duplicados no site | `duplicate_title`, `duplicate_description` |
| Diretivas robots conflitantes e noindex a revisar | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Canonical: formato, outro domínio, URL compartilhada ou insegura | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Preparação para respostas (AEO): dados estruturados de artigos | `aeo_missing_author`, `aeo_article_missing_date` |
| Palavra-chave de foco definida, após ativação | `missing_focus_keyword` |
| Alternativas hreflang do registro do núcleo, quando declaradas | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

Muitos códigos também aparecem no Pro, mas os registros são separados. O núcleo usa `hreflang_missing_self`, enquanto o Pro usa `hreflang_missing_self_reference`. `hreflang_duplicate_code` tem nível `notice` no núcleo e `warning` no Pro. Um nome compartilhado não garante a mesma cobertura ou gravidade. `blank_explicit_override` pertence ao registro do núcleo. Os tamanhos seguem o [orçamento por escrita](/pt-BR/guide/multilingual#title-and-description-budgets-per-script): 60/160 grafemas para texto latino e cerca de 30/80 para CJK. A medida usa o **valor resolvido, incluindo o sufixo do título**. O [editor Filament](/pt-BR/guide/filament) lê a mesma política, embora também possa mostrar texto ainda não salvo.

As verificações hreflang usam a lista após as políticas de `seo.hreflang`, como as tags e o sitemap. A reciprocidade exige rastreamento e fica no Pro.

As verificações **AEO** só se aplicam a artigos JSON-LD (`Article`, `BlogPosting`, `NewsArticle`, …) sem entidade `author` ou sem `datePublished` / `dateModified`. Elas examinam autoria e cronologia explícitas nos dados estruturados. Páginas sem artigo declarado não recebem esses avisos. São recomendações de nível `notice`, excluídas do score Pro de 0 a 100.

## O que não é verificado — limites do comando {#what-it-does-not-check-—-the-capability-boundary}

Uma auditoria local de metadados não cobre toda a análise Pro. Cada execução informa que não inclui:

- **Verificações do HTML servido:** `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content` e `mixed_content` precisam do conteúdo real da página.
- **Verificações de rede do canonical:** `canonical_target_broken` / `_redirect` / `_noindex` exigem uma requisição de saída protegida.
- **O score numérico de 0 a 100:** o Pro o salva com uma rubrica versionada no resultado da análise; veja [score SEO (EN)](/pt-BR/pro/scoring).

Essas funções pertencem ao **Pro**. Consulte o [registro completo de problemas (EN)](/pt-BR/pro/scan-issues).

## Escolher os models {#choosing-what-to-audit}

O comando usa `seo.audit.models`, com fallback para `seo.sitemap.models`:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Você também pode fornecer as classes explicitamente:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Opções {#options}

| Opção | Efeito |
|---|---|
| `--model=` | Classe com `HasSEO`; repetível, substitui a configuração. |
| `--locale=` | Idioma para resolver os dados; padrão da aplicação se omitido. |
| `--limit=` | Máximo de registros por model; `0` significa todos. |
| `--issues-only` | Mostrar apenas páginas com pelo menos um problema. |
| `--strict` | Retornar código de saída diferente de zero se houver qualquer problema, para CI. |
| `--json` | Produzir JSON com páginas, resumo e cobertura, em vez da tabela. |

### Verificação em CI {#ci-gate}

Com `--strict`, a auditoria vira uma verificação do build:

```bash
php artisan seo:audit --strict
```

Retorna `1` se alguma página tiver aviso ou falha e `0` quando todas as páginas auditadas passarem.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Palavras-chave de foco {#focus-keywords}

O aviso `missing_focus_keyword` vem **desativado por padrão**. Ele aparece após ativar o fluxo de palavras-chave:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

O Pro lê a mesma opção, de modo que auditoria, análise e editor compartilham a ativação. Use o [campo Filament](/pt-BR/guide/filament) ou `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Explicar valores inesperados com `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` mostra **o problema**. [`seo:explain`](/pt-BR/guide/explain) explica **a origem do valor**: configuração, padrão, cálculo ou dado explícito; o que foi substituído e quais políticas vieram depois, como sufixo, limpeza do canonical e proteção contra indexação. Use quando uma tag ou resultado for inesperado:

```bash
php artisan seo:explain "App\Models\Post" 42
```

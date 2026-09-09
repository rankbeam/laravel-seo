---
description: "O resolvedor SEO combina seis camadas: as superiores têm prioridade e null nunca substitui um valor de uma camada inferior."
---

# Prioridade do resolvedor

Cada valor SEO efetivo — título, descrição, canonical, robots e imagens — vem da combinação de **seis camadas** pelo `SEOResolver`. As superiores têm prioridade. `null` nunca substitui um valor de uma camada inferior.

## As seis camadas {#the-six-layers}

Da menor para a maior prioridade:

| Nº | Camada | Fonte | Uso comum |
|---|---|---|---|
| 1 | **Configuração do site** | `config/seo.php`: `site_name`, `title_suffix`, `default_og_image`, `default_robots`, … | Padrões da marca |
| 2 | **Padrões globais no banco** | Linhas de `seo_defaults` sem tipo de model | Alterar padrões do site sem deploy |
| 3 | **Padrões por tipo de model** | Linhas de `seo_defaults` vinculadas a uma classe | Imagem OG comum a todos os produtos |
| 4 | **Padrões por rota** | Linhas de `seo_defaults` vinculadas ao nome da rota | Páginas estáticas como `home` ou `contact`, sem model |
| 5 | **Valores calculados** | Atributos do próprio model | Título de `title`, descrição de `excerpt` ou `body` |
| 6 | **Valores explícitos** | Linha `seo_meta` do model, via `saveSEO()` | Valores preenchidos pelo editor |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

O resultado é um objeto de valor imutável `SEOData`, usado pelo Blade, pela saída em array e pelo Inertia.

## Fallbacks calculados, camada 5 {#computed-fallbacks-layer-5}

Sem valor explícito, o resolvedor deriva os dados do model:

- **Título:** atributo `title` ou `name`.
- **Descrição:** primeiro atributo com texto útil em `seo.computed.description_fields`. A ordem padrão é `excerpt`, `summary`, `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`, `article`. O HTML é removido, entidades são decodificadas e o texto é cortado em um limite de palavra segundo `seo.computed.description_max_length`, por padrão 160, sem reticências.
- **Robots:** hook `getSEORobots()` ou atributo `is_indexable`, conforme a seção seguinte.
- **Valores da URL:** canonical e `og:url` a partir de `getUrlForSEO()`.

## Controlar robots e indexabilidade {#controlling-robots-and-indexability}

O núcleo oferece `noindex` por model. `HasSEO` não declara um método robots porque ele é opcional, mas o resolvedor reconhece estas fontes, nesta ordem:

| Prioridade | Fonte | Exemplo |
|---|---|---|
| 1 | **`seo_meta.robots` explícito** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | **Hook `getSEORobots(): ?string`** do model | Retornar `'noindex, nofollow'` ou `null` para seguir à próxima fonte |
| 3 | **Atributo `is_indexable`**, coluna ou accessor | Falso ⇒ `noindex, nofollow`; verdadeiro ⇒ `index, follow` |

```php
class Page extends Model
{
    use HasSEO;

    // Option A: let the resolver derive robots from a boolean flag.
    //   Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));

    // Option B: compute it from your own state.
    public function getSEORobots(): ?string
    {
        return $this->status === 'draft' ? 'noindex, nofollow' : null;
    }
}

// Option C: set it explicitly per page (wins over A and B).
$page->saveSEO(['robots' => 'noindex, follow']);
```

### O que é renderizado {#what-actually-renders}

A política de emissão filtra a diretiva antes de chegar ao `<head>`. A tag `<meta name="robots">` só é gerada **se a diretiva diferir de `default_robots`**, cujo valor inicial é `index,follow`:

- Uma página indexável com `index, follow` não gera tag robots com essa configuração inicial.
- Uma página não indexável gera `<meta name="robots" content="noindex, nofollow">`.
- Diretivas diferentes, como `noindex`, `max-snippet:-1` ou `unavailable_after`, são emitidas literalmente, preservando espaços.

Defina `seo.robots.emit_default = true` para sempre emitir a tag. Veja a [política de robots (EN)](/reference/configuration#robots-rendering-policy).

## Políticas após a resolução {#policies-applied-after-resolution}

Aplicam-se independentemente da camada que forneceu o valor:

- **Sufixo do título:** `title_suffix` é acrescentado, exceto quando o título já termina com ele. Se um template de rota contém a marca, termine-o com o sufixo para evitar repetições como “Brand — X | Brand”.
- **Parâmetros do canonical:** são retirados de URLs derivadas do model ou da requisição, exceto os permitidos em [`canonical.query_whitelist` (EN)](/reference/configuration#canonical-urls), como `page`. Canonicals explícitos são preservados literalmente.
- **Imagens sociais absolutas:** `og:image` e `twitter:image` são emitidas como URLs absolutas, mesmo quando o valor salvo é um caminho relativo.

## Identificar a camada escolhida {#inspecting-which-layer-won}

O [pacote Filament](/pt-BR/guide/filament) mostra a origem por campo: manual, conteúdo, tipo de model, padrão global, configuração ou URL. `SEOWarningEvaluator` também expõe a distinção entre valores manuais e fallbacks para indicadores administrativos próprios.

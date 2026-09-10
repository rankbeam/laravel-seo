---
description: "O contrato que o head de cada frontend deve cumprir ao renderizar dados de SEO do Rankbeam, usado como referência pelos testes e aplicações de exemplo."
---

# Contrato de renderização {#the-rendering-contract}

Este é o **contrato único de referência** que o `<head>` de cada frontend deve cumprir ao renderizar dados de SEO do Rankbeam. Ele orienta:

- os testes unitários de formato do renderizador no Core, em `tests/Unit/Services/RenderingContractTest.php`, executados rapidamente e sem framework na CI do pacote;
- as aplicações de referência em `rankbeam-examples` — Blade, Inertia com Vue, React ou Svelte e Livewire — cujos testes de navegador e SSR verificam as mesmas condições em um DOM real;
- os guias de Blade, Inertia e JSON e Livewire, que devem documentar integrações compatíveis com o contrato.

Se uma integração não cumprir uma cláusula, isso é um **defeito ou uma limitação documentada**. A cláusula continua valendo. A camada de dados (`SEOResolver` → `SEOData` imutável → `TagRenderer`) independe do framework; o que varia é como os dados chegam ao DOM, sobrevivem à navegação no cliente e permanecem visíveis para rastreadores. O contrato define esses resultados.

> Esta especificação passou por uma revisão independente de projeto.
> Uma nova revisão é necessária se ela mudar de forma substancial.

---

## 1. Valores: conteúdo de um `<head>` compatível {#_1-values-—-what-a-compliant-head-contains}

### Título, descrição e URL canônica {#title-description-canonical}

- **Exatamente um `<title>`**, com o título *resolvido* e sem sufixo duplicado. O resolvedor acrescenta `seo.title_suffix` uma vez e evita repeti-lo quando o título já termina com ele.
- **Uma meta description**, somente quando houver descrição resolvida, sem tag vazia.
- **Um `<link rel="canonical">`**.

### Robots {#robots}

- Emitir `<meta name="robots">` **somente quando a diretiva for diferente do padrão do site**. Com `index,follow`, a ausência da tag permite esse mesmo comportamento ao rastreador. A comparação ignora espaços (`index, follow` ≡ `index,follow`); uma diretiva diferente é emitida **sem alterações**. `seo.robots.emit_default = true` força a emissão da tag.
- Aceitar **diretivas avançadas** determinísticas: `noindex`, `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`, `max-video-preview`, `notranslate` e `unavailable_after`. São strings resolvidas cuja **precedência segue a cadeia de resolução**, dos padrões globais aos de rota e modelo e aos valores explícitos. Entradas iguais produzem saídas iguais.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name` e `og:locale`.
- `article:*` (`published_time`, `modified_time`, `author`, `section`, `tag`) **somente quando `og:type === 'article'` e o valor existir**. Não inventar valores nem emiti-los em páginas que não sejam artigos.
- `og:image` com `og:image:width`, `og:image:height`, `og:image:alt` e `og:image:type` **quando conhecidos**. Imagens múltiplas devem ser **agrupadas**: cada `og:image` é seguido imediatamente pelas próprias dimensões, texto alternativo e tipo.

### Twitter Cards {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` e `twitter:image:alt`, quando o texto alternativo for conhecido.
- `twitter:site` e `twitter:creator` são **opcionais e independentes**. Um pode existir sem o outro; nenhum deve ser inventado a partir do outro.

### hreflang e locale {#hreflang-locale}

- Hreflang tem um caminho próprio de resolução pelo método `getSEOAlternates()` do modelo.
- As alternativas hreflang, quando presentes, devem ter URLs **absolutas, normalizadas e únicas por idioma**, com reciprocidade quando os dados estiverem completos. `x-default` só aparece quando configurado.
- `og:locale:alternate` reflete **apenas** locales com uma variante social real. Converta `en-US` em `en_US` e compare os valores convertidos, sem exigir igualdade literal entre os formatos.
- `<html lang>` deve corresponder ao locale resolvido. Esta cláusula faz parte do contrato, embora o elemento `<html>` seja responsabilidade da **aplicação**.

### JSON-LD por página {#per-page-json-ld}

- Deve ser interpretável e seguro contra `</script>`. O conteúdo usa `JSON_HEX_TAG` para impedir que um valor encerre o elemento script antes da hora, protegendo contra XSS armazenado.
- São aceitos **vários blocos `<script>` ou um `@graph` combinado**.
- Um `@id` estável é usado **onde há ligações reais entre entidades**, como Organization ↔ WebSite ↔ WebPage. Nós independentes não precisam obrigatoriamente de `@id` estável.

---

## 2. Normalização e invariantes {#_2-normalization-invariants}

- URLs **absolutas `http(s)`** para `canonical`, `og:url`, `og:image` e `twitter:image`. Tags vazias ou nulas não devem chegar ao DOM.
- **`canonical` e `og:url` DEVEM resolver para a mesma URL normalizada**. Divergência é uma **falha obrigatória**, não um aviso.
- A **política de normalização canônica deve ser consistente**: protocolo, servidor, porta, caixa do caminho, parâmetros permitidos e barra final recebem o mesmo tratamento em todas as saídas. Páginas indexáveis são **autorreferentes**; uma página `noindex` não herda a estratégia canônica de outra página.
- **Escape conforme o destino**: atributos HTML, texto e JSON usam os codificadores apropriados. As verificações comparam **valores semânticos decodificados, não bytes**.
- **A paridade entre renderizadores é semântica**. `render()` em HTML, `toArray()` e `toInertiaHead()` são equivalentes **após normalização**, embora possam diferir na ordem e no formato das tags. As regras de propriedades únicas e repetíveis são explícitas: um `og:title`, vários `article:tag`.
- **Responsabilidade pelas tags**: o renderizador no cliente substitui as tags do pacote, identificadas por chaves conforme a seção 4, sem remover tags independentes da aplicação.

---

## 3. Comportamento na navegação pelo cliente {#_3-behaviour-—-client-side-navigation}

Após cada visita Inertia ou navegação Livewire com `wire:navigate`:

- deve existir **exatamente uma tag de cada propriedade única**, como `<title>`, descrição, canonical e propriedades não repetíveis `og:*` e `twitter:*`, **sem valores antigos**;
- **JSON-LD não deve se acumular**. O schema da página anterior deve ser removido. O Livewire trata `<script>` como um recurso não removível; por isso, os scripts de schema usam `data-seo-schema` e um identificador por URL, e os da página anterior são removidos em `livewire:navigated`, conforme o guia Livewire;
- ao sair de uma página **rica em metadados para outra sem esses dados**, as tags extras devem desaparecer; a nova página não conserva descrição, Open Graph ou schema da anterior;
- não deve haver **avisos de hidratação**, e os metadados devem ser semanticamente iguais antes e depois dela.

---

## 4. Chaves do head no Inertia {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` atribui um **`head-key` estável** a cada entrada meta ou link. O Inertia usa esse atributo para eliminar duplicatas: uma tag no `<Head>` da página com a mesma chave de uma tag do layout a **substitui**.

- A chave base é `name ?? property` nas meta tags e `rel` nos links.
- **Tags repetíveis recebem chaves distintas**: `article:tag` → `article:tag`, `article:tag:1`, …; hreflang → `alternate:en-US`, `alternate:fr-FR`.

Nos templates Vue, vincule **`:head-key`**. O `:key` do Vue identifica elementos para reconciliação de `v-for`; ele não controla a eliminação de duplicatas do head no Inertia.

---

## 5. Visibilidade para rastreadores {#_5-crawler-visibility-explicit-modes}

- **SSR ou pré-renderização DEVEM emitir o contrato completo no HTML bruto da resposta HTTP**. Isso é testado separadamente do DOM hidratado, com JavaScript desativado.
- **Renderização apenas no cliente não pode declarar conformidade para rastreadores**. O Inertia padrão, sem SSR, injeta metadados no cliente; o HTML inicial buscado por um rastreador não contém esses metadados de SEO. Essa limitação deve ser documentada. **Metadados visíveis sem JavaScript exigem SSR ou pré-renderização no Inertia**; JSON-LD destinado a rastreadores também deve ser renderizado no servidor.

---

## 6. Fora do escopo {#_6-out-of-scope-non-goals}

- **Responsabilidades da aplicação**: `charset`, `viewport` e favicons. `<meta charset>` deve preceder metadados com caracteres não ASCII; a aplicação controla essa ordem.
- **Os testes de ponta a ponta verificam somente a saída emitida**. Não comprovam indexação pelo Google, *seleção* de canonical, elegibilidade para resultados enriquecidos, posicionamento nem MIME ou disponibilidade de imagens remotas. Esses aspectos cabem a testes opcionais de integração e HTTP, fora da matriz de navegadores.

---

## 7. Estado de conformidade {#_7-conformance-status}

Estas são as evidências de cada cláusula. **Unitário** indica `RenderingContractTest`, no Core e na CI do pacote. **Navegador/SSR** indica `rankbeam-examples`, na matriz agendada. **Aplicação** indica responsabilidade da aplicação hospedeira. **Planejado** indica uma meta do contrato que ainda não tem dados modelados em `SEOData`; o renderizador emite o subconjunto seguro disponível.

| Cláusula | Estado |
|---|---|
| Exatamente um `<title>` resolvido, sem sufixo duplicado | **Unitário** + navegador |
| Meta description somente quando presente | **Unitário** + navegador |
| Um `<link rel="canonical">`, nunca vazio | **Unitário** + navegador |
| Robots somente quando diferente do padrão, sem alterações, com opção `emit_default` | **Unitário** + navegador |
| Diretivas avançadas de robots pela precedência do resolvedor | **Unitário** do resolvedor |
| `og:title/description/type/url/site_name/locale`; locale `en-US`→`en_US` | **Unitário** + navegador |
| `article:*` somente com `og:type=article` e valores reais | **Unitário** + navegador |
| `og:image` presente e absoluto | **Unitário** + navegador |
| `og:image:width/height/alt`, `og:image:type` e agrupamento de imagens múltiplas | **Planejado**: `SEOData` contém uma única string `ogImage`, sem dimensões, texto alternativo ou tipo. O renderizador emite um `og:image` absoluto. |
| `twitter:card/title/description/image`; `site` e `creator` independentes | **Unitário** + navegador |
| `twitter:image:alt` | **Planejado**: ainda não há campo de texto alternativo de imagem. |
| Hreflang absoluto e único por idioma | **Unitário** + navegador |
| Reciprocidade hreflang e `x-default` quando configurado | Navegador, conforme os dados |
| `og:locale:alternate` refletindo variantes sociais reais | **Planejado**: ainda não há mapa de variantes sociais por locale. |
| Correspondência de `<html lang>` | **Aplicação**, também verificada no navegador |
| JSON-LD interpretável e seguro contra `</script>` | **Unitário** + navegador |
| Vários scripts ou `@graph`; `@id` estável onde entidades se ligam | **Unitário** do grafo comercial + navegador |
| URLs absolutas, sem tags vazias ou nulas | **Unitário** + navegador |
| `canonical` ≡ `og:url`, com falha em caso de divergência | **Unitário** + navegador |
| Normalização canônica consistente, autorreferência e isolamento de noindex | Navegador |
| Escape por destino e paridade semântica decodificada | **Unitário** |
| Paridade semântica entre `render()`, `toArray()` e `toInertiaHead()` | **Unitário** |
| `head-key` estável no Inertia e chaves distintas para tags repetíveis | **Unitário** + navegador |
| Navegação: propriedades únicas sem dados antigos, sem acúmulo de JSON-LD e com remoção de tags extras | Navegador; o renderizador fornece os atributos `data-seo-schema` necessários à limpeza |
| Ausência de avisos de hidratação e paridade antes e depois dela | Navegador |
| SSR com contrato completo no HTML bruto; limitação de CSR documentada | Navegador + documentação |

As cláusulas **planejadas** são lacunas documentadas. O contrato permanece como meta; implementá-las exige novos campos ou colunas em `SEOData`, em uma versão minor compatível. Hoje, o renderizador emite o subconjunto seguro e nunca inventa valores ausentes.

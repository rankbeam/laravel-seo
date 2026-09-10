---
description: "Use as diretivas @seo em aplicações Livewire: elas geram HTML no head e funcionam em componentes de página inteira e layouts Blade."
---

# Livewire {#livewire}

As diretivas Blade `@seo` geram HTML no `<head>` sem depender do framework do frontend. Funcionam em aplicações Livewire da mesma forma que em Blade.

## Renderização inicial da página inteira {#initial-full-page-render}

Em um **componente Livewire de página inteira**, retornado diretamente por uma rota, ou em um layout Blade que envolve componentes Livewire, `@seo` funciona como no [guia de Blade](/pt-BR/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

A primeira resposta HTTP contém o head completo e visível aos rastreadores: título, descrição, URL canônica, Open Graph, Twitter e JSON-LD. Esse é o documento recebido pelos rastreadores e serviços de prévia social.

## A particularidade de `wire:navigate` {#the-wire-navigate-caveat}

O [`wire:navigate`](https://livewire.laravel.com/docs/navigate) transforma cliques em visitas semelhantes às de uma SPA. Nessas visitas, o Livewire substitui o `<body>` e **mescla o `<head>`**, mas trata as tags de formas diferentes:

- **`<title>`, `<meta>` e `<link>`** são mesclados a partir do head da nova página, então o título e os metadados resolvidos geralmente são atualizados.
- **`<script>` é tratado como um recurso que não deve ser removido.** O Livewire mantém todos os scripts já vistos para evitar problemas de reexecução. Isso faz os **blocos `<script>` JSON-LD se acumularem**: depois de visitar três publicações, os esquemas das três podem permanecer no head, e uma ferramenta de dados estruturados encontra entidades incorretas ou múltiplas.

Para permitir a limpeza, o renderizador **marca cada script JSON-LD** que gera:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## Adicionar a limpeza de JSON-LD {#ship-the-json-ld-cleanup}

Adicione este código uma vez, por exemplo no layout raiz depois de `@livewireScripts`. A cada `wire:navigate`, ele mantém apenas o esquema da **página atual** e remove os anteriores:

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        // The page we are now on. data-seo-url is the canonical (query-stripped),
        // so compare on the query-stripped location.
        const here = window.location.href.split('#')[0].split('?')[0]

        // Keep only the LAST schema for this page; remove every other-URL
        // (stale) script AND same-URL duplicates Livewire re-adds when a page is
        // revisited — including clearing a lone stale script when this page has
        // none. Iterate from the end so the freshest copy is the one kept.
        const scripts = [...document.querySelectorAll('script[data-seo-schema]')]
        let kept = false
        for (let i = scripts.length - 1; i >= 0; i--) {
            const url = (scripts[i].getAttribute('data-seo-url') || '').split('?')[0]
            if (url === here && !kept) { kept = true; continue }
            scripts[i].remove()
        }
    })
</script>
```

O código usa apenas o marcador `data-seo-schema` e o identificador por URL que o renderizador já emite. Não exige configuração por página.

::: warning Compare com a URL atual
Uma versão anterior deste exemplo encerrava a execução quando havia menos de dois scripts de esquema e considerava o último script adicionado como o da página atual. Isso preservava um esquema antigo ao navegar de uma página **com** JSON-LD para outra **sem** JSON-LD, pois só restava o script anterior. Também não removia uma **duplicata da mesma URL** que o Livewire adicionasse ao revisitar a página. Comparar cada `data-seo-url` com `window.location` e manter apenas a **última** correspondência remove esquemas antigos e duplicatas. É esse comportamento que a aplicação Livewire de `rankbeam-examples` e seu teste de navegador verificam.
:::

::: tip Metadados únicos durante a navegação SPA
A mesclagem do head do Livewire evita metadados e links únicos desatualizados na maioria dos casos, mas o comportamento exato depende da versão e da estrutura do layout. Para páginas em que os metadados recebidos pelos rastreadores são essenciais, prefira **recarregar a página inteira**, com um link sem `wire:navigate`, ou **renderizar no servidor**, para que a primeira resposta HTTP seja a referência. A aplicação Livewire de [`rankbeam-examples`](https://github.com/rankbeam) testa um fluxo real de `wire:navigate` no navegador.
:::

## Filament {#filament}

O Filament usa Livewire, mas é uma **interface administrativa de edição**: altera `seo_meta` e não renderiza o head do site público. Consulte o [guia de Filament](/pt-BR/guide/filament); as instruções desta página não se aplicam ao painel administrativo.

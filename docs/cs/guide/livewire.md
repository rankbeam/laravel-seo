---
description: "Používejte obecné direktivy @seo Rankbeamu v aplikacích Livewire. Vypisují prosté HTML do hlavičky a fungují v celostránkových komponentách i layoutech stejně jako v Blade."
---

# Livewire {#livewire}

Direktivy Blade `@seo` nejsou vázané na konkrétní frontendový framework. Vypisují prosté HTML do `<head>`, takže v aplikaci Livewire fungují stejně jako v Blade.

## Počáteční vykreslení celé stránky {#initial-full-page-render}

V **celostránkové komponentě Livewire**, tedy routě vracející komponentu, nebo v layoutu Blade obalujícím komponenty Livewire funguje `@seo` stejně jako v [průvodci Blade](/cs/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

První odpověď HTTP obsahuje úplnou hlavičku viditelnou robotům: titulek, popis, kanonickou adresu, Open Graph, Twitter a JSON-LD. Právě tuto cestu vidí roboti vyhledávačů a nástroje sociálních sítí a její výstup je správný.

## Omezení `wire:navigate` {#the-wire-navigate-caveat}

Funkce [`wire:navigate`](https://livewire.laravel.com/docs/navigate) Livewire mění kliknutí na odkazy v návštěvy podobné SPA. Při takové návštěvě Livewire vymění `<body>` a **sloučí `<head>`**, ale pro SEO balíček platí důležitý rozdíl:

- **`<title>` a `<meta>`/`<link>`** se slučují z hlavičky nové stránky, takže vyhodnocený titulek a metadata se obvykle aktualizují.
- **`<script>` se považuje za neodstranitelný zdroj.** Livewire ponechává každý `<script>`, který kdy načetl, aby jejich opětovné spouštění nenarušilo JavaScript. **Bloky JSON-LD `<script>` se proto hromadí**: po návštěvě tří příspěvků zůstávají v hlavičce strukturovaná data všech tří a nástroj, který je čte, vidí nesprávné nebo vícenásobné entity.

Aby bylo možné staré bloky odstranit, renderer **označuje každý generovaný skript JSON-LD**:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## Přidejte odstraňování starých JSON-LD {#ship-the-json-ld-cleanup}

Tento kód vložte jednou, například do kořenového layoutu za `@livewireScripts`. Při každém `wire:navigate` ponechá pouze strukturovaná data **aktuální stránky** a stará odstraní:

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

Využívá pouze značku `data-seo-schema` a identifikátor URL, které renderer už generuje. Nevyžaduje zapojování pro jednotlivé stránky.

::: warning Porovnávejte s aktuální URL, ne s naposledy přidaným skriptem
Dřívější verze ukázky končila, pokud obsahovala méně než dva skripty strukturovaných dat, a za aktuální stránku považovala *naposledy přidaný* skript. Při přechodu ze stránky **s** JSON-LD na stránku **bez** nich tak v hlavičce zůstala stará data: přítomen byl jen původní skript a předčasný návrat ho ponechal. Takový postup navíc neodstraní **duplicitu stejné URL**, kterou Livewire přidá při opakované návštěvě. Porovnání každého `data-seo-url` s `window.location` a ponechání pouze **poslední** shody odstraňuje stará data i duplicity ve všech těchto případech. Právě to ověřuje aplikace Livewire `rankbeam-examples` a její test v prohlížeči.
:::

::: tip Jedinečné metatagy při navigaci SPA
Slučování hlavičky v Livewire většinou brání zastarávání jedinečných značek `<meta>`/`<link>`. Přesné chování ale závisí na verzi Livewire a struktuře layoutu. U stránek, kde jsou správná metadata pro roboty zásadní, upřednostněte **úplné načtení stránky** běžným odkazem bez `wire:navigate` nebo **vykreslení na serveru**, aby rozhodovala první odpověď HTTP. Aplikace Livewire [`rankbeam-examples`](https://github.com/rankbeam) ověřuje skutečný průchod `wire:navigate` v prohlížeči.
:::

## Filament {#filament}

Filament používá Livewire uvnitř, ale jde o **administrační prostředí pro tvorbu obsahu**. Upravuje `seo_meta` a nikdy nevykresluje hlavičku veřejného frontendu. Viz [průvodce Filamentem](/cs/guide/filament); uvedené postupy se na administrační panel nevztahují.

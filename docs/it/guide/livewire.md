---
description: "Usa le direttive @seo di Rankbeam nelle app Livewire: generano HTML nell’head e funzionano nei componenti a pagina intera e nei layout Blade."
---

# Livewire {#livewire}

Le direttive Blade `@seo` non dipendono dal framework frontend: generano HTML nel `<head>` e funzionano nelle applicazioni Livewire come in Blade.

## Rendering iniziale della pagina intera {#initial-full-page-render}

In un **componente Livewire a pagina intera**, restituito direttamente da una rotta, oppure in un layout Blade che contiene componenti Livewire, `@seo` funziona come descritto nella [guida a Blade](/it/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

La prima risposta HTTP contiene l’head completo, visibile ai crawler: titolo, descrizione, canonical, Open Graph, Twitter e JSON-LD. È la risposta che ricevono crawler e strumenti di anteprima social.

## L’avvertenza su `wire:navigate` {#the-wire-navigate-caveat}

Il [`wire:navigate`](https://livewire.laravel.com/docs/navigate) di Livewire trasforma i clic sui link in navigazioni simili a quelle di una SPA. Durante queste visite Livewire sostituisce il `<body>` e **unisce il `<head>`**, ma tratta i suoi elementi in modi diversi:

- **`<title>`, `<meta>` e `<link>`** vengono uniti a quelli della nuova pagina, quindi il titolo risolto e i metadati in genere si aggiornano.
- **`<script>` viene trattato come una risorsa da conservare.** Livewire mantiene ogni `<script>` incontrato per evitare che una nuova esecuzione interrompa il JavaScript. Di conseguenza, **gli script JSON-LD si accumulano**: dopo aver visitato tre articoli, l’head contiene gli schemi di tutti e tre. Uno strumento che legge i dati strutturati trova entità errate o multiple.

Per consentire la pulizia, il renderer **contrassegna ogni script JSON-LD** generato:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## Aggiungi la pulizia del JSON-LD {#ship-the-json-ld-cleanup}

Inserisci questo codice una sola volta, per esempio nel layout principale dopo `@livewireScripts`. A ogni `wire:navigate` conserva solo lo schema della **pagina corrente** ed elimina quelli obsoleti:

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

Il codice usa soltanto il marcatore `data-seo-schema` e l’identificatore per URL già emessi dal renderer. Non richiede modifiche alle singole pagine.

::: warning Confronta con l’URL corrente, non con l’ultimo script aggiunto
Una versione precedente di questo esempio si interrompeva quando gli script erano meno di due e considerava corrente l’ultimo script aggiunto. Questo lasciava uno schema obsoleto nell’head passando da una pagina **con** JSON-LD a una pagina **senza** JSON-LD: rimaneva un solo script, quindi l’uscita anticipata lo conservava. Inoltre, non eliminava i **duplicati dello stesso URL** aggiunti da Livewire quando si tornava su una pagina. Confrontare ogni `data-seo-url` con `window.location` e mantenere solo l’**ultima** corrispondenza elimina sia gli schemi obsoleti sia i duplicati in questi casi. L’app Livewire di `rankbeam-examples` e il relativo test nel browser verificano questo comportamento.
:::

::: tip Metadati unici nella navigazione SPA
L’unione dell’head di Livewire evita nella maggior parte dei casi che i singoli tag `<meta>` e `<link>` rimangano obsoleti. Il comportamento preciso dipende però dalla versione di Livewire e dalla struttura del layout. Quando è essenziale che i crawler ricevano i metadati corretti, preferisci un **ricaricamento completo**, con un link senza `wire:navigate`, oppure il **rendering sul server**, così la prima risposta HTTP contiene i dati corretti. L’app Livewire di [`rankbeam-examples`](https://github.com/rankbeam) verifica un percorso reale con `wire:navigate` nel browser.
:::

## Filament {#filament}

Filament usa Livewire internamente, ma è un’**interfaccia di amministrazione dei contenuti**: modifica `seo_meta` e non genera l’head del frontend pubblico. Vedi la [guida a Filament](/it/guide/filament); le indicazioni di questa pagina non riguardano il pannello di amministrazione.

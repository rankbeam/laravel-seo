---
description: "Gebruik Rankbeams frameworkonafhankelijke @seo-directives in Livewire-apps — ze genereren gewone HTML in de head en werken in volledige paginacomponenten en Blade-layouts zoals in Blade."
---

# Livewire {#livewire}

De `@seo`-directives voor Blade zijn frameworkonafhankelijk — ze genereren gewone HTML in
de `<head>` en werken daardoor in elke Livewire-app zoals in Blade.

## Eerste rendering (volledige pagina) {#initial-full-page-render}

In een **Livewire-component voor een volledige pagina** (een route die een component retourneert), of in elke
Blade-layout rond Livewire-componenten, werkt `@seo` precies zoals in de
[Blade-gids](/nl/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

Het eerste HTTP-antwoord bevat de volledige head die crawlers kunnen zien — titel,
beschrijving, canonieke URL, Open Graph, Twitter en JSON-LD. Dit is het pad dat
crawlers en sociale scrapers zien, en het is volledig correct.

## De beperking van `wire:navigate` {#the-wire-navigate-caveat}

Livewire's [`wire:navigate`](https://livewire.laravel.com/docs/navigate) maakt van
linkklikken bezoeken zoals in een SPA. Bij zo'n bezoek vervangt Livewire de `<body>`
en **voegt het de `<head>` samen** — met één belangrijk verschil voor een SEO-pakket:

- **`<title>` en `<meta>`/`<link>`** worden uit de head van de nieuwe pagina samengevoegd, zodat
  de uiteindelijke titel en metadata doorgaans worden bijgewerkt.
- **`<script>` wordt behandeld als een asset die niet mag worden verwijderd.** Livewire bewaart elk
  `<script>` dat het ooit heeft gezien om te voorkomen dat opnieuw uitvoeren je JavaScript verstoort. Daardoor
  **stapelen JSON-LD-`<script>`-blokken zich op**: na een bezoek aan drie
  berichten staan de schema's van alle drie tegelijk in de head. Een tool die
  gestructureerde gegevens leest, ziet dan de verkeerde of meerdere entiteiten.

Om opruimen mogelijk te maken, **markeert de renderer elk JSON-LD-script** dat hij genereert:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## JSON-LD-opruiming toevoegen {#ship-the-json-ld-cleanup}

Voeg dit één keer toe (bijvoorbeeld in je root-layout, na `@livewireScripts`). Bij elke
`wire:navigate` blijft alleen het schema van de **huidige pagina** behouden en worden
verouderde schema's verwijderd:

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

Dit gebruikt alleen de `data-seo-schema`-markering en de ID per URL die de renderer
al genereert — zonder koppelingen per pagina.

::: warning Vergelijk met de huidige URL, niet met het laatst toegevoegde script
Een eerdere versie van dit fragment stopte wanneer er minder dan twee schemascripts
waren en behandelde het *laatst toegevoegde* script als dat van de huidige pagina. Daardoor
blijft een verouderd schema in de head staan wanneer je van een pagina **met** JSON-LD
naar een pagina **zonder** JSON-LD navigeert (alleen het oude script is aanwezig, dus de vroege return
behoudt het). Bovendien kan dat fragment geen **duplicaat voor dezelfde URL** verwijderen dat Livewire opnieuw toevoegt wanneer een
pagina wordt herbezocht. Elke `data-seo-url` met `window.location` vergelijken en
alleen de **laatste** overeenkomst behouden, verwijdert in alle gevallen zowel verouderde schema's *als* duplicaten
— dat is wat de Livewire-app `rankbeam-examples` en de bijbehorende browsertest
controleren.
:::

::: tip Enkelvoudige metatags bij SPA-navigatie
Livewire's samenvoeging van de head voorkomt in de meeste gevallen dat enkelvoudige `<meta>`-/`<link>`-tags verouderen,
maar het exacte gedrag hangt af van je Livewire-versie en de
opbouw van je layout. Geef voor pagina's waar correcte metadata voor crawlers essentieel is
de voorkeur aan **volledig herladen** (een gewone link zonder `wire:navigate`) of
**serverrendering**, zodat het eerste HTTP-antwoord leidend is. De
Livewire-app [`rankbeam-examples`](https://github.com/rankbeam) doorloopt een echte
`wire:navigate`-flow in de browser om dit te verifiëren.
:::

## Filament {#filament}

Filament gebruikt intern Livewire, maar is een **beheerinterface voor contentbewerking** —
het bewerkt `seo_meta` en rendert nooit de head van je openbare frontend. Zie de
[Filament-gids](/nl/guide/filament); niets op deze pagina is van toepassing op het beheerpaneel.

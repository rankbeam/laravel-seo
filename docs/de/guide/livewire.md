---
description: "Die @seo-Direktiven von Rankbeam in Livewire nutzen: Sie geben gewöhnliches HTML im Head aus und funktionieren in Ganzseitenkomponenten und Blade-Layouts wie in Blade."
---

# Livewire {#livewire}

Die Blade-Direktiven `@seo` sind nicht an ein Frontend-Framework gebunden. Sie geben gewöhnliches HTML im `<head>` aus und funktionieren in Livewire-Apps genauso wie in Blade.

## Erstes Rendern der vollständigen Seite {#initial-full-page-render}

In einer **Livewire-Ganzseitenkomponente**, also einer Route, die eine Komponente zurückgibt, oder einem Blade-Layout um Livewire-Komponenten funktioniert `@seo` wie in der [Blade-Anleitung](/de/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

Die erste HTTP-Antwort enthält den vollständigen, für Crawler sichtbaren Head mit Titel, Beschreibung, Canonical, Open Graph, Twitter und JSON-LD. Diesen vollständig gerenderten Stand sehen Crawler und Social-Media-Scraper.

## Die Besonderheit bei `wire:navigate` {#the-wire-navigate-caveat}

Livewires [`wire:navigate`](https://livewire.laravel.com/docs/navigate) macht aus Link-Klicks Besuche nach dem SPA-Prinzip. Dabei ersetzt Livewire den `<body>` und **führt den `<head>` zusammen**. Für ein SEO-Paket gibt es einen entscheidenden Unterschied zwischen den Elementen:

- **`<title>` und `<meta>`/`<link>`** werden aus dem Head der neuen Seite übernommen. Aufgelöster Titel und Meta-Tags aktualisieren sich daher normalerweise.
- **`<script>` gilt als nicht entfernbares Asset.** Livewire behält jedes bereits geladene `<script>`, damit erneutes Ausführen dein JavaScript nicht beschädigt. Dadurch **sammeln sich JSON-LD-`<script>`-Blöcke an**: Nach drei besuchten Beiträgen stehen die Schemas aller drei Beiträge gleichzeitig im Head. Ein Tool für strukturierte Daten liest dann falsche oder mehrere Entitäten.

Damit sich diese Einträge bereinigen lassen, **markiert der Renderer jedes ausgegebene JSON-LD-Script**:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## JSON-LD-Bereinigung einbauen {#ship-the-json-ld-cleanup}

Füge diesen Code einmal hinzu, beispielsweise im Root-Layout nach `@livewireScripts`. Bei jedem `wire:navigate` behält er nur das Schema der **aktuellen Seite** und entfernt veraltete Einträge:

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

Dafür genügen der Marker `data-seo-schema` und die URL-bezogene ID, die der Renderer bereits ausgibt. Einzelne Seiten benötigen keine zusätzliche Einrichtung.

::: warning Mit der aktuellen URL vergleichen, nicht mit dem zuletzt angehängten Script
Eine frühere Fassung brach ab, wenn weniger als zwei Schema-Scripts vorhanden waren, und behandelte das **zuletzt angehängte** Script als aktuell. Beim Wechsel von einer Seite **mit** JSON-LD zu einer Seite **ohne** JSON-LD blieb dadurch das einzige alte Script im Head. Auch ein **Duplikat derselben URL**, das Livewire beim erneuten Besuch hinzufügt, ließ sich so nicht entfernen. Der Vergleich jedes `data-seo-url` mit `window.location`, bei dem nur der **letzte** Treffer erhalten bleibt, entfernt sowohl veraltete Schemas als auch diese Duplikate. Diese Fälle prüfen die Livewire-App in `rankbeam-examples` und ihr Browsertest.
:::

::: tip Einzelne Meta-Tags bei SPA-Navigation
Livewires Head-Zusammenführung verhindert meist veraltete einzelne `<meta>`-/`<link>`-Tags. Das genaue Verhalten hängt jedoch von der Livewire-Version und dem Layout ab. Für Seiten, bei denen verlässliche Crawler-Metadaten entscheidend sind, verwende einen **vollständigen Seitenaufruf**, also einen gewöhnlichen Link ohne `wire:navigate`, oder **serverseitiges Rendering**, damit bereits die erste HTTP-Antwort die maßgeblichen Daten enthält. Die Livewire-App in [`rankbeam-examples`](https://github.com/rankbeam) prüft einen echten `wire:navigate`-Ablauf im Browser.
:::

## Filament {#filament}

Filament basiert auf Livewire, ist aber eine **Administrationsoberfläche zur Bearbeitung**. Es ändert `seo_meta` und rendert niemals den Head deines öffentlichen Frontends. Siehe die [Filament-Anleitung](/de/guide/filament); die Hinweise auf dieser Seite betreffen nicht das Admin-Panel.

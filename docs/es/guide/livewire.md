---
description: "Usa las directivas @seo de Rankbeam con Livewire: emiten HTML en el head y funcionan en componentes de página completa y layouts Blade."
---

# Livewire {#livewire}

Las directivas Blade `@seo` no dependen del framework del frontend. Emiten HTML normal en el `<head>`, por lo que funcionan en cualquier aplicación Livewire igual que en Blade.

## Renderizado inicial de página completa {#initial-full-page-render}

En un **componente Livewire de página completa**, cuya ruta devuelve un componente, o en un layout Blade que envuelve componentes Livewire, `@seo` funciona como en la [guía de Blade](/es/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

La primera respuesta HTTP incluye el head completo y visible para rastreadores: título, descripción, canonical, Open Graph, Twitter y JSON-LD. Esta es la respuesta que reciben los rastreadores y extractores de vistas previas sociales, con todas las etiquetas correctas.

## La particularidad de `wire:navigate` {#the-wire-navigate-caveat}

[`wire:navigate`](https://livewire.laravel.com/docs/navigate) de Livewire convierte los clics en enlaces en visitas de tipo SPA. En esas visitas, Livewire sustituye el `<body>` y **combina el `<head>`**, pero trata sus elementos de forma distinta:

- **`<title>` y `<meta>`/`<link>`** se combinan con el head de la nueva página, por lo que el título y los metadatos resueltos suelen actualizarse.
- **`<script>` se trata como un recurso que no se elimina.** Livewire conserva todos los `<script>` que ha encontrado para evitar que su reejecución rompa el JavaScript. Por tanto, **los bloques `<script>` JSON-LD se acumulan**: después de visitar tres publicaciones, los datos estructurados de las tres permanecen en el head y una herramienta encuentra entidades incorrectas o múltiples.

Para permitir la limpieza, el renderizador **marca cada script JSON-LD** que emite:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## Añadir la limpieza de JSON-LD {#ship-the-json-ld-cleanup}

Añade este código una vez, por ejemplo en el layout raíz después de `@livewireScripts`. En cada `wire:navigate`, conserva únicamente los datos estructurados de **la página actual** y elimina los antiguos:

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

Solo utiliza el marcador `data-seo-schema` y el identificador por URL que ya emite el renderizador; no requiere configuración por página.

::: warning Compara con la URL actual, no con el último script añadido
Una versión anterior de este ejemplo terminaba si había menos de dos scripts de datos estructurados y consideraba actual el último añadido. Eso deja datos obsoletos al pasar de una página **con** JSON-LD a otra **sin** él: solo queda el script anterior y la salida anticipada lo conserva. Tampoco elimina un **duplicado de la misma URL** que Livewire vuelve a añadir al revisitar una página. Comparar cada `data-seo-url` con `window.location` y conservar solo la **última** coincidencia elimina tanto datos obsoletos como duplicados en todos esos casos. La aplicación Livewire de `rankbeam-examples` y su prueba de navegador verifican este comportamiento.
:::

::: tip Metadatos únicos durante la navegación SPA
La combinación del head de Livewire evita que las etiquetas únicas `<meta>`/`<link>` queden obsoletas en la mayoría de los casos, pero el comportamiento exacto depende de la versión de Livewire y de la estructura del layout. Si los metadatos correctos para rastreadores son esenciales, utiliza una **recarga completa** mediante un enlace sin `wire:navigate`, o **renderiza la página en el servidor** para que la primera respuesta HTTP contenga los datos definitivos. La aplicación Livewire de [`rankbeam-examples`](https://github.com/rankbeam) prueba un flujo real de `wire:navigate` en el navegador.
:::

## Filament {#filament}

Filament utiliza Livewire internamente, pero es una **interfaz de edición administrativa**: modifica `seo_meta` y nunca genera el head del frontend público. Consulta la [guía de Filament](/es/guide/filament); lo descrito aquí no se aplica al panel de administración.

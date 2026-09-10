---
description: "La lista de requisitos que debe cumplir el head de cada frontend al renderizar datos SEO de Rankbeam, utilizada por las pruebas del núcleo y las aplicaciones de referencia."
---

# El contrato de renderizado {#the-rendering-contract}

Esta es la **lista única de requisitos** que debe cumplir el `<head>` de cada frontend al renderizar datos SEO de Rankbeam. Es la referencia para:

- Las pruebas unitarias de estructura del renderizador del núcleo, `tests/Unit/Services/RenderingContractTest.php`: la parte rápida e independiente del framework cubierta por el CI del paquete.
- Las aplicaciones de referencia de `rankbeam-examples` —Blade, Inertia con Vue / React / Svelte y Livewire—, cuyas pruebas de navegador y SSR verifican las mismas condiciones en un DOM real.
- Las guías de Blade, Inertia y JSON, y Livewire, que no deben documentar soluciones que incumplan el contrato.

Si un stack no cumple una cláusula, se trata de un **defecto o una limitación documentada**, no de un motivo para rebajar el contrato. La capa de datos (`SEOResolver` → `SEOData` inmutable → `TagRenderer`) es independiente del framework. Lo que cambia es cómo llegan los datos resueltos al DOM, cómo se mantienen al navegar en el cliente y cómo siguen siendo visibles para rastreadores; eso es lo que define este contrato.

> Esta especificación se reforzó mediante una revisión independiente del diseño.
> Solo necesita otra revisión si cambia de forma sustancial.

---

## 1. Valores: qué contiene un `<head>` conforme {#_1-values-—-what-a-compliant-head-contains}

### Título, descripción y canonical {#title-description-canonical}

- **Exactamente un `<title>`**, con el título resuelto y sin sufijo duplicado: el resolvedor añade `seo.title_suffix` una sola vez y comprueba si el título ya termina con él.
- **Una meta description**, solo si se resuelve una descripción; nunca una etiqueta vacía.
- **Un `<link rel="canonical">`**.

### Robots {#robots}

- Emite `<meta name="robots">` **solo si la directiva difiere del valor predeterminado del sitio**. Un `index,follow` redundante no aporta información; su ausencia ya se interpreta como `index,follow`. La comparación ignora los espacios (`index, follow` ≡ `index,follow`), y una directiva distinta se emite **tal cual**. `seo.robots.emit_default = true` fuerza la etiqueta.
- Admite **directivas avanzadas** deterministas: `noindex`, `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`, `max-video-preview`, `notranslate`, `unavailable_after`. Son valores de cadena resueltos cuya **precedencia sigue la cadena del resolvedor**: global → ruta → modelo → explícito. Las mismas entradas producen la misma salida.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`, `og:locale`.
- `article:*` (`published_time`, `modified_time`, `author`, `section`, `tag`) **solo cuando `og:type === 'article'` y el valor es real**. Nunca se inventa ni se añade a páginas que no sean artículos.
- `og:image` con `og:image:width` / `og:image:height` / `og:image:alt` y `og:image:type` **cuando se conocen**. Si hay varias imágenes, se **agrupan**: cada `og:image` va seguido de sus propias propiedades de dimensiones, texto alternativo y tipo.

### Twitter Cards {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` y `twitter:image:alt` cuando se conoce el texto alternativo.
- `twitter:site` y `twitter:creator` son **opcionales e independientes**. Puede aparecer uno sin el otro y ninguno se inventa a partir del otro.

### Hreflang e idioma {#hreflang-locale}

- Hreflang tiene una vía propia en el resolvedor mediante `getSEOAlternates()` del modelo.
- Las alternativas hreflang, si existen, son **absolutas, normalizadas y únicas por idioma**, con reciprocidad cuando los datos están completos. `x-default` solo aparece si está configurado.
- `og:locale:alternate` refleja **solo** los idiomas con una variante social real. Convierte `en-US` a `en_US` y compara la forma convertida, sin exigir igualdad literal.
- `<html lang>` coincide con el idioma resuelto. La cláusula pertenece al contrato aunque sea la aplicación quien emita `<html>`.

### JSON-LD por página {#per-page-json-ld}

- Se puede analizar y es seguro frente a `</script>`: el contenido usa `JSON_HEX_TAG` para que ningún valor cierre prematuramente el elemento script, como protección contra XSS almacenado.
- Se admiten tanto **varios bloques `<script>` como un `@graph` combinado**.
- Se usa un `@id` estable **donde las entidades se enlazan realmente**, como Organization ↔ WebSite ↔ WebPage. No es obligatorio en nodos independientes.

---

## 2. Normalización e invariantes {#_2-normalization-invariants}

- **URL `http(s)` absolutas** para `canonical`, `og:url`, `og:image` y `twitter:image`. **Ninguna etiqueta vacía o nula** llega al DOM.
- **`canonical` y `og:url` DEBEN resolver a la misma URL normalizada.** Si no coinciden, es un **fallo**, no un aviso.
- La **política de normalización canónica es coherente** en toda la salida: esquema, host, puerto, mayúsculas de ruta, parámetros permitidos y barra final se tratan siempre igual. Las páginas indexables hacen referencia a sí mismas; una página `noindex` no hereda la estrategia canónica de otra.
- **El escape depende del destino**: atributos HTML, texto y JSON usan su codificador correspondiente. Las pruebas comparan **valores semánticos decodificados, no bytes**.
- **La paridad entre renderizadores es semántica**, no byte a byte. `render()` (HTML) ≡ `toArray()` ≡ `toInertiaHead()` después de normalizar. Es válido que difieran el orden y la forma de las etiquetas. Se distinguen explícitamente las propiedades únicas y repetibles: un `og:title`, varios `article:tag`.
- **Propiedad de las etiquetas**: el renderizador del cliente sustituye las etiquetas del paquete, identificadas con claves según §4, sin eliminar etiquetas ajenas de la aplicación.

---

## 3. Comportamiento durante la navegación en el cliente {#_3-behaviour-—-client-side-navigation}

Después de cada visita de Inertia o `wire:navigate` de Livewire:

- Hay **exactamente una etiqueta de cada tipo único** (`<title>`, descripción, canonical y cada `og:*`/`twitter:*`), sin valores obsoletos.
- **JSON-LD no se acumula**: se eliminan los datos de la página anterior. Livewire trata `<script>` como un recurso que no se elimina, por lo que los scripts se marcan con `data-seo-schema` y un identificador por URL; los de la página anterior se eliminan en `livewire:navigated`, como explica la guía de Livewire.
- Pasar de una **página con muchos metadatos a otra sin ellos elimina las etiquetas sobrantes**. La segunda no conserva la descripción, Open Graph ni los datos estructurados de la primera.
- Hay **cero avisos de hidratación** y los metadatos son semánticamente idénticos antes y después de hidratar.

---

## 4. Head-keys de Inertia y propiedad de las etiquetas {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` añade un **`head-key`** estable a cada entrada meta/link. Inertia elimina duplicados usando ese atributo: una etiqueta del `<Head>` de la página con la misma clave que otra del layout la sustituye.

- La clave base es `name ?? property` para meta y `rel` para enlaces.
- **Las etiquetas repetibles se diferencian** para conservar claves únicas: `article:tag` → `article:tag`, `article:tag:1`, etc.; hreflang → `alternate:en-US`, `alternate:fr-FR`.

Vincúlalo en las plantillas como **`:head-key`**, no como `:key` de Vue, que pertenece a la reconciliación de `v-for` y no elimina duplicados del head de Inertia.

---

## 5. Visibilidad para rastreadores: modos explícitos {#_5-crawler-visibility-explicit-modes}

- **SSR / prerenderizado DEBE emitir el contrato completo en el HTML original de la respuesta HTTP**. Se prueba por separado del DOM hidratado, con JavaScript desactivado.
- **Una aplicación solo CSR no puede afirmar que cumple los requisitos para rastreadores.** Inertia sin SSR inserta los metadatos en el cliente; el HTML inicial que recibe el rastreador carece de esos metadatos SEO. Esta limitación se documenta: **los metadatos visibles para rastreadores requieren SSR de Inertia o prerenderizado**, y JSON-LD para rastreadores debe generarse en el servidor.

---

## 6. Fuera de alcance {#_6-out-of-scope-non-goals}

- **Responsabilidades de la aplicación, no del renderizador**: `charset`, `viewport` y favicons. `<meta charset>` debe preceder a los metadatos con caracteres no ASCII, por lo que la aplicación controla ese orden.
- **Las pruebas e2e solo verifican la salida emitida.** No verifican la indexación de Google, su selección del canonical, la elegibilidad para resultados enriquecidos ni el posicionamiento. Tampoco verifican el MIME o la disponibilidad de imágenes remotas. Eso corresponde a pruebas HTTP/de integración opcionales, no a la matriz de navegadores.

---

## 7. Estado de conformidad {#_7-conformance-status}

Qué demuestra cada cláusula actualmente. **Unitaria** = `RenderingContractTest` del núcleo, en el CI del paquete. **Navegador/SSR** = matriz programada de `rankbeam-examples`. **Aplicación** = responsabilidad de la aplicación que aloja el paquete. **Previsto** = objetivo del contrato cuyos datos todavía no están modelados en `SEOData`; el renderizador emite el subconjunto seguro.

| Cláusula | Estado |
|---|---|
| Exactamente un `<title>` resuelto, sin sufijo duplicado | **Unitaria** + navegador |
| Meta description solo si existe | **Unitaria** + navegador |
| Un `<link rel="canonical">`, nunca vacío | **Unitaria** + navegador |
| Robots solo si difiere del valor predeterminado, tal cual; opción `emit_default` | **Unitaria** + navegador |
| Directivas robots avanzadas mediante precedencia | **Unitaria**, resolvedor |
| `og:title/description/type/url/site_name/locale`; idioma `en-US`→`en_US` | **Unitaria** + navegador |
| `article:*` solo si `og:type=article` y el valor es real | **Unitaria** + navegador |
| `og:image` presente y absoluto | **Unitaria** + navegador |
| `og:image:width/height/alt`, `og:image:type` y agrupación de varias imágenes | **Previsto**: `SEOData` contiene una única cadena `ogImage`; todavía no modela dimensiones, texto alternativo ni tipo. El renderizador emite un `og:image` absoluto. |
| `twitter:card/title/description/image`; `site`/`creator` independientes | **Unitaria** + navegador |
| `twitter:image:alt` | **Previsto**: todavía no se modela un campo de texto alternativo de imagen. |
| Hreflang absoluto y único por idioma | **Unitaria** + navegador |
| Reciprocidad hreflang y `x-default` si está configurado | Navegador; depende de los datos |
| `og:locale:alternate` refleja variantes sociales reales | **Previsto**: todavía no se modela un mapa de variantes sociales por idioma. |
| Paridad de `<html lang>` | **Aplicación**; también se comprueba en navegador |
| JSON-LD analizable y seguro frente a `</script>` | **Unitaria** + navegador |
| Varios scripts o `@graph`; `@id` estable donde se enlazan entidades | **Unitaria**, grafo Merchant, + navegador |
| URL absolutas y ausencia de etiquetas vacías/nulas | **Unitaria** + navegador |
| `canonical` ≡ `og:url`; fallo si difieren | **Unitaria** + navegador |
| Normalización canónica coherente, autorreferencia y aislamiento de noindex | Navegador |
| Escape por destino y paridad semántica decodificada | **Unitaria** |
| Paridad semántica entre `render()`, `toArray()` y `toInertiaHead()` | **Unitaria** |
| `head-key` estable y etiquetas repetibles diferenciadas en Inertia | **Unitaria** + navegador |
| Navegación: una etiqueta única, ninguna obsoleta, JSON-LD sin acumular y retirada de sobrantes | Navegador; el renderizador incluye los marcadores `data-seo-schema` necesarios para la limpieza |
| Cero avisos de hidratación y paridad antes/después | Navegador |
| SSR emite el contrato en el HTML original; CSR sin SSR documentado como no conforme | Navegador + documentación |

Las **cláusulas previstas** son carencias deliberadas y documentadas. El contrato define el objetivo mantenido, y esas ampliaciones compatibles se reservan para un trabajo futuro: requieren campos/columnas nuevos en `SEOData` y una versión menor de SemVer. Actualmente, el renderizador emite el subconjunto seguro y nunca inventa valores que no tiene.

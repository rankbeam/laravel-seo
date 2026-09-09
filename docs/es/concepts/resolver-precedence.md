---
description: "El resolvedor SEO combina seis niveles: los superiores tienen prioridad y null nunca reemplaza un valor de un nivel inferior."
---

# Prioridad del resolvedor

Cada valor SEO efectivo —título, descripción, canonical, robots e imágenes— procede de **seis niveles** combinados por `SEOResolver`. Los niveles superiores tienen prioridad. `null` nunca reemplaza un valor de un nivel inferior.

## Los seis niveles {#the-six-layers}

De menor a mayor prioridad:

| Nº | Nivel | Fuente | Uso habitual |
|---|---|---|---|
| 1 | **Configuración del sitio** | `config/seo.php`: `site_name`, `title_suffix`, `default_og_image`, `default_robots`, … | Valores comunes de marca |
| 2 | **Valores globales en la base de datos** | Filas de `seo_defaults` sin tipo de modelo | Cambiar valores del sitio sin desplegar |
| 3 | **Valores por tipo de modelo** | Filas de `seo_defaults` asociadas a una clase | Imagen OG común a todos los productos |
| 4 | **Valores por ruta** | Filas de `seo_defaults` asociadas a un nombre de ruta | Páginas estáticas como `home` o `contact`, sin modelo |
| 5 | **Valores calculados** | Atributos del propio modelo | Título de `title`, descripción de `excerpt` o `body` |
| 6 | **Valores explícitos** | Fila `seo_meta` del modelo, mediante `saveSEO()` | Valores introducidos por el editor |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

El resultado es un objeto de valor inmutable `SEOData`, consumido por Blade, la salida en arrays e Inertia.

## Valores de respaldo calculados, nivel 5 {#computed-fallbacks-layer-5}

Cuando no hay un valor explícito, el resolvedor lo deriva del modelo:

- **Título:** atributo `title` o `name`.
- **Descripción:** primer atributo con texto útil de `seo.computed.description_fields`. El orden predeterminado es `excerpt`, `summary`, `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`, `article`. Se elimina HTML, se decodifican entidades y se corta en un límite de palabra según `seo.computed.description_max_length`, por defecto 160, sin puntos suspensivos.
- **Robots:** hook `getSEORobots()` o atributo `is_indexable`, explicado a continuación.
- **Valores derivados de URL:** canonical y `og:url` desde `getUrlForSEO()`.

## Controlar robots e indexabilidad {#controlling-robots-and-indexability}

El núcleo admite `noindex` por modelo. `HasSEO` no declara un método robots porque es opcional, pero el resolvedor reconoce estas fuentes en orden de prioridad:

| Prioridad | Fuente | Ejemplo |
|---|---|---|
| 1 | **`seo_meta.robots` explícito** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | **Hook `getSEORobots(): ?string`** del modelo | Devolver `'noindex, nofollow'`, o `null` para pasar al siguiente nivel |
| 3 | **Atributo `is_indexable`**, columna o accessor | Falso ⇒ `noindex, nofollow`; verdadero ⇒ `index, follow` |

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

### Qué etiquetas se generan {#what-actually-renders}

La política de emisión filtra la directiva antes de llegar a `<head>`. La etiqueta `<meta name="robots">` se genera **solo si la directiva difiere de `default_robots`**, cuyo valor inicial es `index,follow`:

- Una página indexable con `index, follow` no genera etiqueta robots con esa configuración inicial.
- Una página no indexable genera `<meta name="robots" content="noindex, nofollow">`.
- Las directivas distintas, como `noindex`, `max-snippet:-1` o `unavailable_after`, se emiten literalmente, conservando los espacios.

Activa `seo.robots.emit_default = true` para emitir siempre la etiqueta. Consulta la [política de robots (EN)](/reference/configuration#robots-rendering-policy).

## Políticas posteriores a la resolución {#policies-applied-after-resolution}

Se aplican con independencia del nivel que aportó el valor:

- **Sufijo del título:** se añade `title_suffix`, salvo que el título ya termine con él. Si una plantilla de ruta incluye la marca, termínala con el sufijo para evitar repeticiones como «Brand — X | Brand».
- **Parámetros del canonical:** se eliminan de los canonicals derivados de la URL del modelo o de la petición, salvo los permitidos en [`canonical.query_whitelist` (EN)](/reference/configuration#canonical-urls), como `page`. Los canonicals explícitos se conservan literalmente.
- **Imágenes sociales absolutas:** `og:image` y `twitter:image` se emiten como URL absolutas, aunque el valor guardado sea una ruta relativa.

## Identificar el nivel seleccionado {#inspecting-which-layer-won}

El [paquete Filament](/es/guide/filament) muestra la fuente por campo: manual, contenido, tipo de modelo, valor global, configuración o URL. `SEOWarningEvaluator` expone también la distinción entre valores manuales y de respaldo para crear indicadores propios.

---
description: "Añade una sección SEO a los formularios de recursos Filament 4 o 5 con laravel-seo-filament y el trait HasSEO."
---

# Campos de administración Filament

El paquete gratuito [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) añade una sección SEO con **dos líneas por recurso**. Admite Filament **4.x y 5.x**, con Livewire 3 y 4. Editar metadatos es gratuito; los análisis y el score del ejemplo pertenecen a Pro.

## Requisitos previos {#prerequisites}

Utiliza un panel Filament 4 o 5 existente y un modelo con `HasSEO`. Completa el [inicio rápido del núcleo](/es/guide/quickstart), incluidas migraciones y salida de etiquetas, antes de añadir el editor.

## Instalar {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

El modelo del recurso debe usar `HasSEO`.

## Añadir la sección al recurso {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## Comprobar el valor guardado {#check-the-saved-result}

Abre un registro existente, introduce una descripción SEO, guarda y recarga. La descripción debe conservarse, aparecer en la vista previa y mostrar **Manual** como origen. Comprueba el `<head>` público para confirmar que los visitantes reciben el mismo valor.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="Campos SEO de Merchant: título, descripción, canonical, imagen social, vista previa de búsqueda y origen de los valores." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Ejemplo de Merchant. Los campos adoptan el tema del panel; los controles y presupuestos dependen de la versión y la configuración instaladas.*

La sección incluye:

- **Título y descripción** con contadores. La [política de longitud](/es/guide/multilingual#title-and-description-budgets-per-script) considera la escritura: 60/160 para texto latino, aproximadamente 30/80 para CJK, en grafemas.
- **Palabras clave objetivo:** se introducen como etiquetas y se guardan en `[{keyword, is_primary}]`. La primera es principal; `getPrimaryKeyword()` y `SEOData` leen esta estructura. Activa `seo.keywords.enabled` para que [`seo:audit`](/es/guide/audit) y Pro señalen las páginas sin palabras clave. Está desactivado por defecto; consulta la [configuración (EN)](/reference/configuration#focus-keywords).
- **URL canónica:** vacía para derivarla automáticamente, sin parámetros de consulta.
- **Robots:** vacío para usar el valor del sitio.
- **Imagen social:** subida para `og:image` y `twitter:image`, guardada bajo `seo/` en el disco predeterminado de Filament.
- **Vista previa de búsqueda:** sigue los niveles de respaldo del resolvedor mientras escribes.
- **Indicadores de origen:** manual, contenido, tipo de modelo, valor global, configuración o URL.

## Limitar los campos {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

Admite cualquier subconjunto de `title`, `description`, `focus_keywords`, `canonical`, `robots` y `og_image`. Sin el trait, `SEOFields::make(?array $only)` devuelve directamente la misma sección.

## Cómo se guardan los valores {#how-values-persist}

La sección usa el grupo de estado `seo_meta` y guarda mediante la relación `seoMeta()` del núcleo, actualizando o creando la fila. No añade columnas a tus tablas de contenido. Los valores pasan al nivel 6, explícito, del [resolvedor](/es/concepts/resolver-precedence).

## Varios idiomas {#several-languages}

El núcleo conserva [una fila `seo_meta` por modelo e idioma](/es/guide/multilingual). Desde Filament 1.9 puedes pasar los idiomas publicados para obtener una pestaña por idioma:

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

O definirlos una vez para todos los recursos:

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

Cada pestaña edita su propia fila y tiene:

- contadores según la escritura: un título japonés vacío muestra `0 / 30`, mientras el inglés muestra `0 / 60`;
- vista previa de búsqueda y tarjeta social basada en los valores de ese idioma;
- indicadores propios de respaldo;
- un distintivo con el número de campos definidos, para detectar versiones vacías.

Con `ext-intl`, el nombre del idioma se muestra en la lengua del panel; sin él, aparece el código. Se validan y guardan todas las pestañas juntas. Una lengua sin datos introducidos no crea una fila vacía.

::: details Rutas de estado personalizadas
Con varios idiomas, la ruta es `seo_meta.{locale}.title`. Con uno solo, sigue siendo `seo_meta.title`. Usa la ruta adecuada en tus acciones personalizadas.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Pestañas en inglés, italiano y japonés en Merchant, con presupuestos japoneses de 30 y 80 grafemas y una descripción sin definir." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Merchant, 9 de septiembre de 2026, con `locales: ['en', 'it', 'ja']`. La pestaña japonesa vacía usa sus contadores. El título inglés procede del contenido de respaldo del modelo: añadir una pestaña no traduce el contenido. El score Pro corresponde al último análisis del registro, no a cada pestaña.*

### Con un plugin de traducción {#with-a-translatable-plugin}

Con `lara-zeus/spatie-translatable` **1.x en Filament 4** o **2.x en Filament 5**, utiliza los adaptadores Rankbeam para Edit y Create. Sustituye solo los imports de los traits de página. Conserva los traits de recurso y listado del plugin, su integración en el panel y la acción `LocaleSwitcher`:

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

Cada clase sigue declarando `use Translatable;`. El plugin es una dependencia opcional de la aplicación. Usa su versión corregida más reciente; la fixture local cubre 1.0.4 con Filament 4.13.1 y 2.0.1 con Filament 5.8.1.

Cambiar de idioma conserva los borradores del contenido principal, los metadatos SEO y los datos estructurados. Guardar valida todos los idiomas visitados y los persiste en una transacción. Si hay un error, se abre el idioma afectado. Los archivos se almacenan al guardar; salir o recargar descarta los borradores sin guardar. Guardar no traduce el contenido que falta.

Los adaptadores conservan hooks y mutadores de datos. Si redefiniste `handleRecordCreation()`, `handleRecordUpdate()`, `callHook()` o métodos de transacción, integra el comportamiento del adaptador en tu personalización y prueba el guardado. Las transacciones de base de datos no revierten escrituras de archivos: conserva la limpieza habitual de archivos huérfanos.

Para campos de texto personalizados en Livewire 3, prefiere `->live()` o `->live(onBlur: true)` a un debounce explícito. Este último retrasa el estado local y puede perder las últimas pulsaciones si cambias rápidamente de idioma. Los campos de Rankbeam usan el debounce predeterminado de las peticiones.

Los traits de página originales del plugin rellenan el formulario durante el cambio. Rankbeam protege frente a escrituras accidentales de metadatos, pero esos traits no conservan borradores SEO. Migra Edit/Create a los adaptadores. Las pestañas explícitas `locales:` siguen siendo un editor compartido y tienen prioridad sobre el selector de página.

Sin lista explícita ni idioma de página, se edita el idioma de la aplicación.

## Datos estructurados, schema.org {#structured-data-schema-org}

Una sección opcional permite añadir JSON-LD desde el editor. Colócala junto a la sección SEO:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

También puedes usar `SEOSchemaFields::make()` sin trait. Guarda en `seo_meta.schema_jsonld`, leído por el [generador de esquemas (EN)](/es/guide/schema). La sección solo conecta la interfaz: los builders del núcleo crean los documentos y `SchemaValidator` los valida antes de guardarlos.

Ofrece:

- **Ruta de navegación automática:** un interruptor crea `BreadcrumbList` desde los antecesores del modelo mediante `BreadcrumbSchema::fromModelAncestors()`, sin campos adicionales.
- **Bloques de esquema:** un repetidor para FAQ con preguntas y respuestas, y Product con nombre, descripción, imagen, marca, SKU, precio, moneda y disponibilidad. Utiliza `FAQSchema` y `ProductSchema` del núcleo.

### Validación {#validation}

Un bloque que produciría JSON-LD inválido se rechaza al guardar con el mensaje del validador: por ejemplo, una FAQ sin respuesta o un producto sin imagen u oferta requerida por el builder. Los bloques totalmente vacíos se ignoran.

### Qué se guarda {#what-it-stores}

`schema_jsonld` contiene un objeto si hay un documento, o un array JSON si hay varios: primero la navegación, después los bloques. Ambas formas se emiten sin cambios mediante `@seo` y `renderSchema()`.

### Esquemas que el editor no gestiona {#schema-it-doesn-t-manage}

Los esquemas que el formulario no representa se conservan literalmente: un `@graph` manual, un `@type` especial o un producto con reseñas, valoraciones o GTIN/MPN. Abrir y guardar no los reemplaza.

## Resolución de problemas {#troubleshooting}

- **Falta un valor guardado en la página:** comprueba que `@seo($model)` usa el mismo registro e idioma.
- **Sigue apareciendo un respaldo:** comprueba que el idioma activo tiene un valor explícito guardado. El indicador muestra el nivel utilizado.
- **Falta una pestaña:** comprueba `locales:`, la configuración y el selector de página según la prioridad anterior.

::: details Probar un panel con Testbench
En orchestra/testbench, registra `SupportServiceProvider` de Filament antes de `LivewireServiceProvider`. Filament sustituye el `DataStore` de Livewire; el orden inverso provoca `ViewErrorBag::put(): ... null given`. El descubrimiento automático establece el orden correcto en aplicaciones normales.
:::

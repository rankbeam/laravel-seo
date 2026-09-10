---
description: "Instala Rankbeam, añade HasSEO a un modelo existente, guarda sus metadatos y comprueba las etiquetas generadas con Blade."
---

# Inicio rápido {#quickstart}

Parte de una aplicación Laravel 11, 12 o 13 existente y una base de datos operativa. Necesitas PHP 8.2 o superior, o 8.3 para Laravel 13. El núcleo es gratuito bajo licencia MIT; no requiere cuenta ni licencia Pro.

## Instalar {#install}

Ejecuta estos comandos desde el directorio de la aplicación:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

El proveedor de servicios se descubre automáticamente. La migración crea las tablas SEO, no los modelos de contenido de tu aplicación.

## Antes del ejemplo {#before-the-example}

Los pasos suponen que ya tienes un modelo `Post`, una publicación guardada y una ruta `posts.show` cuya vista Blade recibe esa publicación como `$post`. Adapta los nombres a tu aplicación. Esta guía añade SEO a una página existente, no construye el blog.

Configura `APP_URL` en `.env` con el origen público del sitio. Para otras formas de renderizado, consulta [Inertia y JSON](/es/guide/inertia-json) o [Livewire](/es/guide/livewire).

## 1. Añadir el trait al modelo {#_1-add-the-trait-to-a-model}

```php
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

`getUrlForSEO()` indica al resolvedor la URL canónica del modelo. Se utiliza para canonical, `og:url` y las entradas del sitemap.

## 2. Generar las etiquetas del head {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` genera título, descripción, canonical, robots, Open Graph, Twitter Card y el JSON-LD asociado a los datos resueltos. Si aún no hay valores explícitos, usa los atributos del modelo y la configuración; consulta la [prioridad del resolvedor](/es/concepts/resolver-precedence).

## 3. Guardar valores explícitos {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Los valores explícitos tienen prioridad sobre todos los niveles de respaldo. Para guardar metadatos traducidos, pasa el idioma: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Cargar datos con seeders
El `DatabaseSeeder` predeterminado de Laravel usa `WithoutModelEvents`, que también desactiva el hook de creación automática de `HasSEO`. Retira ese trait o llama a `saveSEO()` de forma explícita en los seeders.
:::

## 4. Comprobar el resultado {#_4-verify-the-result}

Abre la página pública y selecciona **Ver código fuente de la página**. En `<head>`, comprueba que el título contiene `Custom SEO Title`, la descripción es `Custom meta description` y el canonical apunta a la URL pública. El sufijo configurado puede aparecer después del título.

Incluye `@seo($post)` una sola vez por página. Sustituye las etiquetas de título y metadatos que ya genere el layout para evitar duplicados. Si un valor no es el esperado, utiliza la [guía de explicación](/es/guide/explain) para averiguar su origen.

## 5. Añadir un sitemap, si lo necesitas {#_5-add-a-sitemap-optional}

```php
// e.g. in AppServiceProvider::boot()
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

SEO::sitemaps()->register('posts', Post::class);
```

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

`/sitemap.xml` sirve ahora el índice generado. La [guía de sitemaps](/es/guide/sitemaps) describe todas las opciones.

## Siguientes pasos {#where-to-go-next}

- [Prioridad del resolvedor](/es/concepts/resolver-precedence): cómo se eligen los valores.
- [Blade](/es/guide/blade): las siete directivas.
- [Inertia y JSON](/es/guide/inertia-json): salida sin Blade.
- [Grafo de esquemas](/es/guide/schema): JSON-LD conectado.
- [Campos Filament](/es/guide/filament): interfaz de administración.

---
description: "Actualiza de fibonoir/laravel-seo v1 a rankbeam/laravel-seo v2, que renombra el paquete y centra el núcleo en resolución de metadatos, renderizado, JSON-LD y sitemaps."
---

# Actualizar desde fibonoir/laravel-seo v1 {#upgrading-from-fibonoir-laravel-seo-v1}

v2.0.0 cambia el nombre del paquete a `rankbeam/laravel-seo` y concentra el núcleo en resolución de metadatos, renderizado, JSON-LD y sitemaps. El analizador, el escáner, las redirecciones, el monitor de 404 y la interfaz administrativa pasan a paquetes separados.

## 1. Cambiar el paquete {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Actualizar los espacios de nombres {#_2-update-namespaces}

Los nombres de clase no cambian; solo cambia el espacio de nombres raíz: `Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`. Basta con buscar y sustituir en todo el proyecto. El alias de fachada `SEO` y las directivas Blade `@seo` no cambian.

## 3. Eliminar los archivos publicados obsoletos {#_3-delete-stale-published-files}

Antes de eliminar archivos o tablas, guarda una copia de la configuración publicada y exporta los datos afectados. Comprueba que puedes restaurarlos. Esta guía no migra el historial de redirecciones, 404 o scans de v1 al esquema diferente de Pro; la compatibilidad de tablas descrita abajo solo se aplica a `seo_meta` y `seo_defaults`.

::: warning El conflicto puede ser silencioso
El comando `seo:install` de v1 publicaba archivos en la aplicación que pueden entrar en conflicto con v2 sin mostrar ningún mensaje de error.
:::

- **`config/seo.php`**: si lo publicó v1, o `ralphjsmit/laravel-seo` y el instalador de v1 lo dejó, sustituye la configuración del paquete y puede dejar `site_name` y todas las plantillas `{site_name}` sin valor. Elimínalo y vuelve a publicarlo con `php artisan vendor:publish --tag=seo-config`.
- **Migraciones de v1** para tablas que ya no pertenecen al núcleo: `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache`, `seo_internal_links_index`. Elimina los archivos de migración. Si las tablas existen en producción, elimínalas **antes** de instalar `rankbeam/laravel-seo-pro`, que las recrea con un esquema diferente.
- **Archivos base publicados** en `app/` y `resources/js` por los flujos Filament 3 / Livewire / Vue / React de v1: hacen referencia a clases que ya no existen.

Las dos tablas del núcleo, `seo_meta` y `seo_defaults`, tienen un esquema compatible y sus datos se conservan al actualizar.

## 4. Funciones retiradas y su ubicación actual {#_4-removed-features-and-where-they-went}

| Función de v1 | Ubicación actual |
|---|---|
| Sección SEO de formularios Filament | [`rankbeam/laravel-seo-filament`](/es/guide/filament), gratuito y MIT |
| Analizador de contenido de 32 reglas | Esta migración no conserva el analizador anterior. La detección de problemas SEO técnicos está en el escáner de sitios de `rankbeam/laravel-seo-pro`; la puntuación SEO numérica es una función de Pro calculada a partir de problemas. |
| Escáner de todo el sitio | `rankbeam/laravel-seo-pro`: procesamiento en cola y panel |
| Gestor de redirecciones | `rankbeam/laravel-seo-pro`: validación de expresiones regulares y protección contra redirecciones abiertas |
| Monitor de 404 | `rankbeam/laravel-seo-pro`: no guarda IP por defecto |
| Analítica GA4 y enlaces internos | Lista de trabajo pendiente de `rankbeam/laravel-seo-pro` |
| Instalador `seo:install` | Eliminado: instalación con require, publicación de configuración y migraciones |

## 5. Cambios de comportamiento que debes revisar {#_5-behavior-changes-to-review}

- **`og:image` / `twitter:image` siempre son URL absolutas.** v1 emitía tal cual las rutas relativas establecidas manualmente.
- **Los canonical derivados eliminan los parámetros de consulta.** Los canonical explícitos se conservan tal cual.
- **El descubrimiento automático del sitemap da prioridad a las fuentes registradas.** Ya no genera un `sitemap-post.xml` duplicado junto a un `sitemap-posts.xml` registrado.
- **JSON-LD usa escape `JSON_HEX_*`.** Si procesas la salida de scripts, ten en cuenta que caracteres como `<` se representan mediante escapes.

## 6. Particularidades conocidas {#_6-known-gotchas}

- El `DatabaseSeeder` predeterminado de Laravel usa `WithoutModelEvents`, que desactiva la creación automática de `HasSEO` en los seeders.
- Si una plantilla de título predeterminada por ruta ya incluye tu marca, termínala con el `title_suffix` configurado para que el resolvedor no lo añada otra vez.

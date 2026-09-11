---
description: "Instala laravel-seo-pro para añadir scans en cola, seguimiento de problemas, redirecciones y monitor de 404 a Laravel 11–13. Filament es opcional."
---

# Instalar Pro {#installing-pro}

`rankbeam/laravel-seo-pro` añade al núcleo scans de sitios en cola con seguimiento de problemas, un gestor de redirecciones y un monitor de 404. El motor funciona en **cualquier aplicación Laravel 11–13**: Blade, Inertia o API. Filament es una interfaz opcional: si lo instalas, dispones del panel SEO, el gestor de redirecciones y el monitor de 404 como páginas del panel. Sin él, puedes gestionarlo con [comandos artisan](/es/pro/headless).

## Requisitos {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13) |
| Laravel | 11, 12 o 13 |
| `rankbeam/laravel-seo` | ^3.20, instalado automáticamente por Pro 2.40 o superior |
| `filament/filament` | **Opcional**, 4.x o 5.x, solo para la interfaz administrativa |
| `rankbeam/laravel-seo-filament` | **Opcional**, ^1.11 al usar el editor SEO con Pro 2.36 o superior |

Parte de una aplicación Laravel con base de datos configurada. Completa primero el [inicio rápido del núcleo](/es/guide/quickstart) para que un modelo genere metadatos y existan las tablas del núcleo. La licencia Pro proporciona las credenciales Composer indicadas abajo.

Puedes ver el resultado en el [recorrido scan → corrección → informe](/es/pro/walkthrough).

## Instalar el paquete {#install-the-package}

Pro se distribuye mediante un repositorio Composer privado vinculado a la licencia. Añade el repositorio una vez y solicita el paquete. Composer pedirá el correo de la licencia como usuario y la clave de licencia como contraseña:

Lemon Squeezy procesa el pago como merchant of record. Después del pago, la página privada del recibo muestra la clave de descarga y las instrucciones de Composer. Usa el correo de la compra como nombre de usuario. Rankbeam aloja el repositorio; no necesitas una cuenta de Anystack. Mantén privados el enlace del recibo y `auth.json`. Un reembolso completo revoca las descargas y actualizaciones futuras sin interrumpir una aplicación instalada.

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details Autenticación Composer no interactiva
En CI u otros entornos no interactivos, guarda las credenciales previamente:

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

Después ejecuta el instalador:

```bash
php artisan seo-pro:install
```

El instalador publica `config/seo-pro.php` y las migraciones Pro, ejecuta `migrate` y muestra los siguientes pasos. Las tablas del núcleo y Pro deben estar ya en la base de datos de la aplicación.

::: details Instalación manual y opciones del instalador
Las migraciones Pro se publican en la aplicación; el paquete no las carga automáticamente. Los pasos manuales equivalentes son:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Puedes volver a ejecutar el instalador. `--no-migrate` publica sin migrar. Usa `--force` solo si quieres sobrescribir los archivos publicados, incluida tu configuración.
:::

## Registrar objetivos del scan {#register-scan-targets}

Indica qué debe analizar el escáner en un proveedor de servicios: clases de modelo, rutas con nombre o todo el [registro de sitemaps](/es/guide/sitemaps).

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

Sustituye `Post` por tu modelo con `HasSEO`. Necesitas al menos un registro para obtener resultados de modelo. Los objetivos de ruta deben corresponder a rutas existentes; omítelos si solo quieres analizar modelos.

## Verificar la instalación {#verify-your-install}

Ejecuta la comprobación de configuración:

```bash
php artisan seo:doctor
```

Confirma que existen las tablas, que la URL de la aplicación es correcta y que aparecen tus objetivos. Aplica las correcciones indicadas. Es normal recibir un aviso sobre la cola `sync` al probar los comandos en línea siguientes; configura un worker antes de programar scans en producción.

::: details Ejemplo de comprobación de estado
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor` revisa la configuración y el historial reciente sin llamadas de red ni impresión de secretos. No puede demostrar que un cron o worker externo esté funcionando. Los fallos críticos producen un código de salida distinto de cero; los avisos no. Usa `--json` para una salida procesable.
:::

## Ejecutar el primer scan {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

El primer comando completa el scan en el propio proceso, sin necesitar un worker. El segundo muestra la última ejecución y sus resultados. Debe aparecer una ejecución completada con los objetivos registrados procesados; investiga cualquier objetivo fallido antes de dar el scan por terminado.

Corrige un campo señalado, guárdalo y repite el scan. El [recorrido](/es/pro/walkthrough) lo muestra con una descripción ausente y un informe del cambio. La [puntuación técnica](/es/pro/scoring) es un diagnóstico, no una predicción del posicionamiento.

## Uso sin panel {#path-b-headless}

El motor está preparado para funcionar sin panel. Los [comandos artisan](/es/pro/headless) permiten analizar, consultar problemas, crear redirecciones y generar informes. Los middlewares de redirecciones y 404 se registran automáticamente por defecto; se configuran en `config/seo-pro.php`.

Para tareas programadas, sigue [Configuración de producción](/es/pro/production): colas, workers, scheduler y retención.

## Añadir un panel Filament opcional {#path-a-with-a-filament-panel}

En un panel Filament 4 o 5 existente, registra el plugin Pro que aparece abajo. Si aún no tienes panel, instala primero los paquetes de interfaz y créalo:

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

Esto añade el **panel SEO** con acción de scan completo, progreso en directo y lista de problemas con nuevo scan en un clic; el **gestor de redirecciones**; y el **monitor de 404** con la acción *Crear redirección*. `rankbeam/laravel-seo-filament` añade además la [sección de campos SEO](/es/guide/filament) a los formularios de recursos.

## Resolver problemas {#troubleshooting}

| Resultado | Siguiente paso |
|---|---|
| Composer rechaza las credenciales | Comprueba el correo y la clave de licencia para `blog.rankbeam.dev`. No guardes credenciales en el control de versiones. |
| Doctor informa de tablas ausentes | Completa el inicio rápido del núcleo y ejecuta `seo-pro:install` y `migrate` en la misma base que usa la aplicación. |
| El scan no procesa objetivos | Revisa el registro del proveedor y que el modelo contenga filas. |
| El scan en cola permanece pendiente | Arranca el worker configurado o usa `--sync` para una comprobación en el propio proceso. |
| Falla un objetivo | Revisa los detalles de ejecución, nombres de ruta y URL de aplicación antes de repetir. |
| No aparece el panel SEO | Registra `SeoProPlugin` en el panel que utilizas y revisa las reglas de acceso. |

Consulta [Configuración de producción](/es/pro/production) para recuperación de workers y operación continua.

## Licencia y reembolsos {#license}

La licencia de fundador cuesta 179 € en un único pago y cubre hasta cinco proyectos en producción, incluidos proyectos de clientes, con actualizaciones de por vida. Las copias de desarrollo y staging de esos proyectos no cuentan por separado. Incluye ayuda con la instalación y migración, una llamada de instalación de 60 minutos y el kit de lanzamiento anunciado. Puedes pedir un reembolso completo sin condiciones durante los primeros 30 días desde el recibo o escribiendo a valentinogoxhaj@gmail.com. Después del reembolso debes dejar de usar Pro. Puedes modificarlo para los proyectos cubiertos, pero no publicar su código fuente ni revenderlo como paquete independiente o starter kit. El paquete incluye los términos completos de la licencia.

Puedes usar Pro en hasta cinco proyectos en producción, incluidos proyectos de clientes. Las copias de desarrollo, staging y pruebas de esos proyectos no cuentan por separado. Las actualizaciones de por vida incluyen las futuras versiones de Pro, pero no trabajo personal continuo en la aplicación.

Se incluyen una llamada de instalación y configuración de 60 minutos y la migración de metadatos para un solo proyecto inicial. La migración cubre las fuentes compatibles; acordamos el alcance antes de empezar. Los cambios personalizados en la aplicación se presupuestan por separado. Para ese mismo proyecto, revisamos y configuramos llms.txt, las reglas de rastreadores de IA en robots.txt y las respuestas markdown para bots con funciones del Core gratuito. Escribe a hello@rankbeam.dev para organizar la ayuda incluida.

A tu pedido se aplica la oferta mostrada en el momento de la compra.

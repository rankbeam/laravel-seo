---
title: "Qué es Rankbeam: infraestructura SEO para Laravel"
description: "Rankbeam es infraestructura SEO open-core para Laravel: núcleo MIT gratuito para metadatos, canonical, JSON-LD, sitemaps y rastreadores, más un motor comercial Pro y una interfaz Filament opcional."
---

# ¿Qué es Rankbeam? {#what-is-rankbeam}

**Rankbeam es infraestructura SEO open-core para Laravel: un núcleo MIT gratuito para metadatos, URL canónicas, tarjetas sociales, JSON-LD enlazado, sitemaps y control de rastreadores, con funciones comerciales opcionales de monitorización y gestión en Pro.** Resuelve el SEO a partir de tus modelos y configuración, genera los mismos datos tipados mediante Blade, el head de Inertia o una API JSON y, con Pro, sigue comprobándolo después del despliegue.

## La familia de paquetes {#the-package-family}

Rankbeam consta de tres paquetes que comparten una matriz de compatibilidad:

| Paquete | Licencia | Función |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, gratuito** | Núcleo: resolución de metadatos, grafo JSON-LD enlazado, sitemaps XML, control de rastreadores, `seo:audit` gratuito e importadores |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, gratuito** | Campos y vistas previas para Filament 4/5 que escriben en `seo_meta` del núcleo |
| `rankbeam/laravel-seo-pro` | **Comercial** | Motor operativo: scans en cola con puntuación de 0 a 100, redirecciones, monitor de 404 sin IP por defecto, rastreador de enlaces rotos, datos de Search Console y asistencia de IA con tu propia clave |

La separación es deliberada. La generación de la página es MIT y gratuita; la capa de pago es la **auditoría y monitorización de producción**. Pro es un paquete independiente y no se distribuye dentro del núcleo gratuito.

## Para quién está pensado {#who-it-s-for}

Rankbeam resulta útil cuando el SEO se **guarda, se vincula a modelos, usa varios idiomas, funciona sin panel y se audita**: una aplicación Laravel en producción con contenido dinámico o basado en modelos. Para unas pocas páginas estáticas que solo necesitan título y descripción, un generador pequeño de metadatos puede encajar mejor. La [nota sobre cuándo basta un stack combinado](#what-is-honestly-not-in-the-free-core) detalla ese caso.

## Versiones compatibles {#supported-versions}

Una matriz para toda la familia:

- **PHP** 8.2–8.4.
- **Laravel** 11 / 12 / 13; Laravel 13 requiere PHP 8.3 o superior.
- **Filament** 4 / 5, opcional.

## Qué no sustituye Rankbeam {#what-rankbeam-doesn-t-replace}

Rankbeam coordina la salida SEO de tu aplicación Laravel. No es un servicio alojado de seguimiento de posiciones, investigación de palabras clave ni analítica, y no promete posicionamiento, indexación o citas de IA. Para generar sitemaps XML utiliza [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap), y deja el contenido, las rutas y la analítica en tu aplicación.

Puedes [instalar el núcleo gratuito](/es/guide/installation) o seguir leyendo el caso de sustitución real en producción. Pro y la lista de espera de la oferta inicial están en [rankbeam.dev](https://rankbeam.dev/es/).

## Por qué no combinar tres paquetes con código propio {#why-not-three-packages-glue}

Muchas aplicaciones Laravel tienen un stack SEO: un paquete guarda metadatos por modelo, otro añade campos Filament, otro analiza páginas y una capa específica de la aplicación hace que coincidan. Cada pieza puede funcionar bien por separado; el coste está en las conexiones y en mantener ese código propio.

Esta página documenta una sustitución real en producción de ese stack por la familia Rankbeam. Las cifras siguientes corresponden a ese caso medido.

## La aplicación de referencia {#the-reference-app}

Un sitio Laravel real en producción, anonimizado aquí:

- Sitio de **contenido hospitalario e institucional**, con unos tres meses en producción.
- **Migrado desde WordPress**, con unas 900 páginas según el sitemap.
- Unas **20.000 visitas al día**.
- **Laravel 12**, administración **Filament 4**, frontend Blade y MySQL.

Su stack SEO antes del cambio:

| Capa | Paquete |
|---|---|
| Almacenamiento de metadatos en tabla `seo` por modelo | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Campos SEO Filament | `ralphjsmit/laravel-filament-seo` |
| Escáner de páginas | `backstage/laravel-seo-scanner` |
| Integración entre ellos | **Unas 30 clases específicas de la aplicación** |

Se retiraron los tres paquetes, se instalaron **core, Pro y Filament** de Rankbeam y se ejecutó la suite SEO. La aplicación arrancó con **cero regresiones SEO detectadas por esa suite**. A continuación se detalla qué código de integración desapareció.

## Qué eliminó el cambio {#what-the-swap-deleted}

La sustitución eliminó **12 clases propias** porque sus funciones pasaron a la familia de paquetes:

| Clase eliminada | Función anterior | Sustitución |
|---|---|---|
| `Services/SeoService.php` | Punto de entrada SEO de la aplicación | Resolvedor y fachada `SEO` del núcleo |
| `Services/SeoWarningEvaluator.php` | Umbrales de longitud de título/descripción y tamaño de imagen | `SEOWarningEvaluator` del núcleo, compartido por auditoría, vista previa y scan |
| `Services/Seo/SeoAssetInspector.php` | Inspección de dimensiones de imágenes locales | `LocalImageInspector` del núcleo |
| `Jobs/ScanAllPagesSeo.php` | Despacho del scan completo en cola | [Procesamiento de scans](/es/pro/scan-issues) de Pro |
| `Jobs/ScanPageSeo.php` | Scan por página | `PageScanner` de Pro |
| `Jobs/ScanPublicPageSeo.php` | Scan de página pública | Procesamiento de scans de Pro |
| `Models/SeoScanBatch.php` | Registro de ejecuciones | `seo_scan_runs` de Pro |
| `Filament/Pages/SeoDashboard.php` | Panel SEO administrativo | Plugin `SeoDashboard` de Pro |
| `Filament/Widgets/SeoScanProgressWidget.php` | Progreso del scan | Widgets de scan de Pro |
| `Filament/Widgets/SeoTrendChartWidget.php` | Tendencias de scans | Widgets de scan de Pro |
| `Facades/Seo.php` | Fachada propia sobre el paquete de almacenamiento | Fachada `SEO` del núcleo |
| `Console/Commands/RecoverLegacySeoMetadata.php` | Recuperación puntual de metadatos anteriores | [Importadores](/es/guide/migrate-from-wordpress) del núcleo, `seo:import-from` |

::: info Qué ocurrió con el resto
El cambio conservó deliberadamente el rastreador de enlaces rotos de la aplicación: unas 17 clases entre job, comprobador, constructor de URL iniciales, resolvedor de fuentes, dos modelos, dos enums, dos eventos, recurso Filament y tres widgets, y dos comandos. También se conservaron algunos helpers de metadatos y datos estructurados: `CustomSEO`, `EntitySeoSection`, `DynamicSeoDataResolver`, `SitewideSchema` y `SeoKeywords`. En total, unas **22 clases más**. No se eliminaron el primer día porque las sustituciones de Rankbeam llegaron después: el [rastreador de enlaces rotos Pro](/es/pro/production), el destino de **modelo relacionado** y las **vistas previas SERP/sociales** de Filament, y el **grafo de datos estructurados** del núcleo. Al adoptar esas funciones, esa superficie personalizada —unas tres docenas de clases en total— pasa a ser responsabilidad de los paquetes.
:::

El problema no es que esos paquetes sean malos. Es que las clases que los conectan para que un cambio de metadatos aparezca en el escáner, el panel y el head son código específico de tu aplicación, con mantenimiento, pruebas e incidencias a tu cargo.

## Comparación {#side-by-side}

| Capacidad | Stack combinado: tres paquetes y código propio | Familia Rankbeam |
|---|---|---|
| Metadatos por modelo | Paquete de metadatos | **Core**, `seo_meta`, MIT |
| Almacenamiento **por idioma** | Habitualmente código de integración | **Core**, columna de idioma en `seo_meta` |
| Campos SEO Filament | Paquete Filament-SEO | **`laravel-seo-filament`**, MIT |
| Editar el SEO de un modelo **relacionado** | Adaptar el componente de campos | Resolvedor `target:` integrado |
| Vista previa **SERP y social** en directo | Blade/Alpine propio | Vista editorial integrada con pestañas |
| Renderizado Inertia / Livewire / JSON | La referencia usaba Blade; otros stacks requieren integración | **Un resolvedor** para Blade, Inertia, Livewire y JSON, con [pruebas del contrato](/es/contributing/rendering-contract) |
| Escáner y problemas priorizados | Paquete de escáner | **Pro**, [scans](/es/pro/scan-issues) e `IssueRegistry` |
| Puntuación de 0 a 100 | Código propio o ninguna | **Pro**, [reglas transparentes y versionadas](/es/pro/scoring) |
| Redirecciones y recuperación de 404 | Otro paquete o código propio | **Pro**, gestor de redirecciones y monitor sin IP por defecto |
| Rastreador de enlaces rotos | Propio en la aplicación de referencia | **Pro**, rastreo acotado y reanudable |
| **Grafo** JSON-LD | Constructor y enlaces `@id` manuales | **Core**, grafo enlazado Organization/WebSite/WebPage |
| Sitemaps XML | Paquete de sitemaps | **Core**, registro que utiliza `spatie/laravel-sitemap` |
| Importación WordPress / Yoast / Rank Math | Scripts puntuales | **Core**, `seo:import-from` y [procedimiento](/es/guide/wordpress-migration-runbook) |
| **Quién mantiene la integración** | **Tú** | La familia de paquetes, con publicaciones coordinadas |

## Tres dificultades del código de integración {#the-three-things-glue-can-t-do-well}

**1. Una familia coherente con publicaciones coordinadas.** Tres paquetes independientes tienen tres responsables, historiales y ritmos de actualización. El código propio absorbe las diferencias. Rankbeam core, Pro y Filament comparten una [matriz de compatibilidad](#tested-where-it-runs) y [límites de actualización documentados](/es/reference/configuration), para anunciar los cambios de comportamiento antes de que dos paquetes dejen de coincidir.

**2. El idioma como columna de almacenamiento.** `seo_meta` es polimórfico y está delimitado por idioma. El SEO multilingüe usa una fila por `(modelo, idioma)`, sin depender de un bloque serializado o una tabla de integración adicional. La [precedencia del resolvedor](/es/concepts/resolver-precedence) lee el idioma activo de forma nativa.

**3. Renderizado desde un único resolvedor.** Rankbeam resuelve un `SEOData` tipado y genera los mismos datos como HTML, contenido del `Head` de Inertia o array JSON. Un [contrato compartido](/es/contributing/rendering-contract) lo comprueba para [Blade](/es/guide/blade), [Inertia](/es/guide/inertia-json) con Vue/React/Svelte y [Livewire](/es/guide/livewire). No requiere panel administrativo: las funciones Pro también se ejecutan [desde artisan](/es/pro/headless).

## Qué no incluye el núcleo gratuito {#what-is-honestly-not-in-the-free-core}

La separación open-core permite conocer lo que obtienes antes de ejecutar `composer require`:

| Paquete | Licencia | Contenido |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, gratuito** | Resolución de metadatos, grafo JSON-LD, sitemaps, `seo:audit` gratuito e importadores |
| `rankbeam/laravel-seo-filament` | **MIT, gratuito** | Campos y secciones Filament que escriben en `seo_meta` |
| `rankbeam/laravel-seo-pro` | **Comercial** | Scans en cola, problemas priorizados, puntuación de 0 a 100, redirecciones, monitor de 404, rastreador de enlaces rotos, Search Console, IA y panel Filament |

Se paga por la **auditoría SEO técnica y monitorización del sitio**: scans, puntuación, redirecciones, recuperación de 404 y rastreador. El motor de metadatos, grafo, sitemaps y auditoría dentro del proceso son MIT y permanecen gratuitos.

Dos propiedades concretas:

- **Sin comprobación de licencia en tiempo de ejecución.** Pro tiene licencia por proyecto al instalarse. No se comunica con Rankbeam ni tiene un interruptor remoto que pueda detener la aplicación. Emite telemetría operativa **local** para tus registros, que puedes desactivar; no se envía a Rankbeam.
- **IA con tu propia clave.** La [asistencia opcional](/es/pro/ai-assist) usa tu proveedor Anthropic, OpenAI, Google o modelo local. Rankbeam no actúa como intermediario, mide ni revende el consumo. Está desactivada por defecto.

::: tip Cuándo basta el stack combinado
Si solo necesitas un `<title>` y una descripción en unas pocas páginas estáticas, basta un generador de etiquetas. Rankbeam aporta más cuando el SEO está **guardado**, usa **varios idiomas**, se vincula a **modelos**, funciona **sin panel** y se **audita**, y la integración ya supone código real que debes mantener.
:::

## Migrar desde WordPress con verificaciones antes del cambio {#the-lowest-risk-switch-off-wordpress}

La referencia fue una migración WordPress de unas 900 páginas, con años de trabajo en Yoast/Rank Math que conservar. El proceso permite verificar antes de retirar el sistema anterior:

1. **Coexistir.** Prepara Rankbeam junto al sitio activo sin retirar nada todavía.
2. **Importar, primero con dry run.** `seo:import-from yoast` / `rank-math` / `wordpress-csv` lee títulos, descripciones, canonical, robots, palabras clave y valores sociales. Es **idempotente** y **solo rellena campos vacíos por defecto**. Sin `--overwrite` conserva los metadatos existentes; `--dry-run` no escribe.
3. **Trasladar redirecciones.** El núcleo genera un CSV versionado; `seo-pro:redirects-import` valida cada fila, rechazando bucles, destinos inseguros y duplicados antes de escribir.
4. **Verificar antes de eliminar.** `seo:audit --strict` devuelve un código distinto de cero si detecta cualquier problema y sirve como condición de CI o del cambio. La base WordPress permanece intacta hasta que decidas retirarla.

Consulta el [procedimiento de migración](/es/guide/wordpress-migration-runbook) y la [referencia de campos y variables](/es/guide/migrate-from-wordpress). Para cambiar desde un paquete SEO **Laravel** como ralphjsmit, artesaos o Spatie, [también hay un comando](/es/guide/migrate-from-other-packages).

## ¿Cómo se comporta con más tráfico y páginas? {#does-it-hold-up-at-scale}

Las dos exigencias principales de la referencia —resolver SEO en unas 20.000 peticiones diarias y rastrear unas 900 páginas— tienen benchmarks en la suite. Verifican propiedades **deterministas**, como consultas y límites por job, no tiempos ajustados manualmente.

**Caché del resolvedor: un acierto no consulta la base.** Con la caché opcional activa, una resolución cacheada omite toda la cadena. El benchmark ejecuta 25 resoluciones del mismo modelo:

| | Consultas a la base |
|---|---|
| Sin caché; cada resolución relee `seo_meta` | **≥ 25** |
| Acierto con caché caliente | **0** |

La caché está **desactivada por defecto**. Su invalidación elimina las entradas pertinentes al cambiar `seo_meta`, campos de contenido o valores predeterminados. Consulta [Configuración: caché](/es/reference/configuration).

**Rastreador de enlaces: trabajo acotado en 900 páginas.** El benchmark procesa un corpus generado de unas 900 páginas con el job real:

- Termina en **18 o más jobs acotados**, con un máximo de 50 páginas por job.
- **Ningún job** supera ese límite de 50 páginas.
- Comprueba **1.800 enlaces** y cada destino caído produce un hallazgo persistente confirmado.

Hay límites finitos por ejecución y un presupuesto de tiempo por job. La validación SSRF se aplica a la petición inicial y a cada salto de redirección. Un bloqueo temporal en la base permite una sola ejecución activa por ámbito. La [guía de producción](/es/pro/production) explica la operación.

## Probado en los entornos compatibles {#tested-where-it-runs}

Una matriz para toda la familia:

- **PHP** 8.2–8.4.
- **Laravel** 11 / 12 / 13.
- **Filament** 4 / 5.

## ¿Por qué mantener la integración de tres paquetes? {#so-—-why-glue-three-packages-together}

Si combinar paquetes te exige más de una docena de clases propias, adaptarte a ritmos de publicación ajenos, integrar el renderizado por stack y añadir idiomas manualmente, merece la pena evaluar una familia coordinada que asuma ese trabajo. El caso descrito la utiliza en una aplicación real de unas 900 páginas y 20.000 visitas diarias.

Empieza por el [inicio rápido](/es/guide/quickstart): de `composer require` a un `<head>` completo en cinco minutos.

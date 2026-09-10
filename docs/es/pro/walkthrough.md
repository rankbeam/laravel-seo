---
description: "Sigue un scan real de Rankbeam Pro: revisa una descripción ausente, guárdala en Filament, repite el scan y descarga el PDF de ejemplo generado."
---

# De un scan a una corrección verificada {#from-a-scan-to-a-verified-fix}

Un scan encontró una descripción ausente en un artículo de demostración. Añadimos la descripción en Filament, repetimos el scan y generamos un informe que muestra la corrección.

Las capturas proceden de una demo Merchant local en funcionamiento el 9 de septiembre de 2026. El contenido son datos de ejemplo cargados mediante seeders; ambos scans y el informe se generaron para este recorrido, sin tendencias históricas prellenadas. La aplicación usa Laravel 12 y Filament 4 con el núcleo, editor gratuito y motor Pro de Rankbeam. Las capturas y el PDF conservan su contenido original en inglés.

**[Descargar el informe generado: PDF en inglés, 98 KB](/pro-walkthrough/merchant-demo-report.pdf)**

## Analizar las páginas registradas {#scan-the-registered-pages}

Después de [instalar Pro](/es/pro/installation) y registrar objetivos, ejecuta:

```bash
php artisan seo-pro:scan --sync
```

La demo registra 18 contenidos y tres rutas. El primer scan completó los 21 objetivos sin fallos y detectó 20 problemas: seis warnings y 14 notices.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="Captura original en inglés del primer scan: 21 objetivos, 20 problemas, seis warnings y 14 notices." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Las capturas están tomadas a resolución 2×. Ábrelas para verlas a tamaño completo.*

## Revisar un problema {#inspect-one-issue}

En **SEO Dashboard**, abre **Page issues** junto a la fila afectada. Para «Behind the Scenes: Our Product Photography», el hallazgo identifica el campo `description` ausente, la URL y la ejecución que lo detectó.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="El diálogo original en inglés identifica Post 5, su URL y la descripción ausente." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Guardar la descripción {#save-the-description}

Abre el artículo en **Posts**, rellena **SEO description** y guarda. El [editor Filament gratuito](/es/guide/filament) muestra el texto en la vista previa de búsqueda y señala **Manual** como origen. La descripción del ejemplo tiene 142 caracteres; el título sigue procediendo del artículo.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="Descripción SEO guardada y contador de 142 caracteres, en la captura original en inglés." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="La vista previa original utiliza la descripción introducida con la etiqueta Manual." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Guardar un campo y verificar su corrección son pasos separados. La puntuación se actualiza después del siguiente scan. Sin Filament, guarda el mismo valor mediante `saveSEO()` del modelo.

## Repetir el scan y comprobar los cambios {#rescan-and-check-what-changed}

Ejecuta otra vez el mismo comando:

```bash
php artisan seo-pro:scan --sync
```

El panel identifica ahora ese problema concreto como **Fixed**. Los otros 19 permanecen abiertos.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Comparación original en inglés: cero problemas nuevos, cero reaperturas, uno resuelto y 19 abiertos." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Comprobación | Antes | Después |
|---|---|---|
| Objetivos completados | 21 | 21 |
| Problemas abiertos | 20 | 19 |
| Warnings | 6 | 5 |
| Notices | 14 | 14 |
| Puntuación SEO técnica media | 92 | 93 |

La [puntuación](/es/pro/scoring) refleja las comprobaciones técnicas de Rankbeam. No mide tráfico, posición en búsquedas ni presencia en respuestas de IA. Superar la comprobación de descripción tampoco garantiza que un buscador muestre esa descripción.

## Generar el informe {#generate-the-report}

Para esta demostración generamos un informe de referencia **antes** de editar el artículo y otro después del nuevo scan:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

El segundo PDF muestra **un problema resuelto**, **cero nuevos** y **19 abiertos**. La tendencia solo contiene los dos scans anteriores. Search Console y el registro de bots de IA estaban desactivados, así que sus secciones indican que no hay datos disponibles.

[![Primera página del PDF original en inglés: puntuación 93, un problema resuelto y 19 abiertos.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

El primer informe establece la referencia de comparación. Si solo generas uno después de corregir una página, no puede mostrar el cambio respecto a un informe anterior. Usa `--no-store` para una vista previa que no deba avanzar esa referencia.

El ejemplo utiliza el renderizador Browsershot. Consulta los requisitos de renderizado, marca y envío programado en [Informes con marca propia](/es/pro/reports).

## Probarlo en tu aplicación {#run-it-on-your-own-app}

Empieza por [Instalar Pro](/es/pro/installation) y analiza una página cuya salida puedas comprobar. Pro también funciona [sin Filament](/es/pro/headless). Para probar primero el renderizador gratuito de metadatos, utiliza la [demo Docker](/es/guide/demo).

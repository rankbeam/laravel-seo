---
description: "Ejecuta la demo de Rankbeam con un comando: una aplicación con datos de ejemplo y paquetes publicados que genera metadatos, un grafo JSON-LD y un sitemap."
---

# Ejecutar la demo {#run-the-demo}

La demo permite ver Rankbeam en páginas reales sin integrarlo primero en tu aplicación. Es una aplicación Laravel con datos de ejemplo que instala los paquetes **publicados**, sin repositorios path ni checkouts vecinos. Genera varias páginas con metadatos SEO completos, un grafo JSON-LD y un sitemap. Con una licencia también ejecuta la [auditoría SEO técnica](/es/pro/scan-issues) de Pro.

## Un comando para el núcleo gratuito {#one-command-free-core}

La demo se distribuye como imagen Docker en el repositorio [`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples):

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

Abre `http://localhost:8080`. Consulta el código fuente de cualquier página para ver el `<head>` resuelto y visita `/sitemap.xml` para ver el sitemap generado. Todo esto utiliza el núcleo gratuito MIT, instalado desde Packagist.

## Con Pro: la auditoría {#with-pro-the-audit}

Pro tiene licencia por proyecto y se instala desde su repositorio Composer privado. Pasa la licencia mediante `COMPOSER_AUTH`, un secreto de compilación que nunca se escribe en una capa de la imagen, y construye la imagen con la opción de Pro:

```bash
export COMPOSER_AUTH='{"http-basic":{"laravel-seo-pro.composer.sh":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

Al arrancar, la demo ejecuta [`seo:doctor`](/es/pro/headless#setup-health-check) y un primer `seo-pro:scan` sobre las páginas de ejemplo. El informe de estado, el resumen del scan y la [puntuación de 0 a 100](/es/pro/scoring) aparecen en los registros de Compose.

## Ver el flujo de trabajo de Pro {#see-the-pro-workflow}

El [recorrido scan → corrección → informe](/es/pro/walkthrough) muestra una demo Merchant en funcionamiento: un scan real, los detalles de los problemas, una descripción guardada en Filament, un nuevo scan y un PDF descargable. El contenido se identifica como datos de ejemplo, y los resultados de antes y después proceden de dos scans nuevos.

Todavía no hay una demo interactiva pública alojada. Usa Docker para ejecutar el motor localmente. El [README de la demo](https://github.com/rankbeam/rankbeam-examples/tree/main/demo) explica la instalación y cómo alternar entre paquetes publicados y locales.

::: tip ¿Ya tienes una aplicación?
Puedes omitir la demo y pasar al [inicio rápido](/es/guide/quickstart): de la instalación a un `<head>` completo en cinco minutos.
:::

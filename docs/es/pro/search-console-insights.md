---
description: "Cinco informes sobre tus datos de Search Console: rangos de posición, candidatos a revisión de CTR, consultas compartidas por páginas, grupos de consultas y cambios entre períodos."
---

# Análisis de Search Console {#search-console-insights}

Cinco informes calculados a partir de tus propios datos de Search Console: consultas en un rango de posición, candidatos a revisión del CTR, coincidencias consulta/página, grupos de consultas y cambios entre períodos. Tres usan el historial sincronizado; dos comparten una petición en directo cacheada. Cubren esos análisis concretos, no todos los datos y funciones de una plataforma externa de palabras clave.

Se apoyan en la [integración Search Console de solo lectura](/es/pro/search-console) y su sincronización histórica. Si ya ejecutas `seo-pro:gsc-sync`, tres de las cinco vistas funcionan **sin llamadas adicionales a la API**.

::: tip Requisito previo
Las tres vistas de instantáneas leen `seo_gsc_metrics`. Programa primero `seo-pro:gsc-sync`, como explica [Search Console](/es/pro/search-console). Cuantos más días sincronices, mayor será el historial disponible para comparar tendencias.
:::

## Las cinco vistas {#the-five-surfaces}

### 1. Palabras clave en posiciones cercanas {#_1-striking-distance-keywords}

Consultas cuya **posición media ponderada por impresiones está entre 5 y 20**, ordenadas por impresiones. Úsalas para revisar relevancia y enlaces internos. Ese rango no demuestra que un pequeño cambio baste para llegar a la primera página.

### 2. Oportunidades de CTR {#_2-ctr-opportunities}

Consultas con **buena posición y una tasa de clics inferior a la esperada**. Se compara el CTR real con una curva de referencia de CTR por posición. Las consultas con impresiones cuyo CTR queda muy por debajo son **candidatas a revisar título y descripción**, ordenadas por una estimación de clics no obtenidos. Esta lista puede orientar las reescrituras con la [asistencia de IA](/es/pro/ai-assist).

### 3. Canibalización {#_3-cannibalization}

Consultas en las que **aparecen dos o más URL tuyas** para el mismo término. La coincidencia no es necesariamente perjudicial: revisa si las páginas responden a intenciones distintas antes de consolidarlas o diferenciarlas.

### 4. Grupos de consultas {#_4-query-clusters}

Las **consultas para las que aparece cada página**, agrupadas por página. Ayudan a ver si una página se aleja del tema previsto o aparece para un término valioso que no habías considerado.

### 5. Tendencia respecto al período anterior {#_5-trend-vs-previous-period}

Los **mayores cambios** en clics, impresiones, posición y CTR entre la ventana actual y otra de igual duración inmediatamente anterior. La posición solo se compara si la consulta tuvo tráfico en ambos períodos: una consulta nueva o desaparecida no tiene una comparación equivalente.

## Origen de las cifras: datos en directo e instantáneas {#where-the-numbers-come-from-live-vs-snapshot}

Cada vista utiliza la fuente que permite resolverla con menor coste. El historial guarda las dimensiones consulta y página por separado, así que no puede reconstruir sus parejas. Solo las dos vistas que necesitan esa relación acceden en directo y **comparten una petición cacheada**.

| Vista | Fuente | Motivo |
|---|---|---|
| Posiciones cercanas | **Instantánea local** | Posición e impresiones por consulta ya están en el historial; no llama a la API |
| Oportunidades de CTR | **Instantánea local** | Usa los mismos datos; la curva esperada es una referencia estática |
| Cambios de tendencia | **Instantánea local** | Necesita el historial diario guardado por la sincronización |
| Canibalización | **En directo**, consulta × página | Esa relación no se almacena; guardar todas las parejas multiplicaría el almacenamiento |
| Grupos de consultas | **En directo**, comparte la petición de la vista 3 | Los mismos pares, agrupados por página en lugar de consulta |

Una visita a Insights hace **como máximo una petición Search Analytics**, cacheada durante `search_console.cache_ttl` segundos. Las dos vistas de parejas buscan una imagen del período consultado y comparten caché para limitar peticiones repetidas. Renovar el token puede requerir otra petición de autenticación, y siguen aplicándose las cuotas de Google. Las vistas de instantáneas no acceden a la red.

## En el panel {#in-the-dashboard}

Con el plugin Filament, **Search Console Insights** aparece en el grupo SEO solo si la integración está habilitada. Es estrictamente de solo lectura. Cada análisis ocupa una sección; si falta historial, las vistas locales indican que debes sincronizarlo. Un fallo de la consulta en directo muestra un aviso saneado sin bloquear toda la página.

## Configuración {#configuration}

Las opciones están en `search_console.insights`, dentro de `config/seo-pro.php`. Ajusta los umbrales al tamaño de tu sitio.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info Curva de CTR esperado
La curva es una **heurística** compuesta a partir de medias publicadas de CTR orgánico por posición. Es una referencia, no una afirmación sobre tu sitio. Una consulta señalada es un candidato a revisar, no un defecto demostrado. Si tienes una curva propia medida, configúrala en `insights.ctr_curve` como mapa `posición => porcentaje`.
:::

## Véase también {#see-also}

- [Search Console](/es/pro/search-console): integración de solo lectura e historial que alimentan estos análisis.
- [Informes con marca propia](/es/pro/reports): cambios entre períodos en el PDF.
- [Asistencia de IA](/es/pro/ai-assist): reescribir títulos y descripciones de los candidatos de CTR.

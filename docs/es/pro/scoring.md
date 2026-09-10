---
description: "La puntuación SEO de Pro, de 0 a 100: cada deducción corresponde a un problema del scan y el mismo conjunto de problemas produce el mismo resultado."
---

# Puntuación SEO: transparente, versionada y propia de Pro {#the-seo-score-—-transparent-versioned-pro-owned}

El scan de Pro asigna a cada página una **puntuación SEO de 0 a 100**. Es un indicador familiar para quien viene de Rank Math o Yoast, pero **completamente auditable**: cada punto descontado corresponde a un [problema del scan](/es/pro/scan-issues), y el mismo conjunto de problemas produce el mismo número.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip Un número con un único responsable
La puntuación numérica pertenece a **Pro** y se guarda en `seo_scan_results`, nunca en `seo_meta` del núcleo. La antigua columna `seo_score` se eliminó en Core 3. La auditoría gratuita [`seo:audit`](/es/guide/audit) muestra **pass / warn / fail por página sin número**.
:::

## Reglas de puntuación {#the-rubric}

Se calcula con unas **reglas publicadas y versionadas**, `Rankbeam\Seo\Pro\Scanning\ScoreRubric`: una lista explícita de códigos que cuentan y una penalización fija por gravedad.

| Gravedad | Penalización | Significado |
|---|---|---|
| `critical` | **−40** | Hallazgo de impacto alto según estas reglas |
| `warning` | **−15** | Hallazgo que conviene investigar pronto |
| `notice` | **−5** | Mejora secundaria |

La gravedad se lee directamente del [registro de problemas](/es/pro/scan-issues), sin volver a calcularla. Cada código tiene una sola gravedad para mantener el determinismo.

### Qué cuenta {#what-the-score-counts}

Son comprobaciones deterministas seleccionadas por Rankbeam, incluidas heurísticas que requieren interpretación editorial. Critical resta 40, warning 15 y notice 5. El resultado no predice el rendimiento en búsquedas.

| Código | Gravedad | Penalización |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

Las comprobaciones de metadatos se realizan en scans de modelo; las de HTML y red, solo en scans de URL, según las [clases de ejecución](/es/pro/scan-issues#execution-classes). Por eso, un **modelo** puntúa sus metadatos y una **URL** su página renderizada. Un 100 en el modelo significa que no se detectaron defectos de metadatos, no que la página renderizada sea perfecta; analiza también la URL.

### Qué se excluye deliberadamente {#what-the-score-deliberately-does-not-count}

Los siguientes códigos del registro no cuentan. Una prueba verifica que cada código del registro esté puntuado o incluido en esta lista:

| Código | Motivo de exclusión |
|---|---|
| `missing_focus_keyword` | **Orientativo.** Depende del flujo opcional `seo.keywords.enabled`. No usar palabras clave objetivo no debe reducir la puntuación ni hacer que dependa de una opción. |
| `noindex_page` | **Informativo.** `noindex` puede ser deliberado. La heurística noindex con canonical propio se puntúa mediante `noindex_warning`. |
| `multiple_h1` | **Informativo.** Google admite varios H1; no hay penalización por ello. |
| `blocked_url` | **Falta de evidencia.** `SsrfGuard` rechazó la petición y la página no se comprobó; no demuestra un defecto de la página. |
| `canonical_target_blocked` | **Falta de evidencia.** No se pudo verificar el destino canonical. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Orientativos por ahora.** Son códigos hreflang de Pro; la auditoría gratuita tiene sus propios códigos. No alteran la puntuación; incluirlos exigiría cambiar `VERSION`. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **Orientativos.** Las comprobaciones de idioma quedan fuera de estas reglas. |
| `hreflang_not_reciprocal` | **Orientativo.** Comprobación opcional de reciprocidad sin puntuación. |
| `hreflang_target_unverified` | **Falta de evidencia.** No se pudo verificar la reciprocidad. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Orientativos.** Señales de preparación para respuestas: artículo sin autor o fecha. Aparecen en el scan y la auditoría gratuita, pero puntuarlas exigiría cambiar `VERSION`. |

La densidad de palabras clave, las palabras persuasivas y el resto de la [lista de comprobación de página](/es/pro/on-page-checklist) tampoco intervienen. Son comprobaciones orientativas pass/warn/fail separadas, no códigos del registro.

## Versionado: las puntuaciones históricas no cambian silenciosamente {#versioning-—-historical-scores-never-silently-change}

Cada puntuación guarda la `ScoreRubric::VERSION` que la produjo en `rubric_version`:

- Un código **nuevo no puntúa** hasta que se añade expresamente a la lista. Publicar una comprobación no cambia retroactivamente resultados guardados. Cambiar la lista o los pesos exige una nueva versión de las reglas.
- La puntuación **se guarda y no se recalcula al leerla**. El número de la semana pasada se conserva junto con la versión que lo explica.

## Dónde se guarda {#where-it-s-stored}

Cada scan inserta o actualiza una fila por objetivo en `seo_scan_results`:

| Columna | Contenido |
|---|---|
| `scannable_type` / `scannable_id` | Modelo puntuado; null para objetivos URL |
| `url` | URL puntuada |
| `score` | Número de 0 a 100 |
| `rubric_version` | Versión de las reglas |
| `penalty_total` | Suma de penalizaciones antes de aplicar el mínimo de cero |
| `scored_issues` | Número de problemas que alteraron la puntuación |
| `breakdown` | Traza completa: `[{code, severity, penalty}, …]` |
| `keywords_enabled` | Estado de `seo.keywords.enabled` durante el scan; se registra por transparencia y no afecta al número |
| `scan_run_id` | Ejecución que lo calculó; se pone en null al limpiar la ejecución, sin eliminar el resultado, que representa el estado actual |
| `scored_at` | Momento del cálculo |

## Consultar la puntuación {#reading-the-score}

**Sin panel**, último resultado de un modelo:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` muestra la **media del sitio**. El panel Filament la presenta como «Avg. SEO score», con color según la categoría.

### Categorías {#grade-bands}

La letra es una representación visual derivada del número; el contrato es el número:

| Puntuación | Categoría |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Señal que revisar antes de publicar: `noindex_warning` {#the-shipping-signal-noindex-warning}

`noindex_warning` se activa al combinar `noindex` con un **canonical propio**, que apunta a la URL de la misma página. Rankbeam lo considera una señal que conviene revisar. Un canonical propio **no demuestra intención de indexar**: la combinación puede ser deliberada. Un canonical entre dominios no activa esta heurística. El problema incluye `context.shipping_signal`, por ejemplo `self_canonical`, y los valores comparados `canonical` y `page_url`.

Ambos escáneres aplican la comprobación. `PageScanner` compara el canonical guardado con la URL del modelo. `UrlScanner` convierte un `noindex_page` informativo en `noindex_warning` puntuado si el canonical es propio. Por eso `noindex_page` queda excluido: el posible conflicto se trata con `noindex_warning` en ambas vías. Revisa la intención real de la página antes de cambiar robots.

## Configuración {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

La lista de códigos y los pesos **no son configurables**. Una misma `rubric_version` debe ser determinista en todas las instalaciones; cambiar el cálculo exige modificar las reglas en el código, no una opción.

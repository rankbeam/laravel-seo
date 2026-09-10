---
description: "Una segunda puntuación determinista de compatibilidad técnica con rastreadores de IA, definida por Rankbeam y separada de la puntuación SEO."
---

# Puntuación AI-Readiness: un segundo indicador determinista {#the-ai-readiness-score-—-a-second-deterministic-axis}

El scan de Pro añade una **puntuación AI-Readiness de 0 a 100** junto a la [puntuación SEO](/es/pro/scoring). Evalúa señales relacionadas con el acceso, la lectura y la atribución del contenido por rastreadores de IA y motores de respuesta. **Nunca se combina con la puntuación SEO orgánica**. Cada indicador tiene sus propias reglas, versión y columna.

::: warning Qué representa el número
Es una **medida determinista de compatibilidad técnica definida por Rankbeam**: comprueba si ciertas señales de la página existen y están bien formadas. No predice posicionamiento, indexación, inclusión ni citas en sistemas de búsqueda o IA, y ninguna puntuación garantiza esos resultados. `air_llms_txt` concede puntos por un archivo `llms.txt` **opcional** para herramientas que decidan consumirlo. Google Search no lo exige y no es una señal de posicionamiento.
:::

Es **determinista y reproducible**: cada punto corresponde a una comprobación identificada y las mismas señales producen el mismo número. **El cálculo no hace llamadas a IA**. Es una medición auditable, no un muestreo de respuestas de un LLM.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip Dos indicadores separados
`AI-readiness: 74/100` aparece junto a `SEO: 82/100`; ninguno altera al otro. AI-Readiness usa sus columnas `ai_readiness_*` en `seo_scan_results`. El número pertenece a **Pro**; [`seo:audit`](/es/guide/audit) del núcleo gratuito no muestra puntuación numérica.
:::

## Suma de puntos {#additive-credit-not-penalty}

La [puntuación SEO](/es/pro/scoring) empieza en 100 y resta penalizaciones. AI-Readiness empieza en **0** y concede el peso completo o parcial de cada comprobación. Un sitio sin las señales evaluadas obtiene un resultado cercano a cero. Los pesos suman exactamente **100**.

## Reglas {#the-rubric}

`Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric` contiene unas **reglas publicadas y versionadas**, con diez comprobaciones en cuatro categorías:

### A · Acceso y control de bots: 30 puntos {#a-·-bot-access-control-—-30-points}

Se evalúan las reglas del **`/robots.txt` servido** para **la ruta concreta de la página analizada**, usando las categorías entrenamiento / búsqueda / asistente del [catálogo de rastreadores](/es/guide/ai-crawlers). Una página bajo `Disallow: /section` está rechazada aunque la raíz esté abierta. Robots indica una política a rastreadores que la respeten; no bloquea la red.

| Comprobación | Peso | Puntos concedidos |
|---|---|---|
| `air_robots_reachable`: se sirve un `robots.txt` legible | 6 | Presente / ausente |
| `air_ai_search_access`: se permite a rastreadores de **búsqueda IA** acceder a la ruta | 10 | Proporción permitida |
| `air_ai_assistant_access`: se permite a rastreadores de **asistentes IA** acceder | 8 | Proporción permitida |
| `air_explicit_ai_policy`: regla explícita para un bot IA conocido | 6 | Presente / ausente |

::: tip Bloquear rastreadores de entrenamiento no reduce la preparación
Rechazar GPTBot, CCBot u otros rastreadores de entrenamiento es una opción legítima y **nunca se penaliza**. El entrenamiento solo cuenta mediante `air_explicit_ai_policy`, que reconoce una política explícita. Un sitio que bloquea entrenamiento y permite búsqueda y asistentes puede obtener todos los puntos de esta categoría.
:::

### B · Descubrimiento: 20 puntos {#b-·-discoverability-—-20-points}

| Comprobación | Peso | Puntos concedidos |
|---|---|---|
| `air_sitemap_discoverable`: sitemap XML accesible y citado por `Sitemap:` | 12 | Ambas señales / una / ninguna |
| `air_llms_txt`: `/llms.txt` válido con encabezado y enlaces | 8 | Válido / presente / ausente |

### C · Contenido legible por máquinas: 22 puntos {#c-·-machine-readable-content-—-22-points}

| Comprobación | Peso | Puntos concedidos |
|---|---|---|
| `air_server_rendered_content`: texto suficiente en el HTML del servidor, sin ejecutar JS | 14 | Según número de palabras |
| `air_markdown_twin`: representación Markdown de la página mediante negociación de contenido | 8 | Presente / ausente |

### D · Datos estructurados y estructura de respuestas: 28 puntos {#d-·-structured-data-answer-readiness-—-28-points}

| Comprobación | Peso | Puntos concedidos |
|---|---|---|
| `air_schema_completeness`: JSON-LD, entidad principal tipada, autoría y fecha; autor y fecha para artículos | 18 | Completo / parcial / ninguno |
| `air_answer_structure`: FAQ/QA/HowTo, jerarquía de encabezados, listas y una introducción concisa | 10 | Según número de elementos |

Cada comprobación concede puntos completos, parciales o ninguno, o se marca **skipped** si no se pudo obtener la señal necesaria, por ejemplo una comprobación de HTML sobre un objetivo analizado sin descargar la página. Una comprobación omitida aporta cero, pero se identifica: no se presenta una señal no comprobada como ausencia confirmada.

### Alcance de la auditoría gratuita {#free-audit-reach}

`air_schema_completeness` puede resolverse desde los datos estructurados del modelo sin petición, igual que las señales de preparación para respuestas de la auditoría gratuita. Las otras nueve comprobaciones necesitan rastreo, por lo que el número completo corresponde al **scan Pro**.

## Alcance y exclusiones {#honest-scope-—-what-this-axis-excludes}

Este indicador puntúa **señales de contenido deterministas**. Excluye comprobaciones de infraestructura de agentes que dependen de DNS o de una aplicación en funcionamiento:

| Excluido | Motivo |
|---|---|
| **DNS-AID**, registros de descubrimiento de agentes | Infraestructura DNS / DNSSEC, no propiedad de una página servida |
| **Web Bot Auth**, firma por petición | Intercambio criptográfico interactivo, no contenido estático |
| **Protocol Discovery**, API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills, WebMCP, etc. | Requiere aplicación, API o servidor MCP activo |
| **Comercio**, x402, MPP, UCP, ACP | Protocolos de pago para agentes; no son señales de una página de contenido |

Incluye la integridad de entidades de datos estructurados y la organización de bloques de respuesta, sin garantizar que un buscador o motor de respuestas las utilice.

## Versionado de las puntuaciones históricas {#versioning-—-historical-scores-never-silently-change}

Cada resultado conserva `AiReadinessRubric::VERSION` en `ai_readiness_version`. Cambiar comprobaciones, pesos o concesión de puntos **incrementa la versión**, para identificar las reglas que explican cada número. La puntuación **se guarda y no se recalcula al leerla**. Los umbrales que influyen en ella, como recuentos de palabras y elementos, son constantes de código vinculadas a la versión, no opciones que puedan mover silenciosamente un resultado publicado.

::: warning Una entrada que la versión no fija
Las comprobaciones de acceso leen el **catálogo actual de rastreadores** del núcleo. Añadir un bot o cambiar su finalidad modifica las entradas y puede alterar los dos subresultados de acceso sin cambiar `AiReadinessRubric::VERSION`, que versiona las reglas y no el catálogo. Para comparaciones históricas exactas, registra también la versión del paquete core.
:::

## Dónde se guarda {#where-it-s-stored}

Cada scan actualiza las columnas AI-Readiness en la **misma fila** `seo_scan_results` que la puntuación SEO:

| Columna | Contenido |
|---|---|
| `ai_readiness_score` | Número de 0 a 100; null hasta analizar el objetivo con el indicador habilitado |
| `ai_readiness_version` | Versión de las reglas |
| `ai_readiness_breakdown` | Traza completa: `[{code, category, credit, weight, points, status, message, evidence}, …]` |

Al terminar, la media se guarda en `seo_scan_runs.avg_ai_readiness`, en paralelo a `avg_score`, para la tendencia.

## Consultar el resultado {#reading-the-score}

**Sin panel**, el último resultado del modelo contiene ambos indicadores:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament**: añade la columna junto a la puntuación SEO en una tabla de recursos:

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

También aparece como distintivo en la tarjeta de puntuación sobre el campo de título SEO y como sección propia del [informe](/es/pro/reports), en PDF y correo: número, categoría, cambio respecto al informe anterior y tendencia por scan. Siempre se presenta junto al SEO orgánico, sin mezclarse.

### Categorías {#grade-bands}

La letra es una presentación del número, con los mismos intervalos que SEO:

| Puntuación | Categoría |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Configuración {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

Las comprobaciones y pesos **no son configurables**. Una misma `ai_readiness_version` debe ser determinista entre instalaciones; cambiar el cálculo requiere una modificación versionada del código.

::: warning Las señales del sitio se leen mediante peticiones dentro del proceso
Para un objetivo del mismo host, el scan resuelve `/robots.txt`, `/llms.txt` y la página mediante el kernel HTTP de Laravel. Un archivo estático que evita las rutas Laravel no se ve en esa vía. Sirve estos archivos mediante las rutas del paquete, la configuración recomendada, para que se evalúen.
:::

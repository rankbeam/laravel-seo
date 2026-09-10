---
description: "Registro estable de códigos de problemas de Pro: gravedad, campo, clase de ejecución y evidencia para paneles, exportaciones y puntuación."
---

# Problemas del scan: registro de códigos {#scan-issues-—-the-issue-code-registry}

Cada problema de Pro tiene un **código estable** definido en `Rankbeam\Seo\Pro\Scanning\IssueRegistry`. Los escáneres construyen problemas con `IssueRegistry::make()`, que asigna gravedad y campo desde el registro y rechaza códigos no definidos. Este catálogo es un contrato para paneles, exportaciones y [puntuación Pro](/es/pro/scoring), que consumen códigos en lugar de interpretar mensajes. La auditoría gratuita [`seo:audit`](/es/guide/audit) utiliza su propio registro de metadatos del núcleo, con menor cobertura y algunos códigos hreflang diferentes.

Cada código incluye:

- **id**: cadena estable guardada en `seo_scan_issues.issue_type`.
- **severity**: `critical`, `warning` o `notice`, fija por código. Se separan códigos si necesitan distinta gravedad.
- **field**: campo `seo_meta` afectado, o `page` para problemas de página.
- **Clase de ejecución**: qué se necesita para detectarlo.
- **Evidencia**: claves del array `context` del problema.

## Clases de ejecución {#execution-classes}

Cada comprobación pertenece a una clase según lo que necesita:

| Clase | Necesita | Quién la ejecuta |
|---|---|---|
| **metadata** | Modelo y resolvedor del núcleo, sin obtener la página | `PageScanner`; [`seo:audit`](/es/guide/audit) cubre un subconjunto |
| **rendered** | HTML servido, mediante kernel dentro del proceso o petición externa | `UrlScanner` |
| **network** | Petición saliente para validar otro destino, como un canonical diferente | `UrlScanner`, siempre mediante `SsrfGuard` |

La auditoría gratuita dentro del proceso no equivale al scan completo de Pro: solo las comprobaciones metadata se calculan sin renderizar. Pro obtiene HTML y valida destinos canonical mediante red. Filtra el registro con `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`.

## Códigos de metadatos {#metadata-codes}

`PageScanner` los detecta desde el modelo y el resolvedor. El scan de URL también emite `missing_title`, `missing_description` y los códigos de longitud al medir el `<head>` servido, con el mismo significado.

| Código | Gravedad | Campo | Evidencia | Significado |
|---|---|---|---|---|
| `missing_title` | critical | title | —  Sin título ni valor calculable de respaldo. |
| `missing_description` | warning | description | —  Sin meta description ni valor calculable de respaldo. |
| `missing_og_image` | notice | og_image | —  Sin imagen Open Graph ni valor calculable de respaldo. |
| `missing_focus_keyword` | notice | focus_keywords | —  No se definió palabra clave objetivo. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls`  Título reutilizado en otras páginas del mismo idioma. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls`  Descripción reutilizada en otras páginas del mismo idioma. |
| `title_too_long` | warning | title | `length`, `max`, `script`  Título resuelto superior a la recomendación del sistema de escritura: 60 para latino y aproximadamente 30 para CJK. |
| `title_too_short` | notice | title | `length`, `min`, `script`  Título resuelto inferior al umbral: 30 para latino y aproximadamente 15 para CJK. |
| `description_too_long` | warning | description | `length`, `max`, `script`  Descripción resuelta superior a la recomendación: 160 / aproximadamente 80. |
| `description_too_short` | notice | description | `length`, `min`, `script`  Descripción resuelta inferior al umbral: 70 / aproximadamente 35. |
| `robots_conflict_indexing` | critical | robots | `robots`  Robots contiene index y noindex a la vez. |
| `robots_conflict_following` | warning | robots | `robots`  Robots contiene follow y nofollow a la vez. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal`  Página noindex con canonical propio: heurística que revisar, no prueba de que deba indexarse. Se emite en scans de modelo y URL. |
| `invalid_canonical` | critical | canonical | `canonical`  Canonical no es una URL válida. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url`  Canonical apunta a un host diferente al de la página. |
| `shared_canonical` | notice | canonical | `canonical`  Varias páginas declaran el mismo canonical. |
| `insecure_canonical` | warning | canonical | `canonical`  Canonical http:// en un sitio https. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes`  Una alternativa hreflang no es x-default ni un código BCP-47 válido. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url`  Se declaran alternativas pero ninguna representa el idioma de la propia página. |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes`  Un código hreflang corresponde a varias URL, creando un grupo ambiguo. |
| `hreflang_missing_x_default` | notice | alternates | `languages`  Un grupo hreflang multilingüe carece de x-default. |
| `aeo_missing_author` | notice | schema | —  El artículo de los datos estructurados carece de entidad author; no explicita autoría/procedencia. |
| `aeo_article_missing_date` | notice | schema | —  El artículo de los datos estructurados carece de fecha de publicación o modificación; no explicita su cronología. |

Los umbrales proceden de la [política de longitud por sistema de escritura](/es/guide/multilingual#title-and-description-budgets-per-script) del núcleo, utilizada desde Pro 2.33: 60/160 para texto latino y aproximadamente 30/80 para CJK, contados en grafemas, en consonancia con los contadores del editor. Los límites inferiores son 30/70 para latino y aproximadamente la mitad para CJK. `script` indica el grupo aplicado. Se mide el título o descripción **resuelto**, incluidos valores de respaldo y sufijo.

Los códigos `hreflang_*` comprueban las alternativas declaradas por el resolvedor: códigos inválidos o duplicados, autorreferencia ausente y falta de `x-default` en grupos multilingües. Solo se ejecutan si se declaran alternativas. La reciprocidad entre páginas no pertenece a estas comprobaciones de metadatos; la comprobación de red opcional obtiene la otra página.

Los códigos `aeo_*` son señales de **preparación para respuestas**. Leen el grafo JSON-LD y solo se activan si declara un artículo (`Article`, `BlogPosting`, `NewsArticle`, etc.) sin entidad `author` o sin `datePublished` / `dateModified`. No señalan páginas sin artículo. Se controlan con `seo-pro.scan.checks.aeo`, activo por defecto, y coinciden con la auditoría gratuita [`seo:audit`](/es/guide/audit).

::: tip `missing_focus_keyword` requiere activación
Este aviso solo aparece si está habilitado el flujo del núcleo `seo.keywords.enabled`, desactivado por defecto. Con él apagado, no se señala la ausencia de palabra clave. Auditoría, scan y editor Filament leen la misma opción.
:::

## Códigos del HTML renderizado {#rendered-codes}

`UrlScanner` los detecta en el HTML servido. Para el mismo host utiliza una petición al kernel dentro del proceso, sin tráfico saliente; para objetivos externos, una petición protegida.

| Código | Gravedad | Campo | Evidencia | Significado |
|---|---|---|---|---|
| `http_error` | critical | page | `status`  La URL respondió con estado 4xx/5xx. |
| `empty_response` | critical | page | —  La URL devolvió un cuerpo vacío. |
| `missing_canonical` | notice | canonical | —  No hay &lt;link rel="canonical"&gt; en el head renderizado. |
| `noindex_page` | notice | robots | `robots`  Página noindex, informativo. Si también tiene canonical propio, se convierte en noindex_warning puntuado. |
| `missing_h1` | notice | page | —  No hay encabezado &lt;h1&gt;. |
| `multiple_h1` | notice | page | `count`  Hay más de un &lt;h1&gt;, informativo. |
| `missing_image_alt` | warning | page | `count`, `total`, `sample`  Imágenes de contenido sin atributo alt. Un alt="" explícito se considera decorativo y no se señala. |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter`  Texto inferior al número de palabras configurado. Usa el tokenizador de la lista de comprobación: espacios para escrituras que los utilizan, segmentación de diccionario ICU (segmenter: intl, requiere ext-intl) para chino, japonés y tailandés. Un artículo japonés de 400 palabras no se cuenta como una sola. |
| `mixed_content` | warning | page | `count`, `sample`  Subrecursos http:// en una página https. |
| `html_lang_missing` | notice | page | —  Falta &lt;html lang&gt; o está vacío; la tecnología de asistencia puede elegir una voz inadecuada. |
| `html_lang_invalid` | notice | page | `declared`  lang no es una etiqueta BCP-47 válida, por ejemplo english, en_US con guion bajo o jp. |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script`  El texto visible usa un sistema de escritura distinto del idioma declarado: lang="en" en una página japonesa o lang="ru" con texto latino. Solo compara sistemas de escritura, sin adivinar idiomas que comparten alfabeto; requiere al menos 40 letras en el cuerpo. |

## Códigos de red {#network-codes}

`UrlScanner` los detecta cuando está activada la opción correspondiente: `seo-pro.scan.url_checks.check_canonical_target` para canonical y `check_hreflang_reciprocity` para alternativas. Todas las peticiones pasan por **`SsrfGuard`**, con restricciones de esquema/host, rechazo de IP privadas y límites de redirecciones, tiempo y tamaño. No siguen redirecciones, para detectar un canonical que redirige. Se omiten canonical y alternativas que apuntan a la propia página, ya obtenida.

| Código | Gravedad | Campo | Evidencia | Significado |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason`  SsrfGuard rechazó el objetivo antes de realizar HTTP. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status`  Canonical apunta a una página con error HTTP. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location`  Canonical apunta a una redirección; utiliza la URL final. |
| `canonical_target_noindex` | warning | canonical | `canonical`  Canonical apunta a una página noindex. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason`  No se pudo verificar el destino canonical por rechazo de la protección o resolución fallida. |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status`  La alternativa no devuelve una referencia a la página. El par hreflang puede ignorarse; esto no hace por sí solo que la traducción no sea indexable. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason`  No se pudo obtener la alternativa por rechazo, error HTTP, redirección o tamaño excesivo. No se comprobó reciprocidad: falta de evidencia, no defecto confirmado. |

La reciprocidad obtiene como máximo `hreflang_max_alternates`, diez por defecto, incluidos `x-default`, omitiendo duplicados y la propia página. Los códigos metadata validan la lista declarada; esta comprobación necesita la otra página.

Todas las vías de red reutilizan `SsrfGuard`. Consulta el modelo de amenazas y la limitación residual TOCTOU en [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md).

## Cómo afectan los códigos a la puntuación {#how-codes-feed-the-score}

La [puntuación SEO Pro](/es/pro/scoring) parte de 100 y resta una penalización fija por problema puntuado según su gravedad. La mayoría cuentan; se excluyen `missing_focus_keyword`, `noindex_page`, `multiple_h1`, las faltas de evidencia `blocked_url` / `canonical_target_blocked` / `hreflang_target_unverified` y las señales orientativas `hreflang_*`, `html_lang_*` y `aeo_*`. La [referencia de puntuación](/es/pro/scoring) contiene la lista completa y cada penalización.

## Ciclo de vida de los problemas {#issue-lifecycle}

El scan actualiza y concilia los problemas de cada objetivo, en lugar de borrarlos y recrearlos. Su identidad es el objetivo —`scannable_type` + `scannable_id` para modelos, o `url` para rutas/sitemaps— junto a `issue_type`. Cada código se emite como máximo una vez por objetivo y scan. Los códigos con varias instancias, como `missing_image_alt`, `mixed_content` o `hreflang_*`, las agrupan en una fila con `count` / `sample`, manteniendo la identidad única.

En cada scan y objetivo:

- Un hallazgo sin fila previa se crea como `open`, con `detected_at`.
- Uno que coincide con una fila abierta actualiza la evidencia y conserva el `detected_at` original.
- Un problema abierto que una comprobación completada ya no encuentra se marca **`fixed`**, con `resolved_at`. La fila se conserva para registrar la corrección.
- Un problema **`fixed` que reaparece** se reabre en la misma fila y actualiza `detected_at`.
- Uno marcado **`ignored`** por un usuario no se modifica.

| Estado | Significado | Quién lo establece |
|---|---|---|
| `open` | Presente actualmente | El scan, nuevo o persistente |
| `fixed` | Estaba presente y ya no se detecta | El siguiente scan que no lo encuentra |
| `ignored` | Silenciado por un usuario; excluido de totales abiertos y puntuación | Acción Ignore del panel |

Al conservar las correcciones, el [informe](/es/pro/reports) puede mostrar recuentos reales de problemas nuevos y resueltos por período. Panel, [`seo-pro:scan-status`](/es/pro/headless) y [puntuación](/es/pro/scoring) filtran por `open`, de modo que las filas resueltas no inflan los totales. Estas se atribuyen a la ejecución que las resolvió y se eliminan según su [retención](/es/pro/production).

## Configuración {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

El límite de tamaño de las peticiones protegidas es `seo-pro.http.max_response_bytes`, 2 MB por defecto. El scan del mismo host dentro del proceso no tiene ese límite.

## Compatibilidad: cambio de nombres de códigos {#compatibility-note-issue-code-rename}

El antiguo `robots_conflict`, que admitía dos gravedades, se dividió para tener una sola gravedad por código:

| Código anterior | Código nuevo | Gravedad |
|---|---|---|
| `robots_conflict` con index + noindex | `robots_conflict_indexing` | critical |
| `robots_conflict` con follow + nofollow | `robots_conflict_following` | warning |

Si guardabas o filtrabas `robots_conflict`, actualiza a los dos nuevos códigos.

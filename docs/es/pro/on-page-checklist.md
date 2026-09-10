---
description: "Lista de comprobación editorial en tiempo real: palabras clave, metadatos, contenido e imágenes, con indicaciones de legibilidad y densidad separadas de la puntuación SEO."
---

# Lista de comprobación de la página {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

La lista de comprobación analiza el contenido durante la petición, sin colas ni llamadas de red, a partir del modelo, los metadatos resueltos y el texto de la propia página. Su resultado **no es una puntuación numérica**.

::: tip La lista de comprobación y la puntuación son independientes
La lista solo devuelve **correcto / advertencia / fallo** y es independiente de la [puntuación SEO de Pro](/es/pro/scoring). No comparte códigos con sus criterios ni puede modificarla: las indicaciones editoriales quedan separadas de la puntuación. La densidad de palabras clave y la legibilidad, en particular, son **orientativas**; se explican más abajo.
:::

## Qué comprueba {#what-it-checks}

| Comprobación | Grupo | Qué busca |
|---|---|---|
| `keyword_in_title` | keyword | La palabra clave principal aparece en el título SEO. |
| `keyword_in_description` | keyword | La palabra clave principal aparece en la metadescripción. |
| `keyword_in_url` | keyword | La palabra clave principal aparece en el slug de la URL. |
| `keyword_in_first_paragraph` | keyword | La palabra clave principal aparece en el primer párrafo. |
| `keyword_density` | keyword | **Orientativa.** Las repeticiones resultan naturales; no hay un porcentaje objetivo. |
| `title_length` | meta | El título respeta los mismos límites que el editor y el escaneo: 30–60 para texto latino y unos 15–30 para CJK, según la [política de longitud](/es/guide/multilingual#title-and-description-budgets-per-script) del paquete principal (Pro 2.33). |
| `description_length` | meta | La descripción respeta los mismos límites: 70–160 para texto latino y unos 35–80 para CJK. |
| `content_length` | content | Hay suficiente texto en el cuerpo, según los intervalos de palabras configurados. |
| `readability` | content | **Orientativa.** Estima la legibilidad con la fórmula seleccionada para diez idiomas, LIX como alternativa o una heurística identificada y sin puntuación para japonés, chino y coreano. |
| `has_image` | media | El contenido incluye al menos una imagen. |
| `internal_links` | links | El contenido enlaza a páginas internas relacionadas. |

Las comprobaciones de palabras clave **se omiten** cuando no hay una palabra clave principal: no devuelven ni un resultado correcto ni un fallo. La lista te pide que añadas una mediante el [campo de palabra clave principal](/es/guide/filament) o `saveSEO(['focus_keywords' => …])`.

### Coincidencia de palabras clave {#keyword-matching}

La palabra clave y el texto se comparan tras **normalizar mayúsculas y minúsculas y reducir las palabras a sus raíces**. Así, «espresso grinder» coincide con «espresso grinders». Con el idioma de análisis correspondiente, el turco «İstanbul» coincide con «istanbul», el griego «ΟΔΟΣ» con «οδος» y el alemán «Straße» con «STRASSE», mediante `CaseFolder` del paquete principal. Indica el idioma del análisis con `SeoPro::checklistFor($post, 'it')` o `--locale=it`.

Desde Pro 2.36.1, las palabras clave, los sinónimos y los campos de texto utilizan el mismo tokenizador antes de la reducción a raíces. Las coincidencias requieren **tokens completos y consecutivos**: `cat` no coincide con `education`, y las frases japonesas utilizan los mismos límites de palabra de ICU que el cuerpo. Los apóstrofos y guiones separan tokens: `meta-tag` coincide con `meta tag`, y los apóstrofos rectos y curvos se tratan igual. Las marcas combinantes permanecen unidas a sus letras. La normalización de mayúsculas conserva los acentos, aunque el algoritmo de raíces de cada idioma puede aplicar reducciones adicionales.

El recuento elige la palabra clave o el sinónimo coincidente más largo en cada posición y cuenta ese tramo una sola vez. Los sinónimos duplicados y las alternativas más cortas que se solapan no aumentan artificialmente la densidad. Por ejemplo, la palabra clave `seo`, con el sinónimo `seo tools`, aparece dos veces en `seo tools seo`. ICU sigue siendo necesario para reconocer límites de palabra mediante diccionario en escrituras sin espacios; la alternativa con expresiones regulares no proporciona esos límites.

Desde Pro 2.37 se incluye **un subconjunto de Snowball 3.1.1**. No requiere otro paquete de Composer ni descarga nada durante la ejecución. Sigue siendo compatible con PHP 8.2.

| Motor | Cuándo se utiliza | Idiomas |
| --- | --- | --- |
| `snowball` | Predeterminado; los ajustes `auto` existentes seleccionan el mismo motor incluido | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | Configuración explícita `seo-pro.checklist.analysis.stemmer = builtin` | Solo inglés, con el anterior algoritmo ligero de flexiones; los demás idiomas usan coincidencia sin reducción |
| `identity` | Idioma no compatible o modo `none` explícito | Ucraniano, japonés, chino, coreano, tailandés y otros idiomas fuera del subconjunto incluido |

Ambos lados de la comparación utilizan el mismo motor. La reducción de raíces es un algoritmo de reducción de sufijos, no un diccionario de sinónimos ni una garantía de equivalencia lingüística. Por ejemplo, el algoritmo griego puede hacer coincidir formas acentuadas y no acentuadas que el modo `identity` mantiene separadas. Los límites de tokens completos siguen impidiendo que `cat` coincida con `education`.

#### Actualización desde Pro 2.36 {#upgrading-from-pro-2-36}

La configuración `auto` existente utiliza ahora los algoritmos incluidos de forma uniforme, esté instalado o no `wamania/php-stemmer`. Revisa las indicaciones editoriales tras actualizar: los cambios de algoritmo pueden modificar las coincidencias, y el turco, griego, polaco y checo disponen ahora de reducción a raíces. Los algoritmos adicionales de catalán, danés, finés, noruego, rumano y sueco del adaptador opcional quedan fuera de este subconjunto y utilizan ahora `identity`.

Establece `SEO_PRO_CHECKLIST_STEMMER=builtin` para recuperar la anterior alternativa solo para inglés, o `none` para comparar sin reducción a raíces, normalizando mayúsculas en todos los idiomas. Regenera la caché de configuración después del cambio. Estos ajustes no reproducen los algoritmos multilingües del antiguo adaptador opcional; conservar exactamente aquellos resultados requiere mantener la versión anterior de Pro. No se reescriben los metadatos SEO guardados.

El adaptador incluido supera 600.395 pares oficiales de vocabulario y resultado, fijados por versión, en PHP 8.2, 8.3 y 8.4. Esto demuestra conformidad con el algoritmo, no aprobación editorial por hablantes nativos. El paquete incluye los hashes de origen, la adaptación exclusiva de sintaxis para PHP 8.2 y las licencias originales. Consulta `THIRD-PARTY-NOTICES.md` en la distribución del código fuente.

### Segmentación de palabras {#word-segmentation}

El recuento, la densidad de palabras clave y las estadísticas de legibilidad necesitan identificar palabras. Para escrituras con espacios, una expresión regular utiliza límites estables de letras y dígitos. El chino, japonés y tailandés necesitan segmentación mediante diccionario: una expresión regular puede interpretar un párrafo entero como una sola «palabra». Cuando está cargada **ext-intl**, el tokenizador pasa esos fragmentos al iterador de ICU basado en diccionario (`IntlBreakIterator::createWordInstance`), que divide 東京タワーは東京のランドマークです en palabras. Si ICU falta, está desactivado o no puede inicializarse, Pro omite las comprobaciones afectadas de longitud del contenido, legibilidad y palabras clave, e indica qué instalar o configurar. Un recuento poco fiable no se convierte en un fallo. Las comprobaciones independientes, como la longitud del título o las coincidencias en escrituras con espacios, siguen ejecutándose. `seo-pro.checklist.analysis.segmenter = regex` fuerza el mismo estado no disponible para textos que requieren diccionario.

El bloque `analysis` incluye `word_count_status` (`available` o `unavailable`) y `segmentation_reason` (`null`, `missing_intl`, `disabled` o `initialization_failed`). El tokenizador de bajo nivel conserva tokens alternativos por compatibilidad; comprueba este estado antes de interpretarlos como palabras.

El escaneo de la página renderizada emite un aviso `word_segmentation_unavailable` sin puntuación, en lugar de un diagnóstico de contenido escaso. Una incidencia de contenido escaso confirmada anteriormente permanece abierta hasta que se pueda comprobar de nuevo. Este escaneo incompleto no actualiza la puntuación: una existente conserva su `scored_at` original y un primer escaneo queda sin puntuación hasta que funcione la segmentación. Instala PHP `ext-intl`, activa el segmentador `auto` y vuelve a escanear para reanudar estas comprobaciones.

### Qué motores analizaron la página {#which-engines-analysed-the-page}

Cada lista incluye un bloque `analysis` con la escritura predominante del texto, el tokenizador (`intl` / `regex`), el motor de raíces (`snowball` / `builtin` / `identity`) y el método de legibilidad (`formula` / `heuristic` / `lix`). Aparece en `toArray()` / `--json`, al pie del modal de Filament y en la última línea de `seo-pro:checklist`:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

El pie identifica el motor utilizado realmente, incluida la segmentación con expresiones regulares cuando falta ext-intl y la coincidencia `identity` cuando se desactiva la reducción a raíces.

### La densidad de palabras clave es orientativa {#keyword-density-is-advisory}

La lista no establece una densidad ideal para posicionar. Esta comprobación es **orientativa**: muestra el recuento como información, nunca falla y **nunca determina el estado global de la página**. Revisa si las repeticiones resultan naturales, en vez de perseguir un porcentaje.

### La legibilidad es orientativa {#readability-is-advisory}

La lista estima la legibilidad con el método seleccionado para el idioma de análisis. Estas son las fórmulas y alternativas implementadas:

| Idioma | Fórmula | Fuente |
| --- | --- | --- |
| Inglés (`en`) | Flesch Reading Ease | Flesch 1948 |
| Italiano (`it`) | Índice Gulpease | Lucisano y Piemontese 1988 |
| Español (`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| Francés (`fr`) | Kandel-Moles | Kandel y Moles 1958 |
| Alemán (`de`) | erste Wiener Sachtextformel | Bamberger y Vanecek 1984 |
| Portugués (`pt`, `pt_BR`) | Flesch adaptado al portugués brasileño | Martins et al. 1996 |
| Neerlandés (`nl`) | Flesch-Douma | Douma 1960 |
| Ruso (`ru`) | Adaptación de Flesch de Oborneva | Оборнева 2006 |
| Turco (`tr`) | Ateşman | Ateşman 1997 |
| Polaco (`pl`) | Pisarek, índice de años de escolarización normalizado | Pisarek 1969 |
| Japonés, chino, coreano (`ja`, `zh`, `ko`) | **Heurística sin puntuación**; consulta más abajo | — |
| Griego, ucraniano, checo (`el`, `uk`, `cs`) | LIX, como alternativa porque aquí no se implementa una fórmula específica; sin calibración para estos idiomas | Björnsson 1968 |
| Cualquier otro | LIX (Läsbarhetsindex), alternativa sin calibración | Björnsson 1968 |

La **puntuación de 0 a 100, donde un valor mayor indica mayor facilidad**, es una convención del paquete. Los resultados de la familia Flesch y de Gulpease se limitan a ese intervalo; los índices Wiener, Pisarek y LIX se transforman a la misma escala. Una puntuación igual en distintos idiomas **no** implica la misma dificultad de lectura. Las fórmulas y sus coeficientes proceden de trabajos publicados; las estimaciones de tokens, oraciones y sílabas de Rankbeam no se han validado como un instrumento completo. No predicen comprensión ni posiciones en buscadores.

Desde Pro 2.37.1, el recuento de sílabas en turco y ruso trata las vocales adyacentes por separado (`saat`: 2; `поэт`: 2). Los demás estimadores siguen teniendo limitaciones: los grupos de vocales no distinguen algunos hiatos y vocales mudas. El inglés dispone de un pequeño mapa de excepciones, no de un diccionario de pronunciación. Por ejemplo, el español `país` y el francés `monde` pueden contarse mal. Revisa manualmente las palabras poco habituales y los nombres propios.

#### Estadísticas del texto y límites de la API {#text-statistics-and-api-limits}

Las etiquetas HTML de bloque y los elementos `br` separan el texto; el énfasis en línea permanece unido a su palabra. Los saltos del código fuente en HTML normal se convierten en espacios, mientras que el texto plano y `pre` conservan los límites de línea. Se excluye el contenido de script, style y noscript. La extracción no evalúa la visibilidad CSS ni la página renderizada. Las entidades se decodifican una vez. Para las estadísticas de las fórmulas, las secuencias de letras o números cuentan como palabras; la puntuación aislada no. Los guiones y apóstrofos separan palabras. Los dígitos cuentan como tokens, pero no se les atribuyen sílabas. Las letras se cuentan en el texto original, sin que la reducción a raíces o la normalización alemana de `ß` a `ss` alteren su longitud.

La estimación de oraciones divide por los signos finales `. ! ? 。 ！ ？` y los límites de bloque o línea, incluye un fragmento final sin puntuación y protege los decimales y una pequeña lista de abreviaturas habituales (`Dr.`, `Prof.`, `e.g.` y formas similares del inglés). Por eso, los encabezados y elementos de listas pueden contar como oraciones. Otras abreviaturas, citas, números, escrituras mixtas y textos con poca puntuación requieren especial atención. El idioma seleccionado determina el método; no detecta si cada oración está escrita en ese idioma.

El método `toArray()` de la calculadora directa añade un bloque `assessment`:

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` distingue entre `formula`, `lix`, `heuristic` y `unavailable` (`unspecified` para resultados construidos manualmente sin metadatos de fórmula). Una entrada vacía o solo con puntuación es `insufficient` y devuelve `isValid() = false`; su antiguo `score: 0` indica que no está disponible, no una dificultad de lectura. Las etiquetas de nivel escolar existentes para inglés e italiano son aproximadas; los demás idiomas y los resultados heurísticos o LIX ya no reciben esas etiquetas. `calculateFleschKincaid()` conserva su nombre público por compatibilidad, pero calcula **Flesch Reading Ease**, no el nivel escolar Flesch-Kincaid.

Las pruebas de las fórmulas fijan entradas contadas de forma independiente y los resultados aritméticos esperados para las diez fórmulas y LIX. Verifican el cálculo, no la calidad editorial nativa. La legibilidad sigue separada de la puntuación SEO de Pro.

::: warning Japonés, chino y coreano: una heurística identificada, nunca un número
Rankbeam implementa un método sin puntuación para estos idiomas. La calculadora devuelve un **nivel** según reglas aproximadas propias del paquete: longitud media de las oraciones en caracteres (ja ≤ 40/60/80, zh ≤ 30/45/60) o palabras (ko ≤ 12/18/25) y, para japonés, proporción de kanji; por encima de aproximadamente el 45 %, sube un nivel en los intervalos de dificultad del paquete. El resultado lleva `heuristic: true` y **puntuación nula**. El mensaje identifica la heurística y la comprobación sigue siendo **orientativa para estos idiomas, independientemente de `readability.advisory`**: informa, pero nunca determina el estado global. Los recuentos de palabras para `ja`/`zh` requieren segmentación ICU funcional; estas comprobaciones se omiten si no está disponible.
:::

Al igual que la densidad, la legibilidad es **orientativa por defecto**: informa a quien escribe, pero **no** determina el estado global, una separación equivalente a la que Yoast mantiene entre sus análisis de legibilidad y SEO. **Se omite** por debajo del mínimo de palabras; las páginas con poco contenido corresponden a `content_length`. Puedes hacer que influya en el resultado si quieres que una página difícil de leer falle:

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## Consultar la lista de comprobación {#reading-the-checklist}

### Headless {#headless}

Pro 2.36 lee los metadatos resueltos, `getContentForSEO()` y las palabras clave en el idioma de contenido solicitado, mientras las etiquetas de la lista mantienen el idioma del operador. Sin un idioma explícito, respeta el valor predeterminado de `seoData()` del modelo traducible. La acción de Filament sigue la pestaña de idioma del campo o el selector de idioma de la página.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Cada `CheckResult` contiene `id`, `group`, `label`, `status`, `message`, una `recommendation` opcional y el indicador `advisory`.

### Comando {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### En el editor de Filament, opcional {#in-the-editor-filament-optional}

Con [`rankbeam/laravel-seo-filament`](/es/guide/filament) instalado, el campo de palabra clave principal incorpora la acción **On-page checklist**. Al pulsarla se abre un modal con las mismas comprobaciones de correcto, advertencia y fallo sobre el contenido guardado del registro. El paquete de Filament nunca depende de Pro: la acción se conecta mediante el mismo punto de extensión unidireccional que las sugerencias de IA, sin afectar a las instalaciones headless.

## Configuración {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Crear una comprobación personalizada {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Regístrala añadiendo su clase a `seo-pro.checklist.rules`. Una comprobación **no debe** reutilizar un identificador de [incidencia de escaneo](/es/pro/scan-issues): la lista tiene un espacio de nombres separado que no afecta a la puntuación.

## Cómo se lee el contenido {#how-the-content-is-read}

`SeoPro::checklistFor($model)` analiza:

- **Título y descripción:** los valores *resueltos*, es decir, efectivos, que también miden los contadores del editor y el escaneo. Así, la lista no contradice esas mediciones.
- **Contenido:** `$model->getContentForSEO()`, el accesor de `HasSEO` del paquete principal, que utiliza `content` / `body` / `text` por defecto. Sobrescríbelo en el modelo para indicar el cuerpo real.
- **URL:** `$model->getUrlForSEO()`.
- **Palabras clave principales:** el valor guardado en `seo_meta.focus_keywords`.

Es un análisis puro: no descarga páginas ni escribe datos.

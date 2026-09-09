---
description: "Contenido multilingüe en Rankbeam: presupuestos por escritura, grafemas, mayúsculas, hreflang, inLanguage, buscadores regionales, fuentes y URL Unicode."
---

# Contenido multilingüe

Las [traducciones (EN)](/guide/translations) determinan el idioma de la interfaz. Esta página trata de la **lengua del contenido**: presupuestos distintos para japonés, cortes de texto tailandés sin espacios, equivalencia turca entre `İstanbul` e `istanbul`, corrección de `it_IT` en hreflang y rastreadores como Naver para Corea. Estas reglas pertenecen al núcleo para que todos los componentes compartan las mismas decisiones.

Los ajustes están en `config/seo.php`. Algunas funciones requieren ICU para segmentar palabras o fuentes instaladas para representar caracteres. Tu aplicación debe aportar el contenido traducido.

## Idioma del contenido y de la interfaz {#content-locale-and-interface-locale}

Core 3.17, Filament 1.11 y Pro 2.36 transmiten el idioma elegido a metadatos, hooks calculados, URL de vista previa, palabras clave y peticiones de IA. Un panel en inglés puede editar italiano o japonés sin cambiar sus etiquetas.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

Estas lecturas seleccionan la fila del idioma y ejecutan `getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()` y `getSEOSchema()` en un contexto lingüístico temporal. Se conservan los idiomas del modelo llamante y de la aplicación, incluso si un hook lanza una excepción. Los modelos con `setLocale()` y `getTranslatableAttributes()` de Spatie también reciben un idioma de instancia aislado. Tus hooks deben devolver traducciones; Rankbeam no traduce atributos ordinarios automáticamente.

Los métodos de IA basados en modelos y el rellenado masivo de Pro aceptan `locale:`. Sin él, el valor predeterminado de `seoData()` redefinido por un modelo traducible determina la lengua, con respaldo en la aplicación. Las acciones Filament reciben el idioma de su campo, también en edición monolingüe o modo de seguimiento. Serializa el idioma elegido en jobs propios y pásalo al ejecutarlos; no dependas del idioma actual del worker.

Para lectores síncronos propios, `ModelLocale::run($model, $locale, $callback)` pasa un modelo aislado al callback y restaura la lengua de la aplicación en `finally`. Termina todas las lecturas dependientes del idioma dentro del callback. Devolver un iterador diferido o una closure no prolonga el contexto.

## Presupuestos de título y descripción por escritura {#title-and-description-budgets-per-script}

Rankbeam usa presupuestos editoriales de 60/160 grafemas para títulos/descripciones latinos y 30/80 para CJK. Son aproximaciones configurables, no medidas en píxeles ni garantías de visualización completa. Google no fija un límite de caracteres para los [enlaces de título](https://developers.google.com/search/docs/appearance/title-link) ni las [descripciones](https://developers.google.com/search/docs/appearance/snippet); el texto puede cortarse según el ancho del dispositivo.

`Rankbeam\Seo\I18n\LengthPolicy` determina el presupuesto del texto:

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

Las advertencias (`SEOWarningEvaluator`), la auditoría, el recorte de descripciones calculadas, Pro y los contadores Filament leen esta política. Las advertencias evalúan valores resueltos, incluido el sufijo del título; el editor puede mostrar además texto no guardado. Se cuentan **grupos de grafemas**, no bytes ni puntos de código. Sus límites dependen de la implementación Unicode instalada; no cuentan sílabas ni píxeles de resultados de búsqueda.

`seo.length_policy` agrupa las reglas en `latin`, `cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew` y `devanagari`, con `default` para los demás. Una fila puede sustituir algunas claves y heredar el resto:

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Solo `cjk` difiere por defecto. Una instalación actualizada con configuración publicada antigua también recibe la fila CJK integrada.

::: tip Títulos mixtos
La detección pondera letras: un carácter CJK cuenta doble. «Laravel SEO の完全ガイド» se clasifica como CJK; «Laravel SEO for the 東京 developer» sigue siendo latino. Un texto sin letras, como un año o precio, usa la escritura del idioma de la página.
:::

`SEOWarningEvaluator::TITLE_MAX_LENGTH` y `DESCRIPTION_MAX_LENGTH` siguen disponibles como valores latinos iniciales.

## Recorte seguro por grafemas y escritura {#grapheme-safe-script-aware-truncation}

La política ajusta el presupuesto latino `seo.computed.description_max_length`, a la mitad para CJK. Después `Rankbeam\Seo\I18n\Truncator` corta:

- Con espacios entre palabras, en el último límite de palabra dentro del máximo, si alcanza al menos el 60 % del presupuesto. Sin puntos suspensivos y retirando puntuación final; mantiene el comportamiento latino anterior byte a byte.
- Para Han, Kana y tailandés, prefiere la última puntuación de frase o cláusula (。！？、，…) dentro del límite; después un espacio si existe, como en coreano; en último término, el propio límite.
- Siempre entre grupos de grafemas, sin separar marcas combinantes de su base, como vocales tailandesas o modificadores de emoji.

## Mayúsculas y minúsculas según el idioma {#locale-aware-casing}

`mb_strtolower()` no tiene en cuenta la lengua. `Rankbeam\Seo\I18n\CaseFolder` sí:

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` produce la forma para mostrar. `fold()`, `equals()`, `contains()` y `containsWord()` sirven para comparar. El núcleo los utiliza para evitar repetir una marca en el sufijo (`seo.title_suffix_skip_when_contains`), incluidas las formas turcas de i y límites Unicode de palabra. Pro basa en ellos sus comprobaciones de palabras clave.

El plegado de mayúsculas conserva acentos. No hace equivalentes todas las formas acentuadas y no acentuadas. Un stemmer puede aplicar reducciones propias, separadas de `CaseFolder` y de la comparación por identidad.

## hreflang {#hreflang}

Google admite `language[-Script][-REGION]`: lengua de dos letras ISO 639-1, escritura ISO 15924 opcional y región de dos letras ISO 3166-1 opcional, además de `x-default`. Las regiones numéricas como `es-419` son BCP47 válido, pero quedan fuera de los [códigos hreflang de Google](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes).

Laravel suele aportar una locale como `it_IT` o `pt_br`, cuyo guion bajo no es válido aquí. Tres políticas de `seo.hreflang` se aplican a `getSEOAlternates()` antes de generar `<link rel="alternate">`, las entradas `<xhtml:link>` del sitemap, enlaces `llms.txt` y datos de auditoría. `llms.txt` omite la propia página y `x-default` en sus enlaces «Also in».

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`**, activo por defecto, adapta separadores, mayúsculas y alias registrados (`iw_IL` → `he-IL`). Conserva separadores repetidos (`en__US` → `en--US`) para que la auditoría los detecte. Desactívalo para mantener los bytes originales.
- **`include_self`** añade idioma y canonical propios cuando ni su URL ni su código están en la lista. Actívalo si el hook devuelve solo las otras lenguas, para que cada versión también se cite a sí misma.
- **`x_default`** elige la lengua cuya alternativa se duplica como `x-default`, si falta ese marcador.

Una lista vacía sigue vacía: no se añaden referencias propias ni `x-default` a páginas sin traducciones.

La auditoría gratuita comprueba la lista tras aplicar las políticas:

| Código | Gravedad | Significado |
|---|---|---|
| `hreflang_invalid_code` | warning | Código fuera del formato Google: `en-UK`, `jp`, `english`, `es-419`, `fil`. |
| `hreflang_duplicate_code` | notice | Código repetido. |
| `hreflang_missing_self` | warning | La URL de la página no aparece en su lista. |

La reciprocidad requiere rastreo. Con `check_hreflang_reciprocity`, Pro recupera cada alternativa mediante SsrfGuard y emite `hreflang_not_reciprocal` si la página destino no declara la URL de origen **con su código lingüístico** (Pro 2.38+; [códigos de red (EN)](/pro/scan-issues#network-codes)). El helper es público:

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### Tres contratos para códigos de idioma {#three-language-code-contracts}

Core **3.18+** distingue el ajuste de la aplicación de la cadena servida en HTML:

| Entrada | Normalización de aplicación | Idioma HTML | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | Inválido tal cual | Inválido tal cual |
| `de-CH-1901` | Se conserva | Variante registrada válida | Variante no admitida |
| `es-419` | Se conserva | Región numérica válida | Región numérica no admitida |
| `zh-Hant-TW` | Se conserva | Válido | Válido |
| `fil` | Se conserva | Lengua registrada válida | No tiene dos letras |
| `iw_IL` | `he-IL` | Guion bajo inválido; `iw-IL` sigue siendo un tag obsoleto válido | Usar `he-IL` normalizado |
| `en__US` | `en--US` | Inválido | Inválido |
| `x-default` | Se conserva | Rechazado por Rankbeam como idioma de contenido | Marcador de respaldo válido |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Migración desde Core 3.17 y anteriores:** `Hreflang::isValid()` y `parse()` validan estrictamente los códigos servidos. Convierte primero una locale Laravel con `fromLocale()`. Para un atributo HTML `lang`, usa `LanguageTag::isValidHtml()` sin recortar ni normalizar. Los tags obsoletos registrados siguen siendo válidos en HTML. Solo se normalizan alias preferidos explícitos de IANA; no se supone que `en-UK` significa `en-GB`. Las entradas malformadas permanecen visibles para la auditoría.

El validador incluye datos del registro IANA del **2026-08-08**, con hashes y generador reproducible. Comprueba estructura RFC 5646, subtags registrados, prefijos extlang y variantes/extensiones duplicadas. Admite tags antiguos conservados y rangos privados. Las recomendaciones de prefijos de variante no son reglas obligatorias. Se validan espacios de nombres y estructura de extensiones, no semántica CLDR ni significado de usos privados. No requiere ICU ni descargas en ejecución. Consulta [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) y la [definición HTML de lang](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes).

Pro **2.38+** trata `lang` ausente o vacío como desconocido/faltante; los bytes malformados producen `html_lang_invalid`. La comparación de escritura usa un subtag Script real o el predeterminado registrado por IANA. Las extensiones, datos privados o lenguas desconocidas no implican escritura latina. Los grupos no soportados quedan sin evaluar. No es un detector completo de lengua.

La reciprocidad usa códigos válidos de autorreferencia de la fuente o, si faltan, su idioma HTML válido compatible con Google. Un enlace de vuelta bajo otro código no basta. Si no se identifica el código de origen, queda `hreflang_target_unverified`. Las URL destino repetidas se recuperan una sola vez dentro de los límites existentes; siguen activos los controles SSRF, rechazo de redirecciones y manejo de errores no verificables.

## `inLanguage` en el grafo de esquemas {#inlanguage-in-the-schema-graph}

`WebPage` recibe `inLanguage` del idioma resuelto (`it_IT` → `it-IT`); `ArticleSchema::fromModel()` lo toma de `seo_meta`. `WebSite` lee sus idiomas de la configuración:

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Buscadores regionales {#regional-search-engines}

El catálogo de `seo:robots-txt` incluye Yandex, Baidu, Naver (`Yeti`), Seznam, Sogou, 360, Cốc Cốc y DuckDuckGo. Su finalidad es `search_engine`, se permiten por defecto y siguen políticas y excepciones individuales:

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` y `match()` siguen limitados a IA. Pide los buscadores con `searchEngines()`, `all(true)` o `match($ua, true)` para conservar el significado del registro y los contadores de IA. Consulta [rastreadores](/es/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
El soporte del rastreador y de su etiqueta de verificación no garantiza descubrimiento, indexación ni posiciones en Baidu.
:::

## Verificación del sitio {#site-verification}

Los tokens de propiedad generan etiquetas meta en todas las páginas, incluida la raíz donde Yandex, Baidu y Naver buscan el comprobante. Una clave vacía no genera salida:

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

El valor puede ser una lista de tokens, por ejemplo para varios propietarios de una propiedad de Google.

## Imágenes OG por escritura {#og-images-in-every-script}

La fuente incluida cubre latín, cirílico y griego. Las otras escrituras dependen de fuentes instaladas en el host que ejecuta `seo:og-images`; una fuente CJK puede superar 16 MB. Las plantillas usan `seo.og_image.font_stack`, colocando delante la familia Noto CJK del idioma para elegir las formas nacionales de caracteres Han. El comando avisa una vez por escritura cuando no detecta una fuente apropiada:

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

En Debian/Ubuntu: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. Consulta [imágenes OG](/es/guide/og-image#fonts-and-non-latin-scripts).

## `llms.txt` en varios idiomas {#llms-txt-in-several-languages}

Con `seo.llms_txt.alternates`, las páginas traducidas añaden `Also in: [it](…), [de](…)` al final de su entrada. La lista respeta las políticas, sin `x-default` ni la propia página. Desactivado por defecto.

## URL Unicode {#unicode-urls}

Rankbeam no genera slugs ni reescribe URL: `/città/` o `/検索` se conservan. La auditoría acepta hosts IDN (`https://münchen.example/`) y rutas Unicode o codificadas en porcentaje. Para ello, `Rankbeam\Seo\I18n\Url::isValid()` sustituye el `FILTER_VALIDATE_URL` de PHP limitado a ASCII. Usa una forma uniforme por URL para que canonical, hreflang y sitemap coincidan byte a byte.

## Idiomas admitidos y alcance {#which-languages-are-supported-and-what-that-means}

Los paquetes incluyen textos y selección de motores para las 17 locales siguientes. La tabla describe cobertura técnica, no aprobación editorial nativa ni representación garantizada en un servidor sin configurar. Las comprobaciones de palabras en japonés y chino requieren ICU operativo; si falta, se omiten las evaluaciones afectadas. El renderizado no latino exige fuentes adecuadas.

`tests/Feature/I18n/SupportedLanguagesTest.php` fija idiomas, hreflang y presupuestos en el núcleo. `tests/Feature/OnPage/LanguageSupportMatrixTest.php` de Pro fija los motores de análisis.

| Idioma | Locale | Título / descripción | Recuento de palabras | Coincidencia de palabras clave | Legibilidad |
|---|---|---|---|---|---|
| Inglés | `en` | 60 / 160 | Espacios | Snowball | Flesch Reading Ease |
| Italiano | `it` | 60 / 160 | Espacios | Snowball | Gulpease |
| Alemán | `de` | 60 / 160 | Espacios | Snowball | Wiener Sachtextformel |
| Francés | `fr` | 60 / 160 | Espacios | Snowball | Kandel-Moles |
| Español | `es` | 60 / 160 | Espacios | Snowball | Fernández-Huerta |
| Portugués de Brasil | `pt_BR` | 60 / 160 | Espacios | Snowball | Martins |
| Neerlandés | `nl` | 60 / 160 | Espacios | Snowball | Flesch-Douma |
| Turco | `tr` | 60 / 160 | Espacios | Snowball | Ateşman |
| Ruso | `ru` | 60 / 160 | Espacios | Snowball | Oborneva |
| Polaco | `pl` | 60 / 160 | Espacios | Snowball | Pisarek |
| Japonés | `ja` | 30 / 80 | Diccionario ICU | Exacta con plegado de mayúsculas | Heurística, **sin score** |
| Chino simplificado | `zh_CN` | 30 / 80 | Diccionario ICU | Exacta con plegado de mayúsculas | Heurística, **sin score** |
| Chino tradicional | `zh_TW` | 30 / 80 | Diccionario ICU | Exacta con plegado de mayúsculas | Heurística, **sin score** |
| Coreano | `ko` | 30 / 80 | Espacios | Exacta con plegado de mayúsculas | Heurística, **sin score** |
| Griego | `el` | 60 / 160 | Espacios | Snowball | LIX |
| Ucraniano | `uk` | 60 / 160 | Espacios | Exacta con plegado de mayúsculas | LIX |
| Checo | `cs` | 60 / 160 | Espacios | Snowball | LIX |

Tres límites acompañan esta cobertura:

- **Snowball se incluye desde Pro 2.37.** Doce lenguas usan los algoritmos fijados en 3.1.1 sin paquetes opcionales. Ucraniano y CJK usan identidad, sin reglas de sufijos inventadas. La identidad puede no reconocer flexiones; stemming puede confundir palabras distintas. Consulta [motores y migración (EN)](/pro/on-page-checklist#upgrading-from-pro-2-36).
- **«Heurística, sin score» no equivale a LIX.** Japonés, chino y coreano reciben un nivel orientativo según longitud de frases y proporción de kanji, con score `null`. Griego, ucraniano y checo usan LIX porque no hay fórmula específica implementada. LIX no necesita sílabas, pero sus umbrales no están calibrados para cada lengua. Todas las fórmulas usan entradas estimadas; consulta [límites estadísticos (EN)](/pro/on-page-checklist#text-statistics-and-api-limits).
- **Las traducciones de paquetes son primeras versiones**, salvo revisión nativa registrada en `TRANSLATING.md`. Los textos italianos tienen revisión acreditada; los demás esperan revisor. Esto no aprueba las traducciones de documentación.

Las locales no listadas pueden recurrir a textos ingleses, presupuestos predeterminados, coincidencia por identidad y LIX o heurísticas. Ese respaldo no es soporte lingüístico validado. El bloque `analysis` identifica escritura, segmentador, stemmer y legibilidad; examina también disponibilidad y evaluaciones omitidas.

### Llegar a los buscadores relevantes localmente {#reaching-the-search-engines-that-matter-locally}

El soporte técnico incluye bots y verificación: Naver para Corea, Seznam para Chequia o Yandex para sitios ucranianos o rusos, entre otros. Consulta [buscadores regionales](#regional-search-engines) y [verificación](#site-verification).

## Qué añaden los otros paquetes {#what-the-other-packages-add}

- **laravel-seo-filament** usa la misma política en contadores y vista previa SERP. Desde 1.9 edita [una fila `seo_meta` por idioma](/es/guide/filament#several-languages), en pestañas con contadores, vista previa e indicadores propios, o siguiendo el selector de un plugin.
- **laravel-seo-pro** la usa en `title_length`, `description_length` y prompts de IA. Su análisis incluye segmentación ICU de chino, japonés y tailandés, Snowball, coincidencia con `CaseFolder`, fórmulas publicadas con entradas estimadas para diez lenguas, heurísticas CJK etiquetadas, LIX para griego/ucraniano/checo, palabras vacías para 16 lenguas, `html lang`, reciprocidad hreflang, prompts con idioma y reportes Chrome para escrituras que dompdf no representa. Consulta [checklist (EN)](/pro/on-page-checklist#keyword-matching), [problemas (EN)](/pro/scan-issues), [asistencia IA (EN)](/pro/ai-assist#output-language) y [reportes (EN)](/pro/reports#reports-in-every-script-browsershot-renderer).

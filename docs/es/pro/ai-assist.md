---
description: "Asistencia opcional de IA con tu propia clave: sugerencias de títulos y metadescripciones, explicación de incidencias, reescrituras y datos estructurados. Desactivada por defecto."
---

# Asistencia de IA {#ai-assist}

Asistencia opcional con **tu propia clave de API**: sugerencias de títulos y metadescripciones, explicaciones de incidencias de escaneo en lenguaje sencillo, **reescritura de descripciones** y **sugerencias de datos estructurados de schema.org**. Está **desactivada por defecto**: con el indicador desactivado no se ejecuta ninguna ruta de código de IA.

El diseño sigue tres criterios:

- **Tu clave y tu proveedor.** Las peticiones van de *tu servidor* directamente al proveedor que configures: Anthropic, OpenAI, Google o un servidor local o compatible con OpenAI. Cuando corresponda, se facturan a tu cuenta. El paquete no actúa como intermediario, no mide ni revende el consumo y no envía telemetría.
- **Las sugerencias interactivas requieren aceptación explícita.** El modelo propone y tú eliges. Una sugerencia o corrección solo se aplica mediante una acción explícita: rellena un campo o guarda un valor revisado al pulsar Aplicar. Se somete a las mismas validaciones que un valor escrito a mano: contadores según la escritura, advertencias del evaluador y validador de esquemas. El comando de relleno masivo descrito más abajo es una operación de escritura que invocas expresamente; no requiere revisar cada campo generado antes de guardarlo.
- **Los errores no bloquean el trabajo.** Una clave ausente o incorrecta, una cuenta sin saldo, un límite de peticiones o un tiempo de espera agotado generan un mensaje en la interfaz. Nunca impiden guardar, renderizar o escanear.

## Proveedores disponibles {#providers-at-a-glance}

Elige según las cuentas a las que tengas acceso, los requisitos de datos y el coste. Las cuatro integraciones ofrecen las mismas tareas, aunque la compatibilidad del modelo, el formato de salida, la velocidad y la calidad pueden variar.

| Proveedor | Modelo predeterminado del paquete | Salida estructurada | Coste ilustrativo | Uso |
|---|---|---|---|---|
| **Local** (Ollama / LM Studio / vLLM) | `llama3.1`, configurable | Se solicita `response_format`, sin garantía de que el servidor lo respete | **0 USD de tarifa de API** para inferencia propia; la infraestructura tiene costes | Control del destino de los datos |
| **OpenAI** | `gpt-5.5` | Structured Outputs si el modelo lo admite | Unos 0,005 USD por sugerencia con los supuestos del ejemplo inferior | Una cuenta existente de OpenAI |
| **Anthropic** | `claude-opus-4-8` | `output_config.format` si es compatible | Unos 0,015 USD por sugerencia con esos supuestos | Una cuenta existente de Anthropic |
| **Google** | `gemini-2.5-flash` | `responseSchema` si es compatible | Unos 0,0005 USD por sugerencia con esos supuestos | Una cuenta de Google; comprueba cuotas y precios del modelo |

Estos nombres describen la configuración distribuida, no garantizan la disponibilidad actual en tu cuenta. Los costes usan los supuestos de ejemplo del paquete, no precios actuales verificados. Comportamiento de las integraciones y observaciones de las pruebas publicadas:

- **Salida estructurada.** Las rutas compatibles de OpenAI, Google y Anthropic reciben un esquema JSON; una respuesta inválida produce un fallo controlado. Los servidores locales reciben una solicitud `response_format` que pueden ignorar. En ese caso, el análisis tolerante devuelve una lista válida o un fallo, sin aplicar resultados parciales.
- **El razonamiento modifica el consumo de tokens.** La prueba descrita de Gemini utilizó unos 500 tokens de razonamiento oculto y unos 100 visibles para una descripción; las llamadas probadas de Anthropic no declararon tokens de razonamiento oculto. No es una propiedad universal de esas familias de modelos. Los tokens ocultos pueden facturarse como salida; por eso existe el mínimo de razonamiento descrito más abajo.
- **Los modelos son configurables.** Establece `SEO_PRO_AI_MODEL` con un modelo disponible y compatible con la API y los parámetros del adaptador. Los ejemplos incluyen `claude-haiku-4-5`, `gpt-5.4-mini` y `gemma-3-12b-it`; verifica la compatibilidad y la calidad antes de utilizarlo en toda una colección.

## Configuración inicial {#setup}

Activa la función y añade la clave del proveedor al entorno. Para cambiar de proveedor en la nube, actualiza el proveedor y la clave y comprueba también cualquier modelo configurado explícitamente. El adaptador local necesita además la URL del servidor.

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip La clave de API es independiente de la suscripción a Claude o ChatGPT
Una **suscripción** a Claude Code, Claude.ai o ChatGPT no paga el consumo de la **API**. `SEO_PRO_AI_API_KEY` debe contener una clave de API de pago por uso de la consola del proveedor, o una clave de Google AI Studio, con su propio saldo de crédito. Una cuenta solo con suscripción o sin fondos puede autenticarse y devolver un error de **crédito o cuota agotados**. Consulta [Solución de problemas](#troubleshooting).
:::

El bloque `ai` de `config/seo-pro.php` expone `timeout`, `max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models` y `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model` para el relleno masivo económico —consulta [Coste](#cheaper-bulk-generation)—, `retry`, la tabla `pricing` y el bloque `local`. Se explican en [Límites y ajustes](#limits-and-tuning).

::: warning Gestión de claves con configuración en caché
La configuración guarda únicamente el **nombre** de la variable de entorno (`api_key_env`), nunca la clave. Por tanto, `php artisan config:cache` no escribe la clave en `bootstrap/cache/config.php`. Como `.env` no se carga al usar configuración en caché, establece `SEO_PRO_AI_API_KEY` como una variable de entorno real del servidor.
:::

## Inferencia local y opciones en la nube {#running-at-0-and-the-cheapest-paid-option}

- **La inferencia en tu infraestructura evita la tarifa de API por token del proveedor.** El hardware, la electricidad y la operación siguen teniendo costes. El contenido solo permanece en tu red si el servidor de inferencia configurado y sus dependencias también permanecen en ella.
- **Google tiene cuotas y precios específicos por modelo y nivel de servicio.** Una clave de AI Studio (`aistudio.google.com/apikey`, formato `AIza…`) puede permitir pruebas en un nivel gratuito; comprueba si sus límites cubren tu carga antes de activar la facturación cuando sea necesario. `gemini-2.5-flash` es el modelo predeterminado. Los modelos Gemini y Gemma no tienen todos las mismas capacidades de razonamiento: `reasoning_models` aplica patrones de nombre configurados, no una prueba de capacidades.

Para controlar dónde se ejecuta la inferencia:

- **Local o compatible con OpenAI.** Usa `provider=local` con un servidor compatible con OpenAI Chat Completions, como **Ollama**, **LM Studio**, **vLLM** o **LocalAI**, o una pasarela remota como **OpenRouter**. Configura `SEO_PRO_AI_LOCAL_BASE_URL` con la raíz de su API; se añade `/chat/completions`. Elige un `SEO_PRO_AI_MODEL` disponible. Una pasarela remota recibe los datos fuera de tu red y puede cobrarte: el nombre `local` del adaptador no implica inferencia local.

::: warning La URL local se valida; localhost requiere autorización explícita
`base_url` es un ajuste privilegiado validado con el mismo `SsrfGuard` de las demás peticiones salientes: solo http/https, sin credenciales en la URL y, por defecto, debe resolver a una dirección **pública**. Así, una URL errónea o maliciosa no permite sondear servicios internos. Un servidor en `127.0.0.1` utiliza una dirección privada y necesita activar explícitamente `seo-pro.ai.local.allow_local_addresses` (`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`). Mantén este ajuste desactivado para una pasarela pública como OpenRouter. La ruta de la petición es fija y no se siguen redirecciones, de modo que la clave no se redirige a otro host.
:::

::: tip Controlar el razonamiento en modelos compatibles de Ollama
Un modelo local con razonamiento puede superar el tiempo de espera predeterminado. Si el modelo y la versión del servidor lo admiten, `['think' => false]` en `seo-pro.ai.local.extra_body` puede desactivar el razonamiento para las sugerencias. La compatibilidad varía: consulta la [documentación de Ollama](https://docs.ollama.com/capabilities/thinking). Aumenta `seo-pro.ai.timeout` si hace falta. El paquete no envía `temperature`, porque algunos modelos rechazan ese parámetro.
:::

## Coste {#cost}

El paquete no añade recargos: pagas directamente al proveedor. La inferencia en tu infraestructura no tiene tarifa de API del proveedor, aunque sí costes de infraestructura. Hay dos cifras que considerar: el coste **por sugerencia** en uso interactivo y el del **relleno masivo** de una colección.

La tabla `seo-pro.ai.pricing`, en USD por 1.000.000 de tokens, convierte una estimación de tokens en el importe mostrado en la confirmación del relleno masivo. Son **supuestos de estimación distribuidos con el paquete**, no precios públicos actuales verificados. **Sustitúyelos por los precios publicados actuales de tu proveedor** para obtener una estimación adecuada:

| Patrón del modelo | Entrada, USD/1 M | Salida, USD/1 M |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

El ejemplo publicado utiliza el consumo de tokens de una página probada, un título y una descripción, con esos supuestos. La última columna aplica el descuento ilustrativo del 50 % por lotes a los adaptadores compatibles. No son cotizaciones de precios actuales.

| Proveedor y modelo | Aprox. por pareja de sugerencias | Aprox. por 1.000 registros, relleno masivo | Aprox. por 1.000 registros con `--batch` |
|---|---|---|---|
| Local `gemma`/`llama` (Ollama) | **0 USD de tarifa de API** | **0 USD de tarifa de API** | No disponible en el adaptador |
| Google `gemini-2.5-flash`, de pago | 0,001 USD | 0,40 USD | No implementado en el adaptador de Rankbeam |
| OpenAI `gpt-5.5` | 0,008 USD | 5,25 USD | **2,63 USD**, descuento del 50 % |
| Anthropic `claude-opus-4-8` | 0,03 USD | 20 USD | **10 USD**, descuento del 50 % |

El comando etiqueta su estimación con un margen aproximado de ±50 %, pero **no es un límite de gasto ni un intervalo de error garantizado**. Las entradas, salidas y tarifas reales cambian el total. La estimación contempla la salida visible; el razonamiento oculto facturado puede aumentar el coste. Los modelos sin una entrada de precios solo muestran la estimación de tokens.

### Generación masiva más económica {#cheaper-bulk-generation}

Establece `seo-pro.ai.bulk_model` (`SEO_PRO_AI_BULK_MODEL`) para usar otro modelo **solo en el relleno masivo**: `seo-pro:ai-fill` / `SeoPro::aiFill()`. Filament y `seo-pro:ai-suggest` siguen usando `model`. Si es nulo, el relleno masivo también usa `model`. La estimación utiliza el patrón de precios del modelo elegido. Evalúa resultados representativos antes de aumentar el volumen; un modelo más barato no es adecuado automáticamente.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Los ejemplos del paquete utilizan `claude-haiku-4-5` para **anthropic**, `gpt-5.5-mini` para **openai**, `gemini-2.5-flash` para **google** o un modelo **local** más pequeño. Un patrón de precios puede coincidir con un nombre aunque el proveedor no lo ofrezca: confirma el identificador real, la compatibilidad de la API y el precio antes de configurarlo.

Un **relleno de 100 páginas**, todas sin título ni descripción, supone 200 llamadas. Con los precios predeterminados de `pricing` y el supuesto del estimador de 600 tokens de entrada y 150 de salida por llamada, esta es la comparación entre el modelo de calidad y la opción económica de `bulk_model`:

| Proveedor | Modelo de calidad, 100 páginas | Modelo económico de `bulk_model`, 100 páginas |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **4,05 USD** | `claude-haiku-4-5` ≈ **0,27 USD** |
| **OpenAI** | `gpt-5.5` ≈ **1,05 USD** | `gpt-5.5-mini` ≈ **0,11 USD** |
| **Google** | `gemini-2.5-pro` ≈ **0,45 USD** | `gemini-2.5-flash` ≈ **0,04 USD** |
| **Local**, Ollama / vLLM | Cualquier modelo: **0 USD de tarifa de API** | Cualquier modelo: **0 USD de tarifa de API** |

Estas estimaciones son ilustrativas, sin margen garantizado de ±50 % y sin asignación para razonamiento oculto. Actualiza `seo-pro.ai.pricing` con las tarifas publicadas del proveedor elegido antes de apoyarte en la estimación.

## Idioma de salida {#output-language}

Cada instrucción indica el idioma de la página y su código BCP-47: por ejemplo, «en portugués brasileño (pt-BR), el idioma de la página, independientemente de otros idiomas del fragmento». El contexto enviado al modelo incluye una línea `Language:` desde Pro 2.34. Antes se pedía «el mismo idioma que el contenido original», dejando que el modelo lo dedujera de un fragmento corto o mixto: una página turca con una marca inglesa podía recibir una respuesta en inglés. Se usa el idioma con el que se resolvieron los metadatos, o el de la aplicación si la página no tiene uno. Es el mismo que determina el [límite de longitud](/es/guide/multilingual#title-and-description-budgets-per-script), de modo que una página japonesa solicita títulos de unos 30 caracteres *en japonés*.

Desde Pro 2.36, un idioma de contenido explícito controla conjuntamente la fila de metadatos, los métodos de contenido y el idioma de la instrucción. El idioma de la interfaz del operador no cambia.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Los argumentos posicionales existentes no cambian. Omite `locale:` para utilizar el valor predeterminado de `seoData()` del modelo, lo que permite que los modelos de traducción separados declaren su idioma. El fragmento utiliza `getContentForSEO()` si devuelve contenido no vacío y, en caso contrario, los campos configurados. Filament 1.11 pasa automáticamente el idioma de la pestaña seleccionada, también en los modos de idioma único y selector de página.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

Los métodos masivos `plan()`, `fill()` y `submitBatchFill()` también aceptan `locale:` al final. Usa el mismo idioma al crear `FillProgress(..., locale: 'it')` y al enviar la ejecución. Cada elemento del lote guarda su idioma de contenido; la recogida utiliza ese valor y vuelve a comprobar su fila de metadatos antes de escribir. Las ejecuciones con idioma explícito tienen archivos de progreso separados y los marcadores de registros procesados distinguen idiomas. Repite el mismo comando para recoger los resultados. Los trabajos personalizados deben serializar y pasar el idioma de contenido.

Los archivos de progreso anteriores a Pro 2.36 no registraban el idioma. Un lote antiguo pendiente se conserva y se rechaza su recogida automática. Concilia los resultados del proveedor y el idioma previsto antes de descartarlo con `--fresh`; un envío nuevo podría facturar el mismo trabajo de nuevo. Un archivo antiguo de progreso secuencial con registros procesados también requiere conciliación antes de reiniciarse.

### Evaluación por idioma {#per-language-evaluation}

El repositorio de Pro contiene 170 páginas de entrada en 17 idiomas y un sistema de evaluación opcional. Las entradas tienen comprobaciones estructurales y heurísticas de idioma base; la aprobación independiente por hablantes nativos sigue pendiente.

La evaluación comprueba **títulos y descripciones**, registra longitudes en grafemas y evidencia de idioma y escritura, y conserva cada respuesta antes de ejecutar las aserciones. Los títulos cortos, textos mixtos y caracteres compartidos entre chino y japonés pueden dejar dudas. Detectar portugués como idioma base no demuestra uso brasileño, y las comprobaciones parciales de caracteres chinos no certifican la calidad regional de la escritura.

Las ejecuciones reales requieren `SEO_PRO_AI_EVAL=1`, una selección explícita en `SEO_PRO_AI_EVAL_LOCALES` y un identificador `SEO_PRO_AI_EVAL_RUN`. Pueden generar cargos; ninguna se ejecuta por defecto. Cada ejecución vincula la evidencia al proveedor, modelo solicitado y devuelto, hashes de entradas, peticiones y código, y marcas de tiempo. Los intentos fallidos se conservan. Al reanudar se reutilizan las respuestas guardadas; una petición interrumpida requiere un reintento explícito porque quizá ya haya llegado al proveedor.

La evidencia se guarda en `storage/app/seo-ai-evals/<run-id>/` dentro del entorno de pruebas del código fuente. El `README.md` de las entradas documenta el esquema versionado y los comandos. Los revisores nativos puntúan hashes exactos de salida en registros independientes. Una comprobación automática correcta no equivale a aprobación nativa ni garantiza un texto publicable.

## Límites y ajustes {#limits-and-tuning}

Todos los ajustes están en el bloque `ai` de `config/seo-pro.php`:

- **`timeout`**, 15 segundos por defecto, variable `SEO_PRO_AI_TIMEOUT`: también limita la llamada síncrona que realiza el modal de sugerencias de Filament al abrirse. Se mantiene corto por la experiencia de uso. Un **modelo local o de razonamiento lento puede superar los 15 segundos**; aumenta el valor y consulta la nota sobre `think => false` en Ollama si lo necesitas. El tiempo agotado produce un error en la interfaz y nunca bloquea el guardado.
- **`max_input_chars`**, 6000 por defecto: limita el texto de la página enviado por petición, sin HTML, para controlar coste y exposición de contenido.
- **`max_output_tokens`**, 1000 por defecto: límite base de tokens generados. Si se alcanza, devuelve un fallo `truncated` explícito, nunca una respuesta incompleta silenciosa.
- **`token_budgets`**: límites de salida por tarea: `suggestions` 800, `explanation` 600, `rewrite` 300 y `schema_suggestion` 700. No necesitan todo el límite predeterminado, aunque se aplica el mínimo de razonamiento.
- **`reasoning_models`** y **`reasoning_min_output_tokens`**, 2000 por defecto: los modelos cuyo nombre coincide con un patrón como `*gemma*`, `gemini-2.5-*` u `o1*`/`o3*`/`o4*` reciben al menos ese presupuesto de salida. El razonamiento consume tokens ocultos antes de producir texto visible y un presupuesto pequeño podría truncarlo.
- **`suggestion_count`**, 3 por defecto: cantidad de alternativas de título o descripción solicitadas.
- **`retry`**: reintentos automáticos solo para determinados fallos transitorios. Consulta [Tratamiento de respuestas](#how-replies-are-handled).

## En Filament {#in-filament}

Con los paquetes opcionales de Filament instalados (`rankbeam/laravel-seo-filament` >= 1.1), activar la asistencia añade:

- **Suggest with AI**, sugerencias de IA en los campos de título y descripción SEO de los recursos que utilizan la sección SEO en sus páginas de edición. El modal muestra alternativas con recuentos de caracteres; al elegir una se rellena el campo para revisarlo.
- **Explain (AI)**, en la tabla de incidencias del panel: una explicación breve y sencilla de la incidencia y su corrección concreta.
- **Rewrite description (AI)**, junto a la explicación: propone una metadescripción dentro del límite de la página, 160 caracteres para texto latino y unos 80 para CJK según la [política de longitud](/es/guide/multilingual#title-and-description-budgets-per-script). Revísala en el modal. **Apply rewrite** la guarda en el registro `seo_meta` de la página; no se escribe nada antes de aplicarla.
- **Suggest structured data (AI)**, en la tabla de incidencias: propone un tipo de resultado enriquecido de schema.org —Product, Article o Breadcrumb— y muestra el JSON-LD construido. **Apply structured data** lo añade a `seo_meta.schema_jsonld`, la misma columna que gestiona el [editor opcional de datos estructurados](../guide/filament#structured-data-schema-org), donde sigue siendo editable. Una sugerencia incompleta, como Article sin autor o imagen, muestra los campos ausentes y **no** se aplica.

Las dos últimas son correcciones acotadas; consulta [Correcciones acotadas](#bounded-fixes-propose-never-auto-apply).

## Correcciones acotadas: propuestas que requieren aceptación {#bounded-fixes-propose-never-auto-apply}

Dos acciones generan un único valor sujeto a límites que puedes aplicar con un clic. Ambas **solo proponen**: nada se guarda hasta que lo aceptas explícitamente.

- **Reescribir descripción**, `SeoSuggestionService::rewriteDescription($model, $issue?)`, devuelve una metadescripción **dentro del límite de la política del paquete principal para la escritura de la página: 160 para texto latino y unos 80 para CJK**. Es el mismo límite que reciben las instrucciones de sugerencias de títulos y descripciones, elegido a partir del valor resuelto de la página. Si el modelo lo supera, el texto se recorta de forma determinista en un límite de oración y después de palabra, para que la reescritura aceptada no active `description_too_long`. Pasar la incidencia orienta la corrección: por ejemplo, descripción demasiado larga o ausente.
- **Sugerir datos estructurados**, `SeoSuggestionService::suggestSchemaType($model)`, pide únicamente una **recomendación de tipo y valores de campos finales**, nunca JSON-LD sin procesar. El código construye el documento con `ProductSchema`, `ArticleSchema` o `BreadcrumbSchema` y lo valida con `SchemaValidator`. Así, un `@type`, `@context` o estructura inventados no llegan a la página. Los valores de los campos aún pueden ser incorrectos y requieren revisión. Si falta un campo obligatorio, la propuesta se presenta como *incompleta* y no se aplica. En una prueba real, un proveedor propuso Article para una página con poco contenido y se rechazó por falta de autor e imagen; otros no propusieron ningún tipo.

## Headless {#headless}

La misma funcionalidad en JSON para scripts y aplicaciones sin Filament:

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

La salida incluye las sugerencias, o el tipo recomendado, JSON-LD construido y resultado de validación, además del modelo y el **consumo de tokens por petición**: entrada, salida y razonamiento. Estos recuentos ayudan a calcular el coste con las tarifas del proveedor; no son una factura. Ante cualquier fallo, el comando termina con código distinto de cero e incluye el error en la respuesta JSON. `seo-pro:suggest-schema` **solo propone**: imprime el documento sin escribir datos.

## Rellenar metadatos ausentes de forma masiva {#bulk-fill-missing-metadata}

Las superficies anteriores presentan propuestas. La operación masiva que **escribe** es `seo-pro:ai-fill`, también disponible mediante `SeoPro::aiFill()`, para rellenar los huecos de una colección de modelos.

```bash
# preview what would be written (no changes saved)
php artisan seo-pro:ai-fill "App\Models\Post" --dry-run

# fill missing descriptions across all configured models
php artisan seo-pro:ai-fill --field=description

# all configured (seo.audit.models / seo.sitemap.models) models, all fields
php artisan seo-pro:ai-fill --force
```

Recorre los registros y busca los que no tienen **título o descripción**: ni valor explícito *ni* alternativa calculada, con la **misma definición que la [auditoría](/es/guide/audit)**. Genera el valor y lo guarda.

Aplica estas restricciones:

- **Solo rellena huecos.** Omite los campos que ya existen o pueden derivarse; **nunca sobrescribe** un valor existente.
- **`--dry-run`** genera e imprime los valores sin guardarlos, para revisar la salida sin escribir en la base de datos. **Sigue llamando al proveedor y puede generar cargos.**
- **Es una operación de escritura.** En producción pide confirmación salvo que pases `--force`. `--field` (title | description | all) y `--limit` acotan la ejecución.
- Cada campo rellenado supone una llamada al generador facturada a tu clave; solo se ejecuta con la asistencia de IA activada.

### A gran escala: ritmo, estimación de coste y reanudación {#at-scale-pacing-a-cost-estimate-and-crash-resume}

Rellenar cientos o miles de modelos es una operación larga y, con un proveedor de pago, **facturada**. El comando incorpora estos controles:

- **Llamadas espaciadas.** `seo-pro.ai.fill.throttle_ms`, 200 por defecto, introduce una pausa entre peticiones para reducir ráfagas que alcancen el límite del proveedor. Usa 0 si tu servidor local o proveedor gratuito tiene capacidad suficiente, o aumenta el valor en niveles con límites bajos.
- **Estimación de coste antes de empezar.** Una ejecución que afecte al menos a `seo-pro.ai.fill.confirm_over` registros, 100 por defecto, muestra una estimación y pide confirmación **antes de la primera llamada**:

  ```text
  About to fill ~890 missing fields via anthropic (claude-opus-4-8) across 948 records.
  Estimated ~667,500 tokens ≈ $18.02 (rough, ±50%).
  Continue? (yes/no) [no]
  ```

  El importe procede de `seo-pro.ai.pricing`; consulta [Coste](#cost). Un modelo sin precio configurado, como uno local, muestra solo los tokens estimados, sin inventar un importe. `--force` omite la confirmación para automatizaciones; `--dry-run` también confirma, porque realiza las mismas llamadas facturadas.
- **Reanudación desde el progreso guardado.** Se guarda el progreso después de **cada registro**. Al reanudar se omiten los campos completados y registrados; los fallos transitorios dejan el registro pendiente de reintento. **No garantiza ausencia de cargos duplicados**: una petición puede llegar al proveedor antes de que un tiempo agotado o una interrupción impidan guardar su resultado. Una ejecución completa elimina su archivo de progreso. Concilia el trabajo incierto antes de usar `--fresh` para ignorar el progreso anterior.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, apply: true);
// ['processed' => 120, 'filled' => 18, 'skipped' => 102, 'failed' => 0, 'resumed' => 0, 'errors' => [], 'records' => [...]]
// On a provider failure, 'errors' maps each distinct error code to its human
// message (e.g. 'quota_exceeded' => 'OpenAI: the provider account is out of
// credit or quota…'), and the seo-pro:ai-fill command prints those reasons —
// so a run never fails silently.
```

### Modo por lotes: descuento del 50 % {#batch-mode-50-cheaper}

Para un relleno grande que no necesita resultados inmediatos, `--batch` envía la ejecución al **servicio asíncrono por lotes** del proveedor: [Anthropic Message Batches](https://docs.anthropic.com/en/docs/build-with-claude/batch-processing) u [OpenAI Batch API](https://platform.openai.com/docs/guides/batch), con la mitad del precio por token. **Los adaptadores de Google y local de Rankbeam no implementan esta ruta**: `--batch` muestra un aviso y ejecuta las llamadas secuencialmente. Google tiene su propia [Batch API](https://ai.google.dev/gemini-api/docs/batch-api), pero esta integración no la utiliza. Comprueba la compatibilidad y los precios actuales del modelo en cada proveedor.

Un lote se **envía ahora y se recoge después**, en dos ejecuciones del mismo comando. Puedes cerrar el proceso entre ambas:

```bash
# 1) Submit: builds one request per missing field, sends the whole batch in a
#    single call, prints the discounted estimate, and exits. Nothing is written yet.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch

#    → Batch submitted: msgbatch_01Hkc… — 890 requests across 890 records via anthropic.
#      Most batches finish within an hour (max 24h, then they expire).
#      Re-run the SAME command to poll and apply the results:
#        php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch

# 2) Collect: re-run the same command. While the batch is still processing it
#    just says so and exits; once results are ready it writes them and prints
#    the usual summary.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

- **Las mismas peticiones, a mitad de precio.** Cada elemento utiliza la misma instrucción, esquema de salida estructurada y configuración de modelo que la llamada síncrona; usa el [`bulk_model`](#cheaper-bulk-generation) económico cuando está configurado. Cambian el envoltorio del lote y el momento de ejecución.
- **La estimación previa ya incluye el descuento.** Al superar `seo-pro.ai.fill.confirm_over`, el envío muestra el importe con **50 % de descuento** y pide confirmación antes de enviar. `--force` la omite para automatizaciones.
- **Permite cerrar el proceso.** El identificador del lote del proveedor y la relación entre peticiones y registros se guardan con el mismo seguimiento que una ejecución secuencial. La recogida funciona desde otro proceso, un cron posterior o después de desplegar. Si el proceso se interrumpe entre la aceptación del proveedor y el guardado del identificador, la siguiente ejecución **se detiene por precaución**: avisa de que puede haber un lote en curso y pide consultar el panel del proveedor, en vez de volver a enviarlo y facturarlo silenciosamente.
- **Nunca sobrescribe.** La regla se mantiene durante toda la ejecución: al recoger resultados, vuelve a comprobar cada registro y solo escribe campos **que siguen ausentes**. Un título o descripción añadidos manualmente mientras se procesaba el lote quedan protegidos.
- **Gestiona resultados parciales.** Los resultados se relacionan por identificador, en cualquier orden. Un campo correcto se guarda y se marca como completado. Los fallos **transitorios**, como límite de peticiones, tiempo agotado, error del proveedor o elemento caducado, quedan pendientes; repetir `--batch` envía un lote nuevo y más pequeño solo con ellos. Los rechazos **definitivos**, como filtros de contenido o peticiones inválidas, se registran y no se reintentan, evitando bucles de facturación.
- **Rechaza cambiar de proveedor durante el lote.** Si cambias `SEO_PRO_AI_PROVIDER` entre envío y recogida, esta se detiene con un mensaje claro: vuelve al proveedor anterior para recoger o descarta con `--fresh` después de conciliar el trabajo pendiente. No consulta silenciosamente la API equivocada. Ejecuta un solo envío `--batch` a la vez; no lances dos para los mismos modelos y campos.
- **Tiempo de espera específico:** `seo-pro.ai.fill.batch.request_timeout`, 120 segundos por defecto, variable `SEO_PRO_AI_FILL_BATCH_TIMEOUT`. Limita las llamadas HTTP de envío, consulta y recogida. Es mayor que el tiempo síncrono porque el envío carga todas las peticiones y la recogida descarga el archivo completo de resultados. Auméntalo para ejecuciones muy grandes.

::: tip Programar la recogida
Como el envío y la recogida son independientes, puedes enviar desde un paso de despliegue o un comando puntual y programar `seo-pro:ai-fill … --batch` cada 15–30 minutos para consultar y aplicar los resultados cuando terminen. No requiere mantener abierto un proceso de larga duración.
:::

## Tratamiento de respuestas {#how-replies-are-handled}

Cada llamada devuelve una respuesta con formato independiente del proveedor, para mantener el mismo comportamiento entre integraciones:

- **Salida estructurada cuando es compatible.** OpenAI Structured Outputs, Google `responseSchema` y Anthropic `output_config.format` imponen la forma JSON mediante la API. Un JSON inválido produce un fallo controlado, sin extraer datos improvisadamente del texto. Al servidor local o compatible con OpenAI también se le solicita `response_format`, aunque puede ignorarlo. El análisis tolerante sirve entonces como alternativa. En ambos casos se obtiene una lista válida o un fallo claro, nunca una respuesta interpretada a medias.
- **El truncamiento es un error explícito.** Si se alcanza el límite de tokens de salida, devuelve `truncated` e indica aumentar `seo-pro.ai.max_output_tokens`; no devuelve silenciosamente un título recortado. Es más habitual con modelos de razonamiento, a los que se aplica el mínimo superior `reasoning_min_output_tokens` cuando coinciden con los patrones configurados.
- **Se reintentan determinados fallos transitorios.** Un límite `429` o un error `5xx` se reintentan con espera exponencial limitada y respetando `Retry-After` dentro de un máximo, para que un valor malicioso no bloquee la petición. **No** se reintentan automáticamente una clave incorrecta, una petición mal formada, un contenido demasiado grande, un **tiempo de espera agotado** ni una cuenta **sin crédito o cuota**. Ajusta el bloque `retry` o establece `max_attempts` en 0 para desactivar los reintentos.
- **Los errores tienen códigos y mensajes depurados.** Cada fallo incluye un código estable (`unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`, `content_too_large`, `bad_request`, `truncated`, `content_filtered`, `provider_error`, etc.) y un indicador `retryable` para los transitorios. El mensaje es breve y depurado. **La ejecución normal no muestra ni registra el cuerpo sin procesar de la respuesta; la evaluación opcional descrita arriba sí conserva respuestas como evidencia.** En Filament, el error aparece en el modal con una indicación concreta para los casos habituales. Consulta [Solución de problemas](#troubleshooting).

## Solución de problemas {#troubleshooting}

Los fallos aparecen en la interfaz, no bloquean el trabajo e incluyen un código y un mensaje depurado. Los más habituales:

| Síntoma y código | Significado | Solución |
|---|---|---|
| **`quota_exceeded`**, cuenta sin crédito o cuota | La clave es válida, pero la **cuenta de API no tiene crédito o cuota**. No es un límite transitorio de peticiones y reintentar no lo resuelve. Anthropic indica saldo demasiado bajo; OpenAI indica cuota agotada y revisar plan y facturación (`insufficient_quota`); Google indica crédito prepago agotado. | Añade crédito o activa la facturación en la consola del proveedor, o usa un modelo **local** sin tarifa de API del proveedor. Una **suscripción** a Claude o ChatGPT no paga la **API**. |
| **`unauthorized`**, autenticación fallida | La clave falta, es incorrecta o no corresponde al proveedor configurado. | Comprueba la variable cuyo nombre figura en `seo-pro.ai.api_key_env`, por defecto `SEO_PRO_AI_API_KEY`: debe existir, estar vigente y corresponder a `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`**, límite de peticiones alcanzado | Límite **transitorio** real; primero se reintenta automáticamente. | Espera y repite, o usa un modelo **local** según la capacidad de tu servidor. Aumenta `seo-pro.ai.fill.throttle_ms` en ejecuciones masivas con cuotas bajas. |
| **`timeout`**, tiempo agotado | El proveedor no respondió dentro de `seo-pro.ai.timeout`, 15 segundos por defecto. Es habitual en modelos locales lentos de razonamiento. | Aumenta `SEO_PRO_AI_TIMEOUT`; en Ollama puedes usar `['think' => false]` en `seo-pro.ai.local.extra_body` si el modelo y servidor lo admiten. |
| **`truncated`**, límite de `max_output_tokens` | La respuesta agotó el presupuesto de salida, posiblemente incluido el razonamiento oculto. | Aumenta `seo-pro.ai.max_output_tokens`, que puede necesitar 2000 o más, o comprueba que el nombre coincide con `reasoning_models` para aplicar el mínimo. |
| **`content_too_large`**, HTTP 413 | El contenido enviado superó el límite del proveedor. | Reduce `seo-pro.ai.max_input_chars` para enviar un fragmento más corto. |
| **`bad_request`** | Petición mal formada, normalmente por un modelo inaccesible para la cuenta o un parámetro incompatible. | Comprueba que `SEO_PRO_AI_MODEL` corresponde a un modelo accesible con tu clave y servidor para ese proveedor. |
| **`content_filtered`** | El filtro de seguridad del proveedor rechazó responder. | Revisa el contenido y las indicaciones del proveedor; no repitas automáticamente la petición rechazada. |

::: tip La inferencia local necesita un servidor funcional
`SEO_PRO_AI_PROVIDER=local` con inferencia propia evita problemas de crédito del proveedor en la nube. Siguen importando el hardware, el modelo, la compatibilidad de la API, los tiempos de espera y la capacidad. Una pasarela remota configurada con este adaptador puede exigir clave y pago.
:::

## Qué sale de tu servidor {#what-leaves-your-server}

Solo se envía al proveedor configurado y tras una acción explícita, como pulsar una acción o invocar un comando:

- **Sugerencias:** nombre corto de la clase del modelo e identificador, por ejemplo «Post #3»; título y descripción resueltos; URL canónica; y un fragmento de texto sin HTML limitado por `max_input_chars`, 6000 caracteres por defecto.
- **Explicación de incidencias:** tipo, gravedad, campo, mensaje y URL de la incidencia, más el título y la descripción resueltos del modelo afectado.
- **Reescritura de descripción:** el mismo contexto mínimo de página que una sugerencia, más el tipo y mensaje de la incidencia cuando se proporcionan.
- **Sugerencia de datos estructurados:** el mismo contexto mínimo. El modelo devuelve únicamente el tipo y valores de campos finales; el JSON-LD se construye localmente.

El paquete no recopila deliberadamente datos de visitantes, direcciones IP, cabeceras de petición ni credenciales para incluirlos en las instrucciones, y no envía el HTML completo. **Tus campos de contenido y fragmentos pueden contener información sensible**: revisa lo que expone tu aplicación. La credencial del proveedor se utiliza para autenticar la petición. `SECURITY.md` del repositorio de Pro es la referencia de tratamiento de datos.

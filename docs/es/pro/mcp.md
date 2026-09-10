---
description: "Servidor MCP stdio sin dependencias adicionales para que un asistente lea y, si lo habilitas, edite el SEO de Laravel. Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5."
---

# Servidor MCP {#mcp-server}

El servidor MCP de Rankbeam permite que un asistente **lea y, opcionalmente, edite el SEO del sitio** mediante [Model Context Protocol](https://modelcontextprotocol.io). Conecta Claude Code, Claude Desktop, Cursor, Codex u otro cliente a tu aplicación Laravel para resolver metadatos, auditar, consultar la puntuación Pro y la política de rastreadores y, si lo permites, guardar cambios SEO.

Es un servidor stdio autónomo, **sin dependencias adicionales**, SDK ni paquetes nuevos. Funciona con PHP 8.2–8.4 (Laravel 11), PHP 8.2–8.5 (Laravel 12), PHP 8.3–8.5 (Laravel 13).

::: tip Función de Pro
Se incluye en `rankbeam/laravel-seo-pro` y es **de solo lectura por defecto**. Las ediciones requieren una opción explícita y una lista de modelos permitidos.
:::

## Qué puede hacer el asistente {#what-the-assistant-can-do}

### Herramientas de análisis disponibles siempre {#analysis-tools-always-available}

| Herramienta | Función |
| --- | --- |
| `seo_resolve` | Metadatos completamente resueltos de un registro: título, descripción, canonical, robots, Open Graph y JSON-LD que se renderizarían |
| `seo_audit` | [Auditoría de metadatos](/es/guide/audit) de un registro o los primeros N, con las mismas comprobaciones de `seo:audit`, sin cola |
| `seo_score` | Última [puntuación Pro](/es/pro/scoring) guardada, número y categoría |
| `seo_robots_directives` | [Directivas robots gestionadas](/es/guide/ai-crawlers) y política resuelta por bot |
| `validate_schema` | Valida un objeto JSON-LD o grafo resuelto de un modelo permitido con el validador del núcleo y sus requisitos por `@type` |
| `analyze_robots` | Decisión allow/disallow por rastreador conocido y origen: regla por bot, política por finalidad o valor predeterminado; política global del sitio |
| `debug_social_share` | Open Graph y Twitter resueltos del modelo, con respaldos y notas orientativas sobre la tarjeta |
| `check_meta` | Título, descripción, canonical, robots e imagen OG resueltos, longitudes, presencia y problemas de auditoría de un registro |

### Herramientas de contenido disponibles siempre {#site-content-tools-always-available}

Permiten enumerar y buscar páginas para responder preguntas sobre el contenido del sitio.

| Herramienta | Función |
| --- | --- |
| `list_pages` | Enumera registros SEO de un modelo permitido, con URL y título efectivo; paginación `limit`/`offset` |
| `search_pages` | Busca en páginas de un modelo permitido usando [Laravel Scout](https://laravel.com/docs/scout) si es buscable, o SQL `LIKE` sobre title/name/headline y metadatos asociados; devuelve URL, título y fragmento |

### Herramientas operativas opcionales {#ops-tools-opt-in}

Leen el estado de scans y modifican configuración del sitio. Como la edición, requieren **`allow_edits`** y permanecen ocultas e inactivas en el modo predeterminado de solo lectura.

| Herramienta | Función |
| --- | --- |
| `list_issues` | Problemas SEO abiertos entre ejecuciones y cabecera del último [scan](/es/pro/scan-issues), con filtros `severity` / `type` |
| `trigger_scan` | Inicia un scan de un registro permitido o de todos los objetivos; en cola por defecto o en el propio proceso con `sync: true` |
| `create_redirect` | Crea una regla de ruta o regex a destino, con estado `301`/`302`/`307`/`308`/`410`, usando los validadores del modelo |

### Herramienta de edición opcional {#edit-tool-opt-in}

| Herramienta | Función |
| --- | --- |
| `seo_save_meta` | Guarda título, descripción, canonical, robots, OG, Twitter y JSON-LD de un registro permitido mediante `saveSEO()` |

Las herramientas operativas y `seo_save_meta` no se anuncian en `tools/list` ni se pueden ejecutar hasta habilitar ediciones. Consulta [Seguridad](#security).

## Conectar un cliente de IA {#wiring-an-ai-client}

El servidor habla JSON-RPC por **stdio**. El cliente inicia un comando Artisan y se comunica por la tubería. El mismo servidor funciona con todos los clientes.

::: tip Un comando para todos los clientes
Todos ejecutan `php artisan seo-pro:mcp` desde la raíz de la aplicación. Si `php` no está en el `PATH` del cliente, algo habitual en Windows o aplicaciones gráficas, usa rutas absolutas tanto a PHP como a `artisan`. Artisan arranca desde el directorio del propio script y no necesita un `cwd` adicional.
:::

### Claude Code: CLI {#claude-code-cli}

Regístralo desde la raíz de la aplicación:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Comprueba la conexión:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

En Windows con Laravel Herd, usa rutas absolutas:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Abre **Settings → Developer → Edit Config** o el archivo directamente:

- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`.
- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`.

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

En Windows, indica la ruta absoluta a `php.exe` y escapa cada barra invertida en JSON:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "C:\\Users\\you\\.config\\herd\\bin\\php84\\php.exe",
      "args": ["C:\\path\\to\\app\\artisan", "seo-pro:mcp"]
    }
  }
}
```

Cierra Claude Desktop por completo y vuelve a abrirlo. Las herramientas aparecen en el icono de herramientas/conexión de la barra de mensajes.

### Cursor {#cursor}

Crea `.cursor/mcp.json` en el proyecto, o `~/.cursor/mcp.json` para todos. Usa la ruta absoluta de `artisan`:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

En Windows, usa `php.exe` absoluto y barras invertidas escapadas, como en el ejemplo anterior. Activa el servidor en **Settings → MCP**.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

En Windows/Herd, usa rutas absolutas a PHP y `artisan`, igual que con Claude Code. El asistente podrá llamar a las herramientas.

## Ejemplo de conversación {#a-worked-conversation}

Intercambio real con una aplicación de demostración de un instituto médico con unas 900 páginas, con la salida abreviada. La lista permite `App\Models\Page` y `App\Models\PublicPage`, y `allow_edits` está activo.

> **Tú:** ¿Cómo está el SEO de «Accoglienza», Page id 1? Corrige lo evidente.

El asistente audita primero con `seo_audit { model: "App\\Models\\Page", id: 1 }`:

```json
[
  {
    "id": 1,
    "url": "http://localhost/accoglienza",
    "issue_count": 2,
    "issues": [
      { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 17 characters (recommended min 30).", "context": { "length": 17, "min": 30 } },
      { "code": "description_too_short", "severity": "notice", "field": "description", "message": "Description is only 11 characters (recommended min 70).", "context": { "length": 11, "min": 70 } }
    ]
  }
]
```

Después resuelve los metadatos con `seo_resolve { model: "App\\Models\\Page", id: 1 }` y encuentra un título de 17 caracteres y una descripción de 11, ambos por debajo de los mínimos recomendados. Guarda una corrección mediante `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

La herramienta confirma la escritura y devuelve el registro recién resuelto:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Finalmente vuelve a auditar; `issue_count` es `0`:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Asistente:** El título de 17 caracteres y la descripción de 11 estaban por debajo de los mínimos recomendados. Los amplié con texto descriptivo y la nueva auditoría devuelve cero problemas.

La escritura usa el mismo `saveSEO()` que el código de aplicación, con campos fillable y casts del modelo. No pasa por los validadores de longitud del formulario Filament. La auditoría posterior solo verifica sus comprobaciones automáticas; la precisión editorial sigue necesitando revisión y cero problemas no certifica la calidad del texto.

### Preguntar sobre el contenido del sitio {#talking-to-your-site-s-content}

Las herramientas de contenido permiten buscar sin conocer previamente el registro.

> **Tú:** ¿Qué páginas mencionan «immunoterapia» y tienen títulos adecuados?

El asistente busca con `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

```json
{
  "model": "App\\Models\\Page",
  "query": "immunoterapia",
  "driver": "like",
  "count": 3,
  "results": [
    { "id": 42, "url": "http://localhost/ricerca/immunoterapia-oncologica", "title": "Immunoterapia oncologica", "snippet": "Il programma di immunoterapia dell'IDI: terapie, sperimentazioni e accesso…" },
    { "id": 87, "url": "http://localhost/reparti/dermatologia-oncologica", "title": "Dermatologia oncologica", "snippet": "…include protocolli di immunoterapia per i tumori cutanei avanzati…" },
    { "id": 91, "url": "http://localhost/news/nuova-immunoterapia-2026", "title": "Nuova", "snippet": "Al via una nuova immunoterapia…" }
  ]
}
```

El tercer título es «Nuova», por lo que consulta `check_meta { model: "App\\Models\\Page", id: 91 }`:

```json
{
  "url": "http://localhost/news/nuova-immunoterapia-2026",
  "title": { "value": "Nuova", "length": 5, "present": true },
  "description": { "value": null, "length": 0, "present": false },
  "og_image": { "value": null, "present": false },
  "issue_count": 2,
  "issues": [
    { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 5 characters (recommended min 30)." },
    { "code": "missing_description", "severity": "warning", "field": "description", "message": "No meta description is set." }
  ]
}
```

> **Asistente:** Tres páginas mencionan «immunoterapia». Dos tienen metadatos adecuados, pero **/news/nuova-immunoterapia-2026** tiene un título de cinco caracteres, «Nuova», y ninguna descripción. ¿Quieres que prepare un título y una descripción más completos?

Con `allow_edits` podría guardarlos mediante `seo_save_meta`, sujeto a la autorización y reglas de aprobación del cliente. En solo lectura se limita a recomendar. Aquí `search_pages` usó SQL `LIKE`, `"driver": "like"`, porque el modelo no estaba indexado en Scout. Al añadir [Laravel Scout](https://laravel.com/docs/scout), la misma herramienta utiliza el motor configurado.

## Seguridad {#security}

Tres capas definen el modo predeterminado. Sus restricciones solo se amplían deliberadamente.

### 1. Ediciones desactivadas por defecto {#_1-edits-are-gated-off-by-default}

La herramienta de escritura es invisible e inactiva hasta habilitarla:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

Con `allow_edits` desactivado, `seo_save_meta` no aparece en `tools/list` y llamarla devuelve JSON-RPC `-32602`. Habilítala solo para clientes y bases de confianza. Esta opción permite la herramienta en el servidor; no sustituye las reglas de aprobación del asistente conectado.

### 2. Lista de modelos permitidos {#_2-the-model-allowlist}

Las herramientas por modelo, de lectura o escritura, solo acceden a modelos con `HasSEO` incluidos en la lista. El cliente no puede dirigirlas a clases arbitrarias de usuarios, facturación u otros datos:

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Una clase no permitida devuelve un error como `Model [App\Models\User] is not in the MCP allowlist`, sin acceder a ella. Si `models` está vacío, se utilizan `seo.audit.models` / `seo.sitemap.models`, sin ampliar el ámbito que ya usa el paquete.

### 3. Solo stdio, sin servicio de red {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

El cliente inicia el proceso y transmite JSON-RPC por stdio. No hay listener HTTP, puerto ni socket accesible desde otra máquina. STDOUT solo contiene protocolo; los diagnósticos van a STDERR para evitar corromper la comunicación. El cliente los registra, por ejemplo Claude Desktop en `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`.

::: warning Habilitar edición equivale a dar acceso de escritura a esos datos
`allow_edits` permite cambiar filas SEO de la base contra la que ejecutas el comando. Experimenta en local/staging, limita los modelos y desactiva la edición al terminar. `'enabled' => false` impide arrancar el servidor. Stdio local no impide que el cliente envíe los resultados a su proveedor de IA; revisa también la configuración de datos del cliente.
:::

## Configuración {#configuration}

```php
// config/seo-pro.php
'mcp' => [
    'enabled'     => true,           // master switch; the command refuses to run when false
    'allow_edits' => false,          // expose + permit the ops tools + seo_save_meta
    'models'      => [],             // allowlist; [] = fall back to audit/sitemap models
    'server_name' => 'rankbeam-seo', // reported in the MCP initialize handshake

    // Optional Server Card discovery route (off by default) — see below.
    'server_card' => [
        'enabled'     => false,      // serve GET {path} with the discovery card
        'path'        => '.well-known/mcp/server-card.json',
        'name'        => null,       // reverse-DNS server name (null = derived from app.url)
        'schema_url'  => 'https://modelcontextprotocol.io/schemas/draft/server-card.json',
        'website_url' => null,       // optional homepage/docs URL stamped on the card
    ],
],
```

## Server Card: descubrimiento experimental {#server-card-discovery-—-experimental-draft-spec}

Una **MCP Server Card** es un documento JSON en una URL conocida que permite descubrir nombre, versión y capacidades del servidor antes de conectarse. Rankbeam puede servirla para anunciar la disponibilidad de MCP. Está desactivada por defecto y activarla no cambia las demás funciones.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Con la opción activa, `GET /.well-known/mcp/server-card.json` devuelve una tarjeta como esta:

```json
{
  "$schema": "https://modelcontextprotocol.io/schemas/draft/server-card.json",
  "name": "com.example/rankbeam-seo",
  "version": "1.0.0",
  "title": "Rankbeam SEO MCP server",
  "description": "Read — and optionally edit — this site's SEO over the Model Context Protocol…",
  "_meta": {
    "io.rankbeam.seo/transport": "stdio",
    "io.rankbeam.seo/launch": "php artisan seo-pro:mcp",
    "io.rankbeam.seo/tool_count": 10,
    "io.rankbeam.seo/tools": [ { "name": "seo_resolve", "description": "…" } ]
  }
}
```

Solo enumera las herramientas actualmente habilitadas. Un servidor de solo lectura no anuncia las operativas ni la de edición.

::: warning Basado en una especificación en borrador
Sigue [SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127), propuesta abierta y no incorporada a fecha de 10 de septiembre de 2026. La ruta, URL `$schema` y campos no son definitivos; `path`, `schema_url`, `name` y `website_url` son configurables. El servidor funciona por stdio, `php artisan seo-pro:mcp`, por lo que la tarjeta no contiene un bloque HTTP `remotes`. Es una señal de descubrimiento, no un endpoint HTTP de conexión. Comprueba formato y ruta con tu cliente y déjala apagada si no la necesitas.
:::

## Notas de protocolo {#protocol-notes}

Implementa directamente JSON-RPC 2.0 para `initialize`, negociación de versión y capacidades, `tools/list`, `tools/call` y `ping`. Anuncia `2025-06-18` y entiende `2025-03-26` y `2024-11-05`. Devuelve `-32601` para métodos desconocidos y `-32700` para líneas mal formadas. Un fallo de herramienta llega como resultado `isError`, no como error de transporte. Las notificaciones sin `id`, como `notifications/initialized`, no reciben respuesta.

## Uso desde código y extensiones {#headless-extending}

`SeoPro::mcp()` devuelve el registro para consultar herramientas o registrar las tuyas:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Una herramienta implementa `McpTool`: `name`, `description`, `inputSchema`, `isEnabled` y `handle`. Extiende `AbstractTool` para reutilizar la resolución de modelos permitidos y conservar esas restricciones.

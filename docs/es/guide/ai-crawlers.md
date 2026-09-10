---
description: "Genera robots.txt y, si lo necesitas, ai.txt con una política para rastreadores de búsqueda, asistentes y entrenamiento de IA."
---

# Control de rastreadores de IA (robots.txt / ai.txt)

Los proveedores de IA recorren la Web con robots identificados por nombre. Muchos consultan **robots.txt** para decidir qué pueden recuperar. Rankbeam incluye un catálogo mantenido y genera una `robots.txt` gestionada, más una `ai.txt` opcional, desde una política `allow` / `disallow`. Puedes permitir búsqueda y asistentes y rechazar entrenamiento.

Es una función gratuita del núcleo. Pro añade un [registro de visitas de bots de IA (EN)](/es/pro/ai-bot-monitor) para observar los accesos recibidos.

## Política predeterminada {#the-default-policy}

Cada robot tiene una finalidad principal:

| Finalidad | Uso | Predeterminado |
|---|---|---|
| `ai_search` | Recuperar páginas para el índice de búsqueda de IA | **allow** |
| `ai_assistant` | Recuperar una página en tiempo real por petición de una persona | **allow** |
| `ai_training` | Recopilar contenido para entrenar un modelo | **disallow** |

La configuración inicial permite buscadores y asistentes, como los de ChatGPT Search y Perplexity, y rechaza el entrenamiento. Puedes cambiar cada decisión.

::: warning Acceso no significa cita
Permitir un rastreador hace posible la recuperación. No garantiza descubrimiento, indexación, posiciones, inclusión en respuestas, citas ni enlaces a la fuente. La política describe el acceso, no sus resultados posteriores.
:::

## Inicio rápido {#quick-start}

Muestra primero el bloque que publicarías:

```bash
php artisan seo:robots-txt --print
```

Puedes usarlo de dos maneras.

### Opción A — añadirlo a una robots.txt existente {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Si ya mantienes `public/robots.txt`, obtén solo el bloque gestionado y pégalo en ella:

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### Opción B — dejar que Rankbeam gestione todo el archivo {#option-b-—-let-rankbeam-manage-the-whole-file}

Genera una `robots.txt` completa con sección general, reglas de IA, línea `Sitemap:` y referencia a [llms.txt](/es/guide/sitemaps):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Programa el comando para seguir los cambios de política:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

También puedes servirlo de forma dinámica. Con `seo.ai_crawlers.route = true`, el paquete responde a `/robots.txt` desde la configuración actual, sin generar antes un archivo.

::: warning El archivo estático tiene prioridad
El servidor suele servir `public/robots.txt` antes de que Laravel reciba la petición. La ruta dinámica está desactivada por defecto para evitar conflictos silenciosos con un archivo olvidado. Úsala solo si no hay una `robots.txt` estática.
:::

## Límites de aplicación de las reglas {#honesty-about-enforcement}

robots.txt expresa una petición; no es una barrera técnica. Muchos robots declaran respetarlo, pero algunos agentes activados por el usuario (`ChatGPT-User`, `Perplexity-User`) y rastreadores de entrenamiento (`Bytespider`) no ofrecen esa garantía. Rankbeam marca esas líneas como `advisory`. Para detener a un bot que no coopera, necesitas reglas del servidor o de red: firewall, WAF o reglas de bots de Cloudflare. El [registro Pro (EN)](/es/pro/ai-bot-monitor) ayuda a identificar visitas observadas.

## Content Signals: preferencias de uso {#content-signals-usage-preferences}

`Allow` y `Disallow` describen el **acceso**. [Content Signals](https://contentsignals.org), impulsado por Cloudflare, describe el **uso deseado** del contenido recuperado. Una línea `Content-Signal:` dentro de `User-agent: *` expresa tres preferencias:

| Señal | Finalidad relacionada | Significado |
|---|---|---|
| `search` | `ai_search` | Crear un índice con enlaces y fragmentos breves |
| `ai-input` | `ai_assistant` | Usar la página como entrada de un modelo en tiempo real, por ejemplo para RAG |
| `ai-train` | `ai_training` | Entrenar o ajustar un modelo |

Está **desactivado por defecto** y no cambia los bytes del archivo hasta activarlo. Rankbeam deriva entonces la línea de `policy`: `allow` pasa a `yes`, `disallow` a `no`.

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Si eliminas una finalidad de `policy`, su señal se omite. Significa que no has expresado preferencia, a diferencia de un `yes` o `no` explícito.

::: warning Preferencias, no protección técnica
Un rastreador puede ignorar Content Signals. Complementan las reglas de acceso y posibles bloqueos de red, pero no los sustituyen.
:::

## Configuración {#configuration}

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose. A purpose left out is allowed.
    'policy' => [
        'ai_training'   => 'disallow',
        'ai_search'     => 'allow',
        'ai_assistant'  => 'allow',
        'search_engine' => 'allow',   // Yandex, Baidu, Naver, Seznam, … (3.15)
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
        'baiduspider' => 'disallow',  // e.g. keep a search engine you don't serve off your bandwidth
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

`overrides` reemplaza la política de finalidad para un bot concreto. Las claves son los **identificadores del catálogo**, como `gptbot`, `claudebot`, `perplexitybot` o `google-extended`.

## Catálogo {#the-catalog}

`SEO::aiCrawlers()` entrega el mismo catálogo que utiliza el registro Pro para identificar visitantes. La generación de reglas y la observación comparten esa fuente.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Incluye, entre otros, OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User), Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended), Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon y ByteDance, con su finalidad documentada y token robots.txt.

### Buscadores regionales {#regional-search-engines}

Desde 3.15, el catálogo incluye rastreadores de buscadores clásicos además de Google y Bing. Su finalidad es `search_engine` y están **permitidos por defecto**:

| ID | Token | Operador |
|---|---|---|
| `yandex` | `Yandex` | Yandex, Rusia; el token base cubre sus robots |
| `baiduspider` | `Baiduspider` | Baidu, China |
| `yeti` | `Yeti` | Naver, Corea |
| `seznambot` | `SeznamBot` | Seznam, Chequia |
| `sogou` | `Sogou web spider` | Sogou, China |
| `360spider` | `360Spider` | Qihoo 360, China |
| `coccocbot` | `coccocbot-web` | Cốc Cốc, Vietnam |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Siguen `policy` y `overrides` como los demás. Por ejemplo, `'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` les pide no recorrer el sitio. Con `'list' => 'all'`, cada uno recibe una regla explícita.

No aparecen en `all()` ni `match()` salvo petición expresa mediante `searchEngines()`, `all(true)` o `match($ua, true)`. Así, el registro y los contadores de IA conservan su significado:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Reconocer un robot no garantiza visibilidad ni posiciones en su buscador. Las etiquetas de verificación (`yandex-verification`, `baidu-site-verification`, `naver-site-verification`, `seznam-wmt`) se configuran bajo `seo.verification`; consulta [contenido multilingüe](/es/guide/multilingual#site-verification).

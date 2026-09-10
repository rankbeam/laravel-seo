---
description: "Ofrece una representación Markdown de una página mediante negociación de contenido, manteniendo el HTML original para los visitantes. Gratis y desactivado por defecto."
---

# Markdown para bots {#markdown-for-bots}

El HTML de una aplicación rodea el contenido de navegación, scripts y marcado del layout. Algunos rastreadores de IA y motores de respuesta aceptan una representación más limpia cuando se ofrece. Esta función sirve una **representación Markdown** a los clientes que la solicitan mediante negociación de contenido, mientras los visitantes normales siguen recibiendo el HTML intacto. Es una opción de compatibilidad que debes activar, no una promesa sobre cómo un cliente concreto interpretará o utilizará el resultado.

Se complementa con el [control de rastreadores de IA](/es/guide/ai-crawlers): aquel define la política de acceso; esta función decide qué contenido servir durante la petición.

Es una función gratuita del núcleo y está **desactivada por defecto**.

## Cómo funciona {#how-it-works}

Al activarla se registra un middleware de negociación de contenido. Después de generar la respuesta habitual, la sustituye por Markdown **solo si se cumplen ambas condiciones**:

1. **La petición solicita Markdown** mediante una cabecera explícita `Accept: text/markdown`, el parámetro `?format=md` o, si lo habilitas, un user-agent de rastreador de IA conocido.
2. **Se resuelve una fuente Markdown para la ruta.**

En los demás casos, la respuesta no cambia. La navegación normal no se ve afectada y solo se sustituye una respuesta **HTML correcta**, nunca JSON, una redirección o una descarga.

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## De dónde procede el Markdown {#where-the-markdown-comes-from}

Las siguientes fuentes pueden proporcionar Markdown para la ruta. El middleware prueba **primero una fuente de ruta registrada** y después los modelos vinculados a la ruta. En cada modelo, un método explícito `toSeoMarkdown()` tiene prioridad sobre la generación de respaldo; si devuelve null o una cadena en blanco, desactiva ese respaldo para el modelo.

### 1. El Markdown del modelo {#_1-a-model-s-own-markdown}

Si ninguna fuente de ruta registrada devuelve contenido, un modelo vinculado a la ruta que implemente `toSeoMarkdown()` controla su salida. Puedes implementar el contrato `ProvidesSeoMarkdown` o añadir directamente el método:

```php
use Rankbeam\Seo\Contracts\ProvidesSeoMarkdown;

class Post extends Model implements ProvidesSeoMarkdown
{
    use HasSEO;

    public function toSeoMarkdown(): ?string
    {
        return $this->body_markdown; // your already-clean markdown
    }
}
```

### 2. Una fuente de ruta registrada {#_2-a-registered-route-source}

Para rutas sin modelo, o para sustituir la salida del modelo, registra una fuente por nombre de ruta:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. La generación de respaldo {#_3-the-built-fallback}

Si un modelo de ruta con `HasSEO` no tiene `toSeoMarkdown()`, el middleware construye un documento básico con el **título** resuelto como H1, la **descripción** y **`getContentForSEO()`** del modelo:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning El contenido se sirve tal cual
El respaldo emite `getContentForSEO()` sin modificarlo. Si el contenido es HTML en lugar de Markdown, implementa `toSeoMarkdown()` para controlar la conversión. Desactiva por completo el respaldo con `seo.markdown_for_bots.build_from_content = false`.
:::

## Configuración {#configuration}

```php
// config/seo.php
'markdown_for_bots' => [
    'enabled'            => false,    // off by default; the middleware isn't registered until true
    'auto_register_middleware' => true,
    'serve_to_known_bots' => false,   // also serve to known AI crawlers by user-agent
    'query_param'        => 'format', // the ?format=md trigger
    'query_value'        => 'md',
    'build_from_content' => true,     // build from getContentForSEO() when no toSeoMarkdown()
],
```

Mantén `serve_to_known_bots` desactivado para negociar exclusivamente mediante la señal explícita `Accept` / `?format`. Actívalo para ofrecer también Markdown a GPTBot, ClaudeBot, PerplexityBot y los demás identificados por el [catálogo de rastreadores de IA](/es/guide/ai-crawlers), aunque no lo soliciten.

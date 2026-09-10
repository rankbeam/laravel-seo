---
description: "Eine Markdown-Fassung deiner Seiten per Content Negotiation an anfragende Clients ausliefern. Gewöhnliche Besucher erhalten unverändertes HTML. Kostenlos im Core, standardmäßig aus."
---

# Markdown für Bots {#markdown-for-bots}

Das HTML einer App umgibt den eigentlichen Inhalt mit Navigation, Scripts und Layout-Markup. Einige KI-Crawler und Antwortdienste akzeptieren eine einfachere Darstellung, wenn sie angeboten wird. Diese Funktion liefert deshalb eine **Markdown-Fassung** an Clients, die sie per Content Negotiation anfordern. Gewöhnliche Besucher erhalten weiterhin dein unverändertes HTML. Die optionale Funktion bietet ein weiteres Ausgabeformat; sie verspricht nicht, wie ein bestimmter Client es auswertet oder verwendet.

Sie ergänzt die [KI-Crawler-Steuerung](/de/guide/ai-crawlers): Dort legst du die Zugriffsregeln fest, hier die *auszuliefernden Inhalte* einer Anfrage.

Die Funktion gehört zum kostenlosen Core und ist **standardmäßig ausgeschaltet**.

## Funktionsweise {#how-it-works}

Bei Aktivierung wird eine Middleware für Content Negotiation registriert. Nach Erzeugung der normalen Antwort ersetzt sie diese **nur dann** durch Markdown, wenn beide Bedingungen erfüllt sind:

1. **Die Anfrage verlangt Markdown**, über einen ausdrücklichen Header `Accept: text/markdown`, den Query-Parameter `?format=md` oder optional einen bekannten KI-Crawler anhand seines User-Agents.
2. **Für die Route wird eine Markdown-Quelle aufgelöst.**

Andernfalls bleibt die Antwort unverändert. Gewöhnliche Browseranfragen sind nicht betroffen, und ersetzt werden nur erfolgreiche **HTML**-Antworten, niemals JSON, Weiterleitungen oder Downloads.

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Woher das Markdown stammt {#where-the-markdown-comes-from}

Die folgenden Quellen können Markdown für die erkannte Route liefern. Die Middleware versucht **zuerst eine registrierte Routenquelle**, dann an die Route gebundene Modelle. Bei jedem Modell hat eine explizite Methode `toSeoMarkdown()` Vorrang vor dem erzeugten Fallback. Liefert die Methode null oder einen leeren Inhalt, ist der Fallback für dieses Modell deaktiviert.

### 1. Markdown aus dem Modell {#_1-a-model-s-own-markdown}

Wenn keine registrierte Routenquelle Inhalt liefert, steuert ein routengebundenes Modell mit `toSeoMarkdown()` seine Ausgabe selbst. Implementiere den Vertrag `ProvidesSeoMarkdown` oder füge nur die Methode hinzu:

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

### 2. Eine registrierte Routenquelle {#_2-a-registered-route-source}

Registriere eine Quelle unter dem Routennamen, wenn die Route kein Modell hat oder du dessen Ausgabe überschreiben möchtest:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. Der erzeugte Fallback {#_3-the-built-fallback}

Hat ein routengebundenes `HasSEO`-Modell keine Methode `toSeoMarkdown()`, erstellt die Middleware ein einfaches Dokument aus dem aufgelösten **Titel** als H1, der **Beschreibung** und **`getContentForSEO()`** des Modells:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning Inhalte werden unverändert ausgegeben
Der Fallback gibt `getContentForSEO()` unverändert aus. Enthält es HTML statt Markdown, implementiere `toSeoMarkdown()`, um die Konvertierung zu steuern. Mit `seo.markdown_for_bots.build_from_content = false` kannst du den Fallback ganz abschalten.
:::

## Konfiguration {#configuration}

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

Lass `serve_to_known_bots` ausgeschaltet, wenn ausschließlich das ausdrückliche Signal `Accept` oder `?format` entscheiden soll. Bei Aktivierung erhalten auch GPTBot, ClaudeBot, PerplexityBot und weitere Einträge aus dem [KI-Crawler-Katalog](/de/guide/ai-crawlers) Markdown, selbst wenn sie es nicht anfordern. Die Erkennung erfolgt anhand des User-Agents.

---
description: "Bied AI-crawlers via content negotiation een overzichtelijke Markdown-versie van een pagina, terwijl gewone bezoekers de ongewijzigde HTML krijgen. Gratis in Core, standaard uit."
---

# Markdown voor bots {#markdown-for-bots}

De HTML van een applicatiepagina omringt de content met navigatie, scripts en
opmaakcode. Sommige AI-crawlers en antwoordmachines accepteren een overzichtelijkere
weergave als die beschikbaar is. Deze functie kan daarom een **Markdown-versie**
van een pagina aanbieden aan clients die daar via content negotiation om vragen,
terwijl gewone bezoekers je ongewijzigde HTML blijven krijgen. Je schakelt deze
compatibiliteitsoptie zelf in; ze belooft niets over hoe een bepaalde client het
resultaat verwerkt of gebruikt.

De functie werkt samen met [AI-crawlerbeheer](/nl/guide/ai-crawlers): dat bepaalt
het toegangsbeleid; deze functie bepaalt *welke* content tijdens de aanvraag wordt aangeboden.

Dit is een gratis Core-functie die **standaard uitstaat**.

## Hoe het werkt {#how-it-works}

Als je de functie inschakelt, wordt middleware voor content negotiation geregistreerd.
Nadat je normale response is gemaakt, vervangt de middleware die door Markdown
**alleen wanneer aan beide voorwaarden is voldaan**:

1. **De aanvraag vraagt om Markdown** — via een expliciete `Accept: text/markdown`-header,
   een `?format=md`-query of, als je dat inschakelt, een bekende AI-crawler herkend aan de user-agent.
2. **Er is een Markdown-bron beschikbaar voor de route.**

Anders blijft de response ongewijzigd. Een browser ondervindt dus geen gevolgen,
en alleen een geslaagde **HTML**-response wordt vervangen, nooit JSON, een
redirect of een download.

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Waar de Markdown vandaan komt {#where-the-markdown-comes-from}

De onderstaande bronnen kunnen Markdown voor de gevonden route leveren. De
middleware probeert **eerst een geregistreerde routebron** en daarna modellen die
aan de route zijn gebonden. Bij elk model heeft een expliciete methode
`toSeoMarkdown()` voorrang op de automatisch opgebouwde terugvalversie. Als die methode
null of een lege waarde teruggeeft, wordt de terugvalversie voor dat model uitgeschakeld.

### 1. De eigen Markdown van een model {#_1-a-model-s-own-markdown}

Als geen geregistreerde routebron content teruggeeft, bepaalt een aan de route
gebonden model met `toSeoMarkdown()` zijn eigen uitvoer. Implementeer het contract
`ProvidesSeoMarkdown` of voeg alleen de methode toe:

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

### 2. Een geregistreerde routebron {#_2-a-registered-route-source}

Voor routes zonder model, of om de uitvoer van het model te vervangen, registreer je een bron op routenaam:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. De automatisch opgebouwde terugvalversie {#_3-the-built-fallback}

Wanneer een aan de route gebonden `HasSEO`-model geen `toSeoMarkdown()` heeft, maakt
de middleware een eenvoudig document van de uiteindelijke **titel** (als H1),
de **beschrijving** en **`getContentForSEO()`** van het model:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning Content wordt ongewijzigd aangeboden
De terugvalversie neemt `getContentForSEO()` letterlijk over. Als je content HTML is in
plaats van Markdown, implementeer dan `toSeoMarkdown()` om de conversie zelf te bepalen.
Schakel de terugvalversie volledig uit met `seo.markdown_for_bots.build_from_content = false`.
:::

## Configuratie {#configuration}

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

Laat `serve_to_known_bots` uitgeschakeld om uitsluitend af te gaan op een expliciet
`Accept`- of `?format`-signaal. Schakel de optie in om ook Markdown aan
GPTBot, ClaudeBot, PerplexityBot en andere bots die via de
[AI-crawlercatalogus](/nl/guide/ai-crawlers) worden herkend te geven, zelfs als ze daar niet om vragen.

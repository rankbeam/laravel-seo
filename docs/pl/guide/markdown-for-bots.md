---
description: "Udostępniaj robotom AI przejrzystą reprezentację strony w Markdown przez negocjację treści, pozostawiając zwykłym odwiedzającym niezmieniony HTML. Bezpłatna funkcja rdzenia, domyślnie wyłączona."
---

# Markdown dla botów {#markdown-for-bots}

Strona HTML aplikacji otacza treść nawigacją, skryptami i znacznikami układu. Niektóre roboty AI i silniki odpowiedzi przyjmują prostszą reprezentację, jeśli jest dostępna, dlatego ta funkcja może udostępniać **reprezentację Markdown** klientom proszącym o nią przez negocjację treści — podczas gdy zwykli odwiedzający nadal otrzymują niezmieniony HTML. To opcjonalny wybór zgodności, a nie obietnica dotycząca sposobu parsowania lub wykorzystania wyniku przez konkretnego klienta.

Uzupełnia [kontrolę robotów AI](/pl/guide/ai-crawlers): tamta ustala zasady dostępu, a ta decyduje, *jaką* treść udostępnić podczas żądania.

To bezpłatna funkcja rdzenia, **domyślnie wyłączona**.

## Jak działa {#how-it-works}

Po włączeniu rejestrowane jest middleware negocjacji treści. Gdy powstanie zwykła odpowiedź, zastępuje ją Markdown **tylko wtedy, gdy oba warunki są spełnione**:

1. **Żądanie prosi o Markdown** — przez jawny nagłówek `Accept: text/markdown`, parametr zapytania `?format=md` lub (po włączeniu opcji) znanego robota AI rozpoznanego przez user-agent.
2. **Dla trasy dostępna jest treść ze źródła Markdown.**

W przeciwnym razie odpowiedź przechodzi bez zmian — przeglądarka nie odczuwa różnicy, a zastąpiona może być wyłącznie zakończona powodzeniem odpowiedź **HTML** (nigdy JSON, przekierowanie ani plik do pobrania).

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Skąd pochodzi Markdown {#where-the-markdown-comes-from}

Poniższe źródła mogą dostarczać Markdown dla dopasowanej trasy. Middleware najpierw próbuje **zarejestrowanego źródła trasy**, a potem modeli powiązanych z trasą. Dla każdego modelu jawna metoda `toSeoMarkdown()` ma pierwszeństwo przed wbudowaną wartością zastępczą; wynik null lub pusty z tej metody wyłącza wartość zastępczą dla tego modelu.

### 1. Własny Markdown modelu {#_1-a-model-s-own-markdown}

Gdy żadne zarejestrowane źródło trasy nie zwraca treści, model powiązany z trasą, który implementuje `toSeoMarkdown()`, kontroluje swój wynik (zaimplementuj kontrakt `ProvidesSeoMarkdown` lub po prostu dodaj metodę):

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

### 2. Zarejestrowane źródło trasy {#_2-a-registered-route-source}

Dla tras bez modelu lub aby nadpisać wynik modelu, zarejestruj źródło według nazwy trasy:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. Wbudowana wartość zastępcza {#_3-the-built-fallback}

Gdy model `HasSEO` powiązany z trasą nie ma `toSeoMarkdown()`, middleware buduje podstawowy dokument z rozstrzygniętego **tytułu** (jako H1), **opisu** i **`getContentForSEO()`** modelu:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning Treść jest udostępniana bez zmian
Mechanizm zastępczy zwraca treść z `getContentForSEO()` bez zmian. Jeśli treść jest w HTML zamiast Markdown, zaimplementuj `toSeoMarkdown()`, aby kontrolować konwersję. Wyłącz całkowicie wartość zastępczą przez `seo.markdown_for_bots.build_from_content = false`.
:::

## Konfiguracja {#configuration}

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

Pozostaw `serve_to_known_bots` wyłączone, aby negocjować wyłącznie na podstawie jawnego sygnału `Accept` / `?format`; włącz je, aby przekazywać Markdown także GPTBot, ClaudeBot, PerplexityBot i pozostałym (rozpoznawanym przez [katalog robotów AI](/pl/guide/ai-crawlers)), nawet gdy o niego nie proszą.

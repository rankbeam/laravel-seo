---
description: "Nabídněte AI robotům čistou reprezentaci stránky v Markdownu pomocí vyjednávání obsahu. Běžní návštěvníci dál dostávají nezměněné HTML. Zdarma v Core, standardně vypnuto."
---

# Markdown pro roboty {#markdown-for-bots}

HTML stránky aplikace obaluje obsah navigací, skripty a značkami layoutu. Někteří AI roboti a odpovědní systémy přijímají čistší reprezentaci, pokud je dostupná. Tato funkce proto může klientům, kteří o ni požádají při vyjednávání obsahu, poskytnout **reprezentaci stránky v Markdownu**, zatímco běžní návštěvníci dál dostávají vaše HTML beze změny. Jde o volitelnou možnost kompatibility, nikoli příslib, jak konkrétní klient výsledek zpracuje nebo použije.

Funkce doplňuje [řízení přístupu AI robotů](/cs/guide/ai-crawlers): to nastavuje pravidla přístupu, zatímco tato funkce rozhoduje, *jaký* obsah během požadavku poskytnout.

Jde o bezplatnou funkci balíčku Core a je **ve výchozím nastavení vypnutá**.

## Jak funguje {#how-it-works}

Po zapnutí se zaregistruje middleware pro vyjednávání obsahu. Po vytvoření běžné odpovědi ji nahradí Markdownem **jen při splnění obou podmínek**:

1. **Požadavek žádá o Markdown**: výslovnou hlavičkou `Accept: text/markdown`, parametrem dotazu `?format=md` nebo po volitelném zapnutí identifikací známého AI robota podle user-agentu.
2. **Pro trasu se podaří získat zdroj Markdownu.**

Jinak odpověď projde beze změny. Běžný prohlížeč tedy funkce neovlivní a nahrazuje se jen úspěšná odpověď **HTML**, nikdy JSON, přesměrování ani soubor ke stažení.

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Odkud Markdown pochází {#where-the-markdown-comes-from}

Níže uvedené zdroje mohou poskytnout Markdown pro nalezenou trasu. Middleware zkouší **nejprve registrovaný zdroj trasy**, potom modely navázané na trasu. U každého modelu má výslovná metoda `toSeoMarkdown()` přednost před vestavěnou náhradou. Pokud tato metoda vrátí null nebo prázdný výsledek, náhradní zdroj se pro daný model nepoužije.

### 1. Vlastní Markdown modelu {#_1-a-model-s-own-markdown}

Pokud žádný registrovaný zdroj trasy nevrátí obsah, řídí výstup model navázaný na trasu, který implementuje `toSeoMarkdown()`. Implementujte kontrakt `ProvidesSeoMarkdown` nebo jednoduše přidejte tuto metodu:

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

### 2. Registrovaný zdroj trasy {#_2-a-registered-route-source}

Pro trasy bez modelu nebo pro přepsání výstupu modelu zaregistrujte zdroj podle názvu trasy:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. Vestavěný náhradní zdroj {#_3-the-built-fallback}

Pokud model `HasSEO` navázaný na trasu nemá `toSeoMarkdown()`, middleware sestaví základní dokument z vyhodnoceného **titulku** jako H1, **popisu** a **`getContentForSEO()`** modelu:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning Obsah se poskytuje beze změny
Náhradní zdroj vypíše `getContentForSEO()` doslova. Pokud je obsah v HTML místo Markdownu, implementujte `toSeoMarkdown()` a převod řiďte sami. Náhradní zdroj úplně vypnete pomocí `seo.markdown_for_bots.build_from_content = false`.
:::

## Konfigurace {#configuration}

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

Ponechte `serve_to_known_bots` vypnuté, pokud chcete vyjednávat pouze podle výslovného signálu `Accept` / `?format`. Zapněte je, pokud chcete Markdown poskytovat také robotům GPTBot, ClaudeBot, PerplexityBot a ostatním identifikovaným pomocí [katalogu AI robotů](/cs/guide/ai-crawlers), i když o něj nepožádají.

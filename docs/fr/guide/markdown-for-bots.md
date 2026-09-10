---
description: "Proposez une représentation Markdown par négociation de contenu aux clients qui la demandent, tout en conservant le HTML habituel. Fonction gratuite du Core, désactivée par défaut."
---

# Markdown pour les robots {#markdown-for-bots}

Une page HTML entoure son contenu de navigation, de scripts et de balisage de mise en page. Certains robots et moteurs de réponse acceptent une représentation plus simple lorsqu'elle est proposée. Cette fonction peut servir une **représentation Markdown** aux clients qui la demandent par négociation de contenu, tout en laissant les visiteurs habituels recevoir le HTML inchangé. C'est un choix facultatif de compatibilité, pas une garantie sur la manière dont un client précis analysera ou utilisera le résultat.

Elle complète le [contrôle des robots IA](/fr/guide/ai-crawlers), qui définit la politique d'accès. Ici, il s'agit du contenu à renvoyer pendant la requête.

Cette fonction appartient au Core gratuit et est **désactivée par défaut**.

## Fonctionnement {#how-it-works}

Une fois activée, elle enregistre un middleware de négociation de contenu. Après la production de la réponse habituelle, il la remplace par du Markdown **si les deux conditions suivantes sont remplies** :

1. **La requête demande du Markdown** : en-tête explicite `Accept: text/markdown`, paramètre `?format=md` ou, sur activation séparée, user-agent reconnu comme robot IA.
2. **Une source Markdown est disponible pour la route.**

Sinon, la réponse reste inchangée. Un navigateur ordinaire qui ne demande pas cette représentation conserve le HTML. Seules les réponses **HTML réussies** peuvent être remplacées, jamais du JSON, une redirection ou un téléchargement.

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Origine du Markdown {#where-the-markdown-comes-from}

Les sources ci-dessous peuvent fournir du Markdown pour la route correspondante. Le middleware essaie **d'abord une source enregistrée pour la route**, puis les modèles liés à la route. Pour chaque modèle, une méthode explicite `toSeoMarkdown()` prime sur le repli intégré. Si cette méthode renvoie null ou un résultat vide, le repli est désactivé pour ce modèle.

### 1. Le Markdown du modèle {#_1-a-model-s-own-markdown}

Si aucune source de route enregistrée ne renvoie de contenu, un modèle lié à la route peut contrôler sa sortie avec `toSeoMarkdown()`. Implémentez le contrat `ProvidesSeoMarkdown` ou ajoutez simplement la méthode :

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

### 2. Une source enregistrée pour la route {#_2-a-registered-route-source}

Pour les routes sans modèle, ou pour remplacer la sortie du modèle, enregistrez une source par nom de route :

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. Le repli intégré {#_3-the-built-fallback}

Si un modèle `HasSEO` lié à la route n'a pas de méthode `toSeoMarkdown()`, le middleware construit un document simple à partir du **titre** résolu sous forme de H1, de la **description** et du **`getContentForSEO()`** du modèle :

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning Le contenu est servi tel quel
Le repli produit `getContentForSEO()` sans conversion. Si le contenu est du HTML, implémentez `toSeoMarkdown()` pour maîtriser sa conversion. Pour désactiver entièrement ce repli, mettez `seo.markdown_for_bots.build_from_content = false`.
:::

## Configuration {#configuration}

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

Laissez `serve_to_known_bots` désactivé pour vous limiter aux demandes explicites `Accept` ou `?format`. Activez-le pour fournir aussi du Markdown à GPTBot, ClaudeBot, PerplexityBot et aux autres robots reconnus par le [catalogue](/fr/guide/ai-crawlers), même sans demande explicite.

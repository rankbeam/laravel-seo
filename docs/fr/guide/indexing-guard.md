---
description: "Liez les directives d'indexation à l'environnement Laravel : hors de la liste autorisée, les pages reçoivent noindex et le robots.txt géré interdit l'exploration."
---

# Protection contre l'indexation hors production {#indexing-guard-non-production-safety-net}

Une copie locale ou de staging peut apparaître dans les résultats de recherche si un `noindex` n'existe que dans un `.env` oublié ou si un déploiement remplace les règles robots. Elle expose alors un environnement qui ne devrait pas être indexé et peut présenter les mêmes contenus que le site public. Sa suppression de l'index peut prendre du temps.

La **protection contre l'indexation** lie les directives à l'*environnement* Laravel. Quand l'application tourne hors de la liste autorisée, chaque page reçoit `noindex,nofollow`, le `robots.txt` géré interdit l'exploration à tous les robots et `seo:audit` signale cet état. Ces directives ne remplacent pas un contrôle d'accès à un environnement privé.

Cette fonction appartient au Core gratuit.

## Effets lorsque la protection est active {#what-it-does-when-active}

Si la protection est activée et que `app()->environment()` **ne figure pas** dans `seo.indexing_guard.allowed_environments`, quatre changements s'appliquent automatiquement :

1. **Le résolveur impose `noindex,nofollow` à chaque page.** Cette règle se situe *au-dessus* de toute la [chaîne de priorité](/fr/concepts/resolver-precedence), même d'une valeur `robots` explicite enregistrée dans `seo_meta`.
2. **Un en-tête HTTP `X-Robots-Tag: noindex,nofollow`** est ajouté aux réponses qui passent par l'application. Voir [réponses non HTML](#non-html-responses-pdfs-feeds-images).
3. **`SEO::robotsTxt()->build()` produit un `robots.txt` qui interdit toute exploration**, ainsi qu'un `ai.txt`, avec `User-agent: *` et `Disallow: /`. Cela couvre la commande `seo:robots-txt` et la [route dynamique](/fr/guide/ai-crawlers) facultative.
4. **`seo:audit` affiche une bannière visible**, pour expliquer immédiatement pourquoi toutes les pages sont en noindex.

Dans les environnements autorisés, `production` par défaut, la protection est entièrement **inactive**. La sortie reste identique octet pour octet.

## Réponses non HTML : PDF, flux et images {#non-html-responses-pdfs-feeds-images}

La **balise meta** robots ne concerne que les clients qui analysent du HTML. Un PDF, un flux RSS/Atom, une image ou une autre réponse non HTML n'a pas de `<head>`. Quand la protection est active, un middleware global ajoute donc la même directive dans un en-tête HTTP :

```http
X-Robots-Tag: noindex,nofollow
```

L'en-tête et la balise meta partagent la même source. L'en-tête est **activé par défaut à l'intérieur de la protection**, qui reste elle-même facultative et inactive dans les environnements autorisés. Désactivez-le pour conserver uniquement la balise meta :

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Le middleware est enregistré **seulement si la protection est activée**. Sinon, le package n'ajoute rien à la pile de middlewares.

::: warning Les fichiers statiques ne passent pas par PHP
Un fichier que le serveur web renvoie directement depuis `public/` n'entre pas dans Laravel et ne reçoit pas cet en-tête. Protégez ces fichiers au niveau du serveur web ou du CDN. Le middleware couvre les réponses qui passent par l'application.
:::

## Pourquoi une valeur robots explicite est remplacée {#why-it-overrides-an-explicit-robots-value}

Ailleurs dans Rankbeam, une valeur explicitement enregistrée l'emporte. La protection constitue une exception volontaire au-dessus de cette couche :

- Une base de staging est généralement une copie de la production. Une page avec `index,follow` demanderait alors aussi son indexation sur le staging.
- **L'indexation accidentelle du staging est indésirable ; `noindex` correspond à l'intention de cet environnement.** Dans les environnements où vous ne voulez jamais d'indexation, une valeur enregistrée ne peut donc pas contourner la protection.

## Activation {#enabling-it}

La protection est **désactivée à l'installation**. Installer ou mettre à jour le package ne modifie donc pas le rendu hors production sans activation explicite, comme pour [`blank_is_unset`](/fr/concepts/resolver-precedence) et la génération d'images OG. Activez-la avec :

```dotenv
SEO_INDEXING_GUARD=true
```

Avec la liste par défaut, `production` reste inchangé. Vous pouvez donc garder la protection activée dans une configuration partagée. Vérifiez que la liste contient chaque environnement que vous souhaitez indexer. Cette activation est recommandée ; une activation par défaut est envisagée pour Core 4.

Pour la désactiver :

```dotenv
SEO_INDEXING_GUARD=false
```

## Choisir les environnements indexables {#choosing-which-environments-may-index}

Seul `production` est autorisé par défaut. Remplacez la liste avec une variable d'environnement contenant des valeurs séparées par des virgules :

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

Ou dans `config/seo.php` :

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

La comparaison utilise `Str::is()`. Les **jokers** sont donc acceptés : `'prod*'` correspond à `production` et `prod-eu`.

```php
'allowed_environments' => ['prod*'],
```

Une liste **vide** n'autorise *aucun* environnement : la protection s'applique partout. En revanche, une variable `SEO_INDEXING_GUARD_ALLOWED` vide ou composée d'espaces revient à `['production']`, pour qu'une valeur vide ne désindexe pas silencieusement la production. Écrivez explicitement `[]` dans la configuration si vous voulez activer la protection partout.

## Vérification {#verifying-it}

`seo:audit` affiche la bannière et inclut un état exploitable par machine avec `--json` :

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

Le `robots.txt` servi ou généré dans un environnement protégé contient :

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Portée {#scope}

La protection contrôle les **directives d'indexation** : balise meta `robots`, en-tête `X-Robots-Tag` et `robots.txt`. Elle ne modifie ni titres, ni descriptions, ni canonicals, ni schémas. Elle est indépendante de la [politique de rendu robots](/fr/concepts/resolver-precedence) `seo.robots.emit_default` : puisque `noindex,nofollow` diffère de la valeur par défaut du site, la balise est affichée.

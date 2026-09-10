---
description: "seo:explain indique quelle couche du résolveur a fixé chaque champ SEO et quelles valeurs elle a remplacées. Lecture seule, sans réseau ni licence."
---

# Expliquer la résolution avec `seo:explain` {#explain-the-resolution-seo-explain}

Rankbeam résout les données SEO d'une page selon une [chaîne de priorité](/fr/concepts/resolver-precedence) : configuration, valeurs en base de données globales/par type de modèle/par route, valeurs calculées du modèle, puis valeurs explicites de `seo_meta`. Viennent ensuite les traitements de suffixe du titre, canonical, conversion des images en URL absolues et la [protection contre l'indexation](/fr/guide/indexing-guard). Si le `<title>` ou la balise `robots` ne contient pas la valeur attendue, **`seo:explain` indique quelle couche a fixé chaque champ et quelles valeurs elle a remplacées**.

La commande fonctionne en lecture seule, sans réseau ni licence. Elle ne réimplémente pas la fusion : l'attribution vient des contributions de couches du résolveur lui-même et les valeurs finales viennent du véritable résolveur. L'explication suit donc le même calcul que le rendu.

## Utilisation {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

Le modèle doit utiliser le trait [`HasSEO`](/fr/guide/quickstart).

## Lire la sortie {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by** : la couche prioritaire ayant fourni une valeur non nulle. Le résultat indique `post-processing` si aucune couche n'a fixé le champ mais qu'une valeur a été *dérivée*, par exemple un canonical depuis l'URL de la requête ou du modèle, un og:url depuis le canonical, ou une URL d'image rendue absolue.
- **Overrode** : toutes les couches moins prioritaires qui proposaient une valeur, dans l'ordre, pour identifier les valeurs masquées.
- **↳ notes** : les traitements ayant modifié la valeur après la fusion : suffixe du titre, suppression des paramètres du canonical, dérivation de og:url, conversion des images en URL absolues et protection contre l'indexation qui impose `noindex` au-dessus de toutes les couches.

::: tip og:type et twitter:card
Ces deux champs ont des valeurs par défaut non nulles, `website` et `summary_large_image`. La couche la plus prioritaire qui les définit, généralement `computed`, l'emporte donc sur `config`. Une page sans ligne `seo_meta` enregistrée ne fournit aucune valeur pour ces champs. Un `og:type` calculé comme `article` n'est ainsi pas masqué par une simple valeur `website`. La sortie suit la fusion réelle du résolveur.
:::

## Résolution au niveau du site {#site-level-resolution}

`seo:explain` affiche aussi les sources des valeurs globales décrites dans la [priorité du résolveur](/fr/concepts/resolver-precedence) : **le host du canonical, le nom du site et la langue par défaut**.

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Vérifiez particulièrement le host du canonical. Un `localhost` resté dans la configuration, un `http://` sur un site HTTPS ou une URL d'application différente de celle du modèle peuvent expliquer un canonical censé pointer vers la page elle-même mais incorrect.

## Sortie JSON {#json-output}

`--json` produit la trace complète pour vos outils ou la CI : `target`, puis pour chaque champ `winner`, `losers`, `final` et `notes`, ainsi que les sources `site_level` :

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## Voir aussi {#see-also}

- [Priorité du résolveur](/fr/concepts/resolver-precedence) : la chaîne complète suivie par `seo:explain`.
- [Audit SEO gratuit](/fr/guide/audit) : `seo:audit` repère les problèmes ; `seo:explain` explique l'origine d'une valeur.

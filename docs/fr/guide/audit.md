---
description: "Vérifiez les métadonnées SEO avec seo:audit : un tableau par page, gratuitement, sans file d'attente, licence ni accès réseau."
---

# Audit SEO gratuit (`seo:audit`)

`php artisan seo:audit` répond à une question : **quels problèmes SEO mes pages présentent-elles maintenant ?** La commande parcourt les modèles `HasSEO` dans le processus courant, **sans file d'attente, licence ni réseau**, et affiche un état **pass / warn / fail** par page, suivi d'un récapitulatif.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## Vérifications effectuées {#what-it-checks}

L'audit exécute seulement la classe **metadata** : les vérifications qui dépendent du modèle et du [résolveur](/fr/concepts/resolver-precedence), sans télécharger la page.

| Vérification | Codes |
|---|---|
| Présence du titre et de la description, valeurs de repli comprises | `missing_title`, `missing_description` |
| Présence de l'image OG, valeurs de repli comprises | `missing_og_image` |
| Longueur du titre et de la description | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Titres et descriptions identiques sur plusieurs pages | `duplicate_title`, `duplicate_description` |
| Directives robots contradictoires et noindex à vérifier | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Canonical : format, autre domaine, URL partagée ou non sécurisée | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Préparation aux réponses (AEO) : données structurées d'article | `aeo_missing_author`, `aeo_article_missing_date` |
| Mot-clé cible présent, après activation | `missing_focus_keyword` |
| Variantes hreflang, dans le Core et si la page en déclare | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

Les codes ont le même sens que dans l'analyse Pro. Les codes hreflang de ce tableau et `blank_explicit_override` sont propres au Core. Les longueurs suivent le [budget par écriture](/fr/guide/multilingual#title-and-description-budgets-per-script) : 60/160 graphèmes pour les textes latins, environ 30/80 pour CJK. L'audit mesure la **valeur résolue, suffixe compris**. L'[éditeur Filament](/fr/guide/filament) lit la même politique, même s'il peut aussi afficher un texte pas encore enregistré.

Les vérifications hreflang utilisent la liste après application de `seo.hreflang`, comme les balises et le sitemap. Les références réciproques nécessitent un crawl, effectué par Pro.

Les vérifications **AEO** s'appliquent seulement à un article JSON-LD (`Article`, `BlogPosting`, `NewsArticle`, …) auquel manque une entité `author` ou une date `datePublished` / `dateModified`. Elles examinent la provenance et la chronologie explicites du document. Une page sans article déclaré ne reçoit pas ces remarques. Elles restent consultatives, au niveau `notice`, et n'entrent pas dans le score Pro de 0 à 100.

## Vérifications exclues — limites de la commande {#what-it-does-not-check-—-the-capability-boundary}

Un audit local des métadonnées ne couvre pas toute l'analyse Pro. Chaque exécution rappelle qu'elle ne produit pas :

- **Les vérifications du HTML servi :** `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content` et `mixed_content` demandent le contenu réel de la page.
- **Les vérifications réseau du canonical :** `canonical_target_broken` / `_redirect` / `_noindex` nécessitent un appel sortant protégé.
- **Le score numérique de 0 à 100 :** Pro l'enregistre avec une grille versionnée dans le résultat d'analyse ; voir [score SEO (EN)](/pro/scoring).

Ces fonctions appartiennent à **Pro**. La [liste complète des problèmes (EN)](/pro/scan-issues) détaille leur couverture.

## Choisir les modèles {#choosing-what-to-audit}

La commande lit `seo.audit.models`, avec repli sur `seo.sitemap.models` :

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Vous pouvez fournir les modèles explicitement :

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Options {#options}

| Option | Effet |
|---|---|
| `--model=` | Classe `HasSEO` à examiner ; répétable, remplace la configuration. |
| `--locale=` | Langue de résolution des données ; langue de l'application par défaut. |
| `--limit=` | Nombre maximal d'enregistrements par modèle ; `0` signifie tous. |
| `--issues-only` | Afficher seulement les pages ayant au moins un problème. |
| `--strict` | Renvoyer un code de sortie non nul dès qu'un problème existe, pour la CI. |
| `--json` | Produire du JSON avec pages, résumé et couverture, au lieu du tableau. |

### Contrôle en CI {#ci-gate}

`--strict` transforme l'audit en contrôle de build :

```bash
php artisan seo:audit --strict
```

La commande renvoie `1` si une page a un avertissement ou un échec, et `0` si toutes les pages examinées réussissent.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Mots-clés cibles {#focus-keywords}

La remarque `missing_focus_keyword` est **désactivée par défaut**. Activez le workflow des mots-clés pour la recevoir :

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Pro lit la même option. Audit, analyse et éditeur suivent donc la même activation. Définissez les mots-clés dans le [champ Filament](/fr/guide/filament) ou avec `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Expliquer une valeur inattendue avec `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` montre **le problème**. [`seo:explain` (EN)](/guide/explain) explique **l'origine de la valeur** : configuration, défaut, calcul ou saisie explicite ; valeurs remplacées ; traitements ultérieurs comme le suffixe, le nettoyage du canonical ou la protection contre l'indexation. Utilisez-le lorsqu'un résultat ou une balise vous surprend :

```bash
php artisan seo:explain "App\Models\Post" 42
```

---
description: "Produisez un graphe JSON-LD dont les nœuds Organization, WebSite, WebPage et Article se relient par des @id stables."
---

# Graphe de schéma JSON-LD {#schema-graph-json-ld}

Des références explicites entre les nœuds JSON-LD décrivent leurs relations : l'Organization publie le WebSite, le WebSite contient la WebPage et la WebPage présente l'Article. `SchemaGraph` produit cet ensemble de nœuds reliés par des **valeurs `@id` stables**, pour un graphe cohérent sur chaque page.

## Le graphe de la page {#the-page-graph}

```php
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\Schema\SchemaCollection;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

$seo = SEO::resolve($post);

$graph = new SchemaGraph();

$schemas = SchemaCollection::make()
    ->add($graph->organization())   // @id: {app_url}#organization
    ->add($graph->webSite())        // @id: {app_url}#website, publisher → #organization
    ->add($graph->webPage($seo));   // @id: {page_url}#webpage, isPartOf → #website
```

Affichez-le dans Blade, dans le head ou le body :

```blade
{!! $schemas->toScript() !!}
```

Les données Organization et WebSite proviennent de `config/seo.php`, sous `schema.organization` et `schema.website`. Le nœud WebPage est rempli à partir du `SEOData` résolu.

WebPage reçoit `inLanguage` à partir de la langue résolue de la page, au format BCP 47 (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` utilise la langue enregistrée dans `seo_meta`. WebSite liste les langues du site depuis `schema.website.inLanguage`, qui accepte un code ou une liste. Mettez `schema.in_language` à `false` pour ne produire aucun `inLanguage`. Voir [contenu multilingue](/fr/guide/multilingual#inlanguage-in-the-schema-graph).

## Builders typés {#typed-builders}

Des builders couvrent les types courants de données structurées :

| Builder | Fonction |
|---|---|
| `ArticleSchema::fromModel($post)` | Dates, auteur et éditeur à partir du modèle et de la configuration |
| `ProductSchema` | Offres, prix et disponibilité |
| `BreadcrumbSchema::fromArray([...])` | Paires nom/URL ordonnées |
| `BreadcrumbSchema::fromModelAncestors($page)` | Parcours d'une chaîne `parent`, avec protection contre les boucles |
| `FAQSchema` | Paires question/réponse |
| `LocalBusinessSchema` | Adresse, coordonnées géographiques et horaires |
| `OrganizationSchema` | Nœud Organization indépendant |

Exemple complet pour une page d'article :

```php
$article = ArticleSchema::fromModel($post)
    ->setPublisherOrganization(config('seo.schema.publisher.name'));

$schemas = SchemaCollection::make()
    ->add($graph->organization())
    ->add($graph->webSite())
    ->add($graph->webPage($seo))
    ->add($article->toArray())
    ->add(BreadcrumbSchema::fromArray([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog'],
        ['name' => $post->title, 'url' => "/blog/{$post->slug}"],
    ])->toArray());
```

## Schéma associé et `@seoSchema` {#attached-schema-and-seoschema}

Un schéma présent dans le `SEOData` résolu, par exemple enregistré avec les métadonnées explicites, est rendu par `@seoSchema` ou dans la section `script` de `SEO::toArray()` :

```blade
@seoSchema($post)
```

Les rédacteurs peuvent remplir `seo_meta.schema_jsonld` sans code grâce à la section facultative **Structured data** du package de [champs Filament](/fr/guide/filament#structured-data-schema-org). Elle propose un fil d'Ariane automatique et des blocs FAQ/Product, validés par `SchemaValidator` avant l'enregistrement.

## Échappement {#escaping}

Toutes les sorties JSON-LD, dont `SchemaCollection::toScript()`, `toJson()` et les chemins de rendu, utilisent `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`. Une séquence `</script>` dans un titre ou un contenu ne peut donc pas fermer l'élément script. Ne contournez pas cette protection en appliquant vous-même `json_encode` aux tableaux de schéma.

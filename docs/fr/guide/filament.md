---
description: "Ajoutez une section SEO aux formulaires de ressources Filament 4 ou 5 avec laravel-seo-filament et le trait HasSEO."
---

# Champs d'administration Filament {#filament-admin-fields}

Le paquet gratuit [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) ajoute une section SEO aux formulaires de ressources, avec **deux lignes par ressource**. Il prend en charge Filament **4.x et 5.x**, avec Livewire 3 et 4. La modification des métadonnées est gratuite ; les analyses et le score visible dans l'exemple viennent de Pro.

## Prérequis {#prerequisites}

Utilisez un panel Filament 4 ou 5 existant et un modèle avec le trait `HasSEO` du Core. Terminez le [démarrage rapide](/fr/guide/quickstart), migrations et rendu compris, avant d'ajouter l'éditeur.

## Installation {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

Le modèle associé à la ressource doit utiliser `HasSEO`.

## Ajouter la section à une ressource {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## Vérifier l'enregistrement {#check-the-saved-result}

Ouvrez un enregistrement existant, saisissez une description SEO, enregistrez puis rechargez le formulaire. La valeur doit persister, apparaître dans l'aperçu et porter la source **Manual**. Vérifiez le `<head>` public pour confirmer que la même description est servie aux visiteurs.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="Champs SEO de la démo Merchant : titre, description, canonical, image sociale, aperçu de recherche et origine des valeurs." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Exemple de la démo Merchant, interface en anglais. Les champs reprennent le thème du panel ; les contrôles et budgets dépendent de la version installée et de sa configuration.*

La section comprend :

- **Titre et description** avec compteurs. La [politique de longueur](/fr/guide/multilingual#title-and-description-budgets-per-script) tient compte de l'écriture : 60/160 pour les textes latins, environ 30/80 pour CJK, en graphèmes.
- **Mots-clés cibles :** saisie sous forme de tags, enregistrés en `[{keyword, is_primary}]`. Le premier est principal ; `getPrimaryKeyword()` et `SEOData` lisent cette structure. Activez `seo.keywords.enabled` pour que [`seo:audit`](/fr/guide/audit) et Pro signalent les mots-clés manquants. Cette option est désactivée par défaut ; voir [configuration](/fr/reference/configuration#focus-keywords).
- **URL canonique :** vide pour une URL automatique, dont les paramètres de requête sont retirés.
- **Robots :** vide pour la valeur du site.
- **Image sociale :** téléversement pour `og:image` et `twitter:image`, stocké sous `seo/` sur le disque par défaut de Filament.
- **Aperçu de recherche :** suit les valeurs de repli du résolveur pendant la saisie.
- **Origine des valeurs :** saisie manuelle, contenu, type de modèle, valeur globale, configuration du site ou URL.

## Limiter les champs {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

Vous pouvez sélectionner un sous-ensemble de `title`, `description`, `focus_keywords`, `canonical`, `robots` et `og_image`. Sans le trait, `SEOFields::make(?array $only)` renvoie directement la même section.

## Persistance des valeurs {#how-values-persist}

La section se lie au groupe d'état `seo_meta` et enregistre via la relation `seoMeta()` du Core, par mise à jour ou création. Aucune colonne n'est ajoutée à vos tables de contenu. Ces valeurs deviennent le niveau 6, explicite, du [résolveur](/fr/concepts/resolver-precedence).

## Plusieurs langues {#several-languages}

Le Core conserve [une ligne `seo_meta` par modèle et langue](/fr/guide/multilingual). Depuis Filament 1.9, fournissez les langues publiées pour obtenir un onglet par langue :

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

Ou configurez-les une fois pour toutes les ressources :

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

Chaque onglet modifie sa propre ligne et possède :

- ses compteurs, selon l'écriture de la langue : `0 / 30` pour un titre japonais vide, `0 / 60` pour l'anglais ;
- son aperçu de recherche et de partage à partir des valeurs résolues de cette langue ;
- ses indications de valeurs de repli ;
- un badge comptant les champs renseignés, pour repérer les versions vides.

Avec `ext-intl`, le nom de la langue est traduit dans la langue du panel ; sinon, son code est affiché. Tous les onglets sont validés et enregistrés ensemble. Une langue entièrement vide ne crée pas de ligne factice.

::: details Chemins d'état personnalisés
Avec plusieurs langues, le chemin est `seo_meta.{locale}.title`. Avec une seule, il reste `seo_meta.title`. Utilisez le bon chemin dans vos actions personnalisées.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Onglets anglais, italien et japonais dans Merchant : budgets japonais de 30 et 80 graphèmes, avec une description non renseignée." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Démo Merchant, 9 septembre 2026, avec `locales: ['en', 'it', 'ja']`. L'onglet japonais vide utilise ses propres compteurs. Le titre anglais vient ici du contenu de repli du modèle : ajouter un onglet ne traduit pas le contenu. Le score Pro est le dernier résultat d'analyse de l'enregistrement, pas un score propre à chaque onglet.*

### Avec un plugin de traduction {#with-a-translatable-plugin}

Avec `lara-zeus/spatie-translatable` **1.x sur Filament 4** ou **2.x sur Filament 5**, utilisez les adaptateurs Rankbeam pour les pages Edit et Create. Remplacez seulement les imports des traits de page. Conservez les traits de ressource et de liste du plugin, son intégration au panel et l'action `LocaleSwitcher` :

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

Chaque classe continue à déclarer `use Translatable;`. Le plugin reste une dépendance facultative de l'application. Utilisez sa dernière version corrigée ; la fixture locale couvre 1.0.4 avec Filament 4.13.1 et 2.0.1 avec Filament 5.8.1.

Changer de langue conserve les brouillons du contenu parent, des métadonnées SEO et des données structurées. L'enregistrement valide toutes les langues visitées, puis les sauvegarde dans une transaction. Une erreur de validation ouvre la langue concernée. Les fichiers sont stockés lors de l'enregistrement ; quitter ou recharger la page supprime les brouillons non enregistrés. Enregistrer ne traduit pas les contenus manquants.

Les adaptateurs conservent les hooks avant/après et les mutateurs de formulaire. Si vous redéfinissez `handleRecordCreation()`, `handleRecordUpdate()`, `callHook()` ou les méthodes de transaction, intégrez le comportement des adaptateurs à cette personnalisation et testez le parcours. Une transaction de base de données n'annule pas les écritures de fichiers : conservez votre nettoyage habituel des fichiers orphelins.

Pour des champs texte personnalisés sous Livewire 3, préférez `->live()` ou `->live(onBlur: true)` à un délai explicite : celui-ci retarde l'état local et peut perdre les dernières frappes lors d'un changement rapide de langue. Les champs titre et description de Rankbeam utilisent le délai de requête par défaut.

Les traits de page du plugin seul remplissent à nouveau le formulaire au changement de langue. Rankbeam protège contre leurs écritures accidentelles de métadonnées, mais ils ne conservent pas les brouillons SEO. Migrez Edit/Create vers les adaptateurs. Les onglets explicites `locales:` restent un éditeur partagé et ont priorité sur le sélecteur de page.

Sans liste explicite ni langue de page, la section utilise la langue de l'application.

## Données structurées, schema.org {#structured-data-schema-org}

Une section facultative permet aux rédacteurs de joindre des données JSON-LD. Ajoutez-la à côté de la section SEO :

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

Ou utilisez directement `SEOSchemaFields::make()` sans trait. La section écrit dans `seo_meta.schema_jsonld`, également lu par le [moteur de schémas](/fr/guide/schema). Elle ne fait que relier l'interface au Core : ses builders produisent les documents et `SchemaValidator` les valide avant l'enregistrement.

Elle propose :

- **Fil d'Ariane automatique :** un interrupteur construit `BreadcrumbList` depuis les ancêtres du modèle avec `BreadcrumbSchema::fromModelAncestors()`. Aucun contenu supplémentaire à saisir.
- **Blocs de schéma :** un répéteur pour les FAQ, avec questions et réponses, et les produits, avec nom, description, image, marque, SKU, prix, devise et disponibilité. Le Core utilise `FAQSchema` et `ProductSchema`.

### Validation {#validation}

Un bloc produisant du JSON-LD invalide est refusé à l'enregistrement avec le message du validateur : par exemple une FAQ sans réponse ou un produit sans image ou offre exigée par ce builder. Les blocs entièrement vides sont ignorés.

### Données enregistrées {#what-it-stores}

`schema_jsonld` contient un objet pour un seul document, ou un tableau JSON pour plusieurs : fil d'Ariane d'abord, puis les blocs. Les deux formes sont rendues sans modification par `@seo` et `renderSchema()`.

### Schémas non gérés par l'éditeur {#schema-it-doesn-t-manage}

Les schémas que le formulaire ne peut pas représenter restent inchangés : `@graph` écrit à la main, `@type` particulier ou produit avec avis, notes, GTIN/MPN. Ouvrir et enregistrer le formulaire ne les remplace pas.

## Dépannage {#troubleshooting}

- **Un champ enregistré manque sur la page :** vérifiez que `@seo($model)` utilise le même enregistrement et la même langue.
- **Une valeur de repli reste affichée :** vérifiez la valeur explicite de la langue active. L'indicateur de source montre le niveau retenu.
- **Un onglet manque :** vérifiez `locales:`, la configuration et le sélecteur de page selon l'ordre de priorité décrit plus haut.

::: details Tester un panel personnalisé avec Testbench
Dans orchestra/testbench, enregistrez le `SupportServiceProvider` de Filament avant `LivewireServiceProvider`. Filament remplace le `DataStore` de Livewire ; l'ordre inverse provoque `ViewErrorBag::put(): ... null given`. La découverte automatique ordonne correctement les fournisseurs dans une application normale.
:::

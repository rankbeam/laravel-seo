---
description: "Le résolveur SEO fusionne six niveaux : les valeurs prioritaires l'emportent et null n'efface jamais une valeur d'un niveau inférieur."
---

# Priorité du résolveur {#resolver-precedence}

Chaque valeur SEO effective — titre, description, canonical, robots et images — provient de la fusion de **six niveaux** par `SEOResolver`. Les niveaux supérieurs l'emportent. `null` ne remplace jamais une valeur provenant d'un niveau inférieur.

## Les six niveaux {#the-six-layers}

Du moins prioritaire au plus prioritaire :

| Nº | Niveau | Source | Usage courant |
|---|---|---|---|
| 1 | **Configuration du site** | `config/seo.php` : `site_name`, `title_suffix`, `default_og_image`, `default_robots`, … | Valeurs de marque communes |
| 2 | **Valeurs globales en base** | Lignes de `seo_defaults` sans type de modèle | Modifier les valeurs du site sans déploiement |
| 3 | **Valeurs par type de modèle** | Lignes de `seo_defaults` liées à une classe | Image OG commune à tous les produits |
| 4 | **Valeurs par route** | Lignes de `seo_defaults` liées à un nom de route | Pages statiques `home` ou `contact`, sans modèle |
| 5 | **Valeurs calculées** | Attributs du modèle | Titre issu de `title`, description issue de `excerpt` ou `body` |
| 6 | **Valeurs explicites** | Ligne `seo_meta` du modèle, via `saveSEO()` | Valeurs saisies par les rédacteurs |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

Le résultat est un objet-valeur immuable `SEOData`, utilisé par tous les moteurs de rendu : Blade, tableau et Inertia.

## Valeurs de repli calculées, niveau 5 {#computed-fallbacks-layer-5}

Sans valeur explicite, le résolveur dérive les données du modèle :

- **Titre :** attribut `title` ou `name`.
- **Description :** premier attribut contenant du texte utile dans `seo.computed.description_fields`. L'ordre par défaut est `excerpt`, `summary`, `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`, `article`. Le HTML est retiré, les entités décodées et le texte tronqué à une limite de mot selon `seo.computed.description_max_length`, par défaut 160, sans points de suspension.
- **Robots :** hook `getSEORobots()` ou attribut `is_indexable`, décrit ci-dessous.
- **Valeurs issues de l'URL :** canonical et `og:url` issus de `getUrlForSEO()`.

## Robots et indexabilité {#controlling-robots-and-indexability}

Le Core prend en charge `noindex` par modèle. `HasSEO` ne déclare pas la méthode robots, car elle reste facultative. Le résolveur reconnaît néanmoins ces trois sources, dans l'ordre :

| Priorité | Source | Exemple |
|---|---|---|
| 1 | **Valeur explicite `seo_meta.robots`** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | **Hook `getSEORobots(): ?string`** du modèle | Renvoyer `'noindex, nofollow'`, ou `null` pour continuer au niveau suivant |
| 3 | **Attribut `is_indexable`**, colonne ou accesseur | Faux ⇒ `noindex, nofollow` ; vrai ⇒ `index, follow` |

```php
class Page extends Model
{
    use HasSEO;

    // Option A: let the resolver derive robots from a boolean flag.
    //   Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));

    // Option B: compute it from your own state.
    public function getSEORobots(): ?string
    {
        return $this->status === 'draft' ? 'noindex, nofollow' : null;
    }
}

// Option C: set it explicitly per page (wins over A and B).
$page->saveSEO(['robots' => 'noindex, follow']);
```

### Balises effectivement rendues {#what-actually-renders}

Une politique d'émission filtre la directive résolue avant `<head>`. La balise `<meta name="robots">` est émise **uniquement si la directive diffère de `default_robots`**, dont la valeur initiale est `index,follow` :

- Une page indexable résolue en `index, follow` n'émet pas de balise robots avec cette configuration par défaut.
- Une page non indexable émet `<meta name="robots" content="noindex, nofollow">`.
- Toute directive différente, comme `noindex`, `max-snippet:-1` ou `unavailable_after`, est rendue telle quelle, espaces compris.

Définissez `seo.robots.emit_default = true` pour toujours émettre la balise. Voir la [politique de rendu robots](/fr/reference/configuration#robots-rendering-policy).

## Politiques après résolution {#policies-applied-after-resolution}

Ces traitements s'appliquent quel que soit le niveau d'origine :

- **Suffixe du titre :** `title_suffix` est ajouté sauf si le titre se termine déjà par ce suffixe. Si un modèle de route contient votre marque, terminez-le par le suffixe pour éviter une répétition comme « Brand — X | Brand ».
- **Paramètres du canonical :** les paramètres des URL dérivées du modèle ou de la requête sont retirés, sauf ceux de [`canonical.query_whitelist`](/fr/reference/configuration#canonical-urls), par exemple `page`. Les canonicals explicitement enregistrés restent inchangés.
- **Images sociales absolues :** `og:image` et `twitter:image` sont rendus comme URL absolues, même si la valeur enregistrée est un chemin relatif.

## Identifier le niveau retenu {#inspecting-which-layer-won}

Le [paquet Filament](/fr/guide/filament) indique la source de chaque champ : saisie manuelle, contenu, type de modèle, valeur globale, configuration du site ou URL. `SEOWarningEvaluator` expose aussi la distinction entre valeur explicite et valeur de repli pour vos propres indicateurs d'administration.

---
description: "Générez des images Open Graph par page avec Blade, Browsershot et Chrome : pré-génération, cache, modèles, polices et limites d'exploitation."
---

# Génération des images OG

Depuis Core 3.20, le rendu Chrome désactive JavaScript et bloque les requêtes d'assets HTTP(S), FTP et WebSocket. Vos templates doivent utiliser du HTML/CSS statique et des assets intégrés, comme ceux du paquet.

Sans image propre, les pages partagent `default_og_image`. Cette fonction produit **une carte Open Graph / Twitter de 1200×630 pixels par page**, depuis une vue Blade rendue par un navigateur headless via [spatie/browsershot](https://github.com/spatie/browsershot). Le navigateur gère les retours à la ligne, accents, polices de repli et titres longs. Les écritures non latines exigent les polices adaptées sur le serveur.

La fonction est gratuite dans le Core et **désactivée par défaut**. Désactivée, elle conserve `default_og_image` et n'exige pas la dépendance de navigateur facultative.

::: info Pré-génération statique
Les cartes sont générées par une commande Artisan, avant les requêtes des visiteurs. Une page ne référence une carte que si son fichier existe déjà. Une requête Web ne lance aucun navigateur. Il n'y a pas de point d'accès de rendu à la demande ; voir [limites](#caveats).
:::

## Prérequis {#requirements}

Installez le pilote facultatif dans votre application :

```bash
composer require spatie/browsershot
```

Ajoutez l'environnement qu'il utilise :

- **Node.js** sur le serveur.
- **Puppeteer** à la **racine de l'application**, pour que Node résolve le module :
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium**. Puppeteer télécharge un navigateur par défaut. En production, vous pouvez pointer vers un Chrome système avec [`chrome_path`](#configuration).

::: warning Sous Windows, installer Puppeteer à la racine
`npm_module_path` utilise `setNodeModulePath()` de Browsershot, qui émet un préfixe POSIX `NODE_PATH=…` sans effet sous Windows. Node y recherche les modules en remontant les répertoires depuis l'application. Installez donc Puppeteer à sa racine ; voir [limites](#caveats).
:::

## Activation {#enabling}

Publiez au besoin la configuration avec `php artisan vendor:publish --tag=seo-config`, puis activez la fonction :

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Générez ensuite les cartes. Aucun rendu n'a lieu avant cette étape :

```bash
php artisan seo:og-images
```

## Résolution de l'image {#how-resolution-works}

Une carte générée ne remplace pas une image choisie pour la page. Le résolveur complète `og:image` seulement si la valeur est vide ou correspond encore au `default_og_image` global. Une image issue de `getSEOImage()`, d'une ligne `seo_meta` ou d'un champ de contenu garde la priorité.

Le générateur calcule le chemin de stockage et renvoie une URL publique **uniquement si le fichier existe déjà** sur le disque configuré. Cette recherche ne rend aucune image :

- La requête Web ne lance jamais de navigateur ; en l'absence de carte, elle conserve le défaut statique.
- La page ne référence pas une carte qui attend encore sa génération.

Après une modification de contenu, exécutez [`seo:og-images`](#the-seo-og-images-command) au déploiement ou selon un calendrier pour produire le nouveau fichier.

## La commande `seo:og-images` {#the-seo-og-images-command}

La commande pré-génère les cartes utilisées par le résolveur :

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*` : classes de modèles à traiter, option répétable. Sans argument, la commande utilise `seo.og_image.models`, puis les [modèles du sitemap](/fr/guide/sitemaps) dans `seo.sitemap.models`, comme `seo:llms-txt`.
- `--force` : régénérer les cartes existantes, notamment après modification du contenu d'un template sans changement de `cache_version`.
- `--prune` : après génération, supprimer les cartes orphelines du chemin configuré. Seuls les noms correspondant à des hashes générés sont supprimés, jamais les autres assets. Cette option est ignorée avec `--model`, car la liste des fichiers à conserver ne couvrirait pas les autres modèles.

Chaque modèle doit utiliser `HasSEO`. Un enregistrement sans titre est ignoré. Le rapport compte `generated`, `skipped`, `failed` et, avec `--prune`, `pruned`.

### Planification {#scheduling}

Actualisez les cartes et nettoyez celles devenues orphelines après un changement de titre :

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Invalidation du cache {#the-invalidation-model}

Le nom du fichier est un hash des entrées influençant son rendu : titre, nom du site, nom du template, pilote, dimensions, couleurs du dégradé, `cache_version` et version du paquet installé.

- **Un nouveau titre produit un nouveau hash et fichier.** L'ancien fichier devient orphelin. La page revient au défaut statique jusqu'à la génération de la nouvelle carte ; `--prune` peut supprimer l'ancienne.
- **Une nouvelle `cache_version` ou version du paquet renouvelle les hashes.** Augmentez `cache_version` après une modification interne des templates pour invalider toutes les cartes. La version du paquet est prise en compte automatiquement, afin d'éviter de conserver le rendu d'un ancien template fourni.

## Templates fournis {#bundled-templates}

Les trois templates utilisent le même dégradé de marque et un format par défaut de 1200×630 :

| Template | Usage | Contenu |
|---|---|---|
| `seo::og.default` | Général | Titre et nom du site |
| `seo::og.article` | Articles et actualités | Rubrique, titre, auteur et date |
| `seo::og.product` | Produits et annonces | Marque, catégorie, titre et description |

Choisissez un template global avec `seo.og_image.template`, ou définissez-en par type de modèle :

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Un modèle peut aussi déclarer `getOgImageTemplate(): ?string`. Renvoyez le nom d'une vue ou `null` pour utiliser la correspondance ou le défaut. Ordre de priorité : hook du modèle, tableau `templates`, valeur globale `template`.

## Personnaliser le template {#customizing-the-template}

La carte est une vue Blade, par défaut `seo::og.default`, rendue en document HTML autonome. La police fournie est intégrée par une URI de données : aucun téléchargement n'est nécessaire.

**Publier et modifier la vue fournie :**

```bash
php artisan vendor:publish --tag=seo-views
```

Modifiez ensuite `resources/views/vendor/seo/og/default.blade.php`.

**Ou choisir votre propre vue :**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

Variables reçues par le template :

| Variable | Type | Sens |
|---|---|---|
| `$title` | `string` | Titre OG, sinon titre de la page. |
| `$siteName` | `?string` | Valeur résolue de `og:site_name`. |
| `$fontDataUri` | `string` | Police grasse fournie en URI `data:` ; chaîne vide si absente, avec repli sans-serif du navigateur. |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Largeur, par défaut `1200`. |
| `$height` | `int` | Hauteur, par défaut `630`. |
| `$locale` | `?string` | Langue résolue de la page pour `<html lang>`. |
| `$author` | `?string` | Auteur pour `seo::og.article`. |
| `$publishedDate` | `?string` | Date préformatée en `M j, Y` pour `seo::og.article`. |
| `$section` | `?string` | Rubrique ou catégorie de l'article/produit. |
| `$description` | `?string` | Description OG, sinon description de page, pour `seo::og.product`. |

::: info Le nom du template entre dans le cache
Changer de nom de template ou de couleurs invalide les cartes. Modifier le contenu d'une vue sous le même nom ne le fait pas : augmentez `cache_version` ou exécutez `--force`.
:::

## Configuration {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

La plupart des valeurs scalaires ont une variable d'environnement correspondante : `SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH`, `SEO_OG_IMAGE_NO_SANDBOX`, etc. La liste complète figure dans la configuration. Les tableaux `templates`, `models`, `browsershot_args` et `font_stack` s'éditent directement dans le fichier.

Le disque doit être **accessible publiquement**, car sa méthode `url()` fournit `og:image`. Avec le disque `public`, exécutez une fois `php artisan storage:link` pour créer `public/storage`.

## Linux et la sandbox {#running-on-linux-the-sandbox}

Si le serveur restreint les mécanismes de sandbox de Chrome, la commande peut échouer avec :

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Les restrictions de namespaces utilisateur sous Ubuntu 23.10+ en sont une cause possible. Examinez l'erreur réelle de lancement et le [guide de dépannage Puppeteer](https://pptr.dev/troubleshooting). Préférez corriger la configuration du serveur pour garder la sandbox.

**1. Solution de repli explicite : `--no-sandbox`.** Cette option désactive l'isolation du navigateur. Utilisez-la seulement si votre déploiement accepte délibérément cette contrepartie :

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Le HTML statique et le blocage des assets distants par Rankbeam ne remplacent pas la sandbox. Exécutez le processus sans privilèges et séparément des autres applications et de leurs secrets.

**2. Conserver la sandbox.** Laissez `no_sandbox` désactivé. Si AppArmor est en cause, adaptez un profil à l'exécutable Chrome exact, selon les [instructions Chromium](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md). Exemple :

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Chargez le profil avec `sudo apparmor_parser -r /etc/apparmor.d/chrome-og`, puis vérifiez que Chrome démarre avec sa sandbox.

::: tip Autres arguments
Dans un conteneur manquant de mémoire partagée, Chrome peut s'arrêter pendant le rendu. Ajoutez les arguments appropriés via `browsershot_args` :

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Pilotes personnalisés {#custom-drivers}

`browsershot` est le seul pilote fourni, mais le rendu passe par `Rankbeam\Seo\Contracts\OgImageRenderer`. Enregistrez votre propre moteur, par exemple canvas ou service distant, puis sélectionnez-le avec `seo.og_image.driver` :

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

Le pilote transforme seulement une chaîne HTML autonome en octets PNG aux dimensions demandées. Il ne gère ni layout ni templates.

## Polices et écritures non latines {#fonts-and-non-latin-scripts}

Noto Sans Bold, fournie sous OFL, couvre les écritures **latine, cyrillique et grecque**. Chinois, japonais, coréen, thaï, arabe, hébreu, devanagari et emoji dépendent des polices du serveur exécutant `seo:og-images`. Une police CJK peut dépasser 16 Mo ; elle n'est pas incluse. Chrome utilise ses replis par caractère quand les familles nécessaires sont installées.

Trois mécanismes complètent cette prise en charge depuis 3.15 :

1. **Une liste de familles dans chaque template.** Le body commence par `'OGBrand'`, puis `seo.og_image.font_stack`, puis `sans-serif`. La liste intégrée inclut `Noto Sans`, les quatre familles `Noto Sans CJK`, `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`, `Noto Sans Devanagari` et `Noto Color Emoji`. Les familles absentes sont ignorées. La famille CJK de la langue est placée en tête : `ja` → JP, `zh-Hans` → SC, `zh-Hant` / `zh-TW` / `zh-HK` → TC, `ko` → KR. Cela choisit les formes nationales des caractères Han partagés. `<html lang>` porte la langue BCP47. La liste fait partie du cache ; la changer renouvelle les cartes.

2. **Une vérification préalable dans `seo:og-images`.** fontconfig (`fc-list :lang=ja`, `th`, `ar`, …) est interrogé pour les écritures du titre, du nom du site et de la description, même minoritaires dans un texte mixte. Un avertissement par écriture indique une police à installer :

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   Sans fontconfig, notamment sous Windows, macOS ou dans un conteneur minimal, le paquet ne devine pas la couverture et reste silencieux. Une police absente ne fait pas nécessairement échouer le rendu : Chrome peut dessiner des carrés .notdef, d'où cette vérification.

3. **Des exemples de glyphes dans le test de rendu réel.** Avec `SEO_OG_IMAGE_LIVE_TEST=1`, `tests/Feature/OgImage/BrowsershotSmokeTest.php` rend un titre en ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he et hi, puis un contrôle de même longueur constitué d'un point de code non assigné. Des PNG identiques font échouer le test avec l'écriture et le paquet à installer. Ce test reste un échantillon : texte latin ou retours à la ligne différents peuvent distinguer les images malgré des glyphes absents. Inspectez les vrais rendus et les polices utilisées sur le serveur. FontProbe est également une vérification préalable, pas une certification exhaustive. Le Core n'a pas de commande `seo:doctor` ; utilisez `seo:og-images` pour cette vérification.

Sur Debian/Ubuntu :

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

Les templates publiés avant 3.15 continuent de fonctionner. Ils reçoivent les nouvelles variables `$fontFamily` et `$lang` et peuvent les ignorer.

## Limites {#caveats}

- **Pré-génération uniquement.** Aucune route ne rend une carte à la demande. Cette fonction n'expose donc pas de point d'accès public de rendu à protéger par URL signée ou contre SSRF/DoS. En contrepartie, exécutez [`seo:og-images`](#the-seo-og-images-command) au déploiement ou selon un calendrier.
- **`npm_module_path` sans effet sous Windows.** Le préfixe POSIX de Browsershot y est ignoré. Installez Puppeteer à la racine de l'application. Ce réglage fonctionne sous Linux/macOS.
- **Polices non latines à installer.** Seuls latin, cyrillique et grec sont inclus. Consultez les [polices](#fonts-and-non-latin-scripts), les avertissements et les images réellement produites.
- **Repli en cas d'échec.** Dépendance manquante, arrêt du navigateur ou délai dépassé sont signalés par la commande. La page conserve `default_og_image` ; un navigateur défaillant ne provoque pas d'erreur 500 sur la page.

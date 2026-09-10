---
description: "Installez laravel-seo-pro : scans du site en file d’attente avec suivi des problèmes, gestionnaire de redirections et suivi des 404 au-dessus du cœur. Compatible avec Laravel 11–13, Filament facultatif."
---

# Installer Pro {#installing-pro}

`rankbeam/laravel-seo-pro` ajoute au package cœur des scans du site en file d’attente avec suivi des problèmes, un gestionnaire de redirections et un suivi des 404. Le moteur fonctionne sur **toute application Laravel 11–13**, avec Blade, Inertia ou une API seule. Filament est une couche d’interface facultative : avec lui, le tableau de bord SEO, le gestionnaire de redirections et le suivi des 404 deviennent des pages du panneau ; sans lui, vous gérez tout avec les [commandes artisan](/fr/pro/headless).

## Prérequis {#requirements}

| | |
|---|---|
| PHP | 8.2+ ; 8.3+ avec Laravel 13 |
| Laravel | 11, 12 ou 13 |
| `rankbeam/laravel-seo` | ^3.20, installé automatiquement par Pro 2.40+ |
| `filament/filament` | **Facultatif** : 4.x ou 5.x, uniquement pour l’interface d’administration |
| `rankbeam/laravel-seo-filament` | **Facultatif** : ^1.11 pour utiliser l’éditeur SEO avec Pro 2.36+ |

Partez d’une application Laravel existante avec une base de données configurée. Suivez d’abord le [démarrage rapide du cœur](/fr/guide/quickstart) pour disposer des tables et d’un modèle qui affiche ses métadonnées. Une licence Pro fournit les identifiants Composer ci-dessous.

Pour voir le résultat, consultez [scanner → corriger → générer un rapport](/fr/pro/walkthrough).

## Installer le package {#install-the-package}

Pro est distribué via un dépôt Composer privé lié à votre licence. Ajoutez le dépôt une fois, puis installez le package. Composer demande l’adresse e-mail de la licence comme nom d’utilisateur et la clé de licence comme mot de passe :

```bash
composer config repositories.rankbeam-pro composer https://laravel-seo-pro.composer.sh
composer require rankbeam/laravel-seo-pro
```

::: details Authentification Composer non interactive
Pour la CI ou les environnements non interactifs, enregistrez les identifiants au préalable :

```bash
composer config http-basic.laravel-seo-pro.composer.sh you@example.com YOUR-LICENSE-KEY
```

:::

Lancez ensuite l’installateur :

```bash
php artisan seo-pro:install
```

L’installateur publie `config/seo-pro.php` et les migrations Pro, exécute `migrate` et affiche les prochaines étapes. Les tables du cœur et de Pro doivent maintenant être présentes dans la base de l’application.

::: details Installation manuelle et options de l’installateur
Les migrations Pro sont publiées dans votre application ; elles ne sont pas chargées automatiquement depuis le package. Les étapes manuelles équivalentes sont :

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

L’installateur peut être relancé. `--no-migrate` publie les fichiers sans exécuter les migrations. Utilisez `--force` uniquement si vous souhaitez écraser les fichiers publiés, configuration comprise.
:::

## Enregistrer les cibles du scan {#register-scan-targets}

Dans un service provider, indiquez au scanner ce qu’il doit analyser : classes de modèles, routes nommées ou ensemble du [registre de sitemaps](/fr/guide/sitemaps) :

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

Remplacez `Post` par votre propre modèle utilisant `HasSEO`. Au moins un enregistrement est nécessaire pour obtenir un résultat de scan de modèle. Les cibles de route doivent désigner des routes existantes ; omettez cet enregistrement si vous souhaitez seulement scanner des modèles.

## Vérifier l’installation {#verify-your-install}

Lancez le contrôle de configuration :

```bash
php artisan seo:doctor
```

Confirmez la présence des tables du cœur et de Pro, l’exactitude de l’URL de l’application et la liste des cibles. Appliquez les corrections signalées. Un avertissement sur la file `sync` est normal pendant l’essai des commandes exécutées directement ci-dessous ; configurez un worker avant de planifier des scans en production.

::: details Exemple de sortie du diagnostic
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor` vérifie la configuration et l’historique récent sans appel réseau ni affichage de secrets. Il ne peut pas prouver qu’un cron externe ou un worker fonctionne. Les erreurs critiques renvoient un code de sortie non nul ; les avertissements, non. Utilisez `--json` pour un résultat exploitable par programme.
:::

## Lancer le premier scan {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

La première commande exécute le scan directement : ce contrôle initial n’a donc pas besoin d’un worker de file d’attente. La seconde affiche la dernière exécution et ses résultats. Vous devez obtenir une exécution terminée avec vos cibles enregistrées traitées. Examinez les cibles en échec avant de considérer le scan comme complet.

Corrigez un champ signalé, enregistrez-le et relancez le scan. Le [parcours de démonstration](/fr/pro/walkthrough) montre cette opération avec une description manquante et un rapport du changement. Un [score technique](/fr/pro/scoring) est un diagnostic, pas une prévision de classement.

## Utilisation headless {#path-b-headless}

Le moteur est prêt à fonctionner sans panneau. Les [commandes artisan](/fr/pro/headless) permettent de scanner, examiner les problèmes, créer des redirections et générer des rapports. Les middlewares de redirection et de suivi des 404 s’enregistrent automatiquement par défaut ; leurs réglages se trouvent dans `config/seo-pro.php`.

Pour le travail planifié, suivez le guide de [configuration en production](/fr/pro/production) afin de configurer files, workers, ordonnanceur et rétention.

## Ajouter un panneau Filament, facultatif {#path-a-with-a-filament-panel}

Sur un panneau Filament 4 ou 5 existant, enregistrez le plugin Pro ci-dessous. Si votre application n’a pas encore de panneau, installez les packages d’interface et créez-en un :

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

Cela ajoute le **tableau de bord SEO**, avec action de scan global, progression en direct et liste des problèmes avec nouveau scan en un clic, le **gestionnaire de redirections** et le **suivi des 404** avec l’action *Create redirect*. `rankbeam/laravel-seo-filament` ajoute aussi la [section de champs SEO](/fr/guide/filament) aux formulaires de vos ressources.

## Résoudre les problèmes {#troubleshooting}

| Résultat | Étape suivante |
|---|---|
| Composer refuse les identifiants | Vérifiez l’e-mail et la clé de licence pour `laravel-seo-pro.composer.sh`. Ne placez pas les identifiants dans le contrôle de version. |
| Doctor signale des tables manquantes | Terminez le démarrage rapide du cœur, puis lancez `seo-pro:install` et `migrate` sur la même base que l’application. |
| Le scan ne traite aucune cible | Vérifiez l’enregistrement du provider et la présence d’enregistrements dans le modèle. |
| Un scan en file d’attente reste en attente | Démarrez le worker configuré ou utilisez `--sync` pour un contrôle direct. |
| Une cible échoue | Vérifiez les détails de l’exécution, les noms de route et l’URL de l’application avant de relancer le scan. |
| Le tableau de bord est absent | Enregistrez `SeoProPlugin` sur le panneau réellement utilisé et vérifiez les règles d’accès. |

Consultez la [configuration en production](/fr/pro/production) pour la reprise des workers et l’exploitation courante.


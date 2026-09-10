---
description: "Installez rankbeam/laravel-seo avec Composer, publiez la configuration et préparez les tables sous Laravel 11, 12 ou 13."
---

# Installation {#installation}

## Prérequis {#requirements}

- Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 ou 13.
- `spatie/laravel-sitemap` ^7.0 ou ^8.0 : facultatif, nécessaire uniquement pour générer des sitemaps.

## Installer le paquet {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Le fournisseur de services et la façade `SEO` sont découverts automatiquement. Les deux migrations créent les seules tables propres au paquet :

| Table | Rôle |
|---|---|
| `seo_meta` | Valeurs explicites par modèle, relation polymorphe et langue |
| `seo_defaults` | Valeurs par défaut globales, par type de modèle et par route |

## Facultatif : sitemaps {#optional-sitemaps}

La génération utilise [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap) :

```bash
composer require spatie/laravel-sitemap
```

Le [guide du registre des sitemaps](/fr/guide/sitemaps) décrit les sources et la génération.

## Mise à niveau depuis la v1 {#upgrading-from-v1}

Si votre application utilisait `fibonoir/laravel-seo` v1, consultez d'abord la [migration depuis la v1](/fr/guide/upgrade-from-v1). Le fournisseur, l'espace de noms et l'API ont changé. Les fichiers publiés par la v1 peuvent entrer en conflit avec la configuration v2.

## Paquets complémentaires {#companion-packages}

| Paquet | Fonctions ajoutées | Licence |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Section SEO dans les formulaires de ressources Filament 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/fr/pro/installation) | Analyses en file d'attente, redirections et suivi des erreurs 404 dans toute application Laravel ; tableau de bord Filament facultatif | Commerciale |

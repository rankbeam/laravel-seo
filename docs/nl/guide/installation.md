---
description: Installeer rankbeam/laravel-seo met Composer, publiceer de configuratie en voer de migraties uit — vereisten en installatie voor Laravel 11, 12 en 13.
---

# Installatie {#installation}

## Vereisten {#requirements}

- Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 of 13
- `spatie/laravel-sitemap` ^7.0 of ^8.0 — optioneel, alleen vereist voor het genereren
  van sitemaps

## Het pakket installeren {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Dat is de volledige installatie. De serviceprovider en de `SEO`-facade worden
automatisch ontdekt; de twee migraties maken de enige tabellen aan die het pakket beheert:

| Tabel | Doel |
|---|---|
| `seo_meta` | Expliciete waarden per model (morph + locale) |
| `seo_defaults` | Globale standaarden en standaarden per modeltype en route |

## Optioneel: sitemaps {#optional-sitemaps}

Voor het genereren van sitemaps gebruikt het pakket [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

Zie de [gids voor het sitemapregister](/nl/guide/sitemaps) voor bronnen en het genereren van sitemaps.

## Upgraden vanaf v1? {#upgrading-from-v1}

Gebruikte je app `fibonoir/laravel-seo` v1? Lees dan eerst
[Upgraden vanaf v1](/nl/guide/upgrade-from-v1) — de vendor, de namespace
en de functionaliteit van het pakket zijn veranderd. Bovendien kan v1 bestanden hebben gepubliceerd
die conflicteren met de configuratie van v2.

## Aanvullende pakketten {#companion-packages}

| Pakket | Wat het toevoegt | Licentie |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | SEO-sectie voor resourceformulieren in Filament 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/nl/pro/installation) | Sitescans via wachtrijen, redirectbeheer en een 404-monitor — in elke Laravel-app, met een optioneel Filament-dashboard | Commercieel |

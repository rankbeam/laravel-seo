---
description: "Installiere rankbeam/laravel-seo mit Composer, veröffentliche die Konfiguration und führe die Migrationen aus. Voraussetzungen für Laravel 11, 12 und 13."
---

# Installation {#installation}

## Voraussetzungen {#requirements}

- PHP 8.2+; bei Laravel 13 mindestens PHP 8.3
- Laravel 11, 12 oder 13
- `spatie/laravel-sitemap` ^7.0 oder ^8.0 — optional, nur zum Erzeugen von Sitemaps erforderlich

## Paket installieren {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Service Provider und `SEO`-Facade werden automatisch registriert. Die beiden Migrationen erstellen die einzigen Tabellen des Pakets:

| Tabelle | Zweck |
|---|---|
| `seo_meta` | Explizite Werte je Modell und Sprache über eine polymorphe Beziehung |
| `seo_defaults` | Globale Vorgaben sowie Vorgaben für Modelltypen und Routen |

## Optional: Sitemaps {#optional-sitemaps}

Die Sitemap-Erzeugung verwendet [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

Die [Sitemap-Anleitung](/de/guide/sitemaps) beschreibt Quellen und Erzeugung.

## Upgrade von v1 {#upgrading-from-v1}

Wenn deine Anwendung `fibonoir/laravel-seo` v1 verwendet hat, lies zuerst [Upgrade von v1](/de/guide/upgrade-from-v1). Vendor, Namespace und API haben sich geändert. Bereits veröffentlichte v1-Dateien können mit der v2-Konfiguration kollidieren.

## Ergänzende Pakete {#companion-packages}

| Paket | Zusätzliche Funktionen | Lizenz |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | SEO-Bereich für Ressourcenformulare in Filament 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/de/pro/installation) | Website-Scans über die Queue, Redirect-Verwaltung und 404-Monitor; für Laravel-Anwendungen mit optionalem Filament-Dashboard | Kommerziell |

---
description: "Nainstalujte rankbeam/laravel-seo přes Composer, zkopírujte konfiguraci do aplikace a spusťte migrace. Požadavky a nastavení pro Laravel 11, 12 a 13."
---

# Instalace {#installation}

## Požadavky {#requirements}

- Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 nebo 13
- `spatie/laravel-sitemap` ^7.0 nebo ^8.0 — volitelný, potřebný pouze pro generování map webu

## Instalace balíčku {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Tím je instalace hotová. Service provider a fasáda `SEO` se objeví automaticky. Dvě migrace vytvoří jediné tabulky, které balíček spravuje:

| Tabulka | Účel |
|---|---|
| `seo_meta` | Explicitní hodnoty jednotlivých modelů (polymorfní vztah + jazyková verze) |
| `seo_defaults` | Globální výchozí hodnoty a výchozí hodnoty typu modelu a routy |

## Volitelně: mapy webu {#optional-sitemaps}

Generování map webu využívá [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

Zdroje a generování popisuje [průvodce registrem map webu](/cs/guide/sitemaps).

## Přecházíte z v1? {#upgrading-from-v1}

Pokud aplikace používala `fibonoir/laravel-seo` v1, nejprve si přečtěte [přechod z v1](/cs/guide/upgrade-from-v1). Změnil se vendor, jmenný prostor i rozhraní balíčku. Verze v1 také mohla do aplikace zkopírovat soubory, které kolidují s konfigurací v2.

## Doplňkové balíčky {#companion-packages}

| Balíček | Co přidává | Licence |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | SEO sekce formulářů resource ve Filamentu 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/cs/pro/installation) | Skenování webu přes frontu, správce přesměrování a sledování 404 v libovolné aplikaci Laravelu, s volitelným přehledem Filament | Komerční |

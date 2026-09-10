---
description: "Installa rankbeam/laravel-seo con Composer, pubblica la configurazione ed esegui le migrazioni. Requisiti per Laravel 11, 12 e 13."
---

# Installazione {#installation}

## Requisiti {#requirements}

- Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 o 13
- `spatie/laravel-sitemap` ^7.0 o ^8.0 — facoltativo, serve solo per generare le sitemap

## Installa il pacchetto {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Il service provider e la facade `SEO` vengono registrati automaticamente. Le due migrazioni creano le sole tabelle gestite dal pacchetto:

| Tabella | Funzione |
|---|---|
| `seo_meta` | Valori espliciti per modello e lingua, tramite relazione polimorfica |
| `seo_defaults` | Valori predefiniti globali, per tipo di modello e per rotta |

## Facoltativo: sitemap {#optional-sitemaps}

La generazione delle sitemap usa [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

La [guida al registro delle sitemap](/it/guide/sitemaps) spiega come registrare le sorgenti e generare i file.

## Aggiornamento dalla v1 {#upgrading-from-v1}

Se usavi `fibonoir/laravel-seo` v1, leggi prima [Aggiornamento dalla v1](/it/guide/upgrade-from-v1). Sono cambiati vendor, namespace e API; i file pubblicati dalla v1 possono interferire con la configurazione della v2.

## Pacchetti aggiuntivi {#companion-packages}

| Pacchetto | Cosa aggiunge | Licenza |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Sezione SEO nei form delle risorse Filament 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/it/pro/installation) | Scansioni del sito in coda, gestione redirect e monitoraggio 404; su qualsiasi app Laravel, con dashboard Filament facoltativa | Commerciale |

---
description: Zainstaluj rankbeam/laravel-seo przez Composer, opublikuj konfigurację i uruchom migracje — wymagania i konfiguracja dla Laravel 11, 12 i 13.
---

# Instalacja {#installation}

## Wymagania {#requirements}

- Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 lub 13
- `spatie/laravel-sitemap` ^7.0 lub ^8.0 — opcjonalny, wymagany tylko do generowania mapy witryny

## Zainstaluj pakiet {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

To cała instalacja. Dostawca usług i fasada `SEO` są wykrywane automatycznie. Dwie migracje tworzą jedyne tabele należące do pakietu:

| Tabela | Przeznaczenie |
|---|---|
| `seo_meta` | Jawnie ustawione wartości dla poszczególnych modeli (relacja polimorficzna + locale) |
| `seo_defaults` | Wartości domyślne globalne, dla typu modelu i dla trasy |

## Opcjonalnie: mapy witryny {#optional-sitemaps}

Generowanie map witryny korzysta z [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

Źródła i generowanie opisuje [przewodnik po rejestrze map witryny](/pl/guide/sitemaps).

## Aktualizujesz z wersji v1? {#upgrading-from-v1}

Jeśli aplikacja używała `fibonoir/laravel-seo` v1, najpierw przeczytaj [Aktualizację z v1](/pl/guide/upgrade-from-v1). Zmieniły się prefiks nazwy pakietu (vendor), przestrzeń nazw i zakres API pakietu, a pliki opublikowane przez v1 mogą kolidować z konfiguracją v2.

## Pakiety uzupełniające {#companion-packages}

| Pakiet | Co dodaje | Licencja |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Sekcja SEO w formularzach zasobów Filament 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/pl/pro/installation) | Kolejkowane skany witryny, menedżer przekierowań i monitor 404 w dowolnej aplikacji Laravel, z opcjonalnym panelem Filament | Komercyjna |

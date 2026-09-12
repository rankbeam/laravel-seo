---
description: Εγκαταστήστε το rankbeam/laravel-seo με το Composer, δημοσιεύστε το αρχείο ρυθμίσεων και εκτελέστε τις μεταναστεύσεις — απαιτήσεις και ρύθμιση για Laravel 11, 12 και 13.
---

# Εγκατάσταση {#installation}

## Απαιτήσεις {#requirements}

- Laravel 11: PHP 8.2–8.4· Laravel 12: PHP 8.2–8.5· Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 ή 13
- `spatie/laravel-sitemap` ^7.0 ή ^8.0 — προαιρετικό, απαιτείται μόνο για τη δημιουργία
  χαρτών ιστοτόπου

## Εγκατάσταση του πακέτου {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Αυτή είναι όλη η εγκατάσταση. Ο service provider και η facade `SEO`
εντοπίζονται αυτόματα· οι δύο μεταναστεύσεις δημιουργούν τους μόνους πίνακες που ανήκουν στο πακέτο:

| Πίνακας | Σκοπός |
|---|---|
| `seo_meta` | Ρητές τιμές ανά μοντέλο (πολυμορφική συσχέτιση + locale) |
| `seo_defaults` | Προεπιλογές καθολικές, ανά τύπο μοντέλου και ανά διαδρομή |

## Προαιρετικά: χάρτες ιστοτόπου {#optional-sitemaps}

Η δημιουργία χαρτών ιστοτόπου βασίζεται στο [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap):

```bash
composer require spatie/laravel-sitemap
```

Δείτε τον [οδηγό μητρώου χαρτών ιστοτόπου](/el/guide/sitemaps) για τις πηγές και τη δημιουργία.

## Αναβάθμιση από τη v1; {#upgrading-from-v1}

Αν η εφαρμογή σας χρησιμοποιούσε το `fibonoir/laravel-seo` v1, διαβάστε πρώτα την
[Αναβάθμιση από τη v1](/el/guide/upgrade-from-v1) — ο vendor, το namespace
και το API του πακέτου έχουν αλλάξει, και η v1 μπορεί να έχει δημοσιεύσει αρχεία
που συγκρούονται με τις ρυθμίσεις της v2.

## Συνοδευτικά πακέτα {#companion-packages}

| Πακέτο | Τι προσθέτει | Άδεια |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Ενότητα SEO στις φόρμες πόρων του Filament 4/5 | MIT |
| [`rankbeam/laravel-seo-pro`](/el/pro/installation) | Σαρώσεις ιστοτόπου μέσω ουράς, διαχείριση ανακατευθύνσεων, παρακολούθηση 404 — σε οποιαδήποτε εφαρμογή Laravel, με προαιρετικό πίνακα ελέγχου Filament | Εμπορική |

---
description: "Auditbevindingen, editorwaarschuwingen en Filament-labels van Rankbeam volgen de locale van je app. Publiceer de taalbestanden om tekst te overschrijven of draag een taal bij."
---

# Vertalingen {#translations}

Elke tekst die de pakketten aan gebruikers tonen — auditbevindingen, live editorwaarschuwingen onder
de Filament-velden, labels, voorbeelden en rapporten — is een Laravel-taalregel. De pakketten
volgen `app()->getLocale()`: een paneel dat in het Italiaans draait, toont Italiaans zonder extra configuratie.

Probleem- en waarschuwings**codes** (`missing_title`, `title_too_long`, …) veranderen nooit en worden nooit
vertaald. Alleen de leesbare zin die bij een code hoort, wordt vertaald.

Meegeleverde talen: Engels, Italiaans (eerdere teksten beoordeeld; gewijzigde teksten vereisen een nieuwe beoordeling) en eerste vertaalversies in het Duits, Frans, Spaans,
Braziliaans Portugees, Nederlands, Turks, Russisch en Pools (Tier 1). Sinds core 3.16 / Filament
1.10 / Pro 2.35 zijn ook Japans, Vereenvoudigd Chinees (`zh_CN`), Traditioneel Chinees (`zh_TW`), Koreaans,
Grieks, Oekraïens en Tsjechisch beschikbaar (Tier 2). De exacte status per locale staat in
[TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md);
beoordeling door een moedertaalspreker maakt van een eerste vertaalversie een ondersteunde taal.

## Een tekst overschrijven {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Bewerk daarna `lang/vendor/seo/{locale}/seo.php` (en de bijbehorende mappen voor de andere
pakketten). De sleutels die je behoudt, overschrijven de pakketwaarden; de rest valt terug op het pakketbestand en daarna op
Engels.

## Een taal bijdragen {#contribute-a-language}

Kopieer het bestand `en` naar je locale, vertaal de waarden, behoud elke `:placeholder` en voer de
testsuite uit (een pariteitstest faalt bij elke ontbrekende of onbekende sleutel, lege waarde of verloren placeholder).
Open daarna een pull request. De volledige regels en de woordenlijst staan in
[TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md).

## Wat bewust niet wordt vertaald {#what-is-not-translated-on-purpose}

De CLI-weergave is standaard in het Engels. Stel `seo.cli_locale` / `SEO_CLI_LOCALE` in
of geef `--display-locale=it` mee om ondersteunde berichten en auditsamenvattingen te vertalen.
Pro heeft een eigen instelling `seo-pro.cli_locale`. De weergavetaal staat los van
de contentlocale die met `--locale` wordt gekozen.

- Opdrachthulp, onderhoudsdiagnostiek en de uitvoer van `seo:explain` blijven Engels; de labels PASS/WARN/FAIL blijven stabiel.
- Gerenderde HTML (`<meta>`, JSON-LD): de taal van je content, nooit die van het pakket.
- Probleemcodes, JSON-sleutels en statuscodes blijven stabiele identificatoren. Leesbare labels in de uitvoer van `--json` kunnen worden vertaald; integraties horen sleutels en codes te gebruiken.

## De andere helft: de taal van je content {#the-other-half-your-content-s-language}

Deze pagina gaat over de taal die het *pakket* spreekt. Hoe het de taal van je
*content* verwerkt — titellimieten per schrift, afkappen, hoofdlettergebruik,
hreflang-beleid, `inLanguage`, regionale zoekmachines en lettertypen voor OG-afbeeldingen — lees je bij
[Meertalige content](/nl/guide/multilingual).

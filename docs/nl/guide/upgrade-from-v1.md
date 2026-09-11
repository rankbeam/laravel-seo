---
description: "Upgrade van fibonoir/laravel-seo v1 naar rankbeam/laravel-seo v2. Het pakket krijgt een nieuwe naam en richt Core op metadataresolutie, rendering, JSON-LD en sitemaps."
---

# Upgraden vanaf fibonoir/laravel-seo v1 {#upgrading-from-fibonoir-laravel-seo-v1}

v2.0.0 hernoemt het pakket naar `rankbeam/laravel-seo` en brengt het terug tot een
gerichte kern: metadataresolutie, rendering, JSON-LD en sitemaps. De analyzer,
scanner, redirects, 404-monitor en beheerinterface zijn naar afzonderlijke
pakketten verplaatst.

## 1. Het pakket vervangen {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Namespaces bijwerken {#_2-update-namespaces}

De klassenamen blijven gelijk; alleen de basisnamespace verandert:
`Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`. Zoeken en vervangen in het hele project is voldoende.
De facade-alias `SEO` en de Blade-directives `@seo` blijven ongewijzigd.

## 3. Verouderde gepubliceerde bestanden verwijderen {#_3-delete-stale-published-files}

Maak vóór het verwijderen van bestanden of tabellen een back-up van de
gepubliceerde configuratie en exporteer de betrokken gegevens. Controleer of je
ze kunt herstellen. Deze handleiding migreert de redirect-, 404- en scangeschiedenis
van v1 niet naar het afwijkende schema van Pro. De hieronder beschreven
compatibiliteit van Core-tabellen geldt alleen voor `seo_meta` en `seo_defaults`.

::: warning Dit kan ongemerkt problemen veroorzaken
`seo:install` uit v1 publiceerde bestanden in je applicatie die met het v2-pakket
kunnen conflicteren zonder ook maar één foutmelding te geven.
:::

- **`config/seo.php`** — als jouw bestand door v1 is gepubliceerd, of door
  `ralphjsmit/laravel-seo` dat de v1-installer kon achterlaten, krijgt het voorrang op de
  pakketconfiguratie. Daardoor kunnen `site_name` en alle `{site_name}`-templates
  null worden. Verwijder het bestand en publiceer opnieuw: `php artisan vendor:publish --tag=seo-config`.
- **v1-migraties** voor tabellen die niet meer onder Core vallen: `seo_redirects`,
  `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache` en `seo_internal_links_index`.
  Verwijder de migratiebestanden. Bestaan de tabellen in productie, verwijder ze
  dan **vóór** het installeren van `rankbeam/laravel-seo-pro`. Pro maakt ze opnieuw aan met een ander schema.
- **Gepubliceerde stubs** onder `app/` en `resources/js` uit de
  Filament 3-/Livewire-/Vue-/React-werkwijze van v1. Ze verwijzen naar klassen die niet meer bestaan.

De twee Core-tabellen (`seo_meta` en `seo_defaults`) hebben een compatibel schema.
Je gegevens blijven bij de upgrade behouden.

## 4. Verwijderde functies en hun nieuwe plek {#_4-removed-features-and-where-they-went}

| v1-functie | Waar die nu staat |
|---|---|
| SEO-formuliersectie voor Filament | [`rankbeam/laravel-seo-filament`](/nl/guide/filament) (gratis, MIT) |
| Contentanalyzer (32 regels) | De oude analyzer wordt niet meegenomen in deze migratie. De sitescanner van `rankbeam/laravel-seo-pro` detecteert technische SEO-problemen. De numerieke SEO-score is een Pro-functie, afgeleid van bevindingen. |
| Sitebrede scanner | `rankbeam/laravel-seo-pro` — pipeline via een queue en dashboard |
| Redirectbeheer | `rankbeam/laravel-seo-pro` — met extra beveiliging: regex-validatie en bescherming tegen open redirects |
| 404-monitor | `rankbeam/laravel-seo-pro` — privacy als uitgangspunt: standaard geen IP-adressen opslaan |
| GA4-analytics, interne links | Backlog van `rankbeam/laravel-seo-pro` |
| Installer `seo:install` | Vervallen: installeren bestaat uit require, configuratie publiceren en migreren |

## 5. Gedragswijzigingen om te controleren {#_5-behavior-changes-to-review}

- **`og:image` / `twitter:image` zijn altijd absolute URL's.** v1 gaf
  handmatig ingestelde relatieve paden ongewijzigd weer.
- **Bij afgeleide canonieke URL's wordt de querystring verwijderd.** Expliciete
  canonieke URL's blijven ongewijzigd.
- **Geregistreerde sitemapbronnen hebben voorrang op automatische detectie.**
  Geen dubbele `sitemap-post.xml` meer naast een geregistreerde `sitemap-posts.xml`.
- **JSON-LD wordt met `JSON_HEX_*` geëscapet.** Als je de ruwe scriptuitvoer
  nabewerkt, houd dan rekening met escapes zoals `<`.

## 6. Bekende aandachtspunten {#_6-known-gotchas}

- Laravels standaard-`DatabaseSeeder` gebruikt `WithoutModelEvents`. Dat schakelt in seeders
  de hook voor automatisch aanmaken van `HasSEO` uit.
- Als een titeltemplate voor routestandaardwaarden je merk al bevat, laat de
  template dan eindigen met het geconfigureerde `title_suffix`. De resolver voegt
  dat achtervoegsel dan niet nogmaals toe.

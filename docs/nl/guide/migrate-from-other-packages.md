---
description: "Stap over van een ander Laravel SEO-pakket naar Rankbeam: koppel de bestaande API en opslag aan de HasSEO-trait en saveSEO(), met een importer in één commando voor SEO-gegevens per model."
---

# Migreren vanaf andere Laravel SEO-pakketten {#migrating-from-other-laravel-seo-packages}

Gebruik je al een ander SEO-pakket? Overstappen naar Rankbeam is bedoeld als
een dag werk, zonder je applicatie te herschrijven. Deze handleiding koppelt
de API en opslag van veelgebruikte pakketten aan de twee basiselementen van
Rankbeam: de trait [`HasSEO`](/nl/guide/quickstart) en `saveSEO()`.
Voor het pakket dat SEO-gegevens per model opslaat, is er een importer die je
met één commando uitvoert.

::: tip Kom je van WordPress?
Migreer je een contentsite vanaf WordPress met Yoast of Rank Math, bekijk dan
de aparte handleiding [**Migreren vanaf WordPress**](/nl/guide/migrate-from-wordpress).
Die behandelt de CSV-importer en de readers die rechtstreeks uit de database lezen.
:::

| Huidig pakket | Slaat gegevens op in | Migratieroute |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | een polymorfe `seo`-tabel | **`php artisan seo:import-from ralphjsmit`** en de trait vervangen |
| [`artesaos/seotools`](#from-artesaos-seotools) | nergens (runtime en configuratie) | code vervangen: waarden instellen via `saveSEO()` of berekende getters |
| [`spatie/*`](#from-spatie-packages) | nergens (schema-org- en sitemapbuilders) | behouden wat aanvullend is, de rest naar Rankbeam verplaatsen |

Alleen **ralphjsmit** bewaart SEO-gegevens in een databasetabel. Dat is dus het
enige pakket met gegevens om in bulk te importeren. De andere bouwen tags
tijdens de uitvoering: er is geen tabel om uit te lezen. Je vervangt hun
aanroepen per aanvraag door opgeslagen `seo_meta`.

---

## Vanaf `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

`ralphjsmit/laravel-seo` slaat per model één polymorfe rij op in een `seo`-tabel.
De structuur ligt dicht bij die van Rankbeams `seo_meta`. Daardoor is een
overzichtelijke, idempotente bulkimport mogelijk.

### 1. Rankbeam ernaast installeren {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Beide pakketten kunnen tijdens de migratie naast elkaar bestaan. Ze gebruiken
verschillende tabellen (`seo` en `seo_meta`) en verschillende namespaces voor hun traits.

::: warning Eén configuratiebestand, geen twee
Als er nog een gepubliceerde `config/seo.php` van `ralphjsmit/laravel-seo` in je applicatie
staat, krijgt die voorrang op de configuratie van Rankbeam. Beide gebruiken
de configuratiesleutel `seo`. Maak een back-up, verwijder het bestand
en publiceer dat van Rankbeam opnieuw: `php artisan vendor:publish
--tag=seo-config`.
:::

### 2. De importer uitvoeren {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

De importer leest de `seo`-tabel van ralphjsmit, zoekt voor elke rij het
werkelijke Eloquent-model op en schrijft de gegevens naar `seo_meta`.

| Optie | Effect |
|---|---|
| `--dry-run` | Rapporteer wat zou worden geïmporteerd, zonder iets te schrijven. |
| `--model="App\Models\Post"` | Beperk de import tot één of meer modelklassen (herhaalbaar). |
| `--locale=fr` | Schrijf de geïmporteerde rijen voor deze locale (standaard de applicatielocale). |
| `--table=legacy_seo` | Lees een brontabel met een andere naam. |
| `--connection=legacy` | Lees de brontabel via een andere databaseverbinding. |
| `--limit=100` | Importeer maximaal N rijen (handig voor een gefaseerde migratie). |
| `--overwrite` | Vervang bestaande niet-lege waarden (standaard worden alleen lege velden gevuld). |
| `--json` | Machinaal leesbaar rapport. |
| `--force` | Sla de bevestigingsvraag over (voor scripts en CI). |

De importer is **idempotent**: opnieuw uitvoeren werkt dezelfde rijen bij en
maakt nooit duplicaten. Standaard *vult* hij alleen lege velden; SEO-gegevens
die je al in Rankbeam hebt ingesteld worden niet overschreven. Geef `--overwrite`
mee als de geïmporteerde waarden je bestaande waarden moeten vervangen.

### 3. De trait op je modellen vervangen {#_3-swap-the-trait-on-your-models}

Vervang de trait van ralphjsmit door die van Rankbeam. De methodenamen verschillen
iets; de tabel die de trait leest is voortaan `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Heb je SEO-gegevens aangepast met `getDynamicSEOData()` van ralphjsmit, verplaats die
logica dan naar Rankbeams berekende getters per veld: `getSEOTitle()`,
`getSEODescription()`, `getSEOImage()`, `getUrlForSEO()` en `getSEOAlternates()`.
Zie [Snelstart](/nl/guide/quickstart). Opgeslagen overschrijvingen gaan via `saveSEO()`:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Veldkoppeling {#field-mapping}

De importer koppelt velden **expliciet**. Hij kopieert nooit blindelings een
kolom die het schema van Core 3 niet heeft.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Opmerkingen |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | **Opnieuw bepaald** vanuit het actuele model (zie hieronder), niet letterlijk gekopieerd. |
| `title` | `title` | Afgekapt op 70 tekens, de lengte van de titelkolom in `seo_meta`. Te lange waarden worden gerapporteerd. |
| `description` | `description` | Afgekapt op 160 tekens. Te lange waarden worden gerapporteerd. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Afgekapt op 50 tekens. |
| `image` | `og_image` | `twitter:image` neemt deze automatisch over via de resolver. |
| `author` | *(niet geïmporteerd)* | `seo_meta` in Core 3 heeft geen auteurskolom. De artikelauteur wordt op resolverniveau bepaald en is geen opgeslagen sociale metadata. Rijen met een auteur worden **geteld en gerapporteerd**, zodat je kunt beslissen waar die thuishoort, bijvoorbeeld in een berekende waarde zoals `getSEOData`. |
| `id`, `created_at`, `updated_at` | *(niet geïmporteerd)* | Structureel. |

**Waarom het morph-type opnieuw wordt bepaald.** Voor elke bronrij wordt het
werkelijke model opgezocht. De `seoable`-sleutels komen uit de eigen
`getMorphClass()` van dat model. Zo blijft de relatie correct onder de *huidige*
[morph map](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types)
van je applicatie, ook als ralphjsmit een andere conventie opsloeg. De importer
kan daardoor ook rijen overslaan waarvan het model inmiddels is verwijderd.
Die worden als overgeslagen gerapporteerd en nooit als verweesde rijen geschreven.

### Wat het rapport laat zien {#what-the-report-tells-you}

Zonder `--json` toont het commando een resultatentabel en drie onderdelen om te controleren:

- **Truncated (ingekort)** — waarden die zijn ingekort om in een `seo_meta`-kolom te passen. Controleer deze.
- **Not imported (niet geïmporteerd)** — bronkolommen, zoals `author`, die gegevens bevatten maar geen plek in Core 3 hebben.
- **Skipped rows by reason (overgeslagen rijen per reden)** — overgeslagen rijen per reden: lege bronrijen, verwijderde modellen en modeltypen die niet konden worden gevonden.

### Controleren {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Als je tevreden bent, verwijder je `ralphjsmit/laravel-seo` en de bijbehorende `seo`-tabel.

---

## Vanaf `artesaos/seotools` {#from-artesaos-seotools}

`artesaos/seotools` bouwt tags **tijdens de uitvoering**. Je stelt per aanvraag waarden
in via de facades `SEOMeta`, `OpenGraph`, `TwitterCard` en `JsonLd`,
vaak in een controller, met standaardwaarden uit `config/seotools.php`.
Er wordt niets per model opgeslagen. Er is dus geen tabel om te importeren:
je verplaatst de aanroepen per aanvraag naar opgeslagen of berekende waarden.

| Aanroep in artesaos/seotools | Equivalent in Rankbeam |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` of `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` of `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` of `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | Geen equivalente keywords-metatag: focuszoekwoorden zijn bedoeld voor interne redactionele controles. `saveSEO(['focus_keywords' => [...]])` (zie [audit](/nl/guide/audit)) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | de [JSON-LD-schemagraaf](/nl/guide/schema) |
| standaardwaarden uit `config/seotools.php` | sitestandaardwaarden in `config/seo.php` en [prioriteit van resolverlagen](/nl/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` in de layout | `@seo($model)` (zie [Blade](/nl/guide/blade)) |

De verandering zit in de aanpak. In plaats van in elke controller tags stap
voor stap in te stellen, sla je SEO-gegevens één keer op, per model in
`seo_meta`, en rendert de resolver van Rankbeam ze. Sitebrede terugvalwaarden
uit `config/seotools.php` worden [configuratiestandaardwaarden](/nl/reference/configuration)
van Rankbeam. Statische pagina's per route gebruiken `@seoForRoute()`.

---

## Vanaf Spatie-pakketten {#from-spatie-packages}

Er is geen pakket `spatie/laravel-seo` voor metadataopslag, dus er valt niets te
importeren. De Spatie-pakketten die mensen met SEO combineren zijn
**aanvullende builders**. Je kunt ze elk afzonderlijk behouden of vervangen:

- **`spatie/schema-org`** — een fluent JSON-LD-builder. Rankbeam heeft een eigen
  [schemagraaf](/nl/guide/schema) met getypeerde builders voor `Article`,
  `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` en `Organization`.
  Ze slaan op in `seo_meta.schema_jsonld` en renderen zonder duplicaten. Heb je zelf
  opgebouwde `spatie/schema-org`-objecten, geef hun `->toArray()`-uitvoer dan mee aan
  `saveSEO(['schema_jsonld' => $array])` of bouw ze opnieuw op met de builders van Rankbeam.
- **`spatie/laravel-sitemap`** — een sitemapgenerator. Het [sitemapregister](/nl/guide/sitemaps)
  van Rankbeam bouwt hierop voort. Je kunt je modellen als bronnen registreren
  en Rankbeam een gecombineerde sitemap laten genereren, of je bestaande
  Spatie-sitemap behouden en de route van Rankbeam uitschakelen.

(Gebruikte je [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO), een
andere metadatabuilder die tijdens de uitvoering met structs werkt, volg dan
dezelfde aanpak als voor artesaos: verplaats `setTitle`-/`addMeta`-aanroepen
per aanvraag naar `saveSEO()` of berekende getters.)

---

## De importer uitbreiden {#extending-the-importer}

Het commando `seo:import-from` gebruikt een klein register met implementaties van
`Rankbeam\Seo\Importing\Contracts\Importer`. Je kunt dus nieuwe bronnen toevoegen zonder het commando aan te
passen. Ingebouwde bronnen zijn momenteel `ralphjsmit` en de
WordPress-importers `wordpress-csv`, `yoast` en `rank-math`.
Zie [Migreren vanaf WordPress](/nl/guide/migrate-from-wordpress).
Registreer je eigen bron in een serviceprovider:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```


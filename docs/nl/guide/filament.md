---
description: "Voeg in twee regels een volledige SEO-sectie toe aan elk Filament-resourceformulier met het gratis pakket laravel-seo-filament. Ondersteunt Filament 4.x en 5.x met de HasSEO-trait."
---

# Filament-beheervelden {#filament-admin-fields}

Het gratis pakket [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament)
voegt een volledige SEO-sectie toe aan elk Filament-resourceformulier —
**twee regels per resource**. Het ondersteunt Filament **4.x en 5.x** (Livewire 3
en 4). Metadata bewerken is gratis; Pro voegt scans toe en de score die in het
onderstaande voorbeeld wordt getoond.

## Vooraf vereist {#prerequisites}

Gebruik een bestaand Filament 4- of 5-paneel en een model met de `HasSEO`-trait
uit de core. Voltooi de [snelstart voor de core](/nl/guide/quickstart), inclusief migraties
en rendering, voordat je de editor toevoegt.

## Installeren {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

Het model achter de resource moet de `HasSEO`-trait uit de core gebruiken.

## De sectie aan een resource toevoegen {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## Het opgeslagen resultaat controleren {#check-the-saved-result}

Open een bestaand record. Voer een SEO-beschrijving in, sla op en laad het formulier opnieuw.
De waarde moet bewaard blijven, zichtbaar zijn in het voorbeeld en als bron
**Handmatig** hebben. Controleer de `<head>` van de gerenderde pagina om te bevestigen dat dezelfde
beschrijving je bezoekers bereikt.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="SEO-velden in de Merchant-demo: titel, beschrijving, canonieke URL, sociale afbeelding, zoekresultaatvoorbeeld en bronnen van de uiteindelijke waarden." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Voorbeeld uit de Merchant-demo. De velden gebruiken het thema van je paneel; de beschikbare
instellingen en tekenlimieten hangen af van de geïnstalleerde versie en configuratie.*

De sectie bevat:

- **Titel en beschrijving** met live tekentellers — de limiet komt
  uit het [lengtebeleid](/nl/guide/multilingual#title-and-description-budgets-per-script) van de core
  voor het schrift waarin je typt (60/160 voor Latijnse tekst, ~30/80 voor CJK, geteld
  in grafemen).
- **Focuszoekwoorden** — een taginvoerveld. Je typt gewone zoekwoorden; ze worden opgeslagen in
  de gestructureerde `[{keyword, is_primary}]`-vorm van de core (het eerste is het primaire zoekwoord),
  zodat `getPrimaryKeyword()` en `SEOData` ze ongewijzigd lezen. Schakel
  `seo.keywords.enabled` in om de opdracht [`seo:audit`](/nl/guide/audit) en de
  Pro-scan pagina's zonder zoekwoord te laten markeren (standaard uit — één gedeelde instelling, zie
  [Configuratie](/nl/reference/configuration#focus-keywords)).
- **Canonieke URL** (leeg = automatisch, querystring verwijderd).
- **Robots**-keuzelijst (leeg = sitestandaard).
- **Afbeelding voor sociaal delen** uploaden (og:image / twitter:image), opgeslagen op
  de standaardschijf van Filament onder `seo/`.
- **Zoekresultaatvoorbeeld** dat de terugvalvolgorde van de resolver live volgt
  terwijl je typt.
- **Bronindicatoren** — per veld zie je welke resolverlaag de
  effectieve waarde heeft geleverd: *Handmatig*, *Uit de content*, *Standaard van het modeltype*,
  *Globale standaard*, *Siteconfiguratie* of *Afgeleid van de URL*.

## Velden beperken {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

Accepteert elke deelverzameling van `title`, `description`, `focus_keywords`, `canonical`,
`robots`, `og_image`.

Zonder de trait retourneert `SEOFields::make(?array $only)` dezelfde sectie
rechtstreeks.

## Hoe waarden worden opgeslagen {#how-values-persist}

De sectie koppelt aan een `seo_meta`-statusgroep en slaat op via de
`seoMeta()`-relatie van de core (bijwerken of aanmaken) — zonder kolommen in je eigen tabellen.
De waarden worden direct laag 6 (expliciet) in de
[resolver](/nl/concepts/resolver-precedence).

## Meerdere talen {#several-languages}

De core bewaart [één `seo_meta`-rij per (model, locale)](/nl/guide/multilingual).
Geef de locales mee waarin een pagina wordt gepubliceerd en de sectie toont **één tabblad per
taal** (Filament 1.9):

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

Of stel dit één keer in voor elke resource, in de pakketconfiguratie:

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

Elk tabblad bewerkt zijn eigen rij en heeft zijn eigen:

- **tellers** — het [lengtebeleid](/nl/guide/multilingual#title-and-description-budgets-per-script)
  voor het schrift van die taal, zodat een lege Japanse titel `0 / 30` toont
  terwijl het Engelse tabblad op dezelfde pagina `0 / 60` toont;
- **voorbeeld** (SERP / sociale kaart), gerenderd uit de uiteindelijke waarden van die locale;
- **terugvalindicatoren** die de rij voor die locale beschrijven;
- een **badge** met het aantal ingevulde velden in die versie, zodat lege
  vertalingen opvallen.

Het tabblad krijgt de taalnaam in de taal van het paneel als label
(`Italiano` / `Italian`) wanneer `ext-intl` is geladen, en anders de
taalcode. Alle tabbladen worden samen gevalideerd en opgeslagen; voor een taal waarvoor niets is
ingevuld, wordt nooit een lege rij aangemaakt.

::: details Eigen koppelingen voor formulierstatus
Bij meerdere locales is het statuspad `seo_meta.{locale}.title`; bij één
blijft het `seo_meta.title`. Gebruik het bijbehorende pad in eigen formulieracties.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Engelse, Italiaanse en Japanse tabbladen in de Merchant-demo, met Japanse titel- en beschrijvingslimieten van 30 en 80 en een niet-ingevulde beschrijving." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Merchant-demo, 9 september 2026, met `locales: ['en', 'it', 'ja']`.
Het lege Japanse tabblad gebruikt zijn eigen tellers. De Engelse titel komt hier
uit de contentterugvalwaarde van het demomodel: een taaltabblad toevoegen
vertaalt je content niet. De Pro-score boven de velden is het laatste
scanresultaat van het record, geen afzonderlijke score per taaltabblad.*

### Met een vertaalplugin {#with-a-translatable-plugin}

Met `lara-zeus/spatie-translatable` **1.x op Filament 4** of **2.x op
Filament 5** gebruik je de pagina-adapters van Rankbeam voor Edit en Create. Vervang alleen
de trait-imports van de pagina; behoud de resource-/lijsttraits van de plugin, de paneelplugin
en de actie `LocaleSwitcher`:

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

Elke pagina declareert nog steeds `use Translatable;` binnen haar klasse. De plugin
blijft een optionele applicatieafhankelijkheid. Gebruik de nieuwste gepatchte versie;
de lokale integratiefixture dekt plugin 1.0.4 / Filament 4.13.1 en plugin
2.0.1 / Filament 5.8.1.

Bij het wisselen blijven niet-opgeslagen bovenliggende content, SEO-metadata en concepten voor gestructureerde gegevens
in de editor bewaard. Opslaan valideert elke bezochte taal en slaat ze samen op
in een databasetransactie. Een validatiefout opent de taal die
aandacht nodig heeft. Uploads worden bij Opslaan opgeslagen; bij het verlaten of herladen van de pagina gaan
niet-opgeslagen concepten verloren. Een concept opslaan vertaalt ontbrekende content niet voor je.

De adapters behouden de normale before-/after-hooks en mutators voor formuliergegevens.
Als je pagina `handleRecordCreation()`, `handleRecordUpdate()`,
`callHook()` of transactiemethoden overschrijft, integreer het adaptergedrag dan in die
aanpassing en test de opslagprocedure. Databasetransacties draaien
schrijfacties op het bestandssysteem niet terug; applicaties moeten hun gebruikelijke opruiming van verweesde bestanden behouden.

Gebruik voor eigen live tekstvelden op Livewire 3 bij voorkeur `->live()` of
`->live(onBlur: true)` in plaats van een expliciete debounce: die laatste vertraagt de lokale modelstatus
en kan de laatste toetsaanslagen verliezen wanneer je snel van locale wisselt. De titel-
en beschrijvingsvelden van Rankbeam gebruiken de standaarddebounce voor verzoeken.

De upstream-paginatraits alleen vullen formulieren opnieuw tijdens het wisselen. Rankbeam voorkomt
dat ze per ongeluk metadata wegschrijven, maar die traits bewaren geen SEO-concepten;
migreer Edit-/Create-pagina's naar de adapters. Expliciete `locales:`-tabbladen
blijven een gedeelde editor en gaan voor op de taalwisselaar van de pagina.

Zonder expliciete localelijst of paginalocale bewerkt de sectie de app-locale.

## Gestructureerde gegevens (schema.org) {#structured-data-schema-org}

Met de optionele sectie **Gestructureerde data** kunnen redacteuren JSON-LD-schema
voor uitgebreide zoekresultaten toevoegen zonder code aan te passen. Voeg haar naast de SEO-sectie toe:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

(of rechtstreeks `SEOSchemaFields::make()`, zonder de trait).

De sectie schrijft naar de kolom `seo_meta.schema_jsonld` van de core — dezelfde waarde die de
[schemarenderer](/nl/guide/schema) genereert — en is **uitsluitend een interfacekoppeling**: elk
document wordt opgebouwd door een schemabuilder van de core en gevalideerd door de
`SchemaValidator` van de core voordat het kan worden opgeslagen. De sectie voegt geen eigen schemalogica toe.

De sectie biedt:

- **Automatische breadcrumb** — één schakelaar, als eerste getoond omdat er geen configuratie nodig is.
  Leidt een `BreadcrumbList` af uit de ouderketen van het record via
  `BreadcrumbSchema::fromModelAncestors()`. Niets om in te vullen — het volgt de
  voorouders van het model.
- **Schema-blokken** — een repeater. Elk blok is een **FAQ** (vraag-/
  antwoordparen → `FAQPage`) of een **Product** (naam, beschrijving, afbeelding, merk,
  SKU, prijs + valuta, beschikbaarheid → `Product`), opgebouwd door de
  `FAQSchema`- / `ProductSchema`-builders van de core.

### Validatie {#validation}

Een blok dat ongeldige JSON-LD zou opleveren, wordt **bij het opslaan afgewezen** met de melding van de
core-validator — bijvoorbeeld een FAQ-item zonder antwoord of een Product zonder
afbeelding of aanbieding (deze builder vereist die velden; dit is geen volledige beschrijving van
Googles vereisten voor elke Product-zoekfunctie). Leeggelaten blokken
worden eenvoudigweg genegeerd.

### Wat wordt opgeslagen {#what-it-stores}

`schema_jsonld` bevat de opgebouwde documenten: één object als er één document is,
een JSON-array als er meerdere zijn (eerst het kruimelpad, daarna je blokken). Beide
vormen zijn geldige JSON-LD en worden ongewijzigd gerenderd via `@seo` / `renderSchema()`.

### Schema dat de editor niet beheert {#schema-it-doesn-t-manage}

Schema dat je in code hebt geschreven en dat deze editor niet kan weergeven — een handgeschreven
`@graph`, een ongebruikelijke `@type` of een Product met velden die het formulier niet aanbiedt
(reviews, beoordelingen, GTIN/MPN) — blijft **letterlijk behouden**. Het formulier openen en opslaan
overschrijft dat schema nooit.

## Problemen oplossen {#troubleshooting}

- **Het opgeslagen veld ontbreekt op de pagina:** controleer of je template
  `@seo($model)` rendert voor hetzelfde record en dezelfde locale.
- **Een veld gebruikt nog een terugvalwaarde:** controleer of er voor het veld een overschrijving is opgeslagen
  in de actieve taal. De bronindicatoren tonen welke laag de uiteindelijke waarde levert.
- **Een taaltabblad ontbreekt:** controleer het expliciete argument `locales:`, de pakketconfiguratie
  en een eventuele taalwisselaar op paginaniveau. Hun voorrangsvolgorde staat hierboven beschreven.

::: details Een eigen paneel testen in Testbench

Als je Filament opstart in orchestra/testbench, registreer de
`SupportServiceProvider` van Filament dan **vóór** `LivewireServiceProvider` — Filament
koppelt de `DataStore` van Livewire opnieuw. Bij de verkeerde volgorde faalt elke Livewire-test
met `ViewErrorBag::put(): ... null given`. Echte apps hebben hier geen last van
(pakketontdekking zet de providers in de juiste volgorde).
:::

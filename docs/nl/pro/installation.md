---
description: "Installeer laravel-seo-pro voor sitescans via een queue, bevindingenbeheer, redirects en 404-monitoring boven op Core. Werkt in elke Laravel 11–13-app; Filament is optioneel."
---

# Pro installeren {#installing-pro}

`rankbeam/laravel-seo-pro` voegt sitescans via een queue met bevindingenbeheer, redirectbeheer
en een 404-monitor toe aan het Core-pakket. De engine werkt in **elke Laravel
11–13-app**: Blade, Inertia of een zuivere API. Filament is een optionele
interfacelaag. Installeer je die, dan krijg je het SEO-dashboard, redirectbeheer
en de 404-monitor als paneelpagina's. Zonder Filament beheer je alles via
[Artisan-commando's](/nl/pro/headless).

## Vereisten {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13) |
| Laravel | 11, 12 of 13 |
| `rankbeam/laravel-seo` | ^3.20 (automatisch geïnstalleerd door Pro 2.40+) |
| `filament/filament` | **optioneel** — 4.x of 5.x, alleen voor de beheerinterface |
| `rankbeam/laravel-seo-filament` | **optioneel** — ^1.11 bij gebruik van de SEO-editor met Pro 2.36+ |

Begin met een bestaande Laravel-app en een geconfigureerde database. Volg eerst
de [Snelstart voor Core](/nl/guide/quickstart), zodat een model metadata rendert
en de Core-tabellen bestaan. Je Pro-licentie geeft je de onderstaande Composer-inloggegevens.

Bekijk [van scan naar correctie en rapport](/nl/pro/walkthrough) voor een visueel voorbeeld van het resultaat.

## Het pakket installeren {#install-the-package}

Pro wordt verspreid via een private Composer-repository die aan je licentie is
gekoppeld. Voeg de repository één keer toe en installeer het pakket. Composer
vraagt om het e-mailadres van je licentie als gebruikersnaam en de licentiesleutel als wachtwoord:

Lemon Squeezy verwerkt de betaling als merchant of record. Na de betaling vind je op de privépagina met je aankoopbewijs de downloadsleutel en Composer-instructies. Gebruik het e-mailadres van de aankoop als gebruikersnaam. Rankbeam host de pakketrepository; je hebt geen Anystack-account nodig. Houd de link naar het aankoopbewijs en `auth.json` privé. Een volledige terugbetaling trekt de toegang tot toekomstige downloads en updates in, zonder een geïnstalleerde applicatie te onderbreken.

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details Niet-interactieve Composer-authenticatie
Sla de inloggegevens vooraf op voor CI of andere niet-interactieve omgevingen:

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

Voer daarna de installer uit:

```bash
php artisan seo-pro:install
```

De installer publiceert `config/seo-pro.php` en de Pro-migraties, voert `migrate` uit
en toont de volgende stappen. De Core- en Pro-tabellen zouden nu in de database
van je applicatie moeten staan.

::: details Handmatige installatie en installeropties
Pro-migraties worden in je applicatie gepubliceerd; het pakket laadt ze niet
automatisch. De gelijkwaardige handmatige stappen zijn:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Je kunt de installer opnieuw uitvoeren. `--no-migrate` publiceert bestanden
zonder te migreren. Gebruik `--force` alleen als je gepubliceerde bestanden,
inclusief je configuratie, bewust wilt overschrijven.
:::

## Scandoelen registreren {#register-scan-targets}

Geef in een serviceprovider aan wat de scanner moet controleren: modelklassen,
benoemde routes of alles in je [sitemapregister](/nl/guide/sitemaps):

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

Vervang `Post` door je eigen model met `HasSEO`. Je hebt minstens
één record nodig om een modelscanresultaat te zien. Doelen op basis van routes moeten
bestaande routes benoemen. Laat die registratie weg als je alleen modellen wilt scannen.

## Je installatie controleren {#verify-your-install}

Voer de configuratiecontrole uit:

```bash
php artisan seo:doctor
```

Controleer of de Core- en Pro-tabellen bestaan, de applicatie-URL klopt en je
scandoelen worden vermeld. Volg de voorgestelde correcties. Een waarschuwing
over de queue `sync` is normaal als je de onderstaande commando's direct
uitprobeert. Configureer een worker voordat je productiescans inplant.

::: details Voorbeeld van de statuscontrole
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor` controleert de configuratie en recente uitvoeringsgeschiedenis.
Het doet geen netwerkverzoeken en toont geen geheime waarden. Het kan niet bewijzen
dat een externe cron of worker draait. Kritieke fouten geven een niet-nul-exitcode;
waarschuwingen niet. Gebruik `--json` voor het machinaal leesbare resultaat.
:::

## Je eerste scan uitvoeren {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

Het eerste commando voltooit de scan direct in hetzelfde proces. Voor deze
eerste controle is dus geen queueworker nodig. Het tweede toont de laatste
uitvoering en de resultaten. Verwacht een voltooide uitvoering waarin de
geregistreerde doelen zijn verwerkt. Onderzoek mislukte doelen voordat je
de scan als voltooid beschouwt.

Corrigeer één gemeld veld, sla het op en scan opnieuw. De
[rondleiding](/nl/pro/walkthrough) toont dit met een ontbrekende beschrijving
en een rapport van de wijziging. Een [technische score](/nl/pro/scoring) is
een diagnostisch resultaat en geen voorspelling van zoekposities.

## Headless gebruik {#path-b-headless}

De engine is zonder paneel klaar voor gebruik. Via [Artisan-commando's](/nl/pro/headless)
kun je scannen, bevindingen bekijken, redirects aanmaken en rapporten genereren.
De middleware voor redirects en 404's wordt standaard automatisch geregistreerd.
De instellingen staan in `config/seo-pro.php`.

Volg voor ingepland werk de [productieconfiguratie](/nl/pro/production) om
queues, workers, de scheduler en bewaartermijnen in te stellen.

## Een Filament-paneel toevoegen (optioneel) {#path-a-with-a-filament-panel}

Registreer de onderstaande Pro-plugin in een bestaand Filament 4- of 5-paneel.
Heeft je app nog geen paneel, installeer dan eerst de interfacepakketten en maak er één aan:

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

Dit voegt het **SEO-dashboard** toe, met een actie om alles te scannen, live
voortgang en een bevindingenlijst waarin je met één klik opnieuw scant.
Daarnaast krijg je **redirectbeheer** en de **404-monitor** met de actie
*Redirect aanmaken*. `rankbeam/laravel-seo-filament` voegt ook de
[sectie met SEO-velden](/nl/guide/filament) toe aan je resourceformulieren.

## Problemen oplossen {#troubleshooting}

| Resultaat | Volgende stap |
|---|---|
| Composer weigert de inloggegevens | Controleer het licentie-e-mailadres en de sleutel voor `blog.rankbeam.dev`. Houd inloggegevens buiten versiebeheer. |
| Doctor meldt ontbrekende tabellen | Volg de Snelstart voor Core en voer daarna `seo-pro:install` en `migrate` uit op dezelfde database als de app. |
| Een scan verwerkt geen doelen | Controleer de registratie in je provider en of het model records bevat. |
| Een scan in de queue blijft wachten | Start de geconfigureerde queueworker of gebruik `--sync` voor een directe controle. |
| Een doel mislukt | Controleer de uitvoeringsdetails, routenamen en applicatie-URL voordat je opnieuw scant. |
| Het dashboard ontbreekt | Registreer `SeoProPlugin` in het paneel dat je daadwerkelijk gebruikt en controleer de toegangsgates. |

Zie [Productieconfiguratie](/nl/pro/production) voor workerherstel en dagelijks beheer.

## Licentie en terugbetalingen {#license}

De early-adopterlicentie kost eenmalig €179 en dekt maximaal vijf productieprojecten, inclusief klantprojecten, met levenslange updates. Ontwikkel- en stagingkopieën van die projecten tellen niet apart mee. Installatie- en migratiehulp, een installatiegesprek van 60 minuten en het geadverteerde startpakket zijn inbegrepen. Je kunt binnen 30 dagen zonder voorwaarden een volledige terugbetaling aanvragen via je aankoopbewijs of door te mailen naar valentinogoxhaj@gmail.com. Stop na een volledige terugbetaling met het gebruik van Pro. Je mag Pro aanpassen voor je gelicentieerde projecten, maar de broncode niet publiceren of Pro als zelfstandig pakket of starterkit doorverkopen. Het pakket bevat de volledige licentievoorwaarden.

Gebruik Pro in maximaal vijf productieprojecten, inclusief klantprojecten. Ontwikkel-, staging- en testkopieën van die projecten tellen niet apart mee. Levenslange updates omvatten toekomstige Pro-releases, maar geen doorlopende persoonlijke implementatiewerkzaamheden.

Eén gesprek van 60 minuten voor installatie en configuratie en metadatamigratie voor één eerste project zijn inbegrepen. De migratie dekt ondersteunde bronnen. We bevestigen de omvang voordat we beginnen; voor maatwerk aan de applicatie maken we een aparte offerte. Het startpakket omvat het controleren en configureren van llms.txt, AI-crawlerregels in robots.txt en Markdown-responses voor bots in datzelfde project, met functies uit de gratis Core. Mail hello@rankbeam.dev om de inbegrepen hulp af te spreken.

Voor je bestelling geldt het aanbod dat op het moment van aankoop werd getoond.

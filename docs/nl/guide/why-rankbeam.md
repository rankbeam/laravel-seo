---
title: Wat is Rankbeam? Uitleg over SEO-infrastructuur voor Laravel
description: "Wat Rankbeam is: open-core SEO-infrastructuur voor Laravel — een gratis MIT-core voor metadata, canonieke URL's, JSON-LD, sitemaps en crawlerbeheer, met een commerciële Pro-engine voor monitoring en een optionele Filament-interface."
---

# Wat is Rankbeam? {#what-is-rankbeam}

**Rankbeam is open-core SEO-infrastructuur voor Laravel: een gratis MIT-core voor
metadata, canonieke URL's, sociale kaarten, gekoppelde JSON-LD, sitemaps en crawlerbeheer, met optionele commerciële Pro-monitoring en workflows.** Het is geen
losse helper die tijdens runtime tags aan de app toevoegt. Het bepaalt SEO vanuit je eigen
modellen en configuratie, rendert dezelfde getypeerde gegevens als Blade, een Inertia-head of een
JSON-API en blijft die met Pro ook na de deployment controleren.

## De pakketfamilie {#the-package-family}

Rankbeam bestaat uit drie pakketten met één gedeelde ondersteuningsmatrix:

| Pakket | Licentie | Wat het is |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, gratis** | de core — metadatabepaling, de gekoppelde JSON-LD-schemagraaf, XML-sitemaps, crawlerbeheer, de gratis `seo:audit` en de importers |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, gratis** | Formuliervelden en livevoorbeelden voor Filament 4/5 die naar `seo_meta` van de core schrijven |
| `rankbeam/laravel-seo-pro` | **commercieel** | de beheerengine — scans via wachtrijen met een score van 0–100, redirectbeheer, een 404-monitor zonder IP-opslag, een crawler voor kapotte links, Search Console-inzichten en AI-ondersteuning met je eigen sleutel |

De afbakening is bewust gekozen. Alles wat de gerenderde pagina uitvoert, valt onder MIT en blijft
altijd gratis; je betaalt voor de laag voor **audits en monitoring** in productie.
De commerciële Pro-versie is een eigen pakket en wordt nooit in de gratis core meegeleverd.

## Voor wie het is {#who-it-s-for}

Rankbeam wordt nuttig wanneer SEO **opgeslagen, aan modellen gekoppeld, meertalig,
headless en gecontroleerd** is — een Laravel-applicatie in productie met dynamische of
modelgebaseerde content. Voor een handvol statische pagina's die alleen een titel en
beschrijving nodig hebben, past een kleine runtime-helper voor metadata beter. De toelichting hieronder over
[wanneer een samengestelde stack nog prima werkt](#what-is-honestly-not-in-the-free-core)
zegt dat ook expliciet.

## Ondersteunde versies {#supported-versions}

Eén matrix voor de hele familie:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13 (Laravel 13 vereist PHP 8.3+)
- **Filament** 4 / 5 (optioneel)

## Wat Rankbeam niet vervangt {#what-rankbeam-doesn-t-replace}

Rankbeam coördineert de eigen SEO-uitvoer van een Laravel-app. Het is geen gehoste tool om zoekposities
te volgen, geen suite voor zoekwoordonderzoek en geen analyticsproduct. Het belooft
niets over zoekposities, indexering of AI-bronvermeldingen. Voor het genereren van XML-sitemaps
gebruikt het [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap)
in plaats van dat opnieuw te bouwen. Je content, routing en analytics
blijven waar ze zijn.

Nieuw hier? [Installeer de gratis core](/nl/guide/installation) of lees verder voor de
resultaten van een echte overstap in productie. Pro en het early-adopteraanbod staan op
[rankbeam.dev](https://rankbeam.dev/nl/).

## Waarom niet drie pakketten met verbindingscode? {#why-not-three-packages-glue}

De meeste Laravel-apps hebben geen "SEO-pakket". Ze hebben een **SEO-stack**: een
pakket dat metadata per model opslaat, een tweede dat de velden aan
Filament toevoegt, een derde dat pagina's scant en daarbovenop applicatiespecifieke verbindingscode
die de drie op elkaar afstemt. Elk onderdeel is op zichzelf prima. De kosten
zitten in de verbindingen ertussen — en die verbindingscode moet je zelf blijven onderhouden.

Deze pagina toont de resultaten van één echte overstap in productie waarbij precies zo'n
samengestelde stack is verwijderd en vervangen door de Rankbeam-familie. De onderstaande cijfers
zijn gemeten, niet voor marketing bedacht.

## De referentieapp {#the-reference-app}

Een echte Laravel-contentsite in productie (hier geanonimiseerd):

- Een **ziekenhuis-/institutionele contentsite**, ongeveer 3 maanden in productie.
- **Gemigreerd vanuit WordPress**, ongeveer 900 pagina's volgens de sitemap.
- Ongeveer **20.000 bezoeken per dag**.
- **Laravel 12**, een **Filament 4**-beheeromgeving, Blade-frontend en MySQL.

De SEO-stack vóór de overstap:

| Laag | Pakket |
|---|---|
| Metadataopslag (een `seo`-tabel per model) | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Filament SEO-velden | `ralphjsmit/laravel-filament-seo` |
| Paginascanner | `backstage/laravel-seo-scanner` |
| Alles ertussenin | **ongeveer 30 eigen applicatieklassen** |

We verwijderden de drie pakketten, installeerden Rankbeam **core + Pro + Filament**, voerden
de SEO-testsuite uit en de app startte op met **nul door die tests vastgestelde SEO-regressies**. Hieronder staat
wat de verbindingslaag werkelijk kostte en wat er verdween.

## Wat bij de overstap is verwijderd {#what-the-swap-deleted}

De scannerstack vervangen door Rankbeam verwijderde **12 eigen klassen volledig**
— werk waarvoor de app niet langer verantwoordelijk is omdat de pakketfamilie het equivalent levert:

| Verwijderde applicatieklasse | Wat het was | Nu geleverd door |
|---|---|---|
| `Services/SeoService.php` | wrapper voor het SEO-ingangspunt van de app | core-resolver + `SEO`-facade |
| `Services/SeoWarningEvaluator.php` | drempels voor titel-/beschrijvingslengte en afbeeldingsafmetingen | `SEOWarningEvaluator` uit de core (gedeeld door audit, voorbeeld en scan) |
| `Services/Seo/SeoAssetInspector.php` | lokale controle van afbeeldingsafmetingen | `LocalImageInspector` uit de core |
| `Jobs/ScanAllPagesSeo.php` | dispatch van sitescans via wachtrijen | [scanpipeline](/nl/pro/scan-issues) van Pro via wachtrijen |
| `Jobs/ScanPageSeo.php` | scan per pagina | `PageScanner` van Pro |
| `Jobs/ScanPublicPageSeo.php` | scan per openbare pagina | scanpipeline van Pro |
| `Models/SeoScanBatch.php` | administratie van scanuitvoeringen | `seo_scan_runs` van Pro |
| `Filament/Pages/SeoDashboard.php` | het SEO-beheerdashboard | `SeoDashboard`-plugin van Pro |
| `Filament/Widgets/SeoScanProgressWidget.php` | widget voor scanvoortgang | scanwidgets van Pro |
| `Filament/Widgets/SeoTrendChartWidget.php` | widget voor scantrends | scanwidgets van Pro |
| `Facades/Seo.php` | applicatiefacade rond het opslagpakket | `SEO`-facade van de core |
| `Console/Commands/RecoverLegacySeoMetadata.php` | eenmalig metadataherstel | [importers](/nl/guide/migrate-from-wordpress) uit de core (`seo:import-from`) |

::: info Een eerlijke telling van de rest
Bij de overstap **bleef** de zelfgeschreven crawler voor kapotte links bewust behouden
(ongeveer 17 klassen: de scantaak, checker, seedbuilder, bronresolver, twee modellen,
twee enums, twee events, de Filament-resource met drie widgets en twee opdrachten), samen met
enkele metadata-/schemahelpers (`CustomSEO`, `EntitySeoSection`,
`DynamicSeoDataResolver`, `SitewideSchema`, `SeoKeywords`) — samen ongeveer **22 extra
klassen**. Die werden niet op dag één verwijderd, omdat de vervangingen van Rankbeam
pas daarna beschikbaar kwamen: de [Pro-crawler voor kapotte links](/nl/pro/production) voor de
eigen crawler, het **gerelateerde model als doel** en het **SERP-/sociale voorbeeldweergave** in Filament
voor `CustomSEO`/`EntitySeoSection` en de **schemagraaf** van de core voor
`SitewideSchema`. Met de volledige familie wordt dat maatwerk — in totaal ongeveer
**drie dozijn klassen** — de verantwoordelijkheid van het pakket.
:::

Het punt is niet dat een van die pakketten slecht is. Het gaat om de *integratie*
— de ruim twaalf klassen die ervoor zorgen dat een metadatawijziging doorwerkt in de
scanner, het dashboard en de gerenderde head. Dat is maatwerkcode zonder upstream,
zonder andere tests dan die van jou en zonder bugmeldingen van anderen.

## Naast elkaar {#side-by-side}

| Functie | Samengestelde stack (3 pakketten + verbindingscode) | Rankbeam-familie |
|---|---|---|
| Metadataopslag per model | metadatapakket | **core** (`seo_meta`, MIT) |
| **Locale-afhankelijke** opslag | meestal eigen verbindingscode | **core** — `seo_meta` is via een kolom aan een locale gekoppeld |
| Filament SEO-velden | Filament-SEO-pakket | **`laravel-seo-filament`** (MIT) |
| SEO voor een **gerelateerd** model bewerken | zelf de veldcomponent omhullen | ingebouwde `target:`-resolver |
| Live voorbeeldweergave voor **SERP en sociale media** | zelfgebouwde Blade/Alpine-interface | ingebouwd redactioneel voorbeeld met tabbladen |
| Headless rendering (Inertia / Livewire / JSON) | de referentieapp gebruikte Blade; andere stacks vereisen integratie | **één resolver** → Blade, Inertia, Livewire, JSON ([getest tegen een gedeeld contract](/nl/contributing/rendering-contract)) |
| Paginascanner + problemen op prioriteit | scannerpakket | **Pro**-[scanpipeline](/nl/pro/scan-issues) + `IssueRegistry` |
| Score van 0–100 | verbindingscode / geen | **Pro**, transparante [beoordelingsregels met versienummer](/nl/pro/scoring) |
| Redirects + 404-herstel | nog een pakket / maatwerk | **Pro**-redirectbeheer + 404-monitor zonder IP-opslag |
| Crawler voor kapotte links | maatwerk (de app bouwde zijn eigen crawler) | begrensde, hervatbare crawler van **Pro** |
| JSON-LD-schema**graaf** | een builder + eigen `@id`-koppelingen | **core**, onderling gekoppelde Organization/WebSite/WebPage-graaf |
| XML-sitemaps | sitemappakket | sitemapregister van de **core** (gebruikt `spatie/laravel-sitemap`) |
| Import uit WordPress / Yoast / Rank Math | eenmalige scripts | **core** `seo:import-from` + een [draaiboek](/nl/guide/wordpress-migration-runbook) |
| **Wie onderhoudt de verbindingen?** | **jij** | de pakketfamilie, één releaselijn |

## Drie dingen die verbindingscode niet goed kan {#the-three-things-glue-can-t-do-well}

**1 — Eén samenhangende familie, één releaselijn.** Drie pakketten hebben drie
beheerders, drie wijzigingslogboeken en drie updateritmes; de verbindingscode moet
de verschillen ertussen opvangen. Rankbeam core, Pro en Filament krijgen hun versies
binnen één [ondersteuningsmatrix](#tested-where-it-runs) met gedocumenteerde
[upgradegrenzen](/nl/reference/configuration). Een gedragswijziging wordt
op één plek aangekondigd, in plaats van pas ontdekt te worden wanneer twee pakketten elkaar tegenspreken.

**2 — Locale-afhankelijke opslag als kolom, niet als conventie.** `seo_meta` is
polymorf **en** op opslagniveau aan een locale gekoppeld. Meertalige SEO gebruikt
één rij per `(model, locale)`, geen geserialiseerd gegevensblok of koppeltabel die je zelf
moest toevoegen. De [voorrangsvolgorde van de resolver](/nl/concepts/resolver-precedence) leest de actieve
locale rechtstreeks.

**3 — Headless rendering vanuit één resolver.** Rankbeam bepaalt getypeerde `SEOData` en rendert *dezelfde* gegevens als HTML,
een Inertia-`Head`-payload of een JSON-array — bewezen met een gedeeld
[renderingcontract](/nl/contributing/rendering-contract) voor
[Blade](/nl/guide/blade), [Inertia](/nl/guide/inertia-json) (Vue/React/Svelte) en
[Livewire](/nl/guide/livewire). Een beheerpaneel is niet vereist: elke Pro-functie werkt ook
[headless via Artisan](/nl/pro/headless).

## Wat *niet* in de gratis core zit {#what-is-honestly-not-in-the-free-core}

Rankbeam is open-core en de afbakening is bewust gekozen — zodat je precies weet wat
je krijgt voordat je `composer require` uitvoert:

| Pakket | Licentie | Wat erin zit |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, gratis** | metadatabepaling, JSON-LD-schemagraaf, sitemaps, de gratis `seo:audit` en importers |
| `rankbeam/laravel-seo-filament` | **MIT, gratis** | Filament-formuliervelden/-secties die naar `seo_meta` schrijven |
| `rankbeam/laravel-seo-pro` | **commercieel** | scans via wachtrijen + problemen op prioriteit + score van 0–100, redirects, 404-monitor, crawler voor kapotte links, Search Console, AI-ondersteuning en het Filament-dashboard |

Je betaalt dus voor de **technische SEO-audit** en de **sitemonitoring**: scans, score, redirects, 404-herstel en de crawler. De
metadata-engine, schemagraaf, sitemaps en gratis audit binnen het proces vallen onder MIT
en blijven gratis.

Twee eigenschappen die ook bij nadere controle overeind blijven:

- **Geen licentiecontrole tijdens gebruik.** Pro wordt bij installatie per project gelicentieerd;
  er is geen automatische terugkoppeling naar Rankbeam en geen uitschakelmechanisme dat je app kan stilleggen. (Pro genereert
  *lokale* operationele telemetrie voor je eigen logs — uitschakelbaar en nooit naar ons.)
- **AI met je eigen sleutel.** De optionele [AI-ondersteuning](/nl/pro/ai-assist) gebruikt *jouw*
  sleutel voor Anthropic, OpenAI, Google of een lokaal model. Niets loopt via een proxy van Rankbeam, wordt door ons gemeten of
  doorverkocht, en de functie staat standaard uit.

::: tip Wanneer een samengestelde stack nog prima werkt
Als je alleen een `<title>` en beschrijving nodig hebt voor een handvol statische pagina's, is een
runtime-tagbuilder voldoende. Rankbeam wordt nuttig wanneer SEO **opgeslagen**,
**meertalig**, **modelgekoppeld**, **headless** en **gecontroleerd** is — zodra
de verbindingen tussen pakketten echte code worden die je zelf onderhoudt.
:::

## De overstap met het minste risico: weg van WordPress {#the-lowest-risk-switch-off-wordpress}

De referentieapp was een WordPress-migratie van ongeveer 900 pagina's. Die doelgroep heeft
het meest te verliezen: jaren aan Yoast-/Rank Math-optimalisatie. Rankbeam behandelt dat als
een veilige route, niet als een sprong in het diepe:

1. **Naast elkaar draaien.** Zet Rankbeam naast de live site neer; verwijder nog niets.
2. **Importeren (eerst een dry-run).** `seo:import-from yoast` / `rank-math` /
   `wordpress-csv` leest je titels, beschrijvingen, canonieke URL's, robots, focuszoekwoorden en sociale overschrijvingen. De importers zijn **idempotent** en
   **vullen standaard alleen lege velden** — zonder `--overwrite` behouden ze metadata die je al hebt ingesteld, en
   `--dry-run` schrijft niets weg.
3. **Redirects overdragen.** De core genereert een redirect-CSV met versienummer; de
   `seo-pro:redirects-import` van Pro valideert elke rij (en weigert lussen, onveilige
   doelen en duplicaten) voordat er iets wordt opgeslagen.
4. **Controleren voordat je iets verwijdert.** `seo:audit --strict` is een CI-/overstapcontrole die bij elk probleem met een niet-nul-exitcode eindigt. De oude WordPress-database blijft
   onaangeroerd totdat je besluit haar te verwijderen.

De volledige procedure staat in het [WordPress-migratiedraaiboek](/nl/guide/wordpress-migration-runbook);
de veldtoewijzing en tokenverwerking staan bij
[Migreren vanuit WordPress](/nl/guide/migrate-from-wordpress). Stap je in plaats daarvan over vanuit een
**Laravel** SEO-pakket (ralphjsmit, artesaos, Spatie)?
[Bekijk de gids voor pakketmigratie](/nl/guide/migrate-from-other-packages).

## Blijft het werken op grotere schaal? {#does-it-hold-up-at-scale}

De twee zwaarste eisen van de referentieapp — een resolver bij elk van ongeveer 20.000
dagelijkse verzoeken en een linkcrawl van ongeveer 900 pagina's — hebben elk een benchmark in de
testsuite. Die bewijzen **deterministische** verbeteringen (queryaantallen en begrenzing van taken),
geen handmatig geoptimaliseerde looptijden:

**Resolvercache — een treffer in een gevulde cache raakt de database nul keer.** Met de
optionele cache voor waardebepaling ingeschakeld slaat een gecachet resultaat de *hele* voorrangsvolgorde over.
De benchmark bepaalt 25 keer de waarden voor hetzelfde model:

| | Databasequery's |
|---|---|
| Zonder cache (elke bepaling leest `seo_meta` opnieuw) | **≥ 25** |
| Treffer in een gevulde cache | **0** |

De cache staat **standaard uit** en is gedocumenteerd als hulpmiddel voor schaalvergroting; invalidatie
verwijdert de juiste vermeldingen wanneer `seo_meta`, een contentveld of je standaardwaarden veranderen.
Zie [Configuratie → caching](/nl/reference/configuration).

**Crawler voor kapotte links — begrensd bij 900 pagina's.** De crawlerbenchmark voert een
gegenereerde verzameling van ongeveer 900 pagina's door de echte taak:

- Voltooit het werk via **≥ 18 begrensde taken** (limiet van 50 pagina's per taak).
- **Geen enkele taak** bezoekt meer dan de limiet van 50 pagina's.
- **1.800 links** gecontroleerd; elk onbereikbaar doel wordt een blijvend opgeslagen bevinding met een bevestigde kapotte link.

De crawler heeft eindige limieten per uitvoering en een harde tijdslimiet per taak, met SSRF-validatie bij het ophalen van de start-URL **en elke redirectstap**, plus een databaselease zodat
per bereik maar één uitvoering actief is. Het beheer wordt behandeld in de
[gids voor productieconfiguratie](/nl/pro/production).

## Getest waar het draait {#tested-where-it-runs}

Eén ondersteuningsmatrix voor de hele familie, geen drie:

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13
- **Filament** 4 / 5

## Waarom dan nog drie pakketten aan elkaar koppelen? {#so-—-why-glue-three-packages-together}

Als de samengestelde stack ruim twaalf eigen klassen voor integratie kost, een
releaseritme waar je geen controle over hebt, stackspecifieke renderingintegratie en localeverwerking die je
zelf toevoegt — terwijl een samenhangende, headless familie met ingebouwde localeondersteuning die
verbindingscode wegneemt en zich heeft bewezen in een echte productieapp met 900 pagina's en 20.000 bezoeken per dag — dan is de samengestelde
stack niet langer vanzelfsprekend de veilige keuze.

Begin met de [Snelstart](/nl/guide/quickstart): van `composer require` naar een volledig
gerenderde `<head>` in vijf minuten.

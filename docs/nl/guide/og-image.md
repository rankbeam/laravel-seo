---
description: "Geef elke pagina een eigen Open Graph-afbeelding van 1200×630, gerenderd vanuit Blade door een headless browser die titels netjes laat afbreken en afkapt. Gratis in Core, standaard uit."
---

# Gegenereerde OG-afbeeldingen {#generated-og-images}

Vanaf Core 3.20 schakelt de Chrome-renderer JavaScript uit en blokkeert hij
aanvragen voor assets via HTTP(S), FTP en WebSocket. Eigen templates moeten
statische HTML/CSS en ingesloten assets gebruiken, net als de meegeleverde templates.

Een pagina zonder eigen afbeelding voor sociale media valt terug op één
gedeelde `default_og_image`: dezelfde afbeelding bij elke gedeelde link.
Deze functie geeft elke pagina een **eigen** Open Graph-/Twitter-kaart van
1200×630, gerenderd vanuit een Blade-template door een echte headless browser
via [spatie/browsershot](https://github.com/spatie/browsershot). Titels kunnen
daardoor over meerdere regels lopen, accenten worden weergegeven, CJK valt
terug op het juiste lettertype en te lange titels worden netjes afgekapt.
Een zelfgebouwde afbeeldingsbibliotheek regelt dit niet allemaal vanzelf.

Dit is een gratis Core-functie die **standaard uitstaat**. Zolang ze uitstaat,
blijft `default_og_image` ongewijzigd in gebruik en krijgt het pakket geen extra afhankelijkheden.

::: info Bewust vooraf statisch gegenereerd
Kaarten worden vooraf gegenereerd met een Artisan-commando, niet tijdens een
webaanvraag. Een pagina verwijst alleen naar een kaart die al op schijf bestaat.
Een bezoekersaanvraag start dus nooit een browser en verwijst nooit naar een
ontbrekende afbeelding met een 404. Er is **geen endpoint voor live rendering**.
Zie [Aandachtspunten](#caveats).
:::

## Vereisten {#requirements}

De browserdriver is een optionele afhankelijkheid: de gratis Core wordt zonder
deze driver geïnstalleerd. Om de functie te gebruiken, heb je in je applicatie het volgende nodig:

```bash
composer require spatie/browsershot
```

Daarnaast heb je de runtime nodig die Browsershot aanstuurt:

- **Node.js** op de host.
- **Puppeteer**, geïnstalleerd in de **hoofdmap van je applicatie**, zodat Node het kan vinden:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium** — Puppeteer downloadt standaard een eigen Chromium.
  In productie verwijs je meestal naar een Chrome-installatie op het systeem.
  Zie [`chrome_path`](#configuration).

::: warning Installeer puppeteer op Windows in de hoofdmap van de app
Installeer `puppeteer` op Windows in de hoofdmap van je applicatie en vertrouw
niet op `npm_module_path`. Die configuratiesleutel komt overeen met Browsershots
`setNodeModulePath()`, dat een POSIX-voorvoegsel `NODE_PATH=…` toevoegt en **op Windows
geen effect heeft**. Node zoekt modules daar door vanuit de applicatie de
bovenliggende mappen af te lopen. Installeren in de hoofdmap werkt daarom wel.
Zie [Aandachtspunten](#caveats).
:::

## Inschakelen {#enabling}

Publiceer de configuratie als je dat nog niet hebt gedaan (`php artisan vendor:publish --tag=seo-config`)
en schakel de functie in:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

**Genereer** daarna de kaarten **vooraf**. Er wordt niets gerenderd totdat je dit doet:

```bash
php artisan seo:og-images
```

## Hoe de afbeeldingswaarde wordt bepaald {#how-resolution-works}

Een gegenereerde afbeelding overschrijft nooit een afbeelding die je zelf hebt
ingesteld. Als de functie aanstaat, vult de resolver `og:image` **alleen als
de pagina geen eigen afbeelding heeft**. Dat is het geval wanneer de uiteindelijke
`og:image` leeg is of nog de sitebrede statische `default_og_image` bevat.
Een expliciete afbeelding per model, bijvoorbeeld uit `getSEOImage()`, een
`seo_meta`-rij of een contentveld, wint altijd van een gegenereerde kaart.

Om de waarde te bepalen, gebruikt de resolver een opzoekactie van de generator
die **controleert of het bestand bestaat**. Die berekent het opslagpad en geeft
de publieke URL **alleen terug als het bestand al op de geconfigureerde schijf
staat**. De opzoekactie rendert nooit. Daarop berust de beveiliging:

- Een webaanvraag **start nooit een browser**. In het ongunstigste geval verwijst
  de pagina naar de statische `default_og_image`, precies zoals vóór deze functie.
- Een pagina **verwijst nooit naar een nog niet gegenereerde afbeelding**.
  Er is dus geen periode waarin gedeelde links voor hun afbeelding op een 404 uitkomen.

Je overbrugt de tijd tussen gewijzigde content en een beschikbare kaart door
het commando [`seo:og-images`](#the-seo-og-images-command) uit te voeren bij een
deployment en/of volgens een schema.

## Het commando `seo:og-images` {#the-seo-og-images-command}

Dit genereert de kaarten vooraf, zodat de resolver iets kan aanbieden.

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*` — één of meer modelklassen waarvoor je kaarten wilt voorbereiden.
  Herhaalbaar. Zonder deze optie gebruikt het commando `seo.og_image.models`, met je
  [sitemapmodellen](/nl/guide/sitemaps) (`seo.sitemap.models`) als terugvaloptie.
  Dit deelt de sitemapbronnen op dezelfde manier als `seo:llms-txt`.
- `--force` — render bestaande kaarten opnieuw. Gebruik dit na een wijziging
  aan de template of merkkleuren als je `cache_version` niet hebt verhoogd.
- `--prune` — verwijder na het voorbereiden opgeslagen kaarten onder het
  geconfigureerde pad die niet meer overeenkomen met de huidige content van
  een model (zie hieronder). Voor de veiligheid worden alleen bestanden met
  gegenereerde contenthashes als naam verwijderd, nooit andere assets in dezelfde
  map. De optie wordt **genegeerd bij een uitvoering beperkt met `--model`**,
  omdat de lijst te behouden kaarten dan je overige modellen niet dekt.
  Voer het commando dus zonder `--model` uit.

Elk model moet de trait `HasSEO` gebruiken. Records zonder titel worden
overgeslagen, omdat er niets is om op de kaart te zetten. Het commando
rapporteert aantallen voor `generated`, `skipped`, `failed` en,
met `--prune`, `pruned`.

### Inplannen {#scheduling}

Bereid kaarten volgens een schema voor, zodat ze je content blijven volgen.
Ruim ook de verweesde kaarten op die na titelwijzigingen achterblijven:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Het model voor cache-invalidatie {#the-invalidation-model}

De bestandsnaam van een kaart is een **hash van alles wat de pixels beïnvloedt**:
titel, sitenaam, templatenaam, driver, afmetingen, kleuren van het merkverloop,
het nummer `cache_version` **en de geïnstalleerde pakketversie**.

Die hash is de cachesleutel. Dat heeft twee gevolgen:

- **Titel wijzigen → nieuwe hash → nieuw bestand.** De oude kaart is nu
  *verweesd* op schijf. De pagina valt terug op de statische standaardafbeelding
  totdat je de kaarten opnieuw voorbereidt. Het commando genereert de nieuwe
  kaart; `--prune` verwijdert de verweesde kaart. Dit is het invalidatiemodel:
  er is geen afzonderlijke stap om de cache voor één pagina te wissen.
- **`cache_version` verhogen of het pakket upgraden → alle hashes veranderen.**
  Gebruik `cache_version` na het aanpassen van de template of merkkleuren om alle
  kaarten tegelijk ongeldig te maken. Een pakketupgrade wordt automatisch
  meegenomen, zodat een nieuwe release met een aangepaste standaardtemplate
  geen verouderde kaarten kan blijven aanbieden.

## Meegeleverde templates {#bundled-templates}

Het pakket bevat drie templates, alle van 1200×630 met hetzelfde merkverloop:

| Template | Geschikt voor | Toont |
| --- | --- | --- |
| `seo::og.default` | Alles | Titel en sitenaam |
| `seo::og.article` | Blogartikelen, nieuws | Sectielabel boven de titel, titel en een regel met auteur en datum |
| `seo::og.product` | Producten, vermeldingen | Merkcombinatie, categorielabel, titel en beschrijving |

Kies één globale template met `seo.og_image.template` of koppel templates **per modeltype**, zodat
bijvoorbeeld artikelen en producten automatisch verschillende kaarten krijgen:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Een model kan tijdens de uitvoering ook een eigen template kiezen via
`getOgImageTemplate(): ?string`. Geef een viewnaam terug, of `null` om terug te vallen op
de koppeling of standaardwaarde. De volgorde is: modelhook, daarna de koppeling
in `templates`, daarna de globale `template`.

## De template aanpassen {#customizing-the-template}

De kaart is een Blade-view, standaard `seo::og.default`, die naar een zelfstandig
HTML-document wordt gerenderd. Het meegeleverde lettertype wordt als data-URI
ingesloten, zodat de browser geen netwerk nodig heeft. Er zijn twee manieren om dit aan te passen:

**Publiceer de meegeleverde view en bewerk die:**

```bash
php artisan vendor:publish --tag=seo-views
```

Bewerk vervolgens `resources/views/vendor/seo/og/default.blade.php`.

**Of verwijs naar je eigen view:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

De template ontvangt deze variabelen:

| Variabele | Type | Opmerkingen |
| --- | --- | --- |
| `$title` | `string` | De OG-titel indien ingesteld, anders de paginatitel. |
| `$siteName` | `?string` | De uiteindelijke `og:site_name`. |
| `$fontDataUri` | `string` | Het meegeleverde vette lettertype als `data:`-URI. Een lege string als het niet beschikbaar is; de browser gebruikt dan zijn eigen schreefloze lettertype. |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Uitvoerbreedte (standaard `1200`). |
| `$height` | `int` | Uitvoerhoogte (standaard `630`). |
| `$locale` | `?string` | De uiteindelijke paginalocale, voor het attribuut `<html lang>`. |
| `$author` | `?string` | Artikelauteur (gebruikt door `seo::og.article`). |
| `$publishedDate` | `?string` | Publicatiedatum voor `seo::og.article`: ICU-notatie medium in de paginalocale als die beschikbaar is. Anders vertaalt Carbon de maand in de volgorde `M j, Y`. Null als er geen datum is opgegeven. |
| `$section` | `?string` | Contentsectie of categorie (sectielabel bij artikelen, categorielabel bij producten). |
| `$description` | `?string` | De OG-beschrijving, anders de paginabeschrijving (gebruikt door `seo::og.product`). |

::: info De templatenaam maakt deel uit van de cachesleutel
Zowel de **naam** van de template als de verloopkleuren worden in de contenthash
opgenomen. Een andere template of andere kleuren maken bestaande kaarten dus
automatisch ongeldig. Een template *ter plaatse bewerken* doet dat niet, want
de naam blijft gelijk. Verhoog na het bewerken `cache_version` of voer `--force` uit.
:::

## Configuratie {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

De meeste scalaire waarden hebben een bijbehorende omgevingsvariabele:
`SEO_OG_IMAGE_ENABLED`, `SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH`, `SEO_OG_IMAGE_NO_SANDBOX`, enzovoort.
De volledige lijst staat in het configuratiebestand. Sleutels met een array
als waarde (`templates`, `models`, `browsershot_args`, `font_stack`)
bewerk je rechtstreeks in het configuratiebestand.

De schijf moet **publiek toegankelijk** zijn, omdat de resolver de
`url()` ervan gebruikt als waarde voor `og:image`. Gebruik je de
schijf `public`, voer dan één keer `php artisan storage:link` uit, zodat `public/storage` ernaar verwijst.

## Draaien op Linux (de sandbox) {#running-on-linux-the-sandbox}

Op hosts die de sandboxmechanismen van Chrome beperken, kan `php artisan seo:og-images`
mislukken met deze melding:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Een mogelijke oorzaak is de beperking van user namespaces op Ubuntu 23.10+.
Bekijk de [Puppeteer-handleiding voor probleemoplossing](https://pptr.dev/troubleshooting)
en de werkelijke opstartfout van de browser. Pas bij voorkeur de hostconfiguratie
aan, zodat Chrome zijn sandbox kan behouden.

**1. Expliciete terugvaloptie: Chrome starten met `--no-sandbox`.** Dit schakelt
browserisolatie uit. Gebruik dit alleen als je deployment deze afweging bewust accepteert:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Rankbeam rendert statisch gegenereerde HTML en blokkeert externe assetaanvragen.
Die maatregelen vervangen de sandbox van Chrome echter niet. Laat het renderproces
zonder verhoogde rechten draaien, geïsoleerd van andere workloads en geheimen.

**2. De sandbox behouden.** Laat `no_sandbox` uitgeschakeld. Als AppArmor de
oorzaak is, pas dan een profiel aan voor het exacte uitvoerbare Chrome-bestand.
Zie de [Chromium-richtlijnen](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md).
Bijvoorbeeld:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Laad het profiel daarna met `sudo apparmor_parser -r /etc/apparmor.d/chrome-og` en controleer of Chrome met ingeschakelde sandbox start.

::: tip Andere opties
Voeg voor een container met weinig gedeeld geheugen opties toe via
`browsershot_args`. Te weinig gedeeld geheugen is de andere veelvoorkomende Linux-fout:
Chrome crasht dan tijdens het renderen.

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Eigen drivers {#custom-drivers}

`browsershot` is de enige meegeleverde driver, maar de renderer gebruikt een
contract, `Rankbeam\Seo\Contracts\OgImageRenderer`. Registreer je eigen driver, bijvoorbeeld op basis van
canvas of een dienst, en selecteer die met `seo.og_image.driver`:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

Een driver zet alleen een zelfstandige HTML-string om naar PNG-bytes met de
opgegeven afmetingen. Layout en templates vallen niet onder zijn verantwoordelijkheid.

## Lettertypen en niet-Latijnse schriften {#fonts-and-non-latin-scripts}

Het meegeleverde kaartlettertype, Noto Sans Bold onder de OFL, dekt **Latijn,
Cyrillisch en Grieks**. Alle andere schriften — Chinees, Japans, Koreaans,
Thai, Arabisch, Hebreeuws, Devanagari en emoji — komen uit lettertypen die
**zijn geïnstalleerd op de machine waarop `seo:og-images` draait**.
Er worden bewust geen andere lettertypen meegeleverd. Eén CJK-lettertype is
al 16 MB of groter. Chrome kiest per teken automatisch een passend
terugvallettertype zodra dat op de host aanwezig is.

Drie onderdelen maken dit betrouwbaar (3.15):

1. **Een `font-family`-reeks per schrift in elke meegeleverde template.** De
   body vermeldt eerst `'OGBrand'`, het meegeleverde lettertype, daarna
   `seo.og_image.font_stack`: standaard `Noto Sans`, de vier `Noto Sans CJK`-families,
   `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`, `Noto Sans Devanagari` en `Noto Color Emoji`.
   Daarna volgt `sans-serif`. Chrome valt per teken terug op de eerste
   geïnstalleerde familie; ontbrekende families worden overgeslagen, dus de
   lijst kan alleen helpen. De **CJK-familie voor de paginataal wordt vooraan
   gezet**: `ja` → JP, `zh-Hans` → SC, `zh-Hant` /
   `zh-TW` / `zh-HK` → TC en `ko` → KR.
   Hetzelfde Han-codepunt ziet er namelijk per nationaal lettertype anders
   uit (Han-unificatie). Het attribuut `<html lang>` bevat de paginalocale in
   BCP 47-notatie. De lettertypereeks maakt deel uit van de cachesleutel:
   wijzigen zorgt ervoor dat elke kaart opnieuw wordt gerenderd.

2. **Een voorafgaande controle in `seo:og-images`.** Vóór het renderen vraagt het
   commando aan fontconfig (`fc-list :lang=ja`, `th`, `ar`, …)
   of een lettertype de schriften in de titel, sitenaam en beschrijving dekt,
   ook wanneer een schrift maar in een klein deel van gemengde tekst voorkomt.
   Het waarschuwt **één keer per schrift** en vermeldt welk pakket je moet installeren:

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   Waar fontconfig ontbreekt, zoals op Windows, macOS of in een minimale
   container, blijft het stil in plaats van te gokken. Het renderen zelf
   mislukt nooit door een ontbrekend lettertype: Chrome tekent .notdef-vakjes.
   Juist daarom bestaat deze waarschuwing.

3. **Een testvoorbeeld met tekens per schrift in de live smoketest.** Met
   `SEO_OG_IMAGE_LIVE_TEST=1` rendert `tests/Feature/OgImage/BrowsershotSmokeTest.php` een titel in ja, zh-Hans, zh-Hant,
   ko, el, ru, tr, th, ar, he en hi naast een controlebeeld van dezelfde lengte
   met een niet-toegewezen codepunt, dat gegarandeerd vakjes oplevert.
   Als beide PNG's byte voor byte gelijk zijn, faalt de test en vermeldt hij
   het schrift en pakket. Dit is een snelle controle, geen bewijs dat elk
   teken wordt ondersteund. Gemengde Latijnse tekst of andere regelafbreking
   kan verschillende afbeeldingen opleveren terwijl er toch tekens ontbreken.
   Bekijk de werkelijke rendering en de gebruikte lettertypen op de
   deploymenthost. Ook de waarschuwing van FontProbe op taalniveau is een
   voorafgaande controle, geen volledige certificering van lettertypedekking.
   Er is geen Core-commando `seo:doctor`. Gebruik `seo:og-images` voor deze controle.

Op Debian/Ubuntu:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

Eigen templates die vóór 3.15 met `--tag=seo-views` zijn gepubliceerd, blijven
werken. Ze ontvangen de nieuwe variabelen `$fontFamily` en `$lang` en
mogen die negeren.

## Aandachtspunten {#caveats}

Expliciet vermeld, omdat ze in productie problemen kunnen opleveren:

- **Alleen vooraf genereren; geen endpoint voor live rendering (v1).** Er is
  geen route die op aanvraag een kaart rendert. Omdat een webaanvraag niets
  rendert, is er **geen aanvalsoppervlak voor ondertekende URL's, SSRF of DoS
  om hiervoor te configureren of te beveiligen**. De keerzijde is dat je
  [`seo:og-images`](#the-seo-og-images-command) bij deployment en/of volgens een
  schema moet uitvoeren voordat kaarten beschikbaar zijn.
- **`npm_module_path` heeft op Windows geen effect.** Het komt overeen met
  Browsershots `setNodeModulePath()`, dat POSIX-`NODE_PATH=…` vóór het commando zet.
  Windows negeert dat. Installeer `puppeteer` op Windows in de
  **hoofdmap van de applicatie**, zodat Node het via de bovenliggende mappen
  kan vinden. Op Linux en macOS werkt de instelling zoals verwacht.
- **Niet-Latijnse schriften hebben een lettertype op de host nodig.** Zie
  [Lettertypen en niet-Latijnse schriften](#fonts-and-non-latin-scripts).
  Het meegeleverde lettertype dekt Latijn, Cyrillisch en Grieks. De rest komt
  uit lettertypen op het deploymentimage. Het commando meldt wanneer er een ontbreekt.
- **Bij fouten blijft de pagina werken.** Als rendering mislukt door een
  ontbrekend pakket, browsercrash of timeout, meldt het commando dit. De pagina
  behoudt gewoon zijn statische `default_og_image`. Een defecte browser veroorzaakt
  dus nooit een 500-fout voor een pagina.

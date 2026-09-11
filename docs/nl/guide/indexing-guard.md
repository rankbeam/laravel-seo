---
description: "Koppel indexeerbaarheid aan de Laravel-omgeving om staging en lokale kopieën uit zoekresultaten te houden. Buiten je toestemmingslijst worden noindex en crawlerblokkades afgedwongen."
---

# Indexeringsbeveiliging buiten productie {#indexing-guard-non-production-safety-net}

Een staging- of lokale kopie van je site die in Google terechtkomt, is een van de
meest voorkomende en schadelijkste SEO-fouten: dubbele content die met je echte
pagina's concurreert, een privéomgeving in de index en weken opruimwerk met het
hulpmiddel voor URL-verwijdering. Vaak komt dit door een `noindex` die alleen
afhangt van een vergeten instelling in `.env`, of een robots-regel die bij de deployment werd overschreven.

De **indexeringsbeveiliging** maakt dit structureel moeilijker. Koppel
indexeerbaarheid aan de Laravel-*omgeving* in plaats van een instelling die iemand
moet onthouden. Als de applicatie draait in een omgeving buiten je toestemmingslijst,
krijgt elke pagina verplicht `noindex,nofollow`, geeft de beheerde `robots.txt` alle
crawlers de instructie weg te blijven en vermeldt `seo:audit` dat nadrukkelijk.

Dit is een gratis Core-functie.

## Wat er gebeurt wanneer de beveiliging actief is {#what-it-does-when-active}

Wanneer `app()->environment()` **niet** in `seo.indexing_guard.allowed_environments` staat
en de beveiliging is ingeschakeld, gebeuren automatisch vier dingen:

1. **De resolver dwingt `noindex,nofollow` af op elke pagina.** Dit gebeurt
   *boven* de volledige [prioriteitsketen](/nl/concepts/resolver-precedence), zelfs
   boven een expliciete `robots`-waarde die voor een pagina in `seo_meta` is opgeslagen.
2. **Een HTTP-header `X-Robots-Tag: noindex,nofollow`** wordt meegestuurd met elke response
   die via de applicatie loopt. Zie hieronder [Niet-HTML-responses](#non-html-responses-pdfs-feeds-images).
3. **`SEO::robotsTxt()->build()` genereert een `robots.txt` die alle paden uitsluit**, en ook
   een `ai.txt`: eenvoudigweg `User-agent: *` / `Disallow: /`. Dit geldt zowel
   voor het commando `seo:robots-txt` als voor de optionele [dynamische route](/nl/guide/ai-crawlers).
4. **`seo:audit` toont een opvallende melding**, zodat de toestand waarin
   alles op noindex staat nooit een verrassing is wanneer je een rapport leest.

In toegestane omgevingen (standaard `production`) doet de beveiliging
**helemaal niets**: de uitvoer verandert niet en de rendering is byte voor byte gelijk.

## Niet-HTML-responses (PDF's, feeds, afbeeldingen) {#non-html-responses-pdfs-feeds-images}

De afgedwongen **metatag** `robots` bereikt alleen crawlers die HTML verwerken.
Een PDF, RSS/Atom-feed, afbeelding of andere niet-HTML-response heeft geen `<head>`.
Daarom verstuurt de actieve beveiliging dezelfde instructie ook als HTTP-header,
via globale middleware:

```http
X-Robots-Tag: noindex,nofollow
```

De header en de metatag komen uit dezelfde bron en kunnen elkaar dus nooit
tegenspreken. De header staat **standaard aan binnen de beveiliging**. De beveiliging
zelf moet je inschakelen en doet niets in toegestane omgevingen. Schakel de header
uit om alleen de metatag te gebruiken:

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

De middleware wordt **alleen geregistreerd als de beveiliging is ingeschakeld**.
Met de beveiliging uit voegt het pakket dus niets aan je middlewarestack toe.

::: warning Statische bestanden omzeilen PHP
Een bestand dat je webserver rechtstreeks uit `public/` teruggeeft, komt nooit
in Laravel en kan deze header dus niet krijgen. Bescherm zulke bestanden op
webserver- of CDN-niveau. De beveiliging dekt alles wat via de applicatie loopt.
:::

## Waarom de beveiliging een expliciete robots-waarde overschrijft {#why-it-overrides-an-explicit-robots-value}

Overal elders in Rankbeam wint een expliciet opgeslagen waarde. Dat is het hele
doel van de prioriteitsketen. De beveiliging is de bewuste uitzondering: ze staat
*boven* de expliciete laag, omdat het risico hier maar in één richting werkt.

- Een stagingdatabase is meestal een kopie van productie. Een pagina met de
  opgeslagen waarde `index,follow` zou die instructie dus meenemen naar staging en om indexering vragen.
- **Staging ten onrechte indexeren is rampzalig; er ten onrechte `noindex`
  op zetten heeft geen gevolgen.** De beveiliging stelt daarom een grens die de
  opgeslagen waarde niet kan doorbreken, precies in de omgevingen die je toch nooit wilt laten indexeren.

## Inschakelen {#enabling-it}

De beveiliging staat bij levering **uit**. Het installeren of upgraden van het
pakket verandert dus nooit zonder jouw toestemming de uitvoer van een omgeving
buiten productie. Dit is hetzelfde beleid als bij
[`blank_is_unset`](/nl/concepts/resolver-precedence) van de resolver en gegenereerde
OG-afbeeldingen: byte voor byte gelijk totdat je de functie inschakelt. Inschakelen kan met één regel:

```dotenv
SEO_INDEXING_GUARD=true
```

Met de standaardtoestemmingslijst blijft `production` ongewijzigd. Je kunt de
beveiliging dus in gedeelde configuratie ingeschakeld laten. Controleer of de lijst
elke omgeving bevat die je wilt laten indexeren. Inschakelen wordt **sterk aanbevolen**;
de functie komt in aanmerking om in Core 4 standaard aan te staan.

Uitschakelen kan op dezelfde manier met één regel:

```dotenv
SEO_INDEXING_GUARD=false
```

## Kiezen welke omgevingen mogen worden geïndexeerd {#choosing-which-environments-may-index}

Standaard is alleen `production` toegestaan. Vervang de lijst met een
kommagescheiden omgevingsvariabele:

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

Of in `config/seo.php`:

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

Vermeldingen worden vergeleken met `Str::is()`. **Jokertekens** werken dus:
`'prod*'` komt overeen met `production` en `prod-eu`:

```php
'allowed_environments' => ['prod*'],
```

Een **lege** lijst betekent dat *geen enkele* omgeving mag worden geïndexeerd:
de beveiliging is dan overal actief en kiest bij twijfel de veilige kant.
Een lege of uitsluitend uit witruimte bestaande waarde voor `SEO_INDEXING_GUARD_ALLOWED` valt
echter terug op `['production']`. Zo kan een typefout nooit stilzwijgend productie
uit de index halen. Zet expliciet `[]` in de configuratie als je echt
overal de beveiliging actief wilt hebben.

## Controleren {#verifying-it}

`seo:audit` toont de melding en bevat met `--json` ook de machinaal leesbare toestand:

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

De aangeboden of gegenereerde `robots.txt` in een beveiligde omgeving:

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Reikwijdte {#scope}

De beveiliging regelt **indexeringsinstructies**: de metatag `robots`, de
header `X-Robots-Tag` en `robots.txt`. Ze verandert niets aan titels, beschrijvingen,
canonieke URL's of schema en staat los van het
[beleid voor robots-rendering](/nl/concepts/resolver-precedence)
(`seo.robots.emit_default`). Omdat `noindex,nofollow` afwijkt van de standaardwaarde van de site,
wordt deze altijd als tag weergegeven.

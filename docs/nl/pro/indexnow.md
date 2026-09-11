---
description: "Meld zoekmachines zodra een URL wordt gepubliceerd of bijgewerkt. Pro verstuurt naar het gedeelde api.indexnow.org-endpoint, dat doorgeeft aan deelnemende zoekmachines. Standaard uit."
---

# IndexNow: melden bij publicatie {#indexnow-—-push-on-publish-indexing}

In plaats van te wachten tot een crawler een gewijzigde pagina ontdekt,
kun je met **IndexNow** zoekmachines *vertellen* dat een URL is gepubliceerd
of bijgewerkt. Pro verstuurt naar het gedeelde endpoint `api.indexnow.org`,
dat de melding met één aanvraag **aan alle deelnemende zoekmachines
doorgeeft**. Er zijn geen afzonderlijke aanvragen per zoekmachine.
De [officiële FAQ](https://www.indexnow.org/faq) noemt Amazon, Bing, Naver,
Seznam, Yandex en Yep. Een melding garandeert geen indexering.

De functie staat **standaard uit**. Er vindt geen netwerkverkeer plaats
totdat je haar inschakelt en een URL indient.

## Instellen {#setup}

### 1. Een sleutel genereren {#_1-generate-a-key}

IndexNow gebruikt een **sleutel** om te controleren of je de host beheert.
Pro accepteert 8–128 tekens uit `[a-f0-9-]`; een hexadecimale string van
32 tekens is ideaal. Genereer de sleutel één keer, houd hem stabiel en
maak hem beschikbaar via de omgeving:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip De sleutel wordt via configuratie gelezen en blijft beschikbaar na `config:cache`
Anders dan de Search Console-inloggegevens is de IndexNow-sleutel **geen
geheim**. Hij wordt publiek aangeboden op `/{key}.txt` als bewijs dat je
de host beheert. Pro leest hem daarom via de configuratielaag:
`indexnow.key`, standaard `env('SEO_PRO_INDEXNOW_KEY')`. Dat is bewust. Waarden die **alleen
in `.env`** staan, zijn na `config:cache` niet beschikbaar voor
`env()`, omdat Laravel dat bestand dan niet meer laadt.
Echte omgevingsvariabelen van het proces blijven wel beschikbaar.
Via configuratie wordt de sleutel door `config:cache` vastgelegd en blijft
hij beschikbaar. De keerzijde: **bij sleutelrotatie moet je
`php artisan config:cache` opnieuw uitvoeren**. De sleutel wordt nooit gelogd.
Zie [Servers met configuratiecache](#config-cached-servers) als het
sleutelbestand in productie een 404 geeft.
:::

### 2. Het sleutelbestand aanbieden {#_2-serve-the-key-file}

IndexNow haalt `https://{host}/{key}.txt` op, met alleen de sleutel als inhoud, om
het beheer van de host te verifiëren. Als `route` aanstaat,
wat standaard het geval is, **biedt Pro dit bestand voor je aan**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Alleen het geconfigureerde sleutelpad geeft het bestand terug. Andere
paden die deze route opvangt geven een 404; als IndexNow uitstaat,
geeft de hele route een 404. Wil je het bestand zelf hosten of op een
CDN zetten? Schakel `route` uit en verwijs met `key_location` naar je URL.

## URL's indienen {#submitting-urls}

### Automatisch bij opslaan: melden bij publicatie {#automatically-on-save-the-push-on-publish-path}

Voeg de trait toe aan een model en schakel `auto_submit` in.
Elke opslagactie zet een indiening van de `getUrlForSEO()` van het model in de queue:

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

De trait respecteert een publicatievoorwaarde. Implementeer `shouldSubmitToIndexNow(): bool`
voor volledige controle. Anders valt de trait terug op het attribuut
`is_published`; ontbreekt ook dat, dan dient hij bij elke opslagactie in.
Indienen gaat altijd **via de queue**, zodat opslaan nooit op het netwerk wacht.

### Handmatig {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

`submit()` gebruikt standaard de queue. Geef `queue: false` mee voor directe uitvoering.

### Via de opdrachtregel {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Alleen dezelfde host
Elke URL wordt gecontroleerd op `http(s)` **en** of die bij de
geconfigureerde `host` hoort. Andere URL's worden **weggelaten**:
wel geteld, nooit verstuurd. Je kunt alleen URL's van je eigen host
indienen; het endpoint zou een afwijkende host bovendien weigeren.
Lijsten groter dan `max_urls_per_request`, de protocollimiet van 10.000,
worden automatisch in delen opgesplitst.
:::

## Configuratie {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## Hoe herhaalde pogingen werken {#how-retries-work}

De job `SubmitToIndexNowJob` in de queue herhaalt alleen fouten die daarvoor
in aanmerking komen. `429` (aanvraaglimiet bereikt), `5xx`
of een timeout wordt met `backoff` maximaal `tries` keer
opnieuw geprobeerd. `400`/`403`/`422`, een
blijvende clientfout zoals een verkeerde sleutel of afwijkende host,
wordt gelogd en **stopt** de verwerking zonder nutteloze herhalingen.
Zowel `200` als `202` geldt als succes: ontvangen,
respectievelijk in afwachting van sleutelcontrole.

Geef de job in productie een **eigen queue**, zodat een traag endpoint
nooit werk voor gebruikers vertraagt:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Problemen oplossen {#troubleshooting}

### Servers met configuratiecache {#config-cached-servers}

Als `/{key}.txt` in productie een 404 geeft, of indieningen stilzwijgend
niets doen, terwijl `indexnow.enabled` duidelijk op `true` staat,
is de oorzaak bijna altijd een sleutel die **alleen in `.env`**
staat op een server met `php artisan config:cache`. Laravel leest `.env`
niet meer nadat de configuratie is gecachet. `env('SEO_PRO_INDEXNOW_KEY')` geeft dan
`null` terug, de sleutelroute wordt niet geregistreerd en elke
indiening wordt als niet-geconfigureerd geweigerd.

De standaardconfiguratie leest `indexnow.key` uit `env(...)`.
Bij een normale inrichting wordt de waarde dus tijdens het opbouwen
van de cache vastgelegd en werkt dit vanzelf. Het gaat alleen mis
als je **de configuratie hebt gepubliceerd en de standaardwaarde
`env(...)` hebt verwijderd**, of de sleutel onder een **eigen naam
`key_env` zet die alleen in `.env` bestaat**.
Er zijn twee oplossingen:

1. **Bewaar de sleutel in de configuratie** (aanbevolen). Laat
   `indexnow.key` op `env('SEO_PRO_INDEXNOW_KEY')` staan, of stel een letterlijke waarde
   in, en voer `php artisan config:cache` opnieuw uit. Bij latere sleutelrotatie
   moet de configuratie opnieuw worden gecachet.
2. **Stel een echte omgevingsvariabele in.** Maak `SEO_PRO_INDEXNOW_KEY`
   beschikbaar als echte besturingssysteem-/procesvariabele, bijvoorbeeld
   via `env[...]` in een PHP-FPM-pool, `Environment=` in systemd
   of de omgevingsinstellingen van je platform. Zet hem **niet alleen
   in `.env`**. Echte systeemvariabelen blijven leesbaar als
   de configuratie gecachet is.

Voer `php artisan seo:doctor` uit om dit te bevestigen. Het meldt
**“IndexNow is enabled but no valid key resolves”** (IndexNow is ingeschakeld, maar er is geen geldige sleutel beschikbaar), met de concrete
oplossing, als het deze toestand vindt. Pro logt ook één keer per
proces een waarschuwing als de app opstart met gecachete configuratie
en een onleesbare sleutel.

::: tip Google
Google doet **niet** mee aan IndexNow. Gebruik voor Google de
[Search Console-integratie](/nl/pro/search-console) en een actuele sitemap.
:::

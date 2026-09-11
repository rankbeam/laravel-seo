---
description: "Een Google Search Console-paneel dat uitsluitend leest: belangrijkste zoekopdrachten en pagina's met vertoningen, klikken, CTR en positie, gekoppeld aan pagina's die de scanner al kent. Standaard uitgeschakeld."
---

# Search Console (alleen-lezen) {#search-console-read-only}

Een **alleen-lezen** Google Search Console-paneel met je belangrijkste
zoekopdrachten en pagina's, **vertoningen, klikken, CTR en gemiddelde
positie**. Die worden gekoppeld aan de pagina's die de scanner al kent.
Zo zie je op één plek dat *deze pagina problemen heeft **en** vertoningen
verliest*. De functie staat **standaard uit**.

Drie uitgangspunten bepalen het ontwerp:

- **Uitsluitend lezen.** De integratie vraagt één OAuth-scope aan,
  `webmasters.readonly`, vastgelegd in het pakket. Hij kan alleen Search Analytics
  lezen. Hij dient nooit een sitemap in, vraagt geen indexering aan en
  verandert niets in Search Console. Er is geen instelling om het
  rechtenbereik te verruimen.
- **Jouw property, jouw inloggegevens.** Verzoeken gaan rechtstreeks van
  *jouw server* naar Google, geauthenticeerd met *jouw* serviceaccount-
  of OAuth-gegevens. Er wordt niets via een tussenpartij geleid,
  per gebruik afgerekend of doorverkocht, en het pakket stuurt geen telemetrie.
- **Fouten verschijnen op de betreffende plek.** Ontbrekende inloggegevens,
  een 403, quotumfouten of time-outs geven een melding zonder de weergave
  van het paneel te onderbreken. Het historische synchronisatiecommando
  meldt fouten en stopt met het ophalen van volgende dagen, zoals hieronder beschreven.

## Wat je krijgt {#what-you-get}

- **Pagina's die aandacht nodig hebben**: pagina's met **openstaande
  scanproblemen** die **nog zoekverkeer aantrekken**. De grootste kansen
  staan bovenaan, op basis van de meeste vertoningen onder pagina's met
  problemen. Pak die eerst aan.
- **Belangrijkste pagina's** en **belangrijkste zoekopdrachten**:
  de gebruikelijke Search Analytics-tabellen.

In het Filament-dashboard verschijnt een pagina **Search Console** onder
de navigatiegroep *SEO*, alleen als de integratie is ingeschakeld.
Zonder paneel zijn dezelfde meetwaarden beschikbaar via het commando
`seo-pro:search-console` en `SeoPro::searchConsole()`.

## Inrichting {#setup}

Je hebt Google-inloggegevens nodig met leestoegang tot de Search
Console-property. Er zijn twee modi; een **serviceaccount** is voor
een server het eenvoudigst.

### Serviceaccount (aanbevolen) {#service-account-recommended}

1. Schakel in Google Cloud de **Search Console API** in, maak een
   **serviceaccount** en download de JSON-sleutel.
2. Voeg in Search Console bij *Instellingen → Gebruikers en rechten*
   het e-mailadres van het serviceaccount (`…@….iam.gserviceaccount.com`) toe als
   gebruiker. Beperkte toegang is voldoende voor alleen-lezengebruik.
3. Stel in het pakket de sleutel en property in:

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=service_account
# The raw JSON, OR an absolute path to the .json key file:
SEO_PRO_GSC_CREDENTIALS=/etc/secrets/gsc-service-account.json
# The property exactly as it appears in Search Console:
SEO_PRO_GSC_SITE_URL=https://example.com/
# or a Domain property:  SEO_PRO_GSC_SITE_URL=sc-domain:example.com
```

Als `SEO_PRO_GSC_SITE_URL` ontbreekt, wordt een URL-prefixproperty afgeleid van `app.url`.

### OAuth (offline vernieuwingstoken) {#oauth-offline-refresh-token}

Heb je een OAuth-client en een langdurig geldig **vernieuwingstoken**,
bij voorkeur alleen geautoriseerd voor `webmasters.readonly`, configureer die
dan hieronder. Elke vernieuwing vraagt die scope aan. Het pakket weigert
een teruggegeven token tenzij het antwoord expliciet precies de
alleen-lezenscope bevestigt. Het neemt niet aan dat Google een ruimere
toestemming altijd beperkt.

```dotenv
SEO_PRO_GSC_ENABLED=true
SEO_PRO_GSC_CONNECTION=oauth
SEO_PRO_GSC_OAUTH_CLIENT_ID=xxxx.apps.googleusercontent.com
SEO_PRO_GSC_OAUTH_CLIENT_SECRET=...
SEO_PRO_GSC_OAUTH_REFRESH_TOKEN=1//...
SEO_PRO_GSC_SITE_URL=https://example.com/
```

### De tokenmigratie publiceren {#publish-the-token-migration}

De versleutelde toegangstokencache staat in de tabel `seo_gsc_tokens`.
Publiceer en migreer eenmaal:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Controleer de inrichting daarna met `php artisan seo:doctor`. Dit meldt of
Search Console is ingeschakeld en geconfigureerd, zonder netwerkverzoek
en zonder geheime gegevens te tonen.

## Gebruik zonder paneel {#headless-usage}

```bash
# Pages with open issues AND search traffic (the default view):
php artisan seo-pro:search-console

# Top pages / top queries:
php artisan seo-pro:search-console --view=pages
php artisan seo-pro:search-console --view=queries

# Window + size, and machine-readable output:
php artisan seo-pro:search-console --view=queries --days=7 --limit=25 --json
```

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$gsc = SeoPro::searchConsole();

$gsc->isConfigured();             // bool, no network
$gsc->topQueries();               // SearchConsoleResult (rows: GscRow[])
$gsc->topPages(days: 7);          // SearchConsoleResult
$gsc->pagesNeedingAttention();    // rows annotated with issueCount + score

$result = $gsc->topQueries();
if ($result->ok) {
    foreach ($result->rows as $row) {
        // $row->key, ->clicks, ->impressions, ->ctrPercent(), ->position
    }
} else {
    // $result->errorCode (a stable code), $result->errorMessage (sanitized)
}
```

## Historische meetwaarden {#historical-metrics}

Het paneel en commando hierboven lezen een **actueel voortschrijdend
tijdsvenster**; Search Console zelf is de enige opslag. Voor **geschiedenis
per dag** die je voor eerdere perioden kunt opvragen, voer je het
synchronisatiecommando uit. Dat bewaart meetwaarden per dag, zoekopdracht
en pagina in de tabel `seo_gsc_metrics`:

```bash
# Publish + run the migration once (creates seo_gsc_metrics):
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate

# Backfill on the first run, then keep it current — schedule it daily:
php artisan seo-pro:gsc-sync

# Pull a specific number of days back (forces a full re-pull of that window):
php artisan seo-pro:gsc-sync --days=180
```

```php
// app/Console/Kernel.php (or bootstrap/app.php withSchedule)
$schedule->command('seo-pro:gsc-sync')->daily();
```

- **De eerste uitvoering vult eerdere dagen aan** volgens `sync.backfill_days`,
  standaard 90. Search Console bewaart circa 16 maanden; verhoog de waarde
  om meer op te halen. Latere uitvoeringen **hervatten vanaf de laatst
  opgeslagen datum** en halen de laatste `sync.overlap_days` dagen opnieuw op,
  omdat recente Search Console-gegevens later definitief worden.
  Het tijdsvenster eindigt altijd 3 dagen geleden vanwege die vertraging.
- **Idempotent.** Rijen worden ingevoegd of bijgewerkt op `(date, dimension, key)`,
  zodat opnieuw uitvoeren veilig is. Mislukt een dag, bijvoorbeeld door
  een quotumfout, dan stopt de uitvoering netjes en meldt hoeveel rijen
  zijn opgeslagen. De volgende uitvoering hervat waar hij gebleven was.
- **Waarvoor het wordt gebruikt.** De grootste Search Console-veranderingen
  in het [rapport in je eigen huisstijl](/nl/pro/reports) gebruiken
  werkelijke periodevergelijkingen zodra de tabel beide perioden beslaat:
  deze periode tegenover een even lange voorgaande periode. Tot die tijd
  wordt de vorige rapportmomentopname gebruikt. De geschiedenis vormt
  ook de basis voor uitgebreidere zoekwoordinzichten.

Alleen samengevoegde meetwaarden worden opgeslagen: zoektekst, pagina-URL
en de vier waarden klikken, vertoningen, CTR en positie per dag.
Er worden nooit gegevens per gebruiker of per verzoek opgehaald of geschreven.

## Gegevensverwerking en beveiliging {#data-handling-security}

- **Alleen-lezenscope gecontroleerd.** De JWT van het serviceaccount
  vraagt alleen `webmasters.readonly` aan. OAuth-vernieuwingsverzoeken doen
  hetzelfde, en het pakket weigert antwoorden zonder scope of met een
  ruimer bereik. Gebruik inloggegevens die alleen voor lezen zijn
  geautoriseerd. Het pakket bevat geen wijzigende Search Console-aanroep.
- **Inloggegevens blijven in de omgeving.** De serviceaccountsleutel of
  het OAuth-geheim en vernieuwingstoken worden tijdens de aanroep uit de
  **benoemde** omgevingsvariabelen gelezen, net als de AI-sleutel.
  `php artisan config:cache` schrijft ze dus nooit naar `bootstrap/cache/config.php`. Maak ze
  beschikbaar in de procesomgeving als gecachete configuratie het laden
  van `.env` verhindert.
- **Tokens worden versleuteld opgeslagen.** Het kortlevende toegangstoken
  uit je inloggegevens staat **versleuteld met de app-sleutel** in
  `seo_gsc_tokens` en wordt hergebruikt totdat het bijna verloopt. Er is
  dus niet bij elke weergave een tokenuitwisseling nodig. De langdurig
  geldige inloggegevens staan nooit in de database, alleen in je omgeving.
- **Elk verzoek heeft SSRF-beveiliging.** Zowel tokenuitwisseling als
  Search Analytics-aanroepen gaan via de gedeelde `SsrfGuard`:
  alleen HTTPS en een host die naar een openbaar adres verwijst.
  Redirects staan uit, zodat een verzoek niet naar een interne dienst
  kan worden doorgestuurd.
- **Geheimen worden niet gelogd.** Toegangstokens, sleutels en
  authenticatieheaders verschijnen nooit in logs. Een API-fout toont
  alleen Googles eigen opgeschoonde foutmelding met begrensde lengte.
- **Meetwaarden worden lokaal gecachet** gedurende `seo-pro.search_console.cache_ttl`
  seconden, standaard 30 minuten, zodat het paneel niet bij elke
  weergave de API aanroept. Het live paneel en commando bewaren niets
  buiten die cache en het versleutelde toegangstoken. Alleen het
  optionele commando `seo-pro:gsc-sync` slaat meetwaarden permanent op:
  totalen per dag, zoekopdracht en pagina in `seo_gsc_metrics`,
  zonder gegevens per gebruiker.

## Configuratie-overzicht {#configuration-reference}

Alle sleutels staan onder `config/seo-pro.php` → `search_console`:

| Sleutel | Standaard | Doel |
| --- | --- | --- |
| `enabled` | `false` | Hoofdschakelaar (`SEO_PRO_GSC_ENABLED`). |
| `connection` | `service_account` | `service_account` of `oauth`. |
| `site_url` | Afgeleid van `app.url` | De property: `https://example.com/` of `sc-domain:example.com`. |
| `service_account.credentials_env` | `SEO_PRO_GSC_CREDENTIALS` | **Naam** van de omgevingsvariabele met de JSON-sleutel of het pad ernaartoe. |
| `oauth.client_id` | — | OAuth-client-ID, niet geheim. |
| `oauth.client_secret_env` | `SEO_PRO_GSC_OAUTH_CLIENT_SECRET` | **Naam** van de omgevingsvariabele met het clientgeheim. |
| `oauth.refresh_token_env` | `SEO_PRO_GSC_OAUTH_REFRESH_TOKEN` | **Naam** van de omgevingsvariabele met het vernieuwingstoken. |
| `default_days` | `28` | Rapportperiode; eindigt 3 dagen geleden vanwege vertraagde GSC-gegevens. |
| `row_limit` | `100` | Aantal toprijen per rapport; API-maximum 25000. |
| `cache_ttl` | `1800` | Aantal seconden dat een opgehaald rapport wordt gecachet. |
| `sync.backfill_days` | `90` | Dagen opgehaald bij de eerste `gsc-sync` met een lege tabel. |
| `sync.overlap_days` | `2` | Recente dagen die elke uitvoering opnieuw ophaalt vanwege latere afronding. |
| `sync.row_limit` | `5000` | Maximaal aangevraagde rijen per dag en dimensie bij synchronisatie. |

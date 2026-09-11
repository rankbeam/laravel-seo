---
description: "Leg waargenomen AI-crawlerverzoeken op je site vast: welke bots langskwamen, hoe vaak en hun laatste URL en status. De observatiekant van AI-crawlerbeheer."
---

# AI-botmonitor {#ai-bot-monitor}

Verzoeken worden toegeschreven op basis van **overeenkomende user-agents**,
niet op basis van geverifieerde botidentiteit. De monitor registreert
waargenomen verzoeken; een user-agent kan worden vervalst.

[AI-crawlerbeheer](/nl/guide/ai-crawlers) in de core bepaalt wat
`robots.txt` aan AI-crawlers *meedeelt*. De **AI-botmonitor** van Pro
registreert de andere kant: wat ze daadwerkelijk *deden*. Welke AI-crawlers
haalden je site op, hoe vaak, en wat waren hun laatste URL en HTTP-status?

De monitor hergebruikt de techniek van de 404-monitor: globale middleware die
na het antwoord draait, een model dat rijen invoegt of bijwerkt met een
verzoekteller, en dezelfde privacykeuzes. Hij groepeert op **bot** in plaats
van pad en registreert bij **elke** antwoordstatus, juist voor de AI-crawlers
die de 404-monitor bewust uitsluit. Botidentificatie gebruikt de
`AiCrawlerRegistry` uit de core, zodat robots.txt-beleid en waargenomen verkeer
dezelfde bron gebruiken.

::: tip Vereist core ≥ 3.3
De monitor herkent bots via de AI-crawlercatalogus uit de core:
[`SEO::aiCrawlers()`](/nl/guide/ai-crawlers). Met een oudere core blijft hij inactief.
:::

## Inschakelen {#enabling-it}

Standaard uitgeschakeld. Na inschakelen registreert de globale middleware
overeenkomende crawlers na elk antwoord, zonder de pagina te vertragen:

```php
// config/seo-pro.php
'ai_bots' => [
    'enabled' => true,
],
```

Dat is alles. De middleware wordt automatisch geregistreerd; je kunt dit
uitschakelen met `ai_bots.auto_register_middleware`. Per bekende bot wordt één rij ingevoegd
of bijgewerkt, waardoor de catalogus de tabelomvang begrenst.

## Het logboek uitlezen {#reading-the-log}

### Zonder paneel {#headless}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::aiBots()->hits();                    // every bot seen, most-hit first
SeoPro::aiBots()->forPurpose('ai_training'); // just the trainers
SeoPro::aiBots()->totalHits();               // total recorded fetches
SeoPro::aiBots()->isEnabled();
```

Elke rij biedt `bot`, `label`, `operator`, `purpose`,
`hit_count`, `last_path`, `last_status`, `first_seen_at` en `last_seen_at`.

### Artisan {#artisan}

```bash
php artisan seo-pro:ai-bots                       # most-hit first
php artisan seo-pro:ai-bots --purpose=ai_training # filter by purpose
php artisan seo-pro:ai-bots-prune                 # drop stale bots + old daily buckets
```

### Filament {#filament}

Als de Pro-plugin is geregistreerd, verschijnt onder de SEO-navigatiegroep
een tabel **AI-bots** met bot, beheerder, doel, verzoeken, laatste status,
laatste pad en laatst gezien. De tabel is alleen-lezen en filterbaar op doel.

## Privacy {#privacy}

Dezelfde aanpak als bij de 404-monitor: **standaard wordt geen IP-adres
opgeslagen**. De optionele instelling `ai_bots.hash_ip` bewaart alleen een
SHA-256-hash met sleutel (`ip_hash`), nooit het ruwe IP-adres.

```php
'ai_bots' => [
    'enabled' => true,
    'hash_ip' => false,            // true → keyed sha256 only
    'exclude_paths' => ['/filament/*', '/livewire/*', /* … */],
    'max_path_length' => 500,
    'retention_days' => 90,        // lifetime rows; seo-pro:ai-bots-prune; null disables

    // Day-granular per-path buckets (below)
    'daily_enabled' => true,       // false → keep only the lifetime leaderboard
    'daily_max_paths' => 500,      // distinct paths tracked per bot per day
    'daily_retention_days' => 90,  // prune buckets older than this; null disables
],
```

## Meetwaarden per periode (dagtotalen) {#period-metrics-daily-buckets}

De tabel met looptijdtotalen bewaart één rij per bot. Dat is bruikbaar voor
een ranglijst, maar vertelt niet *hoeveel verzoeken* een bot **in een bepaalde periode**
heeft gedaan of *hoeveel unieke URL's* hij daarbij heeft bezocht. Met `daily_enabled` aan,
de standaard, wordt elk verzoek ook per dag en pad vastgelegd in
`seo_ai_bot_daily`. Het [rapport in je eigen huisstijl](/nl/pro/reports) toont
daardoor **werkelijke** periodecijfers: verzoeken sinds het vorige rapport
en unieke URL's in deze periode, in plaats van een verschil tussen looptijdtotalen.

De omvang blijft begrensd, net als bij het oorspronkelijke logboek met één rij per bot:

- Een **maximumaantal unieke paden per bot per dag** (`daily_max_paths`).
  Nieuwe paden boven die grens worden samengevoegd in één overlooprij.
  Het dagtotaal aan verzoeken blijft exact, terwijl het aantal rijen niet
  onbeperkt groeit. Een begrensd aantal unieke URL's wordt als 'N+' weergegeven.
- Een **bewaartermijn** (`daily_retention_days`), toegepast door `seo-pro:ai-bots-prune`.

Zet `daily_enabled` op `false` om alleen de ranglijst met
looptijdtotalen bij te houden. Voor 'sinds het vorige rapport' valt het
rapport dan terug op het verschil met de vorige momentopname. Bestaande
dagtotalen worden genegeerd, zodat verouderde gegevens niet worden gebruikt.

De periodecijfers hebben **dagprecisie**. 'Sinds het vorige rapport' telt
volledige dagen vanaf de dag van dat rapport. Een verzoek op die dag kan
dus vóór of na het precieze generatietijdstip zijn gedaan. Bij een normale
dagelijkse, wekelijkse of maandelijkse frequentie is die grensafwijking
verwaarloosbaar.

## Van waarnemen naar beheren {#turning-observation-into-control}

De monitor laat zien *wie* er crawlt; [AI-crawlerbeheer](/nl/guide/ai-crawlers)
in de core bepaalt *wat je toestaat*. Verschijnt er een crawler voor
modeltraining die je liever beperkt?

```php
// config/seo.php
'ai_crawlers' => [
    'overrides' => ['bytespider' => 'disallow'],
],
```

```bash
php artisan seo:robots-txt
```

Van sommige bots is gedocumenteerd dat ze `robots.txt` niet naleven.
Met de monitor kun je ze waarnemen en bepalen of je ze aan de netwerkrand
wilt blokkeren, via een firewall, WAF of Cloudflare.

---
description: "Plan volledige SEO-scans en zie wat er sinds de vorige veranderde: nieuwe, teruggekeerde en opgeloste bevindingen, geordend op impact, in het dashboard en optioneel per e-mail."
---

# Scans inplannen en verschillen volgen {#scan-scheduling-delta}

Voer volledige SEO-scans **volgens een schema** uit en zie **wat er sinds de
vorige scan veranderde**: bevindingen die nieuw zijn, terugkwamen of zijn
opgelost. Je ziet ze op impact geordend in het dashboard en optioneel in
een samenvattende e-mail.

Twee functies op één pagina, omdat ze samenwerken: juist de veranderingen
maken het de moeite waard om een geplande scan te ontvangen.

## Wat is er veranderd sinds de vorige scan? {#what-changed-since-the-last-scan}

Elke voltooide scan legt zijn **set open bevindingen** vast in een compacte
momentopname (`seo_scan_run_issues`). Vergelijk je de momentopnamen van twee
uitvoeringen, dan krijg je een exact verschil in drie groepen:

- **Nieuw** — een probleem dat eerder niet openstond, nu wel openstaat en
  ook in geen enkele eerdere scan openstond: een werkelijk nieuwe bevinding.
- **Teruggevallen** — een probleem dat was opgelost en **opnieuw is
  verschenen**. Dit betekent niet dat de ernst is toegenomen: de ernst
  ligt per bevindingstype vast. Een regressie betekent hier terugkeer,
  precies wat de [levenscyclus](/nl/pro/scan-issues#issue-lifecycle) heropenen noemt.
- **Opgelost** — een probleem dat in de eerdere scan openstond en nu verdwenen is.

Elke groep wordt [op impact geordend](#impact-ordering), zodat de
belangrijkste bevindingen bovenaan staan.

### Waarom een momentopname en niet de bevindingentabel? {#why-a-snapshot-not-the-issues-table}

Bevindingen hebben een [levenscyclus](/nl/pro/scan-issues#issue-lifecycle)
met oplossen en heropenen. Dezelfde rij wordt tijdens opeenvolgende scans
bijgewerkt; `scan_run_id` verwijst telkens naar de nieuwste uitvoering zolang
de bevinding openstaat. Dat geeft een duurzame geschiedenis, maar de actuele
tabel kan daardoor niet vertellen *welke bevindingen aan het einde van
uitvoering N openstonden*. Een aanhoudende bevinding verwijst alleen naar de
laatste uitvoering.

Elke uitvoering maakt daarom een momentopname van de open set met een
stabiele **vingerafdruk van de bevinding** als sleutel: `issue_type | target | field`.
Dit is dezelfde identiteit waarmee het [rapport in eigen huisstijl](/nl/pro/reports)
verschillen berekent. Het verschil wordt vervolgens berekend met gewone
verzamelingsbewerkingen op twee vastgelegde sets vingerafdrukken.
Dat werkt voor **elke** twee uitvoeringen, niet alleen opeenvolgende.

### Randgevallen en hun betekenis {#edge-cases-handled-honestly}

- **Een pagina valt buiten de scanset.** Haar open bevindingen worden niet
  opnieuw gecontroleerd en blijven daarom open. Ze worden bij elke uitvoering
  opnieuw in de momentopname opgenomen als **nog open**, nooit ten onrechte
  als opgelost. We noemen een pagina niet opgelost alleen omdat we niet meer kijken.
- **Een controle wordt tussen scans uitgeschakeld.** De controle genereert
  geen bevindingen meer, de levenscyclus markeert bestaande bevindingen als
  opgelost en ze verdwijnen uit de open set. Ze worden dus als **opgelost**
  getoond. Dat weerspiegelt het huidige oordeel van de scanner; per bevinding
  is niet te onderscheiden of je het probleem oploste of de controle uitschakelde.
- **De eerste scan na een upgrade.** Uitvoeringen van vóór deze functie
  hebben geen momentopname en worden nooit als vergelijkingsbasis gekozen.
  De eerste scan met een momentopname vormt de **nulmeting**: huidige
  toestand, zonder verschil. Niet je hele site wordt als nieuw gemeld.
  Vanaf de tweede scan met een momentopname zijn verschillen beschikbaar.

### In het dashboard {#on-the-dashboard}

De widget **Wat er veranderd is sinds de laatste scan** op het
[SEO-dashboard](/nl/pro/installation) vergelijkt de twee meest recente
voltooide scans. Hij toont de aantallen nieuwe, teruggekeerde en opgeloste
bevindingen en de belangrijkste uit elke groep, geordend op impact.
Zolang er geen twee scans met momentopnamen zijn, toont hij een korte
melding over de nulmeting.

## Volgorde op impact {#impact-ordering}

Elke groep wordt geordend op een **impactscore**, zodat de grootste
problemen vooraan staan:

```
impact = severity_weight × page_importance
```

- **severity_weight** hergebruikt het gepubliceerde
  [beoordelingsmodel voor de score](/nl/pro/scoring): een kritieke bevinding
  weegt `40`, een waarschuwing `15` en een informatieve
  melding `5`. De ernst is het expliciete productoordeel over hoe
  zwaar een defect weegt. De rangschikking gebruikt dat oordeel in plaats
  van een tweede schaal te verzinnen.
- **page_importance** wordt bepaald door **werkelijke zoekvraag**:
  hoeveel vertoningen de pagina in [Search Console](/nl/pro/search-console)
  krijgt. Dat is het signaal dat pagina's daadwerkelijk van elkaar onderscheidt:

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  Vertoningen worden logaritmisch geschaald: een pagina met 10× zoveel
  verkeer is niet 10× zo belangrijk. De waarde wordt genormaliseerd ten
  opzichte van je drukste pagina, zodat de formule hetzelfde werkt voor
  een kleine blog en een grote catalogus. Sitemap-`<priority>` is alleen
  een **zwak aanvullend signaal**. Standaard is het niet ingesteld en ook
  als het aanstaat, is het een vaste waarde. Het kan de rangschikking
  dus niet dragen, maar geeft een kleine verschuiving als je in
  `seo.sitemap.models` prioriteiten per type hebt ingesteld.

**Geen Search Console is geen probleem.** Zonder gesynchroniseerde
GSC-geschiedenis en zonder ingestelde prioriteiten is `page_importance` voor
elke pagina `1`. Impact volgt dan uitsluitend de **ernst**:
een zinvolle standaard, zonder verzonnen gegevens. Synchroniseer de
[GSC-geschiedenis](/nl/pro/search-console#historical-metrics) met `seo-pro:gsc-sync`
om de zoekvraag mee te wegen.

Pas gewichten en tijdvenster aan onder `seo-pro.scan.delta.impact`.

## Een scan inplannen {#scheduling-a-scan}

Het pakket **plant standaard niets in**. Schakel dit in:

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

Of geef een volledige cronexpressie op voor volledige controle.
Die heeft voorrang op `frequency`:

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

Dat is alles. Het pakket registreert `seo-pro:scan` in Laravels scheduler
met `withoutOverlapping`, om overlappende uitvoeringen van het geplande **commando**
te voorkomen. Deze schedulervergrendeling dekt niet de volledige levensduur
van jobs in de queue. Registratie vindt alleen plaats in een scheduler- of
consolecontext en voegt dus **geen werk aan webaanvragen toe**.

::: warning Een actieve scheduler is vereist
De pakketplanning doet niets als Laravels scheduler niet draait.
Gebruik de standaardcronregel `* * * * * php artisan schedule:run` of tijdens ontwikkeling
`php artisan
schedule:work`. Zie [Productieconfiguratie](/nl/pro/production#scheduler).
:::

Wil je de planning zelf instellen? Laat `schedule.enabled` uit en plan het
commando in vanuit je eigen consolekernel. De verschillen en samenvatting
blijven werken:

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` voert de scan direct uit in plaats van één job per doel in
de queue te zetten. Dat is bruikbaar voor een kleine site zonder queueworker;
laat het in productie uit.

## Samenvattende e-mail {#summary-e-mail}

Schakel desgewenst een e-mail met **veranderingen sinds de vorige scan**
in na afloop van een geplande scan. Je ontvangt een HTML-samenvatting in
je huisstijl met nieuwe, teruggekeerde en opgeloste bevindingen, op impact geordend:

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

De e-mail gebruikt de huisstijl en mailinstellingen van het
[rapport in eigen huisstijl](/nl/pro/reports). Naam, logo en accentkleur
van je bureau worden dus overgenomen. Zonder afzonderlijke ontvangers
worden de rapportontvangers gebruikt. `only_on_change` slaat de e-mail over
als de scan niets heeft veranderd; de eerste nulmetingsscan wordt altijd verstuurd.

De samenvatting wordt alleen verstuurd voor een uitvoering die met
`--notify` is gestart. De scheduler voegt dat automatisch toe wanneer
`notify.enabled` aanstaat. Een losse `seo-pro:scan` **zonder `--notify`**
mailt niemand.

::: tip Een ander kanaal?
Wil je Slack, een webhook of een eigen overzicht in plaats van e-mail?
Luister naar de gebeurtenis `Rankbeam\Seo\Pro\Events\SeoScanCompleted`. Die wordt één keer per
afgeronde uitvoering verstuurd en bevat die uitvoering. Je kunt daarmee
via `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` het verschil opbouwen en naar het gewenste kanaal sturen.
:::

## Bewaartermijnen {#retention}

Momentopnamen worden samen met hun uitvoering verwijderd. Daardoor ruimt
[`seo-pro:scan-prune`](/nl/pro/production#scheduler) ze automatisch op en hoef je
niets extra in te plannen. Een uitvoering wordt pas opgeruimd als ze
geen open bevindingen meer bevat. De momentopname van een recente
uitvoering blijft dus beschikbaar voor vergelijking.

Schakel momentopnamen volledig uit, zonder verschillen of samenvatting,
met `seo-pro.scan.delta.snapshot => false`.

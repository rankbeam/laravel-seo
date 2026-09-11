---
description: "Een PDF-rapport in je eigen huisstijl: score, probleemtrend, opgelost tegenover nieuw, herstelde 404's, veranderingen in Search Console en AI-botactiviteit. Met één commando, eventueel periodiek per e-mail."
---

# Rapporten in je eigen huisstijl {#white-label-reports}

Een **PDF-rapport** voor één site in je eigen huisstijl: totaalscore, de trend in
gevonden problemen, wat er **sinds het vorige rapport is opgelost of bijgekomen**,
herstelde 404's en kapotte links, veranderingen in Search Console en AI-botactiviteit.
Je genereert het met één commando en kunt het **periodiek per e-mail versturen**.
Gemaakt voor bureaus: voeg je logo, kleur en 'opgesteld voor {client}' toe
en stuur het naar de klant.

[Download een gegenereerd voorbeeldrapport in het Engels (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf)
of volg het [praktijkvoorbeeld: scannen → oplossen → rapporteren](/nl/pro/walkthrough).
Het voorbeeld gebruikt vooraf ingevulde Merchant-inhoud en twee nieuwe scans;
het toont één opgelost probleem, 19 openstaande problemen en geen Search Console-gegevens.

[![Eerste pagina van het gegenereerde Merchant-demorapport in het Engels.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## Wat het rapport bevat {#what-s-in-it}

- **Totaalscore** — het gemiddelde van de meest recente scores per pagina
  (volgens de gepubliceerde [beoordelingsmethode](/nl/pro/scoring): A ≥ 90 … F),
  met de verandering sinds je vorige rapport en een **trend van de totaalscore**
  over recente scans. Elke scan slaat nu de sitescore op bij de uitvoering.
  De trend toont dus werkelijke scanresultaten en wordt opgebouwd vanaf de eerste
  scan na je upgrade. Oudere uitvoeringen hebben geen score en worden overgeslagen.
- **Gevonden problemen per scan** — de werkelijke trend over je recente afgeronde
  scans; minder is beter.
- **Opgeloste en nieuwe problemen** — hoeveel gebreken je hebt verholpen en hoeveel
  er sinds het vorige rapport zijn bijgekomen. Dit komt uit de werkelijke
  probleemgeschiedenis, waarin problemen nu een [levenscyclus](/nl/pro/scan-issues#issue-lifecycle)
  met opgelost en heropend hebben, zodra die geschiedenis een volledige periode
  beslaat. Tot die tijd gebruikt het rapport de momentopname van het vorige rapport.
- **Hersteld** — opgeloste kapotte links, **herstelde** 404's (het pad geeft zelf
  weer 200 terug; zie [`seo-pro:404-recheck`](/nl/pro/production#scheduler)) en sinds het
  vorige rapport **doorgestuurde** 404's, plus wat nog openstaat. Een herstelde
  404 is daadwerkelijk bij de bron opgelost en wordt apart van een redirect geteld.
- **Search Console** — belangrijkste zoekopdrachten en pagina's en de grootste
  veranderingen in klikken tegenover het vorige rapport. Dit onderdeel wordt
  overgeslagen als GSC niet is ingesteld.
- **AI-botactiviteit** — verzoeken toegeschreven op basis van de user-agent,
  zonder geverifieerde botidentiteit, en totalen over de hele looptijd.
  Wanneer de [geschiedenis per dag](/nl/pro/ai-bot-monitor#period-metrics-daily-buckets)
  de rapportperiode beslaat, toont het rapport **werkelijke verzoeken in deze
  periode en de unieke URL's die elke bot heeft gecrawld**. Anders gebruikt het
  het verschil tussen de looptijdtotalen in de momentopnamen.

## 'Sinds het vorige rapport' {#since-the-last-report}

Het rapport vergelijkt **opeenvolgende perioden ten opzichte van je vorige
rapport**, niet ten opzichte van een willekeurige datum. Bij elke generatie wordt
een kleine momentopname opgeslagen (`seo_report_runs`): de score, identificaties van
openstaande problemen, Search Console-rijen en de verzoekteller van elke bot.
Het volgende rapport vergelijkt de huidige toestand met die momentopname.

Dit is de terugvaloptie voor gegevens die geen eigen geschiedenis bijhouden:
per pagina wordt alleen de recentste score bewaard. Een momentopname bij het
genereren van het rapport maakt daarvan een eerlijke vergelijking. Verschillende
gegevensbronnen houden inmiddels **werkelijke** geschiedenis bij. Het rapport
gebruikt die bij voorkeur en valt alleen zo nodig terug op de momentopname:
problemen hebben een [levenscyclus](/nl/pro/scan-issues#issue-lifecycle) met opgelost
en heropend, voor echte aantallen opgeloste en nieuwe problemen zodra een volledige
periode is vastgelegd; Search Console bewaart [meetwaarden per dag](/nl/pro/search-console#historical-metrics);
AI-botverzoeken worden in [dagtotalen](/nl/pro/ai-bot-monitor#period-metrics-daily-buckets)
bijgehouden, voor werkelijke verzoeken en unieke URL's per periode. Bij het eerste
rapport na een upgrade, of als de geschiedenis de periode niet beslaat, valt elk
onderdeel terug op het verschil tussen de momentopnamen.

Dit heeft twee gevolgen:

- **Het eerste rapport is de nulmeting.** Het toont de huidige toestand.
  'Opgelost', 'nieuw', verschuivingen en cijfers 'sinds het vorige rapport'
  verschijnen vanaf het *tweede* rapport.
- **Je bepaalt zelf de frequentie.** Genereer je maandelijks, dan beslaan de
  verschillen een maand; wekelijks beslaan ze een week. Gebruik `--no-store`
  voor een tussentijds voorbeeld dat het vergelijkingspunt niet mag verplaatsen.

## Een rapport genereren {#generate-a-report}

```bash
php artisan seo-pro:report
```

Zonder opties schrijft het commando een PDF naar `storage/app/seo-reports/`.
Kies een andere bestemming of verstuur het per e-mail:

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### Opties {#options}

| Optie | Effect |
| --- | --- |
| `--client=` | De klantnaam bij 'opgesteld voor' overschrijven |
| `--agency=` | De bureaunaam op het rapport overschrijven |
| `--accent=` | De accentkleur overschrijven, als hexwaarde, bijvoorbeeld `#3D5AFE` |
| `--logo=` | Het pad naar het logo overschrijven |
| `--email=` | Ontvangersadres, herhaalbaar; verstuurt het rapport per e-mail |
| `--send` | Naar de ingestelde ontvangers mailen |
| `--output=` | De PDF naar dit bestand of deze map schrijven |
| `--no-store` | Geen momentopname opslaan; het vergelijkingspunt voor periodeverschillen blijft staan |
| `--json` | Een machineleesbare samenvatting weergeven |

## Periodieke e-mail instellen {#schedule-the-e-mail}

Het pakket plant zichzelf nooit in; je bepaalt zelf de frequentie.
Voeg dit toe aan de consoleplanning van je app (`routes/console.php` of `app/Console/Kernel.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Stel de standaardontvangers eenmaal in via de configuratie of `.env`:

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` gebruikt deze adressen als terugvaloptie; expliciete
`--email`-opties hebben voorrang.

## Huisstijl {#branding}

Huisstijlgegevens zijn niet geheim en staan daarom in de configuratie. Stel ze
eenmaal in en elk rapport neemt ze over. Je kunt elk veld per rapport
overschrijven met de commando-opties hierboven, handig wanneer één installatie
rapporten voor meerdere klanten maakt.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Aandachtspunten:

- **Logo** — een absoluut pad naar een `PNG`-, `JPG`-,
  `GIF`-, `WEBP`- of `SVG`-bestand. De applicatie leest
  het bestand en sluit het als data-URI in de PDF in. De renderer hoeft de
  afbeelding dus niet via het netwerk op te halen. `PNG` of
  `JPG` is de veiligste keuze.
- **Accentkleur** — wordt gevalideerd als hexwaarde; bij een ongeldige waarde
  wordt de standaard gebruikt. De waarde verschijnt alleen als kleur, nooit als ruwe CSS.
- **Bureaunaam** — gebruikt standaard de naam van je app (`config('app.name')`).

Het volledige configuratieblok staat onder `reports` in `config/seo-pro.php`,
inclusief `paper` (standaard `a4`), `include_gsc` en het aantal
scans voor de trend, GSC-rijen en bots dat wordt opgenomen.

## Eén site per installatie {#one-site-per-install}

Pro scant de applicatie waarin het is geïnstalleerd. Een rapport beschrijft dus
**die installatie**. Een bureau met meerdere klantsites genereert per installatie
een rapport; `--client` en huisstijlopties bepalen het label van elk rapport.
Er is geen multi-tenantmodel met meerdere sites.

## Hoe het werkt {#how-it-s-built}

De PDF wordt standaard met **dompdf** gerenderd: alleen PHP, zonder Node of
headless Chromium. Een ingepland rapport kan daardoor in een queueworker of
cron draaien zonder extra systeembinaries, en Pro blijft zonder paneel bruikbaar.
Extern ophalen is uitgeschakeld in de renderer. De enige afbeelding, je logo,
wordt ingesloten; een weergegeven veld kan dus geen netwerkverzoek starten.

### Rapporten in elk schrift (Browsershot-renderer) {#reports-in-every-script-browsershot-renderer}

Vanaf core 3.20 en Pro 2.40 schakelen de Chrome-renderers JavaScript uit en
blokkeren ze verzoeken voor pagina-assets via HTTP(S), FTP en WebSocket.
Gepubliceerde templates moeten statische HTML/CSS met ingesloten assets gebruiken.
Deze maatregelen gelden voor pagina-assets; Chrome heeft nog steeds een correct
geconfigureerde host en sandbox nodig. De PDF-renderer logt een waarschuwing met
een concrete fontinstallatie-instructie wanneer Fontconfig een ontbrekend schrift
meldt, ook als dat schrift maar in een klein deel van gemengde tekst voorkomt.
Een ontbrekend font verhindert niet dat Chrome een PDF maakt. Bekijk de uitvoer
daarom voordat je een rapport verstuurt.

dompdf gebruikt alleen het ingesloten font, DejaVu Sans, met Latijns, Cyrillisch
en Grieks schrift. Een rapport voor een Japanse, Thaise of Arabische klant toont
daardoor blokjes in plaats van ontbrekende tekens. Sinds Pro 2.34 kan het rapport
ook via `spatie/browsershot` met **headless Chrome** worden gerenderd. Dat is dezelfde
afhankelijkheid die de core voor OG-afbeeldingen gebruikt; je hoeft de machine
dus maar eenmaal in te richten:

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chrome gebruikt de fonts die op de server zijn geïnstalleerd. De template gebruikt
dan de fontstack per schrift uit de core: `Noto Sans`, eerst de
`Noto Sans CJK`-familie voor de paginataal, gevolgd door Thais, Arabisch,
Hebreeuws, Devanagari, kleurenemoji en DejaVu Sans voor het Latijnse schrift.
Installeer de benodigde families — `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji` op Debian en Ubuntu — net als
voor [OG-afbeeldingen](/nl/guide/multilingual#og-images-in-every-script).
`seo:og-images` waarschuwt tijdens een uitvoering als voor het schrift van een
pagina geen fontfamilie is geïnstalleerd. Voor rapporten geldt dezelfde oplossing.
De Blade-template, gegevens en momentopname zijn bij beide engines identiek;
alleen de rasterisatie verschilt. `ReportGenerator::renderer()` toont welke engine is gekoppeld.

### Datums en getallen volgens de locale van de lezer {#dates-and-numbers-in-the-reader-s-locale}

Het rapport legt `seo-pro.reports.locale` vast wanneer het wordt opgebouwd. Bij null wordt
de applicatielocale gebruikt. De gekozen vertaaltaal bepaalt de labels in PDF
en e-mail, het standaardonderwerp, de fontkeuze en het HTML-attribuut `lang`.
Regionale locales zonder eigen vertaalbestand vallen terug op de meegeleverde
basistaal en daarna op Engels. Vereenvoudigd Chinees (`zh_CN`) en
Traditioneel Chinees (`zh_TW`) blijven afzonderlijke talenvarianten.

Datums en getallen volgen de aangevraagde locale via ICU als `ext-intl` is
geïnstalleerd. Stel `seo-pro.reports.format_locale` in om bewust een andere regionale notatie
te kiezen: `locale=it` en `format_locale=en_US` geven Italiaanse labels met
Amerikaanse datum- en getalnotatie. Zonder `ext-intl` blijft de terugval op
Engelse datums en getallen met komma's als scheiding tussen duizendtallen bestaan.

E-mail in de wachtrij behoudt de vastgelegde taal, notatie en het onderwerp, ook
als de workerconfiguratie verandert. Kies de taal voordat je de PDF genereert;
de locale van een mailable achteraf wijzigen vertaalt de bijlage niet. Oude
wachtrijgegevens van vóór Pro 2.39 gebruiken de workerconfiguratie, omdat ze
geen vastgelegde instellingen bevatten. Aangepaste onderwerpen, huisstijlgegevens
en opgeslagen probleemmeldingen blijven brongegevens.

De CLI-weergave staat hier los van: `php artisan seo-pro:report --display-locale=it` vertaalt de samenvatting van
het commando; de rapportconfiguratie bepaalt de taal van de PDF en e-mail voor
de klant. De CLI gebruikt standaard Engels, instelbaar met `SEO_PRO_CLI_LOCALE`.
JSON-sleutels en codes blijven stabiel, terwijl leesbare labels vertaald kunnen
worden. Publiceer `seo-pro-lang` om rapport- en workflowteksten te overschrijven
in `lang/vendor/seo-pro/{locale}/seo-pro.php`.

Haal voor programmatisch gebruik `ReportGenerator` op uit de container:

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```


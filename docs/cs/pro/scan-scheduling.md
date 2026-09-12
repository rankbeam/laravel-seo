---
description: "Spouštějte úplné skeny SEO podle plánu a sledujte změny: nové, vrácené či opravené problémy seřazené podle dopadu v přehledu a volitelném souhrnném e-mailu."
---

# Plánování skenů a změny mezi běhy {#scan-scheduling-delta}

Spouštějte úplný sken SEO **podle plánu** a sledujte **změny od posledního skenu**: problémy, které se objevily, vrátily nebo byly opraveny, seřazené podle dopadu v přehledu a volitelně v souhrnném e-mailu.

Dvě funkce na jedné stránce, protože spolupracují: právě přehled změn dává zprávě z plánovaného skenu smysl.

## Co se změnilo od posledního skenu {#what-changed-since-the-last-scan}

Každý dokončený sken uloží svou **množinu otevřených problémů** do malého neměnného snímku (`seo_scan_run_issues`). Porovnání snímků dvou běhů poskytne přesný rozdíl ve třech skupinách:

- **Nové** — problém dříve otevřený nebyl, nyní je a nebyl otevřený ani v žádném starším skenu. Jde o první výskyt nálezu.
- **Zhoršené (vrácené)** — problém byl opraven a **objevil se znovu**. Neznamená to zvýšení závažnosti: závažnost je pevná pro každý typ problému. Smysluplnou regresí je jeho návrat, tedy to, co [životní cyklus](/cs/pro/scan-issues#issue-lifecycle) označuje jako znovuotevření.
- **Opravené** — problém byl otevřený v dřívějším skenu a nyní už není.

Každá skupina je [seřazená podle dopadu](#impact-ordering), takže začíná nejdůležitějšími položkami.

### Proč snímek místo tabulky problémů {#why-a-snapshot-not-the-issues-table}

Problémy procházejí [životním cyklem](/cs/pro/scan-issues#issue-lifecycle) opravy a znovuotevření. Stejný řádek se mezi skeny průběžně aktualizuje; pokud je stále otevřený, jeho `scan_run_id` se pokaždé přepíše na nejnovější běh. To se hodí pro trvalou historii problému, ale živá tabulka neřekne, *které problémy byly otevřené na konci běhu N*. Přetrvávající problém vždy odkazuje jen na nejnovější běh.

Každý běh proto pořídí snímek otevřené množiny podle stabilního **otisku problému**: `issue_type | target | field`. Stejnou identitu používá k porovnání [report s vlastní značkou](/cs/pro/reports). Změna se pak určí běžnými množinovými operacemi nad dvěma neměnnými množinami otisků. Funguje pro **libovolné** dva běhy, nejen po sobě jdoucí.

### Okrajové případy bez zavádějících závěrů {#edge-cases-handled-honestly}

- **Stránka vypadne ze sady skenovaných cílů.** Její otevřené problémy se znovu nekontrolují, proto zůstávají otevřené a zahrnují se do každého dalšího snímku. Zobrazí se jako **stále otevřené**, nikdy falešně „opravené“. Nepovažujeme stránku za opravenou jen proto, že jsme ji přestali sledovat.
- **Mezi skeny vypnete kontrolu.** Její problémy se přestanou hlásit, životní cyklus je označí za opravené a opustí otevřenou množinu. Zobrazí se tedy jako **opravené**. To odpovídá aktuálnímu vyhodnocení skeneru; na úrovni problému nelze rozlišit skutečnou opravu od vypnutí kontroly.
- **První sken po aktualizaci.** Běhy z doby před touto funkcí nemají snímek, takže se nikdy nevolí jako základ porovnání. První běh se snímkem vytvoří **výchozí stav**, tedy aktuální stav bez změn, místo aby celý web označil za „nový“. Od druhého běhu se snímkem porovnání funguje.

### V přehledu {#on-the-dashboard}

Widget **„Co se změnilo od posledního skenu“** v [přehledu SEO](/cs/pro/installation) ukazuje počty nových, vrácených a opravených problémů i nejdůležitější položky každé skupiny podle dopadu. Porovnává dva nejnovější dokončené skeny. Dokud neexistují dva snímky, zobrazí krátkou poznámku o výchozím stavu.

## Řazení podle dopadu {#impact-ordering}

Každá skupina změn se řadí podle skóre **dopadu**, aby největší problémy byly první:

```
impact = severity_weight × page_importance
```

- **severity_weight** používá zveřejněná [kritéria skóre](/cs/pro/scoring): kritický problém má váhu `40`, upozornění `15` a oznámení `5`. Závažnost vyjadřuje stanovisko produktu k významu vady. Řazení je přebírá a nevytváří druhou stupnici.
- **page_importance** vychází ze **skutečné poptávky ve vyhledávání**, tedy počtu zobrazení stránky v [Search Console](/cs/pro/search-console). Tento signál skutečně odlišuje jednu stránku od druhé:

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  Zobrazení se převádějí logaritmicky; stránka s 10× vyšší návštěvností není 10× důležitější. Výsledek se normalizuje vůči nejvytíženější stránce, takže vzorec funguje stejně na malém blogu i rozsáhlém katalogu. Hodnota `<priority>` v mapě webu je pouze **slabý vedlejší** signál. Standardně není nastavená a i po nastavení bývá plošně stejná, takže nemůže být hlavním základem řazení. Pořadí mírně upraví, pokud nastavíte priority jednotlivých typů v `seo.sitemap.models`.

**Bez Search Console to funguje také.** Bez synchronizované historie GSC a nastavených priorit je `page_importance` pro každou stránku `1`. Dopad se tak řadí čistě podle **závažnosti**, což je rozumný výchozí stav bez vymyšlených dat. Pro vážení podle poptávky synchronizujte [historii GSC](/cs/pro/search-console#historical-metrics) pomocí `seo-pro:gsc-sync`.

Váhy a časové okno upravte v `seo-pro.scan.delta.impact`.

## Naplánování skenu {#scheduling-a-scan}

Balíček **ve výchozím nastavení nic neplánuje**. Zapněte plánování:

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

Nebo pro plnou kontrolu nastavte úplný výraz cron, který má přednost před `frequency`:

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

To je vše. Balíček zaregistruje `seo-pro:scan` v plánovači Laravelu s `withoutOverlapping`, aby zabránil souběžným spuštěním plánovaného **příkazu**. Tento zámek plánovače nepokrývá celou dobu života úloh ve frontě. Registrace probíhá jen v kontextu plánovače nebo konzole, takže nepřidává **žádnou režii webovým požadavkům**.

::: warning Vyžaduje běžící plánovač
Plánování balíčku nic nedělá, pokud neběží plánovač Laravelu: standardní jednořádkový cron s `* * * * * php artisan schedule:run` nebo `php artisan
schedule:work` při vývoji. Viz [Produkční provoz](/cs/pro/production#scheduler).
:::

Chcete zapojení spravovat sami? Ponechte `schedule.enabled` vypnuté a naplánujte příkaz ve vlastní konzolové konfiguraci. Porovnání změn i souhrn budou dál fungovat:

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync` spouští sken přímo místo zařazení jedné úlohy na cíl. Hodí se pro malý web bez workeru fronty; na produkci jej ponechte vypnutý.

## Souhrnný e-mail {#summary-e-mail}

Volitelně zapněte e-mail **„Co se změnilo od posledního skenu“** po dokončení plánovaného skenu. Jde o HTML souhrn s vaší značkou a novými, vrácenými a opravenými problémy seřazenými podle dopadu:

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

Používá vizuální identitu a nastavení pošty [reportu s vlastní značkou](/cs/pro/reports), takže přebírá název agentury, logo i akcentovou barvu. Pokud neurčíte samostatné příjemce, použije příjemce reportu. `only_on_change` přeskočí e-mail, pokud se skenováním nic nezměnilo; první sken vytvářející výchozí stav se odesílá vždy.

Souhrn se odesílá pouze pro běh spuštěný s `--notify`. Plánovač tuto volbu automaticky přidává při zapnutém `notify.enabled`. Jednorázové `seo-pro:scan` **bez `--notify`** nikomu e-mail neodešle.

::: tip Jiný kanál?
Chcete místo e-mailu Slack, webhook nebo vlastní přehled? Odebírejte událost `Rankbeam\Seo\Pro\Events\SeoScanCompleted`. Vyvolá se jednou za dokončený běh a tento běh předává, takže můžete sestavit změny pomocí `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta` a odeslat je, kam potřebujete.
:::

## Uchovávání dat {#retention}

Snímky se kaskádově odstraňují spolu s během, takže je [`seo-pro:scan-prune`](/cs/pro/production#scheduler) automaticky promazává podle stáří. Není třeba plánovat nic nového. Běh se odstraní, jen pokud nemá otevřený problém, takže snímek nedávného běhu zůstává dostupný pro porovnání.

Pořizování snímků úplně vypnete pomocí `seo-pro.scan.delta.snapshot => false`. Nebude pak porovnání změn ani souhrn.

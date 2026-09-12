---
description: "Analýzy klíčových slov z vlastních dat Search Console: pět reportů o rozsazích pozic, kandidátech ke kontrole CTR a dotazech sdílených několika stránkami."
---

# Přehledy Search Console {#search-console-insights}

Pět reportů vypočítaných z vašich vlastních dat Search Console: dotazy ve zvoleném rozsahu pozic, kandidáti ke kontrole CTR, překryvy dotazů a stránek, skupiny dotazů a změny mezi obdobími. Tři používají synchronizovanou historii, dva sdílejí požadavek na aktuální data uložený do mezipaměti. Pokrývají tyto konkrétní analýzy, nikoli úplnou datovou sadu a funkce externí platformy pro klíčová slova.

Vycházejí z [integrace Search Console pouze pro čtení](/cs/pro/search-console) a její synchronizace historie. Pokud už běží příkaz `seo-pro:gsc-sync` popsaný na této stránce, tři z pěti přehledů fungují **bez dalších volání API**.

::: tip Předpoklad
Tři přehledy založené na *snímcích* čtou uloženou historii `seo_gsc_metrics`. Nejprve proto naplánujte `seo-pro:gsc-sync`; viz [Search Console → historie](/cs/pro/search-console). Čím více dní synchronizujete, tím delší období může porovnání trendů pokrýt.
:::

## Pět přehledů {#the-five-surfaces}

### 1. Klíčová slova v dosahu předních pozic {#_1-striking-distance-keywords}

Dotazy, jejichž **průměrná pozice vážená počtem zobrazení leží mezi 5 a 20**, seřazené podle zobrazení. Využijte je ke kontrole relevance a interních odkazů. Tento rozsah nedokazuje, že drobná změna dostane dotaz na první stránku.

### 2. Příležitosti ke zlepšení CTR {#_2-ctr-opportunities}

Dotazy, které mají **dobrou pozici, ale méně kliknutí, než se pro ni očekává**. Skutečné CTR každého dotazu se porovnává se souhrnnou oborovou křivkou CTR podle pozice. Dotazy se skutečnými zobrazeními a CTR výrazně pod očekáváním jsou **kandidáty na přepsání titulku či popisu**, seřazenými podle odhadovaných *ztracených kliknutí*. Seznam je přirozeným vstupem pro [AI návrhy metadat](/cs/pro/ai-assist): poskytuje konkrétní dotazy, pro které má smysl text přehodnotit.

### 3. Kanibalizace {#_3-cannibalization}

Dotazy, u kterých se pro stejný výraz zobrazují **dvě nebo více vašich URL**. Překryv nemusí být škodlivý. Než stránky sloučíte nebo odlišíte, ověřte, zda neslouží různým záměrům.

### 4. Skupiny dotazů {#_4-query-clusters}

**Dotazy, pro které se jednotlivé stránky skutečně umísťují**, seskupené podle stránky. Ukazují její skutečné tematické pokrytí z pohledu Googlu. Pomáhají odhalit stránku, která se vzdaluje zamýšlenému tématu, nebo se nenápadně umísťuje pro cenný výraz, na který jste nikdy necílili.

### 5. Trend proti předchozímu období {#_5-trend-vs-previous-period}

**Největší změny** kliknutí, zobrazení, pozice a CTR v aktuálním období proti bezprostředně předcházejícímu období stejné délky. Pozice se porovnává jen u dotazů s provozem v obou obdobích. Zcela nový nebo úplně vypadlý dotaz nemá s čím porovnat.

## Odkud čísla pocházejí: aktuální data a snímky {#where-the-numbers-come-from-live-vs-snapshot}

Každý přehled čte zdroj, který jeho otázku zodpoví správně s nejnižšími náklady. Uložená historie nedokáže rekonstruovat, který **dotaz** patřil ke které **stránce**, protože obě dimenze ukládá odděleně. Jen dva přehledy vyžadující tuto dvojici proto načítají aktuální data a **sdílejí jediný požadavek s mezipamětí**.

| Přehled | Zdroj | Důvod |
|---|---|---|
| Klíčová slova v dosahu | **Místní snímek** | Potřebuje pozici a zobrazení jednotlivých dotazů, které už obsahuje synchronizovaná historie; bez volání API |
| Příležitosti CTR | **Místní snímek** | Stejná vlastní data; křivka očekávaného CTR je statické srovnávací měřítko, nikoli externí dotaz |
| Změny trendu | **Místní snímek** | Potřebuje skutečnou denní historii, kterou synchronizace ukládá |
| Kanibalizace | **Aktuální data** (dotaz × stránka) | Párování dotazu se stránkou není uložené; uložení všech dvojic by násobilo objem dat |
| Skupiny dotazů | **Aktuální data**, *sdílí načtení přehledu 3* | Stejné dvojice seskupené podle stránky místo dotazu |

Návštěva stránky přehledů tak vyžaduje **nejvýše jeden** požadavek Search Analytics, uložený do mezipaměti na `search_console.cache_ttl` sekund. Přehledy dvojic používají aktuální data záměrně: kanibalizace a seskupování jsou otázky na *konkrétní okamžik*, kdy potřebujete aktuální obraz. Sdílená mezipaměť omezuje opakované požadavky. Obnovení tokenu může vyžadovat další autentizační požadavek a nadále platí kvóty Googlu. Přehledy ze snímků síť nikdy nepoužívají.

## V přehledu administrace {#in-the-dashboard}

S nainstalovaným pluginem Filament se ve skupině navigace *SEO* objeví **Přehledy** (stránka **Přehledy Search Console**), pouze pokud je integrace zapnutá. Vše slouží výhradně ke čtení. Každý přehled je samostatná sekce, prázdné přehledy ze snímků doporučí synchronizaci historie a neúspěšné načtení aktuálních dvojic zobrazí očištěné upozornění přímo v sekci, aniž by zablokovalo stránku.

## Konfigurace {#configuration}

Vše je v `search_console.insights` souboru `config/seo-pro.php`. Výchozí hodnoty jsou rozumné; prahy upravte podle velikosti svého webu.

```php
'search_console' => [
    // ...
    'insights' => [
        // Rolling window (days) the snapshot surfaces aggregate over,
        // anchored to the latest synced day.
        'window_days' => 28,

        // Max rows a surface returns to its panel section.
        'max_rows' => 50,

        // (1) Striking distance: impression-weighted position in [min,max]
        // with at least this many impressions.
        'striking_distance' => [
            'min_position' => 5.0,
            'max_position' => 20.0,
            'min_impressions' => 30,
        ],

        // (2) CTR opportunity: queries ranking at/above max_position, with at
        // least min_impressions, whose CTR is at least min_gap_ratio below the
        // expected curve.
        'ctr_opportunity' => [
            'max_position' => 10.0,
            'min_impressions' => 50,
            'min_gap_ratio' => 0.30,
        ],

        // Optional override of the expected CTR-by-position curve
        // (position => percent). null uses the built-in blended curve.
        'ctr_curve' => null,

        // (3) Cannibalization: a query with this many URLs each drawing at
        // least min_impressions.
        'cannibalization' => [
            'min_urls' => 2,
            'min_impressions' => 10,
        ],

        // (4) Query clustering: queries per page above min_impressions.
        'clustering' => [
            'min_impressions' => 10,
            'max_queries_per_page' => 15,
        ],

        // (5) Trend deltas: surface queries with at least this many
        // impressions in either period.
        'trend' => [
            'min_impressions' => 20,
        ],

        // The shared live (query,page) fetch for surfaces 3 + 4.
        'pair_days' => 28,
        'pair_row_limit' => 5000,
    ],
],
```

::: info Křivka očekávaného CTR
Křivka příležitostí CTR je **heuristika** sestavená ze zveřejněných průměrů organického CTR podle pozice. Je měřítkem, nikoli tvrzením o konkrétním webu. Označený dotaz je *kandidát ke kontrole*, ne prokázaná vada. Máte-li vlastní naměřenou křivku, vložte ji do `insights.ctr_curve` jako mapu `position => percent`.
:::

## Související {#see-also}

- [Search Console](/cs/pro/search-console) — integrace pouze pro čtení a synchronizace historie, ze kterých přehledy vycházejí
- [Reporty s vlastní značkou](/cs/pro/reports) — změny mezi obdobími v PDF s vaší značkou
- [Asistence AI](/cs/pro/ai-assist) — přepsání titulků a popisů označených přehledem CTR

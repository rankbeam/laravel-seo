---
title: Co je Rankbeam? SEO infrastruktura pro Laravel
description: "Rankbeam je SEO infrastruktura pro Laravel s otevřeným základem: bezplatný Core pod MIT pro metadata, kanonické URL, JSON-LD, mapy webu a roboty, komerční monitoring Pro a volitelné UI Filament."
---

# Co je Rankbeam? {#what-is-rankbeam}

**Rankbeam je SEO infrastruktura pro Laravel s otevřeným základem: bezplatný Core pod MIT pro metadata, kanonické URL, karty pro sdílení, propojené JSON-LD, mapy webu a řízení robotů, doplněný volitelným komerčním monitoringem a pracovními postupy Pro.** Není to jen pomocník pro značky připojený k aplikaci za běhu. Vyhodnocuje SEO z vašich modelů a konfigurace, stejná typovaná data vykreslí přes Blade, hlavičku Inertia nebo JSON API a s Pro je dál sleduje po nasazení.

## Rodina balíčků {#the-package-family}

Rankbeam tvoří tři balíčky se společnou maticí podpory:

| Balíček | Licence | Co obsahuje |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, zdarma** | Core: vyhodnocování metadat, propojený graf schématu JSON-LD, XML mapy webu, řízení robotů, bezplatný `seo:audit` a importéry |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, zdarma** | Pole formulářů a průběžné náhledy Filament 4/5 zapisující do `seo_meta` Core |
| `rankbeam/laravel-seo-pro` | **Komerční** | Provozní nástroje: skeny ve frontě se skóre 0–100, správa přesměrování, monitor 404 bez IP, robot pro nefunkční odkazy, přehledy Search Console a asistence AI s vlastním klíčem |

Hranice je záměrná. Vše, co vykreslená stránka vypisuje, má licenci MIT a zůstává navždy zdarma. Platíte za produkční vrstvu **auditu a monitoringu**. Komerční Pro je samostatný balíček; nikdy se nedodává uvnitř bezplatného Core.

## Pro koho je {#who-it-s-for}

Rankbeam se vyplatí, když se SEO **ukládá, váže na modely, používá více národních prostředí, funguje bez panelu a audituje se**: v produkční aplikaci Laravel s dynamickým obsahem nebo obsahem z modelů. Pro několik statických stránek vyžadujících jeden titulek a popis je vhodnější malý pomocník pro metadata za běhu. Níže to výslovně uvádí poznámka o tom, [kdy stále stačí sestava balíčků](#what-is-honestly-not-in-the-free-core).

## Podporované verze {#supported-versions}

Jedna matice pro celou rodinu:

- **PHP** 8.2–8.4 s Laravelem 11; 8.2–8.5 s Laravelem 12; 8.3–8.5 s Laravelem 13
- **Laravel** 11 / 12 / 13; Laravel 13 vyžaduje PHP 8.3+
- **Filament** 4 / 5, volitelně

## Co Rankbeam nenahrazuje {#what-rankbeam-doesn-t-replace}

Rankbeam sjednocuje vlastní SEO výstup aplikace Laravel. Není hostovaným sledováním pozic, sadou pro průzkum klíčových slov ani analytickým produktem. Neslibuje umístění, indexaci ani citace AI. Generování XML map webu staví na [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap), místo aby je vytvářel znovu. Obsah, směrování a analytiku ponechá tam, kde jsou.

Začínáte? [Nainstalujte bezplatný Core](/cs/guide/installation) nebo pokračujte k dokladům ze skutečné produkční výměny. Pro a nabídka pro první zákazníky jsou na [rankbeam.dev](https://rankbeam.dev/cs/).

## Proč ne tři balíčky a spojovací kód {#why-not-three-packages-glue}

Většina aplikací Laravel nemá jeden „SEO balíček“. Má **sestavu nástrojů SEO**: balíček ukládající metadata modelů, druhý přidávající pole do Filamentu, třetí skenující stránky a k tomu vrstvu vlastního spojovacího kódu, která jejich chování sjednotí. Každá část funguje sama o sobě. Náklady vznikají na spojích mezi nimi a jejich kód musíte trvale udržovat vy.

Tato stránka dokládá jednu skutečnou produkční výměnu, při které takovou sestavu nahradila rodina Rankbeam. Níže uvedená čísla pocházejí z měření, nikoli z marketingových odhadů.

## Referenční aplikace {#the-reference-app}

Skutečný produkční obsahový web v Laravelu, zde anonymizovaný:

- **Obsahový web nemocnice / instituce**, přibližně 3 měsíce v produkci.
- **Převedený z WordPressu**, podle mapy webu přibližně 900 stránek.
- Přibližně **20 000 návštěv denně**.
- **Laravel 12**, administrace **Filament 4**, frontend Blade, MySQL.

Původní sestava SEO:

| Vrstva | Balíček |
|---|---|
| Ukládání metadat, tabulka `seo` pro modely | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Pole SEO ve Filamentu | `ralphjsmit/laravel-filament-seo` |
| Skenování stránek | `backstage/laravel-seo-scanner` |
| Vše mezi nimi | **Přibližně 30 vlastních tříd aplikace** |

Odstranili jsme tři balíčky, nainstalovali Rankbeam **Core + Pro + Filament**, spustili testy SEO a aplikace naběhla s **nulovými regresemi SEO v této sadě testů**. Následuje skutečná cena spojovací vrstvy a přehled toho, co zmizelo.

## Co výměna odstranila {#what-the-swap-deleted}

Nahrazení sestavy skeneru Rankbeamem úplně odstranilo **12 vlastních tříd**. Jejich práci už aplikace nevlastní, protože odpovídající funkce poskytuje rodina balíčků:

| Odstraněná třída aplikace | Co dělala | Nyní poskytuje |
|---|---|---|
| `Services/SeoService.php` | Obal vstupního bodu SEO aplikace | Resolver Core a fasáda `SEO` |
| `Services/SeoWarningEvaluator.php` | Prahy délek titulku a popisu a rozměrů obrázků | `SEOWarningEvaluator` z Core, sdílené auditem, náhledem a skenem |
| `Services/Seo/SeoAssetInspector.php` | Kontrola rozměrů místních obrázků | `LocalImageInspector` z Core |
| `Jobs/ScanAllPagesSeo.php` | Zařazení skenu celého webu do fronty | [Postup skenu](/cs/pro/scan-issues) Pro ve frontě |
| `Jobs/ScanPageSeo.php` | Sken jednotlivé stránky | `PageScanner` z Pro |
| `Jobs/ScanPublicPageSeo.php` | Sken veřejné stránky | Postup skenu Pro |
| `Models/SeoScanBatch.php` | Evidence běhů skenu | `seo_scan_runs` z Pro |
| `Filament/Pages/SeoDashboard.php` | Přehled SEO v administraci | Plugin `SeoDashboard` z Pro |
| `Filament/Widgets/SeoScanProgressWidget.php` | Widget průběhu skenu | Widgety skenu Pro |
| `Filament/Widgets/SeoTrendChartWidget.php` | Widget vývoje skenů | Widgety skenu Pro |
| `Facades/Seo.php` | Fasáda aplikace nad ukládacím balíčkem | Fasáda `SEO` z Core |
| `Console/Commands/RecoverLegacySeoMetadata.php` | Jednorázová obnova metadat | [Importéry](/cs/guide/migrate-from-wordpress) Core, `seo:import-from` |

::: info Přesné vyčíslení zbytku
Výměna záměrně **ponechala** vlastního robota aplikace pro nefunkční odkazy, přibližně 17 tříd: úlohu skenu, kontrolu, sestavování výchozích cílů, vyhodnocování zdrojů, dva modely, dva výčtové typy, dvě události, resource Filamentu a tři widgety, dva příkazy. Zůstalo i několik pomocníků metadat a schématu: `CustomSEO`, `EntitySeoSection`, `DynamicSeoDataResolver`, `SitewideSchema` a `SeoKeywords`. Celkem jde o dalších přibližně **22 tříd**. První den odstraněné nebyly, protože náhrady Rankbeamu přišly později: [robot Pro pro nefunkční odkazy](/cs/pro/production) místo vlastního robota, **cíl souvisejícího modelu** a **náhled SERP / sociálních sítí** ve Filamentu místo `CustomSEO`/`EntitySeoSection` a **graf schématu** Core místo `SitewideSchema`. Při přijetí celé rodiny přejde tato vlastní vrstva, celkem přibližně **tři tucty tříd**, do odpovědnosti balíčků.
:::

Nejde o to, že by některý původní balíček byl špatný. *Integrace*, více než tucet tříd propojujících změnu metadat se skenerem, přehledem a vykreslenou hlavičkou, je vlastní kód bez nadřazeného projektu, bez jiných než vašich testů a bez hlášení chyb od ostatních.

## Srovnání vedle sebe {#side-by-side}

| Schopnost | Sestava 3 balíčků a spojovacího kódu | Rodina Rankbeam |
|---|---|---|
| Metadata jednotlivých modelů | Balíček metadat | **Core**, `seo_meta` pod MIT |
| Ukládání **podle národního prostředí** | Obvykle vlastní spojovací kód | **Core**, `seo_meta` oddělené sloupcem podle národního prostředí |
| Pole SEO ve Filamentu | Balíček Filament-SEO | **`laravel-seo-filament`**, MIT |
| Úpravy SEO **souvisejícího** modelu | Vlastní obal komponenty pole | Plně podporovaný resolver `target:` |
| Průběžný náhled **SERP a sociálních sítí** | Vlastní Blade/Alpine | Vestavěný redakční náhled s kartami |
| Vykreslování bez panelu, Inertia / Livewire / JSON | Referenční aplikace používala Blade; jiné sestavy potřebují integraci | **Jeden resolver** → Blade, Inertia, Livewire, JSON, [ověřené smlouvou vykreslování](/cs/contributing/rendering-contract) |
| Skener stránek a seřazené problémy | Balíček skeneru | **Pro**, [postup skenu](/cs/pro/scan-issues) a `IssueRegistry` |
| Skóre 0–100 | Vlastní kód nebo žádné | **Pro**, transparentní [pravidla s verzí](/cs/pro/scoring) |
| Přesměrování a obnova po 404 | Další balíček nebo vlastní kód | **Pro**, správa přesměrování a monitor 404 bez IP |
| Robot pro nefunkční odkazy | Vlastní, aplikace si jej napsala | **Pro**, omezený robot s pokračováním |
| **Graf** schématu JSON-LD | Sestavovací nástroj a vlastní propojení `@id` | **Core**, propojený graf Organization/WebSite/WebPage |
| XML mapy webu | Balíček mapy webu | **Core**, registr mapy webu nad `spatie/laravel-sitemap` |
| Import WordPress / Yoast / Rank Math | Jednorázové skripty | **Core**, `seo:import-from` a [provozní postup](/cs/guide/wordpress-migration-runbook) |
| **Kdo udržuje propojení** | **Vy** | Rodina balíčků s jednotným postupem vydávání |

## Tři věci, které spojovací kód řeší obtížně {#the-three-things-glue-can-t-do-well}

**1 — Ucelená rodina a jednotný postup vydávání.** Tři balíčky mají tři správce, tři changelogy a tři rytmy aktualizací. Spojovací kód vyrovnává rozdíly mezi nimi. Core, Pro a Filament Rankbeamu mají koordinované verze se společnou [maticí podpory](#tested-where-it-runs) a popsanými [hranicemi aktualizací](/cs/reference/configuration). Změnu chování oznámí na jednom místě; nezjistíte ji až při rozporu mezi dvěma balíčky.

**2 — Národní prostředí je sloupec úložiště, nikoli dohoda.** `seo_meta` je polymorfní **a** už v úložišti oddělené podle národního prostředí. Vícejazyčné SEO má řádek pro `(model, locale)`, nikoli serializovaný blok nebo propojovací tabulku, na kterou jste museli pamatovat. [Řetězec přednosti resolveru](/cs/concepts/resolver-precedence) čte aktivní národní prostředí přímo.

**3 — Vykreslování bez panelu z jednoho resolveru.** Rankbeam vyhodnotí typované `SEOData` a *stejná* data vykreslí jako HTML, data `Head` pro Inertia nebo pole JSON. Ověřuje je společná [smlouva vykreslování](/cs/contributing/rendering-contract) pro [Blade](/cs/guide/blade), [Inertia](/cs/guide/inertia-json), Vue/React/Svelte, a [Livewire](/cs/guide/livewire). Administrační panel není potřeba: všechny funkce Pro běží také [bez panelu přes Artisan](/cs/pro/headless).

## Co skutečně není v bezplatném Core {#what-is-honestly-not-in-the-free-core}

Rankbeam má otevřený základ a záměrnou hranici, abyste přesně věděli, co získáte, než spustíte `composer require`:

| Balíček | Licence | Co obsahuje |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, zdarma** | Vyhodnocování metadat, graf schématu JSON-LD, mapy webu, bezplatný `seo:audit`, importéry |
| `rankbeam/laravel-seo-filament` | **MIT, zdarma** | Pole a sekce formulářů Filamentu zapisující do `seo_meta` |
| `rankbeam/laravel-seo-pro` | **Komerční** | Skeny ve frontě, seřazené problémy a skóre 0–100, přesměrování, monitor 404, robot pro nefunkční odkazy, Search Console, asistence AI a přehled Filamentu |

Platíte tedy za **audit technického SEO** a sadu pro **monitoring webu**: skeny, skóre, přesměrování, obnovu po 404 a robot. Metadata, graf schématu, mapy webu a bezplatný audit uvnitř procesu mají licenci MIT a zůstávají zdarma.

Dvě ověřitelné vlastnosti:

- **Žádná kontrola licence za běhu.** Pro se licencuje na projekt při instalaci. Nic se nehlásí domů a žádný vypínač nemůže shodit aplikaci. Pro vydává *místní* provozní telemetrii do vašich logů, kterou lze vypnout; nikdy ji neposílá nám.
- **AI s vlastním klíčem.** Volitelná [asistence AI](/cs/pro/ai-assist) používá *váš* klíč Anthropic, OpenAI, Google nebo místního modelu. Nic nezprostředkováváme, neměříme pro účtování ani nepřeprodáváme a funkce je standardně vypnutá.

::: tip Kdy stále stačí sestava balíčků
Potřebujete-li jediný `<title>` a popis na několika statických stránkách, nástroj pro vytváření značek za běhu stačí. Rankbeam se vyplatí, když se SEO **ukládá**, má **více národních prostředí**, **váže se na modely**, funguje **bez panelu** a **audituje se**: ve chvíli, kdy propojení balíčků představuje skutečný kód, který udržujete.
:::

## Přechod s nejnižším rizikem: z WordPressu {#the-lowest-risk-switch-off-wordpress}

Referenční aplikace přešla z WordPressu s přibližně 900 stránkami. Právě takový web může ztratit nejvíce: roky optimalizace v Yoast nebo Rank Math. Rankbeam pro něj nabízí bezpečný postup:

1. **Souběh.** Zprovozněte Rankbeam vedle živého webu; zatím nic neodstraňujte.
2. **Import, nejprve nanečisto.** `seo:import-from yoast` / `rank-math` / `wordpress-csv` načte titulky, popisy, kanonické URL, robots, hlavní klíčová slova a přepsané údaje pro sociální sítě. Importéry jsou **idempotentní** a **standardně doplňují jen prázdná pole**. Bez `--overwrite` zachovají již nastavená metadata a `--dry-run` nic nezapisuje.
3. **Předání přesměrování.** Core vytvoří CSV přesměrování s verzí. `seo-pro:redirects-import` z Pro ověří každý řádek před zápisem a odmítne smyčky, nebezpečné cíle a duplicity.
4. **Ověření před odstraněním čehokoli.** `seo:audit --strict` slouží jako podmínka CI či přechodu a při jakémkoli problému skončí nenulovým kódem. Původní databáze WordPressu zůstává nedotčená, dokud se nerozhodnete ji odstranit.

Úplný postup obsahuje [provozní průvodce migrací z WordPressu](/cs/guide/wordpress-migration-runbook). Mapování jednotlivých polí a zpracování tokenů popisuje [migrace z WordPressu](/cs/guide/migrate-from-wordpress). Přecházíte z balíčku SEO pro **Laravel**, například ralphjsmit, artesaos nebo Spatie? [Viz průvodce migrací balíčků](/cs/guide/migrate-from-other-packages).

## Zvládá větší rozsah? {#does-it-hold-up-at-scale}

Dvě nejnáročnější podmínky referenční aplikace, resolver při každém z přibližně 20 tisíc denních požadavků a procházení odkazů na přibližně 900 stránkách, mají benchmark v testech. Ověřují **deterministické** přínosy, tedy počty dotazů a omezení úloh, nikoli ručně laděné časy běhu:

**Mezipaměť resolveru — zásah naplněné mezipaměti nevolá databázi.** Při výslovně zapnuté mezipaměti vyhodnocování se při zásahu přeskočí *celý* řetězec přednosti. Benchmark provede 25 vyhodnocení stejného modelu:

| | Databázové dotazy |
|---|---|
| Bez mezipaměti, každé vyhodnocení znovu čte `seo_meta` | **≥ 25** |
| Zásah naplněné mezipaměti | **0** |

Mezipaměť je **standardně vypnutá** a popsaná jako nástroj pro větší zatížení. Zneplatnění odstraní správné položky při změně `seo_meta`, pole obsahu nebo výchozích hodnot. Viz [Konfigurace → mezipaměť](/cs/reference/configuration).

**Robot pro nefunkční odkazy — omezené úlohy na 900 stránkách.** Benchmark prožene generovaný korpus přibližně 900 stránek skutečnou úlohou:

- Dokončí se v **nejméně 18 omezených úlohách**, s limitem 50 stránek na úlohu.
- **Žádná úloha** nenavštíví více než svůj limit 50 stránek.
- Zkontroluje **1 800 odkazů**; každý nefunkční cíl se stane trvalým potvrzeným zjištěním.

Procházení omezují konečné limity celého běhu a pevný časový rozpočet úlohy. SSRF se ověřuje při načtení výchozího cíle **i každém kroku přesměrování** a databázový pronájem práce dovolí jen jeden aktivní běh pro rozsah. Provoz popisuje [průvodce produkčním nastavením](/cs/pro/production).

## Testováno v podporovaných prostředích {#tested-where-it-runs}

Jedna matice podpory pro celou rodinu, nikoli tři:

- **PHP** 8.2–8.4 s Laravelem 11; 8.2–8.5 s Laravelem 12; 8.3–8.5 s Laravelem 13
- **Laravel** 11 / 12 / 13
- **Filament** 4 / 5

## Proč tedy propojovat tři balíčky? {#so-—-why-glue-three-packages-together}

Pokud sestava stojí více než tucet vlastních integračních tříd, rytmus vydávání mimo vaši kontrolu, integraci vykreslování pro konkrétní frontend a ručně doplněné zpracování národních prostředí, zatímco ucelená rodina s podporou jazyků a provozem bez panelu tento kód odstraňuje a prošla skutečnou produkční aplikací o 900 stránkách a 20 tisících návštěv denně, přestává být sestava automaticky bezpečnější volbou.

Začněte [rychlým startem](/cs/guide/quickstart): od `composer require` k úplně vykreslenému `<head>` za pět minut.

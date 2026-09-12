---
description: "PDF report s vaší značkou: skóre, vývoj problémů, opravené a nové chyby, obnovené URL po 404, změny Search Console a aktivita AI botů. Jedním příkazem, volitelně s plánovaným e-mailem."
---

# Reporty s vlastní značkou {#white-label-reports}

**PDF report** s vaší značkou pro jeden web: celkové skóre, vývoj nalezených problémů, co bylo **opraveno a co přibylo od posledního reportu**, opravené nefunkční odkazy a obnovené URL po chybách 404, změny v Search Console a aktivita AI botů. Vygenerujete jej jediným příkazem a volitelně **odešlete e-mailem podle plánu**. Je určen agenturám: přidejte své logo, barvu a údaj „připraveno pro {client}“ a předejte jej klientovi.

[Stáhněte si vygenerovaný ukázkový report v angličtině (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf) nebo projděte [postup sken → oprava → report](/cs/pro/walkthrough). Ukázka používá předem připravený obsah dema Merchant a dva nové skeny. Obsahuje jeden opravený problém, 19 stále otevřených a žádná data Search Console.

[![První stránka vygenerovaného reportu dema Merchant.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## Co obsahuje {#what-s-in-it}

- **Celkové skóre** — průměr posledních skóre jednotlivých stránek podle zveřejněných [pravidel hodnocení](/cs/pro/scoring), A ≥ 90 … F, se změnou od posledního reportu a **vývojem celkového skóre** napříč nedávnými skeny. Každý sken nyní uloží skóre webu do svého běhu, takže jde o skutečnou historii po jednotlivých skenech. Začne se plnit prvním skenem po aktualizaci; starší běhy skóre nemají a přeskočí se.
- **Problémy nalezené při skenu** — skutečný vývoj napříč nedávnými dokončenými skeny; méně je lépe.
- **Opravené a nové problémy** — kolik závad jste odstranili a kolik přibylo od posledního reportu. Jakmile nově evidovaný [životní cyklus](/cs/pro/scan-issues#issue-lifecycle) opravení a opětovného otevření pokrývá celé období, počty se čtou ze skutečné historie problémů. Jinak se použije snímek předchozího reportu.
- **Obnovené URL a opravené odkazy** — opravené nefunkční odkazy, **obnovené** URL po chybě 404, jejichž cesta sama znovu vrací 200, viz [`seo-pro:404-recheck`](/cs/pro/production#scheduler), a **přesměrované** URL po 404 od posledního reportu, spolu s dosud otevřenými případy. Obnovení po 404 je skutečná oprava na původní adrese a počítá se odděleně od přesměrování.
- **Search Console** — hlavní dotazy a stránky a **největší změny** počtu kliknutí proti poslednímu reportu. Pokud GSC není nastavená, část se vynechá bez chyby.
- **Aktivita AI botů** — požadavky přiřazené podle user-agentu, nikoli ověřená identita bota, celkové historické počty a při dostatečném pokrytí [denní historií](/cs/pro/ai-bot-monitor#period-metrics-daily-buckets) **skutečné požadavky za období a počet různých URL navštívených každým botem**. Jinak se použije rozdíl celkových počtů proti snímku.

## „Od posledního reportu“ {#since-the-last-report}

Report **porovnává období vůči předchozímu reportu**, nikoli libovolnému datu. Při každém vygenerování se uloží malý snímek do `seo_report_runs`: skóre, identity otevřených problémů, řádky Search Console a počitadlo požadavků jednotlivých botů. Příští report porovná dnešní stav s tímto snímkem.

Jde o náhradní postup pro údaje bez vlastní historie: u skóre stránek se uchovává jen poslední hodnota. Snímky při vytváření reportů umožňují poctivé porovnání. Několik ukazatelů už vede **skutečnou** historii a report jí dává přednost; snímek použije jen jako náhradu. Problémy mají [životní cyklus](/cs/pro/scan-issues#issue-lifecycle) opravení a opětovného otevření, který po pokrytí celého období poskytne skutečné počty opravených a nových problémů. Search Console vede [denní metriky](/cs/pro/search-console#historical-metrics) a požadavky AI botů [denní souhrny](/cs/pro/ai-bot-monitor#period-metrics-daily-buckets) se skutečnými počty za období a různými URL. Při prvním reportu po aktualizaci nebo při nedostatečném pokrytí období se každý z nich vrátí k rozdílu snímků.

Dva důsledky:

- **První report je výchozí stav.** Ukazuje současnost. Údaje „opravené“, „nové“, změny a hodnoty „od posledního reportu“ se začnou plnit od *druhého* reportu.
- **Četnost určujete vy.** Při měsíčním vytváření změny pokrývají měsíc, při týdenním týden. Pro jednorázový náhled, který nesmí posunout výchozí stav, použijte `--no-store`.

## Vygenerování reportu {#generate-a-report}

```bash
php artisan seo-pro:report
```

Bez voleb zapíše PDF do `storage/app/seo-reports/`. Určete jiné umístění nebo jej odešlete e-mailem:

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

### Volby {#options}

| Volba | Účinek |
| --- | --- |
| `--client=` | Přepíše označení klienta „připraveno pro“ |
| `--agency=` | Přepíše název agentury v reportu |
| `--accent=` | Přepíše zvýrazňující barvu, hexadecimálně, např. `#3D5AFE` |
| `--logo=` | Přepíše cestu k obrázku loga |
| `--email=` | Adresa příjemce; lze opakovat, odešle report e-mailem |
| `--send` | Odešle e-mail nastaveným příjemcům |
| `--output=` | Zapíše PDF do tohoto souboru nebo adresáře |
| `--no-store` | Neuloží snímek; základ pro změny mezi obdobími se neposune |
| `--json` | Vypíše strojově čitelný souhrn |

## Plánování e-mailu {#schedule-the-e-mail}

Balíček se nikdy sám nezařazuje do plánovače. Četnost určujete vy. V konzolovém plánu aplikace, tedy `routes/console.php` nebo `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Výchozí příjemce nastavte jednou v konfiguraci nebo `.env`:

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` použije tyto adresy; výslovné volby `--email` je přepíší.

## Vlastní značka {#branding}

Údaje značky nejsou tajné, proto patří do konfigurace. Nastavíte je jednou a převezme je každý report. Libovolné pole lze přepsat pro konkrétní report výše uvedenými volbami, což se hodí, pokud jedna instalace vytváří reporty pro více klientů.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Poznámky:

- **Logo** — absolutní cesta k souboru `PNG`/`JPG`/`GIF`/`WEBP`/`SVG`. Aplikace soubor načte a vloží do PDF jako datové URI, takže vykreslovací nástroj obrázek nemusí stahovat po síti. Nejbezpečnější je `PNG` nebo `JPG`.
- **Zvýrazňující barva** — ověřuje se jako hexadecimální hodnota. Neplatná hodnota se nahradí výchozí. Používá se vždy jen jako barva, nikdy jako surové CSS.
- **Název agentury** — výchozí je název vaší aplikace, `config('app.name')`.

Celý konfigurační blok je pod `reports` v `config/seo-pro.php`, včetně `paper` s výchozím `a4`, `include_gsc` a počtu běhů zahrnutých do vývoje skóre, řádků GSC a botů, které se mají zahrnout.

## Jeden web na instalaci {#one-site-per-install}

Pro skenuje jedinou aplikaci, ve které je nainstalováno, takže report popisuje **tuto instalaci**. Agentura provozující více klientských webů vytváří jeden report pro každou instalaci; jednotlivé reporty označí přepsáním `--client` a údajů značky. Datový model pro více oddělených klientů s evidencí „webů“ neexistuje.

## Jak vzniká {#how-it-s-built}

PDF ve výchozím nastavení vykresluje **dompdf**, čisté PHP bez Node nebo bezhlavého Chromia. Plánovaný report tak vznikne ve workeru fronty nebo v cronu bez systémových binárních souborů a Pro zůstává použitelné bez panelu. Vzdálené načítání je ve vykreslovacím nástroji vypnuté. Jediný obrázek, vaše logo, je vložený, takže žádné vykreslené pole nemůže vyvolat síťové načtení.

### Reporty ve všech písmech (vykreslování přes Browsershot) {#reports-in-every-script-browsershot-renderer}

Od Core 3.20 / Pro 2.40 vypínají vykreslovací nástroje Chrome JavaScript a blokují požadavky na prostředky přes HTTP(S), FTP a WebSocket. Zkopírované šablony musí používat statické HTML/CSS s vloženými prostředky. Tyto kontroly se týkají prostředků stránky; Chrome stále potřebuje správně nastavený server a sandbox. Když Fontconfig ohlásí chybějící písmo pro některou použitou soustavu znaků, včetně té zastoupené jen menšinou smíšeného textu, PDF renderer zaloguje upozornění s postupem instalace. Chybějící font nezabrání vytvoření PDF v Chromu, proto výstup před odesláním zkontrolujte.

dompdf vykresluje pouze vložený font, DejaVu Sans pro latinku, cyrilici a řečtinu. Report pro japonského, thajského nebo arabského klienta proto zobrazí náhradní čtverečky. Od Pro 2.34 lze místo něj použít **bezhlavý Chrome** přes `spatie/browsershot`, stejnou závislost, kterou Core používá pro obrázky OG. Server tak nastavíte jednou:

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

Chrome vykreslí písma nainstalovaná na serveru. Šablona pak použije zásobník fontů Core podle soustavy znaků: `Noto Sans`, nejprve rodinu `Noto Sans CJK` podle jazyka stránky, dále thajštinu, arabštinu, hebrejštinu, dévanágarí, barevné emoji a DejaVu Sans pro latinku. Nainstalujte potřebné rodiny, na Debianu a Ubuntu `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`, stejně jako pro [obrázky OG](/cs/guide/multilingual#og-images-in-every-script). `seo:og-images` při běhu upozorní, pokud pro písmo stránky není nainstalovaná rodina; stejné řešení platí pro reporty. Šablona Blade, data i snímek jsou v obou nástrojích totožné. Mění se pouze rasterizér a `ReportGenerator::renderer()` ukáže, který je navázaný.

### Data a čísla podle národního prostředí čtenáře {#dates-and-numbers-in-the-reader-s-locale}

Report při sestavení zachytí `seo-pro.reports.locale`. Null použije národní prostředí aplikace. Vyhodnocený jazyk překladu určuje popisky v PDF a e-mailu, výchozí předmět, výběr fontů a HTML `lang`. Regionální varianty bez vlastního souboru překladu přejdou na dodaný základní jazyk a pak na angličtinu. Zjednodušená, `zh_CN`, a tradiční, `zh_TW`, čínština zůstávají oddělené.

Je-li nainstalováno `ext-intl`, data a čísla se řídí požadovaným národním prostředím přes ICU. Pomocí `seo-pro.reports.format_locale` můžete záměrně zvolit jiný regionální formát: `locale=it` a `format_locale=en_US` vytvoří italské popisky s americkým formátem dat a čísel. Bez `ext-intl` zůstane náhradní anglický formát data a oddělování skupin číslic čárkami.

E-mail ve frontě zachová zachycený jazyk, formátování a předmět i při změně konfigurace workeru. Jazyk zvolte před vygenerováním PDF; pozdější změna národního prostředí objektu mailable nemůže přeložit jeho přílohu. Staré úlohy z doby před Pro 2.39 používají konfiguraci workeru, protože zachycená nastavení nemají. Vlastní předměty, údaje značky a uložené zprávy problémů zůstávají zdrojovými daty.

Zobrazení CLI je oddělené: `php artisan seo-pro:report --display-locale=it` přeloží souhrn příkazu, zatímco konfigurace reportu určuje jazyk klientova PDF a e-mailu. CLI má výchozí angličtinu, nastavitelnou přes `SEO_PRO_CLI_LOCALE`. Klíče a kódy JSON zůstávají stabilní, textové popisky lze přeložit. Zkopírujte `seo-pro-lang`, pokud chcete přepsat zprávy reportů a pracovních postupů v `lang/vendor/seo-pro/{locale}/seo-pro.php`.

V kódu získejte `ReportGenerator` z kontejneru:

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


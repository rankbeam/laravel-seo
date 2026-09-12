---
description: "Nainstalujte laravel-seo-pro: skenování webu přes frontu, sledování problémů, správce přesměrování a monitor 404 nad Core. Funguje v Laravelu 11–13; Filament je volitelný."
---

# Instalace Pro {#installing-pro}

`rankbeam/laravel-seo-pro` přidává k balíčku Core skenování webu přes frontu se sledováním problémů, správce přesměrování a monitor 404. Jádro Pro běží v **jakékoli aplikaci Laravel 11–13**, ať používá Blade, Inertii nebo čisté API. Filament je volitelná vrstva rozhraní: s ním získáte přehled SEO, správce přesměrování a monitor 404 jako stránky panelu; bez něj spravujete vše pomocí [příkazů Artisan](/cs/pro/headless).

## Požadavky {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13) |
| Laravel | 11, 12 nebo 13 |
| `rankbeam/laravel-seo` | ^3.20; Pro 2.40+ instaluje automaticky |
| `filament/filament` | **volitelný** — 4.x nebo 5.x, pouze pro administrační rozhraní |
| `rankbeam/laravel-seo-filament` | **volitelný** — ^1.11 při použití editoru SEO s Pro 2.36+ |

Začněte s existující aplikací Laravel a nastavenou databází. Nejprve dokončete [Rychlý začátek s Core](/cs/guide/quickstart), aby model vykresloval metadata a existovaly tabulky Core. Licence Pro poskytuje přihlašovací údaje pro Composer popsané níže.

Názorný příklad výsledku najdete v průvodci [skenování → oprava → report](/cs/pro/walkthrough).

## Instalace balíčku {#install-the-package}

Pro se distribuuje přes soukromý repozitář Composeru propojený s vaší licencí. Jednou přidejte repozitář a potom vyžádejte balíček. Composer se zeptá na licenční e-mail jako uživatelské jméno a licenční klíč jako heslo:

Platbu zpracovává Lemon Squeezy jako merchant of record, tedy oficiální prodejce odpovědný za transakci. Po zaplacení soukromá stránka potvrzení nákupu poskytne klíč ke stažení a pokyny pro Composer. Jako uživatelské jméno použijte nákupní e-mail. Repozitář balíčku hostuje Rankbeam; účet Anystack není potřeba. Odkaz na potvrzení nákupu i `auth.json` uchovávejte v soukromí. Úplné vrácení platby zruší přístup k budoucím stažením a aktualizacím, ale nepřeruší běh nainstalované aplikace.

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details Neinteraktivní autentizace Composeru
Pro CI nebo neinteraktivní prostředí uložte údaje předem:

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

Potom spusťte instalátor:

```bash
php artisan seo-pro:install
```

Instalátor zkopíruje `config/seo-pro.php` a migrace Pro do aplikace, spustí `migrate` a vypíše další kroky. Databáze aplikace by nyní měla obsahovat tabulky Core i Pro.

::: details Ruční instalace a volby instalátoru
Migrace Pro se kopírují do aplikace; balíček je automaticky nenačítá. Odpovídající ruční kroky jsou:

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Instalátor lze spustit opakovaně. `--no-migrate` zkopíruje soubory bez spuštění migrací. `--force` použijte jen tehdy, pokud chcete přepsat zkopírované soubory včetně své konfigurace.
:::

## Registrace cílů skenování {#register-scan-targets}

V poskytovateli služeb určete, co má skener kontrolovat: třídy modelů, pojmenované trasy nebo vše z [registru map webu](/cs/guide/sitemaps):

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

Nahraďte `Post` vlastním modelem používajícím `HasSEO`. Pro zobrazení výsledku skenování modelu potřebujete alespoň jeden záznam. Cíle typu trasa musí uvádět existující trasy; pokud chcete skenovat pouze modely, registraci tras vynechte.

## Ověření instalace {#verify-your-install}

Spusťte kontrolu konfigurace:

```bash
php artisan seo:doctor
```

Ověřte, že existují tabulky Core i Pro, URL aplikace je správná a cíle skenování jsou uvedené. Proveďte doporučené opravy. Upozornění na frontu `sync` je při zkoušení níže uvedených příkazů bez fronty očekávané. Před plánováním produkčních skenů nastavte worker.

::: details Příklad výstupu kontroly stavu
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor` kontroluje konfiguraci a nedávnou historii běhů bez síťových volání a vypisování tajných údajů. Nedokáže prokázat, že běží externí cron nebo worker. Kritické chyby vracejí nenulový návratový kód, upozornění nikoli. Pro strojově čitelný výsledek použijte `--json`.
:::

## Spuštění prvního skenu {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

První příkaz dokončí sken přímo v procesu, takže tato počáteční kontrola nepotřebuje worker fronty. Druhý zobrazí nejnovější běh a jeho výsledky. Očekávejte dokončený běh se zpracovanými registrovanými cíli. Než sken považujete za dokončený, vyšetřete každý neúspěšný cíl.

Opravte jedno nahlášené pole, uložte ho a spusťte sken znovu. [Názorný průvodce](/cs/pro/walkthrough) to ukazuje na chybějícím popisu a reportu změny. [Technické skóre](/cs/pro/scoring) je diagnostický výsledek, nikoli předpověď pozic ve vyhledávání.

## Použití bez panelu {#path-b-headless}

Jádro Pro je připravené i bez panelu. Pomocí [příkazů Artisan](/cs/pro/headless) můžete skenovat, prohlížet problémy, vytvářet přesměrování a generovat reporty. Middleware přesměrování a 404 se standardně registrují automaticky; nastavení je v `config/seo-pro.php`.

Pro plánované úlohy postupujte podle [Produkčního provozu](/cs/pro/production), kde nastavíte fronty, workery, plánovač a dobu uchovávání dat.

## Přidání panelu Filament (volitelné) {#path-a-with-a-filament-panel}

Do existujícího panelu Filament 4 nebo 5 zaregistrujte plugin Pro uvedený níže. Pokud aplikace panel zatím nemá, nejprve nainstalujte balíčky rozhraní a vytvořte jej:

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

Přidá se **přehled SEO** s akcí skenování všeho, živým průběhem a seznamem problémů s opětovným skenem jedním kliknutím, **správce přesměrování** a **monitor 404** s akcí *Vytvořit přesměrování* jedním kliknutím. `rankbeam/laravel-seo-filament` navíc přidá do formulářů zdrojů [sekci polí SEO](/cs/guide/filament).

## Řešení potíží {#troubleshooting}

| Výsledek | Další krok |
|---|---|
| Composer odmítá přihlašovací údaje | Zkontrolujte licenční e-mail a klíč pro `blog.rankbeam.dev`. Údaje neukládejte do správy verzí. |
| Doctor hlásí chybějící tabulky | Dokončete Rychlý začátek s Core a potom spusťte `seo-pro:install` a `migrate` proti stejné databázi jako aplikace. |
| Sken nezpracuje žádné cíle | Zkontrolujte registraci v poskytovateli služeb a přítomnost záznamů v modelu. |
| Sken ve frontě stále čeká | Spusťte nastavený worker fronty nebo pro přímou kontrolu použijte `--sync`. |
| Cíl selže | Před opakováním skenu zkontrolujte podrobnosti běhu, názvy tras a URL aplikace. |
| Chybí přehled | Zaregistrujte `SeoProPlugin` v panelu, který skutečně používáte, a zkontrolujte přístupové brány. |

Obnovu workerů a běžnou správu popisuje [Produkční provoz](/cs/pro/production).

## Licence a vrácení peněz {#license}

Zakladatelská licence stojí jednorázově 179 € a pokrývá až pět produkčních projektů včetně klientských, s doživotními aktualizacemi. Vývojové a stagingové kopie těchto projektů se nezapočítávají zvlášť. Součástí je pomoc s instalací a migrací, 60minutový instalační hovor a inzerovaný balíček pro spuštění. Do 30 dnů můžete požádat o úplné vrácení peněz bez podmínek přes potvrzení nákupu nebo e-mailem na valentinogoxhaj@gmail.com. Po úplném vrácení peněz přestaňte Pro používat. Pro můžete upravovat pro své licencované projekty, ale nesmíte zveřejňovat jeho zdrojový kód ani jej prodávat jako samostatný balíček či startovací sadu. Úplné licenční podmínky jsou součástí balíčku.

Pro můžete používat až v pěti produkčních projektech včetně klientských. Vývojové, stagingové a testovací kopie těchto projektů se nezapočítávají zvlášť. Doživotní aktualizace zahrnují budoucí verze Pro, nikoli průběžnou osobní práci na implementaci.

Součástí je jeden 60minutový hovor k instalaci a konfiguraci a migrace metadat jednoho počátečního projektu. Migrace pokrývá podporované zdroje; rozsah potvrdíme před zahájením a vlastní úpravy aplikace se nacení zvlášť. Nastavení pro spuštění zahrnuje kontrolu a konfiguraci llms.txt, pravidel AI robotů v robots.txt a odpovědí v Markdownu pro roboty na stejném projektu s využitím funkcí bezplatného Core. Zahrnutou pomoc si domluvte na hello@rankbeam.dev.

Pro vaši objednávku platí nabídka zobrazená v době nákupu.

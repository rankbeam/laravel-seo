---
description: "Nálezy auditu, upozornění editoru a popisky Filamentu v Rankbeamu sledují jazyk aplikace. Zkopírujte jazykové soubory pro vlastní změny nebo přispějte překladem."
---

# Překlady {#translations}

Každý uživatelský text balíčků — nálezy auditu, živá upozornění pod poli Filamentu, popisky, náhledy i reporty — je jazykový řetězec Laravelu. Balíčky sledují `app()->getLocale()`: panel spuštěný v italštině zobrazí italštinu bez další konfigurace.

**Kódy** problémů a upozornění (`missing_title`, `title_too_long`, …) se nikdy nemění ani nepřekládají. Překládá se pouze lidsky čitelná věta připojená ke kódu.

Dodávané jazyky: angličtina, italština — dřívější řetězce prošly kontrolou, změněné vyžadují novou — a první překlady němčiny, francouzštiny, španělštiny, brazilské portugalštiny, nizozemštiny, turečtiny, ruštiny a polštiny (Tier 1). Od Core 3.16 / Filament 1.10 / Pro 2.35 také japonština, zjednodušená čínština (`zh_CN`), tradiční čínština (`zh_TW`), korejština, řečtina, ukrajinština a čeština (Tier 2). Přesný stav každé jazykové verze je v [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md). Podle tohoto procesu se první překlad stává podporovaným jazykem po kontrole rodilým mluvčím.

## Přepsání řetězce {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Potom upravte `lang/vendor/seo/{locale}/seo.php` a odpovídající složky ostatních balíčků. Ponechané klíče přepíšou původní hodnoty; ostatní použijí soubor balíčku a následně angličtinu.

## Přispění jazykovou verzí {#contribute-a-language}

Zkopírujte soubor `en` do své jazykové verze, přeložte hodnoty, zachovejte každý `:placeholder`, spusťte testy a otevřete pull request. Test shody selže při chybějícím nebo nadbytečném klíči, prázdné hodnotě i ztraceném zástupném parametru. Úplná pravidla a slovník jsou v [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md).

## Co se záměrně nepřekládá {#what-is-not-translated-on-purpose}

Rozhraní CLI je ve výchozím nastavení anglické. Nastavte `seo.cli_locale` / `SEO_CLI_LOCALE` nebo předejte `--display-locale=it` pro překlad podporovaných zpráv a souhrnů auditu. Pro má vlastní nastavení `seo-pro.cli_locale`. Jazyk zobrazení je oddělený od jazyka obsahu zvoleného pomocí `--locale`.

- Nápověda příkazů, diagnostika údržby a výstup `seo:explain` zůstávají anglické. Popisky PASS/WARN/FAIL se nemění.
- Vykreslené HTML (`<meta>`, JSON-LD) používá jazyk vašeho obsahu, nikdy jazyk balíčku.
- Kódy problémů, klíče JSON a stavové kódy zůstávají stabilními identifikátory. Lidsky čitelné popisky ve výstupu `--json` se mohou překládat; integrace mají používat klíče a kódy.

## Druhá část: jazyk vašeho obsahu {#the-other-half-your-content-s-language}

Tato stránka popisuje jazyk, kterým mluví *balíček*. Jak zpracovává jazyk vašeho *obsahu* — délky titulků podle písma, zkracování, velikost písmen, pravidla hreflang, `inLanguage`, regionální vyhledávače a písma obrázků OG — vysvětluje [vícejazyčný obsah](/cs/guide/multilingual).

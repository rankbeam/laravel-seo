---
description: "Přechod z fibonoir/laravel-seo v1 na rankbeam/laravel-seo v2: nový název balíčku a jádro zaměřené na vyhodnocování metadat, vykreslování, JSON-LD a mapy webu."
---

# Přechod z fibonoir/laravel-seo v1 {#upgrading-from-fibonoir-laravel-seo-v1}

Verze v2.0.0 přejmenovává balíček na `rankbeam/laravel-seo` a omezuje jeho záběr na základní funkce: vyhodnocování metadat, vykreslování, JSON-LD a mapy webu. Analyzátor, skener, přesměrování, sledování 404 a administrační rozhraní se přesunuly do samostatných balíčků.

## 1. Vyměňte balíček {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. Aktualizujte jmenné prostory {#_2-update-namespaces}

Názvy tříd se nemění, změnil se jen kořenový jmenný prostor: `Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`. Stačí hledání a nahrazení v celém projektu. Alias fasády `SEO` a direktivy Blade `@seo` zůstávají stejné.

## 3. Odstraňte zastaralé soubory zkopírované z balíčku {#_3-delete-stale-published-files}

Před odstraněním souborů nebo tabulek zálohujte zkopírovanou konfiguraci a exportujte dotčená data. Ověřte, že je dokážete obnovit. Tento průvodce nepřevádí historii přesměrování, 404 ani skenování v1 do odlišného schématu Pro. Níže popsaná kompatibilita tabulek jádra platí pouze pro `seo_meta` a `seo_defaults`.

::: warning Problém se projeví bez chybové zprávy
`seo:install` verze v1 zkopíroval do aplikace soubory, které budou kolidovat s balíčkem v2, aniž by se objevila jediná chybová zpráva.
:::

- **`config/seo.php`** — pokud byl zkopírován z v1 nebo z `ralphjsmit/laravel-seo`, který mohl instalátor v1 zanechat, překryje konfiguraci balíčku a může vyprázdnit `site_name` i všechny šablony `{site_name}`. Odstraňte ho a konfiguraci zkopírujte znovu: `php artisan vendor:publish --tag=seo-config`.
- **Migrace v1** pro tabulky, které už jádro nespravuje: `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache`, `seo_internal_links_index`. Odstraňte soubory migrací. Pokud tabulky existují na produkci, odstraňte je **před** instalací `rankbeam/laravel-seo-pro`. Pro je znovu vytvoří s jiným schématem.
- **Zkopírované šablony souborů** v `app/` a `resources/js` z postupů v1 pro Filament 3, Livewire, Vue a React odkazují na třídy, které už neexistují.

Dvě tabulky jádra, `seo_meta` a `seo_defaults`, mají kompatibilní schéma. Vaše data aktualizaci přežijí.

## 4. Odebrané funkce a jejich nové místo {#_4-removed-features-and-where-they-went}

| Funkce v1 | Kde je nyní |
|---|---|
| SEO sekce formuláře Filament | [`rankbeam/laravel-seo-filament`](/cs/guide/filament), zdarma pod MIT |
| Analyzátor obsahu (32 pravidel) | Starý analyzátor se touto migrací nepřenáší. Technické SEO problémy odhaluje skener webu v `rankbeam/laravel-seo-pro`; číselné SEO skóre je funkcí Pro odvozenou z problémů. |
| Skener celého webu | `rankbeam/laravel-seo-pro` — zpracování přes frontu a přehledové rozhraní |
| Správce přesměrování | `rankbeam/laravel-seo-pro` — zabezpečený, s validací regulárních výrazů a ochranou proti otevřeným přesměrováním |
| Sledování 404 | `rankbeam/laravel-seo-pro` — důraz na soukromí, ve výchozím nastavení bez IP adres |
| Analytika GA4, interní odkazy | Plánované úkoly `rankbeam/laravel-seo-pro` |
| Instalátor `seo:install` | Odstraněn — instalace znamená přidat balíček, zkopírovat konfiguraci a spustit migrace |

## 5. Změny chování ke kontrole {#_5-behavior-changes-to-review}

- **`og:image` / `twitter:image` jsou vždy absolutní URL.** Verze v1 vypisovala ručně nastavené relativní cesty beze změny.
- **Odvozené kanonické adresy odstraňují řetězec dotazu.** Explicitní kanonické adresy zůstávají beze změny.
- **Automatické objevování zdrojů map webu dává přednost registrovaným zdrojům.** Vedle registrovaného `sitemap-posts.xml` už nevzniká duplicitní `sitemap-post.xml`.
- **JSON-LD používá escapování `JSON_HEX_*`.** Pokud dále zpracováváte surový výstup skriptu, počítejte se sekvencemi ve tvaru `<`.

## 6. Známé nástrahy {#_6-known-gotchas}

- Výchozí `DatabaseSeeder` Laravelu používá `WithoutModelEvents`, který vypíná automatické vytváření z `HasSEO` v seederech.
- Pokud šablona výchozího titulku routy už obsahuje značku, zakončete ji nastavenou příponou `title_suffix`. Resolver ji pak nepřidá znovu.

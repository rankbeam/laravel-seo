---
description: "seo:explain ukazuje, která vrstva resolveru nastavila každé SEO pole a co přepsala. Jen čtení, bez sítě a licence, pro ladění neočekávaného titulku nebo robots."
---

# Vysvětlení vyhodnocení (`seo:explain`) {#explain-the-resolution-seo-explain}

Rankbeam vyhodnocuje SEO stránky podle [vrstev přednosti](/cs/concepts/resolver-precedence): konfigurace, databázové výchozí hodnoty — globální, typu modelu a routy —, vypočtené hodnoty modelu a nakonec explicitní `seo_meta`. Následuje zpracování přípony titulku, kanonické adresy a převodu obrázků na absolutní URL a [ochrana indexování](/cs/guide/indexing-guard). Pokud vykreslený `<title>` nebo značka `robots` neodpovídá očekávání, **`seo:explain` přesně ukáže, která vrstva nastavila jednotlivá pole a co přepsala.**

Příkaz pouze čte, nepotřebuje síť ani licenci a neimplementuje slučování znovu. Původ hodnot získává z příspěvků jednotlivých vrstev samotného resolveru a výsledné hodnoty ze skutečného resolveru, takže se vysvětlení nemůže rozejít s vykresleným výstupem.

## Použití {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

Model musí používat trait [`HasSEO`](/cs/guide/quickstart).

## Čtení výstupu {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by** — vítězná vrstva, tedy vrstva s nejvyšší předností, která nastavila hodnotu jinou než null. Případně `post-processing`, pokud pole nenastavila žádná vrstva a hodnota byla *odvozena*: kanonická adresa z URL požadavku či modelu, og:url z kanonické adresy nebo absolutní URL obrázku.
- **Overrode** — všechny nižší vrstvy, které nabídly hodnotu a prohrály, v pořadí. Uvidíte, co bylo překryto.
- **↳ notes** — následné zpracování, které po sloučení vrstev změnilo hodnotu: přípona titulku, odstranění parametrů kanonické URL, odvození og:url, převod obrázku na absolutní URL a ochrana indexování vynucující `noindex` nad všemi vrstvami.

::: tip og:type a twitter:card
Tyto hodnoty mají výchozí nastavení frameworku odlišné od null (`website` / `summary_large_image`), takže nejvyšší vrstva, která je nastaví — obvykle `computed` — má přednost před `config`. Stránka bez uloženého řádku `seo_meta` k nim ničím nepřispívá, takže vypočtené `og:type`, například `article`, nikdy nepřekryje pouhé `website`. To odpovídá skutečnému slučování.
:::

## Vyhodnocení na úrovni webu {#site-level-resolution}

Podle [doplnění záznamu konfigurace webu](/cs/concepts/resolver-precedence) `seo:explain` uvádí také celowebové hodnoty, u kterých bývá původ nejasný: **který zdroj nastavil hostitele kanonických adres, název webu a výchozí jazykovou verzi**:

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Zvlášť důležité je zkontrolovat hostitele kanonických adres. Nesprávný hostitel — uniklé `localhost`, `http://` na webu `https` nebo URL aplikace neodpovídající URL modelu — je běžnou příčinou chybných kanonických odkazů na vlastní stránky.

## Výstup JSON {#json-output}

`--json` vypíše úplný průběh: `target`, hodnoty jednotlivých polí `winner` / `losers` / `final` / `notes` a záznam `site_level` pro nástroje nebo CI:

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## Související {#see-also}

- [Pořadí přednosti resolveru](/cs/concepts/resolver-precedence) — úplný řetězec sledovaný `seo:explain`.
- [Bezplatný SEO audit](/cs/guide/audit) — `seo:audit` hledá, *co je špatně*; `seo:explain` ukazuje, *proč má hodnota právě tuto podobu*.

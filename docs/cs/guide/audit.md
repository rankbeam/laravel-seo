---
description: "Spusťte php artisan seo:audit a získejte tabulku úspěchů, upozornění a chyb SEO pro každou stránku. Uvnitř procesu, bez fronty, licence a sítě. Zdarma v jádru."
---

# Bezplatný SEO audit (`seo:audit`) {#free-seo-audit-seo-audit}

`php artisan seo:audit` jedním příkazem zdarma odpoví na otázku: **co je právě teď s mým SEO špatně?** Projde modely `HasSEO` uvnitř procesu, **bez fronty, licence a sítě**, a vypíše tabulku **úspěchů, upozornění a chyb** jednotlivých stránek se souhrnem.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## Co kontroluje {#what-it-checks}

Audit spouští pouze kontroly **metadat**, tedy kontroly proveditelné ze samotného modelu a [resolveru](/cs/concepts/resolver-precedence), bez načítání stránky:

| Kontrola | Kódy |
|---|---|
| Přítomnost titulku a popisu se zohledněním náhradních hodnot | `missing_title`, `missing_description` |
| Přítomnost obrázku OG se zohledněním náhradních hodnot | `missing_og_image` |
| Délka titulku a popisu | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Duplicitní titulek nebo popis napříč webem | `duplicate_title`, `duplicate_description` |
| Konflikty robots a podezřelé noindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Formát kanonické adresy, jiná doména, sdílená nebo nezabezpečená adresa | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Připravenost pro odpovědi (AEO): strukturovaná data článku | `aeo_missing_author`, `aeo_article_missing_date` |
| Nastavené hlavní klíčové slovo, po výslovném zapnutí | `missing_focus_keyword` |
| Alternativy hreflang, registr jádra, pokud je stránka uvádí | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

Většina kódů se objevuje také ve skenování Pro, registry jsou ale samostatné. Jádro zejména používá `hreflang_missing_self`, zatímco Pro používá `hreflang_missing_self_reference`. `hreflang_duplicate_code` je v jádru oznámení a v Pro upozornění. Ze společného názvu nevyvozujte shodný rozsah kontrol nebo závažnost. `blank_explicit_override` patří do registru jádra. Délka používá [doporučené limity editoru podle písma](/cs/guide/multilingual#title-and-description-budgets-per-script): 60/160 znaků pro latinku, přibližně 30/80 pro CJK, počítané v grafémech a měřené na **vyhodnocené** hodnotě včetně přípony. Audit se tak nerozchází s počítadly znaků v [editoru Filament](/cs/guide/filament). Kontroly hreflang pracují se seznamem po uplatnění pravidel `seo.hreflang`, tedy se stejným seznamem jako značky a mapa webu. Vzájemnost odkazů vyžaduje procházení stránek a zůstává v Pro.

Kontroly **připravenosti pro odpovědi (AEO)** se spustí pouze u stránky deklarující JSON-LD článkového typu (`Article`, `BlogPosting`, `NewsArticle`, …), pokud chybí údaj potřebný pro srozumitelný popis článku ve strukturovaných datech: entita `author` pro explicitní autorství a původ nebo `datePublished` / `dateModified` pro časovou osu. Stránka bez článku se neoznačí, takže tam, kde se kontrola AEO neuplatňuje, audit mlčí. Jde o doporučující oznámení na úrovni notice, která nevstupují do skóre Pro 0–100.

## Co nekontroluje: hranice funkcí {#what-it-does-not-check-—-the-capability-boundary}

Bezplatný audit uvnitř procesu nemůže nahradit úplné skenování Pro a příkaz to při každém běhu uvádí. Nespouští:

- **Kontroly vykresleného HTML** — `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content`, `mixed_content`. Potřebují HTML skutečně poskytované stránkou.
- **Síťové kontroly živých kanonických adres** — `canonical_target_broken` / `_redirect` / `_noindex`. Potřebují odchozí načtení s ochrannými kontrolami.
- **Číselné skóre 0–100.** Skóre je funkcí Pro, uloženou u výsledku skenování s verzovanými kritérii. Viz [SEO skóre](/cs/pro/scoring).

Tyto kontroly poskytuje **skenování Pro**; viz úplný [registr problémů](/cs/pro/scan-issues).

## Výběr obsahu k auditu {#choosing-what-to-audit}

Ve výchozím nastavení příkaz audituje modely uvedené v `seo.audit.models`. Náhradním zdrojem je `seo.sitemap.models`:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Modely můžete také předat explicitně:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Možnosti {#options}

| Možnost | Účinek |
|---|---|
| `--model=` | Třída modelu `HasSEO` k auditu, lze opakovat. Přepisuje konfiguraci. |
| `--locale=` | Vyhodnotí SEO data v tomto jazyce; výchozí je jazyk aplikace. |
| `--limit=` | Nejvyšší počet záznamů na model; `0` znamená všechny. |
| `--issues-only` | Vypíše jen stránky s alespoň jedním problémem. |
| `--strict` | Při jakémkoli problému skončí s nenulovým stavem, vhodné pro CI. |
| `--json` | Místo tabulky vypíše strojově čitelný JSON: stránky, souhrn a rozsah kontrol. |

### Podmínka CI {#ci-gate}

`--strict` promění audit v kontrolu sestavení:

```bash
php artisan seo:audit --strict
```

Pokud kterákoli stránka obsahuje upozornění nebo chybu, skončí s `1`. Když všechny auditované stránky projdou, vrátí `0`.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Hlavní klíčová slova {#focus-keywords}

Oznámení `missing_focus_keyword` je **ve výchozím nastavení vypnuté**. Objeví se až po výslovném zapnutí práce s hlavními klíčovými slovy:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Skenování Pro čte **stejný** příznak, takže audit, skenování i upozornění editoru Pro zůstávají v souladu. Klíčová slova stránky nastavíte [polem ve Filamentu](/cs/guide/filament) nebo přes `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Když hodnota neodpovídá očekávání: `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` říká, *co je špatně*. [`seo:explain`](/cs/guide/explain) vysvětluje, *proč se pole vyhodnotilo právě takto*: která vrstva — konfigurace, výchozí, vypočtená nebo explicitní hodnota — nastavila každé pole, co přepsala a jak výsledek změnilo následné zpracování, například přípona titulku, úprava kanonické adresy nebo ochrana indexování. Použijte ho při překvapivém nálezu auditu nebo vykreslené značce:

```bash
php artisan seo:explain "App\Models\Post" 42
```


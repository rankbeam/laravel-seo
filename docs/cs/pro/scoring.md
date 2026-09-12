---
description: "SEO skóre 0–100 ze skenu Pro: plně dohledatelné a deterministické. Každý odečtený bod odpovídá jednomu problému skenu, takže stejné problémy vždy dávají stejné číslo."
---

# SEO skóre — transparentní, verzované, součást Pro {#the-seo-score-—-transparent-versioned-pro-owned}

Sken Pro přiřadí každé stránce **SEO skóre 0–100**, tedy jedno číslo, které při přechodu z Rank Math nebo Yoastu očekáváte. Na rozdíl od neprůhledného hodnocení je **plně dohledatelné**: každý odečtený bod odpovídá přesně jednomu [problému skenu](/cs/pro/scan-issues) a stejná sada problémů vždy vytvoří stejné číslo.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip Jedno číslo, jeden vlastník
Číselné skóre je funkcí **Pro**. Ukládá se do záznamu `seo_scan_results` v Pro, nikdy zpět do `seo_meta` v Core; starý sloupec `seo_score` byl v Core 3 odstraněn. Bezplatný [`seo:audit`](/cs/guide/audit) v Core hlásí u stránek **pass / warn / fail**, tedy úspěch, upozornění nebo chybu, **bez čísla**. Skóre je přidanou hodnotou placeného balíčku.
:::

## Kritéria hodnocení {#the-rubric}

Skóre se počítá podle **zveřejněných a verzovaných kritérií** v `Rankbeam\Seo\Pro\Scanning\ScoreRubric`. Definují je dvě věci: výslovný **seznam započítávaných kódů** problémů a pevná **srážka podle závažnosti**.

| Závažnost | Srážka | Význam |
|---|---|---|
| `critical` | **−40** | Nález s vysokým dopadem podle těchto kritérií. |
| `warning` | **−15** | Nález, který je vhodné brzy prošetřit. |
| `notice` | **−5** | Doporučené zlepšení. |

Závažnost každého kódu se čte přímo z [registru problémů](/cs/pro/scan-issues), jediného zdroje pravdy. Kritéria ji znovu neodvozují. Každý kód má právě jednu závažnost, aby skóre zůstalo deterministické.

### Co se do skóre započítává {#what-the-score-counts}

Jde o deterministické kontroly zvolené podle produktových kritérií Rankbeamu, včetně heuristik vyžadujících redakční posouzení. Kritický problém stojí 40 bodů, upozornění 15 a oznámení 5. Skóre nepředpovídá výkon ve vyhledávání.

| Kód | Závažnost | Srážka |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

Kódy metadat se zjišťují při skenování modelu, zatímco kódy vykreslování a sítě pouze při skenování URL; viz [třídy provádění](/cs/pro/scan-issues#execution-classes). Skóre cíle typu **model** proto odráží kontroly metadat a skóre cíle typu **URL** vykreslenou stránku. Skóre modelu 100 znamená „žádné vady metadat“, nikoli „vykreslená stránka je dokonalá“. K tomu oskenujte URL.

### Co se do skóre záměrně NEZAPOČÍTÁVÁ {#what-the-score-deliberately-does-not-count}

Tyto kódy registru jsou výslovně vyloučené. Vyloučení je součástí kontraktu: test ověřuje, že každý kód registru se buď započítává, nebo je uveden zde.

| Kód | Proč je vyloučený |
|---|---|
| `missing_focus_keyword` | **Doporučení.** Vyžaduje volitelný postup `seo.keywords.enabled`. Stránka nesmí získat nižší skóre jen proto, že nepoužívá hlavní klíčová slova, a skóre nesmí záviset na konfiguračním příznaku. |
| `noindex_page` | **Informace.** `noindex` je záměrný stav, nikoli vada kvality metadat. Heuristika „noindex s kanonickou URL vlastní stránky“ se místo toho hodnotí přes `noindex_warning`. |
| `multiple_h1` | **Informace.** Google toleruje více H1, proto za ně není srážka. |
| `blocked_url` | **Chybějící důkaz.** SsrfGuard odmítl načtení, takže stránka nebyla zkontrolována. Nejde o vadu stránky. |
| `canonical_target_blocked` | **Chybějící důkaz.** Kanonický cíl nebylo možné ověřit. Nejde o vadu stránky. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Zatím doporučení.** Tyto kódy hreflang v Pro se zobrazují ve skenu. Bezplatný audit má vlastní kódy hreflang; skóre se jimi zatím nemění. Jejich započítání by vyžadovalo zvýšení `VERSION`. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **Doporučení.** Jazykové kontroly jsou z těchto kritérií vyloučené. |
| `hreflang_not_reciprocal` | **Doporučení.** Volitelná kontrola vzájemnosti odkazů se neboduje. |
| `hreflang_target_unverified` | **Chybějící důkaz.** Vzájemnost odkazů nebylo možné ověřit. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Doporučení.** Signály připravenosti pro odpovědi (AEO) ve skenu a bezplatném auditu označují článek bez entity autora nebo data publikace. Skóre nemění; to by vyžadovalo zvýšení `VERSION`. |

Hustota klíčových slov, působivá slova a zbytek [kontrolního seznamu stránky](/cs/pro/on-page-checklist) do skóre vůbec nevstupují. Jsou to doporučující kontroly v samostatném seznamu pass/warn/fail, nikoli kódy registru.

## Verzování — historické skóre se nikdy tiše nemění {#versioning-—-historical-scores-never-silently-change}

Každé uložené skóre je označené hodnotou `ScoreRubric::VERSION`, která jej vytvořila (`rubric_version`). Má to dva důsledky:

- **Nový** kód problému nemá **žádný** bodový dopad, dokud není záměrně přidán do seznamu. Vydání nové kontroly tak nikdy zpětně nezmění uložené skóre. Změna seznamu nebo vah je sama změnou kritérií a zvyšuje jejich verzi.
- Skóre je **uložené a při čtení se nepřepočítává**. Číslo, které jste viděli minulý týden, uvidíte i dnes spolu s kritérii, která ho vysvětlují.

## Kam se ukládá {#where-it-s-stored}

Každý sken vloží nebo aktualizuje jeden řádek na cíl v `seo_scan_results`:

| Sloupec | Co obsahuje |
|---|---|
| `scannable_type` / `scannable_id` | Hodnocený model; null u cílů typu URL. |
| `url` | Hodnocená URL. |
| `score` | Číslo 0–100. |
| `rubric_version` | Kritéria, podle kterých vzniklo. |
| `penalty_total` | Hrubý součet srážek **před** omezením výsledku zdola nulou. |
| `scored_issues` | Kolik problémů číslo ovlivnilo. |
| `breakdown` | `[{code, severity, penalty}, …]` — úplný rozpis. |
| `keywords_enabled` | Stav `seo.keywords.enabled` při skenování; zaznamenává se pro transparentnost, skóre na něm nezávisí. |
| `scan_run_id` | Běh, který skóre vytvořil. Při odstranění běhu se nastaví null, řádek se neodstraní: skóre je aktuální stav, nikoli historie běhů. |
| `scored_at` | Kdy bylo skóre vypočítáno. |

## Čtení skóre {#reading-the-score}

**Bez panelu** — nejnovější skóre modelu:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` vypisuje v souhrnu **průměrné skóre webu**. Přehled Filamentu jej ukazuje jako hlavní statistiku „Prům. SEO skóre“ s barvou podle známky.

### Pásma známek {#grade-bands}

Známka písmenem slouží k prezentaci a odvozuje se z čísla; závazná je číselná hodnota:

| Skóre | Známka |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Signál ke kontrole před zveřejněním (`noindex_warning`) {#the-shipping-signal-noindex-warning}

`noindex_warning` vzniká, když stránka kombinuje `noindex` s **kanonickou URL vlastní stránky**. Rankbeam to považuje za signál ke kontrole před zveřejněním. Kanonická URL vlastní stránky **nedokazuje** záměr indexovat: kombinace může být úmyslná. Kanonická URL na jiné doméně tuto heuristiku nespouští. Problém obsahuje `context.shipping_signal`, například `self_canonical`, a porovnávané `canonical` a `page_url`.

Kontrolu používají oba skenery. Sken modelu (`PageScanner`) porovnává uloženou kanonickou adresu s URL modelu. Sken vykreslené URL (`UrlScanner`) povýší stránku `noindex` s kanonickou URL vlastní stránky z informativního `noindex_page` na bodovaný problém `noindex_warning`. Proto je samotné `noindex_page` vyloučené: možný konflikt řeší v obou cestách `noindex_warning`. Před změnou direktivy indexace ověřte skutečný záměr stránky.

## Konfigurace {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

Seznam započítávaných kódů ani váhy **nejsou konfigurovatelné**. Pro danou hodnotu `rubric_version` musí být skóre deterministické ve všech instalacích. Změna výpočtu je proto změnou kritérií na úrovni kódu, nikoli nastavením.

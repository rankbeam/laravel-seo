---
description: "Stabilní registr kódů všech problémů hlášených skenem Pro. Každý kód má pevnou závažnost a pole, takže přehledy a exporty čtou kódy, nikoli text zpráv."
---

# Problémy skenu — registr kódů {#scan-issues-—-the-issue-code-registry}

Každý problém hlášený skenem Pro má **stabilní kód** z jediného registru, `Rankbeam\Seo\Pro\Scanning\IssueRegistry`. Skenery nikdy nevymýšlejí kód přímo v místě použití. Každý problém sestavují přes `IssueRegistry::make()`, které doplní závažnost a pole z registru a **odmítne nedefinovaný kód**. Na katalogu níže tak můžete stavět: přehledy, exporty i [skóre Pro](/cs/pro/scoring) čtou kódy místo parsování zpráv. Bezplatný [`seo:audit`](/cs/guide/audit) používá vlastní registr metadat Core s užším pokrytím a některými odlišnými kódy hreflang.

Každý kód obsahuje:

- **id** — stabilní řetězec uložený jako `seo_scan_issues.issue_type`.
- **severity** — `critical`, `warning` nebo `notice`; **pevná pro kód**. Místo proměnlivé závažnosti kód rozdělíme.
- **field** — dotčené pole `seo_meta` nebo _page_ pro zjištění na úrovni stránky.
- **třída provedení** — co je potřeba ke zjištění, viz níže.
- **evidence** — klíče obsažené v poli `context` problému.

## Třídy provedení {#execution-classes}

Podle požadavků na spuštění patří kontrola právě do jedné ze tří tříd:

| Třída | Potřebuje | Kdo ji spustí |
|---|---|---|
| **metadata** | Model a resolver Core, bez načtení stránky | Sken modelu, `PageScanner`; bezplatný [`seo:audit`](/cs/guide/audit) pokrývá podmnožinu kontrol metadat |
| **rendered** | Poskytované HTML stránky, požadavek kernelu uvnitř procesu nebo externí načtení | Sken URL, `UrlScanner` |
| **network** | **Odchozí** požadavek k ověření _jiného_ cíle, například kanonické URL mířící jinam | Sken URL, **vždy přes `SsrfGuard`** |

Proto se bezplatný audit uvnitř procesu nemůže vyrovnat úplnému skenu Pro. Bez vykreslení stránky lze spočítat pouze kódy **metadata**; jen postup Pro načítá vykreslené HTML a ověřuje kanonické cíle po síti. Registr filtrujte podle třídy pomocí `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`.

## Kódy metadat {#metadata-codes}

Zjišťuje je `PageScanner` z modelu a resolveru. `missing_title`, `missing_description` a kódy délky vydává také sken vykreslené URL měřením poskytovaného `<head>`. Stejné kódy mají stejný význam.

| Kód | Závažnost | Pole | Důkazy | Význam |
|---|---|---|---|---|
| `missing_title` | critical | title | — | Chybí titulek i vypočitatelná náhradní hodnota. |
| `missing_description` | warning | description | — | Chybí meta popis i vypočitatelná náhradní hodnota. |
| `missing_og_image` | notice | og_image | — | Chybí obrázek Open Graph i vypočitatelná náhradní hodnota. |
| `missing_focus_keyword` | notice | focus_keywords | — | Není nastavené hlavní klíčové slovo. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | Titulek se opakuje na dalších stránkách stejného národního prostředí. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | Popis se opakuje na dalších stránkách stejného národního prostředí. |
| `title_too_long` | warning | title | `length`, `max`, `script` | Vyhodnocený titulek překračuje doporučení pro písmo: 60 pro latinku, přibližně 30 pro CJK. |
| `title_too_short` | notice | title | `length`, `min`, `script` | Vyhodnocený titulek je pod spodním prahem písma: 30 pro latinku, přibližně 15 pro CJK. |
| `description_too_long` | warning | description | `length`, `max`, `script` | Vyhodnocený popis překračuje doporučení písma: 160 / přibližně 80. |
| `description_too_short` | notice | description | `length`, `min`, `script` | Vyhodnocený popis je pod spodním prahem písma: 70 / přibližně 35. |
| `robots_conflict_indexing` | critical | robots | `robots` | Direktiva robots obsahuje současně `index` a `noindex`. |
| `robots_conflict_following` | warning | robots | `robots` | Direktiva robots obsahuje současně `follow` a `nofollow`. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | Stránka s kanonickou URL na sebe má `noindex`: heuristika ke kontrole, nikoli důkaz nutnosti indexace. Vydává ji sken modelu i vykreslené URL. |
| `invalid_canonical` | critical | canonical | `canonical` | Kanonická hodnota není platná URL. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | Kanonická URL míří na jiného hostitele než stránka. |
| `shared_canonical` | notice | canonical | `canonical` | Několik stránek deklaruje tutéž kanonickou URL. |
| `insecure_canonical` | warning | canonical | `canonical` | Kanonická URL `http://` na webu `https`. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | Alternativa hreflang používá hodnotu, která není `x-default` ani platný jazykový kód BCP-47. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | Alternativy jsou deklarované, ale žádná neodkazuje na vlastní národní prostředí stránky. |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | Stejný kód hreflang odpovídá více URL, tedy nejednoznačné skupině. |
| `hreflang_missing_x_default` | notice | alternates | `languages` | Vícejazyčná skupina hreflang nemá náhradní `x-default`. |
| `aeo_missing_author` | notice | schema | — | Článek ve strukturovaných datech stránky nemá entitu autora; autorství a původ nejsou ve schématu výslovné. |
| `aeo_article_missing_date` | notice | schema | — | Článek ve strukturovaných datech stránky nemá datum publikace nebo úpravy; časová osa není ve schématu výslovná. |

Prahy délky pocházejí z [pravidel délky](/cs/guide/multilingual#title-and-description-budgets-per-script) Core podle písma, od Pro 2.33: 60/160 pro latinku a přibližně 30/80 pro CJK, počítáno v grafémech. Sken tak nikdy neodporuje počitadlům znaků v editoru. Spodní meze, titulek 30 a popis 70 pro latinku a přibližně polovina pro CJK, jsou prahem nedostatečné optimalizace ve skenu. Klíč kontextu `script` označuje použitou skupinu. Délka se měří nad **vyhodnoceným** titulkem a popisem, tedy skutečně vykreslenou hodnotou včetně náhradních hodnot a přípony titulku.

Kódy `hreflang_*` ověřují alternativy hreflang deklarované stránkou, čtené z `alternates` resolveru: neplatné či duplicitní kódy, chybějící odkaz na sebe a chybějící `x-default` u vícejazyčné skupiny. Běží jen při deklarovaných alternativách. Tyto kontroly metadat neověřují **vzájemnost** odkazů mezi stránkami. Jinou stránku načítá volitelná síťová kontrola níže.

Kódy `aeo_*` jsou signály **připravenosti pro odpovědi, AEO**: je obsah článku čitelný ve strukturovaných datech? Čtou vyhodnocený graf JSON-LD a spouštějí se **pouze** při deklarovaném článkovém typu, například `Article`, `BlogPosting` či `NewsArticle`, bez entity `author` pro výslovné autorství a původ nebo bez `datePublished` / `dateModified` pro výslovnou časovou osu. Stránka bez článku se nikdy neoznačí. Řídí je `seo-pro.scan.checks.aeo`, standardně zapnuté, a odpovídají bezplatnému [`seo:audit`](/cs/guide/audit).

::: tip `missing_focus_keyword` má přepínač
Upozornění na hlavní klíčové slovo se spouští jen při zapnutém pracovním postupu **Core**, `seo.keywords.enabled` s výchozím `false`. Při vypnutí sken neoznačí stránku kvůli chybějícímu klíčovému slovu. Bezplatný příkaz [`seo:audit`](/cs/guide/audit) a editor Filamentu čtou **stejný** přepínač Core, takže upozornění skenu, auditu a editoru se vždy shodují. Přepínač existuje jen jeden.
:::

## Kódy vykreslené stránky {#rendered-codes}

`UrlScanner` je zjišťuje z poskytovaného HTML. Pro cíle na stejném hostiteli použije požadavek kernelu uvnitř procesu bez odchozího provozu, pro externí cíle chráněné načtení.

| Kód | Závažnost | Pole | Důkazy | Význam |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | URL odpověděla stavem 4xx/5xx. |
| `empty_response` | critical | page | — | URL vrátila prázdné tělo. |
| `missing_canonical` | notice | canonical | — | Ve vykreslené hlavičce chybí `<link rel="canonical">`. |
| `noindex_page` | notice | robots | `robots` | Vykreslená stránka má `noindex`, informativní. Stránka s `noindex` a současně **kanonickou URL na sebe** místo toho dostane skórované `noindex_warning`. |
| `missing_h1` | notice | page | — | Chybí nadpis `<h1>`. |
| `multiple_h1` | notice | page | `count` | Více než jeden `<h1>`, informativní. |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | Obrázkům obsahu chybí atribut `alt`. Výslovné `alt=""` je dekorativní a neoznačí se. |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter` | Hlavní text je pod nastaveným počtem slov. Počítá tokenizér kontrolního seznamu: mezery pro písma s mezerami, slovníkové dělení ICU, `segmenter: intl` vyžadující ext-intl, pro čínštinu, japonštinu a thajštinu. Japonský článek o 400 slovech tak není jedním „slovem“. |
| `mixed_content` | warning | page | `count`, `sample` | Prostředky `http://` na stránce `https`. |
| `html_lang_missing` | notice | page | — | Chybějící nebo prázdné `<html lang>`. Asistenční technologie může zvolit nevhodný hlas. |
| `html_lang_invalid` | notice | page | `declared` | Hodnota `lang` není značka BCP-47, například `english`, `en_US` s podtržítkem nebo `jp`. |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script` | Viditelný hlavní text používá jiné písmo než deklarovaný jazyk: `lang="en"` na japonské stránce, `lang="ru"` u latinky. Jen úroveň písma: nesprávný jazyk latinky u textu latinkou by byl odhad a sken neodhaduje. Potřebuje ≥ 40 písmen hlavního textu. |

## Síťové kódy {#network-codes}

`UrlScanner` je zjišťuje jen při zapnutém odpovídajícím přepínači: `seo-pro.scan.url_checks.check_canonical_target` pro kanonický cíl, `check_hreflang_reciprocity` pro alternativy hreflang. Každý cíl se načítá **přes `SsrfGuard`** se seznamem povolených schémat, rozsahem hostitelů, odmítnutím soukromých IP a limity přesměrování, času a velikosti. Přesměrování se **nenásledují**, aby byl přesměrující kanonický cíl viditelný. Kanonický odkaz nebo alternativa na sebe se přeskočí: samotná stránka se právě načetla.

| Kód | Závažnost | Pole | Důkazy | Význam |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | `SsrfGuard` odmítl cíl před jakýmkoli HTTP požadavkem. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | Kanonická URL míří na stránku vracející chybu HTTP. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | Kanonická URL míří na přesměrování; použijte konečnou URL. |
| `canonical_target_noindex` | warning | canonical | `canonical` | Kanonická URL míří na stránku, která sama má `noindex`. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | Kanonický cíl nešlo ověřit: odmítnutí ochrany nebo nedostupnost. |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status` | Deklarovaná alternativa neodkazuje zpět na stránku. Dvojice hreflang může být ignorována; samo o sobě to neznemožňuje indexaci překladu. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason` | Alternativu nešlo načíst kvůli odmítnutí ochrany, chybovému stavu, přesměrování nebo překročení velikosti. Vzájemnost se proto nikdy neověřila. Chybějící důkaz, nikoli závada. |

Vzájemnost načítá nejvýše `hreflang_max_alternates` cílů na stránku, standardně 10, včetně `x-default`. Duplicity a samotná stránka se přeskočí. Výše uvedené kódy metadat `hreflang_*` na úrovni modelu ověřují *deklarovaný* seznam; tato kontrola procházením potřebuje druhou stránku.

Všechny síťové postupy zde znovu používají sdílený `SsrfGuard`. Model hrozeb a poznámku o zbývajícím riziku TOCTOU popisuje [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md).

## Jak kódy vstupují do skóre {#how-codes-feed-the-score}

[Skóre SEO v Pro](/cs/pro/scoring) je `100 −` pevná penalizace za každý skórovaný problém, vážená výše uvedenou závažností. Většina kódů se počítá, několik je záměrně vyňatých: `missing_focus_keyword` jako doporučení, `noindex_page` a `multiple_h1` jako informace, `blocked_url` / `canonical_target_blocked` / `hreflang_target_unverified`, protože „nešlo ověřit“ není závada, a `hreflang_*`, `html_lang_*` a `aeo_*` jako doporučující signály zatím ponechané mimo skóre. [Stránka hodnocení](/cs/pro/scoring) obsahuje úplný seznam zahrnutých kódů a penalizaci každého z nich.

## Životní cyklus problému {#issue-lifecycle}

Problém není jen řádek existující po dobu závady. Má životní cyklus a sken problémy cíle **sladí se zjištěným stavem**, místo aby je smazal a znovu vytvořil. Každý problém má stabilní identitu: cíl, tedy `scannable_type` + `scannable_id` pro model nebo `url` pro cíl trasy či mapy webu, a jeho `issue_type`. Každý kód se vydá nejvýše jednou pro cíl za sken. Kódy s více výskyty, například `missing_image_alt`, `mixed_content` a `hreflang_*`, je sloučí do jediného řádku s `count` / `sample`. Identita je tak jedinečná.

Při každém skenu pro každý cíl:

- se zjištění **bez existujícího řádku** vytvoří jako `open` s časem `detected_at`;
- zjištění **odpovídající otevřenému řádku** obnoví důkazy a zachová původní `detected_at`, stabilní *první pozorování*, které se už při každém skenu neresetuje;
- otevřený problém, který dokončená kontrola **už nenajde**, se označí **`fixed`** s časem `resolved_at`. Řádek se **ponechá, nesmaže**, takže se zaznamená skutečná oprava;
- problém ve stavu **`fixed`**, který se **vrátí**, se na místě **znovu otevře**, jde o návrat problému, a obnoví se `detected_at`;
- problém označený uživatelem v přehledu jako **`ignored`** zůstane nedotčený.

| Stav | Význam | Nastavuje |
|---|---|---|
| `open` | Aktuálně přítomný. | Sken, nový nebo stále nalezený |
| `fixed` | Byl přítomný, už se nenašel. | Sken automaticky při příštím běhu, který jej znovu nenajde |
| `ignored` | Umlčený uživatelem; vynechaný z otevřených počtů a skóre. | Akce Ignorovat v přehledu |

Protože se opravy nyní zaznamenávají místo zahazování, [report s vlastní značkou](/cs/pro/reports) může ukázat **skutečné počty opravených a nových problémů** za období místo rozdílu snímků mezi reporty. Všechny části čtoucí počty otevřených problémů, tedy přehled, příkaz [`seo-pro:scan-status`](/cs/pro/headless) a [skóre](/cs/pro/scoring), filtrují na `open`. Uložené řádky `fixed` je proto nikdy nenavyšují. Opravené řádky se přiřadí běhu, který je vyřešil, a odstraní se po běžné [době uchovávání](/cs/pro/production) běhů skenu.

## Konfigurace {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

Limit velikosti odpovědi pro chráněná načtení je `seo-pro.http.max_response_bytes`, standardně 2 MB. Sken stejného hostitele uvnitř procesu tento limit nemá.

## Poznámka ke kompatibilitě (přejmenování kódu) {#compatibility-note-issue-code-rename}

Dřívější jediný kód `robots_conflict`, který měl dvě závažnosti, se rozdělil, aby každý kód odpovídal právě jedné:

| Starý kód | Nový kód | Závažnost |
|---|---|---|
| `robots_conflict` (index + noindex) | `robots_conflict_indexing` | critical |
| `robots_conflict` (follow + nofollow) | `robots_conflict_following` | warning |

Pokud jste ukládali nebo filtrovali podle `robots_conflict`, přejděte na dva nové kódy.

---
description: "Kontrolní seznam stránky se stavy vyhovuje, upozornění a nevyhovuje. Zvolte hlavní klíčové slovo a ověřte titulek, URL, úvod, metadata, délku, obrázky a čitelnost."
---

# Kontrolní seznam stránky — klíčová slova a stavy kontrol {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

Kontrolní seznam stránky poskytuje průběžnou redakční kontrolu, kterou očekává uživatel RankMath nebo Yoast. Zvolíte hlavní klíčové slovo a dostanete barevně rozlišené kontroly odpovídající na otázku „je pro ně stránka optimalizovaná?“: klíčové slovo v titulku, URL, úvodním odstavci a meta popisu, dále délka, obrázky, interní odkazy a **čitelnost**.

Běží **během požadavku**, bez fronty a sítě, nad modelem, [resolverem](/cs/concepts/resolver-precedence) a vlastním textem stránky. Záměrně **nejde o číslo**.

::: tip Kontrolní seznam ≠ skóre
Kontrolní seznam má jen stavy **vyhovuje / upozornění / nevyhovuje** a je zcela oddělený od [skóre SEO v Pro](/cs/pro/scoring). Nesdílí žádné kódy s jeho pravidly hodnocení a nikdy jej nemůže změnit. Redakční doporučení zůstávají oddělená od číselného hodnocení. Zejména hustota klíčových slov a čitelnost jsou **doporučující**, viz níže.
:::

## Co kontroluje {#what-it-checks}

| Kontrola | Skupina | Co hledá |
|---|---|---|
| `keyword_in_title` | klíčová slova | Hlavní klíčové slovo je v titulku SEO. |
| `keyword_in_description` | klíčová slova | Hlavní klíčové slovo je v meta popisu. |
| `keyword_in_url` | klíčová slova | Hlavní klíčové slovo je ve slugu URL. |
| `keyword_in_first_paragraph` | klíčová slova | Hlavní klíčové slovo je v úvodním odstavci. |
| `keyword_density` | klíčová slova | **Doporučení.** Hustota působí přirozeně; bez cílové hodnoty, viz níže. |
| `title_length` | metadata | Titulek je ve stejném rozmezí jako v editoru a skenu: 30–60 pro latinku, přibližně 15–30 pro CJK podle [pravidel délky](/cs/guide/multilingual#title-and-description-budgets-per-script) Core, od Pro 2.33. |
| `description_length` | metadata | Popis je ve stejném rozmezí: 70–160 pro latinku, přibližně 35–80 pro CJK. |
| `content_length` | obsah | Dostatek textu; pásma počtu slov určuje konfigurace. |
| `readability` | obsah | **Doporučení.** Odhad čitelnosti zvoleným vzorcem pro deset jazyků, náhradním LIX nebo označenou heuristikou bez skóre pro japonštinu, čínštinu a korejštinu. |
| `has_image` | média | Obsah zahrnuje alespoň jeden obrázek. |
| `internal_links` | odkazy | Obsah odkazuje na související interní stránky. |

Pokud hlavní klíčové slovo není nastavené, kontroly klíčových slov se **přeskočí**, tedy nevyhoví ani neselžou. Kontrolní seznam vás vyzve k jeho přidání. Použijte [pole hlavního klíčového slova](/cs/guide/filament) nebo `saveSEO(['focus_keywords' => …])`.

### Porovnávání klíčových slov {#keyword-matching}

Klíčové slovo a text se porovnávají po **sjednocení velikosti písmen a stemmingu**, tedy redukci slovních tvarů. „espresso grinder“ tak odpovídá „espresso grinders“ a s jazykem analýzy turecké „İstanbul“ odpovídá „istanbul“, řecké „ΟΔΟΣ“ odpovídá „οδος“ a německé „Straße“ odpovídá „STRASSE“, přes `CaseFolder` z Core. Předejte národní prostředí analýzy: `SeoPro::checklistFor($post, 'it')` nebo `--locale=it`.

Od Pro 2.36.1 používají klíčová slova, synonyma i text polí před stemmingem stejný tokenizér. Shoda vyžaduje po sobě jdoucí **celé tokeny**: `cat` neodpovídá `education` a japonské fráze používají stejné hranice slov ICU jako obsah. Apostrofy a spojovníky tokeny oddělují, takže `meta-tag` odpovídá `meta tag` a rovné i typografické apostrofy se chovají stejně. Kombinující znaménka zůstávají u svých písmen. Sjednocení velikosti zachová diakritiku; stemmer konkrétního jazyka může provést další redukce.

Počítání výskytů vybere na každé pozici nejdelší odpovídající klíčové slovo nebo synonymum a daný úsek započítá jednou. Duplicitní synonyma a překrývající se kratší alternativy hustotu nenavyšují. Například klíčové slovo `seo` se synonymem `seo tools` má v `seo tools seo` dva výskyty. Pro slovníkové hranice slov v písmech bez mezer zůstává nutné ICU; náhradní regulární výraz je neposkytne.

Od Pro 2.37 používá stemming **dodanou podmnožinu Snowball 3.1.1**. Nepotřebuje další balíček Composer a za běhu nic nestahuje. PHP 8.2 zůstává podporované.

| Nástroj | Kdy | Jazyky |
| --- | --- | --- |
| `snowball` | Výchozí; stávající nastavení `auto` vybere stejný dodaný nástroj | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | Výslovné `seo-pro.checklist.analysis.stemmer = builtin` | Jen angličtina se starším jednoduchým stemmerem ohýbaných tvarů; ostatní jazyky používají porovnání beze změny tvaru |
| `identity` | Nepodporovaný jazyk nebo výslovný režim `none` | Ukrajinština, japonština, čínština, korejština, thajština a další jazyky mimo dodanou podmnožinu |

Obě strany porovnání používají stejný nástroj. Stemming je algoritmus redukce přípon, nikoli slovník synonym nebo záruka jazykové rovnocennosti. Řecký algoritmus například dokáže spojit tvary s přízvukem a bez něj, které porovnání beze změny tvaru rozlišuje. Hranice celých tokenů stále brání tomu, aby `cat` odpovídalo `education`.

#### Aktualizace z Pro 2.36 {#upgrading-from-pro-2-36}

Stávající konfigurace `auto` nyní soustavně používá dodané algoritmy, ať je balíček `wamania/php-stemmer` nainstalovaný, nebo není. Po aktualizaci znovu zkontrolujte redakční doporučení: nové algoritmy mohou změnit shody a turečtina, řečtina, polština a čeština nově mají stemming. Další algoritmy volitelného wrapperu pro katalánštinu, dánštinu, finštinu, norštinu, rumunštinu a švédštinu jsou mimo tuto podmnožinu a nyní používají porovnání beze změny tvaru.

Nastavte `SEO_PRO_CHECKLIST_STEMMER=builtin` pro předchozí náhradní postup jen pro angličtinu nebo `none` pro porovnání se sjednocenou velikostí písmen bez změny tvaru ve všech jazycích. Po změně nastavení znovu vytvořte mezipaměť konfigurace. Tyto volby nereprodukují vícejazyčné algoritmy starého volitelného wrapperu. Zachovat přesně jejich výsledky vyžaduje ponechat předchozí vydání Pro. Žádná uložená metadata SEO se nepřepisují.

Dodaný adaptér prochází 600 395 pevně stanovenými oficiálními dvojicemi slov a výstupů na PHP 8.2, 8.3 a 8.4. To dokládá shodu algoritmu, nikoli redakční schválení rodilým mluvčím. Balíček obsahuje zdrojové hashe, úpravu pouze syntaxe pro PHP 8.2 a licence původního projektu. Viz `THIRD-PARTY-NOTICES.md` ve zdrojové distribuci.

### Dělení na slova {#word-segmentation}

Počet slov, hustota klíčových slov a statistiky čitelnosti potřebují rozpoznaná slova. Pro písma s mezerami používá regulární výraz stabilní hranice tokenů tvořených písmeny a číslicemi. Čínština, japonština a thajština potřebují slovníkové dělení; regulární výraz může celý odstavec vidět jako jedno „slovo“. Je-li načteno **ext-intl**, tokenizér předá tyto úseky slovníkovému iterátoru hranic ICU, `IntlBreakIterator::createWordInstance`, který rozdělí 東京タワーは東京のランドマークです na slova. Pokud ICU chybí, je vypnuté nebo nejde inicializovat, Pro přeskočí dotčené kontroly délky obsahu, čitelnosti a klíčových slov se zprávou o instalaci či konfiguraci. Nespolehlivý počet nezmění v neúspěch. Nesouvisející kontroly, včetně délky titulku a porovnávání v písmech s mezerami, stále běží. `seo-pro.checklist.analysis.segmenter = regex` vynutí stejný stav nedostupnosti pro text vyžadující slovníkové dělení.

Blok `analysis` zahrnuje `word_count_status`, tedy `available` nebo `unavailable`, a `segmentation_reason`, tedy `null`, `missing_intl`, `disabled` nebo `initialization_failed`. Nízkoúrovňový tokenizér kvůli kompatibilitě zachovává náhradní tokeny. Než je vyložíte jako slova, zkontrolujte tento stav.

Sken vykreslené stránky vydá upozornění `word_segmentation_unavailable` bez vlivu na skóre místo závěru o příliš krátkém obsahu. Dříve potvrzený problém s krátkým obsahem zůstane otevřený, dokud jej nepůjde znovu ověřit. Tento neúplný sken neobnoví skóre stránky: existující skóre si ponechá původní `scored_at` a první sken nemá skóre, dokud dělení nefunguje. Nainstalujte PHP `ext-intl`, zapněte segmenter `auto` a spusťte nový sken, aby tyto kontroly pokračovaly.

### Které nástroje stránku analyzovaly {#which-engines-analysed-the-page}

Každý kontrolní seznam obsahuje blok `analysis`: převládající písmo textu, tokenizér `intl` / `regex`, stemmer `snowball` / `builtin` / `identity` a metodu čitelnosti `formula` / `heuristic` / `lix`. Je v `toArray()` / `--json`, v řádku patičky modálního okna Filamentu a jako poslední řádek `seo-pro:checklist`:

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

Patička určuje skutečně použitý nástroj, včetně dělení regulárním výrazem bez ext-intl a porovnání beze změny tvaru při vypnutém stemmingu.

### Hustota klíčových slov je doporučující {#keyword-density-is-advisory}

Kontrolní seznam neurčuje ideální hustotu klíčového slova pro pořadí ve vyhledávání. Tato kontrola je **doporučující**: ukazuje počet pro informaci, nikdy neselhává a **nikdy neurčuje celkový stav stránky**. Posuďte, zda opakování působí přirozeně, místo snahy dosáhnout procenta.

### Čitelnost je doporučující {#readability-is-advisory}

Kontrolní seznam odhaduje čitelnost metodou zvolenou podle národního prostředí analýzy. Aktuálně implementované vzorce a náhradní postupy:

| Národní prostředí | Vzorec | Zdroj |
| --- | --- | --- |
| Angličtina (`en`) | Flesch Reading Ease | Flesch 1948 |
| Italština (`it`) | Gulpease Index | Lucisano & Piemontese 1988 |
| Španělština (`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| Francouzština (`fr`) | Kandel-Moles | Kandel & Moles 1958 |
| Němčina (`de`) | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| Portugalština (`pt`, `pt_BR`) | Flesch upravený pro brazilskou portugalštinu | Martins et al. 1996 |
| Nizozemština (`nl`) | Flesch-Douma | Douma 1960 |
| Ruština (`ru`) | Fleschova adaptace podle Oborněvové | Оборнева 2006 |
| Turečtina (`tr`) | Ateşman | Ateşman 1997 |
| Polština (`pl`) | Pisarek, normalizovaný index počtu let vzdělávání | Pisarek 1969 |
| Japonština, čínština, korejština (`ja`, `zh`, `ko`) | **Heuristika bez skóre**, viz níže | — |
| Řečtina, ukrajinština, čeština (`el`, `uk`, `cs`) | LIX, náhradní postup, protože zde není implementovaný vlastní vzorec; pro tyto jazyky není kalibrovaný | Björnsson 1968 |
| Vše ostatní | LIX (Läsbarhetsindex), nekalibrovaný náhradní postup | Björnsson 1968 |

Zobrazené **skóre 0–100, vyšší znamená snazší**, je konvence balíčku. Výsledky rodiny Flesch a Gulpease se omezí na toto rozmezí; Wiener, Pisarek a LIX se na ně převedou. Shodné skóre v různých jazycích **neznamená** stejnou obtížnost čtení. Vzorce koeficientů pocházejí z publikovaných prací; odhady tokenů, vět a slabik v Rankbeamu nebyly ověřeny jako úplný měřicí nástroj. Nepředpovídají porozumění ani pořadí ve vyhledávání.

Od Pro 2.37.1 se v turečtině a ruštině sousední samohlásky počítají samostatně, `saat`: 2; `поэт`: 2. Ostatní odhady slabik mají nadále omezení: skupiny samohlásek přehlédnou některé hiáty a nevyslovované samohlásky. Angličtina má malou mapu výjimek, nikoli slovník výslovnosti. Například španělské `país` a francouzské `monde` mohou být spočítané chybně. Neznámá slova a vlastní jména posuzujte ručně.

#### Statistiky textu a omezení API {#text-statistics-and-api-limits}

Blokové značky HTML a prvky `br` oddělují text; zvýraznění uvnitř řádku zůstává připojené ke slovu. Zalomení zdroje v běžném HTML se sloučí do mezer, zatímco prostý text a `pre` zachovají hranice řádků. Obsah script, style a noscript se vyloučí. Extrakce nevyhodnocuje viditelnost CSS ani vykreslenou stránku. Entity se dekódují jednou. Pro statistiky vzorců jsou slova úseky písmen a číslic, samotná interpunkce nikoli. Spojovníky a apostrofy slova oddělují. Číslice se počítají jako tokeny, ale neodhadují se jim slabiky. Písmena se počítají z původního textu, bez změny délky stemmingem nebo německým sjednocením velikosti `ß` → `ss`.

Odhad vět dělí text u koncové interpunkce `. ! ? 。 ！ ？` a na hranicích bloků či řádků, zahrne neukončený závěrečný fragment a chrání desetinná čísla i krátký seznam běžných zkratek, například `Dr.`, `Prof.`, `e.g.` a podobné anglické tvary. Nadpisy a položky seznamů se proto mohou počítat jako věty. Další zkratky, citace, čísla, smíšená písma a text s malým množstvím interpunkce vyžadují zvláštní pozornost. Zvolené národní prostředí vybere metodu; neověřuje, zda je každá věta v daném jazyce.

`toArray()` přímého kalkulátoru přidává blok `assessment`:

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` rozlišuje `formula`, `lix`, `heuristic` a `unavailable`, případně `unspecified` pro ručně sestavené výsledky bez metadat vzorce. Prázdný vstup nebo vstup tvořený jen interpunkcí je `insufficient` a `isValid()` má hodnotu false. Jeho historické `score: 0` označuje nedostupnost, nikoli skóre obtížnosti. Stávající anglické a italské popisky školních stupňů jsou přibližné; ostatní jazyky a výsledky heuristiky či LIX už takové popisky nedostávají. `calculateFleschKincaid()` kvůli kompatibilitě zachovává veřejný název metody, ale počítá **Flesch Reading Ease**, nikoli stupeň Flesch-Kincaid.

Testy vzorců mají pevné nezávisle spočítané vstupy a očekávanou aritmetiku pro všech deset pojmenovaných vzorců i LIX. Ověřují chování výpočtu, nikoli redakční kvalitu rodilého mluvčího. Čitelnost zůstává oddělená od skóre SEO v Pro.

::: warning Japonština, čínština a korejština: označená heuristika, nikdy číslo
Rankbeam pro tyto jazyky implementuje metodu bez skóre. Kalkulátor vrací **úroveň** podle orientačních pravidel balíčku: průměrná délka věty ve znacích, ja ≤ 40/60/80 a zh ≤ 30/45/60, nebo slovech, ko ≤ 12/18/25. V japonštině také podíl kandži; nad přibližně 45 % se obtížnost v pásmech balíčku posune o stupeň výš. Výsledek nese `heuristic: true` a **skóre null**. Zpráva kontrolního seznamu uvádí „heuristika“ a kontrola zůstává **pro tyto jazyky doporučující bez ohledu na `readability.advisory`**. Orientační pravidlo informuje, nikdy neurčuje celkový stav seznamu. Počty slov pro `ja`/`zh` vyžadují funkční dělení ICU; bez něj se tyto kontroly přeskočí.
:::

Stejně jako hustota klíčových slov je čitelnost **ve výchozím nastavení doporučující**: informuje autora, ale **neurčuje** celkový stav stránky. Stejné oddělení používá Yoast mezi analýzou čitelnosti a SEO. Pod minimálním počtem slov se **přeskočí**; krátké stránky řeší kontrola `content_length`, nikoli čitelnost. Pokud má obtížně čitelná stránka nevyhovět, přepněte ji na rozhodující:

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## Čtení kontrolního seznamu {#reading-the-checklist}

### Bez panelu {#headless}

Pro 2.36 čte vyhodnocená metadata, `getContentForSEO()` a hlavní klíčová slova v požadovaném jazyce obsahu, zatímco popisky kontrolního seznamu zůstávají v jazyce obsluhy. Bez výslovného národního prostředí se respektuje výchozí `seoData()` překladového modelu. Akce Filamentu následuje jazykovou kartu pole nebo přepínač národního prostředí stránky.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Každý `CheckResult` obsahuje `id`, `group`, `label`, `status`, `message`, volitelné `recommendation` a příznak `advisory`.

### Příkaz {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### V editoru (Filament, volitelně) {#in-the-editor-filament-optional}

S nainstalovaným [`rankbeam/laravel-seo-filament`](/cs/guide/filament) se na poli hlavního klíčového slova objeví akce **On-page checklist**, tedy kontrolní seznam stránky; tento název akce zůstává v aktuálním českém katalogu anglicky. Kliknutí otevře modální okno stejných kontrol se stavy vyhovuje, upozornění a nevyhovuje pro uložený obsah záznamu. Balíček Filament nikdy nezávisí na Pro. Akce se připojí přes stejný jednosměrný rozšiřující hook jako návrhy AI, takže instalace bez panelu zůstávají nedotčené.

## Konfigurace {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Vlastní kontrola {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Zaregistrujte ji přidáním třídy do `seo-pro.checklist.rules`. Kontrola **nesmí** znovu použít identifikátor [kódu problému skenu](/cs/pro/scan-issues). Kontrolní seznam má samostatný jmenný prostor bez vlivu na skóre.

## Jak se čte obsah {#how-the-content-is-read}

`SeoPro::checklistFor($model)` analyzuje:

- **Titulek / popis** — *vyhodnocené*, tedy výsledné hodnoty, stejné, které měří počitadla editoru a sken. Kontrolní seznam jim proto nikdy neodporuje.
- **Obsah** — `$model->getContentForSEO()`, accessor `HasSEO` z Core s výchozím `content` / `body` / `text`. Přepište jej na modelu, aby odkazoval na skutečný hlavní text.
- **URL** — `$model->getUrlForSEO()`.
- **Hlavní klíčová slova** — uložené `seo_meta.focus_keywords`.

Jde o čistou analýzu: žádná stránka se nenačítá a nic se nezapisuje.

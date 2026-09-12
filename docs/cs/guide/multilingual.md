---
description: "Jak Rankbeam zpracovává neanglický obsah: délky podle písma, bezpečné zkracování grafémů, velikost písmen podle jazyka, hreflang, inLanguage, regionální vyhledávače, ověření webu, fonty OG a Unicode URL."
---

# Vícejazyčný obsah {#multilingual-content}

[Překlady](/cs/guide/translations) umožňují *balíčku* mluvit vaším jazykem. Tato stránka řeší druhou polovinu: aby balíček **rozuměl jazyku vašeho obsahu**. Limit titulku 60 znaků není vhodný pro japonštinu, oříznutí na hranici slova poškodí thajštinu, `İstanbul` a `istanbul` jsou v turečtině stejné slovo, hreflang `it_IT` je neplatné a korejský web zajímá robot Naveru, nejen Googlu. To vše je otázka správného chování, nikoli překladu. Rozhoduje se v Core, aby všechny části systému souhlasily.

Výchozí hodnoty a přepsání pravidel jsou v `config/seo.php`. Některé schopnosti potřebují závislosti za běhu, včetně dělení slov ICU a nainstalovaných fontů. Přeložený obsah musí dodat vaše aplikace.

## Národní prostředí obsahu a rozhraní {#content-locale-and-interface-locale}

Core 3.17, Filament 1.11 a Pro 2.36 přenášejí zvolené národní prostředí obsahu přes metadata, vypočítávané hooky, URL náhledů, klíčová slova kontrolního seznamu i požadavky AI. Anglický panel může upravovat italštinu a japonštinu bez změny vlastních popisků.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

Tato čtení vyberou řádek metadat daného národního prostředí a spustí hooky modelu jako `getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()` a `getSEOSchema()` v dočasném jazykovém kontextu. Model volajícího a národní prostředí aplikace zůstanou zachované, i když hook vyvolá výjimku. Modely implementující `setLocale()` a `getTranslatableAttributes()` od Spatie dostanou také izolované národní prostředí instance. Hooky stále musí vracet přeložený obsah; Rankbeam běžné databázové atributy automaticky nepřekládá.

Pro přijímá výslovné `locale:` v metodách AI nad modelem i při hromadném doplňování. Bez něj jazyk obsahu určí přepsaná výchozí hodnota `seoData()` překladového modelu, případně národní prostředí aplikace. Akce Filamentu dostanou národní prostředí vlastního pole včetně jednojazyčného editoru a režimu následování. Ve vlastní úloze fronty zvolené národní prostředí serializujte a při spuštění výslovně předejte. Nespoléhejte na aktuální jazyk workeru.

Pro vlastní synchronní čtení obsahu předá `ModelLocale::run($model, $locale, $callback)` callbacku izolovaný model a obnoví národní prostředí aplikace v `finally`. Všechna čtení závislá na jazyku dokončete uvnitř callbacku. Vrácený líně vyhodnocovaný iterátor či uzávěr rozsah neprodlužuje.

## Limity titulků a popisů podle písma {#title-and-description-budgets-per-script}

Rankbeam používá redakční limity 60/160 grafémů pro titulky a popisy latinkou a 30/80 pro CJK. Jde o nastavitelné přibližné hodnoty, nikoli měření pixelů nebo záruku úplného zobrazení ve vyhledávači. Google nepředepisuje pevný počet znaků pro [odkazy s titulky](https://developers.google.com/search/docs/appearance/title-link) ani [meta popisy](https://developers.google.com/search/docs/appearance/snippet). Zobrazený text se může zkrátit podle šířky zařízení.

`Rankbeam\Seo\I18n\LengthPolicy` určí limit podle zpracovávaného textu:

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

Čtou jej upozornění editoru, `SEOWarningEvaluator`, bezplatný `seo:audit`, zkracování vypočítaného popisu, sken Pro i počitadla Filamentu, takže používají stejná pravidla. Upozornění hodnotí vyhodnocené hodnoty včetně přípony titulku; editor může zobrazit i neuložený text. Délky počítají **shluky grafémů**, nikoli bajty nebo kódové body. Hranice shluků se řídí nainstalovanou implementací Unicode. Nejde o počet slabik ani měření pixelů ve výsledcích vyhledávání.

Řádky jsou v `seo.length_policy` podle skupiny písma: `latin`, `cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew` a `devanagari`. Neuvedené skupiny použijí `default`. Řádek může nastavit jen některé klíče a ostatní zdědit:

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Ve výchozím nastavení se liší jen `cjk`. Aktualizovaná instalace se starší zkopírovanou konfigurací dostane vestavěný řádek `cjk` bez dalších úprav.

::: tip Smíšené titulky
Detekce počítá písmena s váhami: znak CJK se počítá dvakrát. „Laravel SEO の完全ガイド“ se proto vyhodnotí jako CJK, zatímco „Laravel SEO for the 東京 developer“ zůstane latinkou. Hodnota bez písmen, například rok nebo cena, převezme písmo národního prostředí stránky.
:::

Konstanty `SEOWarningEvaluator::TITLE_MAX_LENGTH` / `DESCRIPTION_MAX_LENGTH` nadále existují jako výchozí hodnoty pro latinku pro kód, který je čte.

## Zkracování podle písma bez rozdělení grafémů {#grapheme-safe-script-aware-truncation}

Limit vypočítaného popisu, `seo.computed.description_max_length` určený pro latinku, se přepočítá podle pravidel. Popis CJK dostane polovinu a ořízne se přes `Rankbeam\Seo\I18n\Truncator`:

- Text s mezerami mezi slovy zachová původní pravidlo: poslední hranice slova uvnitř limitu, pokud leží alespoň na 60 % limitu, bez výpustky a s odstraněním koncové interpunkce. Pro latinku je výstup bajtově totožný s původním.
- Han, kana a thajština nemají mezery mezi slovy. Oříznutí proto upřednostní poslední značku věty či části souvětí, 。！？、，… uvnitř limitu, pak mezeru, pokud nějaká existuje, jako v korejštině, a nakonec přímé oříznutí.
- Dělení probíhá na shlucích grafémů, takže nikdy nezasáhne kombinující sekvenci. Thajské znaménko samohlásky ani modifikátor emoji se neoddělí od základu.

## Velikost písmen podle národního prostředí {#locale-aware-casing}

`mb_strtolower()` národní prostředí nezohledňuje. `Rankbeam\Seo\I18n\CaseFolder` ano:

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` slouží k zobrazení, zatímco `fold()`, `equals()`, `contains()` a `containsWord()` k porovnávání. Core je používá pro vynechání přípony titulku při již uvedené značce, `seo.title_suffix_skip_when_contains`. Turecká značka tak odpovídá v obou tvarech `i` a značka s diakritikou dostane skutečnou hranici slova. Kontroly klíčových slov Pro staví na stejném pomocníkovi.

Sjednocení velikosti zachovává diakritiku. Nedělá všechny tvary s diakritikou a bez ní rovnocennými. Stemmer jazyka může provést vlastní redukce, ale to je oddělené od `CaseFolder` a porovnání beze změny tvaru.

## hreflang {#hreflang}

Google čte `language[-Script][-REGION]`, tedy dvoupísmenný jazyk ISO 639-1, volitelné písmo ISO 15924 a volitelný dvoupísmenný region ISO 3166-1, plus `x-default`. Číselné regiony jako `es-419` jsou platné BCP47, ale mimo [požadavky Googlu na hreflang](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes). Aplikace Laravel často předají své *národní prostředí*, například `it_IT` či `pt_br`, ale podtržítko zde neplatí. Tři pravidla v `seo.hreflang` se uplatní na seznam `getSEOAlternates()` modelu **před** převodem na značky `<link rel="alternate">`, položky `<xhtml:link>` mapy webu, odkazy `llms.txt` a vstup auditu. Všude platí stejná pravidla; `llms.txt` v odkazech „Také v“ vynechá samotnou stránku a `x-default`:

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`**, standardně zapnuté, upraví oddělovače, velikost písmen a registrované aliasy, `iw_IL` → `he-IL`. Opakované oddělovače zachová, `en__US` → `en--US`, aby je audit mohl označit. Pro zachování dodaných bajtů vypněte.
- **`include_self`** doplní vlastní národní prostředí a kanonickou URL stránky, pokud seznam neobsahuje její URL ani kód. Google vyžaduje, aby každá jazyková verze uvedla sama sebe. Zapněte, pokud hook vrací jen *ostatní* jazyky.
- **`x_default`** určuje jazyk, jehož alternativa se zkopíruje jako `x-default`, pokud jej seznam nemá.

Prázdný seznam zůstává prázdný. Stránka bez překladů nedostane odkaz na sebe ani `x-default`.

Bezplatný audit přidává tři kontroly seznamu po uplatnění pravidel:

| Kód | Závažnost | Význam |
|---|---|---|
| `hreflang_invalid_code` | warning | Kód mimo požadavky Googlu, `en-UK`, `jp`, `english`, `es-419`, `fil`. |
| `hreflang_duplicate_code` | notice | Stejný kód uvedený dvakrát. |
| `hreflang_missing_self` | warning | V seznamu není vlastní URL stránky. |

Vzájemnost, tedy zda druhá stránka odkazuje zpět, potřebuje procházení. To řeší sken Pro: volitelné `check_hreflang_reciprocity` načte každou alternativu přes SsrfGuard a vydá `hreflang_not_reciprocal`, pokud druhá stránka nedeklaruje URL této stránky **s jejím jazykovým kódem**. Platí od Pro 2.38+, viz [problémy skenu](/cs/pro/scan-issues#network-codes). Pomocník je veřejný, pokud jej potřebujete:

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### Tři pravidla pro jazykové kódy {#three-language-code-contracts}

Core **3.18+** odděluje nastavení aplikace od hodnoty poskytované v HTML:

| Vstup | Normalizace aplikace | Jazyk HTML | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | V poskytnutém tvaru neplatný | V poskytnutém tvaru neplatný |
| `de-CH-1901` | Zachován | Platná registrovaná varianta | Nepodporovaná varianta |
| `es-419` | Zachován | Platný číselný region | Nepodporovaný číselný region |
| `zh-Hant-TW` | Zachován | Platný | Platný |
| `fil` | Zachován | Platný registrovaný jazyk | Mimo dvoupísmenné požadavky |
| `iw_IL` | `he-IL` | Podtržítko neplatí; `iw-IL` zůstává platná zastaralá značka | Použijte normalizované `he-IL` |
| `en__US` | `en--US` | Neplatný | Neplatný |
| `x-default` | Zachován | Odmítnut pravidly jazyka obsahu Rankbeamu | Platné označení náhradní verze |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Migrace z Core 3.17 a staršího:** `Hreflang::isValid()` a `parse()` ověřují poskytované kódy striktně. Pokud volající předává národní prostředí Laravelu, nejprve zavolejte `fromLocale()`. Pokud zkoumá atribut HTML `lang`, použijte `LanguageTag::isValidHtml()` bez ořezávání a normalizace. Zastaralé registrované značky jsou pro HTML nadále platné. Normalizace uplatní jen výslovné preferované aliasy IANA a nehádá, že `en-UK` znamená `en-GB`. Chybně sestavené položky se neodfiltrují dříve, než je audit může nahlásit.

Validátor obsahuje údaje z registru IANA z **8. srpna 2026**, zdrojové hashe a reprodukovatelný generátor. Kontroluje strukturu RFC 5646, registrované podznačky, prefixy extlang a duplicitní varianty či rozšíření. Podporuje historicky zachované značky a rozsahy pro soukromé použití. Doporučené prefixy variant nejsou povinným pravidlem platnosti. Kontrolují se jmenné prostory a struktura rozšíření, zatímco význam voleb CLDR a soukromého použití je mimo API. ICU ani stahování za běhu není potřeba. Viz [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) a [definice jazyka HTML](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes).

Pro **2.38+** hlásí nepřítomné či prázdné `lang` jako neznámé nebo chybějící. Chybně sestavené poskytované bajty vytvoří `html_lang_invalid`. Kontroly neshody písma používají skutečnou podznačku písma nebo registrovanou výchozí hodnotu IANA. Obsah soukromých značek či rozšíření a neznámé jazyky neznamenají latinku. Nepodporované skupiny písem zůstanou neposouzené. Nejde o úplný detektor jazyka.

Vzájemnost používá platné kódy odkazů zdrojové stránky na sebe, případně platný jazyk HTML kompatibilní s Googlem, pokud vlastní odkazy chybí. Zpětná URL pod jiným jazykovým kódem neprojde. Pokud zdrojový kód nelze určit, výsledek zůstane `hreflang_target_unverified`. Duplicitní cílové URL se v rámci stávajících limitů alternativ a těla načtou jednou. Ochrany SSRF, odmítnutí přesměrování a zpracování neověřených selhání zůstávají platné.

## `inLanguage` v grafu schématu {#inlanguage-in-the-schema-graph}

Uzel `WebPage` obsahuje `inLanguage` z vyhodnoceného národního prostředí stránky, `it_IT` → `it-IT`. `ArticleSchema::fromModel()` jej převezme z uloženého národního prostředí `seo_meta`. Uzel `WebSite` čte jazyky z konfigurace:

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Regionální vyhledávače {#regional-search-engines}

Katalog robotů pro `seo:robots-txt` obsahuje také klasické webové vyhledávače důležité mimo Google a Bing: Yandex, Baidu, Naver (`Yeti`), Seznam, Sogou, 360, Cốc Cốc a DuckDuckGo. Mají účel `search_engine` a jsou standardně povolené. Účastní se pravidel a přepsání jednotlivých botů, takže obchod neobsluhující Čínu může dvěma robotům omezit využívání přenosové kapacity:

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` a `match()` zůstávají jen pro AI. Log AI botů v Pro i každý počet „N robotů AI“ se nemění. Vyhledávače získejte přes `searchEngines()`, `all(true)` nebo `match($ua, true)`. Viz [řízení robotů AI](/cs/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
Podpora robota a ověřovací značky nezaručuje objevení, indexaci ani pořadí v Baidu.
:::

## Ověření webu {#site-verification}

Tokeny vlastnictví se vykreslí jako jedna meta značka pro každý nastavený vyhledávač na každé stránce. Google přijímá značku kdekoli, Yandex, Baidu a Naver ji hledají na kořenové stránce, která je zahrnutá. Pro prázdný klíč se nic nevypíše:

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

Hodnota může být seznam tokenů; Google vydává jeden každému vlastníkovi služby.

## Obrázky OG ve všech písmech {#og-images-in-every-script}

Dodaný font karet pokrývá latinku, cyrilici a řečtinu. Ostatní písma potřebují font nainstalovaný na stroji, kde běží `seo:og-images`. Další se nepřibalují, protože font CJK má přes 16 MB. Šablony obsahují zásobník náhradních fontů podle písma, `seo.og_image.font_stack`, s rodinou Noto CJK jazyka stránky přesunutou dopředu pro správné národní tvary znaků Han. Příkaz jednou pro každé písmo upozorní, pokud server nemá font pro titulek, který se chystá vykreslit:

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Na Debianu/Ubuntu: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. Podrobnosti jsou v [generovaných obrázcích OG](/cs/guide/og-image#fonts-and-non-latin-scripts).

## `llms.txt` ve více jazycích {#llms-txt-in-several-languages}

Při zapnutém `seo.llms_txt.alternates` skončí položka stránky existující v dalších jazycích textem `Also in: [it](…), [de](…)`. Jde o alternativy po uplatnění pravidel, bez `x-default` a samotné stránky. Standardně vypnuto.

## Unicode URL {#unicode-urls}

Rankbeam nikdy nevytváří slugy ani nepřepisuje vaše URL, takže cesty jako `/città/` či `/検索` projdou všemi artefakty nedotčené. Audit přijímá kanonické URL s hostitelem IDN, `https://münchen.example/`, nebo cestou v Unicode či procentním kódování. `Rankbeam\Seo\I18n\Url::isValid()` nahrazuje `FILTER_VALIDATE_URL` v PHP, které umí jen ASCII. Zachovejte pro každou URL jeden tvar, buď přímé Unicode, nebo procentní kódování, nikoli obojí. Kanonická URL, hreflang a položky mapy webu se pak shodnou bajt po bajtu.

## Které jazyky jsou podporované a co to znamená {#which-languages-are-supported-and-what-that-means}

Balíčky dodávají řetězce a směrování analýzy pro sedmnáct národních prostředí níže. Tabulka popisuje technické pokrytí, nikoli schválení rodilým redaktorem nebo zaručené vykreslení na nenastaveném serveru. Analýza slov japonštiny a čínštiny vyžaduje použitelné ICU; bez něj se dotčené slovní kontroly přeskočí. Nelatinkové vykreslování potřebuje vhodné fonty. Směrování ověřují testy obou repozitářů: `tests/Feature/I18n/SupportedLanguagesTest.php` v Core fixuje seznam jazyků, kódy hreflang a limity, `tests/Feature/OnPage/LanguageSupportMatrixTest.php` v Pro analytické nástroje. Pokud řádek přestane platit, CI selže.

| Jazyk | Národní prostředí | Titulek / popis | Počítání slov | Shoda klíčových slov | Čitelnost |
|---|---|---|---|---|---|
| Angličtina | `en` | 60 / 160 | mezery | Snowball | Flesch Reading Ease |
| Italština | `it` | 60 / 160 | mezery | Snowball | Gulpease |
| Němčina | `de` | 60 / 160 | mezery | Snowball | Wiener Sachtextformel |
| Francouzština | `fr` | 60 / 160 | mezery | Snowball | Kandel-Moles |
| Španělština | `es` | 60 / 160 | mezery | Snowball | Fernández-Huerta |
| Portugalština (Brazílie) | `pt_BR` | 60 / 160 | mezery | Snowball | Martins |
| Nizozemština | `nl` | 60 / 160 | mezery | Snowball | Flesch-Douma |
| Turečtina | `tr` | 60 / 160 | mezery | Snowball | Ateşman |
| Ruština | `ru` | 60 / 160 | mezery | Snowball | Oborneva |
| Polština | `pl` | 60 / 160 | mezery | Snowball | Pisarek |
| Japonština | `ja` | 30 / 80 | slovník ICU | přesná, se sjednocenou velikostí | heuristika, **bez skóre** |
| Čínština (zjednodušená) | `zh_CN` | 30 / 80 | slovník ICU | přesná, se sjednocenou velikostí | heuristika, **bez skóre** |
| Čínština (tradiční) | `zh_TW` | 30 / 80 | slovník ICU | přesná, se sjednocenou velikostí | heuristika, **bez skóre** |
| Korejština | `ko` | 30 / 80 | mezery | přesná, se sjednocenou velikostí | heuristika, **bez skóre** |
| Řečtina | `el` | 60 / 160 | mezery | Snowball | LIX |
| Ukrajinština | `uk` | 60 / 160 | mezery | přesná, se sjednocenou velikostí | LIX |
| Čeština | `cs` | 60 / 160 | mezery | Snowball | LIX |

Tři výslovně přiznaná omezení tabulky:

- **Snowball se dodává od Pro 2.37.** Dvanáct jazyků používá pevné algoritmy 3.1.1 nezávisle na volitelných balíčcích. Ukrajinština a CJK používají porovnání beze změny tvaru; balíček pro ně nevymýšlí pravidla přípon. Toto porovnání může minout ohýbané tvary, zatímco stemming může sloučit různá slova. Viz [ovládání nástrojů a poznámky k migraci](/cs/pro/on-page-checklist#upgrading-from-pro-2-36).
- **„Heuristika bez skóre“ není totéž co „LIX“.** Japonština, čínština a korejština používají v balíčku metodu bez skóre. Kontrolní seznam hlásí *úroveň* z délky vět a podílu kandži se skóre `null` a zůstává doporučující bez ohledu na konfiguraci. Řečtina, ukrajinština a čeština používají LIX, protože zde není vlastní vzorec. LIX nepotřebuje slabiky, ale jeho prahy nejsou kalibrované pro každý jazyk. Všechny vstupy vzorců zahrnují odhady; viz [pravidla statistik](/cs/pro/on-page-checklist#text-statistics-and-api-limits).
- **Překlady balíčků jsou první návrhy**, pokud `TRANSLATING.md` neuvádí kontrolu rodilým mluvčím. Italština je označená jako zkontrolovaná; ostatní hledají recenzenta. Kontrola překladu je nejjednodušší způsob, jak získat uvedení svého přínosu danému jazyku v balíčku. Jde o stav překladů balíčků, nikoli o potvrzení nezávislé rodilé kontroly veřejných edic.

Neuvedená národní prostředí mohou přejít na anglické řetězce, limity podle písma či výchozí limity, porovnání klíčových slov beze změny tvaru a čitelnost LIX nebo heuristiku. Taková náhrada není ověřenou jazykovou podporou. Blok `analysis` kontrolního seznamu určuje písmo, segmenter, stemmer a metodu čitelnosti. Zkoumejte kromě popisků i jejich dostupnost a přeskočená hodnocení.

### Vyhledávače důležité v daném regionu {#reaching-the-search-engines-that-matter-locally}

Vydání jazyka není jen text. Katalog robotů zahrnuje vedle Googlu a Bingu Yandex, Baidu, Yeti od Naveru, Seznam, Sogou, 360 a Cốc Cốc a `seo.verification` vykresluje jejich ověřovací značky: Naver pro korejský web, Seznam pro český, Yandex pro ukrajinský či ruský. Viz [Regionální vyhledávače](#regional-search-engines) a [Ověření webu](#site-verification).

## Co přidávají ostatní balíčky {#what-the-other-packages-add}

- **laravel-seo-filament** čte stejná pravidla délky pro průběžná počitadla i náhled SERP a od verze balíčku 1.9 upravuje [jeden řádek `seo_meta` pro každý jazyk](/cs/guide/filament#several-languages). Každé národní prostředí má kartu s vlastními počitadly, náhledem a ukazateli náhradních hodnot, případně následuje jazykový přepínač překladového pluginu.
- **laravel-seo-pro** je čte pro kontroly `title_length` / `description_length` ve skenu a prompty asistence AI. Od verze 2.34 analyzuje stránku v jejím jazyce. Současná podpora zahrnuje: dělení slov ICU pro čínštinu, japonštinu a thajštinu, Snowball stemming, porovnání klíčových slov podle národního prostředí přes `CaseFolder`, publikované vzorce čitelnosti s odhadovanými vstupy pro deset jazyků, označené heuristiky pro CJK a výslovně uvedené LIX pro řečtinu, ukrajinštinu a češtinu, stop slova pro šestnáct jazyků, kontroly `html lang` a vzájemnosti hreflang, prompty AI pojmenovávající jazyk stránky a report vykreslený Chromem pro písma, která dompdf nezvládá. Viz [kontrolní seznam stránky](/cs/pro/on-page-checklist#keyword-matching), [problémy skenu](/cs/pro/scan-issues), [asistence AI](/cs/pro/ai-assist#output-language) a [reporty](/cs/pro/reports#reports-in-every-script-browsershot-renderer).

---
description: "Hoe Rankbeam niet-Engelse content verwerkt: titel- en beschrijvingslimieten per schrift, afkappen zonder grafemen te splitsen, locale-afhankelijk hoofdlettergebruik, hreflang-normalisatie en -beleid, inLanguage, regionale zoekmachines, siteverificatie, OG-lettertypen en Unicode-URL's."
---

# Meertalige content {#multilingual-content}

[Vertalingen](/nl/guide/translations) laten het *pakket* jouw taal spreken. Deze
pagina gaat over de andere helft: zorgen dat het pakket **de taal van je
content begrijpt**. Een titellimiet van 60 tekens past niet bij Japans, afkappen op een woordgrens
werkt niet voor Thai, `İstanbul` en `istanbul` zijn hetzelfde woord in het Turks, een
hreflang met `it_IT` is ongeldig en voor een Koreaanse site is ook de crawler van Naver van belang,
niet alleen die van Google. Dit gaat niet om vertalen, maar om correcte verwerking. De core
bepaalt die regels, zodat alle onderdelen hetzelfde resultaat gebruiken.

Standaardinstellingen en beleidsoverschrijvingen staan in `config/seo.php`. Sommige functies
vereisen runtime-afhankelijkheden, zoals ICU-woordsegmentatie en geïnstalleerde
lettertypen; vertaalde content moet je applicatie zelf leveren.

## Contentlocale en interfacelocale {#content-locale-and-interface-locale}

Core 3.17, Filament 1.11 en Pro 2.36 geven de gekozen contentlocale door aan
metadata, berekende hooks, voorbeeld-URL's, checklistzoekwoorden en AI-verzoeken.
Een Engels paneel kan Italiaanse en Japanse content bewerken zonder zijn labels te veranderen.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

Deze leesacties selecteren de metadatarij van die locale en voeren modelhooks zoals
`getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()` en `getSEOSchema()` uit binnen
een tijdelijk bereik met die locale. Het model en de applicatielocale van de aanroeper blijven behouden,
ook wanneer een hook een uitzondering gooit. Modellen die Spatie's `setLocale()` en
`getTranslatableAttributes()` implementeren, krijgen ook een geïsoleerde instancelocale.
Je hooks moeten nog steeds vertaalde content retourneren; Rankbeam vertaalt
gewone databaseattributen niet automatisch.

Pro accepteert een expliciete `locale:` bij modelgebaseerde AI-methoden en het in bulk invullen.
Zonder die parameter bepaalt de overschreven standaardwaarde van `seoData()` op een vertaalmodel
de contentlocale, met terugval op de applicatielocale. Filament-acties ontvangen
de locale van hun eigen veld, ook in een editor met één taal en in de volgmodus.
Serialiseer in een eigen wachtrijtaak de gekozen locale en geef die expliciet mee wanneer
de taak wordt uitgevoerd. Vertrouw niet op de huidige locale van de worker.

Voor eigen synchrone contentlezers geeft `ModelLocale::run($model, $locale, $callback)`
een geïsoleerd model aan de callback door en herstelt het de applicatielocale in
`finally`. Rond alle locale-afhankelijke leesacties binnen de callback af; een lazy
iterator of closure retourneren verlengt het bereik niet.

## Titel- en beschrijvingslimieten per schrift {#title-and-description-budgets-per-script}

Rankbeam gebruikt redactionele richtwaarden van 60/160 grafemen voor Latijnse titels/beschrijvingen
en 30/80 voor CJK. Dit zijn configureerbare benaderingen, geen pixelmetingen
of garanties dat een zoekmachine de hele waarde toont. Google schrijft
geen vaste tekenlimiet voor bij [titellinks](https://developers.google.com/search/docs/appearance/title-link)
of [metabeschrijvingen](https://developers.google.com/search/docs/appearance/snippet);
de weergegeven tekst kan worden afgekapt op basis van de breedte van het apparaat.

`Rankbeam\Seo\I18n\LengthPolicy` bepaalt de richtwaarde voor de aangeboden tekst:

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

De editorwaarschuwingen (`SEOWarningEvaluator`), de gratis `seo:audit`,
het afkappen van berekende beschrijvingen, de Pro-scan en de Filament-tellers lezen dit beleid
en gebruiken daardoor dezelfde regels. Waarschuwingen beoordelen de uiteindelijke waarden, inclusief het
titelachtervoegsel, terwijl een editor ook niet-opgeslagen tekst kan tonen. Lengtes worden geteld in
**grafeemclusters**, niet in bytes of codepunten. Clustergrenzen
volgen de geïnstalleerde Unicode-implementatie; ze tellen geen lettergrepen
en meten geen pixels in zoekresultaten.

De regels staan in `seo.length_policy`, met schriftgroepen als sleutel (`latin`,
`cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew`, `devanagari`) en
`default` voor elke niet-vermelde groep. Een regel kan alleen bepaalde sleutels instellen en
de rest overnemen:

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Standaard wijkt alleen `cjk` af. Een bijgewerkte installatie met een oudere gepubliceerde configuratie
krijgt de ingebouwde `cjk`-regel zonder dat je iets hoeft aan te passen.

::: tip Titels met meerdere schriften
Detectie gebruikt een gewogen lettertelling: een CJK-teken telt dubbel. Daardoor
valt "Laravel SEO の完全ガイド" onder CJK, terwijl "Laravel SEO for the 東京
developer" Latijns blijft. Een waarde zonder letters (een jaartal, een prijs) neemt
het schrift van de paginalocale over.
:::

De constanten `SEOWarningEvaluator::TITLE_MAX_LENGTH` / `DESCRIPTION_MAX_LENGTH`
blijven bestaan als Latijnse standaardwaarden voor code die ze uitleest.

## Schriftbewust afkappen zonder grafemen te splitsen {#grapheme-safe-script-aware-truncation}

Een berekende beschrijving (`seo.computed.description_max_length`, een Latijnse richtwaarde)
wordt door het beleid geschaald — een CJK-beschrijving krijgt de helft — en afgekapt door
`Rankbeam\Seo\I18n\Truncator`:

- tekst met spaties tussen woorden behoudt de bestaande regel: de laatste woordgrens binnen
  de limiet (als die op minstens 60 % van de limiet ligt), zonder weglatingsteken en met verwijderde
  afsluitende leestekens — voor Latijnse tekst byte voor byte gelijk aan voorheen;
- Han, Kana en Thai gebruiken geen spaties tussen woorden. Afkappen gebeurt daarom bij voorkeur bij het laatste zins-
  of zinsdeelteken (。！？、，…) binnen de limiet, daarna bij een spatie als de tekst die bevat
  (Koreaans), en anders direct op de limiet;
- afkappen gebeurt op grafeemclusters, zodat een snede nooit midden in een
  gecombineerde tekenreeks valt — een Thais klinkerteken of emoji-modifier wordt nooit
  van zijn basisteken gescheiden.

## Locale-afhankelijk hoofdlettergebruik {#locale-aware-casing}

`mb_strtolower()` houdt geen rekening met de locale. `Rankbeam\Seo\I18n\CaseFolder` wel:

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` is de weergavevorm; `fold()`, `equals()`, `contains()` en
`containsWord()` zijn voor vergelijkingen. De core gebruikt dit om het
titelachtervoegsel over te slaan als de merknaam al aanwezig is (`seo.title_suffix_skip_when_contains`). Een Turkse merknaam wordt zo herkend
in beide `i`-vormen en een merknaam met accenten krijgt een echte woordgrens; de
zoekwoordcontroles van Pro bouwen op dezelfde helper voort.

Case folding behoudt accenten. Het maakt spellingen met en zonder accent
niet automatisch gelijkwaardig. Een taalspecifieke stemmer kan eigen vereenvoudigingen toepassen;
dat staat los van `CaseFolder` en identiteitsmatching.

## hreflang {#hreflang}

Google leest `language[-Script][-REGION]` — een ISO 639-1-taalcode van twee letters,
optioneel een ISO 15924-schriftcode en optioneel een ISO 3166-1 alpha-2-regio — plus
`x-default`. Numerieke regio's zoals `es-419` zijn geldige BCP47-codes, maar vallen buiten
[Googles hreflang-regels](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes).
Laravel-apps geven vaak hun *locale* door (`it_IT`, `pt_br`), terwijl een
underscore daar niet geldig is. Drie beleidsregels in `seo.hreflang` worden op de
`getSEOAlternates()`-lijst van een model toegepast **voordat** die wordt omgezet in `<link rel="alternate">`-
tags, `<xhtml:link>`-sitemapvermeldingen, `llms.txt`-links en auditinvoer. Alles gebruikt
hetzelfde beleid; `llms.txt` laat de pagina zelf en `x-default` weg uit de
"Ook in"-links:

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`** (standaard aan) past scheidingstekens, hoofdlettergebruik en geregistreerde aliassen aan
  (`iw_IL` → `he-IL`). Herhaalde scheidingstekens blijven behouden (`en__US` → `en--US`),
  zodat de audit ze kan signaleren. Schakel dit uit om de aangeleverde bytes te behouden.
- **`include_self`** voegt de eigen locale en canonieke URL van de pagina toe als noch de
  URL, noch de code in de lijst staat. Google vereist dat elke taalversie zichzelf vermeldt;
  schakel dit in wanneer je hook alleen de *andere* talen retourneert.
- **`x_default`** benoemt de taal waarvan het alternatief wordt gedupliceerd als
  `x-default` wanneer de lijst dat nog niet bevat.

Een lege lijst blijft leeg — een pagina zonder vertalingen krijgt noch een
zelfverwijzing, noch een `x-default`.

De gratis audit voegt drie controles toe op de lijst nadat het beleid is toegepast:

| Code | Ernst | Betekenis |
|---|---|---|
| `hreflang_invalid_code` | waarschuwing | Een code buiten Googles regels (`en-UK`, `jp`, `english`, `es-419`, `fil`). |
| `hreflang_duplicate_code` | melding | Dezelfde code staat twee keer in de lijst. |
| `hreflang_missing_self` | waarschuwing | De eigen URL van de pagina staat niet in de lijst. |

Wederkerigheid (verwijst de andere pagina terug?) vereist een crawl; dat is de taak van de
Pro-scan. De optionele `check_hreflang_reciprocity` haalt elk alternatief op
via de SsrfGuard en rapporteert `hreflang_not_reciprocal` wanneer de andere pagina
de URL van deze pagina niet vermeldt **met de bijbehorende taalcode** (Pro 2.38+, zie
[scanproblemen](/nl/pro/scan-issues#network-codes)). De helper is openbaar beschikbaar als je
hem nodig hebt:

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### Drie verschillende regels voor taalcodes {#three-language-code-contracts}

Core **3.18+** onderscheidt een applicatie-instelling van de waarde die in HTML wordt aangeboden:

| Invoer | Normalisatie in de applicatie | HTML-taal | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | Ongeldig zoals aangeboden | Ongeldig zoals aangeboden |
| `de-CH-1901` | Behouden | Geldige geregistreerde variant | Niet-ondersteunde variant |
| `es-419` | Behouden | Geldige numerieke regio | Niet-ondersteunde numerieke regio |
| `zh-Hant-TW` | Behouden | Geldig | Geldig |
| `fil` | Behouden | Geldige geregistreerde taal | Buiten de regels voor codes van twee letters |
| `iw_IL` | `he-IL` | Underscore is ongeldig; `iw-IL` blijft een geldige verouderde tag | Gebruik de genormaliseerde `he-IL` |
| `en__US` | `en--US` | Ongeldig | Ongeldig |
| `x-default` | Behouden | Afgewezen door Rankbeams beleid voor contenttalen | Geldige terugvalmarkering |

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

**Migreren vanuit core 3.17 en ouder:** `Hreflang::isValid()` en `parse()`
valideren aangeboden codes strikt. Als een aanroeper een Laravel-locale meegeeft, roep dan eerst
`fromLocale()` aan. Als de code een HTML-attribuut `lang` controleert, gebruik dan
`LanguageTag::isValidHtml()` zonder het te trimmen of te normaliseren. Verouderde
geregistreerde tags blijven geldig voor HTML; normalisatie past alleen expliciete
IANA-voorkeursaliassen toe en neemt niet aan dat `en-UK` `en-GB` betekent.
Ongeldige vermeldingen worden niet weggefilterd voordat de audit ze kan rapporteren.

De validator bevat gegevens uit het IANA-register van **2026-08-08**, met
bronhashes en een reproduceerbare generator. Hij controleert de RFC 5646-structuur,
geregistreerde subtags, extlang-prefixen en dubbele varianten/extensies. Hij
ondersteunt grandfathered tags en bereiken voor privégebruik. Aanbevelingen voor variantprefixen
zijn geen verplichte geldigheidsregels; extensienamespaces en
-structuur worden gecontroleerd, terwijl de betekenis van CLDR-opties en privégebruik
buiten de API blijft. ICU of een download tijdens runtime is niet vereist. Zie
[RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) en de
[HTML-taaldefinitie](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes).

Pro **2.38+** rapporteert een ontbrekende of lege `lang` als onbekend/ontbrekend, terwijl ongeldige
aangeboden bytes `html_lang_invalid` opleveren. Controles op een afwijkend schrift gebruiken een echte
schriftsubtag of de door IANA geregistreerde standaard; privé-/extensiegegevens en
onbekende talen impliceren geen Latijns schrift. Niet-ondersteunde schriftgroepen worden
niet beoordeeld. Deze controles vormen geen volledige taaldetector.

Wederkerigheid gebruikt de geldige zelfverwijzingscodes van de bronpagina, of haar geldige
Google-compatibele HTML-taal als zelfverwijzingscodes ontbreken. Een terugverwijzende
URL onder een andere taalcode slaagt niet voor de controle. Als de brontaalcode niet kan worden
vastgesteld, blijft het resultaat `hreflang_target_unverified`. Dubbele doel-URL's worden één keer opgehaald binnen de bestaande limieten voor alternatieven en inhoud; SSRF-beveiliging,
het weigeren van redirects en de afhandeling van niet-geverifieerde fouten blijven gelden.

## `inLanguage` in de schemagraaf {#inlanguage-in-the-schema-graph}

De `WebPage`-node krijgt `inLanguage` uit de uiteindelijke locale van de pagina
(`it_IT` → `it-IT`), en `ArticleSchema::fromModel()` haalt die uit de opgeslagen
`seo_meta`-locale. De `WebSite`-node haalt zijn talen uit de configuratie:

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Regionale zoekmachines {#regional-search-engines}

De crawlercatalogus achter `seo:robots-txt` bevat nu ook de klassieke webzoekcrawlers
die buiten Google en Bing van belang zijn — Yandex, Baidu, Naver
(`Yeti`), Seznam, Sogou, 360, Cốc Cốc en DuckDuckGo — met het doel
`search_engine` en standaard toegestaan. Ze vallen onder het beleid en de
overschrijvingen per bot. Een winkel die China niet bedient, kan zo voorkomen dat twee crawlers
bandbreedte gebruiken:

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` en `match()` blijven uitsluitend voor AI (het Pro-logboek voor AI-bots en
alle tellingen van "N AI-crawlers" blijven ongewijzigd); vraag de zoekmachines op met
`searchEngines()`, `all(true)` of `match($ua, true)`. Zie
[AI-crawlers beheren](/nl/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
Ondersteuning voor de crawler en verificatietag garandeert geen ontdekking,
indexering of zoekposities in Baidu.
:::

## Siteverificatie {#site-verification}

Eigendomstokens worden op elke pagina als één metatag per ingestelde zoekmachine gerenderd
(Google accepteert de tag op elke pagina; Yandex, Baidu en Naver zoeken op de hoofdpagina,
die ook wordt gedekt). Voor een leeggelaten sleutel wordt niets gegenereerd:

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

Een waarde mag een lijst met tokens zijn (Google geeft er één uit per property-eigenaar).

## OG-afbeeldingen in elk schrift {#og-images-in-every-script}

Het meegeleverde lettertype voor kaarten dekt Latijns, Cyrillisch en Grieks. Elk ander schrift
vereist een lettertype dat is geïnstalleerd op de machine die `seo:og-images` uitvoert — er wordt verder
niets meegeleverd, omdat een CJK-lettertype 16 MB of groter is. De templates bevatten nu
een terugvalreeks van lettertypen per schrift (`seo.og_image.font_stack`, waarbij de
Noto CJK-familie van de paginataal vooraan staat, zodat Han-tekens de juiste regionale
glyphvormen krijgen). De opdracht waarschuwt één keer per schrift als de host geen lettertype
heeft voor een titel die hij gaat renderen:

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Op Debian/Ubuntu: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`.
Details staan bij [Gegenereerde OG-afbeeldingen](/nl/guide/og-image#fonts-and-non-latin-scripts).

## `llms.txt` in meerdere talen {#llms-txt-in-several-languages}

Als `seo.llms_txt.alternates` aanstaat, eindigt het lijstitem van een pagina die in andere talen bestaat
met `Also in: [it](…), [de](…)` — de alternatieven waarop het beleid is toegepast,
zonder `x-default` en de pagina zelf. Standaard uit.

## Unicode-URL's {#unicode-urls}

Rankbeam maakt nooit slugs van je URL's en herschrijft ze niet. Een pad zoals `/città/` of
`/検索` blijft daarom in elk uitvoerbestand ongewijzigd. Canonieke URL's met een IDN-host
(`https://münchen.example/`) of een Unicode- of procentgecodeerd pad worden door de audit
geaccepteerd (`Rankbeam\Seo\I18n\Url::isValid()` vervangt PHP's uitsluitend op ASCII gerichte
`FILTER_VALIDATE_URL`). Gebruik één vorm per URL — ruwe Unicode *of*
procentcodering, niet beide — zodat vermeldingen in canonical, hreflang en sitemap
byte voor byte gelijk zijn.

## Welke talen worden ondersteund en wat dat betekent {#which-languages-are-supported-and-what-that-means}

De pakketten leveren teksten en analyserouting voor de zeventien onderstaande locales.
Deze tabel beschrijft technische dekking, geen redactionele goedkeuring door moedertaalsprekers
of gegarandeerde rendering op een niet-geconfigureerde host. Woordanalyse voor Japans en Chinees
vereist een bruikbare ICU-installatie; de betrokken woordgebaseerde controles worden overgeslagen als die ontbreekt.
Niet-Latijnse rendering vereist geschikte lettertypen. De routing wordt door tests
in beide repositories bewaakt: `tests/Feature/I18n/SupportedLanguagesTest.php`
in de core legt de localelijst, hreflang-codes en richtwaarden vast, en
`tests/Feature/OnPage/LanguageSupportMatrixTest.php` in Pro legt de analyse-engines vast. Als een rij niet meer klopt, faalt CI.

| Taal | Locale | Titel / beschrijving | Woorden tellen | Zoekwoorden vergelijken | Leesbaarheid |
|---|---|---|---|---|---|
| Engels | `en` | 60 / 160 | spaties | Snowball | Flesch Reading Ease |
| Italiaans | `it` | 60 / 160 | spaties | Snowball | Gulpease |
| Duits | `de` | 60 / 160 | spaties | Snowball | Wiener Sachtextformel |
| Frans | `fr` | 60 / 160 | spaties | Snowball | Kandel-Moles |
| Spaans | `es` | 60 / 160 | spaties | Snowball | Fernández-Huerta |
| Portugees (Brazilië) | `pt_BR` | 60 / 160 | spaties | Snowball | Martins |
| Nederlands | `nl` | 60 / 160 | spaties | Snowball | Flesch-Douma |
| Turks | `tr` | 60 / 160 | spaties | Snowball | Ateşman |
| Russisch | `ru` | 60 / 160 | spaties | Snowball | Oborneva |
| Pools | `pl` | 60 / 160 | spaties | Snowball | Pisarek |
| Japans | `ja` | 30 / 80 | ICU-woordenboek | exact, na case folding | heuristisch, **geen score** |
| Chinees (Vereenvoudigd) | `zh_CN` | 30 / 80 | ICU-woordenboek | exact, na case folding | heuristisch, **geen score** |
| Chinees (Traditioneel) | `zh_TW` | 30 / 80 | ICU-woordenboek | exact, na case folding | heuristisch, **geen score** |
| Koreaans | `ko` | 30 / 80 | spaties | exact, na case folding | heuristisch, **geen score** |
| Grieks | `el` | 60 / 160 | spaties | Snowball | LIX |
| Oekraïens | `uk` | 60 / 160 | spaties | exact, na case folding | LIX |
| Tsjechisch | `cs` | 60 / 160 | spaties | Snowball | LIX |

Drie zaken waarover deze tabel bewust duidelijk is:

- **Snowball wordt meegeleverd vanaf Pro 2.37.** Twaalf talen gebruiken de vastgelegde
  3.1.1-algoritmen, onafhankelijk van optionele pakketten. Oekraïens en CJK gebruiken identiteitsmatching;
  het pakket verzint daarvoor geen achtervoegselregels. Identiteitsmatching
  kan verbogen vormen missen, terwijl stemming verschillende woorden kan samenvoegen. Zie de
  [engine-instellingen en migratienotities](/nl/pro/on-page-checklist#upgrading-from-pro-2-36).
- **"heuristisch, geen score" en "LIX" zijn niet hetzelfde.** Japans, Chinees
  en Koreaans gebruiken in dit pakket een methode zonder score: de checklist rapporteert een
  *niveau* op basis van zinslengte en het aandeel kanji, met een `null`-score, en blijft
  adviserend ongeacht je configuratie. Grieks, Oekraïens en Tsjechisch gebruiken LIX omdat
  hier geen specifieke formule is geïmplementeerd. LIX heeft geen lettergrepen nodig, maar de
  drempels zijn niet voor elke taal gekalibreerd. Alle formule-invoer bevat
  schattingen; zie de [afspraken voor statistieken](/nl/pro/on-page-checklist#text-statistics-and-api-limits).
- **Vertalingen zijn eerste versies**, tenzij `TRANSLATING.md` vermeldt dat een moedertaalspreker
  ze heeft beoordeeld. Italiaans is beoordeeld; de overige talen zoeken een beoordelaar. Een vertaling
  beoordelen is de eenvoudigste manier om voor je taal een vermelding in het pakket te krijgen.

Niet-vermelde locales kunnen terugvallen op Engelse teksten, richtwaarden voor het schrift of standaardrichtwaarden,
identiteitsmatching voor zoekwoorden en LIX of heuristische leesbaarheid. Die terugval is
geen gevalideerde taalondersteuning. Het `analysis`-blok van de checklist vermeldt
het schrift, de segmenter, de stemmer en de leesbaarheidsmethode; controleer naast de labels
ook de beschikbaarheid en de overgeslagen beoordelingen.

### De lokaal relevante zoekmachines bereiken {#reaching-the-search-engines-that-matter-locally}

Een taal aanbieden gaat niet alleen over tekst. De crawlercatalogus bevat
Yandex, Baidu, Navers Yeti, Seznam, Sogou, 360 en Cốc Cốc naast Google en
Bing, en `seo.verification` rendert hun siteverificatietags — Naver voor een
Koreaanse site, Seznam voor een Tsjechische, Yandex voor een Oekraïense of Russische. Zie
[Regionale zoekmachines](#regional-search-engines) en
[Siteverificatie](#site-verification).

## Wat de andere pakketten toevoegen {#what-the-other-packages-add}

- **laravel-seo-filament** leest hetzelfde lengtebeleid voor de live tellers
  en het SERP-voorbeeld en bewerkt (1.9) [één `seo_meta`-rij per taal](/nl/guide/filament#several-languages)
  — één tabblad per locale met eigen tellers, voorbeeld en terugvalindicatoren, of het volgt de taalwisselaar van een vertaalplugin.
- **laravel-seo-pro** leest het beleid voor de `title_length`- /
  `description_length`-controles van de scan en de AI-prompts, en analyseert (2.34)
  de pagina in haar eigen taal: ICU-woordsegmentatie voor Chinees, Japans
  en Thai, Snowball-stemming, locale-afhankelijke zoekwoordvergelijking via deze
  `CaseFolder`, gepubliceerde leesbaarheidsformules met geschatte invoer voor tien talen,
  als zodanig benoemde heuristieken voor CJK en LIX voor Grieks, Oekraïens
  en Tsjechisch, stopwoorden voor zestien talen, scancontroles voor `html lang`
  en hreflang-wederkerigheid, AI-prompts die de taal van de pagina
  benoemen en een met Chrome gerenderd rapport voor schriften die dompdf niet kan tekenen. Zie
  de [on-page checklist](/nl/pro/on-page-checklist#keyword-matching),
  [scanproblemen](/nl/pro/scan-issues), [AI-ondersteuning](/nl/pro/ai-assist#output-language)
  en [rapporten](/nl/pro/reports#reports-in-every-script-browsershot-renderer).

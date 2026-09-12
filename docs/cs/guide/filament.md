---
description: "Přidejte úplnou sekci SEO do libovolného formuláře zdroje Filamentu dvěma řádky s bezplatným laravel-seo-filament. Podporuje Filament 4.x a 5.x s traitem HasSEO."
---

# Pole administrace Filament {#filament-admin-fields}

Bezplatný balíček [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) přidává úplnou sekci SEO do libovolného formuláře zdroje Filamentu: **dva řádky na zdroj**. Podporuje Filament **4.x a 5.x**, tedy Livewire 3 a 4. Úpravy metadat jsou zdarma; Pro přidává skeny a skóre zobrazené v příkladu níže.

## Předpoklady {#prerequisites}

Použijte existující panel Filament 4 nebo 5 a model s traitem `HasSEO` z Core. Před přidáním editoru dokončete [Rychlý začátek s Core](/cs/guide/quickstart) včetně migrací a vykreslování.

## Instalace {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

Model zdroje musí používat trait `HasSEO` z Core.

## Přidání sekce ke zdroji {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## Kontrola uloženého výsledku {#check-the-saved-result}

Otevřete existující záznam, zadejte popis SEO, uložte jej a znovu načtěte formulář. Hodnota má zůstat uložená, zobrazit se v náhledu a její zdroj má být **Ručně**; anglický ukázkový panel uvádí **Manual**. Zkontrolujte `<head>` vykreslené stránky, zda stejný popis dostávají návštěvníci.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="Pole SEO v demu Merchant: titulek, popis, kanonická adresa, obrázek pro sdílení, náhled vyhledávání a zdroje vyhodnocených hodnot." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Příklad z dema Merchant. Pole používají motiv vašeho panelu; dostupné ovládací prvky a limity znaků závisejí na nainstalované verzi a konfiguraci.*

Sekce zahrnuje:

- **Titulek a popis** s živými počítadly znaků. Doporučený limit vychází z [pravidel délky](/cs/guide/multilingual#title-and-description-budgets-per-script) Core pro právě zadávané písmo: 60/160 pro latinku a přibližně 30/80 pro CJK, počítáno v grafémech.
- **Hlavní klíčová slova** jako vstup se štítky. Zadáváte běžná klíčová slova, která se ukládají do struktury `[{keyword, is_primary}]` Core; první je hlavní. `getPrimaryKeyword()` i `SEOData` je tak čtou beze změny. Zapněte `seo.keywords.enabled`, aby příkaz [`seo:audit`](/cs/guide/audit) i sken Pro označovaly stránky, kterým klíčové slovo stále chybí. Standardně vypnuto, jeden společný přepínač; viz [Konfigurace](/cs/reference/configuration#focus-keywords).
- **Kanonickou URL**; prázdná znamená automatickou hodnotu s odstraněným řetězcem dotazu.
- Výběr **robots**; prázdný znamená výchozí nastavení webu.
- Nahrání **obrázku pro sdílení** (og:image / twitter:image), uloženého na výchozím disku Filamentu pod `seo/`.
- **Náhled úryvku ve vyhledávání**, který při psaní živě odráží řetězec náhradních hodnot resolveru.
- **Ukazatele zdroje**: která vrstva resolveru vytvořila výslednou hodnotu každého pole. *Ručně*, *Náhrada z obsahu*, *Výchozí pro typ modelu*, *Globální výchozí*, *Konfigurace webu* nebo *Odvozeno z URL*.

## Omezení polí {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

Přijímá libovolnou podmnožinu `title`, `description`, `focus_keywords`, `canonical`, `robots` a `og_image`.

Bez traitu vrací stejnou sekci přímo `SEOFields::make(?array $only)`.

## Jak se hodnoty ukládají {#how-values-persist}

Sekce se váže na skupinu stavu `seo_meta` a ukládá přes vztah `seoMeta()` v Core pomocí aktualizace nebo vytvoření. Do vlastních tabulek nemusíte přidávat sloupce a hodnoty se ihned stanou šestou, výslovnou vrstvou [resolveru](/cs/concepts/resolver-precedence).

## Více jazyků {#several-languages}

Core uchovává [jeden řádek `seo_meta` na dvojici model/jazyk](/cs/guide/multilingual). Předejte jazyky, ve kterých je stránka publikovaná, a sekce vykreslí **jednu kartu na jazyk**; od verze 1.9 balíčku editoru Filament:

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

Nebo je nastavte jednou pro všechny zdroje v konfiguraci balíčku:

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

Každá karta upravuje vlastní řádek a má vlastní:

- **počítadla** podle [pravidel délky](/cs/guide/multilingual#title-and-description-budgets-per-script) pro písmo daného jazyka. Prázdný japonský titulek tak na stejné stránce ukazuje `0 / 30`, zatímco anglická karta `0 / 60`;
- **náhled** výsledku vyhledávání nebo sociální karty z vyhodnocených hodnot daného jazyka;
- **ukazatele náhradních hodnot** popisující řádek tohoto jazyka;
- **odznak** s počtem vyplněných polí v dané verzi, aby byly prázdné překlady patrné.

Je-li načteno `ext-intl`, karta nese název jazyka v jazyce panelu (`Italiano` / `Italian`), jinak jeho kód. Všechny karty se validují a ukládají společně. Jazyk, pro který nebylo nic zadáno, nikdy nedostane prázdný zástupný řádek.

::: details Vlastní vazby stavu formuláře
Při více jazycích je cesta stavu `seo_meta.{locale}.title`, při jednom zůstává `seo_meta.title`. Ve vlastních akcích formuláře používejte odpovídající cestu.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Anglická, italská a japonská karta v demu Merchant. Japonský titulek a popis mají limity 30 a 80 znaků; popis není nastavený." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*Demo Merchant, 9. září 2026, s `locales: ['en', 'it', 'ja']`. Prázdná japonská karta používá vlastní počítadla. Anglický titulek zde pochází z náhradní hodnoty obsahu modelu dema: přidání jazykové karty obsah nepřeloží. Skóre Pro nad poli je poslední výsledek skenu záznamu, nikoli samostatné skóre každé jazykové karty.*

### S překladovým pluginem {#with-a-translatable-plugin}

S `lara-zeus/spatie-translatable` **1.x na Filamentu 4** nebo **2.x na Filamentu 5** použijte adaptéry stránek Edit a Create od Rankbeamu. Nahraďte pouze importy traitů stránek. Ponechte traity zdrojů a seznamů pluginu, plugin panelu i akci `LocaleSwitcher`:

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

Každá stránka nadále uvádí uvnitř třídy `use Translatable;`. Plugin zůstává volitelnou závislostí aplikace. Používejte jeho nejnovější opravenou verzi. Místní integrační fixture pokrývá plugin 1.0.4 s Filamentem 4.13.1 a plugin 2.0.1 s Filamentem 5.8.1.

Přepínání zachovává v editoru neuložený obsah nadřazeného záznamu, metadata SEO i koncepty strukturovaných dat. Uložení validuje všechny navštívené jazyky a uloží je společně v databázové transakci. Chyba validace otevře jazyk vyžadující pozornost. Nahrané soubory se ukládají při akci Uložit; opuštění nebo opětovné načtení stránky neuložené koncepty zahodí. Uložení konceptu za vás nepřeloží chybějící obsah.

Adaptéry zachovávají běžné hooky před a po akcích i mutátory dat formuláře. Pokud stránka přepisuje `handleRecordCreation()`, `handleRecordUpdate()`, `callHook()` nebo transakční metody, zapojte do těchto úprav chování adaptéru a otestujte ukládání. Databázové transakce nevracejí zápisy do souborového systému; aplikace má zachovat běžný úklid osiřelých souborů.

U vlastních živých textových polí v Livewire 3 dávejte přednost `->live()` nebo `->live(onBlur: true)` před výslovným debounce. Ten odkládá místní stav modelu a při rychlé změně jazyka může ztratit poslední stisky kláves. Pole titulku a popisu Rankbeamu používají výchozí debounce požadavků.

Samotné původní traity stránek pluginu při přepnutí znovu naplňují formuláře. Rankbeam brání jejich nechtěným zápisům metadat, ale tyto traity nezachovávají koncepty SEO. Převeďte stránky Edit/Create na adaptéry. Výslovné karty `locales:` zůstávají sdíleným editorem a mají přednost před přepínačem jazyka stránky.

Bez výslovného seznamu jazyků nebo jazyka stránky sekce upravuje jazyk aplikace.

## Strukturovaná data (schema.org) {#structured-data-schema-org}

Volitelná sekce **Strukturovaná data** umožňuje editorům připojovat schéma JSON-LD pro rozšířené výsledky bez práce s kódem. Přidejte ji vedle sekce SEO:

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

Nebo použijte přímo `SEOSchemaFields::make()` bez traitu.

Zapisuje do sloupce `seo_meta.schema_jsonld` v Core, tedy stejné hodnoty, kterou vypisuje [renderer schématu](/cs/guide/schema). Jde o **čistou vazbu rozhraní**: každý dokument vytváří generátor schématu v Core a před uložením validuje `SchemaValidator` z Core. Sekce nepřidává vlastní logiku schématu.

Sekce nabízí:

- **Automatickou drobečkovou navigaci**: jediný přepínač, uvedený jako první možnost bez konfigurace. Odvozuje `BreadcrumbList` z řetězce rodičů záznamu pomocí `BreadcrumbSchema::fromModelAncestors()`. Není co vyplňovat, sleduje předky modelu.
- **Bloky schématu** jako opakovatelný seznam. Každý blok je buď **FAQ**, tedy dvojice otázka/odpověď → `FAQPage`, nebo **Produkt** s názvem, popisem, obrázkem, značkou, SKU, cenou, měnou a dostupností → `Product`. Vytvářejí je generátory `FAQSchema` / `ProductSchema` z Core.

### Validace {#validation}

Blok, který by vytvořil neplatné JSON-LD, je **při ukládání odmítnut** se zprávou validátoru Core. Například položka FAQ bez odpovědi nebo Produkt bez obrázku či nabídky. Tyto údaje vyžaduje tento generátor; nejde o úplný popis požadavků Googlu na všechny vyhledávací funkce typu Product. Prázdné bloky se jednoduše ignorují.

### Co se ukládá {#what-it-stores}

`schema_jsonld` obsahuje sestavené dokumenty: jeden objekt při jediném dokumentu a pole JSON při více dokumentech, nejprve drobečkovou navigaci a poté vaše bloky. Obojí je platné JSON-LD a vykreslí se beze změny přes `@seo` / `renderSchema()`.

### Schéma, které nespravuje {#schema-it-doesn-t-manage}

Schéma vytvořené v kódu, které editor neumí reprezentovat — ruční `@graph`, neobvyklé `@type` nebo Produkt s poli, která formulář nenabízí, například recenze, hodnocení či GTIN/MPN — se **zachová doslova**. Otevření a uložení formuláře je nikdy nepřepíše.

## Řešení potíží {#troubleshooting}

- **Uložené pole na stránce chybí:** ověřte, že šablona vykresluje `@seo($model)` pro stejný záznam a jazyk.
- **Pole stále používá náhradní hodnotu:** zkontrolujte, zda má v aktivním jazyce uloženou výslovnou hodnotu. Ukazatele zdroje identifikují vyhodnocenou vrstvu.
- **Chybí jazyková karta:** zkontrolujte výslovný argument `locales:`, konfiguraci balíčku a případný přepínač překladů stránky. Jejich priorita je popsaná výše.

::: details Testování vlastního panelu v Testbenchi

Pokud spouštíte Filament uvnitř orchestra/testbench, zaregistrujte `SupportServiceProvider` Filamentu **před** `LivewireServiceProvider`. Filament znovu váže `DataStore` Livewire a nesprávné pořadí způsobí selhání všech testů Livewire s `ViewErrorBag::put(): ... null given`. Skutečných aplikací se to netýká, protože objevování balíčků řadí poskytovatele správně.
:::

---
description: "Závazný kontrolní seznam pro hlavičku každé frontendové technologie vykreslující data SEO Rankbeamu. Zdroj pravdy pro testy rendererů Core a referenční aplikace."
---

# Kontrakt vykreslování {#the-rendering-contract}

Toto je **jediný závazný kontrolní seznam**, který musí splnit `<head>` každé frontendové technologie při vykreslování dat SEO Rankbeamu. Je zdrojem pravdy pro:

- jednotkové testy struktury výstupu rendererů v Core (`tests/Unit/Services/RenderingContractTest.php`), rychlou část bez frameworku pokrytou CI balíčku;
- referenční aplikace jednotlivých technologií v `rankbeam-examples`: Blade, Inertia s Vue, Reactem či Svelte a Livewire. Jejich testy v prohlížeči a SSR ověřují stejné podmínky ve skutečném DOM;
- průvodce frameworky (Blade, Inertia a JSON, Livewire), které nikdy nesmějí dokumentovat postup porušující tento kontrakt.

Pokud technologie nedokáže splnit některý bod, jde o **vadu nebo dokumentované omezení**, nikoli důvod oslabit kontrakt. Datová vrstva (`SEOResolver` → neměnné `SEOData` → `TagRenderer`) je nezávislá na frameworku. Liší se pouze to, *jak se vyhodnocená data dostanou do DOM, přežijí navigaci na straně klienta a zůstanou viditelná pro roboty*. Právě to kontrakt stanovuje.

> Tato specifikace byla zpřesněna nezávislou kontrolou návrhu.
> Novou kontrolu provádějte jen při podstatné změně.

---

## 1. Hodnoty — co obsahuje vyhovující `<head>` {#_1-values-—-what-a-compliant-head-contains}

### Titulek, popis, kanonická URL {#title-description-canonical}

- **Právě jeden `<title>`** s *vyhodnoceným* titulkem, nikdy s dvojitou příponou. Resolver přidává `seo.title_suffix` jednou a kontroluje, zda jím titulek už nekončí.
- **Jedna meta značka description**, jen pokud byl popis vyhodnocen; žádná prázdná značka.
- **Jeden `<link rel="canonical">`**.

### Robots {#robots}

- Vykreslete `<meta name="robots">` **jen tehdy, když se direktiva liší od výchozí hodnoty webu**. Nadbytečné `index,follow` je zbytečné; jeho *nepřítomnost* robot chápe právě jako `index,follow`. Porovnání ignoruje mezery (`index, follow` ≡ `index,follow`), ale odlišná direktiva se vypíše **doslova**. `seo.robots.emit_default = true` vykreslení značky vynutí.
- Podporujte deterministické **pokročilé direktivy**: `noindex`, `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`, `max-video-preview`, `notranslate`, `unavailable_after`. Jde o vyhodnocené řetězcové hodnoty; jejich **prioritu určuje řetězec resolveru** (globální → trasa → model → výslovná hodnota). Stejné vstupy ⇒ stejný výstup.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`, `og:locale`.
- `article:*` (`published_time`, `modified_time`, `author`, `section`, `tag`) **jen při `og:type === 'article'` a skutečné hodnotě**. Nikdy nevymyšlené a nikdy na stránce, která není článkem.
- `og:image` s `og:image:width` / `og:image:height` / `og:image:alt` a `og:image:type`, **pokud jsou známé**. Více obrázků se **seskupuje**: každý `og:image` bezprostředně následují jeho vlastní rozměry, alternativní text a typ.

### Twitter Cards {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` a `twitter:image:alt`, pokud je známý alternativní text obrázku.
- `twitter:site` a `twitter:creator` jsou **volitelné a nezávislé**. Jedno může být přítomné bez druhého a ani jedno se nevymýšlí podle druhého.

### Hreflang a jazyková verze {#hreflang-locale}

- Hreflang má vlastní cestu resolverem přes hook `getSEOAlternates()` modelu.
- Pokud existují alternativy hreflang, jsou **absolutní, normalizované a jedinečné podle jazyka**, při úplných datech i vzájemné. `x-default` pouze při jeho nastavení.
- `og:locale:alternate` odráží **jen** jazykové verze se skutečnou sociální variantou. Mapujte `en-US` → `en_US` a porovnávejte mapovanou podobu; nevyžadujte doslovnou shodu.
- `<html lang>` odpovídá vyhodnocenému jazyku. Tento bod patří do kontraktu, i když prvek `<html>` vykresluje *aplikace*.

### JSON-LD jednotlivých stránek {#per-page-json-ld}

- Musí být parsovatelné a bezpečné vůči `</script>`. Data jsou zakódovaná pomocí `JSON_HEX_TAG`, aby žádná hodnota nemohla předčasně ukončit prvek script; jde o ochranu před uloženým XSS.
- Přípustné jsou obě varianty: **více bloků `<script>` NEBO společný `@graph`**.
- Stabilní `@id` se používá **jen tam, kde se entity skutečně propojují** (Organization ↔ WebSite ↔ WebPage). U samostatných uzlů stabilní `@id` *není* povinné.

---

## 2. Normalizace a invarianty {#_2-normalization-invariants}

- **Absolutní URL `http(s)`** pro `canonical`, `og:url`, `og:image` a `twitter:image`. Do DOM se nikdy nedostanou **prázdné značky ani značky s null**.
- **`canonical` a `og:url` se MUSÍ vyhodnotit na stejnou normalizovanou URL.** Neshoda je **ZÁVAŽNÉ selhání**, nikoli upozornění.
- **Pravidla normalizace kanonických URL jsou všude stejná**: schéma, hostitel, port, velikost písmen cesty, seznam povolených parametrů dotazu i koncové lomítko se pokaždé zpracují stejně. Indexovatelné stránky **odkazují samy na sebe**; stránka `noindex` **nepřebírá** strategii kanonické adresy jiné stránky.
- **Escapování odpovídá místu použití**: atribut HTML, text i JSON mají vlastní správný kodér. Testy porovnávají **dekódované významové hodnoty, nikoli bajty**.
- **Shoda mezi renderery je významová, nikoli bajtová.** `render()` (HTML) ≡ `toArray()` ≡ `toInertiaHead()` *po normalizaci*. Tyto reprezentace se oprávněně liší pořadím a tvarem značek. Pravidla pro jedinečné a opakovatelné vlastnosti jsou výslovná: jeden `og:title`, více `article:tag`.
- **Vlastnictví značek**: klientský renderer nahrazuje značky *vlastněné balíčkem* podle klíčů z oddílu 4, aniž by mazal nesouvisející značky aplikace.

---

## 3. Chování — navigace na straně klienta {#_3-behaviour-—-client-side-navigation}

Po každé návštěvě Inertie nebo `wire:navigate` v Livewire:

- existuje **právě jedna značka každého jedinečného typu** (`<title>`, popis, kanonická adresa, každá `og:*`/`twitter:*`) a **žádná zastaralá**;
- **JSON-LD se nehromadí**. Schéma předchozí stránky se odstraní, nikoli přidá pod nové. Livewire považuje `<script>` za neodstranitelný prostředek, proto skripty schématu nesou `data-seo-schema` a ID pro danou URL. Skripty předchozí stránky se odstraní při `livewire:navigated`; viz průvodce Livewire;
- navigace ze **stránky s bohatými metadaty na stránku bez nich odstraní nadbytečné značky**. Nová stránka neponechá popis, OG ani schéma předchozí;
- nejsou **žádná upozornění hydratace** a metadata jsou významově totožná před hydratací i po ní.

---

## 4. Klíče head-key v Inertii (vlastnictví značek) {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` přidává stabilní **`head-key`** ke každé položce meta/link. Inertia podle tohoto atributu odstraňuje duplikáty hlavičky. Značka `<Head>` stránky se stejným `head-key` jako značka layoutu ji *nahradí*, místo aby vytvořila duplikát.

- Základní klíč je `name ?? property` pro meta značky a `rel` pro odkazy.
- **Opakovatelné značky se rozlišují**, aby každá měla jedinečný klíč: `article:tag` → `article:tag`, `article:tag:1`, …; hreflang → `alternate:en-US`, `alternate:fr-FR`.

V šablonách jej navazujte jako **`:head-key`**, *nikoli* jako `:key` ve Vue. Ten je nesouvisející klíč párování pro `v-for` a deduplikaci hlavičky Inertie neovlivňuje.

---

## 5. Viditelnost pro roboty (výslovné režimy) {#_5-crawler-visibility-explicit-modes}

- **SSR / předvykreslení MUSÍ** splnit celý kontrakt v **samotném HTML HTTP odpovědi**. To se testuje zvlášť od hydratovaného DOM, s vypnutým JavaScriptem.
- **Pouhé CSR nemůže deklarovat shodu pro roboty.** Výchozí Inertia bez SSR vkládá metadata *na straně klienta*; počáteční HTML stažené robotem žádná metadata SEO neobsahuje. Omezení je dokumentované, nikoli skryté: **metadata viditelná pro roboty vyžadují Inertia SSR nebo předvykreslení** a JSON-LD pro roboty se má vykreslovat na serveru.

---

## 6. Mimo rozsah {#_6-out-of-scope-non-goals}

- **Odpovědnost aplikace, nikoli rendereru**: `charset`, `viewport` a ikony webu. Pozor: `<meta charset>` musí předcházet metadatům se znaky mimo ASCII, proto pořadí těchto prvků hlavičky řídí aplikace.
- **Testy e2e ověřují jen vytvořený výstup.** **Neověřují** indexaci Googlem, *výběr* kanonické adresy, způsobilost k rozšířeným výsledkům ani pozice. **Neověřují** ani MIME typ a dostupnost vzdálených obrázků. To patří do volitelných integračních testů nebo testů HTTP, nikdy do matice prohlížečových testů.

---

## 7. Stav shody {#_7-conformance-status}

Co dnes prokazuje jednotlivé body. **Jednotkové** = `RenderingContractTest` v Core a CI balíčku. **Prohlížeč/SSR** = `rankbeam-examples` v plánované matici. **Aplikace** = odpovědnost hostující aplikace. **Plánováno** = cílový bod kontraktu, jehož data ale `SEOData` zatím nemodeluje, takže renderer vykresluje bezpečnou podmnožinu.

| Bod | Stav |
|---|---|
| Právě jeden vyhodnocený `<title>` bez dvojité přípony | **Jednotkové** + prohlížeč |
| Meta značka description jen při přítomnosti popisu | **Jednotkové** + prohlížeč |
| Jeden `<link rel="canonical">`, nikdy prázdný | **Jednotkové** + prohlížeč |
| Robots jen při odchylce od výchozí hodnoty, doslova; přepínač `emit_default` | **Jednotkové** + prohlížeč |
| Pokročilé direktivy robots podle priorit resolveru | **Jednotkové** testy resolveru |
| `og:title/description/type/url/site_name/locale`; jazyková verze `en-US`→`en_US` | **Jednotkové** + prohlížeč |
| `article:*` jen při `og:type=article` a skutečné hodnotě | **Jednotkové** + prohlížeč |
| `og:image` přítomné a absolutní | **Jednotkové** + prohlížeč |
| `og:image:width/height/alt`, `og:image:type`, seskupování více obrázků | **Plánováno** — `SEOData` obsahuje jediný řetězec `ogImage`; rozměry, alternativní text ani typ zatím nemodeluje. Renderer vypisuje jedno absolutní `og:image`. |
| `twitter:card/title/description/image`; nezávislé `site`/`creator` | **Jednotkové** + prohlížeč |
| `twitter:image:alt` | **Plánováno** — pole alternativního textu obrázku zatím neexistuje. |
| Absolutní hreflang, jedinečné podle jazyka | **Jednotkové** + prohlížeč |
| Vzájemnost hreflang, `x-default` při nastavení | Prohlížeč, podle dat |
| `og:locale:alternate` odpovídá skutečným sociálním variantám | **Plánováno** — mapa sociálních variant pro jednotlivé jazyky zatím neexistuje. |
| Shoda `<html lang>` | **Aplikace**, prohlížeč ji ověřuje |
| Parsovatelné JSON-LD bezpečné vůči `</script>` | **Jednotkové** + prohlížeč |
| Více skriptů NEBO `@graph`; stabilní `@id` u propojených entit | **Jednotkové** testy grafu Merchant + prohlížeč |
| Absolutní URL; žádné prázdné značky ani null | **Jednotkové** + prohlížeč |
| `canonical` ≡ `og:url`; závažné selhání při neshodě | **Jednotkové** + prohlížeč |
| Jednotná normalizace kanonických adres, odkaz na sebe, oddělení noindex | Prohlížeč |
| Escapování podle místa použití; shoda dekódovaných významových hodnot | **Jednotkové** |
| Významová shoda rendererů (`render()` ≡ `toArray()` ≡ `toInertiaHead()`) | **Jednotkové** |
| Stabilní `head-key` Inertie a rozlišení opakovaných značek | **Jednotkové** + prohlížeč |
| Klientská navigace: jediné jedinečné značky, žádné zastaralé, JSON-LD se nehromadí, úklid | Prohlížeč — renderer dodává hooky `data-seo-schema` potřebné pro úklid |
| Žádná upozornění hydratace; shoda před ní a po ní | Prohlížeč |
| SSR splňuje celý kontrakt v surovém HTML; pouhé CSR je dokumentováno jako nevyhovující | Prohlížeč + dokumentace |

**Plánované body** jsou záměrné, dokumentované mezery. Kontrakt je trvalý cíl a tyto zpětně kompatibilní doplňky patří do budoucího úkolu. Vyžadují nová pole či sloupce `SEOData` a vydání vedlejší verze podle SemVer. Renderer dnes vypisuje bezpečnou podmnožinu a nikdy nevymýšlí hodnotu, kterou nemá.

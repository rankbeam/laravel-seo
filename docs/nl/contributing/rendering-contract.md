---
description: "De gezaghebbende checklist waaraan de head van elke frontendstack moet voldoen bij het renderen van Rankbeam-SEO-gegevens. De basis voor Core-renderertests en referentieapps."
---

# Het renderingcontract {#the-rendering-contract}

Dit is de **enige gezaghebbende checklist** waaraan de `<head>` van elke
frontendstack moet voldoen wanneer die SEO-gegevens van Rankbeam rendert.
Deze checklist is de bron voor:

- de unittests voor de rendererstructuur in Core (`tests/Unit/Services/RenderingContractTest.php`): het snelle,
  frameworkonafhankelijke deel dat onder de pakket-CI valt;
- de referentieapps per stack in `rankbeam-examples`: Blade, Inertia met Vue, React
  of Svelte, en Livewire. Hun browser- en SSR-tests controleren dezelfde
  voorwaarden in een echte DOM;
- de frameworkhandleidingen voor Blade, Inertia en JSON en Livewire.
  Die mogen nooit een aanpak beschrijven die dit contract schendt.

Als een stack niet aan een bepaling kan voldoen, is dat een **defect of een
gedocumenteerde beperking**. Het is geen reden om het contract af te zwakken.
De gegevenslaag, `SEOResolver` → onveranderlijke `SEOData` → `TagRenderer`,
is frameworkonafhankelijk. Alleen *hoe de uiteindelijke gegevens de DOM
bereiken, clientnavigatie doorstaan en zichtbaar blijven voor crawlers*
verschilt per stack. Precies dat legt dit contract vast.

> Deze specificatie is aangescherpt na een onafhankelijke ontwerpbeoordeling.
> Een nieuwe beoordeling is alleen nodig bij wezenlijke wijzigingen.

---

## 1. Waarden: wat een conforme `<head>` bevat {#_1-values-—-what-a-compliant-head-contains}

### Titel, beschrijving en canonieke URL {#title-description-canonical}

- **Precies één `<title>`** met de *uiteindelijke* titel, nooit met een
  dubbel achtervoegsel. De resolver voegt `seo.title_suffix` één keer toe en
  controleert of de titel er al op eindigt.
- **Eén metabeschrijving**, alleen als er een beschrijving is bepaald; geen lege tag.
- **Eén `<link rel="canonical">`**.

### Robots {#robots}

- Geef `<meta name="robots">` **alleen weer als de instructie afwijkt van de
  sitestandaard**. Een overbodige `index,follow` voegt ruis toe; juist de
  *afwezigheid* ervan leest een crawler als `index,follow`. Bij de vergelijking
  telt witruimte niet mee: `index, follow` ≡ `index,follow`. Een afwijkende
  instructie wordt **letterlijk** weergegeven. `seo.robots.emit_default = true` dwingt de tag af.
- Ondersteun deterministische **geavanceerde instructies**: `noindex`,
  `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`,
  `max-video-preview`, `notranslate` en `unavailable_after`. Dit zijn bepaalde
  stringwaarden. Hun **prioriteit volgt de resolverketen**: globaal → route
  → model → expliciet. Dezelfde invoer geeft dezelfde uitvoer.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`
  en `og:locale`.
- `article:*` (`published_time`, `modified_time`, `author`, `section`,
  `tag`) **alleen wanneer `og:type === 'article'` en de waarde werkelijk
  bestaat**. Nooit verzonnen, nooit op een pagina die geen artikel is.
- `og:image` met `og:image:width` / `og:image:height` / `og:image:alt` en
  `og:image:type` **wanneer bekend**. Meerdere afbeeldingen worden **gegroepeerd**:
  op elke `og:image` volgen direct de eigen eigenschappen voor afmetingen,
  alternatieve tekst en type.

### Twitter Cards {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` en
  `twitter:image:alt` wanneer alternatieve afbeeldingstekst bekend is.
- `twitter:site` en `twitter:creator` zijn **optioneel en onafhankelijk**.
  De ene mag zonder de andere voorkomen en geen van beide wordt uit de andere verzonnen.

### hreflang en locale {#hreflang-locale}

- Hreflang heeft een eigen ondersteunde resolverroute via de modelhook `getSEOAlternates()`.
- Hreflang-alternatieven zijn, indien aanwezig, **absoluut, genormaliseerd en
  uniek per taal**, en wederkerig waar de gegevens volledig zijn.
  `x-default` alleen indien geconfigureerd.
- `og:locale:alternate` weerspiegelt **alleen** locales met een echte sociale
  variant. Koppel `en-US` aan `en_US` en vergelijk de gekoppelde
  vorm; eis geen letterlijke gelijkheid.
- `<html lang>` komt overeen met de uiteindelijke locale. Deze bepaling
  hoort bij het contract, ook al genereert de *app* het `<html>`-element.

### JSON-LD per pagina {#per-page-json-ld}

- Parseerbaar en veilig tegen `</script>`. De payload wordt met
  `JSON_HEX_TAG` gecodeerd, zodat geen enkele waarde het scriptelement
  voortijdig kan sluiten: bescherming tegen opgeslagen XSS.
- Zowel **meerdere `<script>`-blokken als een gecombineerde `@graph`** zijn toegestaan.
- Een stabiele `@id` wordt **alleen gebruikt waar entiteiten
  werkelijk naar elkaar verwijzen**, zoals Organization ↔ WebSite ↔ WebPage.
  Een stabiele `@id` is *niet* verplicht op zelfstandige knooppunten.

---

## 2. Normalisatie en vaste voorwaarden {#_2-normalization-invariants}

- **Absolute `http(s)`-URL's** voor `canonical`, `og:url`,
  `og:image` en `twitter:image`. **Geen lege of null-tags** bereiken ooit de DOM.
- **`canonical` en `og:url` MOETEN dezelfde genormaliseerde URL
  opleveren.** Een verschil is een **HARDE fout**, geen waarschuwing.
- Het **normalisatiebeleid voor canonieke URL's is overal consistent**.
  Protocol, host, poort, hoofdlettergebruik in paden, toegestane queryparameters
  en afsluitende slash worden steeds op dezelfde manier behandeld.
  Indexeerbare pagina's **verwijzen naar zichzelf**. Een `noindex`-pagina
  neemt **niet** de canonieke strategie van een andere pagina over.
- **Escaping gebeurt per uitvoercontext**: HTML-attributen, tekst en JSON
  gebruiken elk de juiste encoder. Tests vergelijken **gedecodeerde
  betekenisvolle waarden, geen bytes**.
- **Gelijkheid tussen renderers gaat om betekenis, niet om bytes.**
  `render()` (HTML) ≡ `toArray()` ≡ `toInertiaHead()` *na normalisatie*.
  De drie representaties mogen verschillen in tagvolgorde en vorm. Regels
  voor eenmalige en herhaalbare eigenschappen zijn expliciet: één
  `og:title`, meerdere `article:tag`.
- **Eigenaarschap van tags**: een clientrenderer vervangt tags die *van het
  pakket zijn*, met sleutels zoals in §4, zonder andere tags van de app te verwijderen.

---

## 3. Gedrag bij clientnavigatie {#_3-behaviour-—-client-side-navigation}

Na elk Inertia-bezoek of elke Livewire-`wire:navigate`:

- bestaat **precies één exemplaar van elke eenmalige tag**: `<title>`,
  description, canonical en elke `og:*`/`twitter:*`. **Geen enkele is verouderd**;
- **stapelt JSON-LD zich niet op**. Schema van een vorige pagina wordt
  verwijderd en niet met het nieuwe schema gecombineerd. Livewire behandelt
  `<script>` als een niet-verwijderbare asset. Schemascripts krijgen daarom
  `data-seo-schema` en een ID per URL; die van de vorige pagina worden bij
  `livewire:navigated` verwijderd. Zie de Livewire-handleiding;
- **verwijdert navigatie van een pagina met veel metadata naar een kale
  pagina de extra tags**. De kale pagina behoudt geen description, OG of
  schema van de uitgebreide pagina;
- zijn er **geen hydratatiewaarschuwingen** en is de metadata vóór en na
  hydratatie inhoudelijk gelijk.

---

## 4. Inertia-head-keys (eigenaarschap van tags) {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` geeft elke meta-/linkvermelding een stabiele **`head-key`**.
Inertia ontdubbelt head-elementen op basis van dat attribuut. Een
`<Head>`-tag van de pagina met dezelfde `head-key` als een layouttag
*vervangt* die tag, in plaats van een duplicaat toe te voegen.

- Basissleutel: `name ?? property` voor meta, `rel` voor links.
- **Herhaalbare tags krijgen onderscheidende sleutels**, zodat elke tag
  uniek blijft: `article:tag` → `article:tag`, `article:tag:1`, …;
  hreflang → `alternate:en-US`, `alternate:fr-FR`.

Bind het in templates als **`:head-key`**, *niet* als Vue's `:key`.
Die laatste is de andere `v-for`-sleutel voor reconciliatie en doet niets
voor het ontdubbelen van de head door Inertia.

---

## 5. Zichtbaarheid voor crawlers (expliciete modi) {#_5-crawler-visibility-explicit-modes}

- **SSR of prerendering MOET de volledige contractuitvoer in de ruwe HTML van de
  HTTP-response opnemen.** Dit wordt apart van de gehydrateerde DOM
  getest, met JavaScript uitgeschakeld.
- **Alleen CSR rechtvaardigt geen claim van crawlerconformiteit.** Inertia
  voegt standaard, zonder SSR, metadata *aan de clientzijde* toe. De
  oorspronkelijke HTML die een crawler ophaalt bevat geen SEO-metadata.
  Dit wordt gedocumenteerd en niet verborgen: **metadata die zichtbaar is
  voor crawlers vereist Inertia SSR of prerendering**. Ook JSON-LD voor
  crawlers moet op de server worden gerenderd.

---

## 6. Buiten de reikwijdte {#_6-out-of-scope-non-goals}

- **Verantwoordelijkheden van de app, niet van de renderer**:
  `charset`, `viewport` en favicons. Let op: `<meta charset>` moet vóór
  niet-ASCII-metadata staan. De app bepaalt dus de volgorde van die head-elementen.
- **De end-to-endtest controleert alleen de weergegeven inhoud.** Hij
  controleert **niet** indexering door Google, de *selectie* van een canonieke
  URL, geschiktheid voor uitgebreide zoekresultaten of rankings. Ook MIME-type
  en beschikbaarheid van externe afbeeldingen vallen erbuiten. Die horen
  in optionele integratie-/HTTP-tests en nooit in de browsermatrix.

---

## 7. Status van conformiteit {#_7-conformance-status}

Het huidige bewijs per bepaling. **Unit** = `RenderingContractTest` in Core en de
pakket-CI. **Browser/SSR** = `rankbeam-examples`, de ingeplande matrix.
**App** = verantwoordelijkheid van de hostapplicatie. **Gepland** = opgenomen
als doel in het contract, maar de gegevens zijn nog niet gemodelleerd in
`SEOData`. De renderer geeft daarom het veilige deel weer.

| Bepaling | Status |
|---|---|
| Precies één uiteindelijke `<title>`, geen dubbel achtervoegsel | **Unit** + Browser |
| Metabeschrijving alleen als die bestaat | **Unit** + Browser |
| Eén `<link rel="canonical">`, nooit leeg | **Unit** + Browser |
| Robots alleen bij afwijking van de standaard, letterlijk, met schakelaar `emit_default` | **Unit** + Browser |
| Geavanceerde robots-instructies via de prioriteit van resolverlagen | **Unit** (resolver) |
| `og:title/description/type/url/site_name/locale`; locale `en-US`→`en_US` | **Unit** + Browser |
| `article:*` alleen wanneer `og:type=article` en de waarde bestaat | **Unit** + Browser |
| `og:image` aanwezig en absoluut | **Unit** + Browser |
| `og:image:width/height/alt`, `og:image:type`, groepering van meerdere afbeeldingen | **Gepland** — `SEOData` bevat één `ogImage`-string; afmetingen, alternatieve tekst en type zijn nog niet gemodelleerd. De renderer geeft één absolute `og:image` weer. |
| `twitter:card/title/description/image`; `site`/`creator` onafhankelijk | **Unit** + Browser |
| `twitter:image:alt` | **Gepland** — er is nog geen veld voor alternatieve afbeeldingstekst gemodelleerd. |
| hreflang absoluut en uniek per taal | **Unit** + Browser |
| hreflang wederkerig, `x-default` indien geconfigureerd | Browser (afhankelijk van de gegevens) |
| `og:locale:alternate` weerspiegelt echte sociale varianten | **Gepland** — er is nog geen koppeling van sociale varianten per locale gemodelleerd. |
| Gelijkheid van `<html lang>` | **App** (ook gecontroleerd door Browser) |
| JSON-LD parseerbaar en veilig tegen `</script>` | **Unit** + Browser |
| Meerdere scripts of `@graph`; stabiele `@id` waar entiteiten verwijzen | **Unit** (Merchant-graaf) + Browser |
| Absolute URL's; geen lege of null-tags | **Unit** + Browser |
| `canonical` ≡ `og:url`, harde fout bij verschil | **Unit** + Browser |
| Consistente canonieke normalisatie, zelfverwijzing en noindex-isolatie | Browser |
| Escaping per uitvoercontext; gedecodeerde inhoudelijke gelijkheid | **Unit** |
| Inhoudelijke gelijkheid tussen renderers: `render()` ≡ `toArray()` ≡ `toInertiaHead()` | **Unit** |
| Inertia-`head-key` stabiel en herhaalbare tags onderscheiden | **Unit** + Browser |
| Clientnavigatie: één exemplaar per eenmalige tag, niets verouderd, geen opstapeling van JSON-LD, opruimen | Browser — de renderer levert de `data-seo-schema`-hooks voor het opruimen |
| Geen hydratatiewaarschuwingen; gelijkheid vóór en na hydratatie | Browser |
| SSR neemt de volledige contractuitvoer in ruwe HTML op; alleen CSR is gedocumenteerd als niet-conform | Browser + documentatie |

**Geplande bepalingen** zijn bewuste, gedocumenteerde hiaten. Het contract is
het blijvende doel; deze aanvullingen behouden de bestaande compatibiliteit
en horen bij toekomstig werk. Ze vereisen nieuwe `SEOData`-velden of
-kolommen, dus een minorversie volgens SemVer. De renderer geeft nu het
veilige deel weer en verzint nooit waarden die hij niet heeft.

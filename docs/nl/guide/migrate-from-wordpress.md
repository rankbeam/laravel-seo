---
description: "Neem handgeschreven SEO uit Yoast of Rank Math mee naar je Laravel-modellen: titels, beschrijvingen, canonieke URL's, robots en focuszoekwoorden. Veldreferentie voor de importer."
---

# Migreren vanaf WordPress {#migrating-from-wordpress}

Verhuis je een contentsite vanaf WordPress? Rankbeam kan de SEO-metadata die
je team in Yoast of Rank Math heeft geschreven overbrengen naar je
Laravel-modellen: titels, beschrijvingen, canonieke URL's, robots-instructies,
focuszoekwoorden en aangepaste sociale metadata. Zo verlies je bij de
overstap geen jaren aan optimalisatiewerk.

::: tip Ga je echt omschakelen?
Deze pagina is de *referentie* voor de importer: veldkoppeling, tokens en
bronsleutels. Volg voor de stapsgewijze **procedure** met beperkt risico —
naast elkaar draaien, importeren, controleren en daarna uitfaseren — het
[WordPress-migratiedraaiboek](/nl/guide/wordpress-migration-runbook).
:::

Er zijn twee routes, beide via hetzelfde commando `seo:import-from`:

| Route | Bron | Geschikt voor |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | een spreadsheet die je uit WordPress exporteert | de meeste bureaumigraties; je bepaalt de exacte URL's |
| [**Database**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | de actieve WordPress-database | volledige overname, inclusief aangepaste OpenGraph-/Twitter-waarden en Rank Math-redirects |

Beide routes zijn **idempotent**: opnieuw uitvoeren werkt dezelfde rijen bij
zonder duplicaten. Ze ondersteunen **`--dry-run`** en *vullen* standaard alleen
lege velden. Ze overschrijven dus nooit SEO-gegevens die je al in Rankbeam hebt
ingesteld. Geef **`--overwrite`** mee om bestaande waarden wel door de
geïmporteerde waarden te vervangen.

## Hoe WordPress-rijen `seo_meta`-rijen worden {#how-wordpress-rows-become-seo-meta-rows}

WordPress gebruikt geen polymorfe Laravel-gegevens. Een WordPress-rij heeft
een **URL** of **bericht-ID** als sleutel, terwijl Rankbeams `seo_meta`
polymorf is: elke rij hoort bij een werkelijk Eloquent-model. De importer
koppelt elke WordPress-rij daarom aan een van je modellen. Het rapport maakt
duidelijk welke rijen zijn gekoppeld en welke alleen een URL hebben:

- **Aan een model gekoppeld.** Je geeft het doelmodel op met `--model="App\Models\Post"`.
  De **slug** van elke rij, het laatste padsegment van de URL of de
  WordPress-`post_name`, wordt met dat model vergeleken. Standaard gebeurt dat
  via de routesleutel, of via een kolom die je met `--match-by=` kiest.
  Overeenkomende rijen worden naar `seo_meta` geschreven.
- **Alleen een URL.** Een rij zonder overeenkomend model, of een uitvoering
  zonder `--model`, kan geen `seo_meta`-rij worden: er is geen model om
  haar aan te koppelen. Ze wordt als overgeslagen gerapporteerd met reden
  `url-only`. Haar canonieke URL kan nog wel een
  [redirectvoorstel](#redirects) opleveren.

WordPress-berichten en -pagina's horen meestal bij *verschillende*
Laravel-modellen. Voer de importer daarom één keer per contenttype uit en
beperk welke rijen worden verwerkt:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Eigen berichttypen worden standaard niet doorzocht
De databasereaders doorlopen alleen de berichttypen **`post`** en
**`page`**. Sites met eigen berichttypen, bijvoorbeeld `product`,
`event` of `pathology` uit een thema, moeten die expliciet opgeven.
Herhaal `--post-type=` voor elk type:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. CSV-import {#_1-csv-import}

De CSV-route dekt de meeste migraties door bureaus. Exporteer één rij per URL
met deze kopregel. De kolommen mogen in elke volgorde staan; onbekende kolommen
worden genegeerd en gerapporteerd:

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Voer de import uit:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Kolom | Koppeling in `seo_meta` | Opmerkingen |
|---|---|---|
| `url` | *(sleutel voor matching)* | De slug, het laatste padsegment, wordt aan het model gekoppeld. Verplicht. |
| `title` | `title` | Afgekapt op 70 tekens; te lange waarden worden gerapporteerd. |
| `description` | `description` | Afgekapt op 160 tekens. |
| `canonical` | `canonical` | Wordt ook gebruikt voor [redirectvoorstellen](#redirects). |
| `robots` | `robots` | Letterlijk opgeslagen, bijvoorbeeld `noindex, nofollow`; afgekapt op 50 tekens. |
| `focus_keyword` | `focus_keywords` | Kommagescheiden; het eerste zoekwoord is het primaire. |

Ongeldige rijen worden overgeslagen en geteld: rijen zonder `url` of
met een aantal kolommen dat afwijkt van de kopregel.

---

## 2. Database-import (Yoast / Rank Math) {#_2-database-import-yoast-rank-math}

Heb je de WordPress-database nog, dan kan de importer de SEO-metadata
rechtstreeks lezen. Dat omvat de aangepaste OpenGraph-/Twitter-waarden en,
voor Rank Math, de redirects die bij een CSV-export meestal verloren gaan.

### Een verbinding met WordPress instellen {#point-a-connection-at-wordpress}

Voeg de WordPress-database als verbinding toe in `config/database.php`:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Importeer vervolgens. Het tabelvoorvoegsel is standaard `wp_`;
overschrijf het met `--table=`:

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

De reader doorloopt `{prefix}posts` met gepubliceerde berichten en pagina's,
haalt de pluginmetadata per bericht uit `{prefix}postmeta` en koppelt de
`post_name`-slug aan je model.

::: tip Een afwijkend tabelvoorvoegsel
Beheerde WordPress-hosts gebruiken vaak een willekeurig voorvoegsel,
bijvoorbeeld `wppg_` in plaats van `wp_`. Controleer de
`CREATE TABLE`-namen in je dump en geef het echte voorvoegsel mee:
`--table=wppg_`. Zo vindt de reader `{prefix}posts` en `{prefix}postmeta`.
:::

::: tip Lezen uit een herstelde dump op MySQL 8
Als je een WordPress-dump in MySQL 8+ laadt om die lokaal te lezen, versoepel
dan vóór het inladen van de `.sql` de strikte SQL-modus. De standaardwaarden
`'0000-00-00'` voor datum/tijd in WordPress worden geweigerd door de standaardmodi
`STRICT`/`NO_ZERO_DATE` van MySQL 8. Daardoor mislukt het importeren van de
dump zelf al met `Invalid default value for 'post_date'`, voordat de SEO-import begint:

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Veldkoppeling {#field-mapping}

Beide importers koppelen velden **expliciet**. Een sleutel zonder kolom in
Core 3 wordt als *unmapped* gerapporteerd; er wordt geen kolom verzonnen.

| Yoast-metasleutel | Rank Math-metasleutel | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Alleen afwijkingen van de WordPress-standaardwaarden worden
opgeslagen. Een gewone indexeerbare pagina laat `robots` dus null en
neemt je sitestandaard over. De afzonderlijke instellingen `noindex`,
`nofollow` en geavanceerde instellingen `noarchive`, `nosnippet` en
`noimageindex` van Yoast worden tot één string samengevoegd. De geserialiseerde
`robots`-array van Rank Math wordt op dezelfde manier gelezen, met
weglating van de standaardwaarden `index` / `follow`.

**Niet-gekoppelde sleutels**, die worden gerapporteerd maar nooit gekopieerd:
ID's van afbeeldingsbijlagen (`*-image-id`), zoekwoord-/SEO-scores
(`linkdex`, `content_score`, `rank_math_seo_score`), keuzes voor de primaire
categorie en Rank Maths schemamarkeringen voor rich snippets. De
[schemagraaf](/nl/guide/schema) is daarvoor een uitgebreidere, getypeerde vervanging.

::: warning Canonieke URL's worden letterlijk geïmporteerd
Een expliciete canonieke URL (`rank_math_canonical_url` / `_yoast_wpseo_canonical`) wordt **exact
overgenomen zoals die is opgeslagen**. Als een pagina de canonieke URL heeft
vastgezet op een absolute URL op het *oude* domein, wat vaak voorkomt bij
beheerde of staginghosts, blijft die daar na import naar verwijzen.
Bijvoorbeeld `https://oldsite-staging.example.com/page/`. De importer herschrijft de host nooit.
`--site-url` leidt aanvraag*paden* af uit absolute URL's voor
[redirectvoorstellen](#redirects) en het koppelen van CSV-rijen, maar
herschrijft **geen** opgeslagen canonieke waarden. Controleer na een
domeinverhuizing de geïmporteerde canonieke URL's en werk de host bij, of wis
ze om terug te vallen op de zelfverwijzende canonieke URL van de resolver.
De meeste pagina's hebben geen expliciete canonieke URL en ondervinden geen
gevolgen: Yoast en Rank Math bepalen die automatisch tijdens het renderen.
:::

### Templatetokens {#template-tokens}

Yoast en Rank Math slaan titels en beschrijvingen op als **templates** met
tokens. Yoast gebruikt `%%title%%` en Rank Math `%title%`.
De importer **vult tokens in die hij kan afleiden** en **verwijdert de rest**.
Zo wordt er nooit een ruwe string zoals `%%token%%` opgeslagen:

| Token | Wordt ingevuld met |
|---|---|
| `%%title%%` / `%title%` | de titel van het WordPress-bericht |
| `%%sitename%%` / `%sitename%` | de blognaam uit `wp_options` (database-import) |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *verwijderd*: leeg gelaten, met opschoning van omliggende scheidingstekens |

Als een uitvoering tokens heeft ingevuld, vermeldt het rapport dat.
**Controleer de geïmporteerde titels** op de gewenste formulering en pas
de titels aan die afhankelijk waren van tokens die we niet konden afleiden.

---

## Redirects {#redirects}

`seo_redirects` is een functie van [Rankbeam **Pro**](/nl/pro/installation).
Een Core-importer schrijft daarom nooit rechtstreeks naar die tabel.
Geef in plaats daarvan `--redirects-csv=` mee: de importer **genereert een CSV**
met dezelfde kolommen als de Pro-redirecttabel, `source_path,target_url,status_code,note`, die je in Pro importeert.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Waar redirectvoorstellen vandaan komen:

- **CSV-import** — een rij waarvan `canonical` naar een **ander pad** wijst
  dan haar eigen `url`, wordt een `301` van het oude pad
  naar de canonieke URL. Een zelfverwijzende canonieke URL met hetzelfde pad
  wordt *niet* geëxporteerd, omdat die een lus zou vormen.
- **Rank Math-database** — actieve regels uit de tabel `{prefix}rank_math_redirections`.
  Alleen regels met een **exacte overeenkomst** worden geëxporteerd.
  Regex-, contains-, start- en end-regels worden als overgeslagen gerapporteerd,
  omdat ze niet overeenkomen met één pad.
- **Yoast (gratis)** heeft geen redirecttabel. Alleen Yoast Premium heeft die,
  en dat schema hoort niet bij het gratis pakket. Gebruik voor Yoast-redirects de CSV-route.

De voorstellen zijn **adviserend**. Controleer de CSV en importeer die daarna
in Pro met [`seo-pro:redirects-import`](/nl/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro).
Dit valideert elke rij en weigert lussen, onveilige doelen en duplicaten.
De CSV-structuur is een stabiel contract: **redirect-CSV-formaat v1**:
`source_path,target_url,status_code,note`.

---

## Wat het rapport laat zien {#what-the-report-tells-you}

Zonder `--json` toont een uitvoering een resultatentabel met created (aangemaakt),
updated (bijgewerkt), unchanged (ongewijzigd), skipped (overgeslagen) en scanned (gecontroleerd), een **Verification report (verificatierapport)** en
onderdelen om te controleren:

- **Verification report (verificatierapport)** — de verdeling die je in één oogopslag controleert:
  **matched**, rijen gekoppeld aan een model; **url-only**, zonder overeenkomend
  model; en de aantallen afgekapt en niet gekoppeld.
- **Truncated (ingekort)** — waarden die zijn ingekort om in een `seo_meta`-kolom te passen.
- **Not imported (niet geïmporteerd)** — bronsleutels die gegevens bevatten maar geen plek in
  Core 3 hebben, **inclusief elke unieke `author`-waarde**. De auteur
  staat niet in een opgeslagen kolom, maar wordt via
  [`getSEOAuthor()`](/nl/concepts/resolver-precedence) bepaald. Het rapport vermeldt
  dus welke gegevens je elders moet onderbrengen, zodat ze niet stilzwijgend verdwijnen.
- **Redirect candidates (redirectvoorstellen)** — hoeveel voorstellen zijn geschreven en naar welk bestand.
- **Skipped rows by reason (overgeslagen rijen per reden)** — rijen met alleen een URL, berichten zonder
  SEO-metadata en redirectregels zonder exacte overeenkomst.
- **Warnings (waarschuwingen)** — bijvoorbeeld dat templatetokens zijn ingevuld.

Voeg `--json` toe voor een machinaal leesbare versie van dit alles.
Het blok `verification` bevat de aantallen matched/url-only en elke auteurswaarde.

### Controleren {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict` geeft een niet-nul-exitcode als een pagina een bevinding heeft.
Zie [Gratis SEO-audit](/nl/guide/audit). Volg voor de volledige omschakelprocedure
in de juiste volgorde — naast elkaar draaien → importeren → controleren →
uitfaseren — het [WordPress-migratiedraaiboek](/nl/guide/wordpress-migration-runbook).

---

Kom je van een **Laravel**-SEO-pakket zoals ralphjsmit, artesaos of Spatie?
Zie [Migreren vanaf andere Laravel-pakketten](/nl/guide/migrate-from-other-packages).

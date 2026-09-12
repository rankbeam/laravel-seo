---
description: "Přeneste ručně napsané SEO z Yoastu či Rank Math do modelů Laravelu: titulky, popisy, kanonické URL, robots a hlavní klíčová slova. Referenční přehled mapování polí importéru."
---

# Migrace z WordPressu {#migrating-from-wordpress}

Převádíte obsahový web z WordPressu? Rankbeam dokáže přenést metadata SEO, která váš tým ručně napsal v Yoastu nebo Rank Math, do modelů Laravelu: titulky, popisy, kanonické URL, direktivy robots, hlavní klíčová slova i vlastní hodnoty pro sociální sítě. Při přechodu tak nepřijdete o roky optimalizace.

::: tip Provádíte skutečný přechod?
Tato stránka je *referencí* importéru: mapování polí, tokeny a zdrojové klíče. Podrobný **postup** s omezením rizik — souběžný provoz, import, ověření a následné vyřazení původního systému — najdete v [postupu migrace z WordPressu](/cs/guide/wordpress-migration-runbook).
:::

Existují dvě cesty. Obě používají stejný příkaz `seo:import-from`:

| Cesta | Zdroj | Nejvhodnější použití |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | tabulka exportovaná z WordPressu | většina agenturních migrací; máte kontrolu nad přesnými URL |
| [**Databáze**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | existující databáze WordPressu | úplný přenos včetně vlastních hodnot OpenGraph/Twitter a přesměrování Rank Math |

Obě cesty jsou **idempotentní**: opakované spuštění aktualizuje stejné řádky a nevytváří duplikáty. Podporují **`--dry-run`** a ve výchozím nastavení pouze *doplňují* prázdná pole, takže nepřepisují data SEO už nastavená v Rankbeamu. Pro nahrazení existujících hodnot importovanými předejte **`--overwrite`**.

## Jak se z řádků WordPressu stávají řádky `seo_meta` {#how-wordpress-rows-become-seo-meta-rows}

Data WordPressu nejsou polymorfní data Laravelu. Řádek WordPressu identifikuje **URL** nebo **ID příspěvku**, zatímco `seo_meta` Rankbeamu je polymorfní: každý řádek patří skutečnému modelu Eloquent. Importér proto páruje řádky WordPressu s vašimi modely a v reportu rozlišuje připojené řádky od řádků obsahujících pouze URL:

- **Připojené k modelu.** Cílový model určíte pomocí `--model="App\Models\Post"`. **Slug** každého řádku, tedy poslední segment cesty URL nebo `post_name` WordPressu, se páruje s modelem. Standardně podle klíče trasy, případně podle sloupce zvoleného přes `--match-by=`. Spárované řádky se zapíší do `seo_meta`.
- **Pouze URL.** Řádek, pro který se nenajde model, nebo běh bez `--model` nemůže vytvořit řádek `seo_meta`: chybí model, ke kterému by se připojil. V reportu bude přeskočen s důvodem `url-only`. Jeho kanonická URL může přesto vytvořit [návrh přesměrování](#redirects).

Příspěvky a stránky WordPressu obvykle odpovídají *různým* modelům Laravelu. Spusťte proto importér zvlášť pro každý typ obsahu a omezte výběr řádků:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Vlastní typy příspěvků se standardně neprocházejí
Databázové čtečky procházejí pouze typy **`post`** a **`page`**. Weby s vlastními typy příspěvků, například `product`, `event` či `pathology` ze šablony, musí každý typ uvést výslovně opakováním `--post-type=`:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. Import CSV {#_1-csv-import}

CSV pokrývá většinu agenturních migrací. Exportujte jeden řádek na URL s touto hlavičkou. Sloupce mohou být v libovolném pořadí; nerozpoznané se ignorují a uvedou v reportu:

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Spusťte import:

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

| Sloupec | Mapování do `seo_meta` | Poznámky |
|---|---|---|
| `url` | *(párovací klíč)* | Slug, poslední segment cesty, se páruje s modelem. Povinný. |
| `title` | `title` | Zkráceno na 70 znaků; příliš dlouhé hodnoty jsou v reportu. |
| `description` | `description` | Zkráceno na 160 znaků. |
| `canonical` | `canonical` | Používá se také pro [návrhy přesměrování](#redirects). |
| `robots` | `robots` | Uloží se doslova, například `noindex, nofollow`; zkráceno na 50 znaků. |
| `focus_keyword` | `focus_keywords` | Oddělená čárkami; první klíčové slovo je hlavní. |

Chybné řádky se přeskočí a započítají: řádky bez `url` nebo s počtem sloupců neodpovídajícím hlavičce.

---

## 2. Import z databáze (Yoast / Rank Math) {#_2-database-import-yoast-rank-math}

Pokud stále máte databázi WordPressu, importér může číst metadata SEO přímo, včetně vlastních hodnot OpenGraph/Twitter a u Rank Math také přesměrování. Ty se při exportu do CSV obvykle ztratí.

### Nastavte připojení k WordPressu {#point-a-connection-at-wordpress}

Přidejte databázi WordPressu jako připojení do `config/database.php`:

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

Potom importujte. Výchozí prefix tabulek je `wp_`; přepíšete jej pomocí `--table=`:

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

Čtečka prochází `{prefix}posts`, tedy publikované příspěvky a stránky, a metadata pluginu získává pro každý příspěvek z `{prefix}postmeta`. Slug `post_name` každého příspěvku páruje s vaším modelem.

::: tip Nestandardní prefix tabulek
Spravované hostingy WordPressu často používají náhodný prefix, například `wppg_` místo `wp_`. Zkontrolujte názvy `CREATE TABLE` v databázovém exportu a předejte skutečný prefix, například `--table=wppg_`, aby čtečka našla `{prefix}posts` a `{prefix}postmeta`.
:::

::: tip Čtení obnoveného exportu v MySQL 8
Pokud načítáte export WordPressu do MySQL 8+ pro místní čtení, před spuštěním `.sql` zmírněte přísný režim SQL. Výchozí hodnoty datetime `'0000-00-00'` z WordPressu odmítají výchozí režimy MySQL 8 `STRICT`/`NO_ZERO_DATE`. Import databázového exportu by tak selhal s `Invalid default value for 'post_date'` ještě před spuštěním importu SEO:

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Mapování polí {#field-mapping}

Oba importéry mapují pole **výslovně**. Klíč bez odpovídajícího sloupce v Core 3 se označí jako *unmapped*, nikdy se pro něj nevymýšlí nové pole.

| Klíč metadat Yoast | Klíč metadat Rank Math | `seo_meta` |
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

**Robots.** Ukládají se jen odchylky od výchozích hodnot WordPressu. Běžná indexovatelná stránka proto ponechá `robots` jako null a zdědí výchozí nastavení webu. Samostatné příznaky Yoastu `noindex` / `nofollow` a pokročilé příznaky `noarchive`, `nosnippet`, `noimageindex` se složí do jednoho řetězce. Serializované pole `robots` Rank Math se čte stejně, s vynecháním výchozích `index` / `follow`.

**Nenamapované klíče**, které se uvedou v reportu a nikdy se nekopírují: ID přiložených obrázků (`*-image-id`), skóre klíčových slov či SEO (`linkdex`, `content_score`, `rank_math_seo_score`), volby hlavní kategorie a značky schématu pro rozšířené výsledky Rank Math. Bohatší typovanou náhradou poslední skupiny je [graf schématu](/cs/guide/schema).

::: warning Kanonické URL se importují doslova
Výslovná kanonická URL (`rank_math_canonical_url` / `_yoast_wpseo_canonical`) se kopíruje **přesně tak, jak je uložená**. Pokud stránka pevně nastavila absolutní kanonickou URL na *staré* doméně, což je běžné na spravovaných či stagingových hostinzích, například `https://oldsite-staging.example.com/page/`, bude tam odkazovat i po importu. Importér hostitele nikdy nepřepisuje. Volba `--site-url` odvozuje z absolutních URL *cesty* pro [návrhy přesměrování](#redirects) a párování řádků CSV, ale uložené kanonické hodnoty **nepřepisuje**. Po změně domény zkontrolujte importované kanonické adresy a upravte hostitele nebo je vymažte, aby resolver odvodil kanonickou URL vlastní stránky. Většina stránek nemá výslovnou kanonickou URL a tento problém se jich netýká: Yoast i Rank Math ji automaticky vytvářejí při vykreslování.
:::

### Tokeny šablon {#template-tokens}

Yoast a Rank Math ukládají titulky a popisy jako **šablony** s tokeny. Yoast používá `%%title%%`, Rank Math `%title%`. Importér **vyhodnotí tokeny, jejichž hodnotu dokáže odvodit**, a **ostatní odstraní**, takže se nikdy neuloží doslovný řetězec `%%token%%`:

| Token | Výsledná hodnota |
|---|---|
| `%%title%%` / `%title%` | titulek příspěvku WordPressu |
| `%%sitename%%` / `%sitename%` | název blogu z `wp_options` při databázovém importu |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *odstraněno*; ponechá se prázdné místo a okolní oddělovače se uklidí |

Pokud běh vyhodnotil některý token, uvede to v reportu. **Zkontrolujte importované titulky**, zda odpovídají zamýšlenému znění, a upravte ty, které závisely na tokenech, jejichž hodnotu se nepodařilo odvodit.

---

## Přesměrování {#redirects}

`seo_redirects` je funkcí [Rankbeamu **Pro**](/cs/pro/installation), takže importér Core do této tabulky nikdy přímo nezapisuje. Místo toho předejte `--redirects-csv=` a importér **vytvoří CSV** se stejnými sloupci jako tabulka přesměrování Pro — `source_path,target_url,status_code,note` — které následně importujete do Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Odkud pocházejí návrhy přesměrování:

- **Import CSV** — řádek, jehož `canonical` směřuje na **jinou cestu** než jeho vlastní `url`, vytvoří `301` ze staré cesty na kanonickou URL. Při kanonické URL se stejnou cestou se přesměrování *nevytvoří*, protože by vznikla smyčka.
- **Databáze Rank Math** — aktivní pravidla v tabulce `{prefix}rank_math_redirections`. Vytvářejí se pouze pravidla **přesné shody**. Regex, obsahuje, začíná a končí se označí jako přeskočené, protože neodpovídají jediné cestě.
- **Bezplatný Yoast** nemá tabulku přesměrování. Má ji pouze Yoast Premium a její schéma není součástí bezplatného balíčku. Pro přesměrování Yoastu použijte CSV.

Jde o **návrhy ke kontrole**. Projděte CSV a potom je importujte do Pro pomocí [`seo-pro:redirects-import`](/cs/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro), který validuje každý řádek a odmítá smyčky, nebezpečné cíle i duplikáty. Struktura CSV je stabilní kontrakt: **formát CSV přesměrování v1**: `source_path,target_url,status_code,note`.

---

## Co vám řekne report {#what-the-report-tells-you}

Běh bez `--json` vypíše tabulku výsledků (created / updated / unchanged / skipped / scanned), **Verification report** a části ke kontrole:

- **Verification report** — přehled, který před pokračováním schválíte: **matched** jsou řádky připojené k modelu, **url-only** řádky bez modelu; doplňují je počty zkrácených a nenamapovaných hodnot.
- **Truncated** — hodnoty zkrácené na délku sloupce v `seo_meta`.
- **Not imported** — zdrojové klíče obsahující data bez odpovídajícího místa v Core 3, **včetně každé odlišné hodnoty `author`**. Autor není uložený sloupec, řeší jej [`getSEOAuthor()`](/cs/concepts/resolver-precedence). Report proto uvádí, co je třeba přesunout jinam, aby údaj nepozorovaně nezmizel.
- **Redirect candidates** — počet zapsaných návrhů přesměrování a cílový soubor.
- **Skipped rows by reason** — řádky pouze s URL, příspěvky bez metadat SEO a přesměrování bez přesné shody.
- **Warnings** — například upozornění na vyhodnocení tokenů šablon.

Přidejte `--json` pro strojově čitelnou podobu všech těchto údajů. Blok `verification` obsahuje počty matched/url-only a každou hodnotu autora.

### Ověření {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict` skončí s nenulovým stavem, pokud má kterákoli stránka problém. Viz [Bezplatný SEO audit](/cs/guide/audit). Úplný postup přechodu v pořadí souběžný provoz → import → ověření → vyřazení původního systému najdete v [postupu migrace z WordPressu](/cs/guide/wordpress-migration-runbook).

---

Přecházíte místo toho z balíčku SEO pro **Laravel**, například ralphjsmit, artesaos nebo Spatie? Viz [Migrace z jiných balíčků pro Laravel](/cs/guide/migrate-from-other-packages).

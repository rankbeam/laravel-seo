---
description: "Přejděte z jiného balíčku SEO pro Laravel na Rankbeam: převeďte jeho API a úložiště na trait HasSEO a saveSEO(). Data SEO jednotlivých modelů importujte jedním příkazem."
---

# Migrace z jiných balíčků SEO pro Laravel {#migrating-from-other-laravel-seo-packages}

Používáte už jiný balíček SEO? Přechod na Rankbeam má být práce na jeden den, nikoli přepis aplikace. Tento průvodce převádí API a úložiště běžných balíčků na dva základní prvky Rankbeamu: trait [`HasSEO`](/cs/guide/quickstart) a `saveSEO()`. Pro balíček, který ukládá data SEO u jednotlivých modelů, nabízí import jedním příkazem.

::: tip Přecházíte z WordPressu?
Pokud převádíte obsahový web z WordPressu s Yoastem nebo Rank Math, použijte samostatného průvodce [**Migrace z WordPressu**](/cs/guide/migrate-from-wordpress). Popisuje importér CSV i čtení z existující databáze.
:::

| Zdrojový balíček | Kam ukládá data | Postup migrace |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | polymorfní tabulka `seo` | **`php artisan seo:import-from ralphjsmit`** a výměna traitu |
| [`artesaos/seotools`](#from-artesaos-seotools) | nikam; běhové hodnoty a konfigurace | změna kódu: hodnoty přes `saveSEO()` nebo vypočítávané gettery |
| [`spatie/*`](#from-spatie-packages) | nikam; generátory schema-org a map webu | ponechte doplňující funkce, zbytek převeďte do Rankbeamu |

Pouze **ralphjsmit** ukládá data SEO do databázové tabulky, takže jen u něj existují data k hromadnému importu. Ostatní sestavují značky za běhu a nemají tabulku ke čtení. Jejich volání při každém požadavku nahradíte uloženými hodnotami `seo_meta`.

---

## Z `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

`ralphjsmit/laravel-seo` ukládá pro každý model jeden polymorfní řádek do tabulky `seo`. Její struktura je blízká tabulce `seo_meta` Rankbeamu, což umožňuje přímý, idempotentní hromadný import.

### 1. Nainstalujte Rankbeam vedle původního balíčku {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Během migrace mohou oba balíčky existovat současně. Používají různé tabulky (`seo` a `seo_meta`) i různé jmenné prostory traitů.

::: warning Jeden konfigurační soubor, nikoli dva
Pokud v aplikaci zůstává zkopírovaný `config/seo.php` z `ralphjsmit/laravel-seo`, překryje konfiguraci Rankbeamu, protože oba balíčky sdílejí konfigurační klíč `seo`. Zálohujte ho, odstraňte a zkopírujte konfiguraci Rankbeamu: `php artisan vendor:publish
--tag=seo-config`.
:::

### 2. Spusťte importér {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

Importér čte tabulku `seo` balíčku ralphjsmit, dohledá skutečný model Eloquent pro každý řádek a zapíše data do `seo_meta`.

| Volba | Účinek |
|---|---|
| `--dry-run` | Vypíše, co by se importovalo, a nic nezapíše. |
| `--model="App\Models\Post"` | Omezí import na jednu nebo více tříd modelů; lze opakovat. |
| `--locale=fr` | Zapíše řádky pro tento jazyk; výchozí je jazyk aplikace. |
| `--table=legacy_seo` | Čte přejmenovanou zdrojovou tabulku. |
| `--connection=legacy` | Čte zdrojovou tabulku z jiného databázového připojení. |
| `--limit=100` | Importuje nejvýše N řádků; vhodné pro postupnou migraci. |
| `--overwrite` | Nahradí existující neprázdné hodnoty; standardně se pouze doplňují prázdná pole. |
| `--json` | Strojově čitelný report. |
| `--force` | Přeskočí potvrzení pro použití ve skriptech nebo CI. |

Import je **idempotentní**: opakované spuštění aktualizuje stejné řádky a nevytváří duplikáty. Ve výchozím nastavení pouze *doplňuje* prázdná pole a nepřepisuje data SEO, která jste už v Rankbeamu nastavili. Pokud chcete stávající hodnoty nahradit importovanými, předejte `--overwrite`.

### 3. Vyměňte trait na modelech {#_3-swap-the-trait-on-your-models}

Nahraďte trait ralphjsmitu traitem Rankbeamu. Názvy metod se mírně liší a trait nyní čte tabulku `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Pokud jste data SEO upravovali pomocí `getDynamicSEOData()` z ralphjsmitu, přesuňte logiku do getterů Rankbeamu pro vypočítávané hodnoty jednotlivých polí: `getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()` a `getSEOAlternates()`. Viz [Rychlý začátek](/cs/guide/quickstart). Výslovně uložené hodnoty nastavujte přes `saveSEO()`:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Mapování polí {#field-mapping}

Importér mapuje pole **výslovně**. Nikdy slepě nekopíruje sloupec, který schéma Core 3 neobsahuje.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Poznámky |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | **Znovu určené** ze skutečného modelu, nikoli doslovně zkopírované; viz níže. |
| `title` | `title` | Zkráceno na 70 znaků, délku sloupce v `seo_meta`; příliš dlouhé hodnoty jsou v reportu. |
| `description` | `description` | Zkráceno na 160 znaků; příliš dlouhé hodnoty jsou v reportu. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Zkráceno na 50 znaků. |
| `image` | `og_image` | `twitter:image` hodnotu automaticky zdědí přes resolver. |
| `author` | *(neimportuje se)* | `seo_meta` v Core 3 nemá sloupec autora. Autor článku se řeší na úrovni resolveru, nejde o uložená sociální metadata. Řádky s autorem se **počítají a uvádějí v reportu**, abyste rozhodli, kam údaj patří, například do vypočítávané hodnoty typu `getSEOData`. |
| `id`, `created_at`, `updated_at` | *(neimportují se)* | Strukturální údaje. |

**Proč se polymorfní typ určuje znovu.** Pro každý zdrojový řádek se dohledá skutečný model a klíče `seoable` se převezmou z jeho vlastního `getMorphClass()`. Vztah tak odpovídá *aktuální* [mapě polymorfních typů](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types) aplikace, i když ralphjsmit používal jinou konvenci. Importér také může přeskočit řádky modelů, které byly mezitím odstraněny. Označí je jako přeskočené a nikdy je nezapíše jako osiřelé záznamy.

### Co vám řekne report {#what-the-report-tells-you}

Běh bez `--json` vypíše tabulku výsledků a tři části ke kontrole:

- **Truncated** — hodnoty zkrácené na délku sloupce v `seo_meta`. Zkontrolujte je.
- **Not imported** — zdrojové sloupce, například `author`, které obsahovaly data, ale nemají odpovídající místo v Core 3.
- **Skipped rows by reason** — přeskočené řádky podle důvodu: prázdné zdroje, odstraněné modely nebo nedohledané typy modelů.

### Ověření {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Jakmile výsledek vyhovuje, odeberte `ralphjsmit/laravel-seo` a odstraňte jeho tabulku `seo`.

---

## Z `artesaos/seotools` {#from-artesaos-seotools}

`artesaos/seotools` sestavuje značky **za běhu**. Hodnoty nastavujete pro každý požadavek přes fasády `SEOMeta`, `OpenGraph`, `TwitterCard` a `JsonLd`, často v controlleru, s výchozími hodnotami z `config/seotools.php`. U modelů se nic neukládá, takže není co importovat z tabulky. Volání pro každý požadavek převedete na uložené nebo vypočítávané hodnoty.

| Volání artesaos/seotools | Ekvivalent v Rankbeamu |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` nebo `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` nebo `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` nebo `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | Odpovídající meta značka keywords neexistuje: hlavní klíčová slova slouží interním redakčním kontrolám. `saveSEO(['focus_keywords' => [...]])`; viz [audit](/cs/guide/audit). |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [Graf schématu JSON-LD](/cs/guide/schema) |
| Výchozí hodnoty `config/seotools.php` | Výchozí hodnoty webu v `config/seo.php` a [priority resolveru](/cs/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` v layoutu | `@seo($model)`; viz [Blade](/cs/guide/blade) |

Mění se celkový přístup: místo nastavování značek v každém controlleru uložíte data SEO jednou pro každý model do `seo_meta` a resolver Rankbeamu je vykreslí. Celowebové náhradní hodnoty z `config/seotools.php` se stanou [výchozími hodnotami konfigurace](/cs/reference/configuration) Rankbeamu. Statické stránky jednotlivých tras používají `@seoForRoute()`.

---

## Z balíčků Spatie {#from-spatie-packages}

Balíček `spatie/laravel-seo` pro ukládání metadat neexistuje, takže není co importovat. Balíčky Spatie používané se SEO jsou **doplňující generátory**. Můžete je ponechat nebo nahrazovat postupně:

- **`spatie/schema-org`** — generátor JSON-LD s řetězeným API. Rankbeam má vlastní [graf schématu](/cs/guide/schema) s typovanými generátory `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness` a `Organization`, které ukládají data do `seo_meta.schema_jsonld` a vykreslují je bez duplikátů. Pokud máte ručně sestavené objekty `spatie/schema-org`, předejte jejich výstup `->toArray()` do `saveSEO(['schema_jsonld' => $array])` nebo je přepište pomocí generátorů Rankbeamu.
- **`spatie/laravel-sitemap`** — generátor map webu. [Registr map webu](/cs/guide/sitemaps) Rankbeamu na něm staví. Můžete zaregistrovat modely jako zdroje a nechat Rankbeam vytvořit společnou mapu, nebo ponechat stávající mapu Spatie a vypnout trasu Rankbeamu.

(Pokud jste používali [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO), další generátor metadat založený na běhových hodnotách a strukturách, postupujte stejně jako u artesaos: volání `setTitle`/`addMeta` při každém požadavku převeďte na `saveSEO()` nebo vypočítávané gettery.)

---

## Rozšíření importéru {#extending-the-importer}

Za příkazem `seo:import-from` stojí malý registr implementací `Rankbeam\Seo\Importing\Contracts\Importer`. Nové zdroje tak lze přidávat bez změn příkazu. Vestavěné zdroje jsou `ralphjsmit` a importéry WordPressu: `wordpress-csv`, `yoast`, `rank-math`; viz [Migrace z WordPressu](/cs/guide/migrate-from-wordpress). Vlastní zdroj zaregistrujte v poskytovateli služeb:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```


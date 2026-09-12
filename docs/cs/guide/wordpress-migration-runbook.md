---
description: "Podrobný postup náhrady Yoastu či Rank Math na živém webu s omezením rizik. Importéry standardně doplňují prázdná pole, zkušební běhy nic nezapisují a WordPress zůstává beze změny."
---

# Postup migrace WordPress → Rankbeam {#wordpress-→-rankbeam-migration-runbook}

Podrobný postup nahrazení řešení SEO ve WordPressu, tedy Yoastu nebo Rank Math, Rankbeamem. Importéry ve výchozím nastavení doplňují prázdná cílová pole; `--overwrite` výslovně povoluje jejich nahrazení. Zkušební běhy nic nezapisují a zdrojová databáze WordPressu zůstává beze změny. Před importem zálohujte zdroj i cíl.

Tento provozní postup doplňuje [Migraci z WordPressu](/cs/guide/migrate-from-wordpress), která podrobně dokumentuje mapování polí, tokeny šablon a zdrojové klíče. Tam najdete *co* se převádí, zde *jak a v jakém pořadí*.

::: tip Co budete potřebovat
- **Core** (`rankbeam/laravel-seo`) pro import metadat a `seo:audit`.
- **Pro** (`rankbeam/laravel-seo-pro`) jen tehdy, pokud migrujete také **přesměrování**: tabulka `seo_redirects` je funkcí Pro.
- Obsah už modelovaný v Laravelu, například `App\Models\Post`, s traitem [`HasSEO`](/cs/guide/quickstart) a způsobem párování slugu WordPressu s modelem. Použijte klíč trasy modelu nebo sloupec určený pomocí `--match-by`.
:::

## Princip migrace {#the-shape-of-the-migration}

Řádky WordPressu identifikuje **URL nebo příspěvek**. Řádky `seo_meta` Rankbeamu jsou **polymorfní**, tedy připojené k modelu Eloquent. Import páruje každý řádek WordPressu s jedním z vašich modelů. Možné jsou tři výsledky a každý běh uvede jejich rozdělení:

| Výsledek | Význam | Další krok |
|---|---|---|
| **matched** | řádek se připojil k modelu a zapsalo se `seo_meta` | žádný |
| **url-only** | nenalezl se odpovídající model nebo nebylo zadáno `--model` | rozhodněte, zda stránka potřebuje model, nebo přesměrování |
| **unmapped** | řádek obsahoval data bez odpovídajícího místa v Core 3, především **autora** | přesuňte je na vhodné místo, například do hooku `getSEOAuthor()` |

---

## Krok 0 — Souběžný provoz (zatím bez přechodu) {#step-0-—-coexist-no-cutover-yet}

Zprovozněte Rankbeam **vedle** běžícího webu. Přidejte na modely trait `HasSEO` a vykreslujte značky přes fasádu nebo direktivu. WordPress ani jeho plugin SEO ale zatím **neodstraňujte**. V této fázi se nic neimportovalo a žádná operace není destruktivní. Jen ověřujete, že nové řešení naběhne.

Pokud při přechodu poskytujete novou aplikaci Laravel i původní WordPress ze stejného hostitele, ponechte je až do kroku 5 na oddělených cestách.

## Krok 1 — Import metadat (nejprve zkušebně) {#step-1-—-import-the-metadata-dry-run-first}

Vždy začněte s `--dry-run`. **Nic nezapíše** a vypíše úplný ověřovací report toho, co *by* nastalo.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

Užitečné volby; úplný seznam zobrazí `php artisan seo:import-from --help`:

| Volba | Účel |
|---|---|
| `--model=` | úplný název cílové třídy modelu; lze opakovat, ale importéry WordPressu připojují **jeden** model na běh, proto spouštějte zvlášť pro každý typ obsahu |
| `--match-by=` | sloupec modelu pro párování slugu; výchozí je klíč trasy |
| `--post-type=` | omezení databázových čteček na vybrané typy příspěvků; standardně `post` a `page` |
| `--connection=` | databázové připojení s tabulkami WordPressu |
| `--table=` | **prefix** tabulek WordPressu; standardně `wp_` |
| `--locale=` | jazyková verze, pro kterou se zapisují řádky `seo_meta` |
| `--redirects-csv=` | také vytvoří návrhy přesměrování v tomto souboru pro krok 3 |
| `--site-url=` | URL původního webu pro odvození cest z absolutních URL |
| `--overwrite` | nahradí existující neprázdné hodnoty v `seo_meta`; standardně **pouze doplňuje prázdná pole** |
| `--limit=` | omezí počet zdrojových řádků; vhodné pro první průchod |
| `--json` | strojově čitelný report |

Jakmile zkušební běh odpovídá očekávání, odeberte `--dry-run` a import proveďte:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

Import je **idempotentní** a ve výchozím nastavení **pouze doplňuje prázdná pole**. Bez volby pro přepis jej proto můžete opakovat, aniž by přepsal metadata už upravená v Rankbeamu.

## Krok 2 — Přečtěte a archivujte ověřovací report {#step-2-—-read-and-archive-the-verification-report}

Každý běh vypíše **Verification report**, tedy čísla, která schválíte před jakýmkoli odstraňováním. Uložte ho jako trvalý záznam:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

Co zkontrolovat:

- **matched** má odpovídat počtu stránek, u kterých očekáváte metadata SEO.
- **url-only** je seznam stránek bez odpovídajícího modelu. U každé rozhodněte, zda potřebuje model, přesměrování z kroku 3, nebo nic.
- **truncated** uvádí pole zkrácená na délku sloupce v `seo_meta`. Zkontrolujte příslušné titulky a popisy.
- **unmapped** uvádí zdrojová data bez sloupce v Core 3, **včetně každé odlišné hodnoty `author`**. Autoři se neukládají do sloupce, řeší je `getSEOAuthor()`. Report slouží k jejich záměrnému přesunu na vhodné místo, abyste ztrátu nezjistili až po měsících.

## Krok 3 — Import přesměrování do Pro {#step-3-—-import-the-redirects-into-pro}

Importér Core **nikdy nezapisuje do `seo_redirects`**, protože jde o tabulku Pro. Předá vám CSV s pevnou verzovanou strukturou: **formát CSV přesměrování v1**: `source_path,target_url,status_code,note`. Importujte ho do Pro, nejprve zkušebně:

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Každý řádek se validuje stejně jako formulář přesměrování ve Filamentu. Chybné řádky, neplatné stavové kódy, **nebezpečné externí cíle**, **duplicitní zdroje** a pravidla vytvářející **smyčku přesměrování** se přeskočí s uvedením důvodu, nikdy se tiše nezapíší. Zkušební běh validuje celý soubor včetně smyček a duplikátů a nic nezapisuje. Pro nahrazení cíle existujícího pravidla předejte `--overwrite`.

## Krok 4 — Ověření pomocí `seo:audit --strict` {#step-4-—-verify-with-seo-audit-strict}

Podmiňte migraci úspěchem bezplatného auditu uvnitř procesu. `--strict` skončí s nenulovým stavem, pokud má **kterákoli** stránka problém, takže může sloužit jako kontrola v CI i před přechodem:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

Audit pokrývá kontroly modelu a resolveru: přítomnost a délku titulku či popisu, obrázek OG, konflikty robots a formát kanonické adresy. Kontroly vykresleného HTML, živých kanonických cílů a skóre 0–100 jsou součástí [skenování Pro](/cs/pro/scan-issues). Pokud máte Pro, spusťte i to. Viz [Bezplatný SEO audit](/cs/guide/audit).

Potom namátkově zkontrolujte několik skutečných stránek v prohlížeči. Zobrazte zdroj a ověřte, že `<title>`, `<meta name="description">`, kanonická adresa, robots a značky OpenGraph vykreslují importované hodnoty.

## Krok 5 — Ověření PŘED odstraněním původního balíčku či tabulky {#step-5-—-verify-before-removing-the-legacy-package-table}

**Neodstraňujte** databázi WordPressu, plugin SEO ani původní balíček, dokud neplatí **všechny** následující body:

- [ ] Import proběhl pro **každý** typ obsahu, s jedním `--model` na běh.
- [ ] Archivovaný ověřovací report ukazuje očekávaný počet **matched** a žádné překvapivé řádky **url-only**.
- [ ] Každá potřebná hodnota **nenamapovaného autora** byla přesunuta na vhodné místo.
- [ ] Přesměrování byla importována do Pro pomocí `seo-pro:redirects-import` a několik starých URL se skutečně přesměruje stavem 301 na nové.
- [ ] `php artisan seo:audit --strict` skončí se stavem `0`.
- [ ] (Pro) `php artisan seo:doctor` nehlásí zbylou původní tabulku `seo` ani konflikt `config/seo.php`.
- [ ] Vykreslené stránky prošly namátkovou kontrolou v prohlížeči.

Protože importéry ve výchozím režimu pouze doplňují prázdná pole a jsou idempotentní, můžete před tímto ověřením opakovat krok 1 bez přepisování existujících hodnot, pokud nezapnete přepis. Původní data stále zůstávají ve WordPressu.

## Krok 6 — Vyřazení původního systému {#step-6-—-decommission}

Teprve po splnění seznamu z kroku 5 odstavte web WordPressu a potom odstraňte jeho databázi či tabulky a původní balíček SEO. Zálohu databáze ponechte, dokud si neověříte, že nové řešení na produkci správně funguje.

::: tip Návrat zpět
Kroky 1–4 ve výchozím režimu bez přepisu nic neodstraňují: do `seo_meta` se doplňují data, přesměrování jsou validovaná a vratná odstraněním pravidel a data WordPressu zůstávají beze změny. Při povoleném přepisu potřebujete pro obnovu původních cílových hodnot zálohu cíle. Před krokem 6 návrat znamená jednoduše *„dál poskytovat WordPress“*, po kroku 6 *„obnovit zálohu WordPressu“*.
:::

---

Přecházíte místo toho z balíčku SEO pro **Laravel**, například ralphjsmit, artesaos nebo Spatie? Viz [Migrace z jiných balíčků pro Laravel](/cs/guide/migrate-from-other-packages).

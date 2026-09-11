---
description: "Stapsgewijs en met beperkt risico Yoast of Rank Math vervangen op een actieve site. Importers vullen standaard lege velden, proefruns schrijven niets en WordPress blijft ongewijzigd."
---

# Migratiedraaiboek WordPress → Rankbeam {#wordpress-→-rankbeam-migration-runbook}

Een stapsgewijze procedure om een WordPress-SEO-stack met Yoast of Rank Math
te vervangen door Rankbeam. Importers vullen standaard lege doelvelden;
`--overwrite` staat vervanging expliciet toe. Proefruns schrijven niets en de
WordPress-brondatabase blijft ongewijzigd. Maak vóór het importeren een
back-up van zowel bron als doel.

Dit is het operationele vervolg op [Migreren vanaf WordPress](/nl/guide/migrate-from-wordpress),
dat de veldkoppeling, verwerking van templatetokens en bronsleutels in detail
beschrijft. Lees die pagina voor het *wat* en deze voor het *hoe, in de juiste volgorde*.

::: tip Wat je nodig hebt
- **Core** (`rankbeam/laravel-seo`) voor metadata-import en `seo:audit`.
- **Pro** (`rankbeam/laravel-seo-pro`) alleen als je ook **redirects** migreert:
  de tabel `seo_redirects` is een Pro-functie.
- Je content moet al in Laravel-modellen staan, bijvoorbeeld `App\Models\Post`,
  met de trait [`HasSEO`](/nl/guide/quickstart). Er moet ook een manier
  zijn om een WordPress-slug aan een model te koppelen: de routesleutel van
  het model of een kolom die je met `--match-by` opgeeft.
:::

## Hoe de migratie is opgebouwd {#the-shape-of-the-migration}

WordPress-rijen hebben een **URL of bericht** als sleutel. Rankbeam-rijen in
`seo_meta` zijn **polymorf** en gekoppeld aan een Eloquent-model. De import
koppelt elke WordPress-rij aan een van je modellen. Er zijn drie mogelijke
uitkomsten; elke uitvoering rapporteert de verdeling:

| Uitkomst | Betekenis | Actie |
|---|---|---|
| **matched** | de rij is aan een model gekoppeld; `seo_meta` is geschreven | geen |
| **url-only** | de rij kwam met geen enkel model overeen, of er is geen `--model` opgegeven | bepaal of die pagina een model of redirect nodig heeft |
| **unmapped** | de rij bevatte gegevens zonder plek in Core 3, vooral de **auteur** | breng die elders onder, bijvoorbeeld in een `getSEOAuthor()`-hook |

---

## Stap 0: naast elkaar draaien, nog niet omschakelen {#step-0-—-coexist-no-cutover-yet}

Zet Rankbeam **naast** de actieve site op. Voeg de trait `HasSEO` toe
aan je modellen en render tags via de facade of directive, maar verwijder
de WordPress-installatie of SEO-plugin **nog niet**. Er is op dit moment
niets geïmporteerd en niets destructiefs gebeurd: je controleert alleen of
de nieuwe stack opstart.

Als je de nieuwe Laravel-app en de oude WordPress-site tijdens de overgang
vanaf dezelfde host aanbiedt, houd ze dan tot stap 5 op afzonderlijke paden.

## Stap 1: metadata importeren, eerst een proefrun {#step-1-—-import-the-metadata-dry-run-first}

Begin altijd met `--dry-run`. Dit **schrijft niets** en toont het volledige
verificatierapport van wat er *zou* gebeuren.

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

Nuttige opties; voer `php artisan seo:import-from --help` uit voor de volledige lijst:

| Optie | Doel |
|---|---|
| `--model=` | de volledig gekwalificeerde klassenaam van het doelmodel (herhaalbaar; WordPress-importers koppelen **één** model per uitvoering, dus voer ze één keer per contenttype uit) |
| `--match-by=` | de modelkolom waarmee een slug wordt vergeleken (standaard de routesleutel) |
| `--post-type=` | beperk de databasereaders tot deze berichttypen (standaard `post` en `page`) |
| `--connection=` | de databaseverbinding met de WordPress-tabellen |
| `--table=` | het **voorvoegsel** van de WordPress-tabellen (standaard `wp_`) |
| `--locale=` | de locale waarvoor de `seo_meta`-rijen worden geschreven |
| `--redirects-csv=` | schrijf ook redirectvoorstellen naar dit bestand voor stap 3 |
| `--site-url=` | de oude site-URL, om paden uit absolute URL's af te leiden |
| `--overwrite` | vervang bestaande niet-lege `seo_meta` (standaard: **alleen lege velden vullen**) |
| `--limit=` | beperk het aantal bronrijen (handig voor een eerste ronde) |
| `--json` | machinaal leesbaar rapport |

Als de proefrun klopt, laat je `--dry-run` weg om de import toe te passen:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

De import is **idempotent** en **vult standaard alleen lege velden**.
Onder die standaardinstelling kun je hem veilig herhalen en kan hij
metadata die je al in Rankbeam hebt bewerkt niet overschrijven.

## Stap 2: het verificatierapport lezen en bewaren {#step-2-—-read-and-archive-the-verification-report}

Elke uitvoering toont een **Verification report (verificatierapport)**: de aantallen die je
controleert voordat je iets verwijdert. Bewaar het als duurzaam bewijs:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

Wat je moet controleren:

- **matched** moet gelijk zijn aan het verwachte aantal pagina's met SEO-metadata.
- **url-only** is je werklijst van pagina's zonder overeenkomend model.
  Bepaal per pagina of er een model, een redirect (stap 3) of niets nodig is.
- **truncated** vermeldt velden die zijn ingekort om in een `seo_meta`-kolom
  te passen. Controleer die titels en beschrijvingen.
- **unmapped** vermeldt brongegevens zonder kolom in Core 3, **met elke
  unieke `author`-waarde** expliciet genoemd. Auteurs worden niet als
  kolom opgeslagen, maar via `getSEOAuthor()` bepaald. Het rapport helpt je ze
  bewust elders onder te brengen, zodat je het verlies niet pas maanden later ontdekt.

## Stap 3: de redirects in Pro importeren {#step-3-—-import-the-redirects-into-pro}

De Core-importer **schrijft nooit naar `seo_redirects`**: dat is een Pro-tabel.
Je krijgt een CSV met een vaste structuur en versienummer:
**redirect-CSV-formaat v1**, `source_path,target_url,status_code,note`. Begin ook bij het importeren in Pro
met een proefrun:

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Elke rij wordt op dezelfde manier gevalideerd als het Filament-redirectformulier.
Ongeldige rijen, ongeldige statuscodes, **onveilige externe doelen**,
**dubbele bronnen** en regels die een **redirectlus** zouden vormen, worden
met een reden overgeslagen en nooit stilzwijgend geschreven. De proefrun
valideert het hele bestand, inclusief lussen en duplicaten, en schrijft
niets. Geef `--overwrite` mee om het doel van een bestaande regel te vervangen.

## Stap 4: controleren met `seo:audit --strict` {#step-4-—-verify-with-seo-audit-strict}

Laat de gratis audit binnen het applicatieproces bepalen of de migratie
door mag gaan. `--strict` geeft een niet-nul-exitcode als **een pagina**
een bevinding heeft en is dus bruikbaar als controle in CI of vóór het omschakelen:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

De audit dekt controles op het model en de resolver: aanwezigheid en lengte
van titel/beschrijving, OG-afbeelding, robots-conflicten en notatie van de
canonieke URL. Controles op gerenderde HTML en live canonieke URL's, en de
score van 0–100, horen bij de [Pro-scan](/nl/pro/scan-issues). Voer die ook uit
als je Pro hebt. Zie [Gratis SEO-audit](/nl/guide/audit).

Controleer daarna een aantal echte pagina's in de browser. Bekijk de
broncode en bevestig dat `<title>`, `<meta name="description">`, de canonieke URL,
robots en OpenGraph-tags de geïmporteerde waarden weergeven.

## Stap 5: controleren VÓÓR het oude pakket of de tabel wordt verwijderd {#step-5-—-verify-before-removing-the-legacy-package-table}

Verwijder de WordPress-database, SEO-plugin of het oude pakket **niet**
voordat aan **alle** onderstaande voorwaarden is voldaan:

- [ ] De import is uitgevoerd voor **elk** contenttype, met één `--model` per uitvoering.
- [ ] Het bewaarde verificatierapport toont het verwachte aantal **matched**
      en geen onverwachte **url-only**-rijen.
- [ ] Elke relevante **unmapped author**-waarde is elders ondergebracht.
- [ ] Redirects zijn in Pro geïmporteerd (`seo-pro:redirects-import`) en enkele oude
      URL's sturen daadwerkelijk met een 301 door naar de nieuwe.
- [ ] `php artisan seo:audit --strict` sluit af met `0`.
- [ ] (Pro) `php artisan seo:doctor` meldt geen achtergebleven oude `seo`-tabel
      en geen conflict met `config/seo.php`.
- [ ] Gerenderde pagina's zijn steekproefsgewijs in de browser gecontroleerd.

Omdat de importers idempotent zijn en standaard alleen lege velden vullen,
kun je stap 1 onder die voorwaarden vóór deze controle op elk moment veilig
herhalen. De oude gegevens staan nog in WordPress.

## Stap 6: uitfaseren {#step-6-—-decommission}

Pas nadat de checklist van stap 5 is geslaagd, haal je de WordPress-site
offline en verwijder je de database/tabellen en het oude SEO-pakket.
Bewaar een databaseback-up totdat je zeker weet dat de nieuwe stack in
productie correct werkt.

::: tip Terugdraaien
In de standaardwerkwijze van stappen 1–4 wordt niets vernietigd:
`seo_meta` voegt gegevens toe, redirects worden gevalideerd en zijn
terug te draaien door de regels te verwijderen, en de WordPress-gegevens
blijven ongewijzigd. Vóór stap 6 betekent terugdraaien simpelweg
*WordPress blijven aanbieden*; na stap 6 betekent het *de WordPress-back-up herstellen*.
:::

---

Kom je van een **Laravel**-SEO-pakket zoals ralphjsmit, artesaos of Spatie?
Zie [Migreren vanaf andere Laravel-pakketten](/nl/guide/migrate-from-other-packages).

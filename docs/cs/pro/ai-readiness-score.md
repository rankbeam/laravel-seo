---
description: "Druhé deterministické skóre vedle skóre SEO: měřítko technické připravenosti pro AI definované Rankbeamem, které hodnotí dostupnost a čitelnost pro roboty odděleně od SEO."
---

# Skóre připravenosti pro AI — druhá deterministická osa {#the-ai-readiness-score-—-a-second-deterministic-axis}

Sken Pro přidává každé stránce vedle [skóre SEO](/cs/pro/scoring) druhé číslo: **skóre připravenosti pro AI od 0 do 100**. Odpovídá na jinou otázku: *mohou roboti AI a systémy odpovídající na dotazy tento obsah načíst, přečíst a přiřadit jeho původ?* **Nikdy se neslučuje se skóre organického SEO.** Jde o dvě samostatné osy, každou s vlastními pravidly hodnocení, verzí a sloupcem.

::: warning Co číslo znamená a co ne
Skóre připravenosti pro AI je **deterministické měřítko technické kompatibility definované Rankbeamem**: zkoumá, zda jsou signály zjišťované při procházení stránky přítomné a správně sestavené. **Nepředpovídá** umístění, indexaci, zařazení ani citování ve vyhledávači či systému AI a žádné skóre takový výsledek nezaručuje. Kontrola `air_llms_txt` přiděluje body za **volitelný** soubor kompatibility `llms.txt` pro nástroje, které jej používají. Vyhledávání Google jej nevyžaduje a nejde o hodnoticí signál pro pořadí výsledků.
:::

Stejně jako skóre SEO je **plně deterministické a reprodukovatelné**: každý bod lze dohledat ke konkrétní pojmenované kontrole při procházení a stejné signály vždy vytvoří stejné číslo. **Hodnocení nikde nevolá AI.** Právě v tom je smysl: jde o transparentní, auditovatelné měření, nikoli o vzorkování odpovědí LLM používané službami SaaS pro „viditelnost v AI“.

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip Dvě osy, nikdy sloučené
`AI-readiness: 74/100` stojí vedle `SEO: 82/100`; jedna druhou nemění. Číslo připravenosti pro AI má vlastní sloupce `ai_readiness_*` na řádku `seo_scan_results` v Pro. Stejně jako u skóre SEO je **číselné skóre funkcí Pro**. Bezplatný Core [`seo:audit`](/cs/guide/audit) žádné číslo nevypisuje.
:::

## Přičítání bodů místo penalizací {#additive-credit-not-penalty}

[Skóre SEO](/cs/pro/scoring) začíná na 100 a penalizace *odečítá*. Připravenost pro AI postupuje opačně: začíná na **0** a **přičítá** celou váhu každé kontroly nebo její část. Připravenost web postupně získává, takže web bez signálů pro AI poctivě skončí poblíž 0, nikoli na „100 minus pár bodů“. Váhy dávají dohromady přesně **100**.

## Pravidla hodnocení {#the-rubric}

Skóre se počítá podle **zveřejněných pravidel s verzí**, `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric`, tvořených deseti kontrolami ve čtyřech kategoriích:

### A · Přístup a řízení botů — 30 bodů {#a-·-bot-access-control-—-30-points}

Mohou se k vám roboti vyhledávání AI a asistenti skutečně dostat? Vyhodnocení používá **poskytovaný `/robots.txt`** webu pro **vlastní cestu skenované stránky**. Stránka pod `Disallow: /section` je zakázaná, i když je kořen otevřený. robots.txt dává pokyny spolupracujícím robotům, neblokuje síťový přístup. Kontrola používá rozdělení účelů z [katalogu robotů AI](/cs/guide/ai-crawlers): trénování, vyhledávání a asistenti.

| Kontrola | Váha | Body |
|---|---|---|
| `air_robots_reachable` — `robots.txt` je poskytovaný a čitelný | 6 | přítomný / chybí |
| `air_ai_search_access` — roboti **vyhledávání** AI, zdroj odkazované návštěvnosti, smějí na web | 10 | podle povoleného podílu |
| `air_ai_assistant_access` — roboti **asistentů** AI smějí na web | 8 | podle povoleného podílu |
| `air_explicit_ai_policy` — výslovné pravidlo `robots.txt` pro známého bota AI | 6 | přítomné / chybí |

::: tip Zákaz trénovacích botů neznamená menší připravenost
Zakázat trénovací boty jako GPTBot nebo CCBot je legitimní volba, proto **nikdy** nepřináší penalizaci. Trénování získává body jen přes `air_explicit_ai_policy`, tedy záměrné výslovné stanovisko. Web, který zakáže trénování, ale povolí roboty vyhledávání a asistentů, může v této kategorii získat plný počet.
:::

### B · Dohledatelnost — 20 bodů {#b-·-discoverability-—-20-points}

| Kontrola | Váha | Body |
|---|---|---|
| `air_sitemap_discoverable` — XML mapa webu je dostupná **a** odkazuje na ni direktiva `Sitemap:` | 12 | obojí / jedno / nic |
| `air_llms_txt` — poskytuje se platný `/llms.txt`, s nadpisem a odkazy | 8 | platný / přítomný / chybí |

### C · Strojově čitelný obsah — 22 bodů {#c-·-machine-readable-content-—-22-points}

| Kontrola | Váha | Body |
|---|---|---|
| `air_server_rendered_content` — podstatný text je přítomný v HTML vykresleném serverem; obsah existuje bez spuštění JS | 14 | podle počtu slov |
| `air_markdown_twin` — přes vyjednávání obsahu se poskytuje markdownová verze stránky | 8 | přítomná / chybí |

### D · Strukturovaná data a připravenost pro odpovědi — 28 bodů {#d-·-structured-data-answer-readiness-—-28-points}

| Kontrola | Váha | Body |
|---|---|---|
| `air_schema_completeness` — přítomné JSON-LD, typ hlavní entity, autorství a datum; u článků autor a datum | 18 | úplné / částečné / žádné |
| `air_answer_structure` — prvky usnadňující získání odpovědi: schéma FAQ/QA/HowTo, hierarchie nadpisů, seznamy a stručný úvod | 10 | podle počtu prvků |

Každá kontrola přidělí **plné**, **částečné** nebo **žádné** body, případně se **přeskočí**, pokud nebylo možné získat potřebný signál. Příkladem je kontrola stránky u cíle skenovaného bez načtení stránky. Přeskočená kontrola dostane 0, ale je označená, takže signál, který *nešlo ověřit*, se nikdy nevydává za potvrzenou nepřítomnost.

### Dosah bezplatného auditu {#free-audit-reach}

Úplnost schématu, `air_schema_completeness`, lze vyhodnotit ze strukturovaných dat modelu bez načítání stránky. Stejný postup už používá bezplatný audit pro zjištění o připravenosti pro odpovědi. Zbylých devět kontrol potřebuje procházení, proto je celé číselné skóre záležitostí **skenu Pro**.

## Vymezení rozsahu — co tato osa nezahrnuje {#honest-scope-—-what-this-axis-excludes}

Tato osa hodnotí **deterministické signály obsahu**. Nezahrnuje kontroly infrastruktury agentů týkající se běžící aplikace nebo DNS:

| Nezahrnuto | Důvod |
|---|---|
| **DNS-AID** (DNS záznamy pro objevování agentů) | Infrastruktura DNS / DNSSEC, nikoli vlastnost poskytované stránky. |
| **Web Bot Auth** (podepisování jednotlivých požadavků) | Interaktivní kryptografické ověření, nikoli statický obsah. |
| **Objevování protokolů** (API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills, WebMCP…) | Vyžadují běžící aplikaci / API / server MCP. |
| **Obchodování** (x402, MPP, UCP, ACP) | Platební infrastruktura agentů; obsahový web nemá co účtovat. |

Zahrnuje úplnost entit schématu a strukturu bloků odpovědí: signály obsahu popisující uspořádání a původ, bez záruky, že je vyhledávač nebo odpovídající systém použije.

## Verze — historická skóre se nikdy tiše nemění {#versioning-—-historical-scores-never-silently-change}

Každé uložené skóre připravenosti pro AI nese `AiReadinessRubric::VERSION`, podle které vzniklo, `ai_readiness_version`. Změna sady kontrol, váhy nebo způsobu přidělování bodů je změnou pravidel a **zvyšuje verzi**. Uložené číslo tak vždy zaznamená pravidla, která jej vysvětlují, a historické hodnoty zůstanou porovnatelné. Skóre se **ukládá, nepřepočítává při čtení**. Prahy ovlivňující skóre, například počet slov či prvků pro získání odpovědi, jsou konstanty v kódu navázané na verzi, nikdy konfigurace. Nastavení tedy nemůže tiše posunout zveřejněné číslo.

::: warning Jeden vstup, který verze nezafixuje
Kontroly přístupu botů čtou **aktuální** [katalog robotů AI](/cs/guide/ai-crawlers) z Core. Aktualizace katalogu, nový bot nebo změna zařazení jeho účelu, fakticky mění vstupy a může posunout dvě dílčí skóre přístupu bez zvýšení `AiReadinessRubric::VERSION`. Verze sleduje *pravidla*, nikoli katalog. Je to záměrné: pro kontrolu je užitečnější současný skutečný seznam než zmrazený. Pro přesné historické porovnání zaznamenejte a zafixujte vedle verze pravidel i verzi balíčku Core.
:::

## Kde se ukládá {#where-it-s-stored}

Každý sken vloží nebo aktualizuje sloupce připravenosti pro AI na **stejném** řádku `seo_scan_results` jako skóre SEO:

| Sloupec | Obsah |
|---|---|
| `ai_readiness_score` | Číslo od 0 do 100; null, dokud cíl neprojde skenem se zapnutou osou. |
| `ai_readiness_version` | Pravidla, podle kterých vzniklo. |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]` — úplný záznam vyhodnocení. |

Průměr běhu se při dokončení uloží do `seo_scan_runs.avg_ai_readiness` obdobně jako `avg_score`. Tvoří vývoj připravenosti pro AI.

## Čtení skóre {#reading-the-score}

**Bez panelu** — poslední výsledek modelu obsahuje obě osy:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament** — přidejte doprovodný sloupec vedle skóre SEO do libovolné tabulky resource:

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

Skóre se zobrazí i jako doprovodný štítek na kartě skóre stránky nad polem titulku SEO a jako vlastní část [reportu s vaší značkou](/cs/pro/reports) v PDF i e-mailu: číslo, známka, změna proti předchozímu reportu a vývoj po skenech. Vždy stojí vedle organického skóre, nikdy se do něj neslučuje.

### Pásma známek {#grade-bands}

Písmenová známka slouží k prezentaci a odvozuje se z čísla; závazné je číslo. Pro konzistenci používá stejná pásma jako skóre SEO:

| Skóre | Známka |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Konfigurace {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

Kontroly a váhy **nelze konfigurovat**. Pro danou `ai_readiness_version` musí být skóre deterministické napříč všemi instalacemi. Změna výpočtu proto mění pravidla v kódu, nikoli nastavení.

::: warning Zjišťování signálů webu používá požadavek uvnitř procesu
Pro cíl na stejném hostiteli sken vyhodnocuje `/robots.txt`, `/llms.txt` a stránku přes HTTP kernel Laravelu uvnitř procesu, stejně jako zbytek skenu. `robots.txt` nebo `llms.txt` poskytované jako **statické soubory**, mimo směrování Laravelu, neuvidí. Aby se započítaly, poskytujte je přes trasy balíčku, což je doporučené nastavení.
:::

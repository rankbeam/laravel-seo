---
description: "Volitelná asistence AI s vlastním klíčem: návrhy titulků a popisů, srozumitelná vysvětlení problémů skenu, přepsání jedním kliknutím a návrhy schema.org. Standardně vypnuto."
---

# Asistence AI {#ai-assist}

Volitelná asistence AI **s vlastním klíčem**: návrhy titulků a meta popisů, srozumitelná vysvětlení problémů skenu, **přepsání popisu** jedním kliknutím a **návrh strukturovaných dat schema.org**. Je **standardně vypnutá**. Při vypnutém přepínači se žádný kód AI vůbec nespouští.

Funkce se řídí třemi zásadami:

- **Váš klíč, váš poskytovatel.** Požadavky jdou z *vašeho serveru* přímo k poskytovateli, kterého nastavíte: Anthropic, OpenAI, Google nebo místní server či server kompatibilní s OpenAI. Případné poplatky jdou na váš účet. Nic nezprostředkováváme, neměříme pro účtování ani nepřeprodáváme a balíček nikam neposílá telemetrii.
- **Interaktivní návrhy vyžadují výslovné přijetí.** Výběr návrhu vyplní formulář; opravy z přehledu vyžadují použití. Od Pro 2.42 hromadné CLI standardně ukládá soukromé návrhy. `--auto-apply` použijte pouze pro záměrný okamžitý zápis.
- **Vždy bez fatálního dopadu.** Chybějící či neplatný klíč, vyčerpaný účet, omezení četnosti nebo časový limit vytvoří zprávu přímo v rozhraní. Nikdy nezablokují ukládání, vykreslování ani skenování.

## Přehled poskytovatelů {#providers-at-a-glance}

Vybírejte podle přístupu k účtu, požadavků na data a nákladů. Všechny čtyři integrace nabízejí stejné úlohy, ale podpora modelů, formát výstupu, rychlost a kvalita se mohou lišit.

| Poskytovatel | Dodaný výchozí model | Strukturovaný výstup | Ilustrační náklady | Použití |
|---|---|---|---|---|
| **Místní** (Ollama / LM Studio / vLLM) | `llama3.1`, nastavte vlastní | Podle možností serveru, `response_format` | **Poplatek za API 0 USD** při vlastním provozu; náklady infrastruktury zůstávají | Kontrola cíle odesílaných dat |
| **OpenAI** | `gpt-5.5` | Structured Outputs, pokud je zvolený model podporuje | Přibližně 0,005 USD za návrh při předpokladech níže | Existující účet OpenAI |
| **Anthropic** | `claude-opus-4-8` | `output_config.format`, pokud je podporováno | Přibližně 0,015 USD za návrh při stejných předpokladech | Existující účet Anthropic |
| **Google** | `gemini-2.5-flash` | `responseSchema`, pokud je podporováno | Přibližně 0,0005 USD za návrh při stejných předpokladech | Účet Google; ověřte kvóty a ceny modelu |

Názvy popisují dodanou konfiguraci, nikoli zaručenou současnou dostupnost na vašem účtu. Náklady používají ukázkové předpoklady balíčku, nikoli ověřené aktuální ceny. Chování integrace a pozorování ze zveřejněných zkoušek:

- **Strukturovaný výstup.** Podporované cesty OpenAI, Google a Anthropic dostávají schéma JSON; neplatné odpovědi skončí řádnou chybou. Místní servery dostávají `response_format` podle svých možností. Pokud jej ignorují, tolerantní parsování vrátí platný seznam nebo chybu, bez použití částečného výstupu.
- **Uvažování mění spotřebu tokenů.** Popsaná zkouška Gemini spotřebovala přibližně 500 skrytých tokenů uvažování a 100 viditelných tokenů na popis. Testovaná volání Anthropic nehlásila skryté tokeny uvažování. Není to univerzální vlastnost těchto rodin modelů. Skryté tokeny mohou být účtovány jako výstup, proto níže existuje minimální rozpočet pro uvažování.
- **Modely lze nastavit.** Nastavte `SEO_PRO_AI_MODEL` na dostupný model kompatibilní s API a parametry adaptéru. Příklady jsou `claude-haiku-4-5`, `gpt-5.4-mini` a `gemma-3-12b-it`. Než model použijete na celou kolekci, ověřte podporu a kvalitu výstupu.

## Nastavení {#setup}

Zapněte funkci a vložte klíč poskytovatele do prostředí. Cloudového poskytovatele změníte úpravou poskytovatele a klíče; zkontrolujte i případné přepsání modelu. Místní adaptér potřebuje také URL serveru.

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip API klíč poskytovatele je oddělený od předplatného Claude / ChatGPT
**Předplatné** Claude Code, Claude.ai nebo ChatGPT nefinancuje **API**. `SEO_PRO_AI_API_KEY` musí být *API klíč účtovaný podle spotřeby* z vývojářské konzole poskytovatele nebo klíč Google AI Studio, s vlastními prostředky či kvótou. Účet pouze s předplatným nebo bez prostředků se může autentizovat, ale vrátí chybu **vyčerpaného kreditu či kvóty**; viz [Řešení potíží](#troubleshooting).
:::

Konfigurační soubor `config/seo-pro.php` v bloku `ai` nabízí `timeout`, `max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models` a `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model`, levnější model pro hromadné doplňování, viz [Náklady](#cheaper-bulk-generation), dále `retry`, tabulku `pricing` a podblok `local`. Vše popisují [limity a ladění](#limits-and-tuning).

::: warning Klíče s konfigurací v mezipaměti
Konfigurace ukládá pouze **název** proměnné prostředí, `api_key_env`, nikdy klíč. `php artisan config:cache` proto nikdy nezapíše klíč do `bootstrap/cache/config.php`. Druhá strana: při konfiguraci v mezipaměti se `.env` nenačítá, proto nastavte `SEO_PRO_AI_API_KEY` jako skutečnou proměnnou prostředí serveru.
:::

## Místní inference a cloudové možnosti {#running-at-0-and-the-cheapest-paid-option}

- **Vlastní provoz inference nemá poplatek poskytovatele za tokeny API.** Hardware, elektřina a provoz stále něco stojí. Obsah zůstává ve vaší síti jen tehdy, pokud tam zůstává nastavený inferenční server i jeho závislosti.
- **Google má kvóty a ceny podle modelu a úrovně služby.** Klíč AI Studio, `aistudio.google.com/apikey` ve formátu `AIza…`, může umožnit zkoušky v bezplatné úrovni. Než podle potřeby zapnete účtování, ověřte, zda limity vyhovují vaší zátěži. Dodaný výchozí model je `gemini-2.5-flash`. Modely Gemini a Gemma nemají všechny stejné možnosti uvažování. `reasoning_models` používá nastavené vzory názvů, nejde o test schopností.

Pro určení místa inference:

- **Místní / kompatibilní s OpenAI.** Použijte `provider=local` se serverem kompatibilním s OpenAI Chat Completions, například **Ollama**, **LM Studio**, **vLLM** nebo **LocalAI**, či vzdálenou bránou jako **OpenRouter**. Nastavte `SEO_PRO_AI_LOCAL_BASE_URL` na kořen API, ke kterému se připojí `/chat/completions`, a zvolte dostupný `SEO_PRO_AI_MODEL`. Vzdálená brána přijímá data mimo vaši síť a může účtovat poplatky. Název adaptéru `local` neznamená místní inferenci.

::: warning Místní `base_url` se ověřuje — localhost povolte výslovně
`base_url` je privilegované nastavení ověřované přes stejný `SsrfGuard` jako ostatní odchozí načtení: jen http/https, bez uživatelských údajů v URL a standardně musí odpovídat **veřejné** adrese. Chybné či nepřátelské `base_url` tak nelze použít k sondování interních služeb. Skutečně místní server běží na `127.0.0.1`, soukromé adrese, proto potřebuje výslovné `seo-pro.ai.local.allow_local_addresses`, `SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`. Pro veřejnou bránu OpenRouter je ponechte vypnuté. Cesta požadavku je pevná a přesměrování se nikdy nenásledují, takže klíč nelze odklonit k jinému hostiteli.
:::

::: tip Řízení uvažování u podporovaných modelů Ollama
Místní model s uvažováním může překročit výchozí časový limit. Pokud model a verze serveru tuto možnost podporují, `['think' => false]` v `seo-pro.ai.local.extra_body` může uvažování pro návrhy vypnout. Podpora se liší; ověřte [dokumentaci Ollama](https://docs.ollama.com/capabilities/thinking). Podle potřeby zvyšte `seo-pro.ai.timeout`. Balíček neposílá `temperature`, protože jej některé modely odmítají.
:::

## Náklady {#cost}

Balíček nepřidává přirážku. Platíte přímo poskytovateli; vlastní inference nemá poplatek poskytovatele za API, ale náklady infrastruktury zůstávají. Důležité jsou dvě hodnoty: cena **jednoho návrhu** při interaktivním použití a cena **hromadného doplnění** celé kolekce.

Tabulka `seo-pro.ai.pricing`, USD za 1 000 000 tokenů, převádí odhad tokenů na částku v dolarech zobrazenou při potvrzení hromadného doplňování. Jde o **dodané předpoklady odhadu**, nikoli ověřené aktuální veřejné ceny. **Nahraďte je současnými zveřejněnými cenami svého poskytovatele**, chcete-li přesný odhad:

| Vzor modelu | Vstup $/1M | Výstup $/1M |
|---|---|---|
| `claude-opus-*` | 15,00 | 75,00 |
| `claude-sonnet-*` | 3,00 | 15,00 |
| `claude-haiku-*` | 1,00 | 5,00 |
| `gpt-5*mini*` | 0,50 | 1,50 |
| `gpt-5*` | 5,00 | 15,00 |
| `gemini-2.5-pro*` | 1,25 | 10,00 |
| `gemini-*flash*` | 0,15 | 0,60 |

Zveřejněná ukázka používá spotřebu tokenů testované stránky pro jeden titulek a jeden popis s těmito předpoklady. Poslední sloupec u podporovaných adaptérů uplatní ukázkovou 50% slevu dávky. Nejde o nabídku aktuálních cen.

| Poskytovatel / model | Přibližně za dvojici návrhů | Přibližně za 1 000 záznamů, hromadně | Přibližně za 1 000 záznamů, `--batch` |
|---|---|---|---|
| Místní `gemma`/`llama`, Ollama | **Poplatek za API 0 USD** | **Poplatek za API 0 USD** | Není implementováno adaptérem |
| Google `gemini-2.5-flash`, placený | Přibližně 0,001 USD | Přibližně 0,40 USD | Není implementováno adaptérem Rankbeam |
| OpenAI `gpt-5.5` | Přibližně 0,008 USD | Přibližně 5,25 USD | **Přibližně 2,63 USD**, sleva 50 % |
| Anthropic `claude-opus-4-8` | Přibližně 0,03 USD | Přibližně 20 USD | **Přibližně 10 USD**, sleva 50 % |

Příkaz označuje odhad přibližně ±50 %, ale **nejde o výdajový strop ani zaručené rozmezí chyby**. Skutečné vstupy, výstupy a ceny celkovou částku mění. Odhad modeluje viditelný výstup; účtované skryté uvažování může náklady zvýšit nad něj. Modely bez cenového záznamu ukazují jen odhad tokenů.

### Levnější hromadné generování {#cheaper-bulk-generation}

Nastavte `seo-pro.ai.bulk_model`, `SEO_PRO_AI_BULK_MODEL`, pro jiný model **jen při hromadném doplňování**, `seo-pro:ai-fill` / `SeoPro::aiFill()`. Filament a `seo-pro:ai-suggest` ponechají `model`. Při null používá hromadné doplňování také `model`. Odhad použije cenový vzor zvoleného modelu. Před zvýšením objemu vyhodnoťte reprezentativní výstupy; levnější model není automaticky vhodný.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Příklady balíčku používají **anthropic** `claude-haiku-4-5`, **openai** `gpt-5.5-mini`, **google** `gemini-2.5-flash` nebo menší **místní** model. Cenový vzor může odpovídat názvu, i když jej poskytovatel nenabízí. Před nastavením ověřte skutečné ID modelu, kompatibilitu API a cenu.

**Doplnění 100 stránek**, každé bez titulku i popisu, tedy 200 volání poskytovatele, při dodaných výchozích cenách `pricing` a modelu tokenů odhadu, 600 vstupních a 150 výstupních tokenů na volání: kvalitnější model proti levnějšímu `bulk_model`.

| Poskytovatel | Kvalitnější model — 100 stránek | Levnější `bulk_model` — 100 stránek |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **4,05 USD** | `claude-haiku-4-5` ≈ **0,27 USD** |
| **OpenAI** | `gpt-5.5` ≈ **1,05 USD** | `gpt-5.5-mini` ≈ **0,11 USD** |
| **Google** | `gemini-2.5-pro` ≈ **0,45 USD** | `gemini-2.5-flash` ≈ **0,04 USD** |
| **Místní** (Ollama / vLLM) | Libovolný model — **poplatek za API 0 USD** | Libovolný model — **poplatek za API 0 USD** |

Jde o ilustrační odhady bez zaručeného rozmezí ±50 % a bez rezervy pro skryté uvažování. Než na odhad spolehnete, aktualizujte `seo-pro.ai.pricing` podle zveřejněných cen zvoleného poskytovatele.

## Jazyk výstupu {#output-language}

Každý prompt uvádí jazyk stránky a kód BCP-47, například *„v brazilské portugalštině (pt-BR), jazyce stránky, bez ohledu na jiné jazyky v úryvku“*. Kontext stránky odeslaný modelu obsahuje řádek `Language:`, od Pro 2.34. Dřívější prompty říkaly „ve stejném jazyce jako zdrojový obsah“, takže model musel hádat z krátkého nebo jazykově smíšeného úryvku. Turecká stránka s anglickým názvem značky mohla dostat anglický výstup. Používá se národní prostředí, ve kterém byla vyhodnocena metadata stránky, případně prostředí aplikace, pokud stránka vlastní nemá. Stejné prostředí určuje [limit délky](/cs/guide/multilingual#title-and-description-budgets-per-script), takže japonská stránka žádá přibližně 30znakové titulky *v japonštině*.

Od Pro 2.36 řídí výslovné národní prostředí obsahu současně řádek metadat, hooky obsahu a jazyk promptu. Jazyk rozhraní obsluhy se nemění.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Stávající poziční argumenty se nemění. Vynechte `locale:` pro výchozí `seoData()` modelu, aby samostatné překladové modely mohly deklarovat svůj jazyk. Úryvek používá `getContentForSEO()`, pokud vrátí neprázdný obsah, jinak nastavená pole obsahu. Filament 1.11 předává národní prostředí vybrané karty automaticky včetně jednojazyčného režimu a přepínače stránky.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

Hromadné `plan()`, `fill()` a `submitBatchFill()` přijímají také závěrečné `locale:`. Použijte stejné národní prostředí při vytvoření `FillProgress(..., locale: 'it')` i odeslání běhu. Každá položka dávky zaznamená jazyk obsahu; převzetí výsledků použije uložený jazyk a před zápisem znovu ověří jeho řádek metadat. Běhy s výslovným prostředím mají samostatné soubory checkpointů a značky zpracování rozlišují jazyky. Pro převzetí výsledků spusťte stejný příkaz znovu. Vlastní úlohy mají jazyk obsahu serializovat a předat.

Checkpointy před Pro 2.36 jazyk obsahu nezaznamenávaly. Nedokončená stará dávka se zachová a automatické převzetí se odmítne. Než ji zahodíte přes `--fresh`, porovnejte výsledky poskytovatele se zamýšleným jazykem; nové odeslání by jinak mohlo zpoplatnit tutéž práci znovu. Také starý sekvenční checkpoint se zpracovanými záznamy potřebuje před resetem sladit.

### Vyhodnocení pro jednotlivé jazyky {#per-language-evaluation}

Zdrojový repozitář Pro obsahuje 170 vstupních stránek pro 17 národních prostředí a výslovně zapínané vyhodnocovací nástroje. Vstupní stránky mají strukturální a heuristické kontroly základního jazyka; nezávislé schválení rodilými mluvčími zůstává otevřené.

Nástroje kontrolují **titulky i popisy**, zaznamenávají délky v grafémech a důkazy jazyka a písma a uchovají každou odpověď poskytovatele před asercemi. Krátké titulky, smíšený text a sdílené čínské či japonské znaky mohou zůstat neurčité. Odhad portugalštiny nepotvrzuje brazilskou variantu a dílčí kontroly čínských znaků necertifikují regionální kvalitu psaní.

Živé běhy vyžadují `SEO_PRO_AI_EVAL=1`, výslovný výběr `SEO_PRO_AI_EVAL_LOCALES` a ID `SEO_PRO_AI_EVAL_RUN`. Mohou vytvářet poplatky poskytovatele; standardně neběží žádný. Každý běh váže důkazy na poskytovatele, požadovaný i vrácený model, hashe vstupů, požadavků a kódu a časové údaje. Neúspěšné pokusy zůstávají dostupné. Pokračování znovu použije uložené odpovědi; přerušený požadavek vyžaduje výslovné opakování, protože už mohl dorazit poskytovateli.

Důkazy se ukládají pod `storage/app/seo-ai-evals/<run-id>/` v testovacím prostředí zdrojů. `README.md` testovacích dat popisuje verzované schéma a příkazy. Hodnocení rodilých recenzentů se zaznamenává samostatně a váže se na přesné hashe výstupů. Úspěšná automatická kontrola není rodilým schválením ani zárukou publikovatelného textu.

## Limity a ladění {#limits-and-tuning}

Všechny volby jsou v bloku `ai` souboru `config/seo-pro.php`:

- **`timeout`**, standardně `15` sekund, prostředí `SEO_PRO_AI_TIMEOUT` — také limit synchronního volání při otevírání modálního okna návrhů Filamentu, proto je kvůli použitelnosti krátký. **Pomalý model s uvažováním nebo místní model může překročit 15 sekund**. V takovém případě jej zvyšte přes `SEO_PRO_AI_TIMEOUT`; viz také tip Ollama `think => false` výše. Časový limit nikdy není fatální: zobrazí chybu v rozhraní, nezablokuje uložení.
- **`max_input_chars`**, standardně `6000` — omezuje náklady i rozsah předávaných dat tím, kolik obsahu stránky se posílá v požadavku jako prostý text bez HTML.
- **`max_output_tokens`**, standardně `1000` — základní limit generovaných tokenů. Odpověď, která jej dosáhne, vrátí odlišnou chybu `truncated`, nikdy tiše poloviční odpověď.
- **`token_budgets`** — výstupní limity podle úloh: `suggestions` 800, `explanation` 600, `rewrite` 300 a `schema_suggestion` 700. Žádná nepotřebuje celý výchozí limit, ale navíc se uplatní minimum pro uvažování.
- **`reasoning_models`** a **`reasoning_min_output_tokens`**, standardně `2000` — modelu, jehož název odpovídá vzoru `*gemma*`, `gemini-2.5-*` či `o1*`/`o3*`/`o4*`, se výstupní rozpočet zvýší na toto minimum. Model s uvažováním spotřebuje skryté tokeny před viditelným výstupem a malý rozpočet by jej usekl.
- **`suggestion_count`**, standardně `3` — kolik alternativ titulku a popisu požadovat.
- **`retry`** — automatické opakování jen *přechodných* chyb; viz [Zpracování odpovědí](#how-replies-are-handled).

## Ve Filamentu {#in-filament}

S nainstalovanými volitelnými balíčky Filament, `rankbeam/laravel-seo-filament` >= 1.1, přidá zapnutí asistence AI:

- **Navrhnout pomocí AI** na pole titulku a popisu SEO každého resource používajícího sekci SEO na stránkách úprav. Modální okno ukáže alternativy s počty znaků; výběr vyplní pole ke kontrole.
- **Vysvětlit (AI)** na tabulku problémů přehledu: krátké srozumitelné vysvětlení problému a konkrétní opravy.
- **Přepsat popis (AI)** vedle vysvětlení: navrhne jeden vylepšený meta popis v limitu stránky, 160 znaků pro latinku a přibližně 80 pro CJK podle [pravidel délky](/cs/guide/multilingual#title-and-description-budgets-per-script) Core. Zkontrolujte jej v okně. Kliknutí na **Použít přepis** jej zapíše do záznamu `seo_meta` stránky. Do použití se nic nezapisuje.
- **Navrhnout strukturovaná data (AI)** v tabulce problémů: navrhne nejvhodnější typ rozšířeného výsledku schema.org, Product, Article nebo Breadcrumb, a ukáže sestavené JSON-LD. Kliknutí na **Použít strukturovaná data** jej přidá do `seo_meta.schema_jsonld` stránky, stejného sloupce spravovaného volitelným [editorem strukturovaných dat](../guide/filament#structured-data-schema-org), takže jej editor znovu načte a může dál upravovat. Neúplný návrh, například Article bez autora nebo obrázku, se ukáže s chybějícími poli a **nepoužije se**.

Poslední dvě akce jsou omezené opravy; viz [Omezené opravy](#bounded-fixes-propose-never-auto-apply).

## Omezené opravy (návrh, nikdy automatické použití) {#bounded-fixes-propose-never-auto-apply}

Dvě akce asistence jdou o krok dál než seznam návrhů: vytvoří jedinou *omezenou* hodnotu použitelnou jedním kliknutím. Obě stále **jen navrhují**. Nic se neuloží, dokud výslovně nepřijmete.

- **Přepsání popisu**, `SeoSuggestionService::rewriteDescription($model, $issue?)`, vrátí jeden meta popis **vždy v limitu pravidel délky Core pro písmo stránky: 160 pro latinku, přibližně 80 pro CJK**. Stejný limit dostávají prompty návrhů titulků a popisů podle vlastní vyhodnocené hodnoty stránky. Pokud jej model překročí, text se deterministicky ořízne na hranici věty a pak slova, takže přijaté přepsání samo nikdy nevyvolá upozornění `description_too_long`. Předaný problém skenu usměrní přepsání, například *příliš dlouhé* proti *chybějící*.
- **Návrh strukturovaných dat**, `SeoSuggestionService::suggestSchemaType($model)`, žádá model jen o **doporučení typu a hodnoty koncových polí**, nikdy surové JSON-LD. Deterministický kód pak dokument sestaví nástroji schématu Core, `ProductSchema` / `ArticleSchema` / `BreadcrumbSchema`, a ověří přes `SchemaValidator`. Vymyšlené `@type`, `@context` ani struktura tak nemohou dorazit na stránku. Pokud sestavenému dokumentu chybí povinné pole, ukáže se jako *neúplný* a nepoužije se. Při živé zkoušce jeden poskytovatel navrhl `Article` pro krátkou stránku a návrh byl správně zadržen kvůli chybějícímu autorovi a obrázku; ostatní neposkytli žádný typ.

## Bez panelu {#headless}

Stejné schopnosti jako JSON pro skripty a aplikace bez Filamentu:

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

Výstup obsahuje návrhy nebo doporučený typ, sestavené JSON-LD a výsledek validace, použitý model a **spotřebu tokenů každého požadavku**, vstup / výstup / uvažování, pro výpočet nákladů podle cen poskytovatele. Počty tokenů nejsou faktura. Při chybě příkaz skončí nenulovým kódem a chybou v obálce JSON. Stejně jako ostatní části asistence `seo-pro:suggest-schema` **pouze navrhuje**: dokument vypíše a nic nezapíše.

## Hromadné doplnění chybějících metadat {#bulk-fill-missing-metadata}

**Pro 2.42 mění výchozí chování CLI:** `seo-pro:ai-fill` uloží jeden soukromý návrh pro každé generované pole. Zveřejněná metadata SEO zůstanou do schválení beze změny. Existující hodnoty a vypočítané náhradní hodnoty se přeskakují. Aktuální čekající návrh se znovu použije místo dalšího generování.

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review` vypíše prvních 100 čekajících návrhů jako JSON. Pro přečtení hodnoty a soukromých důkazů prozkoumejte ID. Schválení a zamítnutí fungují s vypnutou AI a nevolají poskytovatele. Schválení vyžaduje označení obsluhy a odmítá návrhy, jejichž zdrojový záznam či cílová metadata se změnily nebo jejichž záznam byl smazán. Označení zaznamenává, za koho se obsluha vydává; nedokládá věcnou lidskou kontrolu. Pro nastavenou nevýchozí databázi použijte `--connection=NAME`.

`--auto-apply` výslovně obnoví okamžité zveřejnění stále chybějících polí. `--force` přeskočí potvrzení, **nikoli kontrolu**. `--dry-run` generuje a vypisuje hodnoty bez uložení návrhů či metadat; stále volá poskytovatele a může stát peníze. `--field`, `--limit` a `--locale` omezují rozsah generování. Po aktualizaci upravujte plánované příkazy záměrně.

### Ve větším rozsahu: tempo, odhad nákladů a pokračování po pádu {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms` má výchozí hodnotu 200 milisekund. Při `confirm_over`, standardně 100 záznamů, příkaz ukáže odhad z `seo-pro.ai.pricing` a před generováním se zeptá. Checkpointy zachovají dokončená pole přes přerušení. Časový limit po přijetí poskytovatelem stále může způsobit duplicitní poplatek. Nejistou práci prověřte před `--fresh`. Spouštějte vždy jen jednu odpovídající hromadnou úlohu.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

Pro vlastní integrace předejte `review: true` pro ukládání návrhů. Nízkoúrovňové PHP API kvůli kompatibilitě zachovává `apply: true, review: false`, takže stávající volání dál zapisují okamžitě. `apply: false` poskytuje náhled bez ukládání. Klíč souhrnu `filled` počítá obsloužené záznamy včetně uložených návrhů v režimu kontroly; CLI je označuje `staged`.

### Režim dávky (o 50 % levnější) {#batch-mode-50-cheaper}

`--batch` používá podporovaný asynchronní endpoint Anthropic nebo OpenAI. Jejich dokumentovaná sleva se promítne do odhadu, ale ověřte aktuální ceny modelu. Adaptéry Google a místní použijí sekvenční generování. Odešlete nyní a později spusťte stejný příkaz pro převzetí návrhů:

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

Mezi odesláním a převzetím ponechte stejného poskytovatele, národní prostředí a režim publikace. Kontrola a `--auto-apply` používají samostatné checkpointy. CLI odmítne zahájit druhý režim, dokud je odpovídající dávka nedokončená. Nejisté odeslání se zastaví k prověření. Částečné úspěchy se zachovají; přechodné chyby lze opakovat. Převzetí znovu ověří chybějící pole; schválení kontroluje také snímek zdroje pořízený před odesláním. `seo-pro.ai.fill.batch.request_timeout` má výchozí hodnotu 120 sekund. Plánované převzetí standardně ukládá návrhy.

## Záznamy původu, migrace a filtrování dat {#origin-review-and-filtering}

Vyžaduje **Core 3.21 a Pro 2.42**. Core načte svou migraci automaticky. Před generováním ukládaných návrhů zkopírujte migrace Pro a spusťte je pro každou databázi modelů SEO:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Soukromá tabulka `seo_ai_proposals` ukládá generované hodnoty a důkazy poskytovatele, modelu a požadavku přes šifrovaná přetypování Laravelu. Bezpečně uchovávejte `APP_KEY` i jeho zálohu; ztráta způsobí nečitelnost těchto hodnot. Identifikátory záznamů, stav a metadata rozhodnutí zůstávají běžnými databázovými sloupci. Alternativy formuláře mohou při jeho opuštění zůstat ve stavu `offered` nebo `selected`. Automatické promazávání neexistuje. Nastavte dobu uchovávání aplikace, zachovejte čekající návrhy a důkazy stále odkazované z `seo_meta.ai_provenance` a omezte přístup k exportům databáze i výstupu příkazů kontroly.

Přijaté návrhy formuláře, opravy přehledu a hromadné hodnoty nesou původ pole. Pozdější úpravy Eloquent zachovají `origin: ai` a nastaví `edited: true`. Znamená to změnu hodnoty, nikoli ověření člověkem. Vymazání pole odstraní jeho značku. Výstupy Core v HTML, poli, JSON a Inertia zveřejňují jen název pole, původ a stav úpravy, případně pomocí vlastní meta značky `rankbeam:ai-origin`. ID generování a podrobnosti poskytovatele zůstávají soukromé. Nezveřejňujte surové modely `SEOMeta` ve veřejném API.

Zaznamenávají se budoucí podporované cesty uložení, nikoli historický obsah nebo každá revize úprav. Přímé SQL, aktualizace přes query builder a vlastní renderery mohou tyto kontroly obejít. Pokud je namístě nahrazení nezávisle napsaným textem, výslovně resetujte původ; běžné úpravy jej zachovávají. Vlastní značka není standardizovaný vodoznak, nezměnitelné určení autorství ani tvrzení o souladu s článkem 50. Skutečná kvalita výstupu a vlastní značky poskytovatele vyžadují samostatné vyhodnocení.

Volitelně implementujte `AiPromptFilter` a nastavte `seo-pro.ai.context_filter`. Filtr upraví sestavený uživatelský prompt před synchronním či dávkovým odesláním; při selhání odeslání zabrání a vrátí očištěnou chybu. Systémové pokyny zůstávají beze změny. Výchozí hodnota je `null`, **bez automatického odstranění citlivých dat**. Příklad nahrazuje jedinou známou hodnotu; implementujte a otestujte pravidla vhodná pro svou aplikaci:

```php
namespace App\Support;

use Rankbeam\Seo\Pro\Ai\AiPromptFilter;

final class RedactAiContext implements AiPromptFilter
{
    public function filter(string $prompt): string
    {
        return str_replace('internal@example.com', '[redacted]', $prompt);
    }
}

// Configure seo-pro.ai.context_filter with this class in config/seo-pro.php.
// Runtime equivalent:
config(['seo-pro.ai.context_filter' => RedactAiContext::class]);
```

## Zpracování odpovědí {#how-replies-are-handled}

Každé volání vrátí obálku nezávislou na poskytovateli, takže chování je stejné napříč poskytovateli i těmi přidanými později:

- **Strukturovaný výstup, kde jej poskytovatel podporuje.** OpenAI s nativními Structured Outputs, Google s Gemini `responseSchema` a Anthropic s `output_config.format` vynucují tvar JSON přes API. Neplatný JSON řádně selže, nikdy se nevytěžuje z textu. Místní server kompatibilní s OpenAI dostává také `response_format` podle svých možností. Server ignorující pole stále může vrátit použitelný text, který se náhradně tolerantně zpracuje. V obou případech dostanete řádný seznam nebo jasnou chybu, nikdy napůl zpracovanou odpověď.
- **Zkrácení je výslovná chyba s nápravou.** Pokud se odpověď usekne na limitu výstupních tokenů, dostanete chybu `truncated` s pokynem zvýšit `seo-pro.ai.max_output_tokens`, nikoli tiše zkrácený titulek. Nejčastější je to u **modelů s uvažováním**, které automaticky dostávají vyšší minimum `reasoning_min_output_tokens`.
- **Přechodné chyby se opakují automaticky.** Omezení četnosti `429` nebo `5xx` se opakuje s omezenou exponenciálně rostoucí prodlevou. Přítomná hlavička `Retry-After` se respektuje v mezích, aby nepřátelská hodnota nemohla požadavek zdržet neomezeně. **Deterministické** chyby se **neopakují**: chybný klíč, špatně sestavený požadavek, příliš velký obsah, **časový limit** nebo účet **bez kreditu či kvóty**. Opakování u účtu bez kreditu pouze prodlužuje čekání. Opakování nastavte nebo vypněte přes blok `retry`; pro vypnutí nastavte `max_attempts` na `0`.
- **Chyby mají typ a očištěný text.** Každé selhání nese stabilní kód, například `unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`, `content_too_large`, `bad_request`, `truncated`, `content_filtered` nebo `provider_error`, a u přechodných chyb příznak `retryable`. Zpráva je krátký očištěný řetězec. **Běžná aplikační cesta nezobrazuje ani neloguje surové tělo odpovědi poskytovatele; volitelně zapínané vyhodnocovací nástroje výše odpovědi uchovávají jako důkazy.** Ve Filamentu se chyba vykreslí ve stylované části modálního okna s konkrétním dalším krokem pro běžné případy; viz [Řešení potíží](#troubleshooting).

## Řešení potíží {#troubleshooting}

Každé selhání je přímo v rozhraní a bez fatálního dopadu, s kódem typu a očištěnou zprávou. Běžné případy a jejich náprava:

| Příznak (kód chyby) | Co znamená | Oprava |
|---|---|---|
| **`quota_exceeded`** — *„účtu poskytovatele došel kredit nebo kvóta“* | Klíč je platný, ale **API účet nemá kredit či kvótu**. Nejde o omezení četnosti; opakování nepomůže. Anthropic vrací *„credit balance is too low“*, OpenAI *„exceeded your current quota… check your plan and billing“*, `insufficient_quota`, a Google *„prepayment credits are depleted“*. | Doplňte kredit nebo zapněte účtování v konzoli poskytovatele, případně přejděte na **místní** model bez poplatku poskytovatele za API. **Předplatné** Claude/ChatGPT nefinancuje **API**. |
| **`unauthorized`** — *„autentizace selhala“* | Klíč chybí, je chybný nebo neplatí pro nastaveného poskytovatele. | Ověřte klíč v proměnné prostředí pojmenované v `seo-pro.ai.api_key_env`, standardně `SEO_PRO_AI_API_KEY`. Musí být nastavený, aktuální a odpovídat `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`** — *„dosažen limit četnosti poskytovatele“* | Skutečné **přechodné** omezení četnosti, nejprve automaticky opakované. | Počkejte a opakujte nebo použijte **místní** model v mezích kapacity serveru. Pro hromadné běhy na nižší úrovni služby zvyšte `seo-pro.ai.fill.throttle_ms`. |
| **`timeout`** — *„vypršel čas požadavku“* | Poskytovatel neodpověděl do `seo-pro.ai.timeout`, standardně 15 sekund. Běžné u **pomalého místního modelu s uvažováním**. | Zvyšte jej přes `SEO_PRO_AI_TIMEOUT`; pro Ollama nastavte také `['think' => false]` v `seo-pro.ai.local.extra_body`. |
| **`truncated`** — *„dosažen limit max_output_tokens“* | Odpověď dosáhla výstupního rozpočtu, případně včetně skrytého uvažování. | Zvyšte `seo-pro.ai.max_output_tokens`, modely s uvažováním mohou potřebovat přes 2 000, nebo ověřte shodu se vzorem `reasoning_models`, aby se uplatnilo minimum. |
| **`content_too_large`** (HTTP 413) | Odeslaný obsah stránky překročil limit poskytovatele. | Snižte `seo-pro.ai.max_input_chars` pro kratší úryvek. |
| **`bad_request`** | Chybně sestavený požadavek, obvykle **název modelu**, ke kterému účet nemá přístup, nebo nepodporovaný parametr. | Ověřte, že `SEO_PRO_AI_MODEL` je model dostupný vašemu klíči či serveru pro nastaveného poskytovatele. |
| **`content_filtered`** | Bezpečnostní filtr poskytovatele odmítl odpovědět. | Zkontrolujte obsah a pokyny poskytovatele; odmítnutý požadavek automaticky neopakujte. |

::: tip Místní inference stále potřebuje funkční server
`SEO_PRO_AI_PROVIDER=local` proti vlastní inferenci se vyhne problémům s kreditem cloudového poskytovatele. Hardware, model, kompatibilita API, časový limit a kapacita stále rozhodují. Vzdálená brána nastavená přes tento adaptér může potřebovat klíč a platbu.
:::

## Co opouští váš server {#what-leaves-your-server}

Přesně tyto údaje, pouze nastavenému poskytovateli a jen při výslovné akci, kliknutí nebo spuštění příkazu:

- *Návrhy*: základní název třídy modelu a klíč, například „Post #3“, aktuálně vyhodnocený titulek a popis, kanonická URL a úryvek obsahu v prostém textu bez HTML omezený `max_input_chars`, standardně 6 000 znaků.
- *Vysvětlení problémů*: typ, závažnost, pole a zpráva problému a cílová URL. Je-li model dostupný, také jeho třída a klíč, vyhodnocený titulek a popis, kanonická URL a omezený úryvek obsahu v prostém textu.
- *Přepsání popisu*: stejný minimální kontext stránky jako u návrhu a případně typ a zpráva předaného problému skenu.
- *Návrh strukturovaných dat*: stejný minimální kontext stránky jako u návrhu. Model vrací jen typ a hodnoty koncových polí; JSON-LD se sestaví místně.

Balíček do promptů záměrně nesbírá data návštěvníků, IP adresy, hlavičky požadavků ani přihlašovací údaje a neposílá celé HTML. **Vaše pole obsahu a úryvky mohou samy obsahovat citlivé informace**; zkontrolujte, co aplikace zpřístupňuje. Přihlašovací údaj poskytovatele slouží k autentizaci požadavku. Referencí pro zpracování dat je SECURITY.md repozitáře Pro.

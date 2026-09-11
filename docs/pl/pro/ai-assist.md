---
description: "Opcjonalna pomoc AI z własnym kluczem: propozycje tytułów i metadanych, wyjaśnienia problemów skanowania prostym językiem, przeredagowanie jednym kliknięciem i propozycje schema.org. Domyślnie wyłączona."
---

# Pomoc AI {#ai-assist}

Opcjonalna pomoc AI **z własnym kluczem**: propozycje tytułów i metaopisów, wyjaśnienia problemów skanowania prostym językiem, **przeredagowanie opisu** jednym kliknięciem i **propozycja danych strukturalnych (schema.org)**. Jest **domyślnie wyłączona** — przy wyłączonej opcji żadna ścieżka kodu AI nie jest uruchamiana.

Trzy założenia konstrukcyjne:

- **Twój klucz, Twój dostawca.** Żądania idą z *Twojego serwera* bezpośrednio do skonfigurowanego przez *Ciebie* dostawcy: Anthropic, OpenAI, Google lub serwera lokalnego/zgodnego z OpenAI. Tam, gdzie obowiązują opłaty, są naliczane na Twoim koncie. Nie ma pośrednictwa, rozliczania zużycia ani odsprzedaży, a pakiet nigdzie nie wysyła telemetrii.
- **Propozycje interaktywne wymagają jawnej akceptacji.** Wybór propozycji wypełnia formularz, a poprawki w panelu wymagają zastosowania. Od Pro 2.42 zbiorcze CLI domyślnie zapisuje prywatne szkice. Używaj `--auto-apply` tylko wtedy, gdy świadomie chcesz natychmiastowego zapisu.
- **Błędy nigdy nie przerywają podstawowych działań.** Brak klucza, nieprawidłowy klucz, wyczerpane środki konta, limit żądań lub przekroczenie czasu dają komunikat w interfejsie. Nigdy nie mogą blokować zapisywania, renderowania ani skanowania.

## Dostawcy w skrócie {#providers-at-a-glance}

Wybieraj według dostępu do konta, wymagań dotyczących danych i kosztu. Wszystkie cztery integracje udostępniają te same zadania, ale obsługa modeli, format wyniku, szybkość i jakość mogą się różnić.

| Dostawca | Dołączony model domyślny | Wynik ustrukturyzowany | Przykładowy koszt | Zastosowanie |
|---|---|---|---|---|
| **Local** (Ollama / LM Studio / vLLM) | `llama3.1` (ustaw własny) | W miarę możliwości (`response_format`) | **0 USD opłaty API** przy własnym hostingu inferencji; pozostają koszty infrastruktury | Kontrola nad miejscem wysyłania danych |
| **OpenAI** | `gpt-5.5` | Structured Outputs, gdy obsługuje je wybrany model | około 0,005 USD za propozycję przy poniższych założeniach | Istniejące konto OpenAI |
| **Anthropic** | `claude-opus-4-8` | `output_config.format`, gdzie obsługiwane | około 0,015 USD za propozycję przy tych założeniach | Istniejące konto Anthropic |
| **Google** | `gemini-2.5-flash` | `responseSchema`, gdzie obsługiwane | około 0,0005 USD za propozycję przy tych założeniach | Konto Google; sprawdź limity i ceny modelu |

Te nazwy opisują dołączoną konfigurację, nie gwarantowaną bieżącą dostępność na Twoim koncie. Koszty używają przykładowych założeń pakietu, a nie zweryfikowanych aktualnych cen. Zachowanie integracji i obserwacje z opublikowanych prób:

- **Wynik ustrukturyzowany.** Obsługiwane ścieżki OpenAI, Google i Anthropic otrzymują schemat JSON; nieprawidłowe odpowiedzi kończą się kontrolowanym błędem. Serwery lokalne otrzymują `response_format` w miarę możliwości. Jeśli je zignorują, tolerancyjny parser zwraca poprawną listę lub błąd, bez stosowania częściowego wyniku.
- **Rozumowanie zmienia zużycie tokenów.** Opisana próba Gemini użyła około 500 ukrytych tokenów rozumowania i około 100 widocznych tokenów dla opisu. Testowane wywołania Anthropic nie raportowały ukrytych tokenów rozumowania. Nie jest to uniwersalna cecha tych rodzin modeli. Ukryte tokeny mogą być rozliczane jako wyjście, dlatego istnieje opisany poniżej minimalny budżet rozumowania.
- **Modele są konfigurowalne.** Ustaw `SEO_PRO_AI_MODEL` na dostępny model zgodny z API i parametrami adaptera. Przykłady to `claude-haiku-4-5`, `gpt-5.4-mini` i `gemma-3-12b-it`. Przed użyciem modelu dla całej kolekcji sprawdź obsługę i jakość wyniku.

## Konfiguracja {#setup}

Włącz funkcję i umieść klucz dostawcy w środowisku. Zmieniaj dostawcę chmurowego przez aktualizację dostawcy i klucza, sprawdzając także ewentualne nadpisanie modelu. Adapter lokalny wymaga również URL-a serwera.

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

::: tip Klucz API dostawcy jest osobny od subskrypcji Claude / ChatGPT
**Subskrypcja** Claude Code, Claude.ai lub ChatGPT nie finansuje **API**. `SEO_PRO_AI_API_KEY` musi być *kluczem API rozliczanym według użycia* z konsoli deweloperskiej dostawcy lub kluczem Google AI Studio, z własnym saldem środków. Konto wyłącznie subskrypcyjne lub bez środków może się uwierzytelnić, ale zwróci błąd **braku środków lub wyczerpania limitu**. Zobacz [Rozwiązywanie problemów](#troubleshooting).
:::

Plik konfiguracji (`config/seo-pro.php`, blok `ai`) udostępnia `timeout`, `max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models` + `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model` (tańszy wariant zbiorczego uzupełniania; zobacz [Koszt](#cheaper-bulk-generation)), `retry`, tabelę `pricing` i podblok `local`. Wszystkie opisano w [Limitach i dostrajaniu](#limits-and-tuning).

::: warning Obsługa klucza przy konfiguracji w pamięci podręcznej
Konfiguracja przechowuje tylko **nazwę** zmiennej środowiskowej (`api_key_env`), nigdy klucz, więc `php artisan config:cache` nie zapisuje klucza w `bootstrap/cache/config.php`. Druga strona tego rozwiązania: przy konfiguracji w pamięci podręcznej `.env` nie jest ładowane, więc ustaw `SEO_PRO_AI_API_KEY` jako rzeczywistą zmienną środowiskową na serwerze.
:::

## Inferencja lokalna i opcje chmurowe {#running-at-0-and-the-cheapest-paid-option}

- **Własny hosting inferencji eliminuje opłatę API dostawcy za tokeny.** Sprzęt, energia i obsługa nadal kosztują. Treść pozostaje w Twojej sieci tylko wtedy, gdy skonfigurowany serwer inferencji i jego zależności również w niej pozostają.
- **Google ma limity i ceny zależne od modelu i poziomu konta.** Klucz AI Studio (`aistudio.google.com/apikey`, format `AIza…`) może umożliwiać próby w bezpłatnym wariancie. Sprawdź, czy jego limity pasują do obciążenia, zanim w razie potrzeby włączysz płatności. `gemini-2.5-flash` jest dołączonym ustawieniem domyślnym. Modele Gemini i Gemma nie mają jednakowych możliwości rozumowania: `reasoning_models` stosuje skonfigurowane wzorce nazw, a nie test możliwości.

Aby kontrolować miejsce wykonywania inferencji:

- **Lokalny / zgodny z OpenAI.** Użyj `provider=local` z serwerem zgodnym z OpenAI Chat Completions, np. **Ollama**, **LM Studio**, **vLLM**, **LocalAI**, lub zdalną bramą, np. **OpenRouter**. Ustaw `SEO_PRO_AI_LOCAL_BASE_URL` na katalog główny API (dołączane jest `/chat/completions`) i wybierz dostępny `SEO_PRO_AI_MODEL`. Zdalna brama otrzymuje dane poza Twoją siecią i może pobierać opłaty. Nazwa adaptera `local` nie oznacza lokalnej inferencji.

::: warning Lokalny `base_url` jest walidowany — localhost wymaga zgody w konfiguracji
`base_url` jest ustawieniem uprzywilejowanym, sprawdzanym przez ten sam `SsrfGuard` co inne pobrania wychodzące: tylko http/https, bez userinfo i domyślnie z rozstrzyganiem na **publiczny** adres. Przypadkowe lub wrogie `base_url` nie może więc służyć do sondowania usług wewnętrznych. Rzeczywiście lokalny serwer działa na `127.0.0.1`, czyli adresie prywatnym, dlatego lokalny dostawca wymaga jawnego włączenia `seo-pro.ai.local.allow_local_addresses` (`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`). Pozostaw je wyłączone dla publicznej bramy OpenRouter. Ścieżka żądania jest stała, a przekierowania nigdy nie są śledzone, więc klucz nie może zostać przekierowany na inny host.
:::

::: tip Sterowanie rozumowaniem w obsługiwanych modelach Ollama
Lokalny model rozumujący może przekroczyć domyślny limit czasu. Jeśli obsługują to model i wersja serwera, `['think' => false]` w `seo-pro.ai.local.extra_body` może wyłączyć rozumowanie dla propozycji. Obsługa jest różna; sprawdź [dokumentację Ollama](https://docs.ollama.com/capabilities/thinking). W razie potrzeby zwiększ `seo-pro.ai.timeout`. Pakiet nie wysyła `temperature`, ponieważ część modeli je odrzuca.
:::

## Koszt {#cost}

Pakiet nie dolicza marży. Płacisz bezpośrednio dostawcy, a przy własnym hostingu inferencji nie ma opłaty API dostawcy, choć pozostają koszty infrastruktury. Istotne są dwie liczby: koszt **pojedynczej propozycji** przy pracy interaktywnej i koszt **zbiorczego uzupełnienia** całej kolekcji.

Tabela `seo-pro.ai.pricing` (USD za 1 000 000 tokenów) przelicza szacowane tokeny na kwotę pokazywaną w potwierdzeniu zbiorczego uzupełniania. To **dołączone założenia estymacji**, a nie zweryfikowane aktualne ceny publiczne. **Nadpisz je bieżącymi opublikowanymi cenami dostawcy**, aby uzyskać dokładny szacunek:

| Wzorzec modelu | Wejście USD/1 mln | Wyjście USD/1 mln |
|---|---|---|
| `claude-opus-*` | 15,00 | 75,00 |
| `claude-sonnet-*` | 3,00 | 15,00 |
| `claude-haiku-*` | 1,00 | 5,00 |
| `gpt-5*mini*` | 0,50 | 1,50 |
| `gpt-5*` | 5,00 | 15,00 |
| `gemini-2.5-pro*` | 1,25 | 10,00 |
| `gemini-*flash*` | 0,15 | 0,60 |

Opublikowany przykład wykorzystuje zużycie tokenów testowanej strony (jeden tytuł i jeden opis) przy tych założeniach. Ostatnia kolumna stosuje przykładowy rabat 50% na przetwarzanie wsadowe w obsługiwanych adapterach. To nie są oferty cenowe według aktualnego cennika.

| Dostawca / model | Około za parę propozycji | Około za 1000 rekordów (zbiorcze uzupełnianie) | Około za 1000 rekordów (`--batch`) |
|---|---|---|---|
| Lokalny `gemma`/`llama` (Ollama) | **0 USD opłaty API** | **0 USD opłaty API** | nie dotyczy (adapter nie implementuje) |
| Google `gemini-2.5-flash` (płatny) | około 0,001 USD | około 0,40 USD | nie dotyczy (adapter Rankbeam nie implementuje) |
| OpenAI `gpt-5.5` | około 0,008 USD | około 5,25 USD | **około 2,63 USD** (50% taniej) |
| Anthropic `claude-opus-4-8` | około 0,03 USD | około 20 USD | **około 10 USD** (50% taniej) |

Polecenie oznacza szacunek jako około ±50%, ale **nie jest to limit wydatków ani gwarantowany zakres błędu**. Rzeczywiste wejście, wyjście i ceny zmieniają sumę. Szacunek modeluje widoczne wyjście; rozliczane ukryte rozumowanie może zwiększyć koszt ponad tę wartość. Dla modeli bez wpisu ceny pokazywana jest tylko estymacja tokenów.

### Tańsze generowanie zbiorcze {#cheaper-bulk-generation}

Ustaw `seo-pro.ai.bulk_model` (`SEO_PRO_AI_BULK_MODEL`), aby używać innego modelu **tylko przy zbiorczym uzupełnianiu** (`seo-pro:ai-fill` / `SeoPro::aiFill()`). Filament i `seo-pro:ai-suggest` zachowują `model`. Przy null zbiorcze uzupełnianie też używa `model`. Estymacja korzysta ze wzorca ceny wybranego modelu. Przed zwiększeniem skali oceń reprezentatywny wynik; tańszy model nie jest automatycznie odpowiedni.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Przykłady pakietu używają **anthropic** `claude-haiku-4-5`, **openai** `gpt-5.5-mini`, **google** `gemini-2.5-flash` lub mniejszego modelu **local**. Wzorzec ceny może pasować do nazwy, nawet jeśli dostawca jej nie oferuje. Przed konfiguracją potwierdź rzeczywisty identyfikator modelu, zgodność API i cenę.

**Uzupełnienie 100 stron**, z których każdej brakuje tytułu i opisu, oznacza 200 wywołań dostawcy. Poniżej wycena według dołączonych wartości `pricing` i modelu tokenów estymatora (600 wejściowych + 150 wyjściowych na wywołanie), dla modelu jakościowego i jego tańszego wariantu `bulk_model`:

| Dostawca | Model jakościowy — 100 stron | Tańszy wariant `bulk_model` — 100 stron |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **4,05 USD** | `claude-haiku-4-5` ≈ **0,27 USD** |
| **OpenAI** | `gpt-5.5` ≈ **1,05 USD** | `gpt-5.5-mini` ≈ **0,11 USD** |
| **Google** | `gemini-2.5-pro` ≈ **0,45 USD** | `gemini-2.5-flash` ≈ **0,04 USD** |
| **Local** (Ollama / vLLM) | dowolny model — **0 USD opłaty API** | dowolny model — **0 USD opłaty API** |

To przykładowe szacunki, bez gwarancji zakresu ±50% i bez uwzględnienia ukrytego rozumowania. Zaktualizuj `seo-pro.ai.pricing` według opublikowanych cen wybranego dostawcy, zanim oprzesz się na estymacji.

## Język wyniku {#output-language}

Każdy prompt podaje język strony i jego kod BCP-47, np. *„w portugalskim brazylijskim (pt-BR), języku strony, niezależnie od innych języków fragmentu”*. Kontekst strony wysyłany do modelu zawiera wiersz `Language:` (Pro 2.34). Wcześniejsze prompty mówiły „w tym samym języku co treść źródłowa”, zmuszając model do zgadywania na podstawie krótkiego fragmentu lub tekstu mieszanego. Turecka strona z angielską nazwą marki we fragmencie mogła otrzymać angielski wynik. Locale pochodzi z rozstrzygnięcia metadanych strony (z aplikacji, jeśli strona go nie ma). Dla tego samego locale wybierany jest [budżet długości](/pl/guide/multilingual#title-and-description-budgets-per-script), więc strona japońska prosi o tytuły około 30 znaków *po japońsku*.

Od Pro 2.36 jawne locale treści wspólnie steruje wierszem metadanych, metodami treści i językiem promptu. Język interfejsu operatora pozostaje bez zmian.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Istniejące argumenty pozycyjne pozostają bez zmian. Pomiń `locale:`, aby użyć domyślnego `seoData()` modelu, dzięki czemu osobne modele tłumaczeń mogą deklarować swój język. Fragment używa `getContentForSEO()`, gdy zwraca niepustą treść, a w przeciwnym razie skonfigurowanych pól treści. Filament 1.11 automatycznie przekazuje locale wybranej karty, także w trybach pojedynczego locale i przełącznika strony.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

Zbiorcze `plan()`, `fill()` i `submitBatchFill()` również przyjmują końcowe `locale:`. Użyj tego samego locale przy tworzeniu `FillProgress(..., locale: 'it')` i wysyłaniu przebiegu. Każdy element partii zapisuje locale treści; odbieranie wyników używa go i ponownie sprawdza wiersz metadanych przed zapisem. Przebiegi z jawnym locale mają osobne pliki punktów kontrolnych, a znaczniki przetworzenia rozróżniają języki. Aby odebrać wyniki, ponownie uruchom to samo polecenie. Własne zadania powinny serializować i przekazywać locale treści.

Punkty kontrolne sprzed Pro 2.36 nie zapisywały locale treści. Oczekująca starsza partia zostaje zachowana i odrzucona przy automatycznym odbieraniu. Uzgodnij jej wyniki dostawcy i zamierzone locale, zanim odrzucisz ją przez `--fresh`; inaczej ponowne wysłanie może naliczyć opłatę za tę samą pracę. Starszy sekwencyjny punkt kontrolny z przetworzonymi rekordami również wymaga uzgodnienia przed resetem.

### Ocena dla poszczególnych języków {#per-language-evaluation}

Repozytorium źródłowe Pro zawiera 170 stron wejściowych w 17 wariantach językowych oraz opcjonalny zestaw narzędzi oceny. Strony wejściowe przechodzą kontrole strukturalne i heurystyki języka bazowego; niezależna weryfikacja przez rodzimych użytkowników tych języków nadal pozostaje do wykonania.

Narzędzia sprawdzają **tytuły i opisy**, zapisują długości w grafemach i dowody języka/systemu pisma oraz zachowują każdą odpowiedź dostawcy przed asercjami. Krótkie tytuły, tekst mieszany i wspólne znaki chińskiego/japońskiego mogą pozostać niejednoznaczne. Rozpoznanie portugalskiego jako języka bazowego nie potwierdza odmiany brazylijskiej, a częściowe kontrole znaków chińskich nie certyfikują jakości regionalnego stylu pisania.

Uruchomienia na żywo wymagają `SEO_PRO_AI_EVAL=1`, jawnego wyboru `SEO_PRO_AI_EVAL_LOCALES` i identyfikatora `SEO_PRO_AI_EVAL_RUN`. Mogą wiązać się z opłatami dostawcy; żadne nie działa domyślnie. Każdy przebieg wiąże dowody z dostawcą, żądanym/zwróconym modelem, hashami danych testowych/żądania/kodu i znacznikami czasu. Nieudane próby pozostają dostępne. Wznowienie wykorzystuje zapisane odpowiedzi; przerwane żądanie wymaga jawnego ponowienia, bo mogło już dotrzeć do dostawcy.

Dowody są zapisywane pod `storage/app/seo-ai-evals/<run-id>/` w środowisku testów źródłowych. `README.md` danych testowych opisuje wersjonowany schemat i polecenia. Rodzimi recenzenci oceniają dokładne hashe wyników w osobnych rekordach przeglądu. Poprawny test automatyczny nie jest akceptacją rodzimego recenzenta ani gwarancją tekstu gotowego do publikacji.

## Limity i dostrajanie {#limits-and-tuning}

Wszystkie ustawienia znajdują się w bloku `ai` w `config/seo-pro.php`:

- **`timeout`** (domyślnie `15` sekund, zmienna `SEO_PRO_AI_TIMEOUT`) ogranicza też synchroniczne wywołanie przy otwieraniu okna propozycji Filament, dlatego jest krótki dla wygody użytkownika. **Wolny model rozumujący lub lokalny może przekroczyć 15 s**. Zwiększ limit przez `SEO_PRO_AI_TIMEOUT`, jeśli go używasz, i zobacz wskazówkę Ollama `think => false` powyżej. Przekroczenie czasu zawsze daje błąd w interfejsie, nigdy nie blokuje zapisu.
- **`max_input_chars`** (domyślnie `6000`) ogranicza koszt i zakres danych wysyłanych na żądanie: ilość treści strony w zwykłym tekście, po usunięciu HTML.
- **`max_output_tokens`** (domyślnie `1000`) to bazowy limit generowanych tokenów. Odpowiedź, która go osiągnie, daje osobny błąd `truncated`, nigdy po cichu zwróconą częściową odpowiedź.
- **`token_budgets`** to limity wyjścia dla zadań (`suggestions` 800, `explanation` 600, `rewrite` 300, `schema_suggestion` 700). Żadne nie potrzebuje pełnej wartości domyślnej, ale dodatkowo stosowane jest minimum rozumowania.
- **`reasoning_models`** i **`reasoning_min_output_tokens`** (domyślnie `2000`): każdy model z nazwą pasującą do wzorca (`*gemma*`, `gemini-2.5-*`, `o1*`/`o3*`/`o4*`) otrzymuje budżet wyjścia podniesiony do minimum, ponieważ zużywa ukryte tokeny przed widocznym wynikiem, a mały budżet przerwałby odpowiedź.
- **`suggestion_count`** (domyślnie `3`) to liczba zamawianych wariantów tytułu/opisu.
- **`retry`** to automatyczne ponawianie tylko *przejściowych* niepowodzeń. Zobacz [Obsługę odpowiedzi](#how-replies-are-handled).

## W Filament {#in-filament}

Po instalacji opcjonalnych pakietów Filament (`rankbeam/laravel-seo-filament` >= 1.1) włączenie pomocy AI dodaje:

- **Zaproponuj z AI** przy polach tytułu SEO i opisu każdego zasobu korzystającego z sekcji SEO, na stronach edycji. Okno pokazuje wygenerowane warianty z liczbą znaków; wybór wypełnia pole do przeglądu.
- **Wyjaśnij (AI)** w tabeli problemów panelu: krótkie wyjaśnienie problemu i konkretnej poprawki prostym językiem.
- **Przepisz opis (AI)** w tabeli problemów, obok wyjaśnienia: proponuje jeden poprawiony metaopis, zawsze w budżecie opisu strony (160 znaków dla tekstu łacińskiego, około 80 dla CJK, według [zasad długości](/pl/guide/multilingual#title-and-description-budgets-per-script) rdzenia). Sprawdź go w oknie. Kliknięcie **Zastosuj przepisany opis** zapisuje go w rekordzie `seo_meta` strony. Nic nie jest zapisywane przed zastosowaniem.
- **Zaproponuj dane strukturalne (AI)** w tabeli problemów: proponuje najbardziej odpowiedni typ wyników rozszerzonych schema.org (Product, Article lub Breadcrumb) i pokazuje zbudowane dla niego JSON-LD. Kliknięcie **Zastosuj dane strukturalne** dodaje je do `seo_meta.schema_jsonld` strony, tej samej kolumny, którą zarządza opcjonalny [edytor danych strukturalnych](/pl/guide/filament#structured-data-schema-org), więc dane pozostają tam edytowalne i można je ponownie odczytać. Niepełna propozycja, np. Article bez autora lub obrazu, pokazuje brakujące pola i **nie** jest stosowana.

Dwie ostatnie akcje są poprawkami o ograniczonym zakresie; zobacz [Poprawki o ograniczonym zakresie](#bounded-fixes-propose-never-auto-apply).

## Poprawki o ograniczonym zakresie — propozycja, nigdy automatyczny zapis {#bounded-fixes-propose-never-auto-apply}

Dwie akcje pomocy idą o krok dalej niż lista propozycji: tworzą jedną *ograniczoną regułami* wartość, którą można zastosować jednym kliknięciem. Obie nadal **tylko proponują**. Nic nie jest utrwalane bez jawnej akceptacji.

- **Przeredagowanie opisu** (`SeoSuggestionService::rewriteDescription($model, $issue?)`) zwraca jeden metaopis **zawsze w budżecie zasad długości rdzenia dla systemu pisma strony: 160 dla alfabetu łacińskiego, około 80 dla CJK**. To ten sam budżet, który zawierają prompty propozycji tytułu i opisu, wybrany na podstawie własnej rozstrzygniętej wartości strony. Jeśli model przekroczy limit, tekst jest deterministycznie przycinany na granicy zdania, a następnie słowa, więc zaakceptowane przeredagowanie nie może samo wywołać ostrzeżenia `description_too_long`. Przekazanie problemu skanowania ukierunkowuje zmianę, np. *za długi* lub *brakujący* opis.
- **Propozycja danych strukturalnych** (`SeoSuggestionService::suggestSchemaType($model)`) prosi model tylko o **zalecany typ i wartości pól końcowych**, nigdy o surowe JSON-LD. Deterministyczny kod składa następnie dokument generatorami danych strukturalnych rdzenia (`ProductSchema` / `ArticleSchema` / `BreadcrumbSchema`) i sprawdza go przez `SchemaValidator` rdzenia, więc zmyślone `@type`, `@context` lub struktura nie mogą trafić na stronę. Jeśli złożonemu dokumentowi brakuje wymaganego pola, jest pokazywany jako *niepełny* i wstrzymany. W teście na żywo jeden dostawca zaproponował `Article` dla ubogiej strony i poprawnie wstrzymano go z powodu braku autora i obrazu, podczas gdy pozostali w ogóle odmówili zaproponowania typu.

## Bez panelu {#headless}

Te same możliwości jako JSON dla skryptów i aplikacji bez Filament:

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

Wynik zawiera propozycje lub zalecany typ, zbudowane JSON-LD i wynik walidacji, użyty model oraz **zużycie tokenów na żądanie** (wejściowe / wyjściowe / rozumowania). Pomaga to obliczyć koszt według stawek dostawcy; liczby tokenów nie są fakturą. Polecenie kończy się niezerowym kodem przy każdym niepowodzeniu, z błędem w obiekcie odpowiedzi JSON. Tak jak pozostałe funkcje pomocy, `seo-pro:suggest-schema` **tylko proponuje**: wypisuje dokument i niczego nie zapisuje.

## Zbiorcze uzupełnianie brakujących metadanych {#bulk-fill-missing-metadata}

**Pro 2.42 zmienia domyślne zachowanie CLI:** `seo-pro:ai-fill` zapisuje jeden prywatny szkic na wygenerowane pole. Opublikowane metadane SEO pozostają bez zmian do chwili zatwierdzenia. Istniejące wartości i wyliczane wartości zastępcze są pomijane. Aktualny oczekujący szkic jest używany ponownie zamiast ponownego generowania.

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review` wypisuje pierwszych 100 oczekujących szkiców jako JSON. Sprawdź identyfikator, aby odczytać wartość i prywatne dowody. Zatwierdzanie i odrzucanie działają z wyłączonym AI, bez wywołań dostawcy. Zatwierdzenie wymaga etykiety operatora i odrzuca szkice, których rekord źródłowy lub docelowe metadane się zmieniły, albo których rekord został usunięty. Etykieta zapisuje deklarowaną tożsamość operatora; nie dowodzi merytorycznego przeglądu przez człowieka. Użyj `--connection=NAME` dla skonfigurowanej bazy innej niż domyślna.

`--auto-apply` jawnie przywraca natychmiastową publikację nadal brakujących pól. `--force` pomija potwierdzenie, **nie przegląd**. `--dry-run` generuje i wypisuje wartości bez zapisywania szkiców lub metadanych; nadal wywołuje dostawcę i może kosztować. `--field`, `--limit` i `--locale` ograniczają zakres generowania. Po aktualizacji świadomie dostosuj zaplanowane polecenia.

### Większa skala: tempo, szacowanie kosztu i wznawianie po awarii {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms` domyślnie wynosi 200 milisekund. Przy `confirm_over` (domyślnie 100 rekordów) polecenie pokazuje szacunek z `seo-pro.ai.pricing` i pyta przed generowaniem. Punkty kontrolne zachowują ukończone pola mimo przerwań. Przekroczenie czasu po przyjęciu przez dostawcę nadal może spowodować podwójną opłatę. Uzgodnij niepewną pracę przed `--fresh`. Uruchamiaj tylko jedno pasujące zadanie zbiorcze naraz.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

We własnych integracjach przekaż `review: true`, aby zapisywać szkice do przeglądu. API PHP niższego poziomu zachowuje `apply: true, review: false` dla zgodności, więc dotychczasowe wywołania nadal zapisują natychmiast. `apply: false` daje podgląd bez utrwalania. Klucz podsumowania `filled` liczy obsłużone rekordy, w tym zapisane jako szkice w trybie przeglądu. CLI oznacza je jako `staged`.

### Tryb wsadowy (50% taniej) {#batch-mode-50-cheaper}

`--batch` używa obsługiwanego asynchronicznego punktu Anthropic lub OpenAI. Ich udokumentowany rabat jest uwzględniany w estymacji, ale sprawdź bieżące ceny modeli. Adaptery Google i lokalne wracają do generowania sekwencyjnego. Wyślij teraz, a później uruchom to samo polecenie, aby odebrać szkice:

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

Między wysłaniem a odbiorem zachowaj dostawcę, locale i tryb publikacji. Przegląd i `--auto-apply` używają osobnych punktów kontrolnych. CLI odmawia uruchomienia drugiego trybu, gdy pasująca partia nadal oczekuje. Niepewne wysłanie zatrzymuje pracę do uzgodnienia. Częściowe sukcesy są zachowane, a przejściowe niepowodzenia można ponowić. Odbiór ponownie sprawdza brakujące pola; zatwierdzenie sprawdza też migawkę źródła sprzed wysłania. `seo-pro.ai.fill.batch.request_timeout` domyślnie wynosi 120 sekund. Zaplanowany odbiór domyślnie zapisuje szkice.

## Rekordy pochodzenia, migracje i filtrowanie danych {#origin-review-and-filtering}

Wymaga **Core 3.21 i Pro 2.42**. Rdzeń ładuje swoją migrację automatycznie. Opublikuj migracje Pro i wykonaj je na każdej bazie używanej przez modele SEO, zanim zaczniesz generować zapisywane propozycje:

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

Prywatna tabela `seo_ai_proposals` przechowuje wygenerowane wartości i dowody dostawcy/modelu/żądania przy użyciu szyfrowanych rzutowań Laravel. Chroń `APP_KEY` i jego kopię zapasową: utrata klucza uniemożliwia odczyt tych wartości. Identyfikatory rekordów, stan i metadane decyzji pozostają zwykłymi kolumnami bazy. Warianty formularza mogą pozostać `offered` lub `selected` po porzuceniu formularza. Nie ma automatycznego usuwania: ustal zasady przechowywania danych w aplikacji, zachowuj oczekujące szkice i dowody nadal wskazywane przez `seo_meta.ai_provenance` oraz ogranicz dostęp do eksportów bazy i wyników poleceń przeglądu.

Zaakceptowane propozycje formularzy, poprawki panelu i wartości zbiorcze mają oznaczenie pochodzenia pola. Późniejsze edycje Eloquent zachowują `origin: ai` i ustawiają `edited: true`. Oznacza to zmianę wartości, nie weryfikację przez człowieka. Wyczyszczenie pola usuwa znacznik. Wyniki HTML, tablicowe, JSON i Inertia rdzenia ujawniają tylko nazwę pola, pochodzenie i stan edycji, używając w odpowiednich przypadkach własnego znacznika meta `rankbeam:ai-origin`. Identyfikatory generowania i dane dostawcy pozostają prywatne. Nie udostępniaj surowych modeli `SEOMeta` w publicznym API.

Mechanizm rejestruje pochodzenie przy kolejnych zapisach wykonywanych obsługiwanymi ścieżkami, a nie dla historycznej treści ani każdej wersji edycji. Bezpośredni SQL, aktualizacje query buildera i własne renderery mogą omijać te mechanizmy. Jawnie zresetuj pochodzenie, gdy uzasadnia to niezależnie napisana treść zastępcza; zwykłe edycje je zachowują. Własny znacznik nie jest standardowym znakiem wodnym, odporną na manipulacje atrybucją ani deklaracją zgodności z artykułem 50. Rzeczywista jakość wyniku dostawcy i jego własne oznaczenia wymagają osobnej oceny.

Opcjonalnie zaimplementuj `AiPromptFilter` i skonfiguruj `seo-pro.ai.context_filter`. Filtruje złożony prompt użytkownika przed wysłaniem synchronicznym lub wsadowym. Niepowodzenie blokuje wysłanie i zwraca oczyszczony błąd. Instrukcje systemowe pozostają bez zmian. Domyślne `null` **nie usuwa automatycznie danych wrażliwych**. Poniższy przykład zastępuje tylko jedną znaną wartość. Zaimplementuj i przetestuj reguły dopasowane do aplikacji:

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

## Obsługa odpowiedzi {#how-replies-are-handled}

Każde wywołanie zwraca jeden format odpowiedzi niezależny od dostawcy, więc zachowanie pozostaje takie samo między dostawcami, również dodanymi później:

- **Wynik ustrukturyzowany tam, gdzie wspiera go dostawca.** OpenAI (natywne Structured Outputs), Google (Gemini `responseSchema`) i Anthropic (`output_config.format`) mają strukturę JSON egzekwowaną przez API. Odpowiedź niebędąca poprawnym JSON-em daje kontrolowany błąd, nigdy wydobywanie danych ze swobodnego tekstu. Serwer lokalny/zgodny z OpenAI również otrzymuje prośbę o taki format (`response_format`), w miarę możliwości. Serwer ignorujący pole nadal może zwrócić użyteczny tekst, parsowany tolerancyjnie jako rozwiązanie zastępcze. W obu przypadkach otrzymujesz poprawną listę albo jednoznaczny błąd, nigdy częściowo sparsowaną odpowiedź.
- **Ucięcie jest jawnym błędem z instrukcją działania.** Jeśli odpowiedź zostanie ucięta przy limicie tokenów wyjściowych, dostajesz błąd `truncated` z zaleceniem zwiększenia `seo-pro.ai.max_output_tokens`, a nie po cichu skrócony tytuł. Najczęściej dotyczy to **modeli rozumujących**; automatycznie otrzymują one wyższe minimum `reasoning_min_output_tokens`.
- **Przejściowe niepowodzenia są ponawiane automatycznie.** Limit żądań `429` lub `5xx` jest ponawiany z ograniczonym wykładniczym opóźnieniem, z uwzględnieniem nagłówka `Retry-After`, jeśli występuje. Jego wartość jest ograniczona, aby wroga wartość nie zatrzymała żądania. **Deterministyczne** niepowodzenia **nie są** ponawiane: błędny klucz, niepoprawne żądanie, zbyt duże dane, **przekroczenie czasu** lub konto **bez środków lub z wyczerpanym limitem**. Ponawianie konta bez środków tylko marnuje czas opóźnień. Dostosuj lub wyłącz ponowienia blokiem `retry`; ustaw `max_attempts` na `0`, aby je wyłączyć.
- **Błędy mają typy i są oczyszczane.** Każde niepowodzenie ma stabilny kod (`unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`, `content_too_large`, `bad_request`, `truncated`, `content_filtered`, `provider_error`, …) i flagę `retryable` dla błędów przejściowych. Komunikat jest krótki i oczyszczony. **Zwykła ścieżka aplikacji nie pokazuje ani nie loguje surowej treści odpowiedzi dostawcy; opisane wyżej opcjonalne narzędzia oceny zachowują jednak odpowiedzi jako dowody.** W Filament błąd jest renderowany w ostylowanej części okna z dopasowaną wskazówką kolejnego kroku dla częstych przypadków; zobacz [Rozwiązywanie problemów](#troubleshooting).

## Rozwiązywanie problemów {#troubleshooting}

Każde niepowodzenie daje komunikat w kontekście i nie przerywa podstawowych działań, z typowanym kodem i oczyszczonym tekstem. Najczęstsze przypadki i właściwe rozwiązania:

| Objaw (kod błędu) | Znaczenie | Rozwiązanie |
|---|---|---|
| **`quota_exceeded`** — *„the provider account is out of credit or quota”* | Klucz jest poprawny, ale **konto API nie ma środków lub wyczerpało dostępny limit**. To nie ograniczenie częstotliwości; ponowienie nie pomoże. Komunikaty zależą od dostawcy: Anthropic *„credit balance is too low”*, OpenAI *„exceeded your current quota… check your plan and billing”* (`insufficient_quota`), Google *„prepayment credits are depleted”*. | Doładuj środki lub włącz płatności w konsoli dostawcy albo przejdź na model **lokalny** bez opłaty API dostawcy. Pamiętaj, że **subskrypcja** Claude/ChatGPT nie finansuje **API**. |
| **`unauthorized`** — *„authentication failed”* | Brakuje klucza, jest błędny lub nie pasuje do skonfigurowanego dostawcy. | Sprawdź klucz w zmiennej nazwanej przez `seo-pro.ai.api_key_env` (domyślnie `SEO_PRO_AI_API_KEY`): musi być ustawiony, aktualny i zgodny z `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`** — *„the provider rate limit was reached”* | Rzeczywisty, **przejściowy** limit częstotliwości, najpierw automatycznie ponawiany. | Poczekaj i ponów albo przejdź na model **lokalny**, z uwzględnieniem wydajności serwera, aby uniknąć limitu dostawcy. Zwiększ `seo-pro.ai.fill.throttle_ms` przy zbiorczych przebiegach na niskim poziomie konta. |
| **`timeout`** — *„the request timed out”* | Dostawca nie odpowiedział w czasie `seo-pro.ai.timeout` (domyślnie 15 s). Częste dla **wolnego lokalnego modelu rozumującego**. | Zwiększ go przez `SEO_PRO_AI_TIMEOUT`; dla Ollama ustaw też `['think' => false]` w `seo-pro.ai.local.extra_body`. |
| **`truncated`** — *„hit the max_output_tokens limit”* | Odpowiedź osiągnęła budżet wyjścia, być może obejmujący ukryte rozumowanie. | Zwiększ `seo-pro.ai.max_output_tokens` (modele rozumujące mogą potrzebować 2000+) lub potwierdź dopasowanie modelu do wzorca `reasoning_models`, aby działało minimum. |
| **`content_too_large`** (HTTP 413) | Wysłana treść strony przekroczyła limit dostawcy. | Obniż `seo-pro.ai.max_input_chars`, aby wysyłać krótszy fragment. |
| **`bad_request`** | Niepoprawne żądanie, zwykle **nazwa modelu** niedostępnego dla konta lub nieobsługiwany parametr. | Sprawdź, czy `SEO_PRO_AI_MODEL` wskazuje model dostępny dla Twojego klucza/serwera u skonfigurowanego dostawcy. |
| **`content_filtered`** | Filtr bezpieczeństwa dostawcy odmówił odpowiedzi. | Przejrzyj treść i wskazówki dostawcy; nie powtarzaj automatycznie odrzuconego żądania. |

::: tip Lokalna inferencja nadal wymaga działającego serwera
`SEO_PRO_AI_PROVIDER=local` przy własnym hostingu inferencji eliminuje problemy ze środkami u dostawcy chmurowego. Sprzęt, model, zgodność API, limit czasu i wydajność nadal mają znaczenie. Zdalna brama skonfigurowana z tym adapterem może wymagać klucza i płatności.
:::

## Co opuszcza Twój serwer {#what-leaves-your-server}

Dokładnie poniższe dane, tylko do skonfigurowanego dostawcy i tylko po jawnej akcji: kliknięciu lub uruchomieniu polecenia.

- *Propozycje*: nazwa bazowa klasy modelu i klucz (np. „Post #3”), bieżący rozstrzygnięty tytuł i opis, kanoniczny URL oraz fragment zwykłego tekstu po usunięciu HTML, ograniczony do `max_input_chars` (domyślnie 6000 znaków).
- *Wyjaśnienia problemów*: typ, ważność, pole, komunikat i docelowy URL problemu, a gdy model jest dostępny — także jego klasa/klucz, rozstrzygnięty tytuł/opis, kanoniczny URL i ograniczony fragment zwykłego tekstu.
- *Przeredagowanie opisu*: ten sam minimalny kontekst strony co przy propozycji oraz, jeśli podano, typ i komunikat problemu skanowania.
- *Propozycja danych strukturalnych*: ten sam minimalny kontekst strony co przy propozycji. Model zwraca tylko typ i wartości pól końcowych; JSON-LD jest składane lokalnie.

Pakiet nie zbiera celowo do promptów danych odwiedzających, adresów IP, nagłówków żądań ani danych uwierzytelniających i nie wysyła pełnego HTML. **Same pola treści i fragmenty mogą zawierać informacje wrażliwe**; sprawdź, co udostępnia Twoja aplikacja. Dane uwierzytelniające dostawcy służą do uwierzytelnienia żądania. Dokumentem referencyjnym przetwarzania danych jest SECURITY.md repozytorium Pro.

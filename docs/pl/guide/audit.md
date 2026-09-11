---
description: "Uruchom php artisan seo:audit, aby otrzymać tabelę wyników pozytywnych, ostrzeżeń i błędów SEO dla każdej strony — w tym samym procesie, bez kolejki, licencji ani sieci. Bezpłatna funkcja rdzenia."
---

# Bezpłatny audyt SEO (`seo:audit`) {#free-seo-audit-seo-audit}

`php artisan seo:audit` bezpłatnie odpowiada jednym poleceniem na jedno pytanie: **co jest teraz nie tak z moim SEO?** Przechodzi przez modele `HasSEO` w tym samym procesie — **bez kolejki, licencji ani sieci** — i wyświetla tabelę **wyników pozytywnych / ostrzeżeń / błędów** dla każdej strony wraz z podsumowaniem.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## Co sprawdza {#what-it-checks}

Audyt uruchamia tylko klasę wykonawczą **metadata** — kontrole możliwe na podstawie samego modelu i [resolvera](/pl/concepts/resolver-precedence), bez pobierania strony:

| Kontrola | Kody |
|---|---|
| Obecność tytułu / opisu (z uwzględnieniem wartości zastępczych) | `missing_title`, `missing_description` |
| Obecność obrazu OG (z uwzględnieniem wartości zastępczych) | `missing_og_image` |
| Długość tytułu / opisu | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Zduplikowany tytuł / opis w witrynie | `duplicate_title`, `duplicate_description` |
| Konflikty robots i podejrzane noindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Format kanonicznego URL-a / inna domena / wspólny adres / brak zabezpieczenia | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Gotowość do odpowiedzi (AEO) — dane strukturalne artykułu | `aeo_missing_author`, `aeo_article_missing_date` |
| Ustawienie głównych słów kluczowych (po włączeniu) | `missing_focus_keyword` |
| Alternatywy hreflang (rejestr rdzenia, gdy strona ma jakiekolwiek) | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

Większość kodów występuje także w skanie Pro, ale rejestry są oddzielne. W szczególności rdzeń używa `hreflang_missing_self`, a Pro — `hreflang_missing_self_reference`; `hreflang_duplicate_code` jest uwagą w rdzeniu i ostrzeżeniem w Pro. Wspólna nazwa nie oznacza identycznego zakresu ani wagi. `blank_explicit_override` należy do rejestru rdzenia. Długość korzysta z [zalecanych długości edytora według systemu pisma](/pl/guide/multilingual#title-and-description-budgets-per-script) — 60/160 znaków dla tekstu łacińskiego, około 30/80 dla CJK, liczonych jako grafemy i porównywanych z **rozstrzygniętą** wartością wraz z sufiksem — więc audyt nigdy nie przeczy licznikom znaków w [edytorze Filament](/pl/guide/filament). Kontrole hreflang działają na liście po zastosowaniu zasad `seo.hreflang` (tej samej, którą generują tagi i mapa witryny); wzajemność wymaga skanowania witryny i pozostaje w Pro.

Kontrole **gotowości do odpowiedzi (AEO)** uruchamiają się tylko wtedy, gdy strona deklaruje JSON-LD typu artykuł (`Article`, `BlogPosting`, `NewsArticle`, …), któremu brakuje sygnału ułatwiającego odczytanie artykułu z danych strukturalnych — encji `author` (jawne autorstwo / pochodzenie) lub `datePublished` / `dateModified` (jawna chronologia). Strona bez artykułu nigdy nie jest oznaczana, więc audyt nie zgłasza uwag tam, gdzie AEO nie ma zastosowania. Są to zalecenia (poziom uwagi), nieuwzględniane w ocenie Pro 0–100.

## Czego *nie* sprawdza — granice możliwości {#what-it-does-not-check-—-the-capability-boundary}

Bezpłatny audyt w tym samym procesie nigdy nie zastąpi pełnego skanu Pro, a polecenie informuje o tym przy każdym uruchomieniu. **Nie** wykonuje:

- **Kontroli wyrenderowanego HTML** — `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content`, `mixed_content`. Wymagają HTML udostępnianego przez stronę.
- **Sieciowych kontroli rzeczywistych kanonicznych URL-i** — `canonical_target_broken` / `_redirect` / `_noindex`. Wymagają zabezpieczonego pobrania przez połączenie wychodzące.
- **Liczbowej oceny 0–100.** Ocena jest funkcją Pro, zapisywaną w rekordzie wyniku skanu z wersjonowanymi kryteriami — zobacz [Ocena SEO](/pl/pro/scoring).

Te funkcje zawiera **skan Pro** — zobacz pełny [rejestr problemów](/pl/pro/scan-issues).

## Wybór zakresu audytu {#choosing-what-to-audit}

Domyślnie polecenie audytuje modele wymienione w `seo.audit.models`, a w razie ich braku korzysta z `seo.sitemap.models`:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Możesz też jawnie przekazać modele:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Opcje {#options}

| Opcja | Działanie |
|---|---|
| `--model=` | Klasa modelu `HasSEO` do audytu (można powtarzać). Nadpisuje konfigurację. |
| `--locale=` | Rozstrzyga dane SEO dla tych ustawień regionalnych (domyślnie ustawienia aplikacji). |
| `--limit=` | Maksymalna liczba rekordów do audytu na model (`0` = wszystkie). |
| `--issues-only` | Wyświetla tylko strony z przynajmniej jednym problemem. |
| `--strict` | Kończy się kodem różnym od zera po wykryciu dowolnego problemu — dla CI. |
| `--json` | Generuje JSON do odczytu maszynowego (strony, podsumowanie, zakres kontroli) zamiast tabeli. |

### Warunek kontroli CI {#ci-gate}

`--strict` zmienia audyt w kontrolę procesu CI:

```bash
php artisan seo:audit --strict
```

Kończy się kodem `1`, jeśli dowolna strona zgłasza ostrzeżenie lub błąd, oraz `0`, gdy każda audytowana strona przechodzi kontrolę.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Główne słowa kluczowe {#focus-keywords}

Uwaga `missing_focus_keyword` jest **domyślnie wyłączona**. Pojawia się dopiero po włączeniu procesu pracy z głównymi słowami kluczowymi:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Skan Pro odczytuje **tę samą** flagę, więc audyt, skan i przypomnienie w edytorze Pro zawsze pozostają zgodne. Ustaw słowa kluczowe strony w [polu głównych słów kluczowych Filament](/pl/guide/filament) lub przez `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Gdy wartość jest inna niż oczekiwana: `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` mówi, *co jest nie tak*; [`seo:explain`](/pl/guide/explain) wyjaśnia, *dlaczego pole zostało rozstrzygnięte właśnie tak* — która warstwa (konfiguracja / domyślna / obliczana / jawna) ustawiła każdą wartość, co nadpisała i jakie dalsze przetwarzanie (sufiks tytułu, usuwanie parametrów zapytania z wyprowadzanego kanonicznego URL-a, ochrona przed indeksowaniem) zmieniło ją później. Sięgnij po to polecenie, gdy wynik audytu lub wyrenderowany tag Cię zaskoczy:

```bash
php artisan seo:explain "App\Models\Post" 42
```


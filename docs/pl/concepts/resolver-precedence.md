---
description: "Jak Rankbeam rozstrzyga każdą wartość SEO: sześć warstw scalanych według priorytetu, wyższe warstwy wygrywają, a null nigdy nie nadpisuje wartości, więc każda strona otrzymuje sensowny wynik."
---

# Priorytety resolvera {#resolver-precedence}

Każda wynikowa wartość SEO — tytuł, opis, kanoniczny URL, robots i obrazy — powstaje, gdy `SEOResolver` scala **sześć warstw**. Wyższe warstwy wygrywają, a `null` nigdy nie nadpisuje wartości z niższej warstwy. Dzięki temu każda strona zawsze otrzymuje sensowny wynik.

## Sześć warstw {#the-six-layers}

Od najniższej (zawsze obecnej) do najwyższej (zawsze wygrywającej):

| # | Warstwa | Źródło | Typowe zastosowanie |
|---|---|---|---|
| 1 | **Konfiguracja witryny** | `config/seo.php` (`site_name`, `title_suffix`, `default_og_image`, `default_robots`, …) | Wartości domyślne dla całej marki |
| 2 | **Globalne wartości domyślne z bazy** | Rekordy `seo_defaults` bez typu modelu | Edytowalne wartości domyślne dla całej witryny, bez wdrażania kodu |
| 3 | **Wartości domyślne dla typu modelu** | Rekordy `seo_defaults` przypisane do klasy modelu | „Wszystkie produkty otrzymują ten obraz OG” |
| 4 | **Wartości domyślne dla trasy** | Rekordy `seo_defaults` przypisane do nazwy trasy | Strony statyczne (`home`, `contact`) bez modelu |
| 5 | **Wartości wyliczone** | Wyprowadzone z atrybutów samego modelu | Zastępczy tytuł z `title`, opis z `excerpt`/`body` itd. |
| 6 | **Wartości jawne** | Rekord `seo_meta` modelu (`saveSEO()`) | Wartości ustawiane ręcznie przez redaktorów |

```php
use Rankbeam\Seo\Facades\SEO;

$seo = SEO::resolve($post);          // model page: layers 1-3 + 5-6
$seo = SEO::forRoute('contact');     // route page: layers 1-2 + 4
```

Wynik to niemutowalny obiekt wartości `SEOData`, z którego korzystają wszystkie renderery (Blade, tablica, Inertia).

## Wyliczane wartości zastępcze (warstwa 5) {#computed-fallbacks-layer-5}

Gdy nie ma jawnej wartości, resolver wyprowadza ją z modelu:

- **Tytuł** — atrybut `title`/`name` modelu.
- **Opis** — pierwszy atrybut z `seo.computed.description_fields` (domyślny łańcuch: `excerpt`, `summary`, `description`, `intro`, `lead`, `teaser`, `content`, `body`, `text`, `article`), który zawiera znaczącą treść. HTML jest usuwany, encje dekodowane, a tekst skracany na granicy słowa (`seo.computed.description_max_length`, domyślnie 160 — bez wielokropka).
- **Robots** — z metody `getSEORobots()` modelu lub atrybutu `is_indexable` (zobacz [Sterowanie robots i możliwością indeksowania](#controlling-robots-and-indexability)).
- **Wartości wyprowadzone z URL-a** — adres kanoniczny i `og:url` z `getUrlForSEO()`.

## Sterowanie robots i możliwością indeksowania {#controlling-robots-and-indexability}

Obsługa `noindex` dla każdego modelu jest wbudowana; nie potrzebujesz dodatkowego pakietu ani specjalnego przygotowywania kolumn. Cecha `HasSEO` nie *deklaruje* metody robots (jest ona opcjonalna), więc łatwo ją przeoczyć. Resolver uwzględnia jednak trzy źródła, od najwyższego priorytetu:

| Priorytet | Źródło | Przykład |
|---|---|---|
| 1 | **Jawne `seo_meta.robots`** | `$page->saveSEO(['robots' => 'noindex,follow'])` |
| 2 | **Metoda `getSEORobots(): ?string`** w modelu | Zwróć `'noindex, nofollow'` albo `null`, aby przejść do kolejnego źródła |
| 3 | **Atrybut `is_indexable`** (kolumna lub akcesor) | Wartość falsy ⇒ `noindex, nofollow`; truthy ⇒ `index, follow` |

```php
class Page extends Model
{
    use HasSEO;

    // Option A: let the resolver derive robots from a boolean flag.
    //   Schema::table('pages', fn ($t) => $t->boolean('is_indexable')->default(true));

    // Option B: compute it from your own state.
    public function getSEORobots(): ?string
    {
        return $this->status === 'draft' ? 'noindex, nofollow' : null;
    }
}

// Option C: set it explicitly per page (wins over A and B).
$page->saveSEO(['robots' => 'noindex, follow']);
```

### Co rzeczywiście trafia na stronę {#what-actually-renders}

Zanim rozstrzygnięta dyrektywa trafi do `<head>`, podlega **regule generowania tagu**: tag `<meta name="robots">` jest generowany **tylko wtedy, gdy dyrektywa różni się od `default_robots`** (domyślnie `index,follow`). Zatem:

- strona **możliwa do indeksowania** (wynik `index, follow`) **nie generuje tagu robots** — robot interpretuje jego brak dokładnie jako index,follow;
- strona **z dyrektywą zakazującą indeksowania** generuje `<meta name="robots" content="noindex, nofollow">`;
- każda odmienna dyrektywa (`noindex`, `max-snippet:-1`, `unavailable_after`, …) jest generowana **dosłownie**, z zachowaniem wpisanych odstępów.

Ustaw `seo.robots.emit_default = true`, aby zawsze generować tag. Szczegóły opisuje [polityka renderowania robots](/pl/reference/configuration#robots-rendering-policy).

## Reguły stosowane po rozstrzygnięciu {#policies-applied-after-resolution}

Działają niezależnie od tego, która warstwa dostarczyła wartość:

- **Sufiks tytułu** — `title_suffix` jest dopisywany, jeśli rozstrzygnięty tytuł jeszcze się nim nie kończy. Jeśli domyślny szablon trasy zawiera już markę, zakończ go sufiksem, aby uniknąć „Marka — X | Marka”.
- **Usuwanie parametrów z kanonicznego URL-a** — z *wyprowadzonych* adresów kanonicznych (URL modelu / bieżący URL) usuwany jest ciąg zapytania, z wyjątkiem zachowywanych kluczy z [`canonical.query_whitelist`](/pl/reference/configuration#canonical-urls) (np. `page` dla stronicowanych archiwów). *Jawnie ustawione* kanoniczne URL-e pozostają bez zmian.
- **Bezwzględne adresy obrazów społecznościowych** — `og:image` i `twitter:image` zawsze otrzymują bezwzględne URL-e (wymaga tego specyfikacja Open Graph), nawet gdy zapisana wartość jest ścieżką względną.

## Sprawdzanie, która warstwa wygrała {#inspecting-which-layer-won}

[Pakiet Filament](/pl/guide/filament) pokazuje źródło każdego pola: Ręcznie / Z treści / Domyślne dla typu modelu / Domyślne globalne / Konfiguracja witryny / Wyprowadzone z URL. W kodzie `SEOWarningEvaluator` udostępnia to samo rozróżnienie wartości ręcznej i zastępczej, aby można było zbudować własne wskaźniki w panelu.

---
description: "Powiąż możliwość indeksowania ze środowiskiem Laravel, aby kopie stagingowe i lokalne nie trafiały do wyszukiwarek — poza listą dozwolonych środowisk wymuszane jest noindex i blokowanie robotów."
---

# Ochrona przed indeksowaniem (zabezpieczenie poza produkcją) {#indexing-guard-non-production-safety-net}

Przedostanie się stagingowej lub lokalnej kopii witryny do Google to jeden z najczęstszych i najbardziej szkodliwych błędów SEO: zduplikowana treść konkuruje z właściwymi stronami, prywatne środowisko trafia do indeksu, a porządkowanie przez narzędzie usuwania URL-i trwa tygodniami. Typową przyczyną jest `noindex` istniejące tylko w `.env`, o którego ustawieniu zapomniano, albo reguła robots nadpisana przy wdrożeniu.

**Ochrona przed indeksowaniem** utrudnia taki błąd na poziomie konstrukcji aplikacji. Powiąż możliwość indeksowania ze *środowiskiem* Laravel zamiast z flagą, o której ktoś musi pamiętać: gdy aplikacja działa poza listą dozwolonych środowisk, każda strona otrzymuje wymuszone `noindex,nofollow`, zarządzany `robots.txt` zabrania dostępu wszystkim robotom, a `seo:audit` wyraźnie o tym informuje.

To bezpłatna funkcja rdzenia.

## Co robi aktywna ochrona {#what-it-does-when-active}

Gdy `app()->environment()` **nie** znajduje się w `seo.indexing_guard.allowed_environments` (i ochrona jest włączona), automatycznie dzieją się cztery rzeczy:

1. **Resolver wymusza `noindex,nofollow` na każdej stronie.** Jest to stosowane *ponad* całym [łańcuchem pierwszeństwa](/pl/concepts/resolver-precedence) — nadpisuje nawet jawną wartość `robots` zapisaną dla strony w `seo_meta`.
2. **Nagłówek HTTP `X-Robots-Tag: noindex,nofollow`** jest wysyłany z każdą odpowiedzią przechodzącą przez aplikację — zobacz poniżej [Odpowiedzi inne niż HTML](#non-html-responses-pdfs-feeds-images).
3. **`SEO::robotsTxt()->build()` generuje `robots.txt` zabraniający dostępu wszystkim robotom** (oraz `ai.txt`) — zwykłe `User-agent: *` / `Disallow: /`. Dotyczy to zarówno polecenia `seo:robots-txt`, jak i opcjonalnej [trasy dynamicznej](/pl/guide/ai-crawlers).
4. **`seo:audit` wyświetla wyraźny baner**, więc stan „wszystko ma noindex” nigdy nie zaskakuje podczas czytania raportu.

W dozwolonych środowiskach (domyślnie `production`) ochrona jest całkowicie **nieaktywna** — nie zmienia wyniku, renderowanie jest identyczne bajtowo.

## Odpowiedzi inne niż HTML (PDF-y, kanały, obrazy) {#non-html-responses-pdfs-feeds-images}

Wymuszony **metatag** `robots` dociera tylko do robotów parsujących HTML — PDF, kanał RSS/Atom, obraz lub inna odpowiedź niebędąca HTML nie zawiera `<head>`. Dlatego aktywna ochrona wysyła tę samą dyrektywę także jako nagłówek HTTP przez globalne middleware:

```http
X-Robots-Tag: noindex,nofollow
```

Nagłówek i metatag pochodzą z tego samego źródła, więc nigdy nie mogą być sprzeczne. Jest to **domyślnie włączone w ramach ochrony** (sama ochrona wymaga włączenia i jest nieaktywna w dozwolonych środowiskach); wyłącz tę opcję, aby pozostać przy samym metatagu:

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Middleware jest rejestrowane **tylko po włączeniu ochrony**, więc pakiet z wyłączoną ochroną niczego nie dodaje do stosu.

::: warning Pliki statyczne omijają PHP
Plik zwracany przez serwer WWW bezpośrednio z `public/` nigdy nie trafia do Laravel, więc nie może otrzymać tego nagłówka. Chroń takie pliki na brzegu sieci (konfiguracja serwera WWW / CDN). Ta funkcja obejmuje wszystko, co przechodzi przez aplikację.
:::

## Dlaczego nadpisuje jawną wartość robots {#why-it-overrides-an-explicit-robots-value}

W każdym innym miejscu Rankbeam wygrywa jawnie zapisana wartość — to cały sens łańcucha pierwszeństwa. Ochrona jest jedynym świadomym wyjątkiem i znajduje się *ponad* warstwą jawną, ponieważ ryzyko działa tu tylko w jedną stronę:

- Baza stagingowa jest zwykle kopią produkcyjnej, więc strona z zapisanym `index,follow` przeniosłaby tę dyrektywę na staging i poprosiła o indeksowanie.
- **Błędne zaindeksowanie stagingu jest katastrofą; błędne oznaczenie go jako `noindex` niczego nie zmienia.** Ochrona wyznacza więc granicę, której zapisana wartość nie może przekroczyć — dokładnie w tych środowiskach, których i tak nigdy nie chcesz indeksować.

## Włączanie {#enabling-it}

Ochrona jest dostarczana jako **wyłączona**, więc instalacja lub aktualizacja pakietu nigdy nie zmienia bez Twojej zgody wyniku renderowania środowiska nieprodukcyjnego (ta sama zasada identyczności bajtowej do czasu włączenia co dla [`blank_is_unset`](/pl/concepts/resolver-precedence) resolvera i generowanych obrazów OG). Włącz ją jedną linią:

```dotenv
SEO_INDEXING_GUARD=true
```

Przy domyślnej liście dozwolonych środowisk `production` pozostaje bez zmian, więc możesz utrzymywać ochronę włączoną we wspólnej konfiguracji. Sprawdź, czy lista zawiera każde środowisko, które chcesz indeksować. Włączenie jest **zdecydowanie zalecane** i rozważane jako ustawienie domyślne w Core 4.

Wyłącz ją tą samą jedną linią:

```dotenv
SEO_INDEXING_GUARD=false
```

## Wybór środowisk, które mogą być indeksowane {#choosing-which-environments-may-index}

Domyślnie dozwolone jest tylko `production`. Nadpisz listę zmienną środowiskową z wartościami rozdzielonymi przecinkami:

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

Lub w `config/seo.php`:

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

Wpisy są dopasowywane przez `Str::is()`, więc działają **symbole wieloznaczne** — `'prod*'` pasuje do `production` i `prod-eu`:

```php
'allowed_environments' => ['prod*'],
```

**Pusta** lista oznacza, że *żadne* środowisko nie może być indeksowane — ochrona jest aktywna wszędzie (bezpieczny kierunek w razie błędu). Zwróć uwagę, że pusta wartość zmiennej `SEO_INDEXING_GUARD_ALLOWED` lub wartość z samymi białymi znakami przywraca `['production']`, aby taki pusty wpis nie wyłączył po cichu indeksowania produkcji; wpisz jawne `[]` w konfiguracji, jeśli rzeczywiście chcesz ochrony „wszędzie”.

## Weryfikacja {#verifying-it}

`seo:audit` pokazuje baner, a w `--json` przekazuje stan do odczytu maszynowego:

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

A tak wygląda udostępniany/generowany `robots.txt` w chronionym środowisku:

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Zakres {#scope}

Ochrona kontroluje **dyrektywy indeksowania** — metatag `robots`, nagłówek `X-Robots-Tag` i `robots.txt`. Nie zmienia tytułów, opisów, kanonicznych URL-i ani danych strukturalnych i jest niezależna od [zasad renderowania robots](/pl/concepts/resolver-precedence) (`seo.robots.emit_default`): ponieważ `noindex,nofollow` różni się od domyślnej wartości witryny, zawsze jest generowane jako tag.

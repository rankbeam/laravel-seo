---
description: "seo:explain pokazuje, która warstwa resolvera ustawiła każde pole SEO i jakie wartości zastąpiła — tylko odczyt, bez sieci i licencji. Wyjaśnia nieoczekiwany tytuł lub dyrektywę robots."
---

# Wyjaśnianie wyników resolvera (`seo:explain`) {#explain-the-resolution-seo-explain}

Rankbeam rozstrzyga dane SEO strony według [warstwowego łańcucha priorytetów](/pl/concepts/resolver-precedence): konfiguracja, wartości domyślne z bazy (globalne / dla typu modelu / dla trasy), wyliczone wartości modelu i na końcu jawne `seo_meta`. Potem następuje przetwarzanie końcowe (sufiks tytułu, adres kanoniczny, zamiana ścieżek obrazów na bezwzględne URL-e) oraz [ochrona przed indeksowaniem](/pl/guide/indexing-guard). Jeśli wygenerowany tag `<title>` lub `robots` nie jest tym, czego oczekujesz, **`seo:explain` pokaże dokładnie, która warstwa ustawiła każde pole i co zastąpiła.**

Polecenie tylko odczytuje dane, nie wymaga sieci ani licencji i nie implementuje ponownie scalania: przypisanie źródeł pochodzi z wkładu poszczególnych warstw resolvera, a końcowe wartości z rzeczywistego resolvera. Wyjaśnienie nie może więc rozminąć się z generowanym wynikiem.

## Użycie {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

Model musi używać cechy [`HasSEO`](/pl/guide/quickstart).

## Jak czytać wynik {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by** — zwycięska warstwa (o najwyższym priorytecie spośród tych, które ustawiły wartość różną od null) lub `post-processing`, gdy żadna warstwa nie ustawiła pola, ale wartość została *wyprowadzona* (adres kanoniczny z URL-a żądania/modelu, og:url z adresu kanonicznego, bezwzględny URL obrazu).
- **Overrode** — wszystkie warstwy o niższym priorytecie, które dostarczyły wartość i przegrały, podane w kolejności. Widać więc, co zostało przesłonięte.
- **↳ notes** — przetwarzanie, które zmieniło wartość po scaleniu warstw: sufiks tytułu, usunięcie parametrów zapytania z adresu kanonicznego, wyprowadzenie og:url, bezwzględne URL-e obrazów i ochrona przed indeksowaniem wymuszająca `noindex` ponad wszystkimi warstwami.

::: tip og:type i twitter:card
Te dwa pola mają domyślne wartości frameworka różne od null (`website` / `summary_large_image`). Wygrywa więc najwyższa warstwa, która je ustawi — zwykle `computed` — zamiast `config`. Strona bez zapisanego rekordu `seo_meta` nie wnosi dla nich żadnych wartości, więc wyliczone `og:type`, np. `article`, nigdy nie zostanie przesłonięte przez samo `website`. To odpowiada rzeczywistemu działaniu scalania.
:::

## Rozstrzyganie na poziomie witryny {#site-level-resolution}

Zgodnie z [uzupełnieniem dotyczącym rejestru konfiguracji witryny](/pl/concepts/resolver-precedence) `seo:explain` podaje też wartości dla całej witryny, których pochodzenie często budzi wątpliwości: **które źródło ustawiło kanoniczną nazwę hosta, nazwę witryny i domyślne locale**:

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Najbardziej warto sprawdzić kanoniczną nazwę hosta. Błędny host (pozostawiony `localhost`, `http://` w witrynie `https` albo URL aplikacji niezgodny z URL-em modelu) to typowa przyczyna błędów kanonicznego adresu wskazującego na samą stronę.

## Wynik JSON {#json-output}

`--json` zwraca pełny zapis rozstrzygania: `target`, pola `winner` / `losers` / `final` / `notes` dla każdej wartości oraz rejestr `site_level`. Możesz użyć go w narzędziach lub CI:

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## Zobacz też {#see-also}

- [Priorytety resolvera](/pl/concepts/resolver-precedence) — pełny łańcuch śledzony przez `seo:explain`.
- [Bezpłatny audyt SEO](/pl/guide/audit) — `seo:audit` znajduje *problemy*, a `seo:explain` pokazuje, *dlaczego dana wartość jest właśnie taka*.

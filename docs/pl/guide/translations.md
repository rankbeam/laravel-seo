---
description: "Wyniki audytu, ostrzeżenia edytora i etykiety Filament w Rankbeam używają języka aplikacji. Opublikuj pliki językowe, by nadpisać tekst, lub dodaj tłumaczenie."
---

# Tłumaczenia {#translations}

Każdy tekst wyświetlany użytkownikowi przez pakiety — wyniki audytu, ostrzeżenia na żywo pod polami Filament, etykiety, podglądy i raporty — jest wpisem w plikach językowych Laravel. Pakiety korzystają z `app()->getLocale()`: panel działający po włosku wyświetla tekst po włosku bez dodatkowej konfiguracji.

**Kody** problemów i ostrzeżeń (`missing_title`, `title_too_long`, …) nigdy się nie zmieniają i nie są tłumaczone. Tłumaczone jest wyłącznie zdanie przypisane do kodu.

Pakiety zawierają język angielski, włoski (wcześniejsze teksty sprawdzono; zmienione wymagają ponownego przeglądu) oraz wstępne tłumaczenia na niemiecki, francuski, hiszpański, portugalski brazylijski, niderlandzki, turecki, rosyjski i polski (Tier 1). Od core 3.16 / Filament 1.10 / Pro 2.35 dostępne są też japoński, chiński uproszczony (`zh_CN`), chiński tradycyjny (`zh_TW`), koreański, grecki, ukraiński i czeski (Tier 2). Dokładny status każdego języka podaje [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md); przegląd przez rodzimego użytkownika języka zmienia wstępne tłumaczenie w język objęty wsparciem.

## Nadpisz tekst {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Następnie edytuj `lang/vendor/seo/{locale}/seo.php` (oraz sąsiednie katalogi dla pozostałych pakietów). Zachowane klucze nadpisują teksty pakietu; pozostałe korzystają z pliku pakietu, a następnie z wersji angielskiej.

## Dodaj tłumaczenie {#contribute-a-language}

Skopiuj plik `en` do swojego języka, przetłumacz wartości, zachowaj każdy `:placeholder`, uruchom zestaw testów (test zgodności wykrywa każdy brakujący lub nadmiarowy klucz, pustą wartość i utracony znacznik zastępczy), a następnie otwórz pull request. Pełne zasady i słownik znajdziesz w [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md).

## Czego celowo nie tłumaczymy {#what-is-not-translated-on-purpose}

Interfejs CLI domyślnie używa angielskiego. Ustaw `seo.cli_locale` / `SEO_CLI_LOCALE` lub przekaż `--display-locale=it`, by przetłumaczyć obsługiwane komunikaty i podsumowania audytu. Pro ma własne ustawienie `seo-pro.cli_locale`. Język wyświetlania jest niezależny od języka treści wybranego przez `--locale`.

- Pomoc poleceń, diagnostyka utrzymaniowa i wynik `seo:explain` pozostają po angielsku; etykiety PASS/WARN/FAIL są stałe.
- Renderowany HTML (`<meta>`, JSON-LD) używa języka twojej treści, nigdy języka pakietu.
- Kody problemów oraz klucze JSON i kody statusu pozostają stałymi identyfikatorami. Etykiety dla użytkownika w wyniku `--json` mogą być tłumaczone; integracje powinny odczytywać klucze i kody.

## Druga strona: język twojej treści {#the-other-half-your-content-s-language}

Ta strona dotyczy języka, którym posługuje się *pakiet*. To, jak rozpoznaje język twojej *treści* — długości tytułów zależne od pisma, skracanie, wielkość liter, zasady hreflang, `inLanguage`, regionalne wyszukiwarki i fonty obrazów OG — opisuje [Treść wielojęzyczna](/pl/guide/multilingual).

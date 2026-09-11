---
description: "Prześledź rzeczywiste skanowanie Rankbeam Pro: sprawdź brakujący opis, zapisz poprawkę w Filament, przeskanuj ponownie i pobierz wygenerowany przykładowy raport PDF."
---

# Od skanowania do zweryfikowanej poprawki {#from-a-scan-to-a-verified-fix}

Skanowanie wykryło brak opisu w przykładowym artykule. Dodaliśmy opis w Filament, przeskanowaliśmy stronę ponownie i wygenerowaliśmy raport pokazujący poprawkę.

To zrzuty z działającego lokalnie dema Merchant z 9 września 2026 r. Treść pochodzi z przykładowych danych seedera. Oba skanowania i raport wygenerowano na potrzeby tego przewodnika. Nie wypełniono wcześniej żadnego historycznego trendu. Aplikacja korzysta z Laravel 12 i Filament 4 oraz rdzenia Rankbeam, bezpłatnego edytora i silnika Pro.

**[Pobierz wygenerowany raport (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf)**

## Przeskanuj zarejestrowane strony {#scan-the-registered-pages}

Po [zainstalowaniu Pro](/pl/pro/installation) i zarejestrowaniu celów skanowania uruchom:

```bash
php artisan seo-pro:scan --sync
```

Demo rejestruje 18 rekordów treści i trzy trasy. Pierwsze skanowanie przetworzyło wszystkie 21 celów bez niepowodzeń i wykryło 20 problemów: sześć ostrzeżeń i 14 uwag.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="Pierwsze zakończone skanowanie: 21 celów, 20 problemów, sześć ostrzeżeń i 14 uwag." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Zrzuty ekranu wykonano w rozdzielczości 2×. Otwórz wybrany zrzut, aby obejrzeć go w pełnym rozmiarze.*

## Sprawdź jeden problem {#inspect-one-issue}

W **SEO Dashboard** otwórz **Page issues** przy odpowiednim wierszu. Dla artykułu „Behind the Scenes: Our Product Photography” wynik wskazuje brak `description`, URL strony i skanowanie, które wykryło problem.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Okno Page issues wskazuje Post 5, jego URL i brakujące pole opisu." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Zapisz opis {#save-the-description}

Otwórz artykuł w **Posts**, wypełnij **SEO description** i zapisz. [Bezpłatny edytor Filament](/pl/guide/filament) pokazuje wprowadzony tekst w podglądzie wyszukiwania i oznacza jego źródło jako **Manual**. W tym przykładzie opis ma 142 znaki, a tytuł nadal pochodzi z artykułu.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="Zapisany opis SEO i licznik wskazujący 142 znaki." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="Podgląd na żywo używa wprowadzonego opisu, oznaczonego jako Manual." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Zapisanie pola i weryfikacja poprawki to osobne kroki. Ocena aktualizuje się po kolejnym skanowaniu. Bez Filament zapisz tę samą wartość metodą `saveSEO()` swojego modelu.

## Przeskanuj ponownie i sprawdź zmiany {#rescan-and-check-what-changed}

Uruchom ponownie to samo polecenie:

```bash
php artisan seo-pro:scan --sync
```

Panel oznacza teraz ten konkretny problem jako **Fixed**. Pozostałych 19 problemów pozostaje otwartych.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Zapisane porównanie skanowań: zero nowych problemów, zero regresji, jeden naprawiony i 19 nadal otwartych." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Kontrola | Przed | Po |
|---|---|---|
| Przetworzone cele | 21 | 21 |
| Otwarte problemy | 20 | 19 |
| Ostrzeżenia | 6 | 5 |
| Uwagi | 14 | 14 |
| Średnia ocena technicznego SEO | 92 | 93 |

[Ocena](/pl/pro/scoring) odzwierciedla kontrole techniczne Rankbeam. Nie mierzy ruchu, pozycji w wyszukiwarce ani obecności w odpowiedziach AI. Poprawny wynik kontroli opisu również nie gwarantuje, że wyszukiwarka wyświetli ten opis.

## Wygeneruj raport {#generate-the-report}

Na potrzeby demonstracji wygenerowaliśmy raport bazowy **przed** edycją artykułu, a drugi po ponownym skanowaniu:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

Drugi PDF pokazuje **jeden naprawiony**, **zero nowych** i **19 otwartych** problemów. Trend obejmuje tylko dwa powyższe skanowania. Search Console i rejestrowanie botów AI były wyłączone, więc te sekcje informują o niedostępności danych.

[![Pierwsza strona wygenerowanego raportu przykładowego: ocena 93, jeden naprawiony problem i 19 otwartych problemów.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

Pierwszy raport ustanawia punkt odniesienia do porównań. Jeśli wygenerujesz tylko jeden raport po naprawieniu strony, nie pokaże on zmiany względem wcześniejszego raportu. Użyj `--no-store` do podglądu, który nie powinien aktualizować punktu odniesienia.

Przykład korzysta z renderera Browsershot. Wymagania rendererów, oznaczenie marką i dostarczanie według harmonogramu opisują [raporty pod własną marką](/pl/pro/reports).

## Uruchom we własnej aplikacji {#run-it-on-your-own-app}

Zacznij od [instalacji Pro](/pl/pro/installation), a następnie przeskanuj stronę, której wynik możesz sprawdzić. Pro działa też [bez Filament](/pl/pro/headless). Aby najpierw wypróbować bezpłatny renderer metadanych, skorzystaj z [dema Docker](/pl/guide/demo).

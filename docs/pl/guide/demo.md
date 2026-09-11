---
description: "Uruchom aplikację demonstracyjną Rankbeam z przykładowymi danymi jednym poleceniem — opublikowane pakiety, bez repozytoriów path — i zobacz metadane, graf JSON-LD oraz mapę witryny na rzeczywistych stronach."
---

# Uruchom demo {#run-the-demo}

Najszybszym sposobem zobaczenia Rankbeam na rzeczywistych stronach — bez wcześniejszej integracji z własną aplikacją — jest demo do uruchomienia. To aplikacja Laravel z przykładowymi danymi, która instaluje **opublikowane** pakiety (bez repozytoriów path ani sąsiednich kopii roboczych) i renderuje kilka stron z pełnymi metadanymi SEO, grafem danych strukturalnych JSON-LD i mapą witryny. Dodaj licencję, a uruchomi również [audyt technicznego SEO](/pl/pro/scan-issues) Pro.

## Jedno polecenie (bezpłatny rdzeń) {#one-command-free-core}

Demo jest dostępne jako obraz Docker w repozytorium [`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples):

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

Otwórz `http://localhost:8080`. Wyświetl źródło dowolnej strony, aby zobaczyć wynikową sekcję `<head>`; odwiedź `/sitemap.xml`, aby obejrzeć wygenerowaną mapę witryny. Wszystko to zapewnia bezpłatny rdzeń na licencji MIT, zainstalowany z Packagist.

## Z Pro (audyt) {#with-pro-the-audit}

Pro jest licencjonowany na projekt i instalowany z prywatnego repozytorium Composer. Przekaż licencję przez `COMPOSER_AUTH` (sekret procesu budowania — nigdy nie jest zapisywany w warstwie obrazu) i zbuduj obraz z flagą Pro:

```bash
export COMPOSER_AUTH='{"http-basic":{"blog.rankbeam.dev":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

Przy uruchomieniu demo wykonuje [`seo:doctor`](/pl/pro/headless#setup-health-check) i pierwszy `seo-pro:scan` na stronach z przykładowymi danymi — raport stanu, podsumowanie skanu i [ocena 0–100](/pl/pro/scoring) pojawiają się w logach compose.

## Zobacz proces pracy z Pro {#see-the-pro-workflow}

[Prezentacja skan → naprawa → raport](/pl/pro/walkthrough) pokazuje działające demo Merchant: rzeczywisty skan, szczegóły problemu, opis zapisany w Filament, ponowny skan i PDF do pobrania. Treść jest oznaczona jako dane przykładowe, a wyniki przed i po pochodzą z dwóch świeżych skanów.

Publiczne, hostowane demo interaktywne nie jest jeszcze dostępne. Użyj Dockera, aby uruchomić silnik lokalnie; [README demo](https://github.com/rankbeam/rankbeam-examples/tree/main/demo) opisuje konfigurację i przełączanie między pakietami opublikowanymi a lokalnymi.

::: tip Masz już aplikację?
Pomiń demo i przejdź od razu do [Szybkiego startu](/pl/guide/quickstart) — od instalacji do w pełni wyrenderowanej sekcji `<head>` w pięć minut.
:::

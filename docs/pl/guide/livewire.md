---
description: "Używaj niezależnych od frameworka dyrektyw @seo z Rankbeam w Livewire. Generują zwykły HTML w head i działają w komponentach całostronicowych oraz układach Blade tak samo jak w Blade."
---

# Livewire {#livewire}

Dyrektywy Blade `@seo` nie są związane z konkretnym frameworkiem. Generują zwykły HTML w `<head>`, więc w każdej aplikacji Livewire działają tak samo jak w Blade.

## Pierwsze renderowanie całej strony {#initial-full-page-render}

W **całostronicowym komponencie Livewire** (gdy trasa zwraca komponent) albo w dowolnym układzie Blade otaczającym komponenty Livewire `@seo` działa dokładnie tak, jak opisano w [przewodniku Blade](/pl/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

Pierwsza odpowiedź HTTP zawiera kompletną, widoczną dla robotów sekcję head: tytuł, opis, kanoniczny URL, Open Graph, Twitter i JSON-LD. To tę odpowiedź widzą roboty indeksujące i roboty serwisów społecznościowych; w tym przypadku wynik jest w pełni poprawny.

## Zastrzeżenie dotyczące `wire:navigate` {#the-wire-navigate-caveat}

[`wire:navigate`](https://livewire.laravel.com/docs/navigate) w Livewire zmienia kliknięcia linków w przejścia w stylu SPA. Podczas takiego przejścia Livewire podmienia `<body>` i **scala `<head>`**, ale dla pakietu SEO istotna jest pewna asymetria:

- **`<title>` i `<meta>`/`<link>`** są scalane z head nowej strony, więc rozstrzygnięty tytuł i metadane zazwyczaj się aktualizują.
- **`<script>` jest traktowany jako zasób, którego nie można usunąć.** Livewire zachowuje każdy napotkany `<script>`, aby ponowne wykonanie skryptów nie uszkodziło JavaScriptu. Oznacza to, że **bloki JSON-LD `<script>` się gromadzą**. Po odwiedzeniu trzech wpisów schematy wszystkich trzech znajdują się jednocześnie w head, a narzędzie odczytujące dane strukturalne widzi nieprawidłowe lub liczne encje.

Aby umożliwić sprzątanie, renderer **oznacza każdy generowany skrypt JSON-LD**:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## Dodaj usuwanie nieaktualnego JSON-LD {#ship-the-json-ld-cleanup}

Dodaj ten fragment raz, np. w głównym układzie po `@livewireScripts`. Przy każdym `wire:navigate` zachowuje wyłącznie schemat **bieżącej strony**, usuwając nieaktualne:

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        // The page we are now on. data-seo-url is the canonical (query-stripped),
        // so compare on the query-stripped location.
        const here = window.location.href.split('#')[0].split('?')[0]

        // Keep only the LAST schema for this page; remove every other-URL
        // (stale) script AND same-URL duplicates Livewire re-adds when a page is
        // revisited — including clearing a lone stale script when this page has
        // none. Iterate from the end so the freshest copy is the one kept.
        const scripts = [...document.querySelectorAll('script[data-seo-schema]')]
        let kept = false
        for (let i = scripts.length - 1; i >= 0; i--) {
            const url = (scripts[i].getAttribute('data-seo-url') || '').split('?')[0]
            if (url === here && !kept) { kept = true; continue }
            scripts[i].remove()
        }
    })
</script>
```

Wykorzystuje tylko znacznik `data-seo-schema` i identyfikator URL-a, które renderer już generuje. Nie wymaga osobnej konfiguracji każdej strony.

::: warning Porównuj z bieżącym URL-em, nie ze skryptem dodanym jako ostatni
Wcześniejsza wersja tego fragmentu kończyła działanie przy mniej niż dwóch skryptach schematu i uznawała skrypt *dodany jako ostatni* za należący do bieżącej strony. Pozostawiało to stary schemat w head po przejściu ze strony **z** JSON-LD na stronę **bez** niego: obecny był tylko stary skrypt, więc wcześniejsze zakończenie go zachowywało. Nie pozwalało też usunąć **duplikatu dla tego samego URL-a**, ponownie dodanego przez Livewire podczas powrotu na stronę. Porównanie każdego `data-seo-url` z `window.location` i zachowanie tylko **ostatniego** dopasowania usuwa zarówno nieaktualne schematy, jak i duplikaty we wszystkich przypadkach. Właśnie to sprawdzają aplikacja Livewire `rankbeam-examples` i jej test przeglądarkowy.
:::

::: tip Pojedyncze tagi meta przy nawigacji SPA
Scalanie head w Livewire w większości przypadków zapobiega dezaktualizacji pojedynczych tagów `<meta>`/`<link>`. Dokładne zachowanie zależy jednak od wersji Livewire i struktury układu. Na stronach, na których poprawne metadane dla robotów są kluczowe, wybierz **pełne przeładowanie strony** (zwykły link bez `wire:navigate`) lub **renderowanie na serwerze**, tak aby pierwsza odpowiedź HTTP była miarodajna. Aplikacja Livewire [`rankbeam-examples`](https://github.com/rankbeam) sprawdza rzeczywisty przepływ `wire:navigate` w przeglądarce, aby weryfikować tę zgodność.
:::

## Filament {#filament}

Filament korzysta z Livewire, ale służy jako **administracyjny interfejs edycji**: zapisuje `seo_meta` i nigdy nie renderuje head publicznego frontendu. Zobacz [przewodnik Filament](/pl/guide/filament); powyższe wskazówki nie dotyczą panelu administracyjnego.

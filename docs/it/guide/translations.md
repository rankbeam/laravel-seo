---
description: "Risultati dell’audit, avvisi dell’editor ed etichette Filament seguono la lingua dell’app. Pubblica i file di lingua per personalizzare le stringhe o contribuire con una traduzione."
---

# Traduzioni {#translations}

Le stringhe rivolte agli utenti — risultati dell’audit, avvisi sotto i campi Filament, etichette, anteprime e report — sono righe dei file di lingua Laravel. L’interfaccia segue `app()->getLocale()`: un pannello in italiano mostra le etichette italiane senza ulteriore configurazione.

I **codici** dei problemi e degli avvisi, come `missing_title` e `title_too_long`, restano invariati e non vengono tradotti. Viene tradotta la frase associata al codice.

Le lingue distribuite sono inglese, italiano — le stringhe precedenti sono state revisionate, quelle modificate richiedono una nuova revisione — e prime traduzioni in tedesco, francese, spagnolo, portoghese brasiliano, olandese, turco, russo e polacco, che costituiscono il Tier 1. Dal core 3.16, Filament 1.10 e Pro 2.35 sono disponibili anche giapponese, cinese semplificato (`zh_CN`), cinese tradizionale (`zh_TW`), coreano, greco, ucraino e ceco, il Tier 2. Lo stato preciso di ogni lingua è in [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md). La presenza delle stringhe e la loro revisione linguistica sono verifiche separate.

## Personalizzare una stringa {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Modifica poi `lang/vendor/seo/{locale}/seo.php` e le cartelle corrispondenti degli altri pacchetti. Le chiavi mantenute sostituiscono quelle del pacchetto; per le altre si usa il file del pacchetto, poi il fallback inglese.

## Contribuire con una lingua {#contribute-a-language}

Copia il file `en` nella cartella della tua lingua, traduci i valori e conserva ogni `:placeholder`. Esegui la suite: il test di parità rileva chiavi mancanti o estranee, valori vuoti e placeholder persi. Apri quindi una pull request. Le regole complete e il glossario sono in [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md).

## Cosa resta intenzionalmente invariato {#what-is-not-translated-on-purpose}

La presentazione CLI usa l’inglese per impostazione predefinita. Imposta `seo.cli_locale` o `SEO_CLI_LOCALE`, oppure passa `--display-locale=it`, per tradurre i messaggi supportati e i riepiloghi dell’audit. Pro ha un’impostazione separata, `seo-pro.cli_locale`. La lingua dei messaggi è distinta da quella dei contenuti selezionata con `--locale`.

- La guida dei comandi, la diagnostica di manutenzione e l’output di `seo:explain` restano in inglese; le etichette PASS/WARN/FAIL sono stabili.
- L’HTML generato, come `<meta>` e JSON-LD, usa la lingua dei tuoi contenuti, non quella dell’interfaccia del pacchetto.
- Codici dei problemi, chiavi JSON e codici di stato restano identificatori stabili. Le etichette testuali nell’output `--json` possono essere tradotte: le integrazioni devono usare chiavi e codici.

## L’altra parte: la lingua dei tuoi contenuti {#the-other-half-your-content-s-language}

Questa pagina riguarda la lingua usata dal *pacchetto*. La guida ai [contenuti multilingua](/it/guide/multilingual) spiega come viene trattata quella dei *contenuti*: limiti dei titoli per sistema di scrittura, troncamento, maiuscole e minuscole, policy hreflang, `inLanguage`, motori di ricerca regionali e font per le immagini OG.

---
description: "seo:explain mostra quale livello del resolver ha impostato ogni campo SEO e quali valori ha sostituito. È di sola lettura e non richiede rete o licenza."
---

# Spiegare la risoluzione con `seo:explain` {#explain-the-resolution-seo-explain}

Rankbeam risolve i dati SEO attraverso una [catena di priorità](/it/concepts/resolver-precedence): configurazione, valori predefiniti nel database — globali, per tipo di modello e per rotta — valori calcolati dal modello e infine valori espliciti di `seo_meta`. Seguono l’elaborazione finale di suffisso del titolo, canonical e URL delle immagini, poi la [protezione dall’indicizzazione](/it/guide/indexing-guard). Quando il `<title>` o il tag `robots` generato non è quello atteso, **`seo:explain` mostra quale livello ha impostato ogni campo e quali valori ha sostituito.**

Il comando è di sola lettura e non richiede rete né licenza. Non reimplementa l’unione dei dati: l’attribuzione proviene dai contributi dei livelli del resolver e i valori finali dal resolver stesso. La spiegazione segue quindi la risoluzione effettiva.

## Utilizzo {#usage}

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

Il modello deve usare il trait [`HasSEO`](/it/guide/quickstart).

## Leggere l’output {#reading-the-output}

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

- **Set by**: il livello vincente, cioè quello con la priorità più alta che ha impostato un valore non nullo. Compare `post-processing` quando nessun livello ha impostato il campo e il valore è stato ricavato in seguito: per esempio un canonical dall’URL della richiesta o del modello, un og:url dal canonical o l’URL assoluto di un’immagine.
- **Overrode**: tutti i livelli di priorità inferiore che avevano proposto un valore, nell’ordine in cui sono stati superati.
- **↳ notes**: le operazioni che hanno modificato il valore dopo l’unione dei livelli, come il suffisso del titolo, la rimozione della query string dal canonical, la derivazione di og:url, la conversione delle immagini in URL assoluti e la protezione dall’indicizzazione che forza `noindex` sopra ogni livello.

::: tip og:type e twitter:card
Questi due campi hanno valori predefiniti non nulli, `website` e `summary_large_image`. Vince quindi il livello più alto che li imposta, normalmente `computed`, rispetto a `config`. Una pagina senza una riga salvata in `seo_meta` non contribuisce a questi campi: un `og:type` calcolato come `article` non viene sostituito da un semplice `website`. È il comportamento effettivo dell’unione dei livelli.
:::

## Risoluzione dei valori del sito {#site-level-resolution}

Come descritto nella [sezione sui valori del sito](/it/concepts/resolver-precedence), `seo:explain` mostra anche la provenienza dei valori globali che spesso causano dubbi: **host del canonical, nome del sito e lingua predefinita**.

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Controlla soprattutto l’host del canonical. Un `localhost` rimasto nella configurazione, `http://` su un sito HTTPS o un URL dell’app diverso da quello del modello possono produrre canonical che non puntano alla pagina corretta.

## Output JSON {#json-output}

`--json` restituisce la traccia completa per strumenti e CI: `target`, i campi `winner`, `losers`, `final` e `notes` per ogni valore, più il riepilogo `site_level`.

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

## Vedi anche {#see-also}

- [Priorità del resolver](/it/concepts/resolver-precedence): la catena completa tracciata da `seo:explain`.
- [Audit SEO gratuito](/it/guide/audit): `seo:audit` individua i problemi; `seo:explain` mostra perché un campo ha quel valore.

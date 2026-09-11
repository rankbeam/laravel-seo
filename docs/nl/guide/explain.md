---
description: "seo:explain laat zien welke resolverlaag elk SEO-veld instelde en wat die overschreef. Alleen-lezen, zonder netwerk of licentie: onderzoek onverwachte titels of robots-tags."
---

# Waardebepaling uitleggen (`seo:explain`) {#explain-the-resolution-seo-explain}

Rankbeam bepaalt de SEO van een pagina via een [keten van lagen met oplopende prioriteit](/nl/concepts/resolver-precedence): configuratie, standaardwaarden uit de database (globaal, per modeltype en per route), berekende modelwaarden en tot slot expliciete `seo_meta`. Daarna volgen nabewerking (titelachtervoegsel, canonieke URL en absolute afbeeldings-URL's) en de [indexeringsbeveiliging](/nl/guide/indexing-guard). Wanneer de gerenderde `<title>`- of `robots`-tag afwijkt van wat je verwachtte, **laat `seo:explain` precies zien welke laag elk veld instelde en welke waarden die overschreef.**

Het commando is alleen-lezen, heeft geen netwerk of licentie nodig en implementeert het samenvoegen niet opnieuw. De herkomst komt uit de bijdragen van de resolverlagen zelf; de uiteindelijke waarden komen uit de echte resolver. Zo kan de verklaring niet afwijken van wat er werkelijk wordt gerenderd.

## Gebruik {#usage}

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

Het model moet de trait [`HasSEO`](/nl/guide/quickstart) gebruiken.

## De uitvoer lezen {#reading-the-output}

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

- **Set by (ingesteld door)** — de winnende laag (de laag met de hoogste prioriteit die een niet-nullwaarde instelde), of `post-processing` wanneer geen enkele laag het veld instelde maar de waarde werd *afgeleid*: een canonieke URL uit de aanvraag- of model-URL, een og:url uit de canonieke URL of een absolute afbeeldings-URL.
- **Overrode (overschreef)** — alle lagen met een lagere prioriteit die een waarde aanleverden maar niet wonnen, op volgorde. Zo zie je welke waarden werden overschreven.
- **↳ notes (opmerkingen)** — nabewerking die de waarde na het samenvoegen veranderde: het titelachtervoegsel, het verwijderen van de querystring uit de canonieke URL, het afleiden van og:url, het absoluut maken van afbeeldings-URL's en de indexeringsbeveiliging die boven alle lagen `noindex` afdwingt.

::: tip og:type en twitter:card
Deze twee hebben niet-nullstandaardwaarden uit het framework (`website` / `summary_large_image`). De hoogste laag die ze instelt — normaal gesproken `computed` — wint daarom van `config`. Een pagina zonder opgeslagen `seo_meta`-rij draagt voor deze velden niets bij. Een berekende `og:type` zoals `article` wordt dus nooit overschreven door alleen de standaardwaarde `website`. Dit komt overeen met de manier waarop het samenvoegen werkelijk werkt.
:::

## Waarden op siteniveau {#site-level-resolution}

Zoals beschreven in de [aanvulling over het overzicht van de siteconfiguratie](/nl/concepts/resolver-precedence), rapporteert `seo:explain` ook sitebrede waarden waarvan de herkomst vaak verwarrend is: **welke bron de canonieke host, de sitenaam en de standaardlocale instelde**.

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Vooral de canonieke host is het controleren waard. Een verkeerde host — een achtergebleven `localhost`, `http://` op een `https`-site of een applicatie-URL die niet overeenkomt met de model-URL — is een veelvoorkomende oorzaak van fouten in zelfverwijzende canonieke URL's.

## JSON-uitvoer {#json-output}

`--json` geeft de volledige herleiding weer voor tooling of CI: `target`, per veld `winner` / `losers` / `final` / `notes`, en het overzicht `site_level`:

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

## Zie ook {#see-also}

- [Prioriteit van resolverlagen](/nl/concepts/resolver-precedence) — de volledige keten die `seo:explain` volgt.
- [Gratis SEO-audit](/nl/guide/audit) — `seo:audit` vindt *wat er mis is*; `seo:explain` laat zien *waarom een waarde is wat die is*.

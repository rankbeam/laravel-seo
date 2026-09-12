---
description: "Το seo:explain δείχνει ποιο επίπεδο του resolver όρισε κάθε πεδίο SEO και τι αντικατέστησε — μόνο ανάγνωση, χωρίς δίκτυο ή άδεια — για να εντοπίσετε την αιτία ενός απρόσμενου τίτλου ή robots."
---

# Εξήγηση της επίλυσης (`seo:explain`) {#explain-the-resolution-seo-explain}

Το Rankbeam επιλύει το SEO μιας σελίδας μέσω μιας [αλυσίδας επιπέδων προτεραιότητας](/el/concepts/resolver-precedence) — ρυθμίσεις, προεπιλογές βάσης δεδομένων (καθολικές / ανά τύπο μοντέλου / ανά διαδρομή), υπολογιζόμενες τιμές μοντέλου και τέλος ρητό `seo_meta` — και έπειτα εφαρμόζει μετεπεξεργασία (επίθημα τίτλου, canonical, μετατροπή εικόνων σε απόλυτες URL) και την [προστασία ευρετηρίασης](/el/guide/indexing-guard). Όταν η αποδιδόμενη ετικέτα `<title>` ή `robots` δεν είναι αυτή που περιμένατε, **το `seo:explain` δείχνει ακριβώς ποιο επίπεδο όρισε κάθε πεδίο και τι αντικατέστησε.**

Εκτελεί μόνο ανάγνωση, δεν χρειάζεται δίκτυο ή άδεια και δεν υλοποιεί ξανά τη συγχώνευση: η απόδοση προέλευσης βασίζεται στις συνεισφορές επιπέδων του ίδιου του resolver και οι τελικές τιμές προέρχονται από τον πραγματικό resolver — επομένως η εξήγηση δεν μπορεί να αποκλίνει από αυτό που αποδίδεται.

## Χρήση {#usage}

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

Το μοντέλο πρέπει να χρησιμοποιεί το trait [`HasSEO`](/el/guide/quickstart).

## Ανάγνωση της εξόδου {#reading-the-output}

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

- **Set by** — το επίπεδο που υπερίσχυσε (το επίπεδο με την υψηλότερη προτεραιότητα που όρισε μη null τιμή), ή `post-processing` όταν κανένα επίπεδο δεν όρισε το πεδίο αλλά μια τιμή *παρήχθη* (canonical από την URL αιτήματος/μοντέλου, og:url από το canonical, εικόνα που μετατράπηκε σε απόλυτη URL).
- **Overrode** — κάθε επίπεδο χαμηλότερης προτεραιότητας που πρόσφερε τιμή και έχασε, με τη σειρά, ώστε να βλέπετε τι παρακάμφθηκε.
- **↳ notes** — η μετεπεξεργασία που άλλαξε την τιμή μετά τη συγχώνευση των επιπέδων: το επίθημα τίτλου, η αφαίρεση query string από την κανονική URL, η παραγωγή og:url, η μετατροπή εικόνων σε απόλυτες URL και η προστασία ευρετηρίασης που επιβάλλει `noindex` πάνω από κάθε επίπεδο.

::: tip og:type και twitter:card
Αυτά τα δύο έχουν μη null προεπιλογές του framework (`website` / `summary_large_image`), οπότε το υψηλότερο επίπεδο που τα ορίζει — συνήθως το `computed` — υπερισχύει του `config`. Μια σελίδα χωρίς αποθηκευμένη εγγραφή `seo_meta` δεν συνεισφέρει τίποτα γι' αυτά, επομένως ένα υπολογιζόμενο `og:type` όπως το `article` δεν παρακάμπτεται ποτέ από ένα σκέτο `website`. Αυτό αποτυπώνει πιστά τον τρόπο που τα επιλύει η συγχώνευση.
:::

## Επίλυση σε επίπεδο ιστοτόπου {#site-level-resolution}

Σύμφωνα με την [προσθήκη για την καταγραφή ρυθμίσεων ιστοτόπου](/el/concepts/resolver-precedence), το `seo:explain` αναφέρει και τις τιμές που ισχύουν για όλο τον ιστότοπο και των οποίων η προέλευση προκαλεί συχνά σύγχυση — **ποια πηγή όρισε τον κανονικό host, το όνομα του ιστοτόπου και το προεπιλεγμένο locale**:

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

Ο κανονικός host είναι η τιμή που αξίζει περισσότερο να ελέγξετε — ένας λάθος host (ένα `localhost` που διέφυγε στην παραγωγή, ένα `http://` σε ιστότοπο `https`, η URL της εφαρμογής που δεν ταιριάζει με την URL του μοντέλου) είναι κλασική αιτία σφαλμάτων στις αυτοαναφορικές κανονικές URL.

## Έξοδος JSON {#json-output}

Το `--json` παράγει το πλήρες ίχνος — `target`, τα `winner` / `losers` / `final` / `notes` ανά πεδίο και την καταγραφή `site_level` — για εργαλεία ή CI:

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

## Δείτε επίσης {#see-also}

- [Προτεραιότητα του resolver](/el/concepts/resolver-precedence) — η πλήρης αλυσίδα που ιχνηλατεί το `seo:explain`.
- [Δωρεάν έλεγχος SEO](/el/guide/audit) — το `seo:audit` βρίσκει *τι είναι λάθος*· το `seo:explain` δείχνει *γιατί μια τιμή είναι αυτή που είναι*.

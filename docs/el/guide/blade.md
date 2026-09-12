---
description: "Αποδώστε SEO σε Laravel με απόδοση στον διακομιστή μέσω των οδηγιών Blade του πακέτου — η ενιαία @seo επιλύει ένα μοντέλο και παράγει μεταδεδομένα, Open Graph, Twitter Cards και JSON-LD."
---

# Οδηγός Blade {#blade-guide}

Για τις κλασικές εφαρμογές με απόδοση στον διακομιστή, το πακέτο παρέχει επτά οδηγίες Blade.
Μία από αυτές — η `@seo` — συνήθως καλύπτει όλες τις ανάγκες σας.

## Η ενιαία οδηγία {#the-all-in-one-directive}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

Η `@seo` επιλύει το μοντέλο μέσω της [αλυσίδας προτεραιότητας](/el/concepts/resolver-precedence)
και αποδίδει ολόκληρο το μπλοκ head: `<title>`, μεταπεριγραφή, κανονικό
σύνδεσμο, robots, ετικέτες Open Graph, ετικέτες Twitter Card και συνδεδεμένο JSON-LD. Η
ετικέτα robots παράγεται **μόνο όταν διαφέρει από την προεπιλογή του ιστοτόπου** — ένα
περιττό `index,follow` παραλείπεται (η απουσία του ήδη σημαίνει index,follow).
Ορίστε το `seo.robots.emit_default` για να αποδίδεται πάντα. Δείτε το πλήρες
[Συμβόλαιο απόδοσης](/el/contributing/rendering-contract).

Υπογραφές:

```blade
@seo($post)                  {{-- model page --}}
@seo($seoData)               {{-- a hand-built SEOData (model-less page) --}}
@seo($post, 'blog.show')     {{-- model + route defaults --}}
@seo($post, null, 'fr')      {{-- model + locale --}}
@seo(null)                   {{-- current page, no model --}}
```

Η `@seo` δέχεται ένα `Model`, ένα `SEOData` που δημιουργήσατε εσείς ή `null`. Τα ορίσματα διαδρομής και locale
ισχύουν μόνο όταν περνάτε `Model`/`null` — ένα `SEOData` που δημιουργήσατε εσείς περιέχει
τις δικές του τιμές.

## Σελίδες διαδρομών (χωρίς μοντέλο) {#route-pages-no-model}

Για στατικές σελίδες, αρχεία και άλλες σελίδες που βασίζονται σε διαδρομή:

```blade
@seoForRoute('pages.about')
@seoForRoute('contact', 'de')   {{-- with locale --}}
```

Οι τιμές της διαδρομής προέρχονται από εγγραφές `seo_defaults` που περιορίζονται στο όνομα της διαδρομής.

## Σελίδες χωρίς μοντέλο: δικό σας `SEOData` {#model-less-pages-hand-built-seodata}

Οι λίστες, τα αποτελέσματα αναζήτησης και οτιδήποτε συντίθεται σε έναν controller συχνά δεν αντιστοιχούν
σε ένα μόνο μοντέλο. Δημιουργήστε ένα `SEOData` και περάστε το απευθείας στην `@seo` (ή στη
facade `SEO`) — δεν χρειάζεται να καταφύγετε σε `app(TagRenderer::class)->render(...)`:

```php
use Rankbeam\Seo\Data\SEOData;

return view('search.results', [
    'seo' => new SEOData(
        title: "Results for \"{$query}\"",
        description: "Browse {$count} matches for {$query}.",
        ogImage: '/images/search-share.jpg',   // relative is fine — see below
    ),
]);
```

```blade
<head>
    @seo($seo)
</head>
```

Ένα `SEOData` που δημιουργήσατε εσείς αντιμετωπίζεται ως **ρητή πρόθεση**. Κάθε τιμή που ορίζετε
διατηρείται· συμπληρώνονται μόνο τα κενά κατά την απόδοση:

- Τα `canonical` / `og:url` προκύπτουν από την τρέχουσα URL όταν λείπουν (μια
  ρητή τιμή `canonical` διατηρείται αυτούσια, μαζί με το query string).
- Το `title_suffix` προστίθεται μόνο όταν λείπει από τον τίτλο (και παραλείπεται
  εντελώς όταν ο τίτλος περιέχει ήδη έναν όρο της επωνυμίας — δείτε το
  [`title_suffix_skip_when_contains`](/el/reference/configuration)).
- Οι σχετικές διαδρομές `og:image` / `twitter:image` μετατρέπονται σε απόλυτες με το `url()`
  (το οποίο σέβεται το τρέχον πρωτόκολλο — **δεν** επιβάλλει HTTPS).
- Τα `og:site_name` και `locale` συμπληρώνονται από τις ρυθμίσεις / το locale της εφαρμογής.

Η αλυσίδα προτεραιότητας της βάσης δεδομένων (καθολικές προεπιλογές / ανά τύπο μοντέλου / ανά διαδρομή / `seo_meta`)
**δεν** συγχωνεύεται σε ένα `SEOData` που δημιουργήσατε εσείς — αποδίδεται αυτό που
περνάτε, με τη συμπλήρωση των παραπάνω κενών.

Η ίδια τιμή λειτουργεί και μέσω της facade:

```php
SEO::render($seoData);     // HTML string
SEO::toArray($seoData);    // Vue/React structure
SEO::forInertia($seoData); // Inertia Head structure
```

## Ένα μοτίβο layout που επεκτείνεται {#a-layout-pattern-that-scales}

Ένα layout που εξυπηρετεί σελίδες μοντέλων, σελίδες διαδρομών και οτιδήποτε άλλο:

```blade
<head>
    @if(isset($seoModel))
        @seo($seoModel)
    @elseif(isset($seoRoute))
        @seoForRoute($seoRoute)
    @else
        @seo(null)
    @endif
</head>
```

Οι controllers περνούν έπειτα `'seoModel' => $post` ή `'seoRoute' => 'blog.index'`
χωρίς να αγγίζουν το markup.

## Επιμέρους οδηγίες {#granular-directives}

Όταν χρειάζεται να ελέγξετε μεμονωμένες ετικέτες (για παράδειγμα, σε συνδυασμό με
την έξοδο άλλου πακέτου):

| Οδηγία | Τι αποδίδει |
|---|---|
| `@seoTitle($post)` | Μόνο το `<title>` |
| `@seoMeta($post)` | Μόνο τη μεταπεριγραφή |
| `@seoCanonical($post)` | Μόνο τον κανονικό σύνδεσμο (με εναλλακτική την τρέχουσα URL) |
| `@seoRobots($post)` | Μόνο τη μετα-ετικέτα robots — αποδίδεται πάντα (πρόκειται για ρητή επιλογή, επομένως **δεν** εφαρμόζει την παράλειψη όταν η τιμή συμφωνεί με την προεπιλογή, όπως κάνει η `@seo`) |
| `@seoSchema($post)` | Μόνο το `<script>` του JSON-LD — έγκυρο στο head ή στο body |

Όλες δέχονται την ίδια έκφραση `($model, $route, $locale)` με την `@seo`,
ή κανένα όρισμα για την τρέχουσα σελίδα.

## Εναλλακτικές εκδόσεις hreflang {#hreflang-alternates}

Τα μοντέλα που χρησιμοποιούν το `HasSEO` μπορούν να παρέχουν συνδέσμους hreflang απευθείας μέσω του resolver:

```php
public function getSEOAlternates(): ?array
{
    return [
        ['hreflang' => 'en', 'href' => route('posts.show', ['locale' => 'en', 'post' => $this])],
        ['hreflang' => 'it', 'href' => route('posts.show', ['locale' => 'it', 'post' => $this])],
    ];
}
```

Χρησιμοποιήστε απόλυτες URL. Η `@seo($post)` επιλύει αυτές τις εγγραφές και αποδίδει καθεμία ως
`<link rel="alternate" hreflang="..." href="...">`. Οι κωδικοί μετατρέπονται πρώτα
στη μορφή BCP 47 (`it_IT` → `it-IT`), και οι πολιτικές `seo.hreflang` μπορούν
να προσθέσουν αναφορά στην ίδια τη σελίδα και ένα `x-default`· ο δωρεάν έλεγχος επισημαίνει
μη έγκυρες ή διπλές εγγραφές και την απουσία αναφοράς στην ίδια τη σελίδα. Δείτε το
[Πολύγλωσσο περιεχόμενο](/el/guide/multilingual#hreflang).

## Διαφυγή χαρακτήρων και ασφάλεια {#escaping-and-safety}

Οι τιμές κειμένου περνούν από διαφυγή χαρακτήρων με το `e()`. Το JSON-LD κωδικοποιείται με
`JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`, ώστε ένα
`</script>` μέσα σε περιεχόμενο χρήστη να μην μπορεί να κλείσει πρόωρα το στοιχείο script.

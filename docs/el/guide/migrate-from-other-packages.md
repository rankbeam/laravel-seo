---
description: "Μεταβείτε στο Rankbeam από άλλο πακέτο SEO για Laravel, αντιστοιχίζοντας API και αποθήκευση στο trait HasSEO και στο saveSEO(), με εισαγωγή δεδομένων SEO ανά μοντέλο μέσω μίας εντολής."
---

# Μεταφορά από άλλα πακέτα SEO για Laravel {#migrating-from-other-laravel-seo-packages}

Χρησιμοποιείτε ήδη άλλο πακέτο SEO; Η μετάβαση στο Rankbeam προορίζεται να είναι
δουλειά μίας ημέρας, όχι επανεγγραφή. Αυτός ο οδηγός αντιστοιχίζει το API και την
αποθήκευση κάθε συνηθισμένου πακέτου στα δύο βασικά στοιχεία του Rankbeam — το trait [`HasSEO`](/el/guide/quickstart)
και το `saveSEO()` — και παρέχει εργαλείο εισαγωγής μίας εντολής για το πακέτο που
αποθηκεύει δεδομένα SEO ανά μοντέλο.

::: tip Έρχεστε από το WordPress;
Αν μεταφέρετε έναν ιστότοπο περιεχομένου από το WordPress (Yoast ή Rank Math), δείτε τον
ειδικό οδηγό [**Μεταφορά από το WordPress**](/el/guide/migrate-from-wordpress) —
καλύπτει την εισαγωγή CSV και την ανάγνωση από την ενεργή βάση δεδομένων.
:::

| Προέλευση | Αποθήκευση δεδομένων | Διαδρομή μεταφοράς |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | πολυμορφικός πίνακας `seo` | **`php artisan seo:import-from ralphjsmit`** + αντικατάσταση trait |
| [`artesaos/seotools`](#from-artesaos-seotools) | πουθενά (χρόνος εκτέλεσης + ρυθμίσεις) | αντικατάσταση κώδικα — ορισμός τιμών μέσω `saveSEO()` / υπολογιζόμενων getters |
| [`spatie/*`](#from-spatie-packages) | πουθενά (builders schema-org / χάρτη ιστοτόπου) | κρατήστε ό,τι λειτουργεί συμπληρωματικά, μεταφέρετε τα υπόλοιπα στο Rankbeam |

Μόνο το **ralphjsmit** αποθηκεύει μόνιμα δεδομένα SEO σε πίνακα βάσης δεδομένων, οπότε είναι το μόνο
με δεδομένα για μαζική εισαγωγή. Τα υπόλοιπα δημιουργούν ετικέτες κατά την εκτέλεση — δεν υπάρχει
πίνακας για ανάγνωση· αντικαθιστάτε τις κλήσεις τους ανά αίτημα με αποθηκευμένα `seo_meta`.

---

## Από το `ralphjsmit/laravel-seo` {#from-ralphjsmit-laravel-seo}

Το `ralphjsmit/laravel-seo` αποθηκεύει μία πολυμορφική εγγραφή ανά μοντέλο
σε πίνακα `seo` του οποίου η δομή είναι κοντά στο `seo_meta` του Rankbeam. Αυτό επιτρέπει
καθαρή, ιδιοδύναμη μαζική εισαγωγή.

### 1. Εγκαταστήστε παράλληλα το Rankbeam {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

Τα δύο πακέτα μπορούν να συνυπάρχουν όσο κάνετε τη μεταφορά — χρησιμοποιούν διαφορετικούς πίνακες (`seo`
και `seo_meta`) και διαφορετικά namespaces για τα traits.

::: warning Ένα αρχείο ρυθμίσεων, όχι δύο
Αν υπάρχει ακόμη στην εφαρμογή σας δημοσιευμένο `config/seo.php` από το `ralphjsmit/laravel-seo`,
θα υπερισχύει των ρυθμίσεων του Rankbeam (μοιράζονται το κλειδί ρυθμίσεων `seo`). Κρατήστε
αντίγραφο ασφαλείας, διαγράψτε το και δημοσιεύστε ξανά αυτό του Rankbeam: `php artisan vendor:publish
--tag=seo-config`.
:::

### 2. Εκτελέστε το εργαλείο εισαγωγής {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

Το εργαλείο διαβάζει τον πίνακα `seo` του ralphjsmit, επιλύει κάθε εγγραφή στο πραγματικό
μοντέλο Eloquent και γράφει τα δεδομένα στο `seo_meta`.

| Επιλογή | Αποτέλεσμα |
|---|---|
| `--dry-run` | Αναφέρει τι θα εισαγόταν· δεν γράφει τίποτα. |
| `--model="App\Models\Post"` | Περιορίζει την εισαγωγή σε μία ή περισσότερες κλάσεις μοντέλων (επαναλήψιμη επιλογή). |
| `--locale=fr` | Γράφει τις εισαγόμενες εγγραφές για αυτό το locale (προεπιλογή: το locale της εφαρμογής). |
| `--table=legacy_seo` | Διαβάζει μετονομασμένο πίνακα προέλευσης. |
| `--connection=legacy` | Διαβάζει τον πίνακα προέλευσης από άλλη σύνδεση βάσης δεδομένων. |
| `--limit=100` | Εισάγει το πολύ N εγγραφές (χρήσιμο για σταδιακή μεταφορά). |
| `--overwrite` | Αντικαθιστά υπάρχουσες μη κενές τιμές (προεπιλογή: συμπληρώνει μόνο κενά πεδία). |
| `--json` | Μηχαναγνώσιμη αναφορά. |
| `--force` | Παραλείπει το αίτημα επιβεβαίωσης (για scripts/CI). |

Είναι **ιδιοδύναμο**: η επανεκτέλεση ενημερώνει τις ίδιες εγγραφές και δεν δημιουργεί ποτέ
διπλότυπα, ενώ από προεπιλογή μόνο *συμπληρώνει* κενά πεδία — δεν θα
αντικαταστήσει δεδομένα SEO που έχετε ήδη ορίσει στο Rankbeam. Περάστε `--overwrite` αν
θέλετε οι εισαγόμενες τιμές να αντικαταστήσουν τις υπάρχουσες.

### 3. Αντικαταστήστε το trait στα μοντέλα σας {#_3-swap-the-trait-on-your-models}

Αντικαταστήστε το trait του ralphjsmit με αυτό του Rankbeam. Τα ονόματα μεθόδων διαφέρουν ελαφρώς·
ο πίνακας που διαβάζει το trait είναι πλέον ο `seo_meta`.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

Αν προσαρμόζατε τα δεδομένα SEO με το `getDynamicSEOData()` του ralphjsmit, μεταφέρετε αυτή τη
λογική στους υπολογιζόμενους getters ανά πεδίο του Rankbeam (`getSEOTitle()`,
`getSEODescription()`, `getSEOImage()`, `getUrlForSEO()`, `getSEOAlternates()`)
— δείτε τη [Γρήγορη εκκίνηση](/el/guide/quickstart). Οι αποθηκευμένες παρακάμψεις ορίζονται μέσω
`saveSEO()`:

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### Αντιστοίχιση πεδίων {#field-mapping}

Το εργαλείο εισαγωγής αντιστοιχίζει πεδία **ρητά** — δεν αντιγράφει ποτέ τυφλά μια στήλη που
δεν υπάρχει στο σχήμα του Core 3.

| ralphjsmit `seo` | Rankbeam `seo_meta` | Σημειώσεις |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | **Επιλύονται ξανά** από το ενεργό μοντέλο (δείτε παρακάτω), δεν αντιγράφονται αυτούσια. |
| `title` | `title` | Περικόπτεται στους 70 χαρακτήρες (το μήκος της στήλης `seo_meta`)· οι μεγαλύτερες τιμές αναφέρονται. |
| `description` | `description` | Περικόπτεται στους 160 χαρακτήρες· οι μεγαλύτερες τιμές αναφέρονται. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | Περικόπτεται στους 50 χαρακτήρες. |
| `image` | `og_image` | Το `twitter:image` την κληρονομεί αυτόματα μέσω του resolver. |
| `author` | *(δεν εισάγεται)* | Το `seo_meta` του Core 3 δεν έχει στήλη συντάκτη — ο συντάκτης άρθρου αφορά το επίπεδο του resolver, όχι τα αποθηκευμένα μεταδεδομένα κοινοποίησης. Οι εγγραφές με συντάκτη **καταμετρώνται και αναφέρονται**, ώστε να αποφασίσετε πού θα αποθηκεύεται (π.χ. ως υπολογιζόμενη τιμή τύπου `getSEOData`). |
| `id`, `created_at`, `updated_at` | *(δεν εισάγονται)* | Δομικά πεδία. |

**Γιατί ο πολυμορφικός τύπος επιλύεται ξανά.** Κάθε εγγραφή προέλευσης επιλύεται στο πραγματικό της
μοντέλο και τα κλειδιά `seoable` λαμβάνονται από το
`getMorphClass()` του ίδιου του μοντέλου. Έτσι διατηρείται σωστή η σχέση με την *τρέχουσα*
[αντιστοίχιση πολυμορφικών τύπων](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types)
της εφαρμογής σας, ακόμη κι αν το ralphjsmit αποθήκευε διαφορετική σύμβαση, και το εργαλείο εισαγωγής
μπορεί να παραλείπει εγγραφές των οποίων το μοντέλο έχει διαγραφεί (αναφέρονται ως παραλειφθείσες, δεν
γράφονται ποτέ ως ορφανές).

### Τι σας λέει η αναφορά {#what-the-report-tells-you}

Μια εκτέλεση χωρίς `--json` εκτυπώνει πίνακα αποτελεσμάτων και τρεις ενότητες ελέγχου:

- **Περικομμένες τιμές** — τιμές που συντομεύτηκαν για να χωρέσουν σε στήλη `seo_meta`. Ελέγξτε τις.
- **Δεν εισήχθησαν** — στήλες προέλευσης (π.χ. `author`) που είχαν δεδομένα αλλά δεν έχουν
  θέση στο Core 3.
- **Παραλειφθείσες εγγραφές ανά αιτία** — κενές εγγραφές προέλευσης, διαγραμμένα μοντέλα, τύποι
  μοντέλων που δεν επιλύθηκαν.

### Επαλήθευση {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

Όταν μείνετε ικανοποιημένοι, αφαιρέστε το `ralphjsmit/laravel-seo` και διαγράψτε τον πίνακά του
`seo`.

---

## Από το `artesaos/seotools` {#from-artesaos-seotools}

Το `artesaos/seotools` δημιουργεί ετικέτες **κατά την εκτέλεση**: ορίζετε τιμές ανά αίτημα
μέσω των facades `SEOMeta`, `OpenGraph`, `TwitterCard` και `JsonLd` (συχνά
σε έναν controller), με προεπιλογές από το `config/seotools.php`. Δεν αποθηκεύεται τίποτα
ανά μοντέλο, οπότε δεν υπάρχει πίνακας για εισαγωγή — μεταφέρετε τις κλήσεις ανά αίτημα σε
αποθηκευμένες ή υπολογιζόμενες τιμές.

| Κλήση artesaos/seotools | Αντίστοιχο στο Rankbeam |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` ή `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` ή `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` ή `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | Δεν υπάρχει αντίστοιχη μετα-ετικέτα keywords: οι λέξεις-κλειδιά εστίασης προορίζονται για εσωτερικούς συντακτικούς ελέγχους. `saveSEO(['focus_keywords' => [...]])` (δείτε τον [έλεγχο](/el/guide/audit)) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | ο [γράφος schema JSON-LD](/el/guide/schema) |
| προεπιλογές `config/seotools.php` | προεπιλογές ιστοτόπου `config/seo.php` + [προτεραιότητα resolver](/el/concepts/resolver-precedence) |
| `{!! SEO::generate() !!}` στη διάταξη | `@seo($model)` (δείτε το [Blade](/el/guide/blade)) |

Η αλλαγή είναι εννοιολογική: αντί να ορίζετε ετικέτες προστακτικά σε κάθε
controller, αποθηκεύετε τα δεδομένα SEO μία φορά (ανά μοντέλο, στο `seo_meta`) και ο
resolver του Rankbeam τα αποδίδει. Οι εναλλακτικές τιμές για ολόκληρο τον ιστότοπο που βρίσκονταν στο `config/seotools.php`
γίνονται [προεπιλογές ρυθμίσεων](/el/reference/configuration) του Rankbeam· οι στατικές σελίδες
ανά διαδρομή χρησιμοποιούν το `@seoForRoute()`.

---

## Από πακέτα Spatie {#from-spatie-packages}

Δεν υπάρχει πακέτο αποθήκευσης μεταδεδομένων `spatie/laravel-seo`, επομένως δεν υπάρχει κάτι για
εισαγωγή. Τα πακέτα Spatie που χρησιμοποιούνται μαζί με το SEO είναι **συμπληρωματικά εργαλεία δημιουργίας**
και μπορείτε να τα κρατήσετε ή να τα αντικαταστήσετε ένα-ένα:

- **`spatie/schema-org`** — builder JSON-LD με fluent API. Το Rankbeam έχει δικό του
  [γράφο schema](/el/guide/schema) με τυποποιημένους builders `Article`, `FAQPage`, `Product`,
  `BreadcrumbList`, `LocalBusiness` και `Organization` που αποθηκεύουν στο
  `seo_meta.schema_jsonld` και αποδίδονται χωρίς διπλότυπα. Αν έχετε χειροποίητα
  αντικείμενα `spatie/schema-org`, περάστε την έξοδο `->toArray()` τους στο
  `saveSEO(['schema_jsonld' => $array])` ή εκφράστε τα ξανά με τους
  builders του Rankbeam.
- **`spatie/laravel-sitemap`** — εργαλείο δημιουργίας χάρτη ιστοτόπου. Το
  [μητρώο χαρτών ιστοτόπου](/el/guide/sitemaps) του Rankbeam βασίζεται σε αυτό· μπορείτε να καταχωρίσετε τα
  μοντέλα σας ως πηγές και να αφήσετε το Rankbeam να παράγει ενιαίο χάρτη ή να κρατήσετε τον
  υπάρχοντα χάρτη Spatie και να απενεργοποιήσετε τη διαδρομή του Rankbeam.

(Αν χρησιμοποιούσατε το [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO),
ένα ακόμη εργαλείο δημιουργίας μεταδεδομένων κατά την εκτέλεση / με structs, ακολουθήστε το ίδιο μοτίβο με το
artesaos: μεταφέρετε τις κλήσεις `setTitle`/`addMeta` ανά αίτημα σε `saveSEO()` ή
υπολογιζόμενους getters.)

---

## Επέκταση του εργαλείου εισαγωγής {#extending-the-importer}

Η εντολή `seo:import-from` βασίζεται σε ένα μικρό μητρώο υλοποιήσεων
`Rankbeam\Seo\Importing\Contracts\Importer`, οπότε προστίθενται νέες πηγές
χωρίς αλλαγή της εντολής. Οι ενσωματωμένες πηγές σήμερα είναι: `ralphjsmit`
και τα εργαλεία εισαγωγής WordPress (`wordpress-csv`, `yoast`, `rank-math` — δείτε
τη [Μεταφορά από το WordPress](/el/guide/migrate-from-wordpress)). Καταχωρίστε τη δική σας σε
έναν service provider:

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```


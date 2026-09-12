---
description: "Ανιχνευτής με όρια και δυνατότητα συνέχισης που καταγράφει μη λειτουργικούς συνδέσμους: εσωτερικές διαδρομές με διόρθωση μέσω ανακατεύθυνσης και προαιρετικά εξωτερικούς συνδέσμους. Ανενεργός εξ ορισμού."
---

# Ανιχνευτής μη λειτουργικών συνδέσμων {#broken-link-crawler}

Ένας **ανιχνευτής με όρια και δυνατότητα συνέχισης** που διατρέχει τον ιστότοπό σας, ακολουθεί τους συνδέσμους κάθε
σελίδας και καταγράφει όσους δεν οδηγούν σε προσβάσιμο προορισμό — μη λειτουργικούς **εσωτερικούς** συνδέσμους (μια
ανύπαρκτη διαδρομή στον δικό σας host, που διορθώνεται με ένα κλικ ως ανακατεύθυνση) και, προαιρετικά,
μη λειτουργικούς **εξωτερικούς** συνδέσμους. Είναι **ανενεργός από προεπιλογή**.

Τρία στοιχεία καθορίζουν τον σχεδιασμό:

- **Όρια και δυνατότητα συνέχισης.** Μια ανίχνευση εκτελείται με πολλές μικρές εργασίες στην ουρά, καθεμία
  με όριο λίγων σελίδων, οι οποίες αναθέτουν τη συνέχειά τους μέχρι η εκτέλεση
  να ολοκληρωθεί ή να φτάσει τα όριά της. Όριο έχει και ολόκληρη η εκτέλεση (2000 σελίδες από προεπιλογή —
  το `null` επιλέγει ρητά απεριόριστες σελίδες, ποτέ ως προεπιλογή), ενώ τα υπόλοιπα όρια παρτίδας/χρόνου εξακολουθούν να ισχύουν. Προσαρμόστε τα όρια και τις καθυστερήσεις
  στη χωρητικότητα του ιστοτόπου και του διακομιστή.
- **Ασφαλής από προεπιλογή.** Το προεπιλεγμένο πεδίο είναι `internal_only` — ελέγχει μόνο
  συνδέσμους στον δικό σας host, χωρίς αιτήματα προς τρίτους. Κάθε ανάκτηση (εσωτερική ή
  εξωτερική) περνά από το κοινό **SsrfGuard**: λίστα επιτρεπόμενων σχημάτων URL, περιορισμό host
  και απόρριψη ιδιωτικών διευθύνσεων. Ο έλεγχος εξωτερικών συνδέσμων απαιτεί ρητή ενεργοποίηση και παραμένει
  προστατευμένος.
- **Ανεξάρτητος από τη βαθμολογία SEO.** Τα ευρήματα βρίσκονται σε δικούς τους πίνακες και δεν
  γράφονται ποτέ στο `seo_scan_issues` ούτε στη βαθμολογία 0–100 — η βαθμολογία μιας σελίδας δεν αλλάζει
  αν οι εξερχόμενοι σύνδεσμοί της είναι μη λειτουργικοί. Οι μη λειτουργικοί σύνδεσμοι είναι λειτουργικό
  ζήτημα που παρακολουθείται χωριστά.

## Τι παρέχει {#what-you-get}

Στον πίνακα ελέγχου Filament (μόνο όταν είναι ενεργός):

- **Σύνοψη μη λειτουργικών συνδέσμων** — πλήθος ανοιχτών μη λειτουργικών συνδέσμων (εσωτερικών και εξωτερικών) και
  τελευταία ανίχνευση, με σύνδεσμο στον πίνακα ευρημάτων.
- **Ανίχνευση μη λειτουργικών συνδέσμων** — ζωντανή πρόοδος της τρέχουσας ανίχνευσης (σελίδες που ανιχνεύθηκαν,
  σύνδεσμοι που ελέγχθηκαν, μη λειτουργικοί που βρέθηκαν).
- **Μη λειτουργικοί σύνδεσμοι ανά σάρωση** — η τάση στις πρόσφατες ανιχνεύσεις.
- **Πόρος ευρημάτων** — κάθε μη λειτουργικός σύνδεσμος `source → target`, με φίλτρα και
  δυνατότητα διόρθωσης των εσωτερικών μέσω ανακατεύθυνσης.

Σε headless χρήση, τα ίδια δεδομένα παρέχουν οι εντολές `seo-pro:broken-links-*`.

## Γιατί είναι ανενεργός από προεπιλογή {#why-it-s-off-by-default}

Σε αντίθεση με τις παθητικές λειτουργίες απόδοσης και βαθμολόγησης, ο ανιχνευτής **κάνει αιτήματα
δικτύου** και χρειάζεται κάποια υποδομή — επομένως ενεργοποιείται συνειδητά,
χωρίς να αρχίζει σιωπηρά κατά την εγκατάσταση:

- Οι δύο κύριοι πίνακές του απαιτούν **δημοσίευση των μεταναστεύσεων** (όπως κάθε μετανάστευση Pro) — οι μεταναστεύσεις πρέπει
  να εκτελεστούν πριν το περιβάλλον εργασίας μπορέσει να τους διαβάσει. Οι έλεγχοι ανά τύπο χρησιμοποιούν επίσης
  το `seo_broken_link_inspections`.
- Η ανίχνευση **τοποθετείται σε αποκλειστική ουρά** και χρειάζεται **worker** —
  χωρίς worker δεν προχωρά ποτέ.
- Η επιβεβαίωση γίνεται **μεταξύ σαρώσεων** (παρακάτω), οπότε είναι σχεδιασμένος να εκτελείται **προγραμματισμένα**
  επί εβδομάδες, όχι να δίνει άμεσο αποτέλεσμα μόλις ενεργοποιηθεί.

## Εγκατάσταση και ρύθμιση {#setup}

```dotenv
SEO_PRO_BROKEN_LINKS_ENABLED=true
```

Έπειτα εκτελέστε τις μεταναστεύσεις — το `seo-pro:install` δημοσιεύει και εκτελεί κάθε μετανάστευση
Pro (ιδιοδύναμα, με ασφαλή επανεκτέλεση):

```bash
php artisan seo-pro:install
```

Εκτελέστε **αποκλειστικό worker** για την ουρά ανίχνευσης. Είναι ξεχωριστή ουρά
(`seo-broken-links`) ακριβώς ώστε μια μεγάλη ανίχνευση να μην προηγείται των
εργασιών που εξυπηρετούν τους χρήστες:

```bash
# --tries=1: a dead job is reclaimed by the next continuation, so queue-level
#   retries are unnecessary. --timeout must exceed the batch's hard time budget
#   (seo-pro.broken_links.batch.hard_time_budget_seconds, default 180) plus the
#   HTTP timeout, so a batch is never killed mid-bookkeeping.
php artisan queue:work --queue=seo-broken-links --tries=1 --timeout=240
```

Επιβεβαιώστε τη ρύθμιση — το `seo:doctor` ελέγχει τη σημαία, τους πίνακες και αν η
ουρά ανίχνευσης αντιστοιχεί σε πραγματική σύνδεση (όχι `sync`), με την ακριβή διόρθωση για κάθε περίπτωση:

```bash
php artisan seo:doctor
```

Δείτε τη [ρύθμιση παραγωγής](/el/pro/production) για την πλήρη τοπολογία πολλών ουρών
(Redis, Supervisor, αποκλειστικές συνδέσεις) και τη ρύθμιση παρτίδων.

## Εκτέλεση ανίχνευσης {#running-a-crawl}

Ξεκινήστε μια ανίχνευση από την ενέργεια **Σάρωση τώρα** του πίνακα ελέγχου ή σε headless χρήση:

```bash
# Queue a crawl (internal links only, the default).
php artisan seo-pro:broken-links-scan

# Also check outbound/external links. Each external host must still pass the
# SsrfGuard, so widen seo-pro.http.scope (or allowed_hosts) for the fetch to be
# permitted, and raise http.per_host_delay_ms so a third-party host is never hit
# too fast.
php artisan seo-pro:broken-links-scan --scope=internal_and_external
```

Και οι δύο εντολές απλώς **τοποθετούν** την ανίχνευση **στην ουρά** — ο worker κάνει την πραγματική δουλειά.

## Πώς επισημαίνεται ένας σύνδεσμος {#how-a-link-gets-flagged}

Ένας σύνδεσμος αναφέρεται ως μη λειτουργικός μόνο αφού
`seo-pro.broken_links.mark_broken_after_failures` **διαδοχικές ανιχνεύσεις** αποτύχουν να
τον προσπελάσουν (ο μετρητής μηδενίζεται με οποιαδήποτε επιτυχία· η προεπιλογή είναι **3**). Μία
προσωρινή διακοπή δεν επισημαίνει ποτέ σύνδεσμο — γι’ αυτό η ανίχνευση προορίζεται για
**προγραμματισμένη**, όχι εφάπαξ εκτέλεση. Με εβδομαδιαία συχνότητα και το προεπιλεγμένο κατώφλι, τρεις αποτυχημένες ανιχνεύσεις επιβεβαιώνουν τον σύνδεσμο: περίπου δύο εβδομάδες μετά την πρώτη
παρατήρηση ή έως περίπου τρεις εβδομάδες αφότου πάψει να λειτουργεί· αυξήστε τη συχνότητα ή μειώστε το
κατώφλι αν θέλετε ταχύτερη επιβεβαίωση.

## Έλεγχοι συνδέσμων ανά τύπο {#typed-link-inspections}

Πέρα από το «είναι προσβάσιμος;», κάθε σύνδεσμος που ανιχνεύεται περνά από σύνολο **ελέγχων ανά
τύπο** — μια ταξινομία καθαρότητας URL που επισημαίνει ασυνέπεια στην τελική κάθετο,
προβληματικές κωδικοποιήσεις, αλυσίδες ανακατευθύνσεων, href `javascript:`, μη λειτουργικές άγκυρες εντός σελίδας,
μη περιγραφικό κείμενο συνδέσμων και άλλα. Κάθε έλεγχος έχει σταθερή
**σοβαρότητα** (`critical` · `warning` · `notice` — το ίδιο λεξιλόγιο με τα
[προβλήματα σάρωσης](/el/pro/scan-issues), ώστε ένας έλεγχος αποδοχής CI να καλύπτει και τα δύο) και καταγράφεται
ανά ανίχνευση στο `seo_broken_link_inspections`. Σε αντίθεση με ένα *εύρημα* μη λειτουργικού συνδέσμου
(που επιβεβαιώνεται μόνο μετά από πολλές διαδοχικές ανιχνεύσεις), ο έλεγχος είναι στιγμιότυπο κάθε εκτέλεσης:
εμφανίζεται **αμέσως, στην πρώτη ανίχνευση** — ακριβώς αυτό που
χρειάζεται ένας έλεγχος αποδοχής CI.

### Αναφορά ελέγχων {#inspection-reference}

| Έλεγχος | Σοβαρότητα | Τι επισημαίνει | Εφαρμόζεται σε |
| --- | --- | --- | --- |
| `broken_link` | critical | Ο προορισμός επέστρεψε HTTP ≥ 400 | κάθε σύνδεσμο |
| `redirect_chain` | notice · warning | Ο προορισμός είναι προσβάσιμος μόνο μέσω ανακατεύθυνσης· `warning` πέρα από `redirect_chain_warning_hops` | κάθε σύνδεσμο |
| `link_unreachable` | notice | Μη προσβάσιμος σε αυτή την ανίχνευση (σφάλμα δικτύου, λήξη χρόνου, αποκλεισμός) — ενδέχεται να είναι προσωρινό | κάθε σύνδεσμο |
| `insecure_link` | warning | Σύνδεσμος `http://` σε ιστότοπο `https` (υποβάθμιση μεταφοράς) | κάθε σύνδεσμο |
| `trailing_slash` | notice | Εσωτερική διαδρομή παραβιάζει τη δηλωμένη σύμβαση τελικής καθέτου (**ανενεργό αν δεν οριστεί το `trailing_slash`**) | εσωτερικούς |
| `double_slash_url` | warning | Εσωτερική διαδρομή περιέχει `//` (κενό τμήμα) | εσωτερικούς |
| `duplicate_query_param` | notice | Επαναλαμβάνεται κλειδί query (`?a=1&a=2`)· η σύνταξη πίνακα `key[]` εξαιρείται | εσωτερικούς |
| `non_ascii_url` | notice | Εσωτερική διαδρομή έχει μη κωδικοποιημένους χαρακτήρες εκτός ASCII | εσωτερικούς |
| `uppercase_url` | notice | Εσωτερική διαδρομή έχει κεφαλαία γράμματα (εξετάστε παραλλαγές πεζών/κεφαλαίων που εξυπηρετούνται χωριστά) | εσωτερικούς |
| `underscore_in_url` | notice | Εσωτερική διαδρομή χρησιμοποιεί κάτω παύλες (οι παύλες είναι το προτιμώμενο διαχωριστικό για SEO) | εσωτερικούς |
| `javascript_link` | warning | Σύνδεσμος χρησιμοποιεί href `javascript:` — δεν είναι κανονικός ανιχνεύσιμος προορισμός | κάθε στοιχείο συνδέσμου |
| `missing_fragment` | warning | `#fragment` της ίδιας σελίδας χωρίς αντίστοιχο `id`/`name` στη σελίδα | συνδέσμους ίδιας σελίδας |
| `non_descriptive_anchor` | notice | Το κείμενο συνδέσμου είναι γενικό («click here», «read more») ή σκέτη URL | κάθε στοιχείο συνδέσμου |
| `absolute_internal_link` | notice | Εσωτερικός σύνδεσμος γραμμένος ως απόλυτη URL αντί για διαδρομή σχετική με τη ρίζα | εσωτερικούς |

Οι έλεγχοι καθαρότητας (τελική κάθετος, πεζά/κεφαλαία, κωδικοποίηση, διπλή κάθετος…) εφαρμόζονται
μόνο σε **εσωτερικούς** συνδέσμους — δεν ελέγχετε εσείς τη μορφή URL ενός εξωτερικού ιστοτόπου.
Οι έλεγχοι ανακατεύθυνσης, μη λειτουργικού, μη προσβάσιμου και μη ασφαλούς συνδέσμου εφαρμόζονται σε κάθε σύνδεσμο. Σύνδεσμοι
προς τις δικές σας διαδρομές framework και στατικά αρχεία παραλείπονται ώστε η πρώτη εκτέλεση να μην παράγει περιττές ειδοποιήσεις
(δείτε `exclude_paths` / `exclude_extensions` παρακάτω).

Κάθε σύνδεσμος ανακτάται στην **ακριβή URL όπως γράφτηκε** — αφαιρείται μόνο το `#fragment` —
και όχι σε κανονικοποιημένη μορφή, ώστε μια κανονική ανακατεύθυνση διακομιστή όπως
`/about/ → /about` να παρατηρείται πραγματικά και να εμφανίζεται ως `redirect_chain`
αντί να παρακάμπτεται από την κανονικοποίηση. Ελέγχεται κάθε διαφορετική γραμμένη μορφή συνδέσμου
σε μια σελίδα, ώστε τα `/page#ok` και `/page#missing` (ή `/a//b` και
`/a/b`) να αξιολογούνται όλα και όχι μόνο το πρώτο. Το υποκείμενο *εύρημα* μη λειτουργικού συνδέσμου
εξακολουθεί να ενοποιεί κάθε εναλλακτική μορφή προορισμού σε μία ταυτότητα· οι γραμμές ελέγχων
καταγράφονται ανά `(page, target, inspection)`, οπότε ένας προορισμός με πολλές μη λειτουργικές άγκυρες
εντός σελίδας εμφανίζει μία γραμμή `missing_fragment` (με παράδειγμα), όχι μία ανά άγκυρα.

### Προσαρμογή της ταξινομίας {#tuning-the-taxonomy}

Όλα βρίσκονται στο `seo-pro.broken_links.inspections`:

```php
'inspections' => [
    // Master switch. false = the crawler behaves exactly as before (broken-link
    // findings only, no inspection rows, zero added work).
    'enabled' => env('SEO_PRO_BROKEN_LINKS_INSPECTIONS', true),

    // The active rule set — remove a class to silence that inspection per client.
    'rules' => [ /* the 14 rule classes, see config/seo-pro.php */ ],

    // "Pre-learn your own noise": links whose TARGET path matches one of these
    // globs are not inspected at all (framework internals, generated routes).
    'exclude_paths' => ['/livewire/*', '/filament/*', '/admin/*', /* … */],

    // Static assets are still crawled for broken-link detection, but raise no
    // trailing-slash / casing / underscore hygiene noise.
    'exclude_extensions' => ['css', 'js', 'png', 'pdf', /* … */],

    // The site-wide trailing-slash convention. null = don't enforce a style
    // (a server-side slash redirect still shows up under redirect_chain);
    // 'always' or 'never' to enforce one.
    'trailing_slash' => null,

    'redirect_chain_warning_hops' => 2,
    'non_descriptive_anchors' => ['click here', 'read more', /* … */],
    'evidence_sample' => 5, // example rows shown per inspection in a report
],
```

**Απενεργοποιήστε έναν κανόνα** αφαιρώντας την κλάση του από το `rules`· **απενεργοποιήστε ολόκληρη την
ταξινομία** με το `SEO_PRO_BROKEN_LINKS_INSPECTIONS=false`. Δύο κανόνες αξίζει
να γνωρίζετε εκ των προτέρων:

- Το `trailing_slash` είναι **ανενεργό μέχρι να δηλώσετε σύμβαση** (`'always'` /
  `'never'`), επειδή ένας ιστότοπος που εξυπηρετεί και `/x` και `/x/` με `200` δεν έχει
  «λανθασμένη» μορφή να επισημανθεί — ενώ όπου ο διακομιστής *όντως* ορίζει την κανονική μορφή μέσω
  ανακατεύθυνσης, αυτό εμφανίζεται ήδη ως `redirect_chain`.
- Το `absolute_internal_link` ενεργοποιείται για **κάθε** εσωτερικό σύνδεσμο γραμμένο ως
  απόλυτη URL. Αν ο ιστότοπός σας παράγει απόλυτες εσωτερικές URL ως σύμβαση, αυτό σημαίνει
  πολλές (ακίνδυνες, βαθμίδας `notice`) γραμμές — αφαιρέστε το από το `rules` για να πάψουν οι ειδοποιήσεις.

## Συνεχής ενσωμάτωση {#continuous-integration}

Η σάρωση συνδέσμων και ο [έλεγχος SEO](/el/pro/scan-issues) μπορούν να **προκαλέσουν αποτυχία ενός build** και να
**γράψουν αρχείο αναφοράς**, μετατρέποντας το Rankbeam από πίνακα ελέγχου σε έλεγχο
αποδοχής ποιότητας. Το `--fail-on-error` αντιστοιχεί στη βαθμίδα `critical` (μη λειτουργικός σύνδεσμος, κρίσιμο
πρόβλημα)· το `--fail-on-warning` αποτυγχάνει για `critical` **ή** `warning` (δεν υπάρχει
ξεχωριστή βαθμίδα «error»).

```bash
# The audit: run synchronously and fail on any open critical issue, writing a
# machine-readable report. --fail-on-* require --sync — a queued scan has no
# results yet when the command returns.
php artisan seo-pro:scan --sync --fail-on-error --report=reports/audit.json

# The link scan is asynchronous, so gate it in two steps: crawl, drain the
# queue, then read the persisted results.
php artisan seo-pro:broken-links-scan
php artisan queue:work --queue=seo-broken-links --stop-when-empty
php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md
```

Το `--report=<file|dir>` γράφει το αρχείο (αν δοθεί κατάλογος, το όνομα αρχείου προκύπτει αυτόματα)·
το `--format` είναι `json` (προεπιλογή), `md` ή `html`. Το JSON είναι η μορφή για ανάλυση σε
pipeline· το HTML είναι αυτοτελής σελίδα που επισυνάπτεται σε εκτέλεση.

### GitHub Actions {#github-actions}

Ο ανιχνευτής ανακτά τις σελίδες σας μέσω HTTP, οπότε το CI πρέπει να τον κατευθύνει σε περιεχόμενο που
μπορεί να προσπελάσει — μια εφαρμογή που εξυπηρετείται τοπικά (παρακάτω) ή μια URL staging μέσω
`SEO_PRO_BROKEN_LINKS_BASE_URL`, με καταχωρισμένα τα μοντέλα/τον χάρτη ιστοτόπου σας ώστε η ανίχνευση
να έχει αρχικές διευθύνσεις.

```yaml
name: SEO gate
on: [pull_request]

jobs:
  seo:
    runs-on: ubuntu-latest
    env:
      APP_URL: http://127.0.0.1:8000
      SEO_PRO_BROKEN_LINKS_ENABLED: true
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist

      - run: |
          cp .env.example .env
          php artisan key:generate
          touch database/database.sqlite
          php artisan migrate --force
          php artisan seo-pro:install   # publishes + runs the Pro migrations

      # Serve the app so the crawler can reach it.
      - run: php artisan serve --port=8000 &

      - name: SEO audit gate
        run: php artisan seo-pro:scan --sync --fail-on-warning --report=reports/audit.md --format=md

      - name: Broken-link + inspection gate
        run: |
          php artisan seo-pro:broken-links-scan
          php artisan queue:work --queue=seo-broken-links --stop-when-empty
          php artisan seo-pro:broken-links-status --fail-on-error --report=reports/links.md --format=md

      # Always upload the reports — when a gate fails the job, you still get the
      # artifact explaining why.
      - if: always()
        uses: actions/upload-artifact@v4
        with:
          name: seo-reports
          path: reports/
```

## Προγραμματισμός {#scheduling}

Καταχωρίστε την ανίχνευση και τη συντήρησή της στο `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:broken-links-scan')->weekly();     // re-crawl
Schedule::command('seo-pro:broken-links-recover')->hourly();  // reclaim dead-worker runs
Schedule::command('seo-pro:broken-links-prune')->daily();     // retention
```

## Αναφορά εντολών {#command-reference}

| Εντολή | Τι κάνει |
| --- | --- |
| `seo-pro:broken-links-scan` | Τοποθετεί στην ουρά ανίχνευση με όρια και δυνατότητα συνέχισης (`--scope=internal_only\|internal_and_external`, `--url=*` επιπλέον αρχικές διευθύνσεις) |
| `seo-pro:broken-links-status` | Σύνοψη τελευταίας ανίχνευσης, ανοιχτά ευρήματα μη λειτουργικών συνδέσμων και πλήθη ελέγχων αυτής της εκτέλεσης· **έλεγχος αποδοχής CI** (`--fail-on-error`, `--fail-on-warning`, `--report=<file\|dir>`, `--format=json\|md\|html`) |
| `seo-pro:broken-links-cancel` | Ακυρώνει ανίχνευση που εκτελείται/βρίσκεται στην ουρά (`{run?}` — προεπιλογή η τελευταία ενεργή) |
| `seo-pro:broken-links-recover` | Σημειώνει ως αποτυχημένες ανιχνεύσεις που εγκαταλείφθηκαν από worker που σταμάτησε (ληγμένη μίσθωση) |
| `seo-pro:broken-links-prune` | Εφαρμόζει την πολιτική διατήρησης ανιχνευτή (παλιές εκτελέσεις και επιλυμένα ευρήματα) |

## Προσαρμογή {#tuning}

Τα όρια ανίχνευσης — σελίδες ανά εκτέλεση, σύνδεσμοι ανά σελίδα, όρια ανά εργασία, αυστηρός χρονικός
προϋπολογισμός και καθυστερήσεις ανά host για αποφυγή επιβάρυνσης — βρίσκονται όλα στο `seo-pro.broken_links`. Οι
προεπιλογές είναι συντηρητικές και πεπερασμένες· δείτε τον
[πίνακα ρύθμισης παρτίδων στη ρύθμιση παραγωγής](/el/pro/production) πριν τις αυξήσετε.

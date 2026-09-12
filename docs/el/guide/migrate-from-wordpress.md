---
description: "Μεταφέρετε χειροκίνητα γραμμένα δεδομένα SEO από Yoast ή Rank Math — τίτλους, περιγραφές, κανονικές URL, robots, λέξεις-κλειδιά εστίασης — στα μοντέλα Laravel. Αναφορά αντιστοίχισης πεδίων εισαγωγής."
---

# Μεταφορά από το WordPress {#migrating-from-wordpress}

Μεταφέρετε έναν ιστότοπο περιεχομένου από το WordPress; Το Rankbeam μπορεί να μεταφέρει τα μεταδεδομένα SEO που
έγραψε η ομάδα σας στο Yoast ή στο Rank Math — τίτλους, περιγραφές, κανονικές URL,
οδηγίες robots, λέξεις-κλειδιά εστίασης, παρακάμψεις κοινοποίησης — στα μοντέλα
Laravel, ώστε να μη χάσετε χρόνια βελτιστοποίησης κατά τη μετάβαση.

::: tip Κάνετε πραγματική μετάβαση παραγωγής;
Αυτή η σελίδα είναι η *αναφορά* του εργαλείου εισαγωγής (αντιστοίχιση πεδίων, tokens, κλειδιά
προέλευσης). Για τη **διαδικασία** βήμα προς βήμα με περιορισμένο κίνδυνο — συνύπαρξη, εισαγωγή, επαλήθευση,
έπειτα απόσυρση — ακολουθήστε τη
[διαδικασία μεταφοράς από το WordPress](/el/guide/wordpress-migration-runbook).
:::

Υπάρχουν δύο διαδρομές, και οι δύο μέσω της ίδιας εντολής `seo:import-from`:

| Διαδρομή | Πηγή | Κατάλληλη για |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | υπολογιστικό φύλλο που εξάγετε από το WordPress | τις περισσότερες μεταφορές από agencies· ελέγχετε τις ακριβείς URL |
| [**Βάση δεδομένων**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | ενεργή βάση δεδομένων WordPress | πλήρη πιστότητα, μαζί με παρακάμψεις OpenGraph/Twitter και ανακατευθύνσεις Rank Math |

Και οι δύο είναι **ιδιοδύναμες** (η επανεκτέλεση ενημερώνει τις ίδιες εγγραφές, ποτέ δεν δημιουργεί διπλότυπα),
υποστηρίζουν **`--dry-run`** και από προεπιλογή μόνο *συμπληρώνουν* κενά πεδία —
δεν αντικαθιστούν ποτέ δεδομένα SEO που έχετε ήδη ορίσει στο Rankbeam. Περάστε **`--overwrite`**
για να αντικαταστήσετε τις υπάρχουσες τιμές με τις εισαγόμενες.

## Πώς οι εγγραφές WordPress γίνονται εγγραφές `seo_meta` {#how-wordpress-rows-become-seo-meta-rows}

Το WordPress δεν χρησιμοποιεί πολυμορφικά δεδομένα Laravel: μια εγγραφή WordPress έχει κλειδί μια **URL** ή ένα
**ID ανάρτησης**, ενώ το `seo_meta` του Rankbeam είναι πολυμορφικό — κάθε εγγραφή συνδέεται με
πραγματικό μοντέλο Eloquent. Έτσι, το εργαλείο εισαγωγής αντιστοιχίζει κάθε εγγραφή WordPress σε ένα από
τα μοντέλα σας και αναφέρει με ακρίβεια ποιες εγγραφές συνδέθηκαν και ποιες είχαν μόνο URL:

- **Συνδεδεμένες με μοντέλο.** Ορίζετε το μοντέλο προορισμού με `--model="App\Models\Post"`.
  Το **slug** κάθε εγγραφής (το τελευταίο τμήμα διαδρομής της URL ή το
  `post_name` του WordPress) αντιστοιχίζεται στο μοντέλο — από προεπιλογή στο κλειδί διαδρομής του ή σε
  στήλη που επιλέγετε με `--match-by=`. Οι αντιστοιχισμένες εγγραφές γράφονται στο `seo_meta`.
- **Μόνο URL.** Μια εγγραφή που δεν αντιστοιχεί σε μοντέλο (ή εκτέλεση χωρίς `--model`) δεν μπορεί
  να γίνει εγγραφή `seo_meta` — δεν υπάρχει μοντέλο για σύνδεση. Αναφέρεται ως
  παραλειφθείσα με αιτία `url-only`. Η κανονική της URL μπορεί πάντως να γίνει
  [υποψήφια ανακατεύθυνση](#redirects).

Οι αναρτήσεις και οι σελίδες WordPress συνήθως αντιστοιχούν σε *διαφορετικά* μοντέλα Laravel, οπότε εκτελέστε το
εργαλείο εισαγωγής μία φορά ανά τύπο περιεχομένου και περιορίστε τις εγγραφές:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Οι προσαρμοσμένοι τύποι αναρτήσεων δεν σαρώνονται από προεπιλογή
Οι αναγνώστες βάσης δεδομένων διατρέχουν μόνο τους τύπους αναρτήσεων **`post`** και **`page`**. Οι ιστότοποι
που βασίζονται σε προσαρμοσμένους τύπους (`product`, `event`, `pathology` ενός θέματος, …) πρέπει
να ορίζουν κάθε τύπο ρητά — επαναλάβετε το `--post-type=`:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. Εισαγωγή CSV {#_1-csv-import}

Η διαδρομή CSV καλύπτει τις περισσότερες μεταφορές από agencies. Εξαγάγετε μία γραμμή ανά URL με αυτή την
κεφαλίδα (οι στήλες μπορούν να έχουν οποιαδήποτε σειρά· οι μη αναγνωρισμένες στήλες αγνοούνται και
αναφέρονται):

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Εκτελέστε:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Στήλη | Αντιστοίχιση στο `seo_meta` | Σημειώσεις |
|---|---|---|
| `url` | *(κλειδί αντιστοίχισης)* | Το slug (τελευταίο τμήμα διαδρομής) αντιστοιχίζεται στο μοντέλο. Υποχρεωτικό. |
| `title` | `title` | Περικόπτεται στους 70 χαρακτήρες· οι μεγαλύτερες τιμές αναφέρονται. |
| `description` | `description` | Περικόπτεται στους 160 χαρακτήρες. |
| `canonical` | `canonical` | Τροφοδοτεί επίσης τις [υποψήφιες ανακατευθύνσεις](#redirects). |
| `robots` | `robots` | Αποθηκεύεται αυτούσιο (π.χ. `noindex, nofollow`)· περικόπτεται στους 50 χαρακτήρες. |
| `focus_keyword` | `focus_keywords` | Διαχωρισμένες με κόμματα· η πρώτη λέξη-κλειδί είναι η κύρια. |

Οι κακοσχηματισμένες γραμμές παραλείπονται (και καταμετρώνται): γραμμή χωρίς `url` ή με
πλήθος στηλών που δεν ταιριάζει στην κεφαλίδα.

---

## 2. Εισαγωγή βάσης δεδομένων (Yoast / Rank Math) {#_2-database-import-yoast-rank-math}

Αν έχετε ακόμη τη βάση δεδομένων WordPress, το εργαλείο εισαγωγής μπορεί να διαβάσει τα μεταδεδομένα SEO
απευθείας — μαζί με τις παρακάμψεις OpenGraph/Twitter και (για Rank Math) τις
ανακατευθύνσεις, που συνήθως χάνονται κατά την εξαγωγή CSV.

### Ρυθμίστε σύνδεση προς τη βάση δεδομένων WordPress {#point-a-connection-at-wordpress}

Προσθέστε τη βάση δεδομένων WordPress ως σύνδεση στο `config/database.php`:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Έπειτα κάντε την εισαγωγή (το προεπιλεγμένο πρόθεμα πινάκων είναι `wp_`· παρακάμψτε το με `--table=`):

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

Ο αναγνώστης διατρέχει το `{prefix}posts` (δημοσιευμένες αναρτήσεις/σελίδες) και ανακτά τα
μεταδεδομένα πρόσθετου κάθε ανάρτησης από το `{prefix}postmeta`, αντιστοιχίζοντας το slug `post_name`
της ανάρτησης στο μοντέλο σας.

::: tip Μη προεπιλεγμένο πρόθεμα πινάκων
Οι υπηρεσίες διαχειριζόμενης φιλοξενίας WordPress συχνά χρησιμοποιούν τυχαίο πρόθεμα (π.χ. `wppg_`, όχι `wp_`).
Ελέγξτε τα ονόματα `CREATE TABLE` στο dump σας και περάστε το πραγματικό πρόθεμα —
`--table=wppg_` — ώστε ο αναγνώστης να βρει τα `{prefix}posts` και `{prefix}postmeta`.
:::

::: tip Ανάγνωση από επαναφερμένο dump σε MySQL 8
Αν έχετε φορτώσει ένα dump WordPress σε MySQL 8+ για τοπική ανάγνωση, χαλαρώστε την
αυστηρή λειτουργία SQL πριν φορτώσετε το `.sql` — οι προεπιλεγμένες τιμές datetime `'0000-00-00'` του WordPress
απορρίπτονται από τις προεπιλεγμένες λειτουργίες `STRICT`/`NO_ZERO_DATE` της MySQL 8, οπότε
η ίδια η εισαγωγή του dump αποτυγχάνει (`Invalid default value for 'post_date'`) πριν
εκτελεστεί η εισαγωγή SEO:

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Αντιστοίχιση πεδίων {#field-mapping}

Και τα δύο εργαλεία εισαγωγής αντιστοιχίζουν πεδία **ρητά** — ένα κλειδί χωρίς στήλη στο Core 3
αναφέρεται ως *μη αντιστοιχισμένο*, δεν επινοείται ποτέ νέα στήλη.

| Κλειδί μεταδεδομένων Yoast | Κλειδί μεταδεδομένων Rank Math | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Αποθηκεύονται μόνο αποκλίσεις από τις προεπιλογές του WordPress, οπότε μια
συνηθισμένη ευρετηριάσιμη σελίδα αφήνει το `robots` null και κληρονομεί την προεπιλογή του ιστοτόπου.
Οι ξεχωριστές σημαίες `noindex` / `nofollow` / προηγμένων ρυθμίσεων (`noarchive`, `nosnippet`,
`noimageindex`) του Yoast συντίθενται σε μία συμβολοσειρά· ο σειριοποιημένος πίνακας
`robots` του Rank Math διαβάζεται με τον ίδιο τρόπο, αφαιρώντας τις προεπιλογές `index` / `follow`.

**Μη αντιστοιχισμένα κλειδιά** (αναφέρονται, δεν αντιγράφονται ποτέ): ID συνημμένων εικόνων
(`*-image-id`), βαθμολογίες λέξεων-κλειδιών/SEO (`linkdex`, `content_score`,
`rank_math_seo_score`), επιλογές κύριας κατηγορίας και δείκτες schema rich-snippet
του Rank Math — ο [γράφος schema](/el/guide/schema) είναι μια πλουσιότερη, τυποποιημένη
αντικατάστασή τους.

::: warning Οι κανονικές URL εισάγονται αυτούσιες
Μια ρητή κανονική URL (`rank_math_canonical_url` / `_yoast_wpseo_canonical`)
αντιγράφεται **ακριβώς όπως αποθηκεύτηκε**. Αν μια σελίδα όρισε την κανονική της URL ως απόλυτη URL
στον *παλιό* τομέα — συνηθισμένο σε διαχειριζόμενη φιλοξενία / staging, π.χ.
`https://oldsite-staging.example.com/page/` — εισάγεται διατηρώντας αυτόν τον προορισμό·
το εργαλείο εισαγωγής δεν ξαναγράφει ποτέ τον host. Το `--site-url` εξάγει *διαδρομές* αιτήματος από
απόλυτες URL για [υποψήφιες ανακατευθύνσεις](#redirects) και αντιστοίχιση γραμμών CSV, αλλά
**δεν** ξαναγράφει τις αποθηκευμένες κανονικές τιμές. Μετά από μεταφορά σε άλλο τομέα, ελέγξτε
τις εισαγόμενες κανονικές URL και ενημερώστε τον host — ή εκκαθαρίστε τις, ώστε να χρησιμοποιηθεί η
κανονική URL της ίδιας της σελίδας από τον resolver. (Οι περισσότερες σελίδες δεν έχουν ρητή κανονική URL και
δεν επηρεάζονται· τα Yoast και Rank Math την παράγουν αυτόματα κατά την απόδοση.)
:::

### Tokens προτύπων {#template-tokens}

Τα Yoast και Rank Math αποθηκεύουν τίτλους και περιγραφές ως **πρότυπα** με tokens
— το Yoast χρησιμοποιεί `%%title%%`, το Rank Math `%title%`. Το εργαλείο εισαγωγής **επιλύει τα
tokens που μπορεί να προσδιορίσει** και **αφαιρεί τα υπόλοιπα**, ώστε μια αποθηκευμένη τιμή να μην είναι ποτέ ακατέργαστη
συμβολοσειρά `%%token%%`:

| Token | Επιλύεται σε |
|---|---|
| `%%title%%` / `%title%` | τον τίτλο της ανάρτησης WordPress |
| `%%sitename%%` / `%sitename%` | το όνομα του ιστολογίου από το `wp_options` (εισαγωγή βάσης δεδομένων) |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, … | *αφαιρούνται* (μένουν κενά, καθαρίζονται τα γύρω διαχωριστικά) |

Μια εκτέλεση που επέλυσε οποιοδήποτε token το αναφέρει — **ελέγξτε τους εισαγόμενους
τίτλους** για να επιβεβαιώσετε ότι διαβάζονται όπως θέλετε και προσαρμόστε όσους βασίζονταν
σε tokens που δεν μπορέσαμε να προσδιορίσουμε.

---

## Ανακατευθύνσεις {#redirects}

Το `seo_redirects` είναι λειτουργία [Rankbeam **Pro**](/el/pro/installation), οπότε ένα εργαλείο εισαγωγής του
πυρήνα δεν γράφει ποτέ απευθείας σε αυτόν τον πίνακα. Αντί γι' αυτό, περάστε `--redirects-csv=` και
το εργαλείο **παράγει CSV** με τις ίδιες στήλες με τον πίνακα ανακατευθύνσεων Pro —
`source_path,target_url,status_code,note` — το οποίο εισάγετε στο Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Από πού προέρχονται οι υποψήφιες ανακατευθύνσεις:

- **Εισαγωγή CSV** — μια γραμμή της οποίας το `canonical` δείχνει σε **διαφορετική διαδρομή** από
  τη δική της `url` γίνεται `301` από την παλιά διαδρομή προς την κανονική URL. Μια
  κανονική URL της ίδιας σελίδας (ίδια διαδρομή) *δεν* εξάγεται (θα δημιουργούσε βρόχο).
- **Βάση δεδομένων Rank Math** — ενεργοί κανόνες στον πίνακα `{prefix}rank_math_redirections`.
  Εξάγονται μόνο κανόνες **ακριβούς αντιστοίχισης**· οι κανόνες regex/contains/start/end
  αναφέρονται ως παραλειφθέντες, επειδή δεν αντιστοιχούν σε μία μοναδική διαδρομή.
- **Το Yoast (δωρεάν)** δεν έχει πίνακα ανακατευθύνσεων — μόνο το Yoast Premium έχει, και το
  σχήμα του δεν αποτελεί μέρος του δωρεάν πακέτου. Χρησιμοποιήστε τη διαδρομή CSV για ανακατευθύνσεις Yoast.

Οι υποψήφιες ανακατευθύνσεις είναι **συμβουλευτικές** — ελέγξτε το CSV και έπειτα εισαγάγετέ το στο Pro με
το [`seo-pro:redirects-import`](/el/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro),
που επικυρώνει κάθε γραμμή (απορρίπτοντας βρόχους, μη ασφαλείς προορισμούς και διπλότυπα). Η
δομή CSV είναι σταθερό συμβόλαιο — **μορφή CSV ανακατευθύνσεων v1**:
`source_path,target_url,status_code,note`.

---

## Τι σας λέει η αναφορά {#what-the-report-tells-you}

Μια εκτέλεση χωρίς `--json` εκτυπώνει πίνακα αποτελεσμάτων (δημιουργήθηκαν / ενημερώθηκαν / αμετάβλητες /
παραλείφθηκαν / σαρώθηκαν), μια **Αναφορά επαλήθευσης** και ενότητες ελέγχου:

- **Αναφορά επαλήθευσης** — η συνοπτική κατανομή που εγκρίνετε:
  **matched** (εγγραφές συνδεδεμένες με μοντέλο), **url-only** (δεν αντιστοιχίστηκαν σε μοντέλο) και
  τα πλήθη περικομμένων / μη αντιστοιχισμένων τιμών.
- **Περικομμένες τιμές** — τιμές που συντομεύτηκαν για να χωρέσουν σε στήλη `seo_meta`.
- **Δεν εισήχθησαν** — κλειδιά προέλευσης που είχαν δεδομένα αλλά δεν έχουν θέση στο Core 3,
  **συμπεριλαμβανομένης κάθε διακριτής τιμής `author`** (ο συντάκτης δεν είναι αποθηκευμένη στήλη —
  αφορά το [`getSEOAuthor()`](/el/concepts/resolver-precedence) — οπότε η
  αναφορά απαριθμεί όσα χρειάζονται νέα θέση αντί να τα αφήσει να χαθούν σιωπηλά).
- **Υποψήφιες ανακατευθύνσεις** — πόσες γράφτηκαν και σε ποιο αρχείο.
- **Παραλειφθείσες εγγραφές ανά αιτία** — εγγραφές μόνο URL, αναρτήσεις χωρίς μεταδεδομένα SEO, κανόνες
  ανακατεύθυνσης χωρίς ακριβή αντιστοίχιση.
- **Προειδοποιήσεις** — π.χ. ότι επιλύθηκαν tokens προτύπων.

Προσθέστε `--json` για μηχαναγνώσιμη έκδοση όλων των παραπάνω (το μπλοκ
`verification` περιλαμβάνει τα πλήθη matched/url-only και κάθε τιμή συντάκτη).

### Επαλήθευση {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

Το `--strict` τερματίζει με μη μηδενικό κωδικό αν οποιαδήποτε σελίδα έχει ζήτημα. Δείτε τον
[Δωρεάν έλεγχο SEO](/el/guide/audit). Για την πλήρη, διαδοχική διαδικασία μετάβασης —
συνύπαρξη → εισαγωγή → επαλήθευση → απόσυρση — ακολουθήστε τη
[διαδικασία μεταφοράς από το WordPress](/el/guide/wordpress-migration-runbook).

---

Έρχεστε από πακέτο SEO για **Laravel** (ralphjsmit, artesaos, Spatie);
Δείτε τη [Μεταφορά από άλλα πακέτα Laravel](/el/guide/migrate-from-other-packages).

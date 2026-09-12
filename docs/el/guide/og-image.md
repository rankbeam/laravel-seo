---
description: "Δώστε σε κάθε σελίδα δική της εικόνα Open Graph 1200×630, από πρότυπο Blade μέσω headless browser για σωστή αναδίπλωση και περικοπή τίτλων. Δωρεάν λειτουργία πυρήνα, ανενεργή από προεπιλογή."
---

# Παραγόμενες εικόνες OG {#generated-og-images}

Από τον πυρήνα 3.20, η απόδοση Chrome απενεργοποιεί τη JavaScript και αποκλείει αιτήματα πόρων HTTP(S), FTP
και WebSocket. Τα προσαρμοσμένα πρότυπα πρέπει να χρησιμοποιούν στατικό HTML/CSS και
ενσωματωμένους πόρους, όπως τα παρεχόμενα πρότυπα.

Μια σελίδα χωρίς κάρτα κοινοποίησης χρησιμοποιεί την κοινή `default_og_image` — την
ίδια εικόνα σε κάθε κοινοποίηση. Αυτή η λειτουργία δίνει σε κάθε σελίδα **δική της** κάρτα
Open Graph / Twitter 1200×630, που αποδίδεται από πρότυπο Blade μέσω πραγματικού headless
browser (μέσω [spatie/browsershot](https://github.com/spatie/browsershot)), ώστε ο
τίτλος να αναδιπλώνεται σε γραμμές, οι τόνοι να αποδίδονται, το CJK να χρησιμοποιεί την κατάλληλη εναλλακτική γραμματοσειρά και
οι υπερβολικά μεγάλοι τίτλοι να περικόπτονται σωστά — πράγματα που μια αυτοσχέδια βιβλιοθήκη εικόνας δεν χειρίζεται
σωστά από μόνη της.

Είναι δωρεάν λειτουργία του πυρήνα και είναι **ανενεργή από προεπιλογή**. Όταν είναι ανενεργή,
η `default_og_image` χρησιμοποιείται αμετάβλητη και το πακέτο παραμένει χωρίς πρόσθετη εξάρτηση.

::: info Στατική προδημιουργία, εκ σχεδιασμού
Οι κάρτες δημιουργούνται εκ των προτέρων από εντολή Artisan, όχι δυναμικά κατά τη διάρκεια
web αιτήματος. Μια σελίδα συνδέεται μόνο με κάρτα που υπάρχει ήδη στον δίσκο — επομένως ένα
αίτημα επισκέπτη δεν εκκινεί ποτέ browser ούτε συνδέεται με ανύπαρκτη εικόνα (404).
Δεν υπάρχει **endpoint ζωντανής απόδοσης** (δείτε τις [Επισημάνσεις](#caveats)).
:::

## Απαιτήσεις {#requirements}

Ο driver του browser είναι προαιρετική εξάρτηση, οπότε ο δωρεάν πυρήνας εγκαθίσταται χωρίς
αυτόν. Για να ενεργοποιήσετε τη λειτουργία χρειάζεστε στην εφαρμογή σας:

```bash
composer require spatie/browsershot
```

Και το περιβάλλον εκτέλεσης που χειρίζεται το Browsershot:

- **Node.js** στο μηχάνημα.
- **Puppeteer**, εγκατεστημένο στον **ριζικό φάκελο της εφαρμογής**, ώστε να το εντοπίζει το Node:
  ```bash
  npm install puppeteer
  ```
- **Chrome / Chromium** — το Puppeteer κατεβάζει δικό του Chromium από προεπιλογή· στην
  παραγωγή συνήθως θα ορίζετε το Chrome του συστήματος (δείτε το
  [`chrome_path`](#configuration)).

::: warning Εγκαταστήστε το puppeteer στον ριζικό φάκελο της εφαρμογής στα Windows
Στα Windows, εγκαταστήστε το `puppeteer` στον ριζικό φάκελο της εφαρμογής αντί να βασιστείτε στο
`npm_module_path`. Αυτό το κλειδί ρυθμίσεων αντιστοιχεί στο `setNodeModulePath()` του Browsershot,
που παράγει πρόθεμα POSIX `NODE_PATH=…` και **δεν έχει αποτέλεσμα στα Windows** — εκεί το Node
εντοπίζει modules ανεβαίνοντας στους γονικούς φακέλους από την εφαρμογή, οπότε λειτουργεί η εγκατάσταση
στον ριζικό φάκελο. Δείτε τις [Επισημάνσεις](#caveats).
:::

## Ενεργοποίηση {#enabling}

Δημοσιεύστε τις ρυθμίσεις αν δεν το έχετε κάνει (`php artisan vendor:publish --tag=seo-config`)
και ενεργοποιήστε τον διακόπτη:

```php
// config/seo.php
'og_image' => [
    'enabled' => true,   // requires spatie/browsershot + Chrome
],
```

Έπειτα **προδημιουργήστε** τις κάρτες (τίποτα δεν αποδίδεται μέχρι να το κάνετε):

```bash
php artisan seo:og-images
```

## Πώς λειτουργεί η επίλυση {#how-resolution-works}

Η δημιουργία δεν παρακάμπτει ποτέ εικόνα που ορίσατε. Όταν η λειτουργία είναι ενεργή, ο resolver
συμπληρώνει το `og:image` **μόνο όταν η σελίδα δεν έχει δική της εικόνα** — δηλαδή όταν
το επιλυμένο `og:image` είναι κενό ή παραμένει η στατική
`default_og_image` ολόκληρου του ιστοτόπου. Μια ρητή εικόνα ανά μοντέλο (από `getSEOImage()`, εγγραφή
`seo_meta`, πεδίο περιεχομένου, …) υπερισχύει πάντα μιας παραγόμενης κάρτας.

Για να αποφασίσει την τιμή, ο resolver καλεί την αναζήτηση του generator που **ελέγχει την ύπαρξη**:
υπολογίζει τη διαδρομή αποθήκευσης της κάρτας και επιστρέφει τη δημόσια URL της **μόνο αν
το αρχείο υπάρχει ήδη στον ρυθμισμένο δίσκο**. Δεν αποδίδει ποτέ εικόνα. Αυτή είναι όλη η
λογική προστασίας:

- Ένα web αίτημα **δεν εκκινεί ποτέ browser** — στη χειρότερη περίπτωση συνδέεται με τη στατική
  `default_og_image`, ακριβώς όπως πριν υπάρξει η λειτουργία.
- Μια σελίδα **δεν συνδέεται ποτέ με εικόνα που δεν έχει ακόμη δημιουργηθεί**, οπότε δεν υπάρχει διάστημα κατά το οποίο
  οι κοινοποιήσεις οδηγούν σε 404.

Καλύπτετε το κενό ανάμεσα στο «το περιεχόμενο άλλαξε» και στο «η κάρτα υπάρχει» εκτελώντας την
εντολή [`seo:og-images`](#the-seo-og-images-command) — κατά την ανάπτυξη και/ή
με πρόγραμμα.

## Η εντολή `seo:og-images` {#the-seo-og-images-command}

Προδημιουργεί τις κάρτες ώστε ο resolver να έχει κάτι να εξυπηρετήσει.

```bash
php artisan seo:og-images                         # warm the configured models
php artisan seo:og-images --model="App\Models\Post"
php artisan seo:og-images --force                 # re-render even existing cards
php artisan seo:og-images --prune                 # + delete orphaned cards
```

- `--model=*` — μία ή περισσότερες κλάσεις μοντέλων για προδημιουργία. Επαναλήψιμη επιλογή. Αν παραλειφθεί, η
  εντολή χρησιμοποιεί το `seo.og_image.models`, με εναλλακτική τα
  [μοντέλα χαρτών ιστοτόπου](/el/guide/sitemaps) (`seo.sitemap.models`) — την ίδια
  προσέγγιση κοινών πηγών χαρτών με το `seo:llms-txt`.
- `--force` — αποδίδει ξανά κάρτες που υπάρχουν ήδη (χρησιμοποιήστε το μετά από αλλαγή προτύπου
  ή εταιρικών χρωμάτων χωρίς αύξηση του `cache_version`).
- `--prune` — μετά την προδημιουργία, διαγράφει αποθηκευμένες κάρτες κάτω από τη ρυθμισμένη διαδρομή που
  δεν αντιστοιχούν πλέον στο περιεχόμενο κάποιου τρέχοντος μοντέλου (δείτε παρακάτω). Για ασφάλεια, αφαιρεί μόνο
  αρχεία των οποίων τα ονόματα είναι παραγόμενα hashes περιεχομένου (ποτέ άλλους
  πόρους σας στον ίδιο φάκελο) και **αγνοείται σε εκτέλεση περιορισμένη με `--model`**
  (της οποίας το σύνολο διατήρησης δεν θα κάλυπτε τα άλλα μοντέλα σας) — εκτελέστε το χωρίς `--model`.

Κάθε μοντέλο πρέπει να χρησιμοποιεί το trait `HasSEO`. Εγγραφή χωρίς τίτλο παραλείπεται
(δεν υπάρχει τίποτα να μπει στην κάρτα)· η εντολή αναφέρει πλήθη `generated`, `skipped`,
`failed` και (με `--prune`) `pruned`.

### Προγραμματισμός {#scheduling}

Προδημιουργείτε με πρόγραμμα ώστε οι κάρτες να ακολουθούν το περιεχόμενό σας και καθαρίζετε τις ορφανές που μένουν
όταν αλλάζουν οι τίτλοι:

```php
// routes/console.php
Schedule::command('seo:og-images --prune')->daily();
```

### Το μοντέλο ακύρωσης cache {#the-invalidation-model}

Το όνομα αρχείου μιας κάρτας είναι **hash όλων όσων επηρεάζουν τα pixels της** — του
τίτλου, του ονόματος ιστοτόπου, του ονόματος προτύπου, του driver, των διαστάσεων, των εταιρικών χρωμάτων διαβάθμισης,
του αριθμού `cache_version` **και της εγκατεστημένης έκδοσης του πακέτου**.

Αυτό το hash είναι το κλειδί cache και έχει δύο συνέπειες που αξίζει να κατανοήσετε:

- **Αλλαγή τίτλου → νέο hash → νέο αρχείο.** Η παλιά κάρτα είναι πλέον *ορφανή* στον
  δίσκο και η σελίδα επιστρέφει στη στατική προεπιλογή μέχρι να προδημιουργήσετε ξανά. Η εκτέλεση
  της εντολής δημιουργεί τη νέα κάρτα· το `--prune` διαγράφει την ορφανή. Αυτό είναι το
  μοντέλο ακύρωσης — δεν υπάρχει ξεχωριστό βήμα «εκκαθάριση μίας σελίδας».
- **Αύξηση του `cache_version` ή αναβάθμιση του πακέτου → αλλάζουν όλα τα hashes.** Χρησιμοποιήστε
  το `cache_version` μετά από επεξεργασία προτύπου ή εταιρικών χρωμάτων για να ακυρώσετε κάθε
  κάρτα ταυτόχρονα· η αναβάθμιση πακέτου συνυπολογίζεται αυτόματα, οπότε μια νέα έκδοση που
  αλλάζει το παρεχόμενο πρότυπο δεν μπορεί να εξυπηρετεί παλιές κάρτες.

## Παρεχόμενα πρότυπα {#bundled-templates}

Το πακέτο περιλαμβάνει τρία πρότυπα, όλα 1200×630 στην ίδια εταιρική διαβάθμιση:

| Πρότυπο | Κατάλληλο για | Εμφανίζει |
| --- | --- | --- |
| `seo::og.default` | Οτιδήποτε | Τίτλο + όνομα ιστοτόπου |
| `seo::og.article` | Αναρτήσεις ιστολογίου, ειδήσεις | Ετικέτα ενότητας + τίτλο + γραμμή συντάκτη · ημερομηνίας |
| `seo::og.product` | Προϊόντα, καταχωρίσεις | Σήμα επωνυμίας + ετικέτα κατηγορίας + τίτλο + περιγραφή |

Επιλέξτε ένα καθολικά με `seo.og_image.template` ή αντιστοιχίστε πρότυπα **ανά τύπο
μοντέλου**, ώστε ένα άρθρο και ένα προϊόν να λαμβάνουν διαφορετικές κάρτες αυτόματα:

```php
// config/seo.php
'og_image' => [
    'templates' => [
        App\Models\Post::class    => 'seo::og.article',
        App\Models\Product::class => 'seo::og.product',
    ],
],
```

Ένα μοντέλο μπορεί επίσης να παρακάμπτει το δικό του πρότυπο κατά την εκτέλεση ορίζοντας
`getOgImageTemplate(): ?string` (επιστρέψτε όνομα προβολής ή `null` για χρήση της
αντιστοίχισης/προεπιλογής). Προτεραιότητα: το hook μοντέλου, έπειτα η αντιστοίχιση `templates` και έπειτα το
καθολικό `template`.

## Προσαρμογή του προτύπου {#customizing-the-template}

Η κάρτα είναι προβολή Blade (`seo::og.default` από προεπιλογή) που αποδίδεται σε
αυτοτελές έγγραφο HTML — η παρεχόμενη γραμματοσειρά ενσωματώνεται ως data URI ώστε ο
browser να μη χρειάζεται δίκτυο. Δύο τρόποι αλλαγής:

**Δημοσιεύστε και επεξεργαστείτε την παρεχόμενη προβολή:**

```bash
php artisan vendor:publish --tag=seo-views
```

Έπειτα επεξεργαστείτε το `resources/views/vendor/seo/og/default.blade.php`.

**Ή ορίστε τη δική σας προβολή:**

```php
// config/seo.php
'og_image' => [
    'template' => 'og.my-card',   // resources/views/og/my-card.blade.php
],
```

Το πρότυπο λαμβάνει αυτές τις μεταβλητές:

| Μεταβλητή | Τύπος | Σημειώσεις |
| --- | --- | --- |
| `$title` | `string` | Ο τίτλος OG αν έχει οριστεί, διαφορετικά ο τίτλος σελίδας. |
| `$siteName` | `?string` | Το επιλυμένο `og:site_name`. |
| `$fontDataUri` | `string` | Η παρεχόμενη έντονη γραμματοσειρά ως URI `data:` (κενή συμβολοσειρά αν δεν είναι διαθέσιμη — τότε ο browser χρησιμοποιεί τη δική του sans-serif). |
| `$gradientFrom` | `string` | `seo.og_image.gradient_from`. |
| `$gradientTo` | `string` | `seo.og_image.gradient_to`. |
| `$width` | `int` | Πλάτος εξόδου (προεπιλογή `1200`). |
| `$height` | `int` | Ύψος εξόδου (προεπιλογή `630`). |
| `$locale` | `?string` | Επιλυμένο locale σελίδας, για το γνώρισμα `<html lang>`. |
| `$author` | `?string` | Συντάκτης άρθρου (χρησιμοποιείται από το `seo::og.article`). |
| `$publishedDate` | `?string` | Ημερομηνία δημοσίευσης για το `seo::og.article`: μεσαία μορφή ICU στο locale της σελίδας όταν είναι διαθέσιμη· διαφορετικά το Carbon μεταφράζει τον μήνα με σειρά `M j, Y`. Null όταν δεν παρέχεται ημερομηνία. |
| `$section` | `?string` | Ενότητα / κατηγορία περιεχομένου (ετικέτα άρθρου, ετικέτα προϊόντος). |
| `$description` | `?string` | Η περιγραφή OG, διαφορετικά η περιγραφή σελίδας (χρησιμοποιείται από το `seo::og.product`). |

::: info Το όνομα προτύπου είναι μέρος του κλειδιού cache
Τόσο το **όνομα** προτύπου όσο και τα χρώματα διαβάθμισης τροφοδοτούν το hash περιεχομένου, οπότε
η αλλαγή προτύπου ή χρωμάτων ακυρώνει αυτόματα τις υπάρχουσες κάρτες.
Η επεξεργασία προτύπου *επί τόπου* δεν το κάνει (το όνομα μένει ίδιο) — αυξήστε το
`cache_version` (ή εκτελέστε `--force`) μετά την επεξεργασία.
:::

## Ρυθμίσεις {#configuration}

```php
// config/seo.php
'og_image' => [
    'enabled' => false,             // master switch (off by default)
    'driver'  => 'browsershot',     // the render driver; register your own via OgImageManager::extend()
    'template' => 'seo::og.default', // the default Blade view rendered as the card
    'templates' => [],              // per-model-class template overrides (see "Bundled templates")
    'strip_title_suffix' => true,   // trim seo.title_suffix off the card title (the card shows the site name itself)

    'width'  => 1200,               // social-card standard
    'height' => 630,

    'disk' => 'public',             // must be publicly served — its url() becomes the og:image
    'path' => 'og-images',          // path prefix on that disk

    // Models seo:og-images warms. Empty → falls back to seo.sitemap.models.
    // Accepts a list [Post::class] or a map [Post::class => [...]].
    'models' => [],

    // Bump to invalidate every card after editing a template/colors in place.
    // The installed package version is folded in too, so an upgrade busts them.
    'cache_version' => 1,

    // Brand gradient (diagonal) for the bundled default template.
    'gradient_from' => '#1e2a5a',
    'gradient_to'   => '#3D5AFE',

    // Browsershot binary paths. null = its defaults (node/npx on PATH,
    // puppeteer's bundled Chromium). Set explicitly in production.
    'chrome_path'     => null,      // path to a system Chrome/Chromium
    'node_binary'     => null,      // path to the node binary
    'npm_module_path' => null,      // node_modules dir (no-op on Windows — see Caveats)

    'timeout' => 60,                // hard per-render timeout, seconds

    // Launch Chrome with --no-sandbox; weakens browser isolation.
    // Prefer configuring the host to support Chrome's sandbox (see below).
    'no_sandbox' => false,

    // Extra Chromium CLI flags, e.g. ['disable-dev-shm-usage', 'disable-gpu']
    // on a low-/dev-shm container. Leading "--" optional; map form for
    // value-bearing flags: ['proxy-server' => 'http://…'].
    'browsershot_args' => [],

    // Fallback font families for glyphs the bundled face lacks (CJK, Thai,
    // Arabic, …). null = the built-in Noto list; see "Fonts and non-Latin
    // scripts" below.
    'font_stack' => null,
],
```

Οι περισσότερες βαθμωτές τιμές έχουν αντίστοιχη μεταβλητή περιβάλλοντος (`SEO_OG_IMAGE_ENABLED`,
`SEO_OG_IMAGE_DISK`, `SEO_OG_IMAGE_CHROME_PATH`, `SEO_OG_IMAGE_NO_SANDBOX`, …) —
δείτε την πλήρη λίστα στο αρχείο ρυθμίσεων. Τα κλειδιά τύπου πίνακα (`templates`,
`models`, `browsershot_args`, `font_stack`) τροποποιούνται απευθείας στο αρχείο ρυθμίσεων.

Ο δίσκος πρέπει να **εξυπηρετείται δημόσια**, επειδή ο resolver χρησιμοποιεί το `url()` του ως
τιμή `og:image`. Με τον δίσκο `public`, εκτελέστε `php artisan storage:link` μία φορά ώστε
το `public/storage` να δείχνει σε αυτόν.

## Εκτέλεση σε Linux (το sandbox) {#running-on-linux-the-sandbox}

Σε μηχανήματα που περιορίζουν τους μηχανισμούς sandbox του Chrome, το `php artisan seo:og-images`
μπορεί να αποτύχει με:

```
No usable sandbox! Update your OS ... or see
https://chromium.googlesource.com/.../linux/suid_sandbox_development.md
```

Μία πιθανή αιτία είναι οι περιορισμένοι χώροι ονομάτων χρήστη στο Ubuntu 23.10+.
Ελέγξτε τον [οδηγό αντιμετώπισης προβλημάτων Puppeteer](https://pptr.dev/troubleshooting)
και το πραγματικό σφάλμα εκκίνησης του browser. Προτιμήστε τη διόρθωση των ρυθμίσεων του μηχανήματος
ώστε το Chrome να διατηρήσει το sandbox του.

**1. Ρητή εναλλακτική: εκτέλεση Chrome με `--no-sandbox`.** Αυτό απενεργοποιεί την
απομόνωση του browser. Χρησιμοποιήστε το μόνο αν η ανάπτυξή σας αποδέχεται συνειδητά αυτόν τον συμβιβασμό:

```php
// config/seo.php
'og_image' => [
    'no_sandbox' => true,   // or set SEO_OG_IMAGE_NO_SANDBOX=true
],
```

Το Rankbeam αποδίδει στατικό παραγόμενο HTML και αποκλείει αιτήματα απομακρυσμένων πόρων, αλλά
αυτοί οι έλεγχοι δεν αντικαθιστούν το sandbox του Chrome. Κρατήστε τη διεργασία απόδοσης
χωρίς αυξημένα προνόμια και απομονωμένη από άσχετους φόρτους εργασίας και μυστικά.

**2. Διατηρήστε το sandbox.** Αφήστε το `no_sandbox` ανενεργό. Όταν η αιτία είναι το AppArmor,
προσαρμόστε ένα προφίλ για το ακριβές εκτελέσιμο Chrome· δείτε τις
[οδηγίες Chromium](https://chromium.googlesource.com/chromium/src/+/main/docs/security/apparmor-userns-restrictions.md).
Για παράδειγμα:

```
# /etc/apparmor.d/chrome-og
abi <abi/4.0>,
include <tunables/global>
profile chrome-og /path/to/chrome flags=(unconfined) {
  userns,
  include if exists <local/chrome-og>
}
```

Έπειτα φορτώστε το προφίλ με `sudo apparmor_parser -r /etc/apparmor.d/chrome-og`
και επαληθεύστε ότι το Chrome εκκινεί με ενεργό sandbox.

::: tip Άλλες σημαίες
Για container με λίγη κοινόχρηστη μνήμη (η άλλη συνηθισμένη αποτυχία Linux —
κατάρρευση Chrome στη μέση της απόδοσης), προσθέστε σημαίες μέσω `browsershot_args`:

```php
'browsershot_args' => ['disable-dev-shm-usage'],
```
:::

## Προσαρμοσμένοι drivers {#custom-drivers}

Το `browsershot` είναι ο μόνος παρεχόμενος driver, αλλά η απόδοση βρίσκεται πίσω από συμβόλαιο
(`Rankbeam\Seo\Contracts\OgImageRenderer`). Καταχωρίστε τον δικό σας — για παράδειγμα μηχανή απόδοσης με canvas ή
υπηρεσία — και επιλέξτε τον με `seo.og_image.driver`:

```php
use Rankbeam\Seo\Services\OgImage\OgImageManager;

app(OgImageManager::class)->extend('my-driver', fn ($app) => new MyRenderer());
```

Ένας driver μετατρέπει μόνο αυτοτελή συμβολοσειρά HTML σε bytes PNG σε δεδομένες διαστάσεις·
δεν αναλαμβάνει διάταξη ή πρότυπα.

## Γραμματοσειρές και μη λατινικά συστήματα γραφής {#fonts-and-non-latin-scripts}

Η παρεχόμενη γραμματοσειρά κάρτας (Noto Sans Bold, OFL) καλύπτει **λατινικά, κυριλλικά και ελληνικά**.
Κάθε άλλο σύστημα γραφής — κινεζικά, ιαπωνικά, κορεατικά, ταϊλανδικά, αραβικά, εβραϊκά,
Devanagari, emoji — προέρχεται από γραμματοσειρές **εγκατεστημένες στο μηχάνημα που εκτελεί
το `seo:og-images`**. Σκόπιμα δεν παρέχεται τίποτα άλλο: μία γραμματοσειρά CJK είναι 16 MB+,
και η εναλλακτική γραμματοσειρά ανά χαρακτήρα του Chrome λειτουργεί σωστά μόλις
υπάρχει κατάλληλη γραμματοσειρά στο μηχάνημα.

Τρία στοιχεία το κάνουν αξιόπιστο (3.15):

1. **Στοίβα `font-family` ανά σύστημα γραφής σε κάθε παρεχόμενο πρότυπο.** Το body
   δηλώνει πρώτα το `'OGBrand'` (την παρεχόμενη γραμματοσειρά), έπειτα
   το `seo.og_image.font_stack` — από προεπιλογή `Noto Sans`, τις τέσσερις οικογένειες `Noto Sans CJK`,
   `Noto Sans Thai`, `Noto Sans Arabic`, `Noto Sans Hebrew`,
   `Noto Sans Devanagari`, `Noto Color Emoji` — και έπειτα `sans-serif`. Το Chrome επιλέγει
   ανά χαρακτήρα την πρώτη εγκατεστημένη οικογένεια, οπότε η λίστα μόνο
   βοηθά· μια οικογένεια που λείπει παραλείπεται. Η **οικογένεια CJK της γλώσσας σελίδας
   μετακινείται πρώτη** (`ja` → JP, `zh-Hans` → SC, `zh-Hant` / `zh-TW` /
   `zh-HK` → TC, `ko` → KR), επειδή το ίδιο σημείο κώδικα Han σχεδιάζεται
   διαφορετικά σε κάθε εθνική γραμματοσειρά (ενοποίηση Han), και το γνώρισμα `<html lang>`
   μεταφέρει το locale της σελίδας σε μορφή BCP 47. Η στοίβα είναι μέρος του
   κλειδιού cache, οπότε η αλλαγή της αποδίδει ξανά κάθε κάρτα.

2. **Προέλεγχος στο `seo:og-images`.** Πριν από την απόδοση, η εντολή ρωτά το
   fontconfig (`fc-list :lang=ja`, `th`, `ar`, …) αν μια γραμματοσειρά καλύπτει τα
   συστήματα γραφής του τίτλου, του ονόματος ιστοτόπου και της περιγραφής, μαζί με ένα σύστημα γραφής που εμφανίζεται λίγο
   σε μικτό κείμενο, και προειδοποιεί **μία φορά ανά σύστημα γραφής**, αναφέροντας
   το πακέτο προς εγκατάσταση:

   ```
   No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
   ```

   Όπου δεν υπάρχει fontconfig (Windows, macOS, ελάχιστο container), δεν εμφανίζει μήνυμα
   αντί να μαντεύει. Η ίδια η απόδοση δεν αποτυγχάνει ποτέ λόγω γραμματοσειράς που λείπει —
   το Chrome σχεδιάζει πλαίσια .notdef — και γι' αυτό ακριβώς υπάρχει η προειδοποίηση.

3. **Δείγμα γλυφών ανά σύστημα γραφής στη ζωντανή δοκιμή smoke.** Με
   `SEO_OG_IMAGE_LIVE_TEST=1`, το `tests/Feature/OgImage/BrowsershotSmokeTest.php`
   αποδίδει τίτλο σε ja, zh-Hans, zh-Hant, ko, el, ru, tr, th, ar, he και hi
   δίπλα σε δείγμα ελέγχου ίδιου μήκους από μη εκχωρημένο σημείο κώδικα (εγγυημένα
   πλαίσια) και αποτυγχάνει, ονομάζοντας το σύστημα γραφής και το πακέτο, όταν τα δύο PNG είναι
   ταυτόσημα byte προς byte. Είναι βασικός έλεγχος, όχι απόδειξη για κάθε γλύφο: μικτό λατινικό
   κείμενο ή διαφορετική αναδίπλωση μπορεί να κάνει τις εικόνες να διαφέρουν ακόμη κι όταν λείπουν κάποιοι
   γλύφοι. Εξετάστε την πραγματική απόδοση και τις γραμματοσειρές που χρησιμοποιούνται στο μηχάνημα
   ανάπτυξης. Η προειδοποίηση FontProbe ανά γλώσσα είναι επίσης προέλεγχος, όχι πλήρες
   πιστοποιητικό κάλυψης γραμματοσειράς. Δεν υπάρχει εντολή `seo:doctor` στον πυρήνα· χρησιμοποιήστε
   το `seo:og-images` για αυτόν τον προέλεγχο.

Σε Debian/Ubuntu:

```bash
apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji
fc-cache -f
```

Δικά σας πρότυπα που δημοσιεύτηκαν με `--tag=seo-views` πριν από την 3.15 εξακολουθούν να λειτουργούν: λαμβάνουν
τις νέες μεταβλητές `$fontFamily` και `$lang` και μπορούν να τις αγνοήσουν.

## Επισημάνσεις {#caveats}

Διατυπωμένες με ειλικρίνεια, επειδή προκαλούν προβλήματα στην παραγωγή:

- **Μόνο προδημιουργία — χωρίς endpoint ζωντανής απόδοσης (v1).** Δεν υπάρχει διαδρομή που
  αποδίδει κάρτα κατόπιν αιτήματος. Επειδή τίποτα δεν αποδίδεται σε web αίτημα, δεν υπάρχει
  **επιφάνεια signed-URL / SSRF / DoS για ρύθμιση ή προστασία** — ο συμβιβασμός είναι
  ότι πρέπει να εκτελείτε το [`seo:og-images`](#the-seo-og-images-command) (κατά την ανάπτυξη
  και/ή με πρόγραμμα) για να υπάρχουν κάρτες.
- **Το `npm_module_path` δεν έχει αποτέλεσμα στα Windows.** Αντιστοιχεί στο
  `setNodeModulePath()` του Browsershot, που προσθέτει πρόθεμα POSIX `NODE_PATH=…` στην εντολή —
  το οποίο αγνοούν τα Windows. Στα Windows, εγκαταστήστε το `puppeteer` στον **ριζικό φάκελο της εφαρμογής**
  ώστε το Node να το εντοπίζει ανεβαίνοντας στους γονικούς φακέλους. (Σε Linux/macOS η ρύθμιση λειτουργεί
  όπως αναμένεται.)
- **Τα μη λατινικά συστήματα γραφής χρειάζονται γραμματοσειρά στο μηχάνημα.** Δείτε την ενότητα
  [Γραμματοσειρές και μη λατινικά συστήματα γραφής](#fonts-and-non-latin-scripts): η παρεχόμενη
  γραμματοσειρά καλύπτει λατινικά, κυριλλικά και ελληνικά· όλα τα υπόλοιπα προέρχονται από γραμματοσειρές
  εγκατεστημένες στην εικόνα ανάπτυξης και η εντολή σάς ενημερώνει όταν λείπει κάποια.
- **Η αποτυχία δεν διακόπτει τη σελίδα.** Αν αποτύχει μια απόδοση (πακέτο που λείπει, κατάρρευση browser, timeout), η
  εντολή το αναφέρει και η σελίδα απλώς κρατά τη στατική `default_og_image` — ένας
  προβληματικός browser δεν προκαλεί ποτέ σφάλμα 500 στη σελίδα.

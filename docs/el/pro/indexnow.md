---
description: "Ενημερώστε τις μηχανές αναζήτησης μόλις δημοσιευτεί ή ενημερωθεί μια URL. Το Pro υποβάλλει στο κοινό api.indexnow.org, που διαβιβάζει στις συμμετέχουσες μηχανές. Ανενεργό από προεπιλογή."
---

# IndexNow — ειδοποίηση ευρετηρίασης κατά τη δημοσίευση {#indexnow-—-push-on-publish-indexing}

Αντί να περιμένετε να βρει ένας ανιχνευτής μια αλλαγμένη σελίδα, το **IndexNow** σάς επιτρέπει
να *ενημερώσετε* τις μηχανές αναζήτησης μόλις δημοσιευτεί ή ενημερωθεί μια URL. Το Pro υποβάλλει
στο κοινό endpoint `api.indexnow.org`, που **διαβιβάζει σε κάθε
συμμετέχουσα μηχανή** με μία κλήση (χωρίς ξεχωριστές κλήσεις ανά μηχανή). Οι
[επίσημες συχνές ερωτήσεις](https://www.indexnow.org/faq) απαριθμούν Amazon, Bing, Naver, Seznam,
Yandex και Yep. Μια ειδοποίηση δεν εγγυάται ευρετηρίαση.

Είναι **ανενεργό από προεπιλογή**. Δεν γίνεται καμία δικτυακή κλήση μέχρι να το ενεργοποιήσετε και να
υποβληθεί μια URL.

## Ρύθμιση {#setup}

### 1. Δημιουργήστε κλειδί {#_1-generate-a-key}

Το IndexNow χρησιμοποιεί **κλειδί** για να επαληθεύσει τον έλεγχο του host. Το Pro δέχεται 8–128 χαρακτήρες από
`[a-f0-9-]` (ιδανική είναι μια δεκαεξαδική συμβολοσειρά 32 χαρακτήρων). Δημιουργήστε ένα μία φορά και κρατήστε το
σταθερό, έπειτα διαθέστε το μέσω του περιβάλλοντος:

```dotenv
SEO_PRO_INDEXNOW_ENABLED=true
SEO_PRO_INDEXNOW_KEY=0123456789abcdef0123456789abcdef
```

::: tip Το κλειδί διαβάζεται μέσω ρυθμίσεων, οπότε διατηρείται μετά το `config:cache`
Σε αντίθεση με τα διαπιστευτήρια Search Console, το κλειδί IndexNow **δεν είναι μυστικό** —
εξυπηρετείται δημόσια στο `/{key}.txt` για να αποδείξει ότι κατέχετε τον host. Έτσι, το Pro το επιλύει
μέσω του επιπέδου ρυθμίσεων (`indexnow.key`, με προεπιλογή
`env('SEO_PRO_INDEXNOW_KEY')`). Αυτό είναι σκόπιμο: τιμές που ορίζονται **μόνο στο `.env`** δεν είναι διαθέσιμες στο `env()` μετά το
`config:cache`, επειδή το Laravel δεν φορτώνει πλέον αυτό το αρχείο. Οι πραγματικές μεταβλητές
περιβάλλοντος διεργασίας παραμένουν διαθέσιμες. Όταν διαβάζεται
μέσω ρυθμίσεων, καταγράφεται από το `config:cache` και είναι πάντα διαθέσιμο. Ο
συμβιβασμός: **η αλλαγή κλειδιού απαιτεί εκ νέου εκτέλεση του `php artisan config:cache`.** Το
κλειδί δεν καταγράφεται ποτέ στα logs. Δείτε τους [Διακομιστές με cache ρυθμίσεων](#config-cached-servers) αν το
αρχείο κλειδιού επιστρέφει 404 στην παραγωγή.
:::

### 2. Εξυπηρετήστε το αρχείο κλειδιού {#_2-serve-the-key-file}

Το IndexNow ανακτά το `https://{host}/{key}.txt` (που περιέχει μόνο το κλειδί) για επαλήθευση
ιδιοκτησίας. Με ενεργό τον διακόπτη `route` (η προεπιλογή), **το Pro το εξυπηρετεί για εσάς**:

```
GET https://example.com/0123456789abcdef0123456789abcdef.txt  →  the key, text/plain
```

Εξυπηρετείται μόνο η μία ρυθμισμένη διαδρομή κλειδιού· άλλες διαδρομές που αναλαμβάνει αυτή η route επιστρέφουν 404 και ολόκληρη η
route επιστρέφει 404 όταν το IndexNow είναι ανενεργό. Προτιμάτε να φιλοξενείτε μόνοι σας το αρχείο (ή σε
CDN); Απενεργοποιήστε το `route` και κατευθύνετε το `key_location` στη URL σας.

## Υποβολή URL {#submitting-urls}

### Αυτόματα, κατά την αποθήκευση (ειδοποίηση κατά τη δημοσίευση) {#automatically-on-save-the-push-on-publish-path}

Προσθέστε το trait σε ένα μοντέλο και ενεργοποιήστε το `auto_submit`. Κάθε αποθήκευση τοποθετεί σε ουρά
υποβολή του `getUrlForSEO()` του μοντέλου:

```php
use Rankbeam\Seo\Pro\IndexNow\Concerns\SubmitsToIndexNow;

class Post extends Model
{
    use SubmitsToIndexNow;
}
```

```dotenv
SEO_PRO_INDEXNOW_AUTO_SUBMIT=true
```

Το trait σέβεται έναν έλεγχο δημοσίευσης: υλοποιήστε το `shouldSubmitToIndexNow(): bool` για
πλήρη έλεγχο, διαφορετικά χρησιμοποιεί γνώρισμα `is_published` και, αν δεν υπάρχει ούτε αυτό,
υποβάλλει σε κάθε αποθήκευση. Η υποβολή μπαίνει πάντα **σε ουρά**, οπότε η αποθήκευση μοντέλου
δεν περιμένει ποτέ το δίκτυο.

### Χειροκίνητα {#manually}

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::indexNow()->submit('https://example.com/blog/my-post');   // one URL
SeoPro::indexNow()->submit([$urlA, $urlB]);                        // many (batched)
SeoPro::indexNow()->submitModel($post);                            // a model's URL
SeoPro::indexNow()->submitSitemap();                               // every sitemap URL
```

Το `submit()` τοποθετεί σε ουρά από προεπιλογή· περάστε `queue: false` για άμεση εκτέλεση.

### Από τη γραμμή εντολών {#from-the-command-line}

```bash
php artisan seo-pro:indexnow https://example.com/a https://example.com/b
php artisan seo-pro:indexnow --sitemap     # submit every URL in the sitemap registry
php artisan seo-pro:indexnow --sitemap --sync   # run inline instead of queuing
```

::: warning Μόνο ο ίδιος host
Κάθε URL επικυρώνεται ως `http(s)` **και** ως ανήκουσα στον ρυθμισμένο `host`·
οτιδήποτε άλλο **απορρίπτεται** (καταμετράται, δεν αποστέλλεται ποτέ) — μπορείτε να υποβάλλετε μόνο URL που
κατέχετε και το endpoint θα απέρριπτε ούτως ή άλλως ασυμφωνία host. Λίστες μεγαλύτερες από
`max_urls_per_request` (10000, το όριο του πρωτοκόλλου) χωρίζονται αυτόματα σε τμήματα.
:::

## Ρυθμίσεις {#configuration}

```php
// config/seo-pro.php → 'indexnow'
'indexnow' => [
    'enabled' => env('SEO_PRO_INDEXNOW_ENABLED', false),
    'key' => env('SEO_PRO_INDEXNOW_KEY'),   // the key itself, captured by config:cache
    'key_env' => 'SEO_PRO_INDEXNOW_KEY',   // fallback env-var NAME (real OS env var) when 'key' is empty
    'key_location' => env('SEO_PRO_INDEXNOW_KEY_LOCATION'),  // null = the served /{key}.txt
    'endpoint' => env('SEO_PRO_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
    'host' => env('SEO_PRO_INDEXNOW_HOST'),  // null = derived from app.url
    'route' => env('SEO_PRO_INDEXNOW_ROUTE', true),         // serve /{key}.txt
    'auto_submit' => env('SEO_PRO_INDEXNOW_AUTO_SUBMIT', false),
    'max_urls_per_request' => 10000,
    'timeout' => 10,
    'queue' => [
        'connection' => env('SEO_PRO_INDEXNOW_QUEUE_CONNECTION'),
        'name' => env('SEO_PRO_INDEXNOW_QUEUE'),
    ],
    'tries' => 3,
    'backoff' => 30,
],
```

## Πώς λειτουργούν οι επαναπροσπάθειες {#how-retries-work}

Το `SubmitToIndexNowJob` σε ουρά χειρίζεται ανθεκτικά όσα *πρέπει* να επαναληφθούν: ένα
`429` (περιορισμός ρυθμού), `5xx` ή timeout επαναλαμβάνεται με `backoff` έως `tries`
φορές· ένα `400`/`403`/`422` (μόνιμο σφάλμα πελάτη — λάθος κλειδί, ασυμφωνία host)
καταγράφεται και **σταματά**, αντί να σπαταλά επαναπροσπάθειες. Τα `200` και `202` (παραλήφθηκε /
εκκρεμεί έλεγχος κλειδιού) είναι και τα δύο επιτυχίες.

Στην παραγωγή, δώστε στην εργασία **ξεχωριστή ουρά**, ώστε ένα αργό endpoint να μην καθυστερεί ποτέ
εργασία που αφορά τον χρήστη:

```php
Schedule::command('seo-pro:indexnow --sitemap')->daily();
```

## Αντιμετώπιση προβλημάτων {#troubleshooting}

### Διακομιστές με cache ρυθμίσεων {#config-cached-servers}

Αν το `/{key}.txt` επιστρέφει 404 στην παραγωγή — ή οι υποβολές δεν κάνουν σιωπηλά τίποτα — ενώ
το `indexnow.enabled` είναι σαφώς `true`, η αιτία είναι σχεδόν πάντα κλειδί που υπάρχει
**μόνο στο `.env`** σε διακομιστή που εκτελεί `php artisan config:cache`. Το Laravel δεν
διαβάζει το `.env` όταν οι ρυθμίσεις βρίσκονται σε cache, οπότε το `env('SEO_PRO_INDEXNOW_KEY')` επιστρέφει
`null`, η route αρχείου κλειδιού δεν καταχωρίζεται ποτέ και κάθε υποβολή απορρίπτεται ως
«μη ρυθμισμένη».

Οι προεπιλεγμένες ρυθμίσεις επιλύουν το `indexnow.key` από το `env(...)`, οπότε μια κανονική ρύθμιση
καταγράφεται κατά τη δημιουργία της cache και λειτουργεί. Το πρόβλημα εμφανίζεται μόνο όταν έχετε
**δημοσιεύσει τις ρυθμίσεις και αφαιρέσει την προεπιλογή `env(...)`** ή ορίσει το κλειδί με
**προσαρμοσμένο όνομα `key_env` που υπάρχει μόνο στο `.env`**. Δύο τρόποι διόρθωσης:

1. **Κρατήστε το κλειδί στις ρυθμίσεις** (συνιστάται) — αφήστε το `indexnow.key` ως
   `env('SEO_PRO_INDEXNOW_KEY')` (ή ορίστε κυριολεκτική τιμή) και έπειτα εκτελέστε ξανά
   `php artisan config:cache`. Μεταγενέστερη αλλαγή κλειδιού απαιτεί νέα cache.
2. **Εισαγάγετε πραγματική μεταβλητή περιβάλλοντος** — ορίστε το `SEO_PRO_INDEXNOW_KEY` ως
   πραγματική μεταβλητή περιβάλλοντος λειτουργικού/διεργασίας (`env[...]` σε pool PHP-FPM, `Environment=` του systemd
   ή ρυθμίσεις μεταβλητών περιβάλλοντος της πλατφόρμας σας), **όχι μόνο στο `.env`**. Οι πραγματικές μεταβλητές περιβάλλοντος λειτουργικού
   διαβάζονται ακόμη κι όταν οι ρυθμίσεις βρίσκονται σε cache.

Εκτελέστε `php artisan seo:doctor` για επιβεβαίωση: αναφέρει ότι **το IndexNow είναι ενεργό αλλά δεν
επιλύεται έγκυρο κλειδί** με την ακριβή διόρθωση όταν εντοπίζει αυτή την κατάσταση, και το Pro
καταγράφει επίσης προειδοποίηση μία φορά ανά διεργασία όταν η εφαρμογή εκκινεί με cache ρυθμίσεων και
μη αναγνώσιμο κλειδί.

::: tip Google
Το Google **δεν** συμμετέχει στο IndexNow. Για το Google, χρησιμοποιήστε την ενσωμάτωση
[Search Console](/el/pro/search-console) και ενημερωμένο χάρτη ιστοτόπου.
:::

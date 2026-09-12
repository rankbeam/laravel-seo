---
description: "Λειτουργήστε το Pro σε μεγάλη κλίμακα: ξεχωριστές ουρές, scheduler, πολιτική επανάληψης και ανάκαμψης, διατήρηση και τηλεμετρία — η τοπολογία ανεξάρτητη από Filament πίσω από εγκατάσταση παραγωγής ~900 σελίδων."
---

# Ρύθμιση παραγωγής {#production-setup}

Η καθημερινή εργασία του Pro — σαρώσεις ιστοτόπου, ανιχνευτής προβληματικών συνδέσμων, προαιρετική
εκκένωση μετρητών ανακατεύθυνσης, καθαρισμός 404 — εκτελείται στην ουρά και στον scheduler του Laravel. Αυτός είναι ο μοναδικός
έγκυρος οδηγός λειτουργίας σε μεγάλη κλίμακα: ξεχωριστές ουρές, scheduler, πολιτική επανάληψης
και ανάκαμψης, διατήρηση δεδομένων και τηλεμετρία για παρακολούθηση όλων. Είναι η
τοπολογία πίσω από εγκατάσταση παραγωγής με ~20 χιλ. επισκέψεις/ημέρα και ~900 σελίδες, γραμμένη ώστε να μπορεί να
αναπαραχθεί.

Όλα εδώ είναι **ανεξάρτητα από το Filament** — η μηχανή, οι εντολές, οι ουρές και η
τηλεμετρία είναι ίδια με ή χωρίς πάνελ. Αν χρησιμοποιείτε Filament, προσθέτει
προβολές από πάνω· δεν αλλάζει τίποτα στον προγραμματισμό ή την επεξεργασία της εργασίας.

[[toc]]

## Ασφαλής σειρά διάθεσης {#safe-rollout-order}

Κάντε τα με αυτή τη σειρά — κάθε βήμα επαληθεύεται πριν από το επόμενο:

1. **Εγκατάσταση** — δημοσιεύστε ρυθμίσεις και μεταναστεύσεις και εκτελέστε τις:

   ```bash
   php artisan seo-pro:install
   ```

   Το `seo-pro:install` δημοσιεύει το `config/seo-pro.php` και τις μεταναστεύσεις Pro και έπειτα
   εκτελεί `migrate`. Οι μεταναστεύσεις Pro διατίθενται **μόνο μέσω δημοσίευσης** (το πακέτο δεν
   τις φορτώνει ποτέ αυτόματα), οπότε αυτό είναι το βήμα που μετατρέπει ένα απλό `composer require` σε
   λειτουργικό σχήμα. Είναι ιδιοδύναμο — εκτελέστε το ξανά οποτεδήποτε· προσθέστε `--force` για
   αντικατάσταση δημοσιευμένων αρχείων, `--no-migrate` για δημοσίευση χωρίς εκτέλεση μεταναστεύσεων.

2. **Καταχωρίστε στόχους σάρωσης** σε service provider (`AppServiceProvider::boot()`):

   ```php
   use Rankbeam\Seo\Pro\Facades\SeoPro;

   SeoPro::targets()->register('posts', Post::class);
   SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
   // or: SeoPro::targets()->fromSitemaps();
   ```

3. **Επαληθεύστε** τη σύνδεση πριν ενεργοποιήσετε εργασία παρασκηνίου:

   ```bash
   php artisan seo:doctor
   ```

   Διορθώστε κάθε προειδοποίηση που εκτυπώνει (καθεμία περιλαμβάνει την ακριβή εντολή/γραμμή ρυθμίσεων). Στο CI,
   προσθέστε `--json` και βασιστείτε στα σταθερά ID ελέγχων.

4. **Ρυθμίστε ουρές και scheduler** (παρακάτω), εγκαταστήστε worker ουράς και εγγραφή cron
   για το `schedule:run`.

5. **Ενεργοποιήστε τις προαιρετικές λειτουργίες τελευταίες** — ο ανιχνευτής προβληματικών συνδέσμων, η βοήθεια AI και το Search
   Console είναι όλα ανενεργά από προεπιλογή. Ο ανιχνευτής χρειάζεται εκτελεσμένες μεταναστεύσεις για τους πίνακές του (το βήμα 1
   τις δημοσίευσε ήδη) και ξεχωριστό worker (παρακάτω).

**Αναβάθμιση στο Pro 2.41.0:** παύστε τους workers σάρωσης, δημοσιεύστε τις μεταναστεύσεις με `php artisan vendor:publish --tag=seo-pro-migrations --force`, εκτελέστε `php artisan migrate`, έπειτα επανεκκινήστε τους workers και εκτελέστε `php artisan seo:doctor`. Απαιτούνται ο νέος πίνακας `seo_scan_target_completions` και η στήλη `seo_scan_runs.target_tracking`. Οι εγγραφές επιβεβαίωσης ανά εκτέλεση/στόχο εμποδίζουν τα διπλά τελικά αποτελέσματα να διογκώνουν τους μετρητές· υπερισχύει το πρώτο αποδεκτό αποτέλεσμα. Παλιές εκτελέσεις σε ουρά χωρίς επεξεργασμένους στόχους συνεχίζουν. Μερικώς επεξεργασμένες εκτελέσεις πριν από την αναβάθμιση διατηρούν το ιστορικό, αλλά κλείνουν με οδηγία για νέα σάρωση στην επόμενη παράδοσή τους. Επαναλάβετε τους στόχους που εξάντλησαν τις προσπάθειες σε νέα εκτέλεση. Για επαναφορά, σταματήστε τους workers και επαναφέρετε τον κώδικα πριν αναιρέσετε τη μετανάστευση· κρατήστε αντίγραφο ασφαλείας βάσης πριν από την αναβάθμιση αν χρειάζεται να αναιρέσετε και μεταγενέστερες σαρώσεις.

## Ξεχωριστές ουρές ανά φόρτο εργασίας {#dedicated-queues-per-workload}

Μια μεγάλη σάρωση ή ανίχνευση δεν πρέπει ποτέ να προηγείται στην ουρά εργασιών που αφορούν χρήστες (email,
ειδοποιήσεις). Δώστε σε κάθε φόρτο SEO δική του ουρά και δικό του worker.

Η διαδικασία σάρωσης και ο ανιχνευτής προβληματικών συνδέσμων διαβάζουν από παραμετροποιήσιμη ουρά:

| Φόρτος εργασίας | Ρύθμιση | Μεταβλητή περιβάλλοντος | Προεπιλεγμένη ουρά |
|---|---|---|---|
| Εργασίες σάρωσης σελίδας | `seo-pro.scan.queue` | `SEO_PRO_SCAN_QUEUE` | η προεπιλεγμένη ουρά |
| Εργασίες ανίχνευσης προβληματικών συνδέσμων | `seo-pro.broken_links.queue.name` (+ `.connection`) | `SEO_PRO_BROKEN_LINKS_QUEUE` (+ `_CONNECTION`) | `seo-broken-links` |

### Παράδειγμα Redis (η τοπολογία παραγωγής) {#redis-example-the-production-topology}

`.env`:

```dotenv
QUEUE_CONNECTION=redis

# Dedicated queues so SEO work never starves user-facing jobs.
SEO_PRO_SCAN_QUEUE=seo
SEO_PRO_BROKEN_LINKS_QUEUE=broken_links
SEO_PRO_BROKEN_LINKS_QUEUE_CONNECTION=redis
```

Εκτελέστε έναν worker ανά ουρά (καθένας είναι ξεχωριστή διεργασία / πρόγραμμα Supervisor):

```bash
# User-facing jobs — highest priority, most workers.
php artisan queue:work redis --queue=default --tries=3

# On-page scans — moderate; a scan target job is short.
php artisan queue:work redis --queue=seo --tries=3 --timeout=360

# Broken-link crawl — one worker is plenty; jobs are long and self-redispatch.
php artisan queue:work redis --queue=broken_links --tries=1 --timeout=240
```

Το `--timeout` του worker ανίχνευσης πρέπει να υπερβαίνει το
`seo-pro.broken_links.batch.hard_time_budget_seconds` (προεπιλογή 180) συν το HTTP
timeout, ώστε μια παρτίδα να μην τερματίζεται ποτέ στη μέση της καταγραφής κατάστασης· η εργασία ορίζει το δικό της
`$timeout` σε αυτό το άθροισμα, οπότε ευθυγραμμίστε τη σημαία worker με αυτό. Χρησιμοποιήστε `--tries=1` για την
ανίχνευση: μια εργασία που τερματίζεται ανακτάται από την επόμενη συνέχεια (ή το
`seo-pro:broken-links-recover`), οπότε δεν χρειάζονται επαναπροσπάθειες σε επίπεδο ουράς.

Το `seo:doctor` αναφέρει την ουρά κάθε φόρτου και προειδοποιεί όταν κάποια επιλύεται σε `sync`
(που θα εκτελούσε την εργασία άμεσα και θα μπλόκαρε τη διεργασία).

## Scheduler {#scheduler}

Τα Laravel 11, 12 και 13 προγραμματίζουν στο **`routes/console.php`** (η
μέθοδος `schedule()` του `app/Console/Kernel.php` υπάρχει μόνο σε εφαρμογές αναβαθμισμένες από
Laravel 10 — βάλτε εκεί τις ίδιες εγγραφές αν η δική σας την έχει ακόμη). Προσθέστε μία
εγγραφή cron συστήματος ώστε ο scheduler να ενεργοποιείται κάθε λεπτό:

```bash
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Έπειτα καταχωρίστε κάθε επαναλαμβανόμενη εντολή με τη συνιστώμενη συχνότητά της:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

// --- Core ---------------------------------------------------------------
// Regenerate the XML sitemap (needs spatie/laravel-sitemap + registered sources).
Schedule::command('seo:sitemap')->dailyAt('01:30');

// --- Scan pipeline ------------------------------------------------------
// Scan cadence: weekly suits most sites; go daily when content changes fast.
// Queued — pair with the `seo` queue worker above.
Schedule::command('seo-pro:scan')->weekly();
// Fail runs abandoned by a dead worker so they never hang the pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
// Delete finished runs (and their issues) past the retention window.
Schedule::command('seo-pro:scan-prune')->daily();

// --- Redirects & 404s ---------------------------------------------------
// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();
// Keep the 404 log within its retention window and row cap.
Schedule::command('seo-pro:404-prune')->daily();
// Re-fetch open 404 paths; mark any that return 200 again as recovered.
Schedule::command('seo-pro:404-recheck')->daily();

// --- Broken-link crawler (only when enabled) ----------------------------
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Συνιστώμενες συχνότητες συνοπτικά:

| Εντολή | Συχνότητα | Αιτία |
|---|---|---|
| `seo:sitemap` | καθημερινά | Ενημέρωση χάρτη από το τρέχον περιεχόμενο |
| `seo-pro:scan` | εβδομαδιαία (καθημερινά αν το περιεχόμενο αλλάζει γρήγορα) | Νέος έλεγχος κάθε στόχου |
| `seo-pro:scan-recover` | ανά ώρα | Ανάκτηση εκτελέσεων που χάθηκαν λόγω τερματισμένου worker |
| `seo-pro:scan-prune` | καθημερινά | Εφαρμογή χρονικού παραθύρου διατήρησης εκτελέσεων σάρωσης |
| `seo-pro:redirects-flush-hits` | κάθε 5 λεπτά, μόνο όταν `redirects.hits.flush_immediately=false` | Μεταφορά συγκεντρωμένων μετρητών επισκέψεων από την cache στη βάση |
| `seo-pro:404-prune` | καθημερινά | Περιορισμός αρχείου 404 βάσει διατήρησης και ορίου εγγραφών |
| `seo-pro:404-recheck` | καθημερινά | Νέα ανάκτηση ανοιχτών διαδρομών 404· σήμανση όσων διορθώθηκαν στην πηγή (πλέον 200) ως αποκατεστημένων |
| `seo-pro:broken-links-scan` | εβδομαδιαία | Νέα ανίχνευση προβληματικών συνδέσμων (η επιβεβαίωση γίνεται μεταξύ σαρώσεων) |
| `seo-pro:broken-links-recover` | ανά ώρα | Ανάκτηση ανιχνεύσεων που χάθηκαν λόγω τερματισμένου worker |
| `seo-pro:broken-links-prune` | καθημερινά | Εφαρμογή χρονικών παραθύρων διατήρησης ανιχνευτή |

Τα `seo-pro:scan` και `seo-pro:broken-links-scan` μόνο **τοποθετούν σε ουρά** την εργασία· ο worker
την εκτελεί. Οι εντολές recover/prune εκτελούνται άμεσα και έχουν μικρό κόστος.

::: tip Επιβεβαίωση προβληματικών συνδέσμων μεταξύ σαρώσεων
Ένας σύνδεσμος επισημαίνεται ως προβληματικός μόνο αφού `seo-pro.broken_links.mark_broken_after_failures`
**διαδοχικές σαρώσεις** αποτύχουν να τον προσεγγίσουν (ο μετρητής μηδενίζεται σε κάθε επιτυχία). Γι' αυτό
η ανίχνευση προγραμματίζεται και δεν εκτελείται μόνο μία φορά: μία παροδική διακοπή δεν επισημαίνει ποτέ
σύνδεσμο. Με την προεπιλογή 3, οι εβδομαδιαίες σαρώσεις επιβεβαιώνουν περίπου δύο εβδομάδες μετά την πρώτη
παρατήρηση αποτυχίας ή έως περίπου τρεις εβδομάδες μετά τη βλάβη· αυξήστε τη συχνότητα (ή
μειώστε το κατώφλι) αν θέλετε ταχύτερη επιβεβαίωση.
:::

## Ρύθμιση παρτίδων (ανιχνευτής προβληματικών συνδέσμων) {#batch-tuning-broken-link-crawler}

Η ανίχνευση εκτελείται σε πολλές οριοθετημένες εργασίες που αποστέλλουν μόνες τους τη συνέχεια. Οι προεπιλογές είναι
πεπερασμένες· προσαρμόστε τις στην ικανότητα του ιστοτόπου σας και των hosts που ελέγχετε. Ρυθμίστε τις στο
`seo-pro.broken_links`:

| Κλειδί | Προεπιλογή | Τι οριοθετεί |
|---|---|---|
| `max_pages_per_run` | `2000` | Σελίδες που ανακτώνται σε ολόκληρη εκτέλεση. `null` = ρητή επιλογή χωρίς όριο (ποτέ η προεπιλογή) |
| `max_links_per_page` | `200` | Σύνδεσμοι που ελέγχονται ανά σελίδα |
| `max_total_links` | `null` | Προαιρετικό καθολικό όριο ελέγχων συνδέσμων σε όλη την εκτέλεση |
| `batch.max_pages_per_job` | `50` | Σελίδες ανά εργασία ουράς |
| `batch.max_links_per_job` | `1500` | Έλεγχοι συνδέσμων ανά εργασία ουράς |
| `batch.hard_time_budget_seconds` | `180` | Μετά από αυτό, η εργασία δεν ξεκινά **καμία νέα ανάκτηση** και αποστέλλει συνέχεια |
| `batch.dispatch_delay_seconds` | `1` | Καθυστέρηση μεταξύ εργασιών συνέχειας |
| `http.timeout` / `http.connect_timeout` | `10` / `5` | Όρια ανά αίτημα |
| `http.max_response_bytes` | κληρονομεί το `seo-pro.http.max_response_bytes` | Όριο κατά τη ροή για σώματα αποκρίσεων σελίδας και ελέγχου στόχου |
| `seed.max_response_bytes` | κληρονομεί το όριο ανιχνευτή/κοινό HTTP | Bytes ακατέργαστου XML χάρτη / `.gz` που ανακτώνται κατά την αρχική τροφοδότηση |
| `seed.max_inflated_bytes` | κληρονομεί το όριο αρχικής τροφοδότησης/ανιχνευτή/κοινό | Αποσυμπιεσμένα bytes που γίνονται δεκτά από χάρτη `.gz` |
| `http.per_host_delay_ms` | `0` | Καθυστέρηση ευγένειας μεταξύ ελέγχων (αυξήστε για `internal_and_external`) |

Κρατήστε το `batch.hard_time_budget_seconds` αρκετά κάτω από το
`--timeout` του worker ανίχνευσης. Ένα αίτημα σε εξέλιξη δεν μπορεί να ακυρωθεί στη μέση — οριοθετείται από
το `http.timeout`, γι' αυτό το timeout worker = χρονικό όριο εργασίας + HTTP timeout + περιθώριο.

Για ανίχνευση `internal_and_external`, διευρύνετε το `seo-pro.http.scope` (ή
το `seo-pro.http.allowed_hosts`), ώστε το SsrfGuard να επιτρέπει τους εξερχόμενους ελέγχους, και
αυξήστε το `http.per_host_delay_ms`, ώστε ένας host τρίτου να μη δέχεται ποτέ υπερβολικά συχνά αιτήματα.
Το `seo:doctor` προειδοποιεί όταν το εύρος ανίχνευσης είναι εξωτερικό αλλά το εύρος προστασίας θα
απέκλειε κάθε έλεγχο.

## Horizon / Supervisor {#horizon-supervisor}

### Supervisor {#supervisor}

Ένα πρόγραμμα ανά ουρά. Παράδειγμα `/etc/supervisor/conf.d/app-workers.conf`:

```ini
[program:app-queue-default]
command=php /path/to/app/artisan queue:work redis --queue=default --tries=3 --max-time=3600
numprocs=4
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data

[program:app-queue-seo]
command=php /path/to/app/artisan queue:work redis --queue=seo --tries=3 --timeout=360 --max-time=3600
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=400
user=www-data

[program:app-queue-broken-links]
command=php /path/to/app/artisan queue:work redis --queue=broken_links --tries=1 --timeout=240 --max-time=3600
numprocs=1
autostart=true
autorestart=true
stopwaitsecs=260
user=www-data
```

Το `stopwaitsecs` πρέπει να υπερβαίνει το `--timeout` του worker, ώστε μια ομαλή επανεκκίνηση να μην
τερματίζει ποτέ εργασία στη μέση της παρτίδας.

### Horizon {#horizon}

Αν χρησιμοποιείτε Horizon, ορίστε supervisor ανά φόρτο στο `config/horizon.php` και
αφήστε το να διαχειρίζεται τις διεργασίες αντί για το Supervisor:

```php
'environments' => [
    'production' => [
        'default' => ['connection' => 'redis', 'queue' => ['default'], 'maxProcesses' => 6],
        'seo'     => ['connection' => 'redis', 'queue' => ['seo'], 'maxProcesses' => 2, 'timeout' => 360],
        'crawler' => ['connection' => 'redis', 'queue' => ['broken_links'], 'maxProcesses' => 1, 'timeout' => 240, 'tries' => 1],
    ],
],
```

## Χειρισμός επαναπροσπαθειών και αποτυχιών {#retry-failure-handling}

Η εργασία στόχου σάρωσης έχει δική της πολιτική επανάληψης από τις ρυθμίσεις — **δεν**
βασίζεται στο `--tries` του worker:

| Κλειδί | Προεπιλογή | Σημασία |
|---|---|---|
| `seo-pro.scan.tries` | `3` | Προσπάθειες ανά εργασία στόχου |
| `seo-pro.scan.backoff` | `30` | Δευτερόλεπτα μεταξύ προσπαθειών |
| `seo-pro.scan.timeout` | `300` | Timeout εργασίας ανά στόχο (το κλείδωμα επικάλυψης λήγει στο timeout + 60) |

Μια εργασία στόχου που εξαντλεί τις επαναπροσπάθειες καταγράφει τον στόχο ως **αποτυχημένο** και η
εκτέλεση ολοκληρώνεται παρ' όλα αυτά (`partial` ή `failed`) — οι διαχειρισμένες αποτυχίες στόχων δεν αφήνουν εκτέλεση σε κατάσταση `running`. Ένας worker που τερματίστηκε πριν
καταγράψει την κατάσταση εξακολουθεί να χρειάζεται τη διαδικασία ανάκαμψης παρακάτω. Οι αποτυχίες καταλήγουν στον κανονικό πίνακα `failed_jobs`· διαχειριστείτε τις
με τον συνηθισμένο τρόπο:

```bash
php artisan queue:failed
php artisan queue:retry all
```

Προγραμματίστε το `queue:prune-failed` μαζί με τις εγγραφές SEO για να διατηρείται οριοθετημένος ο πίνακας:

```php
Schedule::command('queue:prune-failed --hours=168')->daily();
```

Η ανίχνευση προβληματικών συνδέσμων χρησιμοποιεί `--tries=1`: μια τερματισμένη εργασία ανακτάται από τη δική της επόμενη
συνέχεια (το σήμα ζωής της μίσθωσης παλιώνει) ή από το `seo-pro:broken-links-recover`,
οπότε οι επαναπροσπάθειες ουράς θα επαναλάμβαναν απλώς την εργασία.

## Ανάκαμψη {#recovery}

Ένας worker που τερματίζεται στη μέση μιας εργασίας είναι η μόνη περίπτωση που η καταγραφή προόδου δεν διορθώνεται μόνη της, οπότε
δύο διαδικασίες την κλείνουν — προγραμματίστε και τις δύο **ανά ώρα**:

- Το `seo-pro:scan-recover` σημειώνει ως αποτυχημένες τις εκτελέσεις σάρωσης σελίδας χωρίς πρόοδο για
  `seo-pro.scan.recovery.stuck_scan_timeout_hours` (προεπιλογή 2).
- Το `seo-pro:broken-links-recover` ανακτά εκτελέσεις ανίχνευσης των οποίων το σήμα ζωής μίσθωσης
  έχει παλιώσει (`seo-pro.broken_links.recovery.stuck_scan_timeout_hours`, προεπιλογή 2),
  σημειώνοντάς τις ως αποτυχημένες και ελευθερώνοντας τη θέση μίας ενεργής εκτέλεσης ανά εύρος.

Το `seo:doctor` το παρουσιάζει ως **στοιχεία πρόσφατου σήματος ζωής**: όταν χρησιμοποιούνται σαρώσεις,
αναφέρει τυχόν στάσιμες εκτελέσεις και σας κατευθύνει στην εντολή ανάκαμψης. Δεν μπορεί
να αποδείξει ότι εκτελείται πράγματι το cron σας — καμία εντολή δεν μπορεί — αναφέρει ό,τι δείχνει το ιστορικό
εκτελέσεων.

## Διατήρηση δεδομένων {#retention}

Κρατήστε οριοθετημένους τους πίνακες. Προεπιλογές (όλες στο `seo-pro.*`· το `null` απενεργοποιεί τον αντίστοιχο καθαρισμό):

| Δεδομένα | Ρύθμιση | Προεπιλογή | Εντολή |
|---|---|---|---|
| Εκτελέσεις σάρωσης (+ ζητήματα) | `scan.retention.scan_runs_days` | `90` | `seo-pro:scan-prune` |
| Αρχείο 404 | `monitor_404.retention_days` (+ `max_rows` `10000`) | `90` | `seo-pro:404-prune` |
| Εκτελέσεις ανίχνευσης | `broken_links.retention.scan_runs_days` | `90` | `seo-pro:broken-links-prune` |
| Επιλυμένα ευρήματα | `broken_links.retention.resolved_findings_days` | `30` | `seo-pro:broken-links-prune` |

## Λειτουργική τηλεμετρία {#operational-telemetry}

Κάθε ολοκληρωμένη εκτέλεση — σάρωση σελίδας **και** ανίχνευση προβληματικών συνδέσμων — εκπέμπει μία δομημένη
γραμμή ολοκλήρωσης μέσω του συστήματος logging, ώστε να έχετε ιστορικό μετρήσεων χωρίς πάνελ.
Τα δεδομένα περιλαμβάνουν μόνο πλήθη και χρόνους (χωρίς URL, σώματα, κεφαλίδες ή δεδομένα
επισκεπτών):

| Μετρική | Σάρωση | Ανίχνευση |
|---|:--:|:--:|
| `pages_fetched` | — | ✓ |
| `links_checked` | — | ✓ |
| `links_broken` | — | ✓ |
| `blocked_urls` (στόχοι που απορρίφθηκαν από SSRF) | — | ✓ |
| `transient_failures` (δικτυακές αποτυχίες, ελέγχονται ξανά στην επόμενη σάρωση) | — | ✓ |
| `total_targets` / `completed_targets` / `failed_targets` | ✓ | — |
| `issues_found` | ✓ | — |
| `duration_seconds` | ✓ | ✓ |
| `queue_lag_seconds` (ουρά → πρώτη παρτίδα) | ✓ | ✓ |

Ρυθμίστε την στο `seo-pro.telemetry`:

```php
'telemetry' => [
    'enabled' => env('SEO_PRO_TELEMETRY_ENABLED', true),
    'channel' => env('SEO_PRO_TELEMETRY_CHANNEL'), // null = default log channel
    'level'   => env('SEO_PRO_TELEMETRY_LEVEL', 'info'),
],
```

Κατευθύνετε το `channel` σε ξεχωριστό κανάλι log για να στέλνετε τις γραμμές σε πραγματικό προορισμό
(Loki / Datadog / CloudWatch) χωρίς να τις αναμειγνύετε με τα logs εφαρμογής:

```php
// config/logging.php
'channels' => [
    'seo' => ['driver' => 'single', 'path' => storage_path('logs/seo.log'), 'level' => 'info'],
],
```

```dotenv
SEO_PRO_TELEMETRY_CHANNEL=seo
```

Για πιο πλούσιο χειρισμό, εγγραφείτε απευθείας στα συμβάντα — καθένα εκθέτει τα ίδια δεδομένα
`metrics()`:

```php
use Rankbeam\Seo\Pro\Events\SeoScanCompleted;
use Rankbeam\Seo\Pro\BrokenLinks\Events\BrokenLinkScanCompleted;

Event::listen(SeoScanCompleted::class, function (SeoScanCompleted $event) {
    Metrics::gauge('seo.scan.issues', $event->metrics()['issues_found']);
});

Event::listen(BrokenLinkScanCompleted::class, function (BrokenLinkScanCompleted $event) {
    Metrics::gauge('seo.crawl.broken', $event->metrics()['links_broken']);
});
```

Η τηλεμετρία λειτουργεί κατά το δυνατόν: ένα κακορυθμισμένο κανάλι δεν μπορεί ποτέ να προκαλέσει αποτυχία σάρωσης.

## Ανάπτυξη ανεξάρτητη από το Filament {#filament-independent-deployment}

Τίποτα σε αυτή τη σελίδα δεν χρειάζεται πάνελ. Η μηχανή, κάθε εντολή, οι ουρές, ο
scheduler, η ανάκαμψη, η διατήρηση και η τηλεμετρία είναι ίδια χωρίς πάνελ. Το πάνελ Filament
(`SeoProPlugin`) προσθέτει μόνο **προβολές** — ζωντανή πρόοδο σάρωσης, πίνακα ζητημάτων,
CRUD ανακατευθύνσεων, παρακολούθηση 404, πίνακα ελέγχου προβληματικών συνδέσμων. Αναπτύξτε τη μηχανή και
λειτουργήστε την από CLI και scheduler· προσθέστε το πάνελ αργότερα (ή ποτέ) χωρίς
μεταναστεύσεις ή επανάληψη εργασιών. Δείτε τη [Χρήση χωρίς περιβάλλον διαχείρισης](/el/pro/headless) για την πλήρη
αναφορά εντολών.

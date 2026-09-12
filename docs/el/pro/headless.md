---
description: "Κάθε δυνατότητα Pro — σαρώσεις, ανακατευθύνσεις, καταγραφή 404 — λειτουργεί headless χωρίς Filament. Αναφορά εντολών για πλήρη διαχείριση του Pro μέσω Artisan."
---

# Χρήση headless {#headless-usage}

Κάθε δυνατότητα Pro — σαρώσεις, ανακατευθύνσεις, καταγραφή 404 — λειτουργεί headless:
ανήκει στη μηχανή και δεν χρειάζεται Filament. Ο πίνακας είναι μόνο περιβάλλον διαχείρισης·
αυτές οι εντολές είναι το αντίστοιχό του χωρίς γραφικό περιβάλλον.

## Αναφορά εντολών {#command-reference}

### Ρύθμιση και έλεγχος υγείας {#setup-health-check}

| Εντολή | Τι κάνει |
|---|---|
| `seo-pro:install` | Δημοσιεύει το `config/seo-pro.php` και τις μεταναστεύσεις Pro, τις εκτελεί και έπειτα εκτυπώνει τα επόμενα βήματα (`--no-migrate`, `--force`) |
| `seo:doctor` | Εφάπαξ έλεγχος υγείας — URL εφαρμογής, πίνακες Core + Pro, στόχοι σάρωσης, χάρτης ιστοτόπου, ουρές ανά εργασία, προαιρετικές δυνατότητες και λειτουργική υγεία, με ακριβή διόρθωση για κάθε προειδοποίηση (`--json` για παρακολούθηση) |

Το `seo-pro:install` είναι η τεκμηριωμένη διαδρομή εγκατάστασης: οι μεταναστεύσεις Pro διατίθενται μόνο για δημοσίευση
(το πακέτο δεν τις φορτώνει ποτέ αυτόματα), επομένως ο installer μετατρέπει ένα απλό
`composer require` σε λειτουργικό σχήμα βάσης. Είναι idempotent — εκτελέστε τον ξανά όποτε χρειάζεται.

Το `seo:doctor` δεν κάνει δικτυακές κλήσεις και δεν εκτυπώνει ποτέ μυστικές τιμές (ο έλεγχος AI
αναφέρει μόνο αν η ρυθμισμένη μεταβλητή κλειδιού είναι *ορισμένη*). Ελέγχει
τις ρυθμίσεις και το πρόσφατο ιστορικό εκτελέσεων — δεν μπορεί να αποδείξει ότι ένα εξωτερικό cron ή ένας worker
εκτελείται πραγματικά. Επιστρέφει μη μηδενικό κωδικό μόνο σε κρίσιμη αποτυχία — όταν λείπει απαιτούμενος
πίνακας — οπότε ένα τοπικό περιβάλλον ανάπτυξης με προειδοποιήσεις εξακολουθεί να τερματίζει επιτυχώς. Το `--json` δίνει σε κάθε
έλεγχο ένα σταθερό `id` για αναφορά. Εκτελέστε το αμέσως μετά την [εγκατάσταση](/el/pro/installation) και
στο CI.

### Σάρωση {#scanning}

| Εντολή | Τι κάνει |
|---|---|
| `seo-pro:scan` | Βάζει σε ουρά πλήρη σάρωση κάθε καταχωρισμένου στόχου (`--sync` για άμεση εκτέλεση· **πύλη CI** `--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=json\|md\|html` — απαιτούν `--sync`) |
| `seo-pro:scan-status` | Σύνοψη τελευταίας εκτέλεσης και ανοιχτά ζητήματα, πρώτα τα σοβαρότερα (`--limit=20`, `--severity=critical\|warning\|notice`) |
| `seo-pro:scan-recover` | Επισημαίνει ως αποτυχημένες εκτελέσεις που εγκαταλείφθηκαν από νεκρό worker ουράς |
| `seo-pro:scan-prune` | Διαγράφει ολοκληρωμένες εκτελέσεις (και τα ζητήματά τους) πέρα από το διάστημα διατήρησης |

### Ανιχνευτής προβληματικών συνδέσμων {#broken-link-crawler}

Απενεργοποιημένος από προεπιλογή — ενεργοποιήστε το `seo-pro.broken_links.enabled` και εκτελέστε τις μεταναστεύσεις για τους δύο πίνακές του
(το `seo-pro:install` τις δημοσιεύει). Η ανίχνευση εκτελείται σε εργασίες ουράς με καθορισμένα όρια· εκτελέστε
ξεχωριστό worker για την ουρά της. Δείτε τη [Ρύθμιση παραγωγής](/el/pro/production) για προσαρμογές.

| Εντολή | Τι κάνει |
|---|---|
| `seo-pro:broken-links-scan` | Βάζει σε ουρά ανίχνευση με όρια και δυνατότητα συνέχισης (`--scope=internal_only\|internal_and_external`, `--url=*` για επιπλέον αρχικές URL) |
| `seo-pro:broken-links-status` | Σύνοψη τελευταίας ανίχνευσης, ανοιχτά ευρήματα και [επιθεωρήσεις ανά τύπο](/el/pro/broken-links#typed-link-inspections) αυτής της εκτέλεσης· **πύλη CI** (`--fail-on-error`, `--fail-on-warning`, `--report=`, `--format=`) |
| `seo-pro:broken-links-cancel` | Ακυρώνει ανίχνευση που εκτελείται ή βρίσκεται σε ουρά (`{run?}` — προεπιλογή η πιο πρόσφατη ενεργή) |
| `seo-pro:broken-links-recover` | Επισημαίνει ως αποτυχημένες ανιχνεύσεις που εγκαταλείφθηκαν από νεκρό worker (ληγμένη μίσθωση) |
| `seo-pro:broken-links-prune` | Εφαρμόζει την πολιτική διατήρησης του ανιχνευτή (παλιές εκτελέσεις και επιλυμένα ευρήματα) |

### Ανακατευθύνσεις και 404 {#redirects-404s}

| Εντολή | Τι κάνει |
|---|---|
| `seo-pro:redirect-create {source} {target}` | Δημιουργεί κανόνα ανακατεύθυνσης (`--code=301`, `--regex`, `--no-preserve-query`, `--note=`) |
| `seo-pro:404-list` | Καταγεγραμμένα 404, πρώτα όσα έχουν τις περισσότερες επισκέψεις (`--status=new\|ignored\|redirected\|all`, `--limit=20`) |
| `seo-pro:redirects-flush-hits` | Γράφει στη βάση τους μετρητές επισκέψεων ανακατεύθυνσης που συγκεντρώθηκαν στην cache, όταν `redirects.hits.flush_immediately=false` |
| `seo-pro:404-prune` | Διαγράφει παρωχημένες εγγραφές 404 και επιβάλλει το όριο πλήθους εγγραφών |

### Κατάλογος ελέγχου σελίδας {#on-page-checklist}

| Εντολή | Τι κάνει |
|---|---|
| `seo-pro:checklist {model} {id}` | Κατάλογος pass/warn/fail με συνεκτίμηση λέξεων-κλειδιών για ένα μοντέλο (`--json`, `--strict`, `--locale=`) — δείτε τον [Κατάλογο ελέγχου σελίδας](/el/pro/on-page-checklist) |

Ο ίδιος κατάλογος είναι διαθέσιμος ως `SeoPro::checklistFor($model)`. Είναι ο κύκλος
συντακτικού ελέγχου (θέση λέξεων-κλειδιών, μήκος, εικόνες, εσωτερικοί σύνδεσμοι), **όχι** η
[βαθμολογία SEO](/el/pro/scoring).

### Search Console (μόνο ανάγνωση) {#search-console-read-only}

| Εντολή | Τι κάνει |
|---|---|
| `seo-pro:search-console` | Σελίδες με ανοιχτά ζητήματα **και** επισκεψιμότητα αναζήτησης, πρώτα όσες χρειάζονται παρέμβαση και έχουν τις περισσότερες εμφανίσεις (`--view=attention`, η προεπιλογή) |
| `seo-pro:search-console --view=pages` | Κορυφαίες σελίδες βάσει εμφανίσεων/κλικ/CTR/θέσης |
| `seo-pro:search-console --view=queries` | Κορυφαία ερωτήματα (`--days=`, `--limit=`, `--json`) |

Οι ίδιες μετρήσεις είναι διαθέσιμες ως `SeoPro::searchConsole()` — δείτε το
[Search Console](/el/pro/search-console). Απενεργοποιημένο από προεπιλογή· αυστηρά μόνο για ανάγνωση.

### Βοήθεια AI {#ai-assist}

| Εντολή | Τι κάνει |
|---|---|
| `seo-pro:ai-suggest {model} {id}` | Προτάσεις τίτλου/περιγραφής ως JSON (`--field=title\|description\|all`) — δείτε τη [Βοήθεια AI](/el/pro/ai-assist) |
| `seo-pro:ai-suggest --issue={id}` | Εξήγηση διόρθωσης ενός ζητήματος σάρωσης σε απλή γλώσσα, ως JSON |

### Επίλυση ενός 404 σε ένα βήμα {#resolving-a-404-in-one-step}

Το `--from-404={path}` είναι η headless έκδοση της ενέργειας *Δημιουργία ανακατεύθυνσης*
με ένα κλικ από την παρακολούθηση 404: δημιουργεί τον κανόνα **και** επισημαίνει την αντίστοιχη καταγεγραμμένη
εγγραφή ως ανακατευθυνόμενη, συνδέοντάς τη με τον νέο κανόνα:

```bash
php artisan seo-pro:404-list

#  ID | Path        | Hits | Status | ...
#  1  | /old-pricing | 41  | new

php artisan seo-pro:redirect-create /old-pricing /pricing --from-404=/old-pricing

# Redirect #1 created: /old-pricing → /pricing (301).
# 404 log #1 (/old-pricing) marked redirected.
```

Η εντολή εκτελεί τους ίδιους ελέγχους εγκυρότητας με τη φόρμα Filament — μη έγκυρα μοτίβα
regex, υπερμεγέθεις τιμές και εξωτερικοί στόχοι εκτός λίστας επιτρεπόμενων
απορρίπτονται πριν γραφτεί οτιδήποτε.

## Συνιστώμενος προγραμματισμός {#recommended-schedule}

```php
use Illuminate\Support\Facades\Schedule;

// Only needed when seo-pro.redirects.hits.flush_immediately=false.
Schedule::command('seo-pro:redirects-flush-hits')->everyFiveMinutes();

// Keep the 404 log within retention and the row cap.
Schedule::command('seo-pro:404-prune')->daily();

// Scan cadence: weekly suits most sites; go daily when content
// changes fast. Scans are queued jobs - pair with a queue worker.
Schedule::command('seo-pro:scan')->weekly();

// Housekeeping for the scan pipeline.
Schedule::command('seo-pro:scan-recover')->hourly();
Schedule::command('seo-pro:scan-prune')->daily();

// Broken-link crawler (only when enabled). Run a dedicated worker for
// its queue so a long crawl never starves user-facing jobs.
Schedule::command('seo-pro:broken-links-scan')->weekly();
Schedule::command('seo-pro:broken-links-recover')->hourly();
Schedule::command('seo-pro:broken-links-prune')->daily();
```

Κάθε επαναλαμβανόμενη εντολή παραπάνω έχει συνιστώμενη συχνότητα στον
οδηγό [Ρύθμισης παραγωγής](/el/pro/production), μαζί με την τοπολογία ουρών, τις ρυθμίσεις
workers, την πολιτική επανάληψης/ανάκαμψης, τη διατήρηση και τη δομημένη **τηλεμετρία**
που παράγει κάθε ολοκληρωμένη εκτέλεση (ανακτημένες σελίδες, ελεγμένοι σύνδεσμοι, αποκλεισμένες URL, διάρκεια,
καθυστέρηση ουράς).

## Τι χρειάζεται το περιβάλλον Filament; {#what-needs-the-filament-ui}

Καμία λειτουργία. Ολόκληρη η μηχανή — ροή σάρωσης, παρακολούθηση ζητημάτων,
αντιστοίχιση ανακατευθύνσεων, καταγραφή 404, εκκαθάριση, ανάκαμψη — είναι ίδια με
ή χωρίς Filament. Ο πίνακας προσθέτει τις *προβολές*: πίνακα ελέγχου με ζωντανή πρόοδο
σάρωσης και στατιστικά σοβαρότητας, περιήγηση στα ζητήματα με φίλτρα και διαλόγους
ανά σελίδα, κουμπιά «Παράβλεψη»/«Άνοιγμα ξανά», φόρμες CRUD ανακατευθύνσεων και τον πίνακα 404 με
την ενέργεια ενός κλικ. Η παράβλεψη ή το εκ νέου άνοιγμα ζητήματος δεν έχει προς το παρόν ειδική
εντολή — κάντε την από τον πίνακα ή μέσω του μοντέλου `SEOScanIssue`
(`markIgnored()` / `reopen()`) στο tinker ή στον δικό σας κώδικα.

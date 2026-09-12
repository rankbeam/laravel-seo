---
description: "Ακολουθήστε μια πραγματική σάρωση Rankbeam Pro, εξετάστε μια περιγραφή που λείπει, αποθηκεύστε τη διόρθωση στο Filament, σαρώστε ξανά και κατεβάστε την παραγόμενη ενδεικτική αναφορά PDF."
---

# Από τη σάρωση στην επαληθευμένη διόρθωση {#from-a-scan-to-a-verified-fix}

Μια σάρωση εντόπισε περιγραφή που έλειπε από ένα δοκιμαστικό άρθρο. Προσθέσαμε την περιγραφή στο Filament, σαρώσαμε ξανά και δημιουργήσαμε μια αναφορά που δείχνει τη διόρθωση.

Αυτές οι λήψεις προέρχονται από ένα λειτουργικό τοπικό demo Merchant στις 9 Σεπτεμβρίου 2026. Το περιεχόμενο είναι ενδεικτικά δεδομένα από seeder· οι δύο σαρώσεις και η αναφορά δημιουργήθηκαν για αυτό το παράδειγμα. Δεν προσυμπληρώθηκε ιστορική τάση. Η εφαρμογή χρησιμοποιεί Laravel 12 και Filament 4, με το Core, τον δωρεάν επεξεργαστή και τη μηχανή Pro του Rankbeam.

**[Λήψη της παραγόμενης αναφοράς (PDF στα αγγλικά, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf)**

## Σάρωση των καταχωρισμένων σελίδων {#scan-the-registered-pages}

Μετά την [εγκατάσταση του Pro](/el/pro/installation) και την καταχώριση στόχων σάρωσης, εκτελέστε:

```bash
php artisan seo-pro:scan --sync
```

Το demo καταχωρίζει 18 εγγραφές περιεχομένου και τρεις διαδρομές. Αυτή η πρώτη σάρωση ολοκλήρωσε και τους 21 στόχους χωρίς αποτυχίες και εντόπισε 20 προβλήματα: έξι προειδοποιήσεις και 14 ειδοποιήσεις.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="Η πρώτη ολοκληρωμένη σάρωση: 21 στόχοι, 20 προβλήματα, έξι προειδοποιήσεις και 14 ειδοποιήσεις." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Τα στιγμιότυπα οθόνης έχουν ληφθεί σε ανάλυση 2×. Ανοίξτε ένα για να το εξετάσετε σε πλήρες μέγεθος. Οι λήψεις δείχνουν την αγγλική διεπαφή του demo.*

## Εξέταση ενός προβλήματος {#inspect-one-issue}

Στο **SEO Dashboard**, ανοίξτε το **Page issues** δίπλα στη σχετική εγγραφή. Για το “Behind the Scenes: Our Product Photography”, το εύρημα προσδιορίζει το `description` που λείπει, τη διεύθυνση URL της σελίδας και τη σάρωση που το εντόπισε.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Το παράθυρο Page issues προσδιορίζει το Post 5, τη διεύθυνση URL του και το πεδίο περιγραφής που λείπει." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Αποθήκευση της περιγραφής {#save-the-description}

Ανοίξτε το άρθρο στο **Posts**, συμπληρώστε το **SEO description** και αποθηκεύστε. Ο [δωρεάν επεξεργαστής Filament](/el/guide/filament) εμφανίζει το κείμενο στην προεπισκόπηση αναζήτησης και προσδιορίζει την πηγή του ως **Manual**. Σε αυτό το παράδειγμα, η περιγραφή έχει 142 χαρακτήρες· ο τίτλος εξακολουθεί να προέρχεται από το άρθρο.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="Η αποθηκευμένη περιγραφή SEO και ο μετρητής της με 142 χαρακτήρες." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="Η ζωντανή προεπισκόπηση χρησιμοποιεί την καταχωρισμένη περιγραφή, με την ένδειξη Manual." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Η αποθήκευση ενός πεδίου και η επαλήθευση της διόρθωσης είναι χωριστά βήματα. Η βαθμολογία σάρωσης ενημερώνεται μετά την επόμενη σάρωση. Χωρίς Filament, αποθηκεύστε την ίδια τιμή μέσω της μεθόδου `saveSEO()` του μοντέλου σας.

## Νέα σάρωση και έλεγχος των αλλαγών {#rescan-and-check-what-changed}

Εκτελέστε ξανά την ίδια εντολή:

```bash
php artisan seo-pro:scan --sync
```

Ο πίνακας ελέγχου χαρακτηρίζει πλέον αυτό ακριβώς το πρόβλημα ως **Fixed**. Τα άλλα 19 προβλήματα παραμένουν ανοιχτά.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Καταγεγραμμένη σύγκριση σαρώσεων: μηδέν νέα προβλήματα, μηδέν επανεμφανίσεις, ένα διορθωμένο και 19 ακόμη ανοιχτά." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Έλεγχος | Πριν | Μετά |
|---|---|---|
| Ολοκληρωμένοι στόχοι | 21 | 21 |
| Ανοιχτά προβλήματα | 20 | 19 |
| Προειδοποιήσεις | 6 | 5 |
| Ειδοποιήσεις | 14 | 14 |
| Μέση τεχνική βαθμολογία SEO | 92 | 93 |

Η [βαθμολογία](/el/pro/scoring) αντικατοπτρίζει τους τεχνικούς ελέγχους του Rankbeam. Δεν μετρά επισκεψιμότητα, θέση στην αναζήτηση ή συμπερίληψη σε απαντήσεις AI. Η επιτυχία στον έλεγχο περιγραφής επίσης δεν εγγυάται ότι μια μηχανή αναζήτησης θα εμφανίσει αυτή την περιγραφή.

## Δημιουργία της αναφοράς {#generate-the-report}

Για αυτή την επίδειξη, δημιουργήσαμε μια αναφορά βάσης **πριν** από την επεξεργασία του άρθρου και έπειτα μια δεύτερη αναφορά μετά τη νέα σάρωση:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

Το δεύτερο PDF δείχνει **ένα διορθωμένο**, **μηδέν νέα** και **19 ανοιχτά** προβλήματα. Η τάση του περιέχει μόνο τις δύο παραπάνω σαρώσεις. Το Search Console και η καταγραφή AI bots ήταν απενεργοποιημένα, οπότε αυτές οι ενότητες αναφέρουν ότι δεν υπάρχουν διαθέσιμα δεδομένα.

[![Η πρώτη σελίδα της παραγόμενης ενδεικτικής αναφοράς: βαθμολογία 93, ένα διορθωμένο πρόβλημα και 19 ανοιχτά προβλήματα.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

Η πρώτη αναφορά καθορίζει τη βάση σύγκρισης. Αν δημιουργήσετε μόνο μία αναφορά μετά τη διόρθωση μιας σελίδας, δεν μπορεί να δείξει αλλαγή σε σχέση με προηγούμενη αναφορά. Χρησιμοποιήστε το `--no-store` για προεπισκόπηση που δεν πρέπει να μεταβάλει αυτή τη βάση.

Το δείγμα χρησιμοποιεί τον renderer Browsershot. Δείτε τις [αναφορές με δική σας επωνυμία](/el/pro/reports) για απαιτήσεις renderer, επωνυμία και προγραμματισμένη παράδοση.

## Εκτέλεση στη δική σας εφαρμογή {#run-it-on-your-own-app}

Ξεκινήστε από την [Εγκατάσταση του Pro](/el/pro/installation) και έπειτα σαρώστε μια σελίδα της οποίας μπορείτε να ελέγξετε την έξοδο. Το Pro λειτουργεί και [χωρίς Filament](/el/pro/headless). Για να δοκιμάσετε πρώτα τον δωρεάν renderer μεταδεδομένων, χρησιμοποιήστε το [demo Docker](/el/guide/demo).

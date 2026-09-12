---
description: "Εκτελέστε php artisan seo:audit για πίνακα pass/warn/fail ανά σελίδα με τα τρέχοντα προβλήματα SEO — μέσα στην ίδια διεργασία, χωρίς ουρά, άδεια ή δίκτυο. Δωρεάν, στον πυρήνα."
---

# Δωρεάν έλεγχος SEO (`seo:audit`) {#free-seo-audit-seo-audit}

Το `php artisan seo:audit` απαντά δωρεάν σε μία ερώτηση με μία εντολή: **τι
πάει λάθος με το SEO μου αυτή τη στιγμή;** Διατρέχει τα μοντέλα `HasSEO` μέσα στην ίδια διεργασία —
**χωρίς ουρά, χωρίς άδεια, χωρίς δίκτυο** — και εκτυπώνει έναν πίνακα **pass / warn /
fail** ανά σελίδα, με σύνοψη.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## Τι ελέγχει {#what-it-checks}

Ο έλεγχος εκτελεί μόνο την κλάση εκτέλεσης **metadata** — τους ελέγχους που μπορούν
να επιλυθούν μόνο από το μοντέλο και τον [resolver](/el/concepts/resolver-precedence),
χωρίς ανάκτηση της σελίδας:

| Έλεγχος | Κωδικοί |
|---|---|
| Ύπαρξη τίτλου / περιγραφής (με συνεκτίμηση εναλλακτικών τιμών) | `missing_title`, `missing_description` |
| Ύπαρξη εικόνας OG (με συνεκτίμηση εναλλακτικών τιμών) | `missing_og_image` |
| Μήκος τίτλου / περιγραφής | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Διπλότυπος τίτλος / περιγραφή στον ιστότοπο | `duplicate_title`, `duplicate_description` |
| Συγκρούσεις robots και ύποπτο noindex | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Μορφή canonical / άλλο domain / κοινή URL / μη ασφαλές πρωτόκολλο | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Ετοιμότητα για μηχανές απαντήσεων (AEO) — δομημένα δεδομένα άρθρου | `aeo_missing_author`, `aeo_article_missing_date` |
| Ορισμός λέξης-κλειδιού εστίασης (με ρητή ενεργοποίηση) | `missing_focus_keyword` |
| Εναλλακτικές εκδόσεις hreflang (μητρώο πυρήνα, όταν η σελίδα διαθέτει τέτοιες) | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

Οι περισσότεροι κωδικοί εμφανίζονται και στη σάρωση Pro, αλλά τα μητρώα είναι ξεχωριστά.
Συγκεκριμένα, ο πυρήνας χρησιμοποιεί το `hreflang_missing_self`, ενώ το Pro χρησιμοποιεί
το `hreflang_missing_self_reference`· το `hreflang_duplicate_code` είναι ενημέρωση στον πυρήνα
και προειδοποίηση στο Pro. Μην υποθέτετε ίδια κάλυψη ή σοβαρότητα επειδή ένα
όνομα είναι κοινό. Το `blank_explicit_override` ανήκει στο μητρώο του πυρήνα. Το μήκος χρησιμοποιεί το [όριο ανά σύστημα γραφής](/el/guide/multilingual#title-and-description-budgets-per-script) του επεξεργαστή
— 60/160 χαρακτήρες για λατινικό κείμενο, ~30/80 για CJK, με καταμέτρηση γραφημάτων και
μέτρηση της **επιλυμένης** τιμής, μαζί με το επίθημα — ώστε ο έλεγχος να μην
αντιφάσκει ποτέ με τους μετρητές χαρακτήρων του [επεξεργαστή Filament](/el/guide/filament).
Οι έλεγχοι hreflang εκτελούνται στη λίστα μετά την εφαρμογή των πολιτικών `seo.hreflang` (η ίδια
λίστα που παράγουν οι ετικέτες και ο χάρτης ιστοτόπου)· η αμοιβαιότητα απαιτεί ανίχνευση και παραμένει στο Pro.

Οι έλεγχοι **ετοιμότητας για μηχανές απαντήσεων (AEO)** ενεργοποιούνται μόνο όταν μια σελίδα δηλώνει
JSON-LD τύπου άρθρου (`Article`, `BlogPosting`, `NewsArticle`, …) από το οποίο
λείπει ένα σήμα που καθιστά το άρθρο κατανοητό ως δομημένα δεδομένα — μια οντότητα `author`
(ρητή πατρότητα / προέλευση), ή `datePublished` / `dateModified`
(ρητό χρονολόγιο). Μια σελίδα χωρίς άρθρο δεν επισημαίνεται ποτέ, οπότε ο έλεγχος
παραμένει σιωπηρός όπου το AEO δεν εφαρμόζεται.
Είναι συμβουλευτικοί έλεγχοι (επίπεδο notice) και εξαιρούνται από τη βαθμολογία 0–100 του Pro.

## Τι *δεν* ελέγχει — το όριο δυνατοτήτων {#what-it-does-not-check-—-the-capability-boundary}

Ένας δωρεάν έλεγχος μέσα στην ίδια διεργασία δεν μπορεί να ισοδυναμεί με την πλήρη σάρωση Pro, και η εντολή το δηλώνει
σε κάθε εκτέλεση. **Δεν** εκτελεί:

- **Ελέγχους αποδιδόμενου HTML** — `missing_h1`, `multiple_h1`, `missing_image_alt`,
  `thin_content`, `mixed_content`. Αυτοί χρειάζονται το HTML που εξυπηρετεί η σελίδα.
- **Δικτυακούς ελέγχους της ενεργής κανονικής URL** — `canonical_target_broken` / `_redirect` /
  `_noindex`. Αυτοί χρειάζονται εξερχόμενη ανάκτηση με δικλίδες προστασίας.
- **Την αριθμητική βαθμολογία 0–100.** Η βαθμολογία είναι δυνατότητα Pro, αποθηκευμένη στην
  εγγραφή αποτελέσματος σάρωσης με κανόνες βαθμολόγησης ανά έκδοση — δείτε τη [Βαθμολογία SEO](/el/pro/scoring).

Αυτά παρέχονται στη **σάρωση Pro** — δείτε το πλήρες [μητρώο ζητημάτων](/el/pro/scan-issues).

## Επιλογή του αντικειμένου ελέγχου {#choosing-what-to-audit}

Από προεπιλογή η εντολή ελέγχει τα μοντέλα που παρατίθενται στο `seo.audit.models`,
με εναλλακτική το `seo.sitemap.models`:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Ή δώστε τα μοντέλα ρητά:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Επιλογές {#options}

| Επιλογή | Αποτέλεσμα |
|---|---|
| `--model=` | Κλάση μοντέλου `HasSEO` προς έλεγχο (επαναλήψιμη). Υπερισχύει των ρυθμίσεων. |
| `--locale=` | Επίλυση δεδομένων SEO σε αυτό το locale (προεπιλογή: το locale της εφαρμογής). |
| `--limit=` | Μέγιστες εγγραφές προς έλεγχο ανά μοντέλο (`0` = όλες). |
| `--issues-only` | Εμφάνιση μόνο σελίδων με τουλάχιστον ένα ζήτημα. |
| `--strict` | Έξοδος με μη μηδενικό κωδικό όταν βρεθεί οποιοδήποτε ζήτημα — για CI. |
| `--json` | Παραγωγή μηχαναγνώσιμου JSON (σελίδες, σύνοψη, κάλυψη) αντί για πίνακα. |

### Πύλη ελέγχου CI {#ci-gate}

Το `--strict` μετατρέπει τον έλεγχο σε έλεγχο build:

```bash
php artisan seo:audit --strict
```

Επιστρέφει `1` αν οποιαδήποτε σελίδα έχει προειδοποίηση ή αποτυχία, και `0` όταν όλες οι ελεγμένες σελίδες περνούν.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Λέξεις-κλειδιά εστίασης {#focus-keywords}

Η ενημέρωση `missing_focus_keyword` είναι **απενεργοποιημένη από προεπιλογή**. Ενεργοποιείται μόνο αφού
επιλέξετε τη ροή εργασίας λέξεων-κλειδιών εστίασης:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Η σάρωση Pro διαβάζει την **ίδια** σημαία, ώστε ο έλεγχος, η σάρωση και η υπενθύμιση του
επεξεργαστή Pro να συμφωνούν πάντα. Ορίστε τις λέξεις-κλειδιά μιας σελίδας με το
[πεδίο λέξης-κλειδιού εστίασης του Filament](/el/guide/filament) ή το
`$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Όταν μια τιμή δεν είναι η αναμενόμενη: `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

Το `seo:audit` σάς λέει *τι είναι λάθος*· το [`seo:explain`](/el/guide/explain) εξηγεί
*γιατί ένα πεδίο επιλύθηκε έτσι* — ποιο επίπεδο (ρυθμίσεις / προεπιλογή / υπολογιζόμενο
/ ρητό) όρισε κάθε τιμή, τι αντικατέστησε και ποια μετεπεξεργασία (επίθημα
τίτλου, αφαίρεση query από το canonical, προστασία ευρετηρίασης) την άλλαξε στη συνέχεια. Χρησιμοποιήστε το όταν ένα
εύρημα ελέγχου ή μια αποδιδόμενη ετικέτα σάς εκπλήσσει:

```bash
php artisan seo:explain "App\Models\Post" 42
```


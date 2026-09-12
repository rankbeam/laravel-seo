---
description: "Εγκαταστήστε το Rankbeam, προσθέστε το trait HasSEO σε υπάρχον μοντέλο, αποθηκεύστε πεδία SEO και επαληθεύστε τις ετικέτες που αποδίδονται στο Blade."
---

# Γρήγορη εκκίνηση {#quickstart}

Ξεκινήστε με μια υπάρχουσα εφαρμογή Laravel 11, 12 ή 13 και μια λειτουργική βάση δεδομένων.
Laravel 11: PHP 8.2–8.4· Laravel 12: PHP 8.2–8.5· Laravel 13: PHP 8.3–8.5. Ο πυρήνας διατίθεται δωρεάν με άδεια MIT·
δεν απαιτείται λογαριασμός ή άδεια Pro.

## Εγκατάσταση {#install}

Εκτελέστε τις παρακάτω εντολές από τον κατάλογο της εφαρμογής σας:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Ο service provider εντοπίζεται αυτόματα. Η μετανάστευση δημιουργεί τους πίνακες SEO·
δεν δημιουργεί τα μοντέλα περιεχομένου της εφαρμογής σας.

## Πριν από το παράδειγμα {#before-the-example}

Τα παρακάτω βήματα προϋποθέτουν ότι έχετε ήδη ένα μοντέλο `Post`, μια αποθηκευμένη ανάρτηση και μια
διαδρομή `posts.show`, της οποίας η προβολή Blade λαμβάνει την ανάρτηση ως `$post`. Προσαρμόστε αυτά
τα ονόματα στην εφαρμογή σας. Αυτός ο οδηγός προσθέτει SEO στη συγκεκριμένη σελίδα· δεν κατασκευάζει ιστολόγιο.

Ορίστε το `APP_URL` στη δημόσια διεύθυνση βάσης του ιστοτόπου σας μέσα στο `.env`. Για άλλα συστήματα απόδοσης,
χρησιμοποιήστε τον [οδηγό Inertia και JSON](/el/guide/inertia-json) ή τον
[οδηγό Livewire](/el/guide/livewire).

## 1. Προσθέστε το trait σε ένα μοντέλο {#_1-add-the-trait-to-a-model}

```php
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

Το `getUrlForSEO()` δηλώνει στον resolver την κανονική διεύθυνση URL του μοντέλου —
χρησιμοποιείται για τις κανονικές URL, το `og:url` και τις εγγραφές του χάρτη ιστοτόπου.

## 2. Αποδώστε το head {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

Το `@seo($post)` παράγει ετικέτες τίτλου, μεταπεριγραφής, canonical, robots, Open Graph
και Twitter Card, καθώς και οποιοδήποτε JSON-LD έχει συνδεθεί με τα δεδομένα που επέλεξε ο resolver. Χωρίς
αποθηκευμένες ρητές τιμές, όλα προέρχονται από υπολογιζόμενες εναλλακτικές τιμές (τα
χαρακτηριστικά της ίδιας της ανάρτησης) και τις προεπιλογές που έχετε ρυθμίσει — δείτε την
[προτεραιότητα του resolver](/el/concepts/resolver-precedence).

## 3. Ορίστε ρητές τιμές {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Οι ρητές τιμές υπερισχύουν κάθε επιπέδου εναλλακτικών τιμών. Δώστε ένα locale για μεταφρασμένα
μεταδεδομένα: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Συμπληρώνετε μοντέλα με seeders;
Το προεπιλεγμένο `DatabaseSeeder` του Laravel χρησιμοποιεί το trait `WithoutModelEvents`, το οποίο
απενεργοποιεί σιωπηρά τον μηχανισμό αυτόματης δημιουργίας του `HasSEO`. Αφαιρέστε το trait ή καλέστε
ρητά το `saveSEO()` μέσα στους seeders.
:::

## 4. Επαληθεύστε το αποτέλεσμα {#_4-verify-the-result}

Ανοίξτε τη δημόσια σελίδα της ανάρτησης και επιλέξτε **Προβολή πηγαίου κώδικα σελίδας**. Στο `<head>`, ελέγξτε
ότι ο τίτλος περιέχει το `Custom SEO Title`, η περιγραφή είναι
`Custom meta description` και η κανονική διεύθυνση URL δείχνει στη δημόσια URL της ανάρτησης.
Το επίθημα τίτλου που έχετε ρυθμίσει μπορεί να ακολουθεί τον τίτλο.

Αποδώστε το `@seo($post)` μία φορά ανά σελίδα. Αν το layout παράγει ήδη ετικέτες τίτλου ή μεταδεδομένων,
αντικαταστήστε τις για να αποφύγετε τα διπλότυπα. Αν μια τιμή δεν είναι η αναμενόμενη, χρησιμοποιήστε τον
[οδηγό επίλυσης τιμών](/el/guide/explain) για να εξετάσετε από πού προήλθε.

## 5. Προσθέστε έναν χάρτη ιστοτόπου (προαιρετικά) {#_5-add-a-sitemap-optional}

```php
// e.g. in AppServiceProvider::boot()
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

SEO::sitemaps()->register('posts', Post::class);
```

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

Το `/sitemap.xml` εξυπηρετεί πλέον το παραγόμενο ευρετήριο. Όλες οι επιλογές περιγράφονται στον
[οδηγό μητρώου χαρτών ιστοτόπου](/el/guide/sitemaps).

## Επόμενα βήματα {#where-to-go-next}

- [Προτεραιότητα του resolver](/el/concepts/resolver-precedence) — πώς επιλέγονται οι τιμές
- [Οδηγός Blade](/el/guide/blade) — και οι επτά οδηγίες
- [Inertia και JSON](/el/guide/inertia-json) — headless απόδοση
- [Γράφος schema](/el/guide/schema) — διασυνδεδεμένο JSON-LD
- [Πεδία Filament](/el/guide/filament) — περιβάλλον διαχείρισης με δύο γραμμές

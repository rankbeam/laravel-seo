---
description: "Εξυπηρετήστε μια καθαρή αναπαράσταση Markdown της σελίδας σε ανιχνευτές AI μέσω διαπραγμάτευσης περιεχομένου, ενώ οι κανονικοί επισκέπτες λαμβάνουν το HTML ανέπαφο. Δωρεάν, στον πυρήνα, απενεργοποιημένο από προεπιλογή."
---

# Markdown για bots {#markdown-for-bots}

Μια σελίδα HTML εφαρμογής περιβάλλει το περιεχόμενό της με πλοήγηση, scripts και markup
του layout. Ορισμένοι ανιχνευτές AI και μηχανές απαντήσεων δέχονται μια καθαρότερη αναπαράσταση όταν
προσφέρεται, οπότε αυτή η δυνατότητα μπορεί να εξυπηρετεί μια **αναπαράσταση Markdown** της σελίδας
σε πελάτες που τη ζητούν μέσω διαπραγμάτευσης περιεχομένου — ενώ κάθε κανονικός επισκέπτης
συνεχίζει να λαμβάνει το HTML σας ανέπαφο. Είναι μια επιλογή συμβατότητας που ενεργοποιείτε ρητά, όχι
υπόσχεση για τον τρόπο που κάποιος συγκεκριμένος πελάτης αναλύει ή χρησιμοποιεί το αποτέλεσμα.

Συνδυάζεται με τον [έλεγχο ανιχνευτών AI](/el/guide/ai-crawlers): εκείνος ορίζει την πολιτική
πρόσβασης· αυτή η δυνατότητα αποφασίζει *ποιο* περιεχόμενο θα εξυπηρετηθεί κατά το αίτημα.

Πρόκειται για δωρεάν δυνατότητα του πυρήνα και είναι **απενεργοποιημένη από προεπιλογή**.

## Πώς λειτουργεί {#how-it-works}

Ενεργοποιήστε την και καταχωρίζεται ένα middleware διαπραγμάτευσης περιεχομένου. Αφού παραχθεί η
κανονική σας απόκριση, την αντικαθιστά με Markdown **μόνο όταν ισχύουν και τα δύο**:

1. **Το αίτημα ζητά Markdown** — ρητή κεφαλίδα `Accept: text/markdown`,
   παράμετρος query `?format=md` ή (με ρητή ενεργοποίηση) αναγνωρισμένος ανιχνευτής AI βάσει user-agent.
2. **Βρίσκεται πηγή Markdown για τη διαδρομή.**

Διαφορετικά η απόκριση περνά αμετάβλητη — ο browser δεν επηρεάζεται ποτέ
και αντικαθίσταται μόνο μια επιτυχής απόκριση **HTML** (ποτέ JSON,
ανακατεύθυνση ή λήψη αρχείου).

```php
# config/seo.php
'markdown_for_bots' => [ 'enabled' => true ],
```

```
GET /blog/my-post            → text/html   (your normal page)
GET /blog/my-post?format=md  → text/markdown
GET /blog/my-post  (Accept: text/markdown) → text/markdown
```

## Από πού προέρχεται το Markdown {#where-the-markdown-comes-from}

Οι παρακάτω πηγές μπορούν να παρέχουν Markdown για τη διαδρομή που αντιστοιχίστηκε. Το middleware
δοκιμάζει **πρώτα μια καταχωρισμένη πηγή διαδρομής** και έπειτα τα μοντέλα που έχουν συνδεθεί στη διαδρομή. Για κάθε μοντέλο,
μια ρητή μέθοδος `toSeoMarkdown()` υπερισχύει της ενσωματωμένης εναλλακτικής·
ένα null ή κενό αποτέλεσμα αυτής της μεθόδου απενεργοποιεί την εναλλακτική για το συγκεκριμένο μοντέλο.

### 1. Το Markdown του ίδιου του μοντέλου {#_1-a-model-s-own-markdown}

Όταν καμία καταχωρισμένη πηγή διαδρομής δεν επιστρέφει περιεχόμενο, ένα μοντέλο συνδεδεμένο στη διαδρομή που
υλοποιεί το `toSeoMarkdown()` ελέγχει την έξοδό του (υλοποιήστε το
συμβόλαιο `ProvidesSeoMarkdown` ή απλώς προσθέστε τη μέθοδο):

```php
use Rankbeam\Seo\Contracts\ProvidesSeoMarkdown;

class Post extends Model implements ProvidesSeoMarkdown
{
    use HasSEO;

    public function toSeoMarkdown(): ?string
    {
        return $this->body_markdown; // your already-clean markdown
    }
}
```

### 2. Καταχωρισμένη πηγή διαδρομής {#_2-a-registered-route-source}

Για διαδρομές χωρίς μοντέλο ή για να παρακάμψετε την έξοδο του μοντέλου, καταχωρίστε μια πηγή με το όνομα της διαδρομής:

```php
use Rankbeam\Seo\Facades\SEO;

SEO::markdown()->register('pages.about', "# About us\n\nWe build things.");
SEO::markdown()->register('posts.show', fn ($request) => $request->route('post')->body_markdown);
```

### 3. Η ενσωματωμένη εναλλακτική {#_3-the-built-fallback}

Όταν ένα μοντέλο `HasSEO` συνδεδεμένο στη διαδρομή δεν έχει `toSeoMarkdown()`, το middleware δημιουργεί
ένα βασικό έγγραφο από τον επιλυμένο **τίτλο** (ως H1), την **περιγραφή** και
το **`getContentForSEO()`** του μοντέλου:

```markdown
# Post title

The meta description.

…the model's content…
```

::: warning Το περιεχόμενο εξυπηρετείται ως έχει
Η εναλλακτική παράγει το `getContentForSEO()` αυτούσιο. Αν το περιεχόμενό σας είναι HTML
αντί για Markdown, υλοποιήστε το `toSeoMarkdown()` για να ελέγχετε τη μετατροπή. Απενεργοποιήστε
εντελώς την εναλλακτική με το `seo.markdown_for_bots.build_from_content = false`.
:::

## Ρυθμίσεις {#configuration}

```php
// config/seo.php
'markdown_for_bots' => [
    'enabled'            => false,    // off by default; the middleware isn't registered until true
    'auto_register_middleware' => true,
    'serve_to_known_bots' => false,   // also serve to known AI crawlers by user-agent
    'query_param'        => 'format', // the ?format=md trigger
    'query_value'        => 'md',
    'build_from_content' => true,     // build from getContentForSEO() when no toSeoMarkdown()
],
```

Αφήστε το `serve_to_known_bots` απενεργοποιημένο για διαπραγμάτευση μόνο μέσω της ρητής ένδειξης `Accept` /
`?format`· ενεργοποιήστε το για να δίνετε Markdown και στα GPTBot, ClaudeBot,
PerplexityBot και τα υπόλοιπα (αναγνωρίζονται μέσω του
[καταλόγου ανιχνευτών AI](/el/guide/ai-crawlers)), ακόμη και όταν δεν το ζητούν.

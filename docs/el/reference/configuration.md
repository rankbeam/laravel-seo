---
description: Κάθε επιλογή στο config/seo.php, ομαδοποιημένη ανά επίπεδο του resolver, με την προεπιλεγμένη τιμή της.
---

# Ρυθμίσεις {#configuration}

Δημοσιεύστε το αρχείο ρυθμίσεων:

```bash
php artisan vendor:publish --tag=seo-config
```

Όλα τα παρακάτω βρίσκονται στο `config/seo.php`. Οι τιμές που εμφανίζονται είναι οι προεπιλεγμένες.

## Προεπιλογές για όλο τον ιστότοπο (επίπεδο 1) {#site-wide-defaults-layer-1}

```php
'site_name'                       => env('APP_NAME', 'My Site'),
'title_suffix'                    => ' | ' . env('APP_NAME', 'My Site'),
'title_suffix_skip_when_contains' => [],   // brand tokens, e.g. ['Acme']
'default_og_image'                => env('SEO_DEFAULT_OG_IMAGE', '/images/og-default.jpg'),
'default_robots'                  => env('SEO_DEFAULT_ROBOTS', 'index,follow'),
'default_twitter_card'            => env('SEO_DEFAULT_TWITTER_CARD', 'summary_large_image'),
'twitter_site'                    => env('SEO_TWITTER_SITE'),     // @username, without @
'twitter_creator'                 => env('SEO_TWITTER_CREATOR'),
'favicon'                         => '/favicon.ico',
```

Το `title_suffix` προστίθεται στο τέλος των τελικών τίτλων, εκτός αν ο τίτλος τελειώνει ήδη με αυτό.

Το `title_suffix_skip_when_contains` είναι μια λίστα παράλειψης του επιθήματος με βάση την επωνυμία. Όταν ο τελικός τίτλος περιέχει ήδη κάποιο από αυτά τα στοιχεία **ως ολόκληρη λέξη** (χωρίς διάκριση πεζών/κεφαλαίων, με αναγνώριση ορίων λέξεων — άρα το `Acmestic` δεν αντιστοιχεί στο `Acme`), το επίθημα παραλείπεται για να μην επαναλαμβάνεται η επωνυμία στον τίτλο. Η προεπιλογή `[]` διατηρεί την προηγούμενη συμπεριφορά.

## Πολιτική απόδοσης robots {#robots-rendering-policy}

```php
'robots' => [
    'emit_default' => env('SEO_EMIT_DEFAULT_ROBOTS', false),
],
```

Το αποδιδόμενο `<head>` παραλείπει την ετικέτα `<meta name="robots">` όταν η τελική οδηγία ισούται με το `default_robots` (παραπάνω) — ένα περιττό `index,follow` προσθέτει θόρυβο, και η απουσία του είναι ακριβώς αυτό που ο ανιχνευτής ερμηνεύει ως index,follow. Μια οδηγία που **διαφέρει** (`noindex`, `nofollow`, `max-snippet:-1`, …) αποδίδεται πάντα αυτούσια. Ορίστε το `emit_default` σε `true` για να αποδίδεται πάντα η ετικέτα (επαναφέρει τη συμπεριφορά πριν από την 3.1). Η επιμέρους οδηγία `@seoRobots` δεν επηρεάζεται — απαιτεί ρητή ενεργοποίηση και αποδίδεται πάντα. Δείτε τη [Σύμβαση απόδοσης](/el/contributing/rendering-contract) για το υποστηριζόμενο λεξιλόγιο οδηγιών και τη σειρά προτεραιότητας.

## Προστασία ευρετηρίασης (δίχτυ ασφαλείας εκτός παραγωγής) {#indexing-guard-non-production-safety-net}

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production'],
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Όταν είναι ενεργή και η εφαρμογή εκτελείται σε περιβάλλον που **δεν** περιλαμβάνεται στο `allowed_environments`, η προστασία επιβάλλει `noindex,nofollow` σε κάθε σελίδα (πάνω από όλη την αλυσίδα προτεραιότητας, οπότε υπερισχύει ακόμη και μιας αποθηκευμένης τιμής ανά σελίδα), στέλνει την αντίστοιχη κεφαλίδα `X-Robots-Tag`, παράγει ένα `robots.txt` που απαγορεύει τα πάντα και κάνει το `seo:audit` να εμφανίζει μια προειδοποίηση. Στα επιτρεπόμενα περιβάλλοντα (από προεπιλογή, `production`) παραμένει ανενεργή.

Διατίθεται **απενεργοποιημένη** (η έξοδος παραμένει πανομοιότυπη σε επίπεδο byte μέχρι να την ενεργοποιήσετε). Ενεργοποιήστε τη με `SEO_INDEXING_GUARD=true` και απενεργοποιήστε τη με `SEO_INDEXING_GUARD=false` — μία γραμμή σε κάθε περίπτωση. Αντικαταστήστε τη λίστα επιτρεπόμενων περιβαλλόντων μέσω του `SEO_INDEXING_GUARD_ALLOWED` (διαχωρισμένα με κόμμα· λειτουργούν χαρακτήρες μπαλαντέρ `Str::is()` όπως το `prod*`· μια κενή λίστα εφαρμόζει προστασία παντού).

Το `send_header` (ενεργό από προεπιλογή μέσα στην προστασία) στέλνει επίσης `X-Robots-Tag: noindex,nofollow` σε κάθε απόκριση που περνά από την εφαρμογή, ώστε να προστατεύονται και PDF, feeds και εικόνες — που δεν περιέχουν `<meta robots>`. Το middleware καταχωρίζεται μόνο όταν είναι ενεργή η προστασία. Συνιστάται ιδιαίτερα — δείτε τον πλήρη [οδηγό προστασίας ευρετηρίασης](/el/guide/indexing-guard).

## Κανονικές διευθύνσεις URL {#canonical-urls}

```php
'canonical' => [
    'query_whitelist' => [],   // e.g. ['page']
],
```

Από μια κανονική διεύθυνση URL που ο resolver **παράγει** (από τη διεύθυνση του αιτήματος ή το `getUrlForSEO()` ενός μοντέλου) αφαιρείται από προεπιλογή το query string — οι παράμετροι παρακολούθησης, φιλτραρίσματος και ταξινόμησης δημιουργούν διαφορετικούς κανονικούς προορισμούς για το ίδιο περιεχόμενο της ίδιας σελίδας. Καταχωρίστε κλειδιά στο `query_whitelist` και αυτά **διατηρούνται** στις παραγόμενες κανονικές διευθύνσεις URL, με αυτή τη σειρά, ενώ όλες οι άλλες παράμετροι εξακολουθούν να αφαιρούνται. Συνήθης περίπτωση είναι το `page` για σελιδοποιημένα αρχεία (το `/blog?page=2` πράγματι δεν είναι το `/blog`).

Μια κανονική διεύθυνση URL που έχει **οριστεί ρητά** (από διαχειριστή ή από επίπεδο υψηλότερης προτεραιότητας) αποδίδεται πάντα αυτούσια, μαζί με όλο το query string — η λίστα επιτρεπόμενων κλειδιών αφορά μόνο την παραγόμενη εφεδρική τιμή. Η προεπιλογή `[]` διατηρεί τη συμπεριφορά αφαίρεσης όλων των παραμέτρων.

## Διακόπτες λειτουργιών {#feature-toggles}

```php
'features' => [
    'auto_create_meta' => env('SEO_AUTO_CREATE_META', true),
    'sitemap'          => env('SEO_SITEMAP_ENABLED', true),
    'schema'           => env('SEO_SCHEMA_ENABLED', true),
    'multilingual'     => env('SEO_MULTILINGUAL_ENABLED', false),
],
```

Το `auto_create_meta` δημιουργεί μια κενή εγγραφή `seo_meta` όταν δημιουργείται ένα μοντέλο `HasSEO` (σημείωση: οι seeders που χρησιμοποιούν `WithoutModelEvents` το παρακάμπτουν).

## Λέξεις-κλειδιά εστίασης {#focus-keywords}

```php
'keywords' => [
    'enabled' => env('SEO_KEYWORDS_ENABLED', false),
],
```

Ο **διακόπτης της ροής εργασίας** για τις λέξεις-κλειδιά εστίασης. Όσο είναι `false` (η προεπιλογή), μια σελίδα χωρίς λέξη-κλειδί εστίασης δεν επισημαίνεται πουθενά — ούτε το [`seo:audit`](/el/guide/audit) ούτε η σάρωση Pro αναφέρουν πρόβλημα. Έτσι, μια εφαρμογή που δεν υιοθετεί ποτέ λέξεις-κλειδιά εστίασης δεν δέχεται ειδοποιήσεις για μια λειτουργία που δεν χρησιμοποιεί. Ενεργοποιήστε το όταν αρχίσετε να ορίζετε λέξεις-κλειδιά εστίασης (π.χ. με το [πεδίο λέξης-κλειδιού εστίασης του Filament](/el/guide/filament)). Ο δωρεάν έλεγχος, η σάρωση Pro και ο επεξεργαστής Pro αρχίζουν τότε να αναφέρουν ειδοποίηση `missing_focus_keyword` για σελίδες που εξακολουθούν να μην έχουν κάποια — διαβάζουν την ίδια ρύθμιση, οπότε συμφωνούν πάντα.

## Δωρεάν έλεγχος (`seo:audit`) {#free-audit-seo-audit}

```php
'audit' => [
    // \App\Models\Post::class, \App\Models\Page::class
    'models' => [],
],
```

Τα μοντέλα που ελέγχει η δωρεάν εντολή [`seo:audit`](/el/guide/audit) όταν δεν δίνεται επιλογή `--model`. Κάθε μοντέλο πρέπει να χρησιμοποιεί το trait `HasSEO`. Όταν η λίστα είναι κενή, η εντολή χρησιμοποιεί τα μοντέλα που είναι καταχωρισμένα στο `sitemap.models`.

## Υπολογιζόμενες εφεδρικές τιμές (επίπεδο 5) {#computed-fallbacks-layer-5}

```php
'computed' => [
    // Ordered attribute candidates for the description fallback.
    // Empty = built-in chain: excerpt, summary, description, intro,
    // lead, teaser, content, body, text, article.
    'description_fields' => [],

    // Truncation length — word boundary, no ellipsis.
    'description_max_length' => 160,

    // Social / Open Graph image selection.
    'image_selection' => [
        // 'first' (default) — first non-empty source wins, nothing measured.
        // 'best' — score local candidates by closeness to the ideal below,
        //          skipping any under the minimum.
        'strategy' => env('SEO_IMAGE_SELECTION', 'first'),
        'minimum_width' => 200,
        'minimum_height' => 200,
        'ideal_width' => 1200,
        'ideal_height' => 630,
    ],
],
```

Με τη στρατηγική `best`, που απαιτεί ρητή ενεργοποίηση, ο builder βαθμολογεί μια ταξινομημένη λίστα υποψηφίων — πρώτα το `getSEOImage()` (παραμένει ο υποψήφιος υψηλότερης προτεραιότητας), έπειτα το hook `getSEOImages()` του μοντέλου, τα συνήθη πεδία εικόνων, την πρώτη εικόνα περιεχομένου και τη ρυθμισμένη προεπιλογή — με βάση το πόσο κοντά είναι οι διαστάσεις κάθε εικόνας σε pixel στις ιδανικές, και **παραλείπει όσες είναι κάτω από το ελάχιστο**. Μετριούνται μόνο **τοπικές** εικόνες (σχετική διαδρομή μέσα στο `public/`, ο δημόσιος δίσκος ή απόλυτη διεύθυνση URL στον δικό σας host). Μια απομακρυσμένη διεύθυνση URL δεν ανακτάται ποτέ και λειτουργεί μόνο ως εφεδρική επιλογή. Όταν κανένας τοπικός υποψήφιος δεν καλύπτει το ελάχιστο, η επιλογή επιστρέφει στην πρώτη αντιστοίχιση, ώστε το `best` να μην επιστρέφει ποτέ λιγότερα από όσα θα επέστρεφε το `first`. Εκθέστε υποψήφιες εικόνες από το μοντέλο σας:

```php
use Rankbeam\Seo\Data\SEOImageCandidate;

public function getSEOImages(): iterable
{
    return [
        SEOImageCandidate::make($this->hero_url)->priority(100),
        SEOImageCandidate::make($this->thumbnail_url)->priority(10),
    ];
}
```

## Χάρτες ιστοτόπου {#sitemaps}

```php
'sitemap' => [
    'disk'                 => env('SEO_SITEMAP_DISK', 'public'),
    'path'                 => 'sitemap.xml',
    'max_urls_per_sitemap' => 50000,

    // ModelClass::class => ['priority' => 0.8, 'changefreq' => 'weekly']
    'models' => [],

    // [['url' => '/', 'priority' => 1.0, 'changefreq' => 'daily']]
    'static_urls' => [],

    'ping_search_engines' => env('SEO_SITEMAP_PING', false),
],
```

Δείτε τον [οδηγό μητρώου χαρτών ιστοτόπου](/el/guide/sitemaps) για πηγές που ορίζονται προγραμματιστικά.

## Δομημένα δεδομένα (JSON-LD) {#schema-json-ld}

```php
'schema' => [
    'organization' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        'logo' => env('SEO_ORGANIZATION_LOGO'),
        // 'sameAs' => [],   // social profile URLs
    ],
    'publisher' => [
        'name' => env('APP_NAME'),
        'logo' => env('SEO_PUBLISHER_LOGO'),
    ],
    'website' => [
        'name' => env('APP_NAME'),
        'url'  => env('APP_URL'),
        // 'potentialAction' => [],   // SearchAction for sitelinks search box
    ],
],
```

Αυτές οι τιμές τροφοδοτούν τους κόμβους του [γράφου δομημένων δεδομένων](/el/guide/schema).

## Διαδρομές {#routes}

```php
'routes' => [
    'enabled'        => env('SEO_ROUTES_ENABLED', true),
    'prefix'         => '',
    'middleware'     => ['web'],
    'api_prefix'     => 'api/seo',
    'api_middleware' => ['api'],
],
```

Ορίστε `enabled => false` όταν η εφαρμογή σας σερβίρει το δικό της στατικό `/sitemap.xml`.

## Προσωρινή μνήμη {#cache}

```php
'cache' => [
    'prefix' => 'seo_',
    'store'  => env('SEO_CACHE_STORE'),   // null = app default

    // Resolver result cache — the scale lever for hot frontends. OFF by default.
    'resolver' => [
        'enabled' => env('SEO_RESOLVER_CACHE', false),
        'ttl'     => env('SEO_RESOLVER_CACHE_TTL', 3600),
    ],
],
```

### Προσωρινή μνήμη αποτελεσμάτων του resolver {#resolver-result-cache}

Ο `SEOResolver` εκτελεί ολόκληρη την αλυσίδα προτεραιότητας — ρυθμίσεις → καθολικές προεπιλογές / προεπιλογές τύπου μοντέλου / διαδρομής → υπολογιζόμενες τιμές μοντέλου → ρητό `seo_meta` → επίθημα τίτλου / κανονική διεύθυνση URL / δομημένα δεδομένα — σε **κάθε** απόδοση του frontend. Σε έναν ιστότοπο με μεγάλη κίνηση (η εφαρμογή αναφοράς δέχεται περίπου 20 χιλ. αιτήματα την ημέρα), αυτό σημαίνει αρκετές αναγνώσεις από τη βάση ανά σελίδα.

Ενεργοποιήστε το `cache.resolver.enabled` και τα πλήρως επιλυμένα δεδομένα SEO ενός μοντέλου αποθηκεύονται στην προσωρινή μνήμη. Μια **εύρεση στην προσωρινή μνήμη παρακάμπτει εντελώς την αλυσίδα προτεραιότητας** — στο benchmark του πακέτου, μια εύρεση σε ήδη γεμάτη προσωρινή μνήμη εκτελεί **μηδέν** ερωτήματα στη βάση, ενώ κάθε επίλυση χωρίς προσωρινή μνήμη διαβάζει ξανά το `seo_meta` του μοντέλου. Το αποθηκευμένο payload είναι απλός πίνακας, που ανασυστήνεται με `SEOData::fromArray()` (ποτέ αντικείμενο — το Laravel 13 διαθέτει `cache.serializable_classes = false`, οπότε ένα αποθηκευμένο αντικείμενο επιστρέφεται ως `__PHP_Incomplete_Class`).

Χρησιμοποιεί το `store` που ρυθμίζεται παραπάνω, οπότε στην παραγωγή κατευθύνετέ το σε **κοινόχρηστη, μόνιμη προσωρινή μνήμη** (`redis` / `memcached`) — η προσωρινή μνήμη και η ακύρωσή της πρέπει να είναι ορατές σε κάθε worker ιστού/ουράς. Αφήστε το απενεργοποιημένο μέχρι να διαθέτετε μία.

**Η ακύρωση είναι αυτόματη και σωστή** — με ενεργή προσωρινή μνήμη η επίλυση είναι ίδια με την επίλυση χωρίς αυτή. Οι εγγραφές έχουν κλειδί `(model class, id, locale, route, request URL)` και διαγράφονται όταν:

- η εγγραφή `seo_meta` της σελίδας **αποθηκεύεται ή διαγράφεται** (από οποιαδήποτε διαδρομή: `saveSEO()`, Filament, άμεση εγγραφή `SEOMeta`)·
- αλλάζει ένα **πεδίο περιεχομένου** στο μοντέλο — οι στήλες από το `getSEOContentFields()` (η προεπιλογή περιλαμβάνει κάθε ενσωματωμένο πεδίο υπολογιζόμενης εφεδρικής τιμής: πεδία title/headline, πεδία excerpt/summary/content/body/text/article και συνήθη πεδία εικόνων όπως `featured_image`, `thumbnail`, `cover_image`, `og_image`, `photo`, `banner` και `hero_image`· αντικαταστήστε τη λίστα αν το μοντέλο σας υπολογίζει SEO από επιπλέον στήλες)·
- αλλάζει **οποιαδήποτε εγγραφή `seo_defaults`** (μια προεπιλογή μπορεί να τροφοδοτεί οποιοδήποτε μοντέλο, οπότε αυτό αδειάζει ολόκληρη την προσωρινή μνήμη επίλυσης).

Σε αποθηκευτικό μέσο που **υποστηρίζει ετικέτες** (`redis`, `memcached`, `array`), οι εγγραφές ενός μοντέλου διαγράφονται μέσω **ετικετών** προσωρινής μνήμης. Σε μέσο **χωρίς υποστήριξη ετικετών** (`file`, `database`), το πακέτο χρησιμοποιεί μια **ένδειξη έκδοσης** ανά μοντέλο. Και οι δύο τρόποι λειτουργούν χωρίς σάρωση κλειδιών.

::: tip
Αποθηκεύονται μόνο επιλύσεις που βασίζονται σε μοντέλο. Τα `SEO::render()`/`@seo()` για ένα χειροκίνητα κατασκευασμένο `SEOData` και το `@seoForRoute()` για διαδρομή χωρίς μοντέλο εξακολουθούν να επιλύονται ζωντανά.
:::

::: warning
Η προσωρινή μνήμη αντικατοπτρίζει το `updated_at` / υπολογιζόμενο `modified_time` του μοντέλου κατά την τελευταία αλλαγή **πεδίου περιεχομένου** (ή μέχρι να λήξει το TTL). Ένα απλό `touch()` που μεταβάλλει μόνο το `updated_at` χωρίς να αλλάζει στήλη `getSEOContentFields()` δεν επιβάλλει νέα επίλυση — το `article:modified_time` μπορεί να καθυστερεί έως και το TTL. Προσθέστε οποιαδήποτε υπολογιζόμενη στήλη της εφαρμογής σας στο `getSEOContentFields()` αν χρειάζεστε άμεση ακύρωση.
:::

---
description: "Παράγετε έναν διασυνδεδεμένο γράφο schema JSON-LD με κόμβους που αναφέρονται μεταξύ τους μέσω σταθερών @id — Organization, WebSite, WebPage, Article — για συνεπή γράφο σε κάθε σελίδα."
---

# Γράφος schema (JSON-LD) {#schema-graph-json-ld}

Οι μηχανές αναζήτησης διαβάζουν καλύτερα το JSON-LD όταν οι κόμβοι αναφέρονται μεταξύ τους — ο κόμβος
Organization δημοσιεύει το WebSite, το WebSite περιέχει τη WebPage και η
WebPage αφορά το Article. Το `SchemaGraph` παράγει ακριβώς αυτό: ένα σύνολο
κόμβων διασυνδεδεμένων μέσω **σταθερών τιμών `@id`**, ώστε κάθε σελίδα να παράγει έναν
συνεπή γράφο.

## Ο γράφος της σελίδας {#the-page-graph}

```php
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\Schema\SchemaCollection;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

$seo = SEO::resolve($post);

$graph = new SchemaGraph();

$schemas = SchemaCollection::make()
    ->add($graph->organization())   // @id: {app_url}#organization
    ->add($graph->webSite())        // @id: {app_url}#website, publisher → #organization
    ->add($graph->webPage($seo));   // @id: {page_url}#webpage, isPartOf → #website
```

Αποδώστε τον στο Blade (στο head ή στο body):

```blade
{!! $schemas->toScript() !!}
```

Τα δεδομένα Organization και WebSite προέρχονται από το `config/seo.php` (`schema.organization`,
`schema.website`)· ο κόμβος WebPage συμπληρώνεται από το επιλυμένο `SEOData`.

Ο κόμβος WebPage περιέχει το `inLanguage` από το επιλυμένο locale της σελίδας σε μορφή BCP 47
(`it_IT` → `it-IT`), το `ArticleSchema::fromModel()` το αντλεί από το locale του αποθηκευμένου
`seo_meta` και ο κόμβος WebSite παραθέτει τις γλώσσες του ιστοτόπου από το
`schema.website.inLanguage` (ένας κωδικός ή λίστα). Ορίστε το `schema.in_language` σε
`false` για να μην παράγεται καθόλου `inLanguage`. Δείτε το [Πολύγλωσσο περιεχόμενο](/el/guide/multilingual#inlanguage-in-the-schema-graph).

## Builders συγκεκριμένων τύπων {#typed-builders}

Υπάρχουν builders για τους συνήθεις τύπους εμπλουτισμένων αποτελεσμάτων:

| Builder | Σημειώσεις |
|---|---|
| `ArticleSchema::fromModel($post)` | Ημερομηνίες, συντάκτης, εκδότης από το μοντέλο + τις ρυθμίσεις |
| `ProductSchema` | Προσφορές, τιμή, διαθεσιμότητα |
| `BreadcrumbSchema::fromArray([...])` | Διατεταγμένα ζεύγη ονόματος/URL |
| `BreadcrumbSchema::fromModelAncestors($page)` | Διατρέχει μια αλυσίδα `parent` (με προστασία από βρόχους) |
| `FAQSchema` | Ζεύγη ερώτησης/απάντησης |
| `LocalBusinessSchema` | Διεύθυνση, γεωγραφικά στοιχεία, ωράριο λειτουργίας |
| `OrganizationSchema` | Αυτόνομος κόμβος οργανισμού |

Μια πλήρης σελίδα άρθρου:

```php
$article = ArticleSchema::fromModel($post)
    ->setPublisherOrganization(config('seo.schema.publisher.name'));

$schemas = SchemaCollection::make()
    ->add($graph->organization())
    ->add($graph->webSite())
    ->add($graph->webPage($seo))
    ->add($article->toArray())
    ->add(BreadcrumbSchema::fromArray([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog'],
        ['name' => $post->title, 'url' => "/blog/{$post->slug}"],
    ])->toArray());
```

## Συνδεδεμένο schema και `@seoSchema` {#attached-schema-and-seoschema}

Το schema που αποθηκεύεται στο επιλυμένο `SEOData` (π.χ. μαζί με ρητά μεταδεδομένα)
αποδίδεται μέσω της οδηγίας `@seoSchema` ή της ενότητας `script` του `SEO::toArray()`:

```blade
@seoSchema($post)
```

Οι συντάκτες μπορούν να συμπληρώσουν το `seo_meta.schema_jsonld` χωρίς κώδικα μέσω της προαιρετικής
ενότητας **Δομημένα δεδομένα** στο πακέτο [πεδίων Filament](/el/guide/filament#structured-data-schema-org)
— ένας διακόπτης για αυτόματη διαδρομή πλοήγησης και μπλοκ FAQ / Product, που ελέγχονται
μέσω του `SchemaValidator` πριν αποθηκευτούν.

## Διαφυγή χαρακτήρων {#escaping}

Όλη η έξοδος JSON-LD — `SchemaCollection::toScript()`, `toJson()` και οι
διαδρομές του renderer — κωδικοποιείται με `JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP`. Μια ακολουθία `</script>` μέσα σε τίτλους ή
περιεχόμενο δεν μπορεί να τερματίσει το στοιχείο script. Μην παρακάμπτετε αυτή την προστασία
καλώντας εσείς το `json_encode` σε πίνακες schema.

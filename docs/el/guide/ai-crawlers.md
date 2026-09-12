---
description: "Αποδώστε διαχειριζόμενο robots.txt (και προαιρετικό ai.txt) από μια πολιτική allow/disallow πάνω σε επιμελημένο κατάλογο ανιχνευτών AI, επιλέγοντας ποια bots μπορούν να προσπελάσουν τον ιστότοπό σας. Δωρεάν, στον πυρήνα."
---

# Έλεγχος ανιχνευτών AI (robots.txt / ai.txt) {#ai-crawler-control-robots-txt-ai-txt}

Οι μεγάλοι πάροχοι AI ανιχνεύουν τον ιστό με επώνυμα bots και οι περισσότεροι διαβάζουν το **robots.txt**
για να αποφασίσουν τι επιτρέπεται να ανακτήσουν. Το Rankbeam παρέχει έναν επιμελημένο κατάλογο αυτών των bots και
αποδίδει διαχειριζόμενο `robots.txt` (και προαιρετικό `ai.txt`) από μια απλή πολιτική allow /
disallow — ώστε να μπορείτε **να επιτρέπετε τους ανιχνευτές αναζήτησης AI και βοηθών και
να περιορίζετε εκείνους που εκπαιδεύονται με το περιεχόμενό σας.**

Πρόκειται για δωρεάν δυνατότητα του πυρήνα. Το πακέτο Pro προσθέτει το άλλο μισό —
[παρατηρησιμότητα: αρχείο επισκέψεων bots AI](/el/pro/ai-bot-monitor), που δείχνει ποιοι ανιχνευτές AI
επισκέφθηκαν πράγματι τον ιστότοπο.

## Η προεπιλεγμένη πολιτική {#the-default-policy}

Κάθε bot του καταλόγου επισημαίνεται με την κύρια λειτουργία του:

| Σκοπός | Τι κάνει | Προεπιλογή |
| --- | --- | --- |
| `ai_search` | Ανακτά τις σελίδες σας για να τις ευρετηριάσει για απαντήσεις **αναζήτησης AI** (το κανάλι παραπομπών AI) | **allow** |
| `ai_assistant` | Ανακτά μια σελίδα σε πραγματικό χρόνο για λογαριασμό ενός **χρήστη** μέσα σε συνομιλία | **allow** |
| `ai_training` | Συλλέγει περιεχόμενο για να **εκπαιδεύσει** ένα μοντέλο | **disallow** |

Αυτό αντανακλά την προσέγγιση των περισσότερων εκδοτών στην εποχή της AI: προσβάσιμο περιεχόμενο στους ανιχνευτές
αναζήτησης AI και βοηθών που υποστηρίζουν το ChatGPT search, το Perplexity και παρόμοιες υπηρεσίες, με ταυτόχρονη
εξαίρεση από τα δεδομένα εκπαίδευσης. Μπορείτε να αλλάξετε κάθε επιλογή στις ρυθμίσεις.

::: warning Η πρόσβαση δεν συνεπάγεται παραπομπή
Η άδεια πρόσβασης σε έναν ανιχνευτή καθιστά την ανάκτηση *δυνατή*· δεν εγγυάται ανακάλυψη,
ευρετηρίαση, κατάταξη, συμπερίληψη, παράθεση αποσπάσματος ή παραπομπή. Αυτή η πολιτική ελέγχει την
**πρόσβαση** — ποια bots μπορούν να ανακτήσουν τις σελίδες σας — και τίποτα από όσα ακολουθούν.
:::

## Γρήγορη εκκίνηση {#quick-start}

Εκτυπώστε το μπλοκ ανιχνευτών AI για να δείτε τι θα δημοσιεύσετε:

```bash
php artisan seo:robots-txt --print
```

Μπορείτε να το χρησιμοποιήσετε με δύο τρόπους.

### Επιλογή A — επικολλήστε το μπλοκ στο υπάρχον robots.txt {#option-a-—-paste-the-block-into-your-existing-robots-txt}

Αν διατηρείτε ήδη ένα `public/robots.txt`, πάρτε μόνο το διαχειριζόμενο μπλοκ και
επικολλήστε το:

```php
use Rankbeam\Seo\Facades\SEO;

echo SEO::robotsTxt()->aiDirectives();
```

```
# --- AI crawlers (managed by Rankbeam) ---

# GPTBot — OpenAI (AI training)
User-agent: GPTBot
Disallow: /

# Bytespider — ByteDance (AI training) — advisory: this bot may not honour robots.txt
User-agent: Bytespider
Disallow: /
...
```

### Επιλογή B — αφήστε το Rankbeam να διαχειρίζεται ολόκληρο το αρχείο {#option-b-—-let-rankbeam-manage-the-whole-file}

Δημιουργήστε ένα πλήρες `robots.txt` (γενική ενότητα + οδηγίες AI + γραμμή `Sitemap:` +
παραπομπή στο [llms.txt](/el/guide/sitemaps) σας):

```bash
php artisan seo:robots-txt          # writes public/robots.txt
php artisan seo:robots-txt --ai-txt # also write public/ai.txt
```

Προγραμματίστε την εκτέλεση ώστε το αρχείο να ακολουθεί την πολιτική σας:

```php
// routes/console.php
Schedule::command('seo:robots-txt')->daily();
```

Ή εξυπηρετήστε το δυναμικά — ορίστε το `seo.ai_crawlers.route` σε `true` και το πακέτο
απαντά στο `/robots.txt` από τις τρέχουσες ρυθμίσεις (χωρίς βήμα δημιουργίας):

::: warning Το στατικό αρχείο υπερισχύει
Οι περισσότερες εφαρμογές περιλαμβάνουν ήδη ένα `public/robots.txt`, το οποίο εξυπηρετεί ο web server πριν
το Laravel δρομολογήσει το αίτημα. Η δυναμική διαδρομή είναι **απενεργοποιημένη από προεπιλογή**, ώστε
να μην παρακάμπτει σιωπηρά — ή να παρακάμπτεται από — ένα αρχείο που ξεχάσατε. Χρησιμοποιήστε τη
διαδρομή μόνο όταν δεν υπάρχει στατικό `robots.txt`.
:::

## Τα πραγματικά όρια επιβολής {#honesty-about-enforcement}

Το robots.txt είναι αίτημα, όχι φράχτης. Για τα περισσότερα bots του καταλόγου τεκμηριώνεται
ότι το σέβονται, αλλά για ορισμένους agents που ενεργοποιούνται από χρήστες (`ChatGPT-User`, `Perplexity-User`)
και κάποιους ανιχνευτές εκπαίδευσης (`Bytespider`) **δεν ισχύει αυτό** — το Rankbeam επισημαίνει αυτές
τις γραμμές ως `advisory` αντί να υπονοεί έναν αποκλεισμό που δεν θα τηρηθεί. Για να σταματήσετε πραγματικά
ένα bot που δεν συμμορφώνεται, χρειάζεστε αποκλεισμό στον διακομιστή ή στην περιφέρεια του δικτύου (firewall, WAF,
κανόνες bots του Cloudflare)· το [αρχείο επισκέψεων bots AI του Pro](/el/pro/ai-bot-monitor) σάς δείχνει
ποια χρειάζονται προσοχή.

## Σήματα περιεχομένου (προτιμήσεις χρήσης) {#content-signals-usage-preferences}

Τα `Allow` / `Disallow` ελέγχουν την **πρόσβαση** — αν ένα bot μπορεί να ανακτήσει τη σελίδα.
Τα [Content signals](https://contentsignals.org) (το πρότυπο που προωθεί το
Cloudflare) αποτελούν τον άλλο άξονα: δηλώνουν πώς μπορεί να **χρησιμοποιηθεί** το περιεχόμενο
αφού ανακτηθεί. Μία γραμμή `Content-Signal:` στην ομάδα `User-agent: *` περιέχει τρεις
προτιμήσεις:

| Σήμα | Προκύπτει από τον σκοπό πολιτικής | Σημασία |
| --- | --- | --- |
| `search` | `ai_search` | Δημιουργία ευρετηρίου αναζήτησης (σύνδεσμοι + σύντομα αποσπάσματα) |
| `ai-input` | `ai_assistant` | Τροφοδότηση ενός μοντέλου AI με τη σελίδα σε πραγματικό χρόνο (RAG / grounding) |
| `ai-train` | `ai_training` | Εκπαίδευση ή λεπτομερής προσαρμογή μοντέλου AI |

Η δυνατότητα είναι **απενεργοποιημένη από προεπιλογή** (το αρχείο παραμένει ίδιο byte προς byte μέχρι να την ενεργοποιήσετε). Ενεργοποιήστε
την και το Rankbeam παράγει τη γραμμή απευθείας από το υπάρχον `policy` — το `allow`
γίνεται `yes` και το `disallow` γίνεται `no`:

```php
'ai_crawlers' => [
    'content_signals' => true,   // env: SEO_AI_CONTENT_SIGNALS
    // ...with the default policy, this emits, in the User-agent: * group:
    //   Content-Signal: search=yes, ai-input=yes, ai-train=no
],
```

Αν αφαιρέσετε εντελώς έναν σκοπό από το `policy`, το σήμα του **παραλείπεται** — δηλαδή
«δεν εκφράζεται προτίμηση», όπως ορίζει η προδιαγραφή, κάτι διαφορετικό από ένα ρητό `yes`/`no`.

::: warning Συμβουλευτικό, όπως το ίδιο το robots.txt
Τα σήματα περιεχομένου εκφράζουν προτίμηση· **δεν** αποτελούν τεχνικό μέτρο ελέγχου. Ένας
ανιχνευτής μπορεί να τα αγνοήσει. Συνυπάρχουν με — δεν αντικαθιστούν — τους παραπάνω κανόνες πρόσβασης
και κάθε αποκλεισμό στην περιφέρεια του δικτύου.
:::

## Ρυθμίσεις {#configuration}

```php
// config/seo.php
'ai_crawlers' => [
    'enabled' => true,
    'route'   => false,             // serve /robots.txt dynamically (off by default)
    'disk'    => 'public',
    'path'    => 'robots.txt',
    'ai_txt_path' => 'ai.txt',

    // Policy by purpose. A purpose left out is allowed.
    'policy' => [
        'ai_training'   => 'disallow',
        'ai_search'     => 'allow',
        'ai_assistant'  => 'allow',
        'search_engine' => 'allow',   // Yandex, Baidu, Naver, Seznam, … (3.15)
    ],

    // Per-bot overrides, keyed by catalog id (win over the purpose policy).
    'overrides' => [
        'gptbot' => 'allow',          // e.g. opt GPTBot back in
        'baiduspider' => 'disallow',  // e.g. keep a search engine you don't serve off your bandwidth
    ],

    // 'blocked' = only disallowed bots get a line (lean file);
    // 'all'     = every known bot gets an explicit allow/disallow (auditable).
    'list' => 'blocked',

    // Emit a Content-Signal usage-preference line (off by default), derived
    // from `policy` above. See "Content signals" above.
    'content_signals' => false,

    // The general `User-agent: *` section: true = permissive default,
    // a string = your own rules verbatim, false = omit.
    'general' => true,

    'include_sitemap' => true,
    'sitemap_url'     => null,        // null = derive from the sitemap route
    'include_llms_txt' => true,
],
```

Παρακάμψτε την πολιτική για ένα συγκεκριμένο bot ανεξάρτητα από τον σκοπό του με το `overrides`, χρησιμοποιώντας ως κλειδί το
**id** του καταλόγου (π.χ. `gptbot`, `claudebot`, `perplexitybot`, `google-extended`).

## Ο κατάλογος {#the-catalog}

Το `SEO::aiCrawlers()` είναι η πηγή αλήθειας — ο ίδιος κατάλογος που χρησιμοποιεί το αρχείο επισκέψεων Pro
για την αναγνώριση επισκεπτών, ώστε το αρχείο που ελέγχει ένα bot και ο πίνακας που
το παρακολουθεί να μη διαφωνούν ποτέ.

```php
SEO::aiCrawlers()->all();               // every known AiCrawler
SEO::aiCrawlers()->get('gptbot');       // one bot
SEO::aiCrawlers()->actionFor('gptbot'); // 'allow' | 'disallow' (resolved policy)
SEO::aiCrawlers()->match($userAgent);   // identify a request UA, or null
```

Καλύπτει τους μεγάλους παρόχους — OpenAI (GPTBot, OAI-SearchBot, ChatGPT-User),
Anthropic (ClaudeBot, Claude-SearchBot, Claude-User), Google (Google-Extended),
Perplexity, Apple (Applebot-Extended), Common Crawl (CCBot), Meta, Amazon,
ByteDance και άλλους — καθέναν με τον τεκμηριωμένο σκοπό και το token του στο robots.txt.

### Περιφερειακές μηχανές αναζήτησης {#regional-search-engines}

Από την 3.15, ο κατάλογος περιλαμβάνει και τους κλασικούς ανιχνευτές αναζήτησης που έχουν σημασία
πέρα από τον κόσμο Google/Bing, με σκοπό `search_engine` και
**επιτρεπόμενη πρόσβαση από προεπιλογή**:

| id | Token | Πάροχος |
|---|---|---|
| `yandex` | `Yandex` | Yandex (Ρωσία) — το σκέτο token καλύπτει όλα τα bots του |
| `baiduspider` | `Baiduspider` | Baidu (Κίνα) |
| `yeti` | `Yeti` | Naver (Κορέα) |
| `seznambot` | `SeznamBot` | Seznam (Τσεχία) |
| `sogou` | `Sogou web spider` | Sogou (Κίνα) |
| `360spider` | `360Spider` | Qihoo 360 (Κίνα) |
| `coccocbot` | `coccocbot-web` | Cốc Cốc (Βιετνάμ) |
| `duckduckbot` | `DuckDuckBot` | DuckDuckGo |

Συμμετέχουν στα `policy` και `overrides` όπως κάθε άλλο bot — επομένως το
`'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow']` εμποδίζει δύο
ανιχνευτές που δεν εξυπηρετείτε να καταναλώνουν το εύρος ζώνης σας, και το `'list' => 'all'` δίνει στον καθένα
μια ρητή γραμμή. Παραμένουν **εκτός** των `all()` και `match()` εκτός αν ζητηθούν —
`searchEngines()`, `all(true)`, `match($ua, true)` — ώστε το αρχείο bots AI του Pro και
κάθε πλήθος «N ανιχνευτές AI» να διατηρούν τη σημασία τους:

```php
SEO::aiCrawlers()->searchEngines();          // the eight engines
SEO::aiCrawlers()->get('yandex');            // works for both lists
SEO::aiCrawlers()->match($userAgent, true);  // identify an engine too
```

Η αναγνώριση ενός ανιχνευτή δεν εγγυάται προβολή ή κατάταξη στη συγκεκριμένη
μηχανή αναζήτησης. Οι αντίστοιχες ετικέτες επαλήθευσης ιστοτόπου
(`yandex-verification`, `baidu-site-verification`, `naver-site-verification`,
`seznam-wmt`) βρίσκονται στο `seo.verification`· δείτε το
[Πολύγλωσσο περιεχόμενο](/el/guide/multilingual#site-verification).

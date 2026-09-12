---
description: "Αυτόνομος διακομιστής MCP μέσω stdio, χωρίς εξαρτήσεις, για ανάγνωση και προαιρετική επεξεργασία του SEO ιστοτόπου Laravel από βοηθό AI. Laravel 11: PHP 8.2–8.4· Laravel 12: PHP 8.2–8.5· Laravel 13: PHP 8.3–8.5."
---

# Διακομιστής MCP {#mcp-server}

Ο διακομιστής MCP του Rankbeam επιτρέπει σε βοηθό AI να **διαβάζει — και προαιρετικά να επεξεργάζεται — το
SEO ενός ιστοτόπου** μέσω του [Model Context Protocol](https://modelcontextprotocol.io).
Συνδέστε έναν πελάτη MCP (Claude Code / Claude Desktop, Cursor, Codex, …) με την εφαρμογή
Laravel σας και μπορεί να επιλύει τα μεταδεδομένα μιας σελίδας, να εκτελεί έλεγχο, να διαβάζει τη βαθμολογία
Pro, να βλέπει την πολιτική ανιχνευτών AI και — όταν το επιτρέπετε — να αποθηκεύει αλλαγές SEO.

Είναι αυτόνομος διακομιστής stdio **χωρίς εξαρτήσεις** — χωρίς SDK ή νέα
πακέτα — και λειτουργεί με PHP 8.2–8.4 (Laravel 11), PHP 8.2–8.5 (Laravel 12), PHP 8.3–8.5 (Laravel 13).

::: tip Λειτουργία Pro
Ο διακομιστής MCP περιλαμβάνεται στο `rankbeam/laravel-seo-pro`. Είναι **μόνο για ανάγνωση από
προεπιλογή**· οι αλλαγές απαιτούν ρητή ενεργοποίηση με σημαία ρυθμίσεων και λίστα επιτρεπόμενων μοντέλων.
:::

## Τι μπορεί να κάνει ο βοηθός {#what-the-assistant-can-do}

### Εργαλεία ανάλυσης (πάντα διαθέσιμα) {#analysis-tools-always-available}

| Εργαλείο | Τι κάνει |
| --- | --- |
| `seo_resolve` | Πλήρως επιλυμένα μεταδεδομένα SEO (τίτλος, περιγραφή, κανονική διεύθυνση URL, robots, Open Graph, JSON-LD) για εγγραφή μοντέλου — όσα θα απέδιδε πραγματικά η σελίδα. |
| `seo_audit` | [Έλεγχος μεταδεδομένων](/el/guide/audit) εντός της διεργασίας για εγγραφή μοντέλου (ή τις πρώτες N εγγραφές) — οι ίδιοι έλεγχοι `seo:audit`, ζωντανά, χωρίς ουρά. |
| `seo_score` | Η τελευταία αποθηκευμένη [βαθμολογία SEO Pro](/el/pro/scoring) (0–100 και βαθμίδα) για εγγραφή μοντέλου. |
| `seo_robots_directives` | Οι διαχειριζόμενες [οδηγίες robots.txt για ανιχνευτές AI](/el/guide/ai-crawlers) και η επιλυμένη πολιτική επιτρέπεται/απαγορεύεται ανά bot. |
| `validate_schema` | Επικυρώνει αντικείμενο JSON-LD — ή τον επιλυμένο γράφο schema επιτρεπόμενου μοντέλου — με τον επικυρωτή δομημένων δεδομένων του core (απαιτήσεις εμπλουτισμένων αποτελεσμάτων Google ανά `@type`). |
| `analyze_robots` | Η οριστική απόφαση επιτρέπεται/απαγορεύεται **ανά γνωστό ανιχνευτή AI**, μαζί με το τι την καθόρισε (παράκαμψη ανά bot, πολιτική σκοπού ή προεπιλογή). Η πολιτική ισχύει συνολικά στον ιστότοπο. |
| `debug_social_share` | Η επιλυμένη κάρτα Open Graph και Twitter που θα έβλεπε πραγματικά ένας κοινωνικός ανιχνευτής για εγγραφή μοντέλου (μετά τις εναλλακτικές τιμές), με συμβουλευτικές σημειώσεις κατάστασης κάρτας. |
| `check_meta` | Εστιασμένη εικόνα κατάστασης μεταδεδομένων — επιλυμένα title/description/canonical/robots/og:image με μήκη και παρουσία, μαζί με τα προβλήματα ελέγχου — για μία εγγραφή μοντέλου. |

### Εργαλεία περιεχομένου ιστοτόπου (πάντα διαθέσιμα) {#site-content-tools-always-available}

Τα εργαλεία «μιλήστε στον ιστότοπό σας» μετατρέπουν τον διακομιστή σε βοηθό που γνωρίζει
το περιεχόμενο και μπορεί να απαριθμεί και να αναζητά τις σελίδες σας.

| Εργαλείο | Τι κάνει |
| --- | --- |
| `list_pages` | Παραθέτει τις σελίδες (εγγραφές) ενός μοντέλου **στη λίστα επιτρεπόμενων**, των οποίων το SEO διαχειρίζεται το πακέτο — καθεμία με URL και τελικό τίτλο. Υποστηρίζει σελιδοποίηση `limit`/`offset`. |
| `search_pages` | Αναζήτηση πλήρους κειμένου στις σελίδες μοντέλου **στη λίστα επιτρεπόμενων** — μέσω Laravel [Scout](https://laravel.com/docs/scout) όταν το μοντέλο υποστηρίζει αναζήτηση, αλλιώς με ασφαλές SQL `LIKE` (title/name/headline και συνδεδεμένα μεταδεδομένα SEO). Επιστρέφει URL, τίτλο και απόσπασμα ανά αποτέλεσμα. |

### Λειτουργικά εργαλεία (με ρητή ενεργοποίηση) {#ops-tools-opt-in}

Λειτουργικά εργαλεία που διαβάζουν την κατάσταση σαρώσεων και αλλάζουν τις ρυθμίσεις ιστοτόπου. Όπως το
εργαλείο επεξεργασίας, **απαιτούν το `allow_edits`** — είναι αόρατα και αδρανή σε
διακομιστή μόνο για ανάγνωση (την προεπιλογή).

| Εργαλείο | Τι κάνει |
| --- | --- |
| `list_issues` | Τα τρέχοντα ανοιχτά προβλήματα σάρωσης SEO (το μόνιμο σύνολο ανοιχτών προβλημάτων μεταξύ εκτελέσεων) και η κεφαλίδα της τελευταίας [σάρωσης](/el/pro/scan-issues). Φίλτρα ανά `severity` / `type`. |
| `trigger_scan` | Ξεκινά σάρωση: στοχευμένη σε μία επιτρεπόμενη εγγραφή (επιστρέφει την εκτέλεση) ή πλήρη σάρωση κάθε στόχου — στην ουρά από προεπιλογή ή άμεσα με `sync: true`. |
| `create_redirect` | Δημιουργεί κανόνα ανακατεύθυνσης (διαδρομή πηγής ή regex → προορισμός, κωδικός `301`/`302`/`307`/`308`/`410`), επαναχρησιμοποιώντας τους επικυρωτές του ίδιου του μοντέλου ανακατεύθυνσης. |

### Εργαλείο επεξεργασίας (με ρητή ενεργοποίηση) {#edit-tool-opt-in}

| Εργαλείο | Τι κάνει |
| --- | --- |
| `seo_save_meta` | Γράφει μεταδεδομένα SEO (τίτλο, περιγραφή, κανονική διεύθυνση URL, robots, OG, Twitter, JSON-LD) σε εγγραφή μοντέλου **στη λίστα επιτρεπόμενων** μέσω `saveSEO()`. |

Τα λειτουργικά εργαλεία και το `seo_save_meta` **δεν εμφανίζονται στο `tools/list` και δεν
εκτελούνται** αν δεν ενεργοποιήσετε τις αλλαγές — δείτε [Ασφάλεια](#security). Ένας διακομιστής μόνο για ανάγνωση
δεν ενημερώνει καν τον βοηθό ότι υπάρχουν αυτά τα εργαλεία.

## Σύνδεση πελάτη AI {#wiring-an-ai-client}

Ο διακομιστής μιλά JSON-RPC μέσω **stdio**: ο πελάτης εκκινεί μια εντολή
Artisan και επικοινωνεί μαζί της μέσω διοχέτευσης. Καταχωρίστε τον στους πελάτες που
χρησιμοποιείτε — ο ίδιος διακομιστής λειτουργεί για όλους.

::: tip Μία εντολή, οποιοσδήποτε πελάτης
Κάθε παρακάτω πελάτης εκτελεί την ίδια εντολή εκκίνησης:
`php artisan seo-pro:mcp`, **από τη ρίζα της εφαρμογής σας** (ώστε το Artisan να
αρχικοποιήσει την εφαρμογή). Σε μηχάνημα όπου το `php` δεν βρίσκεται στο `PATH` του πελάτη
(συνηθισμένο στα Windows ή σε εφαρμογές GUI που δεν κληρονομούν το περιβάλλον του shell),
δώστε **απόλυτη διαδρομή και για το `php` και για το `artisan`** — το Artisan ξεκινά από
τον κατάλογο του ίδιου του script `artisan`, οπότε δεν χρειάζεται `cwd`.
:::

### Claude Code (CLI) {#claude-code-cli}

Μία εντολή το καταχωρίζει. Εκτελέστε την **από τη ρίζα της εφαρμογής σας**:

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Επιβεβαιώστε τη σύνδεση:

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

Σε Windows / Laravel Herd, ορίστε τις απόλυτες διαδρομές ώστε να λειτουργεί ανεξάρτητα από τον
κατάλογο εκκίνησης:

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Επεξεργαστείτε το αρχείο ρυθμίσεων (**Settings → Developer → Edit Config**) ή ανοίξτε το
απευθείας:

- **Windows**: `%APPDATA%\Claude\claude_desktop_config.json`
- **macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

Στα **Windows**, χρησιμοποιήστε το απόλυτο `php.exe` και διπλασιάστε κάθε ανάστροφη κάθετο στο
JSON:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "C:\\Users\\you\\.config\\herd\\bin\\php84\\php.exe",
      "args": ["C:\\path\\to\\app\\artisan", "seo-pro:mcp"]
    }
  }
}
```

Κλείστε πλήρως και ανοίξτε ξανά το Claude Desktop. Τα εργαλεία εμφανίζονται πίσω από το
εικονίδιο εργαλείων/βύσματος στη γραμμή μηνύματος.

### Cursor {#cursor}

Δημιουργήστε το `.cursor/mcp.json` στο έργο σας (ή το `~/.cursor/mcp.json` για όλα τα
έργα). Δώστε απόλυτη διαδρομή `artisan` ώστε να εκκινεί από οπουδήποτε:

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

Στα Windows, χρησιμοποιήστε απόλυτο `php.exe` και διπλές ανάστροφες καθέτους, ακριβώς
όπως στο παραπάνω παράδειγμα Claude Desktop. Ενεργοποιήστε τον διακομιστή από
**Settings → MCP**.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Όπως με το Claude Code, δώστε απόλυτες διαδρομές `php` και `artisan` σε Windows/Herd.

Αυτό ήταν — ο βοηθός μπορεί πλέον να καλεί τα εργαλεία.

## Παράδειγμα συνομιλίας {#a-worked-conversation}

Ακολουθεί πραγματική συνομιλία με εφαρμογή επίδειξης (ιστότοπο ιατρικού ιδρύματος 900 σελίδων),
με συντομευμένη την πραγματική έξοδο των εργαλείων. Τα δύο μοντέλα ανάγνωσης στη λίστα επιτρεπόμενων
είναι τα `App\Models\Page` και `App\Models\PublicPage`, και το `allow_edits` είναι ενεργό.

> **Εσείς:** Πώς είναι το SEO στη σελίδα «Accoglienza» (Page id 1); Διόρθωσε ό,τι προφανές βρεις.

Ο βοηθός πρώτα την **ελέγχει** — `seo_audit { model: "App\\Models\\Page", id: 1 }`:

```json
[
  {
    "id": 1,
    "url": "http://localhost/accoglienza",
    "issue_count": 2,
    "issues": [
      { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 17 characters (recommended min 30).", "context": { "length": 17, "min": 30 } },
      { "code": "description_too_short", "severity": "notice", "field": "description", "message": "Description is only 11 characters (recommended min 70).", "context": { "length": 11, "min": 70 } }
    ]
  }
]
```

**Επιλύει** τα τρέχοντα μεταδεδομένα για να δει τι αποδίδεται πραγματικά —
`seo_resolve { model: "App\\Models\\Page", id: 1 }` — και βρίσκει τίτλο 17 χαρακτήρων και
περιγραφή 11 χαρακτήρων, και τα δύο κάτω από τα προτεινόμενα ελάχιστα. Έτσι
**γράφει μια διόρθωση** — `seo_save_meta`:

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

Το εργαλείο επιβεβαιώνει την εγγραφή και επιστρέφει την εκ νέου επιλυμένη εγγραφή:

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

Τέλος **επαναλαμβάνει τον έλεγχο** για να επιβεβαιώσει τη διόρθωση — το `issue_count` είναι πλέον `0`:

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Βοηθός:** Έγινε. Ο τίτλος (17 χαρακτήρες) και η περιγραφή (11 χαρακτήρες) ήταν και τα δύο
> κάτω από τα προτεινόμενα ελάχιστα — όρισα πληρέστερο, περιγραφικό τίτλο και
> περιγραφή, και ο νέος έλεγχος δεν αναφέρει προβλήματα (0 προβλήματα).

Η εγγραφή πέρασε από το ίδιο `saveSEO()` που χρησιμοποιεί κάθε προγραμματιστική αποθήκευση, οπότε η
τιμή αποθηκεύεται και επιλύεται ακριβώς όπως μια τιμή που ορίζεται σε κώδικα (με μαζική ανάθεση μέσω
των fillable πεδίων και των casts του μοντέλου). **Δεν** περνά από τους επικυρωτές μήκους της φόρμας
Filament — επομένως ο νέος έλεγχος επιβεβαιώνει μόνο τους αυτόματους ελέγχους του. Η συντακτική ακρίβεια εξακολουθεί
να χρειάζεται ανασκόπηση· ένας έλεγχος χωρίς προβλήματα δεν πιστοποιεί την ποιότητα του κειμένου.

### Συνομιλία με το περιεχόμενο του ιστοτόπου σας {#talking-to-your-site-s-content}

Τα εργαλεία περιεχομένου επιτρέπουν στον βοηθό να απαντά σε ερωτήσεις **για τον ίδιο τον
ιστότοπο**, όχι μόνο για μία εγγραφή που κατονομάζετε — η επίδειξη «μιλήστε στον ιστότοπό σας».

> **Εσείς:** Ποιες σελίδες αναφέρουν το «immunoterapia» και είναι εντάξει οι τίτλοι τους;

Ο βοηθός **αναζητά** — `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }`:

```json
{
  "model": "App\\Models\\Page",
  "query": "immunoterapia",
  "driver": "like",
  "count": 3,
  "results": [
    { "id": 42, "url": "http://localhost/ricerca/immunoterapia-oncologica", "title": "Immunoterapia oncologica", "snippet": "Il programma di immunoterapia dell'IDI: terapie, sperimentazioni e accesso…" },
    { "id": 87, "url": "http://localhost/reparti/dermatologia-oncologica", "title": "Dermatologia oncologica", "snippet": "…include protocolli di immunoterapia per i tumori cutanei avanzati…" },
    { "id": 91, "url": "http://localhost/news/nuova-immunoterapia-2026", "title": "Nuova", "snippet": "Al via una nuova immunoterapia…" }
  ]
}
```

Ο τρίτος τίτλος είναι μόνο «Nuova», οπότε **ελέγχει τα μεταδεδομένα αυτής της σελίδας** —
`check_meta { model: "App\\Models\\Page", id: 91 }`:

```json
{
  "url": "http://localhost/news/nuova-immunoterapia-2026",
  "title": { "value": "Nuova", "length": 5, "present": true },
  "description": { "value": null, "length": 0, "present": false },
  "og_image": { "value": null, "present": false },
  "issue_count": 2,
  "issues": [
    { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 5 characters (recommended min 30)." },
    { "code": "missing_description", "severity": "warning", "field": "description", "message": "No meta description is set." }
  ]
}
```

> **Βοηθός:** Τρεις σελίδες αναφέρουν το «immunoterapia». Δύο φαίνονται εντάξει, αλλά η
> **/news/nuova-immunoterapia-2026** έχει τίτλο 5 χαρακτήρων («Nuova») και καθόλου
> meta description. Θέλετε να γράψω πληρέστερο τίτλο και περιγραφή;

Με ενεργό το `allow_edits` θα μπορούσε να τη διορθώσει επιτόπου με το `seo_save_meta`· σε λειτουργία μόνο για ανάγνωση,
σταματά στην πρόταση. Το `search_pages` χρησιμοποίησε εδώ την εναλλακτική SQL `LIKE`
(`"driver": "like"`), επειδή το μοντέλο δεν είναι ευρετηριασμένο στο Scout — προσθέστε
[Laravel Scout](https://laravel.com/docs/scout) και το ίδιο εργαλείο αναζητά αυτόματα
μέσω της μηχανής σας.

## Ασφάλεια {#security}

Τρία επίπεδα κρατούν τον διακομιστή ασφαλή από προεπιλογή. Και τα τρία είναι ενεργά στην
προεπιλογή μόνο για ανάγνωση· τα χαλαρώνετε συνειδητά.

### 1. Οι αλλαγές απαιτούν ενεργοποίηση (ανενεργές από προεπιλογή) {#_1-edits-are-gated-off-by-default}

Το εργαλείο εγγραφής είναι αόρατο και αδρανές μέχρι να αλλάξετε μια σημαία:

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

Με ανενεργό το `allow_edits` (η προεπιλογή), το `seo_save_meta` **δεν επιστρέφεται από το
`tools/list`** και μια κλήση `tools/call` προς αυτό αποτυγχάνει με JSON-RPC
`-32602` — ο βοηθός δεν μπορεί να γράψει ούτε καν να ανακαλύψει ότι θα μπορούσε.
Ενεργοποιήστε το μόνο για πελάτη και βάση δεδομένων που εμπιστεύεστε.

### 2. Η λίστα επιτρεπόμενων μοντέλων {#_2-the-model-allowlist}

Κάθε εργαλείο που αφορά μοντέλο — ανάγνωσης *ή* εγγραφής — μπορεί να προσπελάσει μόνο μοντέλο `HasSEO`
στη λίστα επιτρεπόμενων. Ένας πελάτης AI δεν μπορεί ποτέ να κατευθύνει εργαλείο σε αυθαίρετη
κλάση (`User`, μοντέλο χρέωσης ή οτιδήποτε άλλο):

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Εργαλείο που λαμβάνει κλάση εκτός λίστας επιστρέφει αποτέλεσμα σφάλματος που διαβάζει ο βοηθός
(`Model [App\Models\User] is not in the MCP allowlist`) — δεν
προσπελαύνει ποτέ την κλάση. Όταν το `models` είναι κενό, χρησιμοποιεί τα ρυθμισμένα
`seo.audit.models` / `seo.sitemap.models`, οπότε το MCP έχει ακριβώς το ίδιο πεδίο πρόσβασης
με το υπόλοιπο πακέτο — ποτέ ευρύτερο.

### 3. Μόνο stdio — τίποτε δεν εκτίθεται στο δίκτυο {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

Ο διακομιστής επικοινωνεί **μόνο μέσω stdio**: ο πελάτης εκκινεί τη διεργασία και διοχετεύει
JSON-RPC προς και από αυτή. **Δεν υπάρχει HTTP listener, θύρα ή socket** — τίποτε
προσβάσιμο από άλλο μηχάνημα και τίποτε που να χρειάζεται έλεγχο ταυτότητας, επειδή δεν υπάρχει
απομακρυσμένο σημείο πρόσβασης. Το STDOUT μεταφέρει μόνο κίνηση πρωτοκόλλου· όλα τα
διαγνωστικά πηγαίνουν στο STDERR (ο πελάτης τα καταγράφει, π.χ. το Claude Desktop τα γράφει
στο `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`), ώστε μια άσχετη γραμμή καταγραφής
να μην μπορεί να αλλοιώσει τη ροή.

::: warning Αντιμετωπίστε τον διακομιστή με ενεργές αλλαγές ως πρόσβαση εγγραφής στη βάση σας
Το `allow_edits` επιτρέπει σε συνδεδεμένο βοηθό να αλλάζει γραμμές SEO στη βάση δεδομένων όπου
εκτελείται η εντολή. Συνδέστε τον σε τοπικό περιβάλλον/staging όσο πειραματίζεστε, περιορίστε τη
λίστα επιτρεπόμενων και απενεργοποιήστε ξανά τις αλλαγές όταν τελειώσετε. Ο κεντρικός διακόπτης
`'enabled' => false` εμποδίζει εντελώς την εκκίνηση της εντολής. Η τοπική μεταφορά stdio
δεν εμποδίζει τον πελάτη AI να στείλει αποτελέσματα εργαλείων στον δικό του πάροχο·
εξετάστε και τις ρυθμίσεις δεδομένων αυτού του πελάτη.
:::

## Ρυθμίσεις {#configuration}

```php
// config/seo-pro.php
'mcp' => [
    'enabled'     => true,           // master switch; the command refuses to run when false
    'allow_edits' => false,          // expose + permit the ops tools + seo_save_meta
    'models'      => [],             // allowlist; [] = fall back to audit/sitemap models
    'server_name' => 'rankbeam-seo', // reported in the MCP initialize handshake

    // Optional Server Card discovery route (off by default) — see below.
    'server_card' => [
        'enabled'     => false,      // serve GET {path} with the discovery card
        'path'        => '.well-known/mcp/server-card.json',
        'name'        => null,       // reverse-DNS server name (null = derived from app.url)
        'schema_url'  => 'https://modelcontextprotocol.io/schemas/draft/server-card.json',
        'website_url' => null,       // optional homepage/docs URL stamped on the card
    ],
],
```

## Server Card (ανακάλυψη) — πειραματική, σχέδιο προδιαγραφής {#server-card-discovery-—-experimental-draft-spec}

Μια **Server Card** MCP είναι μικρό έγγραφο JSON σε γνωστή URL που επιτρέπει σε έναν
πελάτη να ανακαλύψει διακομιστή — το όνομα, την έκδοση και τις δυνατότητές του — πριν
συνδεθεί. Το Rankbeam μπορεί να εξυπηρετεί μία για τον ιστότοπό σας, γνωστοποιώντας στα εργαλεία πρακτόρων
ότι *«αυτός ο ιστότοπος έχει διακομιστή MCP με τον οποίο μπορείτε να επικοινωνήσετε»*. Είναι **ανενεργή από προεπιλογή** και
αποκλειστικά προσθετική — η ενεργοποίησή της δεν αλλάζει τίποτε άλλο.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Όταν είναι ενεργή, το `GET /.well-known/mcp/server-card.json` επιστρέφει κάρτα όπως:

```json
{
  "$schema": "https://modelcontextprotocol.io/schemas/draft/server-card.json",
  "name": "com.example/rankbeam-seo",
  "version": "1.0.0",
  "title": "Rankbeam SEO MCP server",
  "description": "Read — and optionally edit — this site's SEO over the Model Context Protocol…",
  "_meta": {
    "io.rankbeam.seo/transport": "stdio",
    "io.rankbeam.seo/launch": "php artisan seo-pro:mcp",
    "io.rankbeam.seo/tool_count": 10,
    "io.rankbeam.seo/tools": [ { "name": "seo_resolve", "description": "…" } ]
  }
}
```

Η κάρτα παραθέτει μόνο τα **τρέχοντα ενεργά** εργαλεία, οπότε ένας διακομιστής μόνο για ανάγνωση δεν
γνωστοποιεί μέσω αυτής τα εργαλεία λειτουργιών/επεξεργασίας που απαιτούν ενεργοποίηση.

::: warning Ακολουθεί σχέδιο προδιαγραφής
Αυτό ακολουθεί την **προτεινόμενη** προδιαγραφή MCP Server Card
([SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127),
ανοιχτή πρόταση, μη συγχωνευμένη στις 10 Σεπτεμβρίου 2026). Η γνωστή διαδρομή, η URL `$schema` και το ακριβές σύνολο πεδίων
**δεν έχουν οριστικοποιηθεί** — επομένως όλα ρυθμίζονται (`path`, `schema_url`, `name`,
`website_url`). Αυτός ο διακομιστής λειτουργεί μέσω **stdio** (`php artisan seo-pro:mcp`), οπότε
η κάρτα δεν έχει τμήμα HTTP `remotes` — είναι ένδειξη ανακάλυψης, όχι
endpoint HTTP στο οποίο συνδέεστε. Επιβεβαιώστε τη διαδρομή και τη μορφή με τον πελάτη σας
πριν βασιστείτε σε αυτή και αφήστε την ανενεργή αν δεν τη χρειάζεστε.
:::

## Σημειώσεις πρωτοκόλλου {#protocol-notes}

Ένας διακομιστής MCP μόνο με εργαλεία είναι μικρό σύνολο λειτουργιών JSON-RPC 2.0, το οποίο εδώ υλοποιείται
απευθείας: `initialize` (διαπραγμάτευση έκδοσης και χειραψία δυνατοτήτων),
`tools/list`, `tools/call` και `ping`. Δηλώνει την
έκδοση πρωτοκόλλου `2025-06-18` (και κατανοεί τις `2025-03-26` και `2024-11-05`),
επιστρέφει `-32601` για άγνωστες μεθόδους και `-32700` για κακοσχηματισμένες γραμμές και
επιστρέφει αποτυχία **εργαλείου** ως αποτέλεσμα `isError` που μπορεί να διαβάσει ο βοηθός — όχι ως
σφάλμα μεταφοράς. Οι ειδοποιήσεις (μήνυμα χωρίς `id`, όπως
`notifications/initialized`) σωστά δεν λαμβάνουν απάντηση.

## Headless χρήση / επέκταση {#headless-extending}

Το `SeoPro::mcp()` επιστρέφει το μητρώο εργαλείων, ώστε να εξετάζετε τα διαθέσιμα
εργαλεία ή να καταχωρίζετε δικά σας:

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Ένα προσαρμοσμένο εργαλείο υλοποιεί το `McpTool` (`name`, `description`, `inputSchema`,
`isEnabled`, `handle`) — επεκτείνετε το `AbstractTool` για να επαναχρησιμοποιήσετε την επίλυση
με λίστα επιτρεπόμενων μοντέλων, ώστε το εργαλείο σας να κληρονομεί τις ίδιες εγγυήσεις ασφάλειας με τα ενσωματωμένα.

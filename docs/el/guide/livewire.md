---
description: "Χρησιμοποιήστε τις ανεξάρτητες από framework οδηγίες @seo του Rankbeam σε εφαρμογές Livewire — παράγουν απλό HTML στο head και λειτουργούν σε στοιχεία πλήρους σελίδας και layouts Blade όπως στο Blade."
---

# Livewire {#livewire}

Οι οδηγίες Blade `@seo` είναι ανεξάρτητες από framework — παράγουν απλό HTML στο
`<head>`, επομένως λειτουργούν σε οποιαδήποτε εφαρμογή Livewire όπως ακριβώς στο Blade.

## Αρχική απόδοση (πλήρους σελίδας) {#initial-full-page-render}

Σε ένα **στοιχείο Livewire πλήρους σελίδας** (διαδρομή που επιστρέφει ένα στοιχείο), ή σε οποιοδήποτε
layout Blade που περιβάλλει στοιχεία Livewire, η `@seo` λειτουργεί ακριβώς όπως στον
[οδηγό Blade](/el/guide/blade):

```blade
{{-- layouts/app.blade.php --}}
<head>
    @seo($post ?? null)
</head>
```

Η πρώτη απόκριση HTTP περιέχει ολόκληρο το head που βλέπουν οι ανιχνευτές — τίτλο,
περιγραφή, canonical, Open Graph, Twitter και JSON-LD. Αυτή είναι η διαδρομή που
βλέπουν οι ανιχνευτές και τα εργαλεία συλλογής προεπισκοπήσεων κοινωνικών δικτύων, και είναι πλήρως σωστή.

## Η ιδιαιτερότητα του `wire:navigate` {#the-wire-navigate-caveat}

Το [`wire:navigate`](https://livewire.laravel.com/docs/navigate) του Livewire μετατρέπει
τα κλικ σε συνδέσμους σε επισκέψεις τύπου SPA. Σε μια τέτοια επίσκεψη το Livewire αντικαθιστά το `<body>`
και **συγχωνεύει το `<head>`** — με μια σημαντική ασυμμετρία για ένα πακέτο
SEO:

- Τα **`<title>` και `<meta>`/`<link>`** συγχωνεύονται από το head της νέας σελίδας, οπότε
  ο επιλυμένος τίτλος και τα μεταδεδομένα συνήθως ενημερώνονται.
- Το **`<script>` αντιμετωπίζεται ως πόρος που δεν αφαιρείται.** Το Livewire διατηρεί κάθε
  `<script>` που έχει συναντήσει, ώστε η επανεκτέλεσή τους να μην μπορεί να διαταράξει τη JavaScript σας. Αυτό
  σημαίνει ότι τα **μπλοκ JSON-LD `<script>` συσσωρεύονται**: αφού επισκεφθείτε τρεις
  αναρτήσεις, τα schema και των τριών βρίσκονται ταυτόχρονα στο head, και ένα εργαλείο που διαβάζει
  δομημένα δεδομένα βλέπει λανθασμένες (ή πολλαπλές) οντότητες.

Για να είναι δυνατός ο καθαρισμός, ο renderer **επισημαίνει κάθε script JSON-LD** που παράγει:

```html
<script type="application/ld+json" data-seo-schema
        data-seo-url="https://example.com/blog/the-post"> … </script>
```

## Ενσωματώστε τον καθαρισμό JSON-LD {#ship-the-json-ld-cleanup}

Προσθέστε αυτό μία φορά (π.χ. στο ριζικό layout, μετά το `@livewireScripts`). Σε κάθε
`wire:navigate`, διατηρεί μόνο το schema της **τρέχουσας σελίδας** και αφαιρεί
τα παρωχημένα:

```blade
<script>
    document.addEventListener('livewire:navigated', () => {
        // The page we are now on. data-seo-url is the canonical (query-stripped),
        // so compare on the query-stripped location.
        const here = window.location.href.split('#')[0].split('?')[0]

        // Keep only the LAST schema for this page; remove every other-URL
        // (stale) script AND same-URL duplicates Livewire re-adds when a page is
        // revisited — including clearing a lone stale script when this page has
        // none. Iterate from the end so the freshest copy is the one kept.
        const scripts = [...document.querySelectorAll('script[data-seo-schema]')]
        let kept = false
        for (let i = scripts.length - 1; i >= 0; i--) {
            const url = (scripts[i].getAttribute('data-seo-url') || '').split('?')[0]
            if (url === here && !kept) { kept = true; continue }
            scripts[i].remove()
        }
    })
</script>
```

Αυτό βασίζεται μόνο στον δείκτη `data-seo-schema` και στο αναγνωριστικό ανά URL που παράγει
ήδη ο renderer — χωρίς ρύθμιση ανά σελίδα.

::: warning Συγκρίνετε με την τρέχουσα URL, όχι με το script που προστέθηκε τελευταίο
Μια παλαιότερη έκδοση αυτού του αποσπάσματος τερμάτιζε όταν υπήρχαν λιγότερα από δύο scripts schema
και θεωρούσε ότι το script που *προστέθηκε τελευταίο* ανήκε στην τρέχουσα σελίδα. Αυτό
αφήνει ένα παρωχημένο schema στο head όταν μεταβαίνετε από σελίδα **με** JSON-LD
σε σελίδα **χωρίς** JSON-LD (υπάρχει μόνο το παλιό script, οπότε η πρόωρη επιστροφή
το διατηρεί), και δεν μπορεί να αφαιρέσει ένα **διπλότυπο της ίδιας URL** που προσθέτει ξανά το Livewire όταν
επιστρέφετε σε μια σελίδα. Η σύγκριση κάθε `data-seo-url` με το `window.location` και
η διατήρηση μόνο της **τελευταίας** αντιστοιχίας αφαιρεί τόσο τα παρωχημένα schema *όσο και* τα διπλότυπα σε κάθε
περίπτωση — αυτό επαληθεύουν η εφαρμογή Livewire `rankbeam-examples` και η δοκιμή της
στον browser.
:::

::: tip Μοναδικές μετα-ετικέτες κατά την πλοήγηση SPA
Η συγχώνευση head του Livewire αποτρέπει συνήθως την παραμονή παρωχημένων μοναδικών ετικετών `<meta>`/`<link>`,
αλλά η ακριβής συμπεριφορά εξαρτάται από την έκδοση του Livewire και τη δομή
του layout σας. Σε σελίδες όπου η ορθότητα των μεταδεδομένων για τους ανιχνευτές είναι κρίσιμη,
προτιμήστε **πλήρη επαναφόρτωση σελίδας** (απλό σύνδεσμο χωρίς `wire:navigate`) ή
**απόδοση στον διακομιστή**, ώστε η πρώτη απόκριση HTTP να είναι η έγκυρη πηγή. Η
εφαρμογή Livewire [`rankbeam-examples`](https://github.com/rankbeam) δοκιμάζει μια πραγματική ροή
`wire:navigate` στον browser για να επαληθεύει αυτή τη συμπεριφορά.
:::

## Filament {#filament}

Το Filament βασίζεται εσωτερικά στο Livewire, αλλά είναι ένα **περιβάλλον διαχείρισης περιεχομένου** —
τροποποιεί το `seo_meta` και δεν αποδίδει ποτέ το head του δημόσιου frontend σας. Δείτε τον
[οδηγό Filament](/el/guide/filament)· τίποτα από τα παραπάνω δεν εφαρμόζεται στον πίνακα διαχείρισης.

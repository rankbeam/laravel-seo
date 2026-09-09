<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| rankbeam/laravel-seo — Ελληνικά
|--------------------------------------------------------------------------
|
| Πρώτη έκδοση: Claude (2026-09-07), αυτόματη μετάφραση. Δεν έχει γίνει
| ακόμη έλεγχος από φυσικό ομιλητή — βλ. TRANSLATING.md. Τα κλειδιά είναι
| κωδικοί και δεν μεταφράζονται· τα placeholders (:length, :max, …) μένουν ως έχουν.
|
*/

return [
    'cli' => [
        'audit' => [
            'no_models' => 'Δεν υπάρχουν μοντέλα για έλεγχο.',
            'model_hint' => 'Χρησιμοποιήστε --model="App\\Models\\Post" ή ρυθμίστε seo.audit.models ή seo.sitemap.models στο config/seo.php.',
            'skipped' => 'Παραλείφθηκε το μοντέλο :model: :reason',
            'no_pages' => 'Δεν βρέθηκαν σελίδες για έλεγχο.',
            'page' => 'Σελίδα',
            'status' => 'Κατάσταση',
            'findings' => 'Ευρήματα',
            'all_passed' => 'Δεν βρέθηκαν προβλήματα: όλες οι σελίδες πέρασαν τον έλεγχο.',
            'page_summary' => 'Σελίδες: :pages · επιτυχία: :passed · με προειδοποιήσεις: :warned · αποτυχία: :failed',
            'issue_summary' => 'Προβλήματα: :issues · κρίσιμα: :critical · προειδοποιήσεις: :warning · ειδοποιήσεις: :notice',
            'guard_title' => 'ΠΡΟΣΤΑΣΙΑ ΕΥΡΕΤΗΡΙΑΣΗΣ ΕΝΕΡΓΗ',
            'guard_environment' => 'Το περιβάλλον ":environment" δεν περιλαμβάνεται στο seo.indexing_guard.allowed_environments (:allowed).',
            'guard_explanation' => 'Κάθε σελίδα χρησιμοποιεί :directive και το διαχειριζόμενο robots.txt αποκλείει τους ανιχνευτές. Χρησιμοποιήστε επιτρεπόμενο περιβάλλον παραγωγής ή ορίστε SEO_INDEXING_GUARD=false.',
            'coverage' => 'Κάλυψη',
            'coverage_core' => 'Ελέγχονται εδώ (μοντέλο και resolver, χωρίς αιτήματα): παρουσία/μήκος τίτλου και περιγραφής, εικόνα OG, συγκρούσεις robots, μορφή/τομέας/κοινή χρήση/ασφάλεια canonical URL και κύρια λέξη-κλειδί.',
            'coverage_pro' => 'Απαιτείται σάρωση Pro (αποδομένο HTML ή εξωτερικά αιτήματα): H1, alt εικόνων, ανεπαρκές περιεχόμενο, μικτό περιεχόμενο και έλεγχοι canonical URL στο διαδίκτυο. Η βαθμολογία 0–100 είναι επίσης λειτουργία Pro.',
        ],
    ],
    'audit' => [
        'missing_title' => 'Η σελίδα δεν έχει ετικέτα title.',
        'missing_description' => 'Η σελίδα δεν έχει meta περιγραφή.',
        'missing_og_image' => 'Η σελίδα δεν έχει εικόνα Open Graph.',
        'missing_focus_keyword' => 'Δεν έχει οριστεί λέξη-κλειδί εστίασης για αυτή τη σελίδα.',
        'title_too_long' => 'Ο τίτλος έχει :length χαρακτήρες (συνιστώμενο μέγιστο :max)· μπορεί να περικοπεί στο Google.',
        'title_too_short' => 'Ο τίτλος έχει μόνο :length χαρακτήρες (συνιστώμενο ελάχιστο :min).',
        'description_too_long' => 'Η περιγραφή έχει :length χαρακτήρες (συνιστώμενο μέγιστο :max)· μπορεί να περικοπεί.',
        'description_too_short' => 'Η περιγραφή έχει μόνο :length χαρακτήρες (συνιστώμενο ελάχιστο :min).',
        'duplicate_title' => 'Ο τίτλος «:title» χρησιμοποιείται και σε :count ακόμη σελίδα(-ες).',
        'duplicate_description' => 'Η meta περιγραφή επαναλαμβάνεται σε :count ακόμη σελίδα(-ες).',
        'robots_conflict_indexing' => 'Το robots meta περιέχει αντικρουόμενες οδηγίες index/noindex.',
        'robots_conflict_following' => 'Το robots meta περιέχει αντικρουόμενες οδηγίες follow/nofollow.',
        'noindex_warning' => 'Η σελίδα έχει noindex, αλλά φαίνεται να είναι σημαντικό περιεχόμενο.',
        'invalid_canonical' => 'Το canonical URL δεν έχει έγκυρη μορφή URL.',
        'cross_domain_canonical' => 'Το canonical URL δείχνει σε διαφορετικό domain.',
        'insecure_canonical' => 'Το canonical URL χρησιμοποιεί http:// σε ιστότοπο https.',
        'shared_canonical' => ':count σελίδες μοιράζονται το ίδιο canonical URL.',
        'aeo_missing_author' => 'Ένα άρθρο σε αυτή τη σελίδα δεν έχει συντάκτη στα δομημένα δεδομένα του. Η δήλωση συντάκτη κάνει ρητή στο schema την πατρότητα και την προέλευση του άρθρου.',
        'aeo_article_missing_date' => 'Ένα άρθρο σε αυτή τη σελίδα δεν έχει ημερομηνία δημοσίευσης στα δομημένα δεδομένα του. Ένα datePublished ή dateModified κάνει ρητό στο schema το χρονικό πλαίσιο του άρθρου.',
        'hreflang_invalid_code' => 'Οι εναλλακτικές hreflang περιέχουν κωδικό που οι μηχανές αναζήτησης θα αγνοήσουν (:codes). Χρησιμοποιήστε τη μορφή language[-Script][-REGION], π.χ. en, pt-BR, zh-Hant.',
        'hreflang_duplicate_code' => 'Οι εναλλακτικές hreflang αναφέρουν τον ίδιο κωδικό περισσότερες από μία φορές (:codes).',
        'hreflang_missing_self' => 'Οι εναλλακτικές hreflang δεν περιλαμβάνουν την ίδια τη σελίδα. Το Google απαιτεί κάθε γλωσσική έκδοση να αναφέρει και το δικό της URL.',
    ],
    'warnings' => [
        'title_too_long' => 'Ο τίτλος έχει μήκος :length χαρακτήρων (συνιστώμενο μέγιστο: :max). Μπορεί να περικοπεί στο Google.',
        'title_is_fallback' => 'Δεν έχει οριστεί τίτλος SEO — θα χρησιμοποιηθεί εναλλακτικά ο τίτλος του περιεχομένου.',
        'description_too_long' => 'Η περιγραφή έχει μήκος :length χαρακτήρων (συνιστώμενο μέγιστο: :max). Μπορεί να περικοπεί.',
        'description_is_fallback' => 'Δεν έχει οριστεί περιγραφή SEO — θα δημιουργηθεί αυτόματα από το περιεχόμενο.',
        'no_image' => 'Δεν υπάρχει διαθέσιμη εικόνα για τις προεπισκοπήσεις στα social. Προσθέστε μια εικόνα SEO ή μια εικόνα περιεχομένου.',
        'image_is_fallback' => 'Δεν έχει οριστεί ειδική εικόνα SEO — θα χρησιμοποιηθεί εναλλακτικά η εικόνα του περιεχομένου.',
        'image_too_small' => 'Η εικόνα είναι πολύ μικρή (:widthx:height). Οι πλατφόρμες social απαιτούν τουλάχιστον :min_widthx:min_height px.',
        'image_not_ideal' => 'Η εικόνα είναι :widthx:height px. Το ιδανικό μέγεθος για τις πλατφόρμες social είναι :ideal_widthx:ideal_height px.',
    ],
    'status' => [
        'pass' => 'Επιτυχία',
        'warn' => 'Προειδοποίηση',
        'fail' => 'Αποτυχία',
        'skipped' => 'Παραλείφθηκε',
    ],
    'severity' => [
        'critical' => 'Κρίσιμο',
        'warning' => 'Προειδοποίηση',
        'notice' => 'Επισήμανση',
    ],
];

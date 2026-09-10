---
description: "Le registre stable des codes de problème du scan Pro : chaque code possède une gravité et un champ fixes, pour que tableaux de bord et exports lisent les codes plutôt que les messages."
---

# Problèmes de scan : le registre des codes {#scan-issues-—-the-issue-code-registry}

Chaque problème signalé par le scan Pro possède un **code stable** issu d’un registre unique, `Rankbeam\Seo\Pro\Scanning\IssueRegistry`. Les scanners n’inventent jamais de code à la volée : ils construisent chaque problème avec `IssueRegistry::make()`, qui applique la gravité et le champ du registre et **rejette les codes non définis**. Le catalogue ci-dessous constitue donc un contrat : tableaux de bord, exports et [score Pro](/fr/pro/scoring) lisent les codes plutôt que d’analyser les messages. Le [`seo:audit`](/fr/guide/audit) gratuit utilise son propre registre de métadonnées dans le cœur, avec un périmètre plus restreint et certains codes hreflang différents.

Chaque code porte :

- **id** : chaîne stable enregistrée dans `seo_scan_issues.issue_type`.
- **severity** : `critical`, `warning` ou `notice`, **fixe par code**. Un code est scindé si nécessaire plutôt que de lui attribuer plusieurs gravités.
- **field** : champ `seo_meta` concerné, ou _page_ pour les problèmes de page.
- **classe d’exécution** : données nécessaires à sa détection, ci-dessous.
- **preuves** : clés du tableau `context` du problème.

## Classes d’exécution {#execution-classes}

Chaque contrôle appartient à exactement une de ces classes selon ses besoins :

| Classe | Nécessite | Exécution |
|---|---|---|
| **metadata** | Modèle et résolveur du cœur, sans récupération de page | Scan de modèle, `PageScanner` ; le [`seo:audit`](/fr/guide/audit) gratuit couvre une partie de ces contrôles |
| **rendered** | HTML servi par la page, requête interne au noyau ou récupération externe | Scan d’URL, `UrlScanner` |
| **network** | Requête **sortante** pour valider une cible _distincte_, par exemple une canonique vers une autre page | Scan d’URL, **toujours via `SsrfGuard`** |

Un audit gratuit dans le processus ne peut donc pas couvrir tout le scan Pro. Seuls les codes **metadata** sont calculables sans rendre une page ; la chaîne Pro récupère le HTML rendu et valide les cibles canoniques sur le réseau. Filtrez le registre par classe avec `IssueRegistry::byClass(IssueRegistry::EXEC_METADATA)`.

## Codes de métadonnées {#metadata-codes}

Détectés depuis le modèle et le résolveur par `PageScanner`. `missing_title`, `missing_description` et les codes de longueur sont aussi émis par le scan d’URL rendue, qui mesure le `<head>` servi : mêmes codes, même sens.

| Code | Gravité | Champ | Preuves | Signification |
|---|---|---|---|---|
| `missing_title` | critical | title | — | Aucun titre ni repli calculable. |
| `missing_description` | warning | description | — | Aucune meta description ni repli calculable. |
| `missing_og_image` | notice | og_image | — | Aucune image Open Graph ni repli calculable. |
| `missing_focus_keyword` | notice | focus_keywords | — | Aucun mot-clé principal défini. |
| `duplicate_title` | warning | title | `title`, `duplicate_urls` | Titre réutilisé sur d’autres pages de la même locale. |
| `duplicate_description` | warning | description | `description`, `duplicate_urls` | Description réutilisée sur d’autres pages de la même locale. |
| `title_too_long` | warning | title | `length`, `max`, `script` | Titre résolu au-dessus de la longueur conseillée pour l’écriture : 60 en latin, environ 30 en CJK. |
| `title_too_short` | notice | title | `length`, `min`, `script` | Titre résolu sous le seuil de l’écriture : 30 en latin, environ 15 en CJK. |
| `description_too_long` | warning | description | `length`, `max`, `script` | Description résolue au-dessus de la longueur conseillée : 160 / environ 80. |
| `description_too_short` | notice | description | `length`, `min`, `script` | Description résolue sous le seuil : 70 / environ 35. |
| `robots_conflict_indexing` | critical | robots | `robots` | Directive robots contenant à la fois `index` et `noindex`. |
| `robots_conflict_following` | warning | robots | `robots` | Directive robots contenant à la fois `follow` et `nofollow`. |
| `noindex_warning` | warning | robots | `robots`, `canonical`, `page_url`, `shipping_signal` | Page `noindex` avec canonique autoréférente : heuristique à examiner, pas preuve qu’elle doit être indexée. Émis sur les scans de modèle et d’URL rendue. |
| `invalid_canonical` | critical | canonical | `canonical` | Valeur canonique qui n’est pas une URL valide. |
| `cross_domain_canonical` | warning | canonical | `canonical`, `page_url` | Canonique vers un autre hôte que celui de la page. |
| `shared_canonical` | notice | canonical | `canonical` | Plusieurs pages déclarent la même canonique. |
| `insecure_canonical` | warning | canonical | `canonical` | Canonique `http://` sur un site `https`. |
| `hreflang_invalid_code` | warning | alternates | `invalid_codes` | Variante hreflang dont la valeur n’est ni `x-default` ni un code de langue BCP-47 valide. |
| `hreflang_missing_self_reference` | warning | alternates | `locale`, `page_url` | Variantes déclarées, mais aucune ne référence la locale de la page elle-même. |
| `hreflang_duplicate_code` | warning | alternates | `duplicate_codes` | Même code hreflang associé à plusieurs URL, groupe ambigu. |
| `hreflang_missing_x_default` | notice | alternates | `languages` | Groupe hreflang multilingue sans repli `x-default`. |
| `aeo_missing_author` | notice | schema | — | Article dans les données structurées sans entité auteur ; attribution ou provenance non explicite dans le schéma. |
| `aeo_article_missing_date` | notice | schema | — | Article dans les données structurées sans date de publication/modification ; chronologie non explicite dans le schéma. |

Les seuils de longueur proviennent de la [politique du cœur par système d’écriture](/fr/guide/multilingual#title-and-description-budgets-per-script), utilisée depuis Pro 2.33 : 60/160 pour le latin, environ 30/80 pour le CJK, comptés en graphèmes. Le scan reste ainsi cohérent avec les compteurs de l’éditeur. Les bornes inférieures, titre 30 et description 70 en latin, environ la moitié en CJK, sont les seuils de sous-optimisation du scan. La clé `script` indique la catégorie appliquée. La mesure porte sur le titre et la description **résolus**, réellement rendus, replis et suffixe de titre compris.

Les codes `hreflang_*` valident les variantes déclarées dans `alternates` du résolveur : codes invalides ou en double, autoréférence absente et absence de `x-default` dans un groupe multilingue. Ils ne s’exécutent que si la page déclare des variantes. Ces contrôles de métadonnées ne vérifient pas la **réciprocité** entre pages ; le contrôle réseau facultatif ci-dessous récupère l’autre page.

Les codes `aeo_*` sont des signaux de **préparation aux réponses, AEO** : le contenu de l’article est-il explicite dans les données structurées ? Ils lisent le graphe JSON-LD résolu et se déclenchent **uniquement** s’il déclare un type article, `Article`, `BlogPosting`, `NewsArticle`, etc., sans entité `author` ou sans `datePublished` / `dateModified`. Une page sans article n’est jamais signalée. Ils dépendent de `seo-pro.scan.checks.aeo`, activé par défaut, et correspondent aux contrôles du [`seo:audit`](/fr/guide/audit) gratuit.

::: tip `missing_focus_keyword` dépend d’une activation
La remarque sur le mot-clé principal ne se déclenche que si son suivi est activé dans le **cœur** : `seo.keywords.enabled`, `false` par défaut. Sinon, le scan ne signale pas une page sans mot-clé. Le [`seo:audit`](/fr/guide/audit) gratuit et l’éditeur Filament lisent le **même** indicateur. Scan, audit et rappel de l’éditeur restent donc cohérents, avec une seule condition d’activation.
:::

## Codes du rendu {#rendered-codes}

Détectés par `UrlScanner` dans le HTML servi : requête interne au noyau pour le même hôte, sans trafic sortant, ou récupération protégée pour une cible externe.

| Code | Gravité | Champ | Preuves | Signification |
|---|---|---|---|---|
| `http_error` | critical | page | `status` | L’URL renvoie un statut 4xx/5xx. |
| `empty_response` | critical | page | — | L’URL renvoie un corps vide. |
| `missing_canonical` | notice | canonical | — | Aucun `<link rel="canonical">` dans le head rendu. |
| `noindex_page` | notice | robots | `robots` | Page rendue `noindex`, information. Avec une **canonique autoréférente**, elle produit plutôt `noindex_warning`, pris en compte dans le score. |
| `missing_h1` | notice | page | — | Aucun titre `<h1>`. |
| `multiple_h1` | notice | page | `count` | Plusieurs `<h1>`, information. |
| `missing_image_alt` | warning | page | `count`, `total`, `sample` | Images de contenu sans attribut `alt`. Un `alt=""` explicite est considéré comme décoratif et n’est pas signalé. |
| `thin_content` | notice | page | `word_count`, `threshold`, `segmenter` | Texte sous le nombre de mots configuré. Le tokenizer de la liste de contrôle utilise les espaces pour les écritures espacées et la segmentation par dictionnaire ICU, `segmenter: intl`, avec ext-intl, pour chinois, japonais et thaï. Un article japonais de 400 mots ne devient donc pas un seul « mot ». |
| `mixed_content` | warning | page | `count`, `sample` | Sous-ressources `http://` dans une page `https`. |
| `html_lang_missing` | notice | page | — | `<html lang>` absent ou vide ; une technologie d’assistance peut choisir une voix inadaptée. |
| `html_lang_invalid` | notice | page | `declared` | `lang` n’est pas une balise BCP-47 valide : `english`, `en_US` avec trait de soulignement, `jp`. |
| `html_lang_mismatch` | warning | page | `declared`, `declared_script`, `detected_script` | Écriture du corps incompatible avec la langue déclarée, par exemple `lang="en"` sur une page japonaise ou `lang="ru"` sur du texte latin. Contrôle au niveau de l’écriture seulement : le scan ne devine pas une autre langue latine. Nécessite au moins 40 lettres de contenu. |

## Codes réseau {#network-codes}

Détectés par `UrlScanner` uniquement avec l’option correspondante : `seo-pro.scan.url_checks.check_canonical_target` pour la cible canonique, `check_hreflang_reciprocity` pour les variantes hreflang. Chaque cible passe **par `SsrfGuard`** : protocoles autorisés, périmètre d’hôtes, rejet des IP privées et budgets de redirection, temps et taille. Les redirections ne sont **pas suivies**, pour rendre visible une cible canonique qui redirige. Canonique et variante autoréférentes sont ignorées, puisque la page vient d’être récupérée.

| Code | Gravité | Champ | Preuves | Signification |
|---|---|---|---|---|
| `blocked_url` | notice | page | `reason` | Cible refusée par `SsrfGuard` avant tout appel HTTP. |
| `canonical_target_broken` | critical | canonical | `canonical`, `status` | Canonique vers une page renvoyant une erreur HTTP. |
| `canonical_target_redirect` | warning | canonical | `canonical`, `status`, `location` | Canonique vers une page qui redirige ; utiliser l’URL finale. |
| `canonical_target_noindex` | warning | canonical | `canonical` | Canonique vers une page elle-même `noindex`. |
| `canonical_target_blocked` | notice | canonical | `canonical`, `reason` | Cible canonique non vérifiable, refus du garde ou résolution impossible. |
| `hreflang_not_reciprocal` | warning | alternates | `hreflang`, `href`, `status` | Une variante déclarée ne référence pas la page en retour. La paire hreflang peut être ignorée ; cela ne rend pas en soi la traduction non indexable. |
| `hreflang_target_unverified` | notice | alternates | `hreflang`, `href`, `reason` | Variante non récupérable : refus, statut d’erreur, redirection ou dépassement de taille. Réciprocité non vérifiée ; absence de preuve, pas défaut confirmé. |

La réciprocité récupère au maximum `hreflang_max_alternates` cibles par page, 10 par défaut, `x-default` compris, hors doublons et page elle-même. Les codes de métadonnées `hreflang_*` ci-dessus valident la liste *déclarée* ; cette exploration est le contrôle qui nécessite l’autre page.

Tous ces chemins réseau réutilisent `SsrfGuard`. Consultez [SECURITY.md](https://github.com/rankbeam/laravel-seo-pro/blob/master/SECURITY.md) pour le modèle de menace et la limite résiduelle TOCTOU.

## Contribution des codes au score {#how-codes-feed-the-score}

Le [score SEO Pro](/fr/pro/scoring) vaut `100 −` une pénalité fixe par problème comptabilisé, pondérée selon les gravités ci-dessus. La plupart des codes comptent. Certains sont volontairement exclus : `missing_focus_keyword`, conseil ; `noindex_page` et `multiple_h1`, informations ; `blocked_url`, `canonical_target_blocked` et `hreflang_target_unverified`, impossibilité de vérifier plutôt que défaut ; enfin les codes `hreflang_*`, `html_lang_*` et `aeo_*`, signaux de conseil exclus du score pour le moment. La [page du score](/fr/pro/scoring) donne la liste complète et la pénalité de chaque code.

## Cycle de vie des problèmes {#issue-lifecycle}

Un problème n’est pas une ligne temporaire supprimée lorsque le défaut disparaît. Le scan **réconcilie** les problèmes de chaque cible au lieu de les supprimer et recréer. Chaque problème possède une identité stable : cible, `scannable_type` + `scannable_id` pour un modèle ou `url` pour une route/sitemap, et `issue_type`. Chaque code est émis au plus une fois par cible et par scan. Les codes regroupant plusieurs occurrences, `missing_image_alt`, `mixed_content`, `hreflang_*`, etc., les réunissent dans une ligne avec `count` / `sample`, ce qui garantit l’unicité.

À chaque scan, pour chaque cible :

- un résultat **sans ligne existante** est créé en `open`, avec `detected_at` ;
- un résultat **correspondant à une ligne ouverte** actualise les preuves et conserve le `detected_at` initial : la première observation reste stable au lieu d’être réinitialisée ;
- un problème ouvert qu’un contrôle terminé **ne retrouve plus** devient **`fixed`**, avec `resolved_at`. La ligne est **conservée**, pas supprimée, pour enregistrer la correction ;
- un problème **`fixed` qui revient** est **rouvert** sur la même ligne, avec nouveau `detected_at` : c’est une régression ;
- un problème marqué **`ignored`** par un utilisateur dans le tableau de bord reste inchangé.

| Statut | Signification | Défini par |
|---|---|---|
| `open` | Actuellement présent. | Scan, nouveau ou toujours détecté |
| `fixed` | Était présent, n’est plus détecté. | Scan automatique suivant qui ne le retrouve plus |
| `ignored` | Masqué par un utilisateur ; exclu des comptes ouverts et du score. | Action Ignore du tableau de bord |

Les corrections étant conservées, le [rapport en marque blanche](/fr/pro/reports) peut afficher de **vrais nombres de problèmes corrigés et nouveaux** sur une période, plutôt qu’une simple différence d’instantanés de rapports. Les consommateurs des comptes ouverts, tableau de bord, [`seo-pro:scan-status`](/fr/pro/headless) et [score](/fr/pro/scoring), filtrent tous sur `open`. Les lignes `fixed` conservées ne les gonflent donc pas. Ces lignes sont rattachées à l’exécution qui les a résolues et suivent la [rétention](/fr/pro/production) normale des scans.

## Configuration {#configuration}

```php
// config/seo-pro.php → 'scan'
'url_checks' => [
    'enabled' => true,
    'crawl_external' => false,             // fetch external URL targets (guarded)
    'check_canonical_target' => false,     // EXEC_NETWORK canonical validation (guarded)
    'check_hreflang_reciprocity' => false, // EXEC_NETWORK hreflang link-back crawl (guarded)
    'hreflang_max_alternates' => 10,       // targets fetched per page by that crawl
],
'checks' => [
    'length' => true,            // title/description length (metadata + rendered)
    'rendered_content' => true,  // H1 / alt / thin content / mixed content / html lang
],
'content' => [
    'min_word_count' => 200,     // thin_content threshold
    'evidence_sample' => 5,      // max example URLs stored per issue
],
```

Le budget de taille des réponses récupérées sous protection est `seo-pro.http.max_response_bytes`, 2 Mo par défaut. Le scan interne au processus sur le même hôte n’a pas ce plafond.

## Compatibilité : renommage d’un code {#compatibility-note-issue-code-rename}

L’ancien code unique `robots_conflict`, qui pouvait avoir deux gravités, a été scindé pour donner une seule gravité à chaque code :

| Ancien code | Nouveau code | Gravité |
|---|---|---|
| `robots_conflict`, index + noindex | `robots_conflict_indexing` | critical |
| `robots_conflict`, follow + nofollow | `robots_conflict_following` | warning |

Si vous stockez ou filtrez `robots_conflict`, passez aux deux nouveaux codes.

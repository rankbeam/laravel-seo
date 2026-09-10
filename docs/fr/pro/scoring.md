---
description: "Le score SEO de 0 à 100 du scan Pro est vérifiable et déterministe : chaque point retiré correspond à un problème précis, et les mêmes problèmes produisent toujours le même score."
---

# Le score SEO : transparent, versionné et géré par Pro {#the-seo-score-—-transparent-versioned-pro-owned}

Le scan Pro attribue à chaque page un **score SEO de 0 à 100**, le repère numérique souvent recherché après Rank Math ou Yoast. Ce score est **entièrement vérifiable** : chaque point retiré correspond à un [problème de scan](/fr/pro/scan-issues) précis, et un même ensemble de problèmes produit toujours le même résultat.

```
score = 100 − Σ penalty(issue) for each scored issue   (floored at 0)
```

::: tip Un score, un seul responsable
Le score numérique est une fonctionnalité **Pro**. Il appartient à l’enregistrement `seo_scan_results` de Pro, jamais au `seo_meta` du cœur ; l’ancienne colonne `seo_score` a été retirée dans Core 3. Le [`seo:audit`](/fr/guide/audit) gratuit indique **réussite / avertissement / échec** par page, **sans chiffre**. Le score appartient à l’offre payante.
:::

## Le barème {#the-rubric}

Le score est calculé selon un **barème publié et versionné**, `Rankbeam\Seo\Pro\Scanning\ScoreRubric`. Deux éléments le définissent : une **liste explicite** des codes de problème pris en compte et une **pénalité fixe par gravité**.

| Gravité | Pénalité | Signification |
|---|---|---|
| `critical` | **−40** | Résultat à fort impact selon ce barème. |
| `warning` | **−15** | Résultat à examiner prochainement. |
| `notice` | **−5** | Amélioration facultative. |

La gravité de chaque code est lue directement dans le [registre des problèmes](/fr/pro/scan-issues), source de référence unique. Le barème ne la recalcule jamais. Chaque code possède une gravité fixe pour conserver un score déterministe.

### Ce qui entre dans le score {#what-the-score-counts}

Ces contrôles déterministes sont sélectionnés par le barème produit de Rankbeam. Certains sont des heuristiques nécessitant une interprétation éditoriale. Une erreur critique coûte 40 points, un avertissement 15 et une remarque 5. Le score ne prédit pas les performances dans les moteurs de recherche.

| Code | Gravité | Pénalité |
|---|---|---|
| `missing_title` | critical | −40 |
| `missing_description` | warning | −15 |
| `missing_og_image` | notice | −5 |
| `duplicate_title` | warning | −15 |
| `duplicate_description` | warning | −15 |
| `title_too_long` | warning | −15 |
| `title_too_short` | notice | −5 |
| `description_too_long` | warning | −15 |
| `description_too_short` | notice | −5 |
| `robots_conflict_indexing` | critical | −40 |
| `robots_conflict_following` | warning | −15 |
| `noindex_warning` | warning | −15 |
| `invalid_canonical` | critical | −40 |
| `cross_domain_canonical` | warning | −15 |
| `shared_canonical` | notice | −5 |
| `insecure_canonical` | warning | −15 |
| `http_error` | critical | −40 |
| `empty_response` | critical | −40 |
| `missing_canonical` | notice | −5 |
| `missing_h1` | notice | −5 |
| `missing_image_alt` | warning | −15 |
| `thin_content` | notice | −5 |
| `mixed_content` | warning | −15 |
| `canonical_target_broken` | critical | −40 |
| `canonical_target_redirect` | warning | −15 |
| `canonical_target_noindex` | warning | −15 |

Les codes de métadonnées sont détectés lors d’un scan de modèle ; les codes de rendu ou de réseau le sont uniquement lors d’un scan d’URL. Voir les [classes d’exécution](/fr/pro/scan-issues#execution-classes). Le score d’une cible **modèle** reflète donc les contrôles de métadonnées ; celui d’une cible **URL** reflète la page rendue. Un modèle noté 100 signifie « aucun défaut détecté dans les métadonnées », pas « page rendue parfaite ». Scannez l’URL pour examiner son rendu.

### Ce qui est volontairement exclu du score {#what-the-score-deliberately-does-not-count}

Ces codes du registre sont exclus délibérément. Les exclusions font partie du contrat : un test vérifie que chaque code du registre est soit pris en compte, soit présent ici.

| Code | Motif d’exclusion |
|---|---|
| `missing_focus_keyword` | **Conseil.** Dépend de l’activation facultative de `seo.keywords.enabled`. Une page ne doit pas perdre de points parce qu’elle n’adopte pas les mots-clés principaux, et le score ne doit pas dépendre d’un indicateur de configuration. |
| `noindex_page` | **Information.** `noindex` peut être volontaire et ne constitue pas un défaut de qualité des métadonnées. L’heuristique « noindex avec canonique autoréférente » est prise en compte via `noindex_warning`. |
| `multiple_h1` | **Information.** Google tolère plusieurs H1 ; aucune pénalité pour ce seul motif. |
| `blocked_url` | **Absence de preuve.** `SsrfGuard` a refusé la récupération ; la page n’a donc pas été contrôlée. Ce n’est pas un défaut de la page. |
| `canonical_target_blocked` | **Absence de preuve.** La cible canonique n’a pas pu être vérifiée ; ce n’est pas un défaut de la page. |
| `hreflang_invalid_code`, `hreflang_missing_self_reference`, `hreflang_duplicate_code`, `hreflang_missing_x_default` | **Conseils pour le moment.** Ces codes hreflang Pro apparaissent dans le scan ; l’audit gratuit possède ses propres codes. Ils ne modifient pas encore le score : les y ajouter nécessiterait une nouvelle `VERSION`. |
| `html_lang_missing`, `html_lang_invalid`, `html_lang_mismatch` | **Conseils.** Les contrôles de langue sont exclus de ce barème. |
| `hreflang_not_reciprocal` | **Conseil.** Contrôle facultatif de réciprocité, sans effet sur le score. |
| `hreflang_target_unverified` | **Absence de preuve.** La réciprocité n’a pas pu être vérifiée. |
| `aeo_missing_author`, `aeo_article_missing_date` | **Conseils.** Signaux de préparation aux réponses, AEO : ils signalent un article sans entité auteur ou date de publication dans le scan et l’audit gratuit, sans modifier le score. Les intégrer nécessiterait une nouvelle `VERSION`. |

La densité de mots-clés, les mots percutants et les autres éléments de la [liste de contrôle on-page](/fr/pro/on-page-checklist) n’entrent jamais dans le score. Ce sont des conseils dans une liste distincte de réussite/avertissement/échec, pas des codes du registre.

## Versionnement : les anciens scores ne changent jamais silencieusement {#versioning-—-historical-scores-never-silently-change}

Chaque score enregistré porte la `ScoreRubric::VERSION` qui l’a produit, dans `rubric_version`. Deux conséquences :

- Un **nouveau** code ne compte **aucun point** tant qu’il n’a pas été ajouté explicitement à la liste. Livrer un nouveau contrôle ne peut donc pas modifier rétroactivement un score enregistré. Une modification de la liste ou des poids change le barème et sa version.
- Le score est **enregistré, pas recalculé à la lecture**. Le chiffre vu la semaine dernière reste celui que vous voyez aujourd’hui, avec le barème qui l’explique.

## Stockage {#where-it-s-stored}

Chaque scan crée ou met à jour une ligne par cible dans `seo_scan_results` :

| Colonne | Contenu |
|---|---|
| `scannable_type` / `scannable_id` | Modèle évalué, null pour les cibles URL. |
| `url` | URL évaluée. |
| `score` | Score de 0 à 100. |
| `rubric_version` | Barème utilisé. |
| `penalty_total` | Somme brute des pénalités **avant** application du plancher à 0. |
| `scored_issues` | Nombre de problèmes ayant modifié le score. |
| `breakdown` | `[{code, severity, penalty}, …]`, trace complète du calcul. |
| `keywords_enabled` | État de `seo.keywords.enabled` lors du scan, conservé par transparence ; le score n’en dépend pas. |
| `scan_run_id` | Exécution ayant produit le score ; mis à null, sans supprimer le résultat, lors de la purge d’une exécution. Les scores représentent l’état courant, pas l’historique des scans. |
| `scored_at` | Date du calcul. |

## Lire le score {#reading-the-score}

**Headless** : dernier score d’un modèle :

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;     // e.g. 85
$result?->grade();   // 'A'..'F'
$result?->breakdown; // [['code' => 'cross_domain_canonical', 'severity' => 'warning', 'penalty' => 15]]
```

`php artisan seo-pro:scan-status` affiche le **score moyen du site** dans son résumé. Le tableau de bord Filament le présente dans la statistique « Avg. SEO score », avec une couleur selon la note.

### Tranches de notes {#grade-bands}

La lettre est une représentation visuelle dérivée du chiffre ; le chiffre reste le contrat :

| Score | Note |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Le signal à examiner avant publication : `noindex_warning` {#the-shipping-signal-noindex-warning}

`noindex_warning` se déclenche lorsqu’une page combine `noindex` et une **canonique autoréférente**, une canonique qui désigne sa propre URL. Rankbeam traite cette combinaison comme un signal à examiner avant publication. Une canonique autoréférente **ne prouve pas** une intention d’indexation : la combinaison peut être volontaire. Une canonique vers un autre domaine ne déclenche pas cette heuristique. Le problème porte `context.shipping_signal`, par exemple `self_canonical`, ainsi que les valeurs `canonical` et `page_url` comparées.

Les deux scanners appliquent ce contrôle. Le scan de modèle, `PageScanner`, compare la canonique enregistrée à l’URL du modèle. Le scan d’URL rendue, `UrlScanner`, fait passer une page `noindex` avec canonique autoréférente du code informatif `noindex_page` au code comptabilisé `noindex_warning`. C’est pourquoi `noindex_page` est exclu : le conflit potentiel est traité par `noindex_warning` dans les deux cas. Vérifiez l’intention réelle de la page avant de modifier sa directive d’indexation.

## Configuration {#configuration}

```php
// config/seo-pro.php → 'scan'
'score' => [
    'enabled' => true, // turn the scoring pass + its persistence on/off
],
```

La liste de codes et les poids ne sont **pas configurables** : pour une `rubric_version` donnée, le score doit être déterministe dans toutes les installations. Modifier son calcul nécessite donc une modification du barème dans le code, pas un simple réglage.

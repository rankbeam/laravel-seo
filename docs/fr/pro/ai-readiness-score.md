---
description: "Un second score déterministe, séparé du score SEO : une mesure technique définie par Rankbeam pour évaluer si les robots IA peuvent atteindre et lire une page."
---

# Le score AI-Readiness : un second axe déterministe {#the-ai-readiness-score-—-a-second-deterministic-axis}

Le scan Pro attribue à chaque page un second chiffre à côté du [score SEO](/fr/pro/scoring) : un **score AI-Readiness de 0 à 100**. Il répond à une autre question : *les robots IA et moteurs de réponses peuvent-ils atteindre, lire et attribuer ce contenu ?* Il n’est **jamais incorporé au score SEO organique**. Ce sont deux axes séparés, chacun avec son barème, sa version et sa colonne.

::: warning Signification et limites du score
Le score AI-Readiness est une **mesure déterministe de compatibilité technique définie par Rankbeam** : présence et bonne formation de signaux observables lors de l’exploration d’une page. Il ne prédit **ni** classement, **ni** indexation, inclusion ou citation dans un système de recherche ou d’IA. Aucun score ne garantit ces résultats. Le contrôle `air_llms_txt` accorde des points à un fichier de compatibilité `llms.txt` **facultatif**, destiné aux outils qui choisissent de le lire. Google Search ne l’exige pas et ce n’est pas un signal de classement.
:::

Comme le score SEO, il est **entièrement déterministe et reproductible** : chaque point correspond à un contrôle nommé fondé sur l’exploration, et les mêmes signaux produisent le même chiffre. **Le calcul du score ne fait aucun appel à une IA.** C’est une mesure transparente et vérifiable, distincte d’un échantillonnage de réponses de LLM utilisé par certains services SaaS de « visibilité IA ».

```
score = round( Σ credit(check) × weight(check) )   for every rubric check
```

::: tip Deux axes toujours séparés
`AI-readiness: 74/100` apparaît à côté de `SEO: 82/100` ; aucun ne modifie l’autre. Le score AI-Readiness possède ses propres colonnes `ai_readiness_*` dans la ligne `seo_scan_results` de Pro. Comme le score SEO, **le chiffre est une fonctionnalité Pro** : le [`seo:audit`](/fr/guide/audit) gratuit n’affiche aucun score numérique.
:::

## Des points ajoutés plutôt que des pénalités {#additive-credit-not-penalty}

Le [score SEO](/fr/pro/scoring) part de 100 et *retire* des pénalités. AI-Readiness fait l’inverse : il part de **0** et **accorde** tout ou partie du poids de chaque contrôle. La préparation se construit à partir des signaux présents ; un site sans ces signaux reçoit donc un score proche de 0, plutôt que « 100 moins quelques points ». La somme des poids vaut exactement **100**.

## Le barème {#the-rubric}

Le calcul utilise un **barème publié et versionné**, `Rankbeam\Seo\Pro\Scanning\AiReadiness\AiReadinessRubric`, composé de dix contrôles dans quatre catégories :

### A · Accès et contrôle des robots : 30 points {#a-·-bot-access-control-—-30-points}

Les robots de recherche IA et d’assistants sont-ils autorisés à explorer la page ? Le contrôle utilise le **`/robots.txt` réellement servi** et le **chemin propre à la page scannée**. Une page sous `Disallow: /section` est interdite même si la racine est ouverte. robots.txt reste une directive pour les robots conformes, pas un blocage réseau. Le contrôle utilise la classification par finalité, entraînement / recherche / assistant, du [catalogue de robots IA](/fr/guide/ai-crawlers).

| Contrôle | Poids | Points accordés |
|---|---|---|
| `air_robots_reachable` : un `robots.txt` est servi et lisible | 6 | Présent / absent |
| `air_ai_search_access` : les robots IA de **recherche**, canal de visites référentes, sont autorisés | 10 | Fraction autorisée |
| `air_ai_assistant_access` : les robots IA d’**assistants** sont autorisés | 8 | Fraction autorisée |
| `air_explicit_ai_policy` : une règle `robots.txt` explicite vise un robot IA connu | 6 | Présente / absente |

::: tip Interdire les robots d’entraînement ne réduit pas la préparation
Interdire GPTBot, CCBot ou d’autres robots d’entraînement est un choix légitime, **jamais** pénalisé. L’entraînement intervient seulement dans `air_explicit_ai_policy`, qui valorise une politique explicite et volontaire. Un site interdisant l’entraînement mais autorisant recherche et assistants peut obtenir tous les points de cette catégorie.
:::

### B · Découverte : 20 points {#b-·-discoverability-—-20-points}

| Contrôle | Poids | Points accordés |
|---|---|---|
| `air_sitemap_discoverable` : un sitemap XML est accessible **et** référencé par une directive `Sitemap:` | 12 | Les deux / un seul / aucun |
| `air_llms_txt` : un `/llms.txt` valide, avec titre et liens, est servi | 8 | Valide / présent / absent |

### C · Contenu lisible par les machines : 22 points {#c-·-machine-readable-content-—-22-points}

| Contrôle | Poids | Points accordés |
|---|---|---|
| `air_server_rendered_content` : du texte substantiel existe dans le HTML rendu côté serveur, sans exécuter JavaScript | 14 | Selon le nombre de mots |
| `air_markdown_twin` : une version Markdown de la page est servie par négociation de contenu | 8 | Présente / absente |

### D · Données structurées et préparation aux réponses : 28 points {#d-·-structured-data-answer-readiness-—-28-points}

| Contrôle | Poids | Points accordés |
|---|---|---|
| `air_schema_completeness` : JSON-LD présent, entité principale typée, auteur et date présents pour les articles | 18 | Complet / partiel / absent |
| `air_answer_structure` : éléments facilitant l’extraction de réponses, schéma FAQ/QA/HowTo, hiérarchie de titres, listes et introduction concise | 10 | Selon le nombre d’éléments |

Chaque contrôle accorde **tous les points**, **une partie** ou **aucun point**. Il peut aussi être **ignoré** si le signal nécessaire n’a pas pu être recueilli, par exemple un contrôle de page sur une cible scannée sans récupération de page. Un contrôle ignoré rapporte 0, mais cet état est signalé : un signal *impossible à vérifier* n’est jamais présenté comme une absence confirmée.

### Périmètre de l’audit gratuit {#free-audit-reach}

La complétude du schéma, `air_schema_completeness`, peut être résolue depuis les données structurées d’un modèle sans requête HTTP, comme les résultats de préparation aux réponses de l’audit gratuit. Les neuf autres contrôles nécessitent une exploration. Le score complet relève donc du **scan Pro**.

## Périmètre : ce que cet axe exclut {#honest-scope-—-what-this-axis-excludes}

Cet axe évalue des **signaux déterministes de contenu**. Il exclut les contrôles d’infrastructure d’agents qui concernent une application en cours d’exécution ou le DNS :

| Élément exclu | Motif |
|---|---|
| **DNS-AID**, enregistrements DNS de découverte d’agents | Infrastructure DNS / DNSSEC, pas propriété d’une page servie. |
| **Web Bot Auth**, signature par requête | Échange cryptographique interactif, pas contenu statique. |
| **Découverte de protocoles**, API Catalog, OAuth/OIDC, MCP Server Card, Agent Skills, WebMCP… | Nécessite une application, API ou serveur MCP en fonctionnement. |
| **Commerce**, x402, MPP, UCP, ACP | Protocoles de paiement pour agents ; un site de contenu n’a pas nécessairement de transaction à facturer. |

Il inclut la complétude des entités de schéma et la structure des blocs de réponse : des signaux de contenu décrivant organisation et attribution, sans garantir leur utilisation par un moteur de recherche ou de réponses.

## Versionnement : les anciens scores ne changent jamais silencieusement {#versioning-—-historical-scores-never-silently-change}

Chaque score AI-Readiness enregistré porte la `AiReadinessRubric::VERSION` qui l’a produit, dans `ai_readiness_version`. Modifier les contrôles, un poids ou un modèle d’attribution de points change le barème et **incrémente sa version**. Un score conservé indique donc toujours le barème qui l’explique, ce qui permet de comparer l’historique en connaissance de cause. Le score est **enregistré, pas recalculé à la lecture**. Les seuils qui l’affectent, nombres de mots ou d’éléments de structure, sont des constantes de code liées à la version, jamais des réglages. Un changement de configuration ne peut donc pas modifier silencieusement un score publié.

::: warning Une entrée non figée par la version
Les contrôles d’accès des robots lisent le [catalogue IA **courant**](/fr/guide/ai-crawlers) du cœur. Une mise à jour du catalogue, ajout d’un robot ou changement de finalité, modifie les entrées et peut déplacer les deux sous-scores d’accès sans changer `AiReadinessRubric::VERSION`. La version suit le *barème*, pas le catalogue. C’est volontaire : le contrôle reste plus utile avec la liste réelle actuelle qu’avec une liste figée. Pour une comparabilité historique exacte, conservez aussi la version du package cœur.
:::

## Stockage {#where-it-s-stored}

Chaque scan crée ou met à jour les colonnes AI-Readiness sur la **même** ligne `seo_scan_results` que le score SEO :

| Colonne | Contenu |
|---|---|
| `ai_readiness_score` | Score de 0 à 100 ; null tant que la cible n’a pas été scannée avec cet axe activé. |
| `ai_readiness_version` | Barème utilisé. |
| `ai_readiness_breakdown` | `[{code, category, credit, weight, points, status, message, evidence}, …]`, trace complète. |

La moyenne par exécution est enregistrée dans `seo_scan_runs.avg_ai_readiness` à la fin du scan, comme `avg_score`, pour constituer la tendance AI-Readiness.

## Lire le score {#reading-the-score}

**Headless** : le dernier résultat d’un modèle contient les deux axes :

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$result = SeoPro::resultFor($post);

$result?->score;                    // organic SEO score, e.g. 82
$result?->ai_readiness_score;       // AI-Readiness, e.g. 74
$result?->aiReadinessGrade();       // 'A'..'F' (null if never scored)
$result?->aiReadinessByCategory();  // ['bot_access' => ['awarded' => 24.0, 'max' => 30], …]
```

**Filament** : placez la colonne associée à côté de celle du score SEO dans le tableau d’une ressource :

```php
use Rankbeam\Seo\Pro\Filament\Tables\Columns\AiReadinessScoreColumn;
use Rankbeam\Seo\Pro\Filament\Tables\Columns\SeoScoreColumn;

SeoScoreColumn::make(),
AiReadinessScoreColumn::make(),
```

Le score apparaît aussi dans un badge à côté du score on-page, au-dessus du champ de titre SEO, et dans une section propre du [rapport en marque blanche](/fr/pro/reports), PDF et e-mail : chiffre, note, évolution depuis le rapport précédent et tendance par scan. Il est toujours présenté à côté du score organique, jamais fusionné avec lui.

### Tranches de notes {#grade-bands}

La lettre est une présentation dérivée du chiffre, qui reste le contrat. Les tranches sont identiques à celles du score SEO :

| Score | Note |
|---|---|
| 90-100 | A |
| 75-89 | B |
| 50-74 | C |
| 25-49 | D |
| 0-24 | F |

## Configuration {#configuration}

```php
// config/seo-pro.php → 'scan'
'ai_readiness' => [
    'enabled' => true,             // turn the AI-Readiness pass + its persistence on/off
    'fetch_site_signals' => true,  // fetch /robots.txt, /llms.txt, /sitemap.xml (per host)
    'probe_markdown_twin' => true, // probe Accept: text/markdown on the page
],
```

Contrôles et poids ne sont **pas configurables**. Pour une `ai_readiness_version` donnée, le score doit être déterministe dans toutes les installations. Modifier le calcul nécessite donc une modification du barème dans le code, pas un réglage.

::: warning Les signaux du site sont détectés via les requêtes internes à Laravel
Pour une cible sur le même hôte, le scan résout `/robots.txt`, `/llms.txt` et la page via le noyau HTTP de Laravel dans le processus, comme le reste du scan. Un `robots.txt` ou `llms.txt` servi comme **fichier statique**, sans passer par les routes Laravel, n’est pas vu. Servez-les via les routes du package, configuration conseillée, pour qu’ils soient pris en compte.
:::

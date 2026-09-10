---
description: "Une liste de contrôles on-page liée au mot-clé principal : réussite, avertissement ou échec pour le titre, l’URL, l’introduction, les métadonnées, la longueur, les images et la lisibilité."
---

# Liste de contrôle on-page : mots-clés, réussite, avertissement et échec {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

La liste de contrôle on-page accompagne le travail éditorial connu des utilisateurs de Rank Math ou Yoast. Choisissez un mot-clé principal et obtenez des indicateurs de présence dans le titre, l’URL, le premier paragraphe et la meta description, ainsi que des contrôles de longueur, images, liens internes et **lisibilité**.

L’analyse s’exécute **pendant la requête**, sans file ni réseau, depuis le modèle, le [résolveur](/fr/concepts/resolver-precedence) et le texte de la page. Elle ne produit volontairement **aucun score global numérique**.

::: tip La liste de contrôle est distincte du score
Elle indique uniquement **réussite / avertissement / échec** et reste entièrement séparée du [score SEO Pro](/fr/pro/scoring). Elle ne partage aucun code avec son barème et ne peut jamais le modifier. Les conseils éditoriaux restent distincts du calcul numérique. La densité de mots-clés et la lisibilité sont notamment des **conseils**, comme expliqué plus bas.
:::

## Contrôles disponibles {#what-it-checks}

| Contrôle | Groupe | Vérification |
|---|---|---|
| `keyword_in_title` | keyword | Présence du mot-clé principal dans le titre SEO. |
| `keyword_in_description` | keyword | Présence du mot-clé principal dans la meta description. |
| `keyword_in_url` | keyword | Présence du mot-clé principal dans le slug de l’URL. |
| `keyword_in_first_paragraph` | keyword | Présence du mot-clé principal dans le premier paragraphe. |
| `keyword_density` | keyword | **Conseil.** Répétition naturelle, sans densité cible ; voir ci-dessous. |
| `title_length` | meta | Même plage que l’éditeur et le scan : 30–60 en latin, environ 15–30 en CJK, selon la [politique de longueur](/fr/guide/multilingual#title-and-description-budgets-per-script) du cœur, depuis Pro 2.33. |
| `description_length` | meta | Même plage : 70–160 en latin, environ 35–80 en CJK. |
| `content_length` | content | Quantité de texte suffisante selon les plages configurées de nombre de mots. |
| `readability` | content | **Conseil.** Niveau estimé selon la formule choisie, dix langues, un repli LIX ou une heuristique explicitement non notée pour japonais, chinois et coréen. |
| `has_image` | media | Au moins une image dans le contenu. |
| `internal_links` | links | Liens vers des pages internes liées au sujet. |

Sans mot-clé principal, les contrôles associés sont **ignorés**, sans réussite ni échec. La liste invite à en ajouter un via le [champ dédié](/fr/guide/filament) ou `saveSEO(['focus_keywords' => …])`.

### Correspondance des mots-clés {#keyword-matching}

Le mot-clé et le texte sont comparés après **normalisation de casse et racinisation**. « espresso grinder » peut ainsi correspondre à « espresso grinders ». Avec la locale d’analyse appropriée, le `CaseFolder` du cœur fait correspondre le turc « İstanbul » à « istanbul », le grec « ΟΔΟΣ » à « οδος » et l’allemand « Straße » à « STRASSE ». Passez la locale à analyser avec `SeoPro::checklistFor($post, 'it')` ou `--locale=it`.

Depuis Pro 2.36.1, mots-clés, synonymes et textes de champs utilisent le même tokenizer avant racinisation. La correspondance exige des **tokens entiers consécutifs** : `cat` ne correspond pas à `education`, et les expressions japonaises utilisent les mêmes frontières de mots ICU que le corps. Apostrophes et traits d’union séparent les tokens : `meta-tag` correspond à `meta tag`, et apostrophes droites ou courbes sont traitées de la même façon. Les marques combinatoires restent liées à leurs lettres. La normalisation de casse conserve les accents ; le racinisateur d’une langue peut appliquer d’autres réductions.

Le comptage sélectionne à chaque position le mot-clé ou synonyme correspondant le plus long et compte ce segment une seule fois. Les synonymes en double et alternatives plus courtes qui se chevauchent ne gonflent pas la densité. Par exemple, le mot-clé `seo` avec le synonyme `seo tools` compte deux occurrences dans `seo tools seo`. ICU reste nécessaire pour les frontières de mots fondées sur dictionnaire dans les écritures sans espaces ; le repli regex ne peut pas les fournir.

Depuis Pro 2.37, la racinisation utilise un **sous-ensemble Snowball 3.1.1 intégré**. Aucun package Composer supplémentaire ni téléchargement à l’exécution n’est nécessaire. PHP 8.2 reste pris en charge.

| Moteur | Utilisation | Langues |
| --- | --- | --- |
| `snowball` | Défaut ; les anciens réglages `auto` sélectionnent le même moteur intégré | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | Réglage explicite `seo-pro.checklist.analysis.stemmer = builtin` | Anglais uniquement, ancien racinisateur flexionnel léger ; identité pour les autres langues |
| `identity` | Langue non prise en charge ou mode `none` explicite | Ukrainien, japonais, chinois, coréen, thaï et autres langues hors sous-ensemble intégré |

Les deux côtés de la comparaison utilisent le même moteur. La racinisation réduit des suffixes ; ce n’est ni un dictionnaire de synonymes ni une garantie d’équivalence linguistique. L’algorithme grec peut par exemple faire correspondre des formes accentuées et non accentuées que le mode identity garde distinctes. Les frontières de tokens empêchent toujours `cat` de correspondre à `education`.

#### Mise à niveau depuis Pro 2.36 {#upgrading-from-pro-2-36}

La configuration `auto` utilise désormais systématiquement les algorithmes intégrés, que `wamania/php-stemmer` soit installé ou non. Réexaminez les suggestions éditoriales après la mise à niveau : les algorithmes peuvent modifier les correspondances, et turc, grec, polonais et tchèque disposent maintenant d’une racinisation. Les algorithmes catalan, danois, finnois, norvégien, roumain et suédois supplémentaires de l’ancien wrapper facultatif sont hors de ce sous-ensemble et utilisent maintenant la correspondance identity.

Définissez `SEO_PRO_CHECKLIST_STEMMER=builtin` pour l’ancien repli anglais seul, ou `none` pour une comparaison sans racinisation, après normalisation de casse, dans toutes les langues. Reconstruisez le cache de configuration après ce changement. Ces options ne reproduisent pas les anciens algorithmes multilingues du wrapper facultatif ; conserver exactement leurs résultats nécessite de rester sur la version précédente de Pro. Aucune métadonnée SEO enregistrée n’est réécrite.

L’adaptateur intégré passe 600 395 paires officielles versionnées vocabulaire/résultat sous PHP 8.2, 8.3 et 8.4. Cela démontre la conformité algorithmique, pas une validation éditoriale native. Les empreintes sources, l’adaptation syntaxique à PHP 8.2 et les licences amont sont fournies avec le package. Consultez `THIRD-PARTY-NOTICES.md` dans la distribution source.

### Segmentation des mots {#word-segmentation}

Nombre de mots, densité et statistiques de lisibilité nécessitent une segmentation. Pour les écritures espacées, une regex utilise des frontières stables entre lettres/chiffres et séparateurs. Chinois, japonais et thaï nécessitent un dictionnaire ; une regex peut traiter un paragraphe comme un seul « mot ». Avec **ext-intl**, le tokenizer confie ces séquences à l’itérateur de mots ICU, `IntlBreakIterator::createWordInstance`, qui segmente par exemple 東京タワーは東京のランドマークです. Si ICU est absent, désactivé ou impossible à initialiser, Pro ignore les contrôles concernés de longueur de contenu, lisibilité et mots-clés, avec un message d’installation ou de configuration. Il ne transforme pas un comptage peu fiable en échec. Les autres contrôles, dont la longueur du titre et les correspondances sur écritures espacées, continuent. `seo-pro.checklist.analysis.segmenter = regex` force le même état indisponible pour les textes nécessitant une segmentation par dictionnaire.

Le bloc `analysis` contient `word_count_status`, `available` ou `unavailable`, et `segmentation_reason`, `null`, `missing_intl`, `disabled` ou `initialization_failed`. Le tokenizer de bas niveau conserve des tokens de repli par compatibilité ; contrôlez cet état avant de les interpréter comme des mots.

Le scan de page rendue émet une remarque non notée `word_segmentation_unavailable` à la place d’un verdict de contenu insuffisant. Un problème de contenu insuffisant déjà confirmé reste ouvert jusqu’à pouvoir être revérifié. Ce scan incomplet ne rafraîchit pas le score : un score existant garde son `scored_at`, et un premier scan reste sans score jusqu’au fonctionnement de la segmentation. Installez PHP `ext-intl`, activez le segmenter `auto` et relancez le scan.

### Moteurs ayant analysé la page {#which-engines-analysed-the-page}

Chaque liste contient un bloc `analysis` : écriture dominante, tokenizer `intl` / `regex`, racinisateur `snowball` / `builtin` / `identity` et méthode de lisibilité `formula` / `heuristic` / `lix`. Il apparaît dans `toArray()` / `--json`, en pied de fenêtre Filament et à la dernière ligne de `seo-pro:checklist` :

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

Le pied de résultat identifie le moteur réellement utilisé, y compris la segmentation regex sans ext-intl et la correspondance identity lorsque la racinisation est désactivée.

### La densité de mots-clés est un conseil {#keyword-density-is-advisory}

La liste ne définit aucune densité idéale pour le classement. Ce contrôle est **indicatif** : il montre le compte pour information, n’échoue jamais et **ne détermine jamais l’état global de la page**. Examinez si la répétition paraît naturelle au lieu de viser un pourcentage.

### La lisibilité est un conseil {#readability-is-advisory}

La liste estime la lisibilité avec la méthode choisie pour la locale d’analyse. Formules et replis actuellement implémentés :

| Locale | Formule | Source |
| --- | --- | --- |
| Anglais, `en` | Flesch Reading Ease | Flesch 1948 |
| Italien, `it` | Indice Gulpease | Lucisano & Piemontese 1988 |
| Espagnol, `es` | Fernández-Huerta | Fernández Huerta 1959 |
| Français, `fr` | Kandel-Moles | Kandel & Moles 1958 |
| Allemand, `de` | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| Portugais, `pt`, `pt_BR` | Flesch adapté au portugais brésilien | Martins et al. 1996 |
| Néerlandais, `nl` | Flesch-Douma | Douma 1960 |
| Russe, `ru` | Adaptation de Flesch par Oborneva | Оборнева 2006 |
| Turc, `tr` | Ateşman | Ateşman 1997 |
| Polonais, `pl` | Pisarek, indice d’années de scolarité normalisé | Pisarek 1969 |
| Japonais, chinois, coréen, `ja`, `zh`, `ko` | **Heuristique sans score**, ci-dessous | — |
| Grec, ukrainien, tchèque, `el`, `uk`, `cs` | LIX : repli faute de formule dédiée implémentée ici, non calibré pour ces langues | Björnsson 1968 |
| Autres langues | LIX, Läsbarhetsindex, repli non calibré | Björnsson 1968 |

Le **score affiché de 0 à 100**, où une valeur plus élevée signifie une lecture estimée plus facile, est une convention du package. Les résultats Flesch et Gulpease sont bornés ; les indices Wiener, Pisarek et LIX sont convertis sur cette échelle. Des scores égaux dans des langues différentes **n’impliquent pas** une difficulté égale. Les coefficients proviennent de travaux publiés, mais les estimations Rankbeam de tokens, phrases et syllabes n’ont pas été validées comme instrument complet. Elles ne prédisent ni compréhension ni classement.

Depuis Pro 2.37.1, les voyelles adjacentes comptent séparément dans les syllabes turques et russes : `saat` vaut 2, `поэт` vaut 2. Les autres estimateurs ont encore des limites : les groupes de voyelles peuvent manquer des hiatus ou mal traiter des voyelles muettes. L’anglais possède une petite table d’exceptions, pas un dictionnaire de prononciation. L’espagnol `país` et le français `monde` peuvent par exemple être mal comptés. Examinez manuellement les mots inhabituels et noms propres.

#### Statistiques du texte et limites de l’API {#text-statistics-and-api-limits}

Les balises HTML de bloc et `br` séparent le texte ; une mise en emphase inline reste attachée au mot. Les retours à la ligne du HTML ordinaire sont réduits à des espaces, tandis que texte brut et `pre` conservent leurs lignes. Le contenu de script, style et noscript est exclu. L’extraction n’évalue ni la visibilité CSS ni la page rendue. Les entités sont décodées une fois. Pour les statistiques des formules, les séquences de lettres/chiffres sont des mots ; la ponctuation seule ne l’est pas. Traits d’union et apostrophes séparent les mots. Les chiffres comptent comme tokens sans syllabe déduite. Les lettres sont comptées dans le texte original, sans changement de longueur dû à la racinisation ou à la conversion allemande `ß` → `ss`.

L’estimation des phrases sépare les ponctuations terminales `. ! ? 。 ！ ？` et les frontières de blocs/lignes, inclut le dernier fragment sans ponctuation et protège les décimales ainsi qu’une courte liste d’abréviations courantes, `Dr.`, `Prof.`, `e.g.` et formes anglaises proches. Titres et éléments de liste peuvent donc compter comme phrases. Les autres abréviations, citations, nombres, écritures mixtes et textes peu ponctués nécessitent une attention particulière. La locale choisie sélectionne la méthode ; elle ne détecte pas la langue de chaque phrase.

Le `toArray()` du calculateur direct ajoute un bloc `assessment` :

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method` distingue `formula`, `lix`, `heuristic` et `unavailable`, ou `unspecified` pour des résultats construits manuellement sans métadonnées de formule. Une entrée vide ou uniquement ponctuée est `insufficient` et `isValid()` renvoie false. Son ancien `score: 0` est un marqueur d’indisponibilité, pas un score de difficulté. Les niveaux scolaires anglais/italiens existants sont approximatifs ; les autres langues et les résultats heuristic/LIX ne reçoivent plus ces libellés scolaires. `calculateFleschKincaid()` conserve son nom public par compatibilité, mais calcule **Flesch Reading Ease**, pas le niveau Flesch-Kincaid.

Les tests des formules fixent des entrées comptées indépendamment et les résultats arithmétiques attendus pour les dix formules nommées et LIX. Ils vérifient le calcul, pas la qualité éditoriale native. La lisibilité reste distincte du score SEO Pro.

::: warning Japonais, chinois et coréen : une heuristique identifiée, jamais un chiffre
Rankbeam utilise une méthode sans score pour ces langues. Le calculateur renvoie un **niveau** selon des règles propres au package : longueur moyenne des phrases en caractères, ja ≤ 40/60/80 et zh ≤ 30/45/60, ou en mots, ko ≤ 12/18/25. Pour le japonais, une part de kanji supérieure à environ 45 % augmente d’un cran la difficulté estimée. Le résultat porte `heuristic: true` et un **score null**. Le message dit « heuristique » ; le contrôle reste **indicatif dans ces langues, quelle que soit la valeur de `readability.advisory`**. Une règle approximative informe sans bloquer l’état global. Le comptage de mots ja/zh nécessite une segmentation ICU fonctionnelle ; ces contrôles sont ignorés si elle est indisponible.
:::

Comme la densité, la lisibilité est **indicative par défaut** : elle informe l’auteur sans déterminer l’état global de la page, suivant la séparation entre analyses de lisibilité et SEO également présente chez Yoast. Elle est **ignorée** sous un nombre minimal de mots : les pages trop courtes relèvent de `content_length`. Pour faire échouer une page jugée difficile à lire, rendez ce contrôle bloquant :

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## Consulter la liste de contrôle {#reading-the-checklist}

### Headless {#headless}

Depuis Pro 2.36, métadonnées résolues, `getContentForSEO()` et mots-clés sont lus dans la locale de contenu demandée, tandis que les libellés restent dans la langue de l’opérateur. Sans locale explicite, la valeur par défaut de `seoData()` d’un modèle de traduction est respectée. L’action Filament suit l’onglet de langue du champ ou le sélecteur de locale de la page.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

Chaque `CheckResult` porte `id`, `group`, `label`, `status`, `message`, une `recommendation` facultative et l’indicateur `advisory`.

### Commande {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### Dans l’éditeur Filament, facultatif {#in-the-editor-filament-optional}

Avec [`rankbeam/laravel-seo-filament`](/fr/guide/filament), une action **On-page checklist** apparaît sur le champ de mot-clé principal. Elle ouvre une fenêtre présentant les mêmes contrôles réussite/avertissement/échec pour le contenu enregistré. Le package Filament ne dépend jamais de Pro : l’action utilise le même hook d’extension à sens unique que les suggestions IA. Les installations headless restent inchangées.

## Configuration {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### Écrire un contrôle personnalisé {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

Enregistrez sa classe dans `seo-pro.checklist.rules`. Un contrôle **ne doit pas** réutiliser l’identifiant d’un [code de problème de scan](/fr/pro/scan-issues) : la liste de contrôle possède son propre espace de noms, sans effet sur le score.

## Lecture du contenu {#how-the-content-is-read}

`SeoPro::checklistFor($model)` analyse :

- **Titre et description** : valeurs *résolues*, effectives, identiques à celles mesurées par les compteurs de l’éditeur et le scan.
- **Contenu** : `$model->getContentForSEO()`, accesseur `HasSEO` du cœur, utilisant `content` / `body` / `text` par défaut. Redéfinissez-le pour viser le véritable corps du texte.
- **URL** : `$model->getUrlForSEO()`.
- **Mots-clés principaux** : `seo_meta.focus_keywords` enregistrés.

L’analyse est pure : aucune page n’est récupérée et rien n’est écrit.

---
description: "Contenus multilingues avec Rankbeam : budgets par écriture, graphèmes, casse, hreflang, inLanguage, moteurs régionaux, polices et URL Unicode."
---

# Contenus multilingues {#multilingual-content}

Les [traductions](/fr/guide/translations) déterminent la langue de l'interface du paquet. Cette page traite de la **langue de votre contenu** : budgets différents pour le japonais, troncature du thaï sans espaces, équivalence turque entre `İstanbul` et `istanbul`, correction d'un hreflang `it_IT`, ou présence du robot Naver pour un site coréen. Ces règles appartiennent au Core afin que les différents composants partagent les mêmes décisions.

Les valeurs et politiques se trouvent dans `config/seo.php`. Certaines fonctions nécessitent ICU pour segmenter les mots ou des polices installées pour le rendu. Votre application doit fournir les contenus traduits.

## Langue du contenu et langue de l'interface {#content-locale-and-interface-locale}

Core 3.17, Filament 1.11 et Pro 2.36 transmettent la langue choisie aux métadonnées, hooks calculés, URL d'aperçu, mots-clés et requêtes IA. Un panel anglais peut modifier des pages italiennes ou japonaises sans changer ses propres libellés.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

Ces lectures sélectionnent la ligne de métadonnées de la langue et exécutent `getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()` et `getSEOSchema()` dans un contexte linguistique temporaire. La langue du modèle appelant et celle de l'application sont conservées, même si un hook lève une exception. Les modèles implémentant les méthodes Spatie `setLocale()` et `getTranslatableAttributes()` reçoivent aussi une langue d'instance isolée. Vos hooks doivent renvoyer les traductions : Rankbeam ne traduit pas automatiquement les attributs ordinaires de la base.

Les méthodes IA basées sur un modèle et le remplissage en masse de Pro acceptent un paramètre explicite `locale:`. Sans lui, le comportement par défaut d'un `seoData()` redéfini par un modèle traduisible détermine la langue, avec repli sur celle de l'application. Les actions Filament reçoivent la langue de leur champ, y compris dans un éditeur monolingue ou en mode de suivi. Dans vos jobs, sérialisez la langue choisie et transmettez-la à l'exécution ; ne dépendez pas de celle du worker.

Pour une lecture synchrone personnalisée, `ModelLocale::run($model, $locale, $callback)` passe un modèle isolé au callback et restaure la langue de l'application dans `finally`. Terminez toutes les lectures linguistiques dans ce callback : renvoyer un itérateur paresseux ou une closure ne prolonge pas le contexte.

## Budgets de titre et description par écriture {#title-and-description-budgets-per-script}

Rankbeam utilise des budgets éditoriaux de 60/160 graphèmes pour les titres/descriptions latins et 30/80 pour CJK. Ce sont des approximations configurables, pas des mesures en pixels ni des garanties d'affichage intégral. Google ne fixe pas de limite de caractères pour les [liens de titre](https://developers.google.com/search/docs/appearance/title-link) ou les [descriptions](https://developers.google.com/search/docs/appearance/snippet) ; le texte peut être tronqué selon la largeur disponible.

`Rankbeam\Seo\I18n\LengthPolicy` choisit le budget du texte courant :

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

Les avertissements (`SEOWarningEvaluator`), l'audit gratuit, la troncature des descriptions calculées, Pro et les compteurs Filament lisent cette politique. Les avertissements examinent les valeurs résolues, suffixe du titre compris, tandis que l'éditeur peut afficher une saisie non enregistrée. Les longueurs comptent les **grappes de graphèmes**, pas les octets ni les points de code. Leurs frontières dépendent de l'implémentation Unicode installée ; elles ne mesurent ni les syllabes ni les pixels des résultats de recherche.

Les groupes de `seo.length_policy` sont `latin`, `cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew` et `devanagari`, avec `default` pour les autres. Une ligne peut ne remplacer que quelques clés :

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

Seul `cjk` diffère par défaut. Une application mise à niveau avec une ancienne configuration publiée bénéficie aussi de cette ligne intégrée.

::: tip Titres mixtes
La détection pondère les lettres : un caractère CJK compte double. « Laravel SEO の完全ガイド » est classé CJK ; « Laravel SEO for the 東京 developer » reste latin. Sans lettres, comme pour un prix ou une année, le paquet utilise l'écriture de la langue de la page.
:::

Les constantes `SEOWarningEvaluator::TITLE_MAX_LENGTH` et `DESCRIPTION_MAX_LENGTH` restent les valeurs latines par défaut pour le code qui les lit.

## Troncature adaptée aux graphèmes et à l'écriture {#grapheme-safe-script-aware-truncation}

Le budget latin `seo.computed.description_max_length` est ajusté par la politique — divisé par deux pour CJK — puis `Rankbeam\Seo\I18n\Truncator` coupe le texte :

- Avec des espaces entre mots, il conserve la dernière frontière de mot dans la limite, si elle atteint au moins 60 % du budget. Pas de points de suspension ; ponctuation finale retirée. Le comportement latin historique reste identique octet par octet.
- Pour Han, Kana et thaï, il préfère la dernière ponctuation de phrase ou proposition (。！？、，…) dans la limite, puis un espace s'il y en a, notamment en coréen, puis une coupe à la limite.
- La coupe respecte les grappes de graphèmes pour ne pas séparer un signe combinant de sa base, comme une voyelle thaïe ou un modificateur d'emoji.

## Casse selon la langue {#locale-aware-casing}

`mb_strtolower()` ne tient pas compte de la langue. `Rankbeam\Seo\I18n\CaseFolder` le fait :

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` produit une forme d'affichage ; `fold()`, `equals()`, `contains()` et `containsWord()` servent aux comparaisons. Le Core les utilise pour éviter de répéter une marque dans le suffixe (`seo.title_suffix_skip_when_contains`), avec les formes turques du i et de vraies frontières de mots Unicode. Les vérifications de mots-clés Pro s'appuient sur le même outil.

Le repli de casse conserve les accents : toutes les formes accentuées ne deviennent pas équivalentes aux formes non accentuées. Un stemmer linguistique peut appliquer ses propres réductions, distinctes de `CaseFolder` et de la comparaison d'identité.

## hreflang {#hreflang}

Google accepte `language[-Script][-REGION]` : langue à deux lettres ISO 639-1, écriture ISO 15924 facultative, région à deux lettres ISO 3166-1 facultative, ainsi que `x-default`. Une région numérique comme `es-419` est valide en BCP47 mais ne fait pas partie des [codes hreflang acceptés par Google](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes).

Les applications Laravel fournissent souvent une locale comme `it_IT` ou `pt_br`, dont le soulignement est invalide ici. Trois politiques `seo.hreflang` s'appliquent à `getSEOAlternates()` avant la production des balises `<link rel="alternate">`, des entrées `<xhtml:link>` du sitemap, des liens `llms.txt` et des données de l'audit. `llms.txt` omet la page elle-même et `x-default` dans ses liens « Also in ».

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`**, activé par défaut, adapte séparateurs, casse et alias enregistrés (`iw_IL` → `he-IL`). Les séparateurs répétés restent visibles (`en__US` → `en--US`) pour que l'audit les signale. Désactivez-le pour conserver les octets fournis.
- **`include_self`** ajoute la langue et le canonical de la page si ni son URL ni son code ne figurent dans la liste. Activez-le quand votre hook renvoie seulement les autres versions, afin que chaque version se référence aussi elle-même.
- **`x_default`** désigne la langue dont l'alternative sera dupliquée en `x-default` si cette entrée manque.

Une liste vide reste vide : pas d'autoréférence ni de `x-default` ajouté à une page sans traduction.

L'audit gratuit vérifie la liste après ces politiques :

| Code | Gravité | Sens |
|---|---|---|
| `hreflang_invalid_code` | warning | Code hors du format Google : `en-UK`, `jp`, `english`, `es-419`, `fil`. |
| `hreflang_duplicate_code` | notice | Même code déclaré deux fois. |
| `hreflang_missing_self` | warning | URL de la page absente de sa liste. |

La réciprocité exige un crawl. Pro, avec `check_hreflang_reciprocity`, récupère chaque alternative via SsrfGuard. Il émet `hreflang_not_reciprocal` si la cible ne déclare pas l'URL source **avec son code de langue** (Pro 2.38+ ; [codes réseau](/fr/pro/scan-issues#network-codes)). L'outil est public :

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### Trois contrats de codes linguistiques {#three-language-code-contracts}

Core **3.18+** distingue le réglage de l'application de la valeur effectivement servie dans le HTML :

| Entrée | Normalisation applicative | Langue HTML | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | Invalide telle quelle | Invalide telle quelle |
| `de-CH-1901` | Conservée | Variante enregistrée valide | Variante non prise en charge |
| `es-419` | Conservée | Région numérique valide | Région numérique non prise en charge |
| `zh-Hant-TW` | Conservée | Valide | Valide |
| `fil` | Conservée | Langue enregistrée valide | Ne respecte pas les deux lettres |
| `iw_IL` | `he-IL` | Soulignement invalide ; `iw-IL` reste un tag obsolète valide | Employer `he-IL` normalisé |
| `en__US` | `en--US` | Invalide | Invalide |
| `x-default` | Conservée | Refusé comme langue de contenu par Rankbeam | Marqueur de repli valide |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Migration depuis Core 3.17 ou antérieur :** `Hreflang::isValid()` et `parse()` valident strictement les codes servis. Pour une locale Laravel, appelez d'abord `fromLocale()`. Pour un attribut HTML `lang`, utilisez `LanguageTag::isValidHtml()` sans retirer d'espaces ni normaliser. Les tags enregistrés obsolètes restent valides en HTML. Seuls les alias préférés explicites de l'IANA sont normalisés ; le paquet ne devine pas que `en-UK` signifie `en-GB`. Les entrées malformées ne disparaissent pas avant l'audit.

Le validateur inclut les données du registre IANA daté du **2026-08-08**, avec hashes de sources et générateur reproductible. Il vérifie la structure RFC 5646, les sous-tags enregistrés, préfixes extlang et doublons de variantes/extensions. Il accepte les anciens tags conservés et plages privées. Les recommandations de préfixes de variantes ne sont pas des règles obligatoires de validité. Les espaces de noms et structures d'extensions sont contrôlés ; la sémantique CLDR et le sens des valeurs privées restent hors API. Aucun ICU ni téléchargement à l'exécution n'est nécessaire. Voir [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html) et la [définition HTML de lang](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes).

Pro **2.38+** considère un `lang` absent ou vide comme inconnu/manquant. Des octets malformés déclenchent `html_lang_invalid`. La comparaison d'écriture utilise un sous-tag Script explicite ou le défaut enregistré par l'IANA. Les données privées, extensions et langues inconnues n'impliquent pas une écriture latine. Les groupes non pris en charge restent non évalués. Ce n'est pas un détecteur complet de langue.

La réciprocité utilise les codes d'autoréférence valides de la source, ou à défaut sa langue HTML valide compatible Google. Un retour vers la bonne URL sous une autre langue ne suffit pas. Si le code source ne peut pas être établi, le résultat reste `hreflang_target_unverified`. Une URL cible répétée n'est récupérée qu'une fois, dans les limites existantes. Protections SSRF, refus des redirections et traitement des échecs non vérifiables restent actifs.

## `inLanguage` dans le graphe de schémas {#inlanguage-in-the-schema-graph}

`WebPage` reçoit `inLanguage` depuis la langue résolue de la page (`it_IT` → `it-IT`). `ArticleSchema::fromModel()` utilise celle de `seo_meta`. `WebSite` lit ses langues dans la configuration :

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## Moteurs de recherche régionaux {#regional-search-engines}

Le catalogue derrière `seo:robots-txt` inclut Yandex, Baidu, Naver (`Yeti`), Seznam, Sogou, 360, Cốc Cốc et DuckDuckGo. Avec la finalité `search_engine`, ils sont autorisés par défaut et suivent les politiques et exceptions individuelles :

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` et `match()` restent réservés aux robots IA. Demandez les moteurs avec `searchEngines()`, `all(true)` ou `match($ua, true)`. Le journal et les compteurs IA gardent ainsi leur sens. Voir [contrôle des robots](/fr/guide/ai-crawlers#regional-search-engines).

::: warning Baidu
Reconnaître le robot et émettre sa balise de vérification ne garantit ni découverte, indexation ni classement dans Baidu.
:::

## Vérification du site {#site-verification}

Les tokens de propriété produisent des balises meta sur chaque page, dont la racine où Yandex, Baidu et Naver recherchent le justificatif. Une clé vide ne produit rien :

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

Une valeur peut être une liste de tokens, notamment pour plusieurs propriétaires d'une propriété Google.

## Images OG et écritures {#og-images-in-every-script}

La police incluse couvre les écritures latine, cyrillique et grecque. Les autres dépendent de polices installées sur le serveur exécutant `seo:og-images` ; une police CJK dépasse souvent 16 Mo. Les templates utilisent `seo.og_image.font_stack`, avec la famille Noto CJK de la langue en premier pour sélectionner les formes nationales des caractères Han. La commande avertit une fois par écriture lorsqu'elle ne trouve pas de police adaptée au texte à rendre :

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Sur Debian/Ubuntu : `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`. Voir [images OG](/fr/guide/og-image#fonts-and-non-latin-scripts).

## `llms.txt` en plusieurs langues {#llms-txt-in-several-languages}

Avec `seo.llms_txt.alternates`, les pages traduites ajoutent `Also in: [it](…), [de](…)` à leur entrée. Les alternatives suivent la politique hreflang, sans `x-default` ni la page elle-même. Désactivé par défaut.

## URL Unicode {#unicode-urls}

Rankbeam ne crée pas de slug et ne réécrit pas vos URL : `/città/` ou `/検索` restent inchangés dans les sorties. L'audit accepte les hôtes IDN (`https://münchen.example/`) et les chemins Unicode ou encodés en pourcentage. `Rankbeam\Seo\I18n\Url::isValid()` remplace pour cela le `FILTER_VALIDATE_URL` de PHP, limité à l'ASCII. Choisissez une forme cohérente par URL afin que canonical, hreflang et sitemap correspondent octet par octet.

## Langues prises en charge et limites {#which-languages-are-supported-and-what-that-means}

Les paquets fournissent les textes et l'orientation des analyses pour les 17 locales ci-dessous. Ce tableau décrit la couverture technique, pas une approbation éditoriale native ni un rendu garanti sur un serveur non configuré. Les analyses de mots japonais et chinois exigent un ICU utilisable ; sinon, les vérifications concernées sont ignorées. Le rendu non latin demande des polices adaptées.

`tests/Feature/I18n/SupportedLanguagesTest.php` dans le Core contrôle les locales, codes hreflang et budgets. `tests/Feature/OnPage/LanguageSupportMatrixTest.php` dans Pro contrôle les moteurs d'analyse.

| Langue | Locale | Titre / description | Comptage des mots | Correspondance des mots-clés | Lisibilité |
|---|---|---|---|---|---|
| Anglais | `en` | 60 / 160 | Espaces | Snowball | Flesch Reading Ease |
| Italien | `it` | 60 / 160 | Espaces | Snowball | Gulpease |
| Allemand | `de` | 60 / 160 | Espaces | Snowball | Wiener Sachtextformel |
| Français | `fr` | 60 / 160 | Espaces | Snowball | Kandel-Moles |
| Espagnol | `es` | 60 / 160 | Espaces | Snowball | Fernández-Huerta |
| Portugais du Brésil | `pt_BR` | 60 / 160 | Espaces | Snowball | Martins |
| Néerlandais | `nl` | 60 / 160 | Espaces | Snowball | Flesch-Douma |
| Turc | `tr` | 60 / 160 | Espaces | Snowball | Ateşman |
| Russe | `ru` | 60 / 160 | Espaces | Snowball | Oborneva |
| Polonais | `pl` | 60 / 160 | Espaces | Snowball | Pisarek |
| Japonais | `ja` | 30 / 80 | Dictionnaire ICU | Exacte avec repli de casse | Heuristique, **sans score** |
| Chinois simplifié | `zh_CN` | 30 / 80 | Dictionnaire ICU | Exacte avec repli de casse | Heuristique, **sans score** |
| Chinois traditionnel | `zh_TW` | 30 / 80 | Dictionnaire ICU | Exacte avec repli de casse | Heuristique, **sans score** |
| Coréen | `ko` | 30 / 80 | Espaces | Exacte avec repli de casse | Heuristique, **sans score** |
| Grec | `el` | 60 / 160 | Espaces | Snowball | LIX |
| Ukrainien | `uk` | 60 / 160 | Espaces | Exacte avec repli de casse | LIX |
| Tchèque | `cs` | 60 / 160 | Espaces | Snowball | LIX |

Trois limites accompagnent cette couverture :

- **Snowball est intégré depuis Pro 2.37.** Douze langues utilisent les algorithmes figés en 3.1.1, sans dépendance optionnelle. L'ukrainien et CJK utilisent une correspondance d'identité plutôt que des règles de suffixes inventées. L'identité peut manquer les formes fléchies ; le stemming peut rapprocher des mots distincts. Voir [réglages et migration](/fr/pro/on-page-checklist#upgrading-from-pro-2-36).
- **« Heuristique, sans score » et LIX diffèrent.** Japonais, chinois et coréen reçoivent un niveau consultatif issu de la longueur des phrases et de la part de kanji, avec un score `null`. Grec, ukrainien et tchèque utilisent LIX faute de formule dédiée implémentée. LIX ne demande pas de syllabes, mais ses seuils ne sont pas calibrés pour chaque langue. Toutes les formules utilisent des entrées estimées ; voir [limites statistiques](/fr/pro/on-page-checklist#text-statistics-and-api-limits).
- **Les traductions des paquets sont des premières versions**, sauf relecture native mentionnée dans `TRANSLATING.md`. Les textes italiens ont une relecture créditée ; les autres attendent un réviseur. Cette mention n'approuve pas les présentes traductions de documentation.

Une locale absente du tableau peut utiliser les textes anglais, les budgets d'écriture ou par défaut, la correspondance d'identité, LIX ou une heuristique. Ce repli n'est pas une prise en charge validée de la langue. Le bloc `analysis` identifie écriture, segmentateur, stemmer et méthode de lisibilité : examinez aussi leur disponibilité et les jugements ignorés.

### Atteindre les moteurs pertinents localement {#reaching-the-search-engines-that-matter-locally}

La couverture technique inclut les robots et balises de vérification : Naver pour la Corée, Seznam pour la Tchéquie, Yandex pour des sites ukrainiens ou russes, entre autres. Voir [moteurs régionaux](#regional-search-engines) et [vérification](#site-verification).

## Fonctions des paquets complémentaires {#what-the-other-packages-add}

- **laravel-seo-filament** utilise la même politique pour les compteurs et l'aperçu SERP. Depuis 1.9, il modifie [une ligne `seo_meta` par langue](/fr/guide/filament#several-languages), avec onglets possédant leurs compteurs, aperçus et indicateurs, ou en suivant le sélecteur d'un plugin de traduction.
- **laravel-seo-pro** utilise cette politique pour `title_length`, `description_length` et les prompts IA. L'analyse linguistique inclut segmentation ICU pour chinois, japonais et thaï, Snowball, correspondance avec `CaseFolder`, formules publiées avec entrées estimées pour dix langues, heuristiques CJK explicites, LIX pour grec/ukrainien/tchèque, mots vides pour 16 langues, vérifications `html lang` et de réciprocité hreflang, prompts indiquant la langue et rapports Chrome pour les écritures que dompdf ne rend pas. Voir [checklist](/fr/pro/on-page-checklist#keyword-matching), [problèmes détectés](/fr/pro/scan-issues), [aide IA](/fr/pro/ai-assist#output-language) et [rapports](/fr/pro/reports#reports-in-every-script-browsershot-renderer).

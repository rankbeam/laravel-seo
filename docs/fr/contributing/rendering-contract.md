---
description: "La liste de contrôle de référence que le head de chaque pile front-end doit respecter pour afficher les données SEO de Rankbeam. Elle définit les exigences des tests du moteur de rendu et des applications de référence."
---

# Le contrat de rendu {#the-rendering-contract}

Cette page constitue la **liste de contrôle de référence unique** que le `<head>` de chaque pile front-end doit respecter lorsqu’il affiche les données SEO de Rankbeam. Elle fait autorité pour :

- les tests unitaires de structure du rendu dans le cœur (`tests/Unit/Services/RenderingContractTest.php`), la partie rapide et indépendante du framework couverte par la CI du package ;
- les applications de référence par pile dans `rankbeam-examples` (Blade, Inertia + Vue / React / Svelte, Livewire), dont les tests navigateur et SSR vérifient les mêmes assertions dans un vrai DOM ;
- les guides des frameworks (Blade, Inertia et JSON, Livewire), qui ne doivent jamais documenter une méthode contraire à ce contrat.

Si une pile ne peut pas satisfaire une clause, il s’agit d’un **défaut ou d’une limitation documentée**, pas d’une raison d’assouplir le contrat. La couche de données (`SEOResolver` → `SEOData` immuable → `TagRenderer`) est indépendante du framework. Seule varie la manière dont *les données résolues atteignent le DOM, survivent à la navigation côté client et restent visibles pour les robots*. C’est précisément ce que fixe ce contrat.

> Cette spécification a été renforcée par une revue de conception indépendante.
> Une nouvelle revue n’est nécessaire qu’en cas de modification substantielle.

---

## 1. Valeurs : contenu d’un `<head>` conforme {#_1-values-—-what-a-compliant-head-contains}

### Titre, description, URL canonique {#title-description-canonical}

- **Exactement un `<title>`**, contenant le titre *résolu*, sans double suffixe : le résolveur ajoute `seo.title_suffix` une seule fois et vérifie que le titre ne se termine pas déjà par ce suffixe.
- **Une meta description**, uniquement lorsqu’une description a été résolue, sans balise vide.
- **Un `<link rel="canonical">`**.

### Robots {#robots}

- Émettre `<meta name="robots">` **uniquement lorsque la directive diffère de la valeur par défaut du site**. Un `index,follow` redondant ajoute du bruit ; son *absence* correspond précisément à `index,follow` pour un robot. La comparaison ignore les espaces (`index, follow` ≡ `index,follow`) ; une directive différente est émise **telle quelle**. `seo.robots.emit_default = true` force l’émission de la balise.
- Prendre en charge les **directives avancées** de manière déterministe : `noindex`, `nofollow`, `noarchive`, `nosnippet`, `max-snippet`, `max-image-preview`, `max-video-preview`, `notranslate`, `unavailable_after`. Ce sont des chaînes résolues ; leur **priorité suit la chaîne du résolveur** (global → route → modèle → explicite). Mêmes entrées ⇒ même sortie.

### Open Graph {#open-graph}

- `og:title`, `og:description`, `og:type`, `og:url`, `og:site_name`, `og:locale`.
- `article:*` (`published_time`, `modified_time`, `author`, `section`, `tag`) **uniquement lorsque `og:type === 'article'` et que la valeur existe réellement**, jamais avec des valeurs inventées ni sur une page qui n’est pas un article.
- `og:image` avec `og:image:width` / `og:image:height` / `og:image:alt` et `og:image:type` **lorsque ces informations sont connues**. Les images multiples sont **regroupées** : chaque `og:image` est immédiatement suivi de ses propres propriétés de dimensions, de texte alternatif et de type.

### Twitter Cards {#twitter-cards}

- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image` et `twitter:image:alt` lorsqu’un texte alternatif est connu.
- `twitter:site` et `twitter:creator` sont **facultatifs et indépendants** : l’un peut être présent sans l’autre, et aucun n’est inventé à partir de l’autre.

### hreflang et locale {#hreflang-locale}

- Hreflang dispose d’un chemin de résolution dédié via le hook `getSEOAlternates()` d’un modèle.
- Les variantes hreflang, lorsqu’elles existent, sont **absolues, normalisées et uniques par langue**, avec réciprocité lorsque les données sont complètes. `x-default` n’apparaît que s’il est configuré.
- `og:locale:alternate` reflète **uniquement** les locales disposant d’une véritable variante sociale : convertir `en-US` → `en_US`, comparer les formes converties et ne pas exiger une égalité littérale.
- `<html lang>` correspond à la locale résolue. Cette clause fait partie du contrat même si c’est *l’application* qui émet l’élément `<html>`.

### JSON-LD par page {#per-page-json-ld}

- Le JSON-LD est analysable et protégé contre `</script>` : la charge utile est encodée avec `JSON_HEX_TAG` pour empêcher qu’une valeur ferme prématurément l’élément script, une protection contre les XSS persistantes.
- **Plusieurs blocs `<script>` OU un `@graph` combiné** sont acceptables.
- Un `@id` stable est utilisé **uniquement lorsque des entités sont réellement liées** (Organization ↔ WebSite ↔ WebPage). Il n’est *pas* obligatoire sur des nœuds autonomes.

---

## 2. Normalisation et invariants {#_2-normalization-invariants}

- **URL `http(s)` absolues** pour `canonical`, `og:url`, `og:image` et `twitter:image`. **Aucune balise vide ou nulle** ne doit atteindre le DOM.
- **`canonical` et `og:url` DOIVENT aboutir à la même URL normalisée.** Une divergence constitue une **ERREUR BLOQUANTE**, pas un avertissement.
- La **politique de normalisation canonique est cohérente** partout : protocole, hôte, port, casse du chemin, liste de paramètres de requête autorisés et barre oblique finale sont toujours traités de la même manière. Les pages indexables sont **autoréférentes** ; une page `noindex` n’hérite **pas** de la stratégie canonique d’une autre page.
- **L’échappement dépend de la destination** : attribut HTML, texte et JSON utilisent chacun l’encodeur approprié. Les assertions comparent les **valeurs sémantiques décodées, pas les octets**.
- **La parité entre moteurs de rendu est sémantique, pas octet par octet.** `render()` (HTML) ≡ `toArray()` ≡ `toInertiaHead()` *après normalisation*. L’ordre et la forme des balises peuvent légitimement différer entre ces représentations. Les règles des propriétés uniques ou répétables sont explicites : un `og:title`, plusieurs `article:tag`.
- **Responsabilité des balises** : un moteur côté client remplace les balises *du package*, identifiées par une clé (voir §4), sans supprimer les autres balises de l’application.

---

## 3. Comportement : navigation côté client {#_3-behaviour-—-client-side-navigation}

Après chaque visite Inertia ou navigation Livewire `wire:navigate` :

- il existe **exactement un exemplaire de chaque balise unique** (`<title>`, description, canonical, chaque `og:*`/`twitter:*`), **sans valeur périmée** ;
- **le JSON-LD ne s’accumule pas** : le schéma de la page précédente est supprimé, pas conservé sous le nouveau. Livewire traite `<script>` comme une ressource non supprimable ; les scripts de schéma portent donc `data-seo-schema` et un identifiant par URL, puis ceux de la page précédente sont supprimés lors de `livewire:navigated` (voir le guide Livewire) ;
- le passage d’une **page riche en métadonnées à une page minimale supprime les balises supplémentaires** : la page minimale ne conserve pas la description, les balises OG ou le schéma de la précédente ;
- **aucun avertissement d’hydratation** n’apparaît et les métadonnées sont sémantiquement identiques avant et après l’hydratation.

---

## 4. Clés head-key d’Inertia et responsabilité des balises {#_4-inertia-head-keys-tag-ownership}

`toInertiaHead()` ajoute un **`head-key`** stable à chaque entrée meta/link. Inertia déduplique les éléments du head à l’aide de cet attribut : une balise du `<Head>` d’une page ayant le même `head-key` qu’une balise du layout la *remplace* au lieu d’ajouter un doublon.

- Clé de base = `name ?? property` pour les meta, `rel` pour les liens.
- **Les balises répétables sont différenciées** pour conserver des clés uniques : `article:tag` → `article:tag`, `article:tag:1`, … ; hreflang → `alternate:en-US`, `alternate:fr-FR`.

Dans les templates, liez cet attribut avec **`:head-key`**, et non avec le `:key` de Vue : celui-ci est la clé de réconciliation de `v-for` et n’agit pas sur la déduplication du head par Inertia.

---

## 5. Visibilité pour les robots : modes explicites {#_5-crawler-visibility-explicit-modes}

- **Le SSR et le prérendu** DOIVENT émettre l’intégralité du contrat dans le **HTML brut de la réponse HTTP**. Cela se teste séparément du DOM hydraté, avec JavaScript désactivé.
- **Un rendu uniquement CSR ne peut pas être déclaré conforme pour les robots.** Par défaut, sans SSR, Inertia injecte les métadonnées *côté client* : le HTML initial récupéré par un robot ne contient pas de métadonnées SEO. Cette limite est documentée : **des métadonnées visibles pour les robots nécessitent Inertia SSR ou du prérendu**, et le JSON-LD destiné aux robots doit être rendu côté serveur.

---

## 6. Hors périmètre {#_6-out-of-scope-non-goals}

- **Responsabilités de l’application, pas du moteur de rendu** : `charset`, `viewport`, favicons. `<meta charset>` doit précéder toute métadonnée non ASCII ; l’application gère donc l’ordre de ces éléments dans le head.
- **Les tests de bout en bout vérifient uniquement la sortie émise.** Ils ne vérifient **ni** l’indexation Google, **ni** la *sélection* de l’URL canonique, l’éligibilité aux résultats enrichis ou le classement. Ils ne vérifient **pas** non plus le MIME ou la disponibilité des images distantes. Ces contrôles relèvent de tests d’intégration/HTTP facultatifs, jamais de la matrice navigateur.

---

## 7. État de conformité {#_7-conformance-status}

Voici les preuves actuelles pour chaque clause. **Unitaire** = `RenderingContractTest` (cœur, CI du package). **Navigateur/SSR** = `rankbeam-examples` (matrice planifiée). **Application** = responsabilité de l’application hôte. **Prévu** = objectif du contrat dont les données ne sont pas encore représentées dans `SEOData` ; le moteur émet donc le sous-ensemble sûr.

| Clause | État |
|---|---|
| Exactement un `<title>` résolu, sans double suffixe | **Unitaire** + navigateur |
| Meta description uniquement si elle existe | **Unitaire** + navigateur |
| Un `<link rel="canonical">`, jamais vide | **Unitaire** + navigateur |
| Robots émis uniquement si différents du défaut, tels quels ; option `emit_default` | **Unitaire** + navigateur |
| Directives robots avancées selon la priorité du résolveur | **Unitaire** (résolveur) |
| `og:title/description/type/url/site_name/locale` ; locale `en-US`→`en_US` | **Unitaire** + navigateur |
| `article:*` uniquement si `og:type=article` et valeur réelle | **Unitaire** + navigateur |
| `og:image` présent et absolu | **Unitaire** + navigateur |
| `og:image:width/height/alt`, `og:image:type`, regroupement d’images multiples | **Prévu** : `SEOData` contient une seule chaîne `ogImage`, sans dimensions, texte alternatif ni type pour le moment. Le moteur émet un `og:image` absolu. |
| `twitter:card/title/description/image` ; `site`/`creator` indépendants | **Unitaire** + navigateur |
| `twitter:image:alt` | **Prévu** : aucun champ de texte alternatif d’image pour le moment. |
| hreflang absolus, uniques par langue | **Unitaire** + navigateur |
| Réciprocité hreflang, `x-default` si configuré | Navigateur, selon les données |
| `og:locale:alternate` reflète les variantes sociales réelles | **Prévu** : aucune correspondance de variantes sociales par locale pour le moment. |
| Parité de `<html lang>` | **Application** + vérification navigateur |
| JSON-LD analysable et protégé contre `</script>` | **Unitaire** + navigateur |
| Plusieurs scripts OU `@graph` ; `@id` stable entre entités liées | **Unitaire** (graphe marchand) + navigateur |
| URL absolues ; aucune balise vide/nulle | **Unitaire** + navigateur |
| `canonical` ≡ `og:url`, erreur bloquante en cas de divergence | **Unitaire** + navigateur |
| Normalisation canonique cohérente ; autoréférence ; isolation de noindex | Navigateur |
| Échappement par destination ; parité sémantique décodée | **Unitaire** |
| Parité sémantique entre moteurs (`render()` ≡ `toArray()` ≡ `toInertiaHead()`) | **Unitaire** |
| `head-key` Inertia stable et balises répétables différenciées | **Unitaire** + navigateur |
| Navigation client : balises uniques à jour, pas d’accumulation de JSON-LD, suppression | Navigateur : le moteur fournit les hooks `data-seo-schema` nécessaires au nettoyage |
| Aucun avertissement d’hydratation ; parité avant/après hydratation | Navigateur |
| SSR : contrat complet dans le HTML brut ; CSR seul documenté comme non conforme | Navigateur + documentation |

Les **clauses prévues** sont des lacunes délibérées et documentées. Le contrat reste l’objectif durable ; ces extensions additives et rétrocompatibles feront l’objet d’un travail ultérieur, car elles nécessitent de nouveaux champs/colonnes `SEOData` et une version mineure SemVer. Le moteur émet aujourd’hui le sous-ensemble sûr et n’invente jamais une valeur dont il ne dispose pas.

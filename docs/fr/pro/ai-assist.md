---
description: "Assistance IA facultative avec votre propre clé : suggestions de titres et métadonnées, explications des problèmes, réécriture de descriptions et suggestions schema.org. Désactivée par défaut."
---

# Assistance IA {#ai-assist}

Une assistance IA facultative **avec votre propre clé** : suggestions de titres et meta descriptions, explications des problèmes de scan en langage courant, **réécriture de description** et **suggestion de données structurées schema.org**. Elle est **désactivée par défaut** : aucun chemin de code IA ne s’exécute lorsque l’indicateur est désactivé.

Trois principes guident la conception :

- **Votre clé, votre fournisseur.** Les requêtes partent de *votre serveur* directement vers le fournisseur *que vous configurez* : Anthropic, OpenAI, Google ou serveur local / compatible OpenAI. Elles sont facturées à votre compte lorsque cela s’applique. Le package ne sert pas d’intermédiaire, ne mesure ni ne revend cette consommation et n’envoie aucune télémétrie.
- **Acceptation explicite des suggestions interactives.** Le modèle propose, vous choisissez. Une suggestion ou correction n’est *appliquée que par une action explicite* : remplir un champ ou enregistrer une valeur relue en cliquant sur Apply. Les validations habituelles, compteurs de longueur par écriture, avertissements de l’évaluateur et validateur de schéma, s’appliquent comme pour une saisie manuelle. La commande de remplissage en masse décrite plus bas est une écriture explicitement lancée ; elle n’exige pas la relecture de chaque champ généré avant enregistrement.
- **Erreurs non bloquantes.** Clé absente ou invalide, compte sans crédit, limitation de débit ou délai dépassé produisent un message dans l’interface. Ils ne doivent jamais empêcher d’enregistrer, de rendre une page ou de scanner.

## Les fournisseurs {#providers-at-a-glance}

Choisissez selon vos accès, vos exigences de données et le coût. Les quatre intégrations proposent les mêmes tâches, mais modèles pris en charge, format de sortie, vitesse et qualité peuvent varier.

| Fournisseur | Modèle fourni par défaut | Sortie structurée | Coût illustratif | Usage |
|---|---|---|---|---|
| **Local**, Ollama / LM Studio / vLLM | `llama3.1`, à remplacer selon votre serveur | Au mieux via `response_format` | **0 $ de frais API** en auto-hébergement ; l’infrastructure reste payante | Maîtrise de la destination des données |
| **OpenAI** | `gpt-5.5` | Structured Outputs si le modèle le prend en charge | Environ 0,005 $ par suggestion selon les hypothèses ci-dessous | Compte OpenAI existant |
| **Anthropic** | `claude-opus-4-8` | `output_config.format` si pris en charge | Environ 0,015 $ par suggestion selon ces hypothèses | Compte Anthropic existant |
| **Google** | `gemini-2.5-flash` | `responseSchema` si pris en charge | Environ 0,0005 $ par suggestion selon ces hypothèses | Compte Google ; vérifier quotas et tarifs du modèle |

Ces noms décrivent la configuration livrée, pas une disponibilité actuelle garantie sur votre compte. Les coûts reposent sur les hypothèses d’exemple du package, pas sur des tarifs actuels vérifiés. Comportements des intégrations et observations des essais publiés :

- **Sortie structurée.** Les chemins OpenAI, Google et Anthropic compatibles reçoivent un schéma JSON ; les réponses invalides échouent proprement. Les serveurs locaux reçoivent `response_format` au mieux. Si cette option est ignorée, un parseur tolérant renvoie une liste valide ou un échec, sans appliquer une sortie partielle.
- **Le raisonnement modifie la consommation de tokens.** L’essai Gemini décrit utilisait environ 500 tokens de raisonnement cachés et 100 tokens visibles pour une description. Les appels Anthropic testés ne rapportaient aucun token de raisonnement caché. Ce n’est pas une propriété universelle de ces familles. Les tokens cachés peuvent être facturés en sortie, d’où le plancher de raisonnement décrit plus bas.
- **Modèles configurables.** Définissez `SEO_PRO_AI_MODEL` sur un modèle disponible et compatible avec l’API et les paramètres de l’adaptateur. Les exemples incluent `claude-haiku-4-5`, `gpt-5.4-mini` et `gemma-3-12b-it`. Vérifiez la compatibilité et la qualité avant de traiter une collection.

## Installation {#setup}

Activez la fonction et placez la clé du fournisseur dans l’environnement. Pour changer de fournisseur cloud, modifiez fournisseur et clé, puis vérifiez tout modèle personnalisé. L’adaptateur local nécessite aussi l’URL du serveur.

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip Une clé API est distincte d’un abonnement Claude ou ChatGPT
Un **abonnement** Claude Code, Claude.ai ou ChatGPT ne finance pas l’**API**. `SEO_PRO_AI_API_KEY` doit être une *clé API à l’usage* issue de la console développeur du fournisseur, ou une clé Google AI Studio, avec ses propres crédits et quotas. Un compte limité à l’abonnement ou sans financement peut être authentifié puis renvoyer une erreur de **crédit ou quota épuisé**. Voir [Résoudre les problèmes](#troubleshooting).
:::

Le bloc `ai` de `config/seo-pro.php` expose `timeout`, `max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models`, `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model`, modèle économique pour le remplissage en masse (voir [Coût](#cheaper-bulk-generation)), `retry`, la table `pricing` et le sous-bloc `local`. Tout est décrit dans [Limites et réglages](#limits-and-tuning).

::: warning Clé et configuration en cache
La configuration ne contient que le **nom** de la variable d’environnement, `api_key_env`, jamais la clé. `php artisan config:cache` ne l’écrit donc jamais dans `bootstrap/cache/config.php`. En contrepartie, `.env` n’est pas chargé avec une configuration en cache : définissez `SEO_PRO_AI_API_KEY` comme véritable variable d’environnement du serveur.
:::

## Inférence locale et options cloud {#running-at-0-and-the-cheapest-paid-option}

- **L’auto-hébergement évite les frais API par token d’un fournisseur.** Matériel, électricité et exploitation ont toujours un coût. Le contenu reste dans votre réseau uniquement si le serveur d’inférence et ses dépendances y restent aussi.
- **Quotas et tarifs Google dépendent du modèle et de l’offre.** Une clé AI Studio, `aistudio.google.com/apikey`, au format `AIza…`, peut permettre des essais sur une offre gratuite. Vérifiez ses limites pour votre charge avant d’activer une facturation si nécessaire. `gemini-2.5-flash` est le défaut livré. Tous les modèles Gemini et Gemma n’ont pas les mêmes capacités de raisonnement : `reasoning_models` applique des motifs de noms configurés, pas un test de capacités.

Pour contrôler où l’inférence s’exécute :

- **Local / compatible OpenAI.** Utilisez `provider=local` avec un serveur compatible OpenAI Chat Completions, **Ollama**, **LM Studio**, **vLLM**, **LocalAI**, ou une passerelle distante comme **OpenRouter**. Définissez `SEO_PRO_AI_LOCAL_BASE_URL` sur la racine de son API, à laquelle `/chat/completions` sera ajouté, et choisissez un `SEO_PRO_AI_MODEL` disponible. Une passerelle distante reçoit les données hors de votre réseau et peut vous facturer : le nom d’adaptateur `local` ne garantit pas une inférence locale.

::: warning `base_url` est validé ; localhost nécessite une activation explicite
`base_url` est un réglage privilégié, validé par le même `SsrfGuard` que les autres récupérations sortantes : http/https uniquement, sans userinfo et, par défaut, résolution vers une adresse **publique**. Une URL erronée ou malveillante ne peut ainsi servir à sonder les services internes. Un vrai serveur local sur `127.0.0.1`, adresse privée, nécessite `seo-pro.ai.local.allow_local_addresses`, soit `SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`. Laissez cette option désactivée pour une passerelle publique comme OpenRouter. Le chemin de requête est fixe et les redirections ne sont jamais suivies, pour empêcher un renvoi de la clé vers un autre hôte.
:::

::: tip Contrôler le raisonnement sur les modèles Ollama compatibles
Un modèle local de raisonnement peut dépasser le délai par défaut. Si le modèle et la version du serveur le permettent, `['think' => false]` dans `seo-pro.ai.local.extra_body` peut désactiver le raisonnement pour les suggestions. La prise en charge varie ; consultez la [documentation Ollama](https://docs.ollama.com/capabilities/thinking). Augmentez `seo-pro.ai.timeout` si nécessaire. Le package n’envoie pas `temperature`, car certains modèles le refusent.
:::

## Coût {#cost}

Le package ne prend aucune majoration : vous payez directement le fournisseur. L’auto-hébergement évite ses frais API, mais conserve les coûts d’infrastructure. Deux montants comptent : le coût **par suggestion** en usage interactif et celui du **remplissage en masse** d’une collection.

La table `seo-pro.ai.pricing`, en dollars US par million de tokens, convertit une estimation de tokens en montant dans la confirmation du remplissage. Ce sont des **hypothèses d’estimation livrées**, pas des tarifs publics actuels vérifiés. **Remplacez-les par les tarifs publiés actuels de votre fournisseur** pour une estimation adaptée :

| Motif de modèle | Entrée, $/1M | Sortie, $/1M |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

L’exemple publié applique ces hypothèses à la consommation d’une page testée, un titre et une description. La dernière colonne applique la réduction d’exemple de 50 % des lots aux adaptateurs compatibles. Il ne s’agit pas de devis aux tarifs actuels.

| Fournisseur / modèle | Environ, par paire de suggestions | Environ, pour 1 000 enregistrements | Environ, pour 1 000 enregistrements avec `--batch` |
|---|---|---|---|
| Local `gemma`/`llama`, Ollama | **0 $ de frais API** | **0 $ de frais API** | Sans objet, non implémenté par l’adaptateur |
| Google `gemini-2.5-flash`, payant | Environ 0,001 $ | Environ 0,40 $ | Sans objet, non implémenté par l’adaptateur Rankbeam |
| OpenAI `gpt-5.5` | Environ 0,008 $ | Environ 5,25 $ | **Environ 2,63 $**, réduction de 50 % |
| Anthropic `claude-opus-4-8` | Environ 0,03 $ | Environ 20 $ | **Environ 10 $**, réduction de 50 % |

La commande affiche une estimation à environ ±50 %, mais **ce n’est ni un plafond de dépenses ni une marge d’erreur garantie**. Entrées, sorties et tarifs réels modifient le total. L’estimation modélise la sortie visible ; le raisonnement caché facturé peut la dépasser. Sans entrée de prix pour un modèle, seule l’estimation de tokens est affichée.

### Génération en masse moins coûteuse {#cheaper-bulk-generation}

Définissez `seo-pro.ai.bulk_model`, `SEO_PRO_AI_BULK_MODEL`, pour choisir un autre modèle **uniquement pour le remplissage en masse**, `seo-pro:ai-fill` / `SeoPro::aiFill()`. Filament et `seo-pro:ai-suggest` conservent `model`. Si `bulk_model` vaut null, le remplissage utilise aussi `model`. L’estimation applique le motif de prix du modèle sélectionné. Évaluez des sorties représentatives avant d’augmenter le volume ; un modèle moins cher n’est pas automatiquement adapté.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

Les exemples du package utilisent **anthropic** `claude-haiku-4-5`, **openai** `gpt-5.5-mini`, **google** `gemini-2.5-flash` ou un modèle **local** plus petit. Un motif tarifaire peut correspondre à un nom non proposé par le fournisseur. Confirmez l’identifiant réel, la compatibilité API et le prix avant configuration.

Exemple de **100 pages**, chacune sans titre ni description, soit 200 appels fournisseur, selon les prix livrés et le modèle d’estimation de 600 tokens d’entrée + 150 de sortie par appel. Comparaison du modèle principal et de son `bulk_model` économique :

| Fournisseur | Modèle principal, 100 pages | `bulk_model` économique, 100 pages |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **4,05 $** | `claude-haiku-4-5` ≈ **0,27 $** |
| **OpenAI** | `gpt-5.5` ≈ **1,05 $** | `gpt-5.5-mini` ≈ **0,11 $** |
| **Google** | `gemini-2.5-pro` ≈ **0,45 $** | `gemini-2.5-flash` ≈ **0,04 $** |
| **Local**, Ollama / vLLM | Tout modèle, **0 $ de frais API** | Tout modèle, **0 $ de frais API** |

Ces estimations illustratives n’ont ni marge garantie de ±50 % ni provision pour le raisonnement caché. Actualisez `seo-pro.ai.pricing` avec les tarifs publiés du fournisseur choisi avant de vous y fier.

## Langue de sortie {#output-language}

Chaque prompt nomme la langue de la page et son code BCP-47, par exemple « en portugais brésilien, pt-BR, langue de la page, quelle que soit une autre langue présente dans l’extrait ». Le contexte transmis au modèle contient aussi une ligne `Language:`, depuis Pro 2.34. Auparavant, le prompt demandait la langue du contenu source et laissait le modèle la déduire d’un extrait court ou mixte ; une page turque contenant une marque anglaise pouvait ainsi recevoir une réponse anglaise. La locale est celle sous laquelle les métadonnées de la page sont résolues, ou celle de l’application à défaut. Elle détermine aussi le [budget de longueur](/fr/guide/multilingual#title-and-description-budgets-per-script) : une page japonaise demande donc des titres d’environ 30 caractères *en japonais*.

Depuis Pro 2.36, une locale de contenu explicite contrôle ensemble la ligne de métadonnées, les hooks de contenu et la langue du prompt. La langue d’interface de l’opérateur reste inchangée.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

Les arguments positionnels existants restent inchangés. Omettez `locale:` pour utiliser le défaut de `seoData()` du modèle, permettant aux modèles de traduction distincts de déclarer leur langue. L’extrait utilise `getContentForSEO()` si son résultat est non vide, puis les champs de contenu configurés comme repli. Filament 1.11 transmet automatiquement la locale de l’onglet sélectionné, y compris dans les modes à une locale et avec sélecteur de page.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

`plan()`, `fill()` et `submitBatchFill()` acceptent aussi un `locale:` final. Utilisez la même locale dans `FillProgress(..., locale: 'it')` et lors de la soumission. Chaque élément du lot conserve sa locale ; la collecte la réutilise et revérifie sa ligne de métadonnées avant écriture. Les exécutions avec locale explicite ont des fichiers de reprise distincts et des marqueurs de traitement qui distinguent les langues. Relancez la même commande pour collecter. Les jobs personnalisés doivent sérialiser et transmettre la locale de contenu.

Les points de reprise antérieurs à Pro 2.36 ne stockaient pas de locale. Un ancien lot en attente est conservé mais sa collecte automatique est refusée. Réconciliez ses résultats fournisseur et sa locale prévue avant de l’abandonner avec `--fresh`, car une nouvelle soumission peut facturer à nouveau le même travail. Un ancien point de reprise séquentiel contenant des enregistrements traités nécessite aussi une réconciliation avant réinitialisation.

### Évaluation par langue {#per-language-evaluation}

Le dépôt source Pro contient 170 pages d’entrée réparties sur 17 locales et un outil d’évaluation facultatif. Ces pages passent des contrôles de structure et des heuristiques de langue de base ; leur validation native indépendante reste en attente.

L’outil contrôle **titres et descriptions**, enregistre les longueurs en graphèmes et les indices de langue/écriture, puis conserve chaque réponse fournisseur avant les assertions. Titres courts, textes mixtes et caractères communs au chinois/japonais peuvent rester incertains. Une détection de portugais ne prouve pas un usage brésilien, et les contrôles partiels des caractères chinois ne certifient pas la qualité rédactionnelle régionale.

Les exécutions réelles nécessitent `SEO_PRO_AI_EVAL=1`, une sélection explicite `SEO_PRO_AI_EVAL_LOCALES` et un identifiant `SEO_PRO_AI_EVAL_RUN`. Elles peuvent entraîner des frais fournisseur et ne s’exécutent jamais par défaut. Chaque exécution lie ses preuves au fournisseur, au modèle demandé et renvoyé, aux empreintes des fixtures, requêtes et code, ainsi qu’aux horodatages. Les tentatives échouées sont conservées. La reprise réutilise les réponses enregistrées ; une requête interrompue nécessite une nouvelle tentative explicite, car elle peut déjà avoir atteint le fournisseur.

Les preuves sont stockées dans `storage/app/seo-ai-evals/<run-id>/` dans l’environnement de test source. Le `README.md` des fixtures décrit le schéma versionné et les commandes. Les relecteurs natifs évaluent des empreintes de sortie exactes dans des enregistrements distincts. Un contrôle automatique réussi n’est ni une validation native ni une garantie de texte publiable.

## Limites et réglages {#limits-and-tuning}

Tous les réglages se trouvent dans le bloc `ai` de `config/seo-pro.php` :

- **`timeout`**, 15 secondes par défaut, `SEO_PRO_AI_TIMEOUT` : limite aussi l’appel synchrone effectué à l’ouverture de la fenêtre de suggestions Filament. Ce délai reste court pour l’usage interactif. Un **modèle local ou de raisonnement lent peut dépasser 15 secondes**. Augmentez-le avec `SEO_PRO_AI_TIMEOUT` si nécessaire ; voir aussi l’option Ollama `think => false`. Un dépassement reste non bloquant : erreur dans l’interface, jamais sauvegarde empêchée.
- **`max_input_chars`**, 6 000 par défaut : limite de coût et de confidentialité sur le texte de page envoyé à chaque requête, HTML retiré.
- **`max_output_tokens`**, 1 000 par défaut : plafond de base des tokens générés. Une réponse qui l’atteint produit un échec distinct `truncated`, jamais une demi-réponse silencieuse.
- **`token_budgets`** : plafonds par tâche, `suggestions` 800, `explanation` 600, `rewrite` 300, `schema_suggestion` 700. Ils restent sous le défaut global, mais le plancher de raisonnement s’applique ensuite.
- **`reasoning_models`** et **`reasoning_min_output_tokens`**, 2 000 par défaut : les modèles dont le nom correspond à un motif, `*gemma*`, `gemini-2.5-*`, `o1*`/`o3*`/`o4*`, voient leur budget relevé au plancher. Un modèle de raisonnement utilise des tokens cachés avant la sortie visible ; un petit budget peut donc tronquer sa réponse.
- **`suggestion_count`**, 3 par défaut : nombre de propositions de titre ou description demandées.
- **`retry`** : nouvelles tentatives automatiques uniquement pour les échecs *transitoires* ; voir [Traitement des réponses](#how-replies-are-handled).

## Dans Filament {#in-filament}

Avec les packages Filament facultatifs, `rankbeam/laravel-seo-filament` >= 1.1, l’activation de l’IA ajoute :

- **Suggest with AI** sur les champs titre et description SEO des ressources utilisant la section SEO, sur les pages d’édition. La fenêtre affiche les alternatives générées avec leur longueur ; en choisir une remplit le champ pour relecture.
- **Explain (AI)** dans le tableau des problèmes : explication courte et concrète du problème et de sa correction.
- **Rewrite description (AI)** à côté d’Explain : proposition d’une meta description dans le budget de la page, 160 caractères en latin, environ 80 en CJK, selon la [politique du cœur](/fr/guide/multilingual#title-and-description-budgets-per-script). Relisez-la dans la fenêtre ; **Apply rewrite** l’écrit dans `seo_meta`. Rien n’est enregistré avant cette action.
- **Suggest structured data (AI)** : proposition d’un type schema.org, Product, Article ou Breadcrumb, avec le JSON-LD construit. **Apply structured data** l’ajoute à `seo_meta.schema_jsonld`, même colonne que celle de [l’éditeur de données structurées](../guide/filament#structured-data-schema-org), où il reste modifiable. Une suggestion incomplète, par exemple Article sans auteur ni image, est affichée avec les champs manquants et **n’est pas appliquée**.

Les deux dernières actions sont des corrections limitées ; voir [Corrections encadrées](#bounded-fixes-propose-never-auto-apply).

## Corrections encadrées : proposition, jamais application automatique {#bounded-fixes-propose-never-auto-apply}

Deux actions vont au-delà d’une liste de suggestions : elles produisent une valeur unique *contrainte*, applicable en un clic. Elles restent des **propositions uniquement** : aucune persistance sans acceptation explicite.

- **Réécriture de description**, `SeoSuggestionService::rewriteDescription($model, $issue?)` : renvoie une meta description **dans le budget du cœur pour l’écriture de la page**, 160 en latin, environ 80 en CJK. C’est le même budget que celui des prompts de titre/description, choisi depuis la valeur résolue de la page. Si le modèle le dépasse, le texte est raccourci de façon déterministe à une frontière de phrase, puis de mot. Une réécriture acceptée ne peut donc pas déclencher à elle seule `description_too_long`. Le problème de scan transmis oriente la réécriture, par exemple description trop longue ou absente.
- **Suggestion de données structurées**, `SeoSuggestionService::suggestSchemaType($model)` : demande seulement une **recommandation de type et les valeurs des champs terminaux**, jamais du JSON-LD brut. Le code assemble ensuite le document avec `ProductSchema`, `ArticleSchema` ou `BreadcrumbSchema`, puis le valide avec `SchemaValidator`. Un `@type`, `@context` ou une structure inventée ne peut ainsi atteindre la page. Les valeurs proposées restent à vérifier éditorialement. Si un champ obligatoire manque, le document est signalé *incomplet* et bloqué. Dans un essai réel, un fournisseur a proposé Article pour une page courte ; la suggestion a été bloquée pour absence d’auteur et d’image, tandis que d’autres fournisseurs n’ont proposé aucun type.

## Headless {#headless}

Les mêmes fonctions en JSON, pour les scripts et applications sans Filament :

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

La sortie comprend les suggestions, ou le type recommandé avec JSON-LD construit et résultat de validation, le modèle utilisé et la **consommation de tokens par requête**, entrée / sortie / raisonnement. Ces comptes aident à estimer le coût aux tarifs du fournisseur ; ils ne sont pas une facture. La commande renvoie un code non nul en cas d’échec, avec l’erreur dans l’enveloppe JSON. Comme les autres actions, `seo-pro:suggest-schema` **propose uniquement** : il affiche le document sans rien écrire.

## Remplir les métadonnées manquantes en masse {#bulk-fill-missing-metadata}

Les fonctions ci-dessus proposent des valeurs. La fonction de lot qui **écrit** est `seo-pro:ai-fill`, ainsi que `SeoPro::aiFill()`, pour remplir les champs absents d’une collection de modèles.

```bash
# preview what would be written (no changes saved)
php artisan seo-pro:ai-fill "App\Models\Post" --dry-run

# fill missing descriptions across all configured models
php artisan seo-pro:ai-fill --field=description

# all configured (seo.audit.models / seo.sitemap.models) models, all fields
php artisan seo-pro:ai-fill --force
```

Elle parcourt les enregistrements, repère les **titres ou descriptions manquants**, sans valeur explicite *ni* repli calculé, selon **la même définition que [l’audit](/fr/guide/audit)**, les génère avec le service de suggestions et les enregistre.

Son périmètre est volontairement restreint :

- **Seules les lacunes sont remplies.** Si un champ existe ou peut être déduit, l’enregistrement est ignoré. Une valeur existante n’est **jamais écrasée**.
- **`--dry-run`** génère et affiche les valeurs sans les enregistrer, pour permettre leur examen sans écriture en base. **La simulation appelle quand même le fournisseur et peut être facturée.**
- **La commande écrit** : en production, elle demande confirmation sauf avec `--force`. `--field`, title | description | all, et `--limit` bornent l’exécution.
- Chaque champ rempli correspond à un appel au service de suggestions facturé à votre clé. L’exécution nécessite l’assistance IA activée.

### À grande échelle : rythme, estimation et reprise après interruption {#at-scale-pacing-a-cost-estimate-and-crash-resume}

Remplir des centaines ou milliers de modèles est une opération longue et potentiellement **payante**. La commande prévoit plusieurs contrôles :

- **Appels espacés.** `seo-pro.ai.fill.throttle_ms`, 200 par défaut, ajoute un délai entre appels pour éviter une rafale contre les limites du fournisseur. Définissez 0 pour un service local ou gratuit capable de suivre, ou augmentez le délai sur une offre limitée.
- **Estimation confirmée avant le premier appel.** À partir de `seo-pro.ai.fill.confirm_over` enregistrements, 100 par défaut, la commande affiche une estimation et demande confirmation avant tout appel :

  ```text
  About to fill ~890 missing fields via anthropic (claude-opus-4-8) across 948 records.
  Estimated ~667,500 tokens ≈ $18.02 (rough, ±50%).
  Continue? (yes/no) [no]
  ```

  Le montant provient de `seo-pro.ai.pricing` ; voir [Coût](#cost). Un modèle sans tarif, par exemple local, affiche seulement les tokens estimés, sans montant inventé. `--force` évite la demande pour l’automatisation. Une simulation demande aussi confirmation, car elle effectue les mêmes appels payants.
- **Reprise avec point de contrôle.** La progression est enregistrée après **chaque enregistrement**. Les champs terminés et enregistrés sont ignorés à la reprise ; les échecs transitoires restent ouverts pour réessai. **Cela ne garantit pas l’absence de double facturation** : une requête peut atteindre le fournisseur avant qu’un délai dépassé ou une interruption empêche d’enregistrer son résultat. Une exécution propre supprime son point de contrôle. Réconciliez le travail incertain avant d’utiliser `--fresh` pour l’ignorer.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, apply: true);
// ['processed' => 120, 'filled' => 18, 'skipped' => 102, 'failed' => 0, 'resumed' => 0, 'errors' => [], 'records' => [...]]
// On a provider failure, 'errors' maps each distinct error code to its human
// message (e.g. 'quota_exceeded' => 'OpenAI: the provider account is out of
// credit or quota…'), and the seo-pro:ai-fill command prints those reasons —
// so a run never fails silently.
```

### Mode batch : réduction de 50 % {#batch-mode-50-cheaper}

Pour un remplissage volumineux sans besoin de résultat immédiat, `--batch` utilise le **point d’accès asynchrone de lots** du fournisseur : [Anthropic Message Batches](https://docs.anthropic.com/en/docs/build-with-claude/batch-processing) ou [OpenAI Batch API](https://platform.openai.com/docs/guides/batch), avec tarif par token réduit de **moitié**. Les adaptateurs Rankbeam Google et local **n’implémentent pas ce chemin** ; `--batch` affiche une remarque puis exécute séquentiellement. Google propose sa propre [Batch API](https://ai.google.dev/gemini-api/docs/batch-api), que cette intégration n’utilise pas. Vérifiez les modèles pris en charge et les tarifs actuels chez chaque fournisseur.

Le lot fonctionne en **soumission puis collecte ultérieure**, lors de deux exécutions de la même commande. Le processus peut être fermé entre les deux :

```bash
# 1) Submit: builds one request per missing field, sends the whole batch in a
#    single call, prints the discounted estimate, and exits. Nothing is written yet.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch

#    → Batch submitted: msgbatch_01Hkc… — 890 requests across 890 records via anthropic.
#      Most batches finish within an hour (max 24h, then they expire).
#      Re-run the SAME command to poll and apply the results:
#        php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch

# 2) Collect: re-run the same command. While the batch is still processing it
#    just says so and exits; once results are ready it writes them and prints
#    the usual summary.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

- **Mêmes prompts, prix réduit de moitié.** Chaque élément utilise le même prompt, schéma de sortie structurée et modèle que l’appel synchrone, ou le [`bulk_model`](#cheaper-bulk-generation) économique s’il est défini. L’enveloppe du lot et le moment d’exécution diffèrent.
- **Estimation déjà réduite.** Au-delà de `seo-pro.ai.fill.confirm_over`, la soumission affiche le montant **réduit de 50 %** et demande confirmation avant tout envoi ; `--force` évite cette demande pour l’automatisation.
- **Reprise après fermeture.** L’identifiant de lot fournisseur et la correspondance requête→enregistrement sont conservés avec les mêmes mécanismes de reprise que l’exécution séquentielle. Un nouveau processus, cron ultérieur ou après déploiement, peut collecter. Si le processus s’arrête entre l’acceptation du lot par le fournisseur et l’écriture de son identifiant, l’exécution suivante **s’arrête par prudence**. Elle indique qu’un lot peut être en cours et demande de vérifier le tableau de bord fournisseur, au lieu de soumettre silencieusement le même lot payant.
- **Jamais d’écrasement.** La règle s’applique pendant *toute* la durée du lot. À la collecte, chaque enregistrement est revérifié et seuls les champs **encore absents** sont écrits. Un titre ou une description ajouté manuellement entre-temps est conservé.
- **Résultats partiels gérés.** Les résultats sont rattachés aux enregistrements par identifiant, dans n’importe quel ordre. Un champ réussi est écrit et marqué terminé. Un échec **transitoire**, limitation, délai, erreur fournisseur ou élément expiré, reste ouvert. Une nouvelle exécution `--batch` soumet un lot réduit à ces éléments. Un enregistrement **refusé définitivement**, filtre de contenu ou requête invalide, est enregistré comme tel et *non* réessayé, pour éviter une boucle facturée.
- **Changement de fournisseur refusé pendant un lot.** Si `SEO_PRO_AI_PROVIDER` change entre soumission et collecte, celle-ci s’arrête avec un message explicite : rétablir le fournisseur pour collecter ou utiliser `--fresh` pour abandonner après réconciliation. Elle n’interroge pas la mauvaise API. N’exécutez qu’une soumission `--batch` à la fois pour les mêmes modèles/champs.
- **Un réglage dédié** : `seo-pro.ai.fill.batch.request_timeout`, 120 secondes par défaut, `SEO_PRO_AI_FILL_BATCH_TIMEOUT`, limite les appels de soumission, interrogation et collecte. Il est distinct du court `timeout` synchrone, car la soumission envoie toutes les requêtes et la collecte lit tout le fichier de résultats. Augmentez-le pour de très gros lots.

::: tip Planifier la collecte
Soumission et collecte étant indépendantes, vous pouvez soumettre depuis un hook de déploiement ou une commande ponctuelle, puis planifier `seo-pro:ai-fill … --batch` toutes les 15 à 30 minutes pour interroger et appliquer les résultats à la fin du lot. Aucun processus permanent de surveillance n’est nécessaire.
:::

## Traitement des réponses {#how-replies-are-handled}

Chaque appel renvoie une enveloppe indépendante du fournisseur, avec le même comportement entre intégrations :

- **Sortie structurée si prise en charge.** OpenAI Structured Outputs, Google Gemini `responseSchema` et Anthropic `output_config.format` font respecter la structure JSON par l’API. Un JSON invalide échoue proprement, sans extraction hasardeuse depuis le texte. Un serveur local / compatible OpenAI reçoit aussi `response_format`, au mieux ; s’il l’ignore, un parseur tolérant traite le texte comme repli. Dans tous les cas, le résultat est une liste valide ou un échec clair, jamais une réponse à moitié analysée.
- **Troncature explicite et corrigeable.** Une réponse coupée au plafond de tokens produit `truncated` et invite à augmenter `seo-pro.ai.max_output_tokens`, au lieu d’un titre raccourci silencieusement. Cela concerne surtout les **modèles de raisonnement**, auxquels le plancher supérieur `reasoning_min_output_tokens` s’applique automatiquement selon les motifs configurés.
- **Nouvelles tentatives pour les erreurs transitoires.** Un `429` ou `5xx` est réessayé avec attente exponentielle plafonnée, en respectant `Retry-After` lorsqu’il existe, lui aussi borné pour ne pas immobiliser la requête. Les échecs **déterministes** ne sont **pas** réessayés automatiquement dans ce chemin : mauvaise clé, requête mal formée, charge trop grande, **délai dépassé** ou compte sans **crédit/quota**. Réessayer un compte non financé n’aiderait pas. Réglez `retry` ou définissez `max_attempts` à 0 pour désactiver ces tentatives.
- **Erreurs typées et nettoyées.** Chaque échec porte un code stable, `unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`, `content_too_large`, `bad_request`, `truncated`, `content_filtered`, `provider_error`, etc., et un indicateur `retryable` pour les cas concernés. Le message est court et nettoyé. **Le chemin normal de l’application n’expose ni ne journalise le corps brut de la réponse fournisseur ; l’outil d’évaluation facultatif décrit plus haut conserve, lui, ces réponses comme preuves.** Dans Filament, l’erreur reste dans la fenêtre avec un conseil adapté aux cas courants ; voir [Résoudre les problèmes](#troubleshooting).

## Résoudre les problèmes {#troubleshooting}

Chaque échec est non bloquant, avec code typé et message nettoyé. Cas courants :

| Symptôme, code d’erreur | Signification | Correction |
|---|---|---|
| **`quota_exceeded`**, compte sans crédit ou quota | Clé valide, mais **compte API sans crédit/quota**. Ce n’est pas une limitation de débit ; réessayer ne résout rien. Exemples : Anthropic « credit balance is too low », OpenAI « exceeded your current quota… check your plan and billing », `insufficient_quota`, Google « prepayment credits are depleted ». | Ajouter du crédit ou activer la facturation dans la console, ou choisir un modèle **auto-hébergé** sans frais API fournisseur. Un **abonnement** Claude/ChatGPT ne finance pas l’**API**. |
| **`unauthorized`**, authentification échouée | Clé absente, incorrecte ou non valable pour le fournisseur configuré. | Vérifier la variable nommée par `seo-pro.ai.api_key_env`, `SEO_PRO_AI_API_KEY` par défaut : définie, actuelle et correspondant à `SEO_PRO_AI_PROVIDER`. |
| **`rate_limited`**, limite de débit atteinte | Vraie limitation **transitoire**, d’abord réessayée automatiquement. | Attendre et réessayer, ou utiliser un modèle **local** selon la capacité du serveur. Augmenter `seo-pro.ai.fill.throttle_ms` pour les lots sur une offre limitée. |
| **`timeout`**, délai dépassé | Pas de réponse dans `seo-pro.ai.timeout`, 15 secondes par défaut ; fréquent avec un modèle local de raisonnement lent. | Augmenter `SEO_PRO_AI_TIMEOUT`. Avec Ollama, envisager `['think' => false]` dans `seo-pro.ai.local.extra_body` si modèle et serveur le permettent. |
| **`truncated`**, plafond max_output_tokens atteint | Budget de sortie atteint, éventuellement avec du raisonnement caché. | Augmenter `seo-pro.ai.max_output_tokens`, parfois 2 000+ pour le raisonnement, ou vérifier la correspondance à un motif `reasoning_models` pour appliquer le plancher. |
| **`content_too_large`**, HTTP 413 | Contenu envoyé au-delà de la limite fournisseur. | Réduire `seo-pro.ai.max_input_chars` pour un extrait plus court. |
| **`bad_request`** | Requête mal formée, souvent un **nom de modèle** inaccessible au compte ou un paramètre non pris en charge. | Vérifier que la clé et le serveur du fournisseur configuré donnent accès à `SEO_PRO_AI_MODEL`. |
| **`content_filtered`** | Le filtre de sécurité du fournisseur refuse la réponse. | Examiner le contenu et les consignes du fournisseur ; ne pas répéter automatiquement la requête refusée. |

::: tip L’inférence locale nécessite toujours un serveur fonctionnel
`SEO_PRO_AI_PROVIDER=local` vers une inférence auto-hébergée évite les problèmes de crédit cloud. Matériel, modèle, compatibilité API, délai et capacité restent nécessaires. Une passerelle distante utilisant cet adaptateur peut exiger une clé et un paiement.
:::

## Ce qui quitte votre serveur {#what-leaves-your-server}

Uniquement les éléments suivants, vers votre fournisseur configuré et sur action explicite, clic ou commande :

- *Suggestions* : nom court de classe et clé du modèle, par exemple « Post #3 », titre et description résolus, URL canonique et extrait de texte sans HTML, limité par `max_input_chars`, 6 000 caractères par défaut.
- *Explications de problèmes* : type, gravité, champ, message et URL cible, avec titre/description résolus du modèle concerné.
- *Réécriture de description* : même contexte minimal que les suggestions, avec type et message du problème de scan lorsqu’il est fourni.
- *Suggestion de données structurées* : même contexte minimal. Le modèle ne renvoie qu’un type et des valeurs de champs terminaux ; le JSON-LD est assemblé localement.

Le package ne collecte pas volontairement de données de visiteurs, IP, en-têtes de requête ou identifiants dans les prompts et n’envoie pas le HTML complet. **Vos champs de contenu et extraits peuvent eux-mêmes contenir des informations sensibles** : examinez ce que votre application expose. Les identifiants fournisseur servent à authentifier la requête. `SECURITY.md` du dépôt Pro fait référence pour le traitement des données.

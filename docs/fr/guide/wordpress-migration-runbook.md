---
description: "Une procédure pas à pas pour remplacer Yoast ou Rank Math sur un site en ligne : les importateurs remplissent les champs vides par défaut, les simulations n’écrivent rien et WordPress reste intact."
---

# Guide opérationnel de migration WordPress → Rankbeam {#wordpress-→-rankbeam-migration-runbook}

Cette procédure décrit le remplacement d’une pile SEO WordPress, Yoast ou Rank Math, par Rankbeam. Par défaut, les importateurs remplissent les champs de destination vides ; `--overwrite` autorise explicitement leur remplacement. Les simulations n’écrivent rien et la base WordPress source reste intacte. Sauvegardez la source et la destination avant tout import.

Ce guide opérationnel accompagne [Migrer depuis WordPress](/fr/guide/migrate-from-wordpress), qui détaille la correspondance des champs, le traitement des variables de template et les clés sources. Cette référence explique *quoi* importer ; ce guide explique *comment, dans quel ordre*.

::: tip Prérequis
- **Core** (`rankbeam/laravel-seo`) pour l’import des métadonnées et `seo:audit`.
- **Pro** (`rankbeam/laravel-seo-pro`) uniquement si vous migrez aussi des **redirections** : la table `seo_redirects` appartient à Pro.
- Votre contenu déjà représenté dans Laravel, par exemple `App\Models\Post`, avec le trait [`HasSEO`](/fr/guide/quickstart) et un moyen de faire correspondre un slug WordPress à un modèle : sa clé de route ou une colonne indiquée avec `--match-by`.
:::

## Structure de la migration {#the-shape-of-the-migration}

Les lignes WordPress sont identifiées par **URL / publication** ; les lignes `seo_meta` de Rankbeam sont **polymorphes**, rattachées à un modèle Eloquent. L’import fait correspondre chaque ligne WordPress à l’un de vos modèles. Trois résultats sont possibles, et chaque exécution en indique la répartition :

| Résultat | Signification | Action |
|---|---|---|
| **matched** | La ligne est rattachée à un modèle ; `seo_meta` a été écrit | Aucune |
| **url-only** | Aucun modèle correspondant, ou absence de `--model` | Décider si cette page nécessite un modèle ou une redirection |
| **unmapped** | La ligne contient des données sans destination dans Core 3, surtout **author** | Les replacer, par exemple dans un hook `getSEOAuthor()` |

---

## Étape 0 : faire coexister les sites, sans basculer {#step-0-—-coexist-no-cutover-yet}

Installez Rankbeam **à côté** du site en ligne. Ajoutez le trait `HasSEO` à vos modèles et affichez les balises via la façade ou la directive, mais ne retirez **pas encore** WordPress ni son plugin SEO. À ce stade, rien n’a été importé et aucune opération n’est destructive : vous vérifiez seulement que la nouvelle pile démarre.

Si vous servez la nouvelle application Laravel et l’ancien site WordPress depuis le même hôte pendant la migration, conservez des chemins séparés jusqu’à l’étape 5.

## Étape 1 : importer les métadonnées, après simulation {#step-1-—-import-the-metadata-dry-run-first}

Commencez toujours par `--dry-run`, qui **n’écrit rien** et affiche le rapport de vérification complet des opérations qui *auraient lieu*.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

Options utiles ; lancez `php artisan seo:import-from --help` pour la liste complète :

| Option | Rôle |
|---|---|
| `--model=` | Nom de classe complet du modèle cible ; option répétable, mais les importateurs WordPress rattachent **un** modèle par exécution : lancez une fois par type de contenu |
| `--match-by=` | Colonne du modèle à comparer au slug ; clé de route par défaut |
| `--post-type=` | Limiter les lecteurs de base de données à ces types de publication ; défaut : `post` + `page` |
| `--connection=` | Connexion à la base contenant les tables WordPress |
| `--table=` | **Préfixe** des tables WordPress ; défaut : `wp_` |
| `--locale=` | Locale des lignes `seo_meta` écrites |
| `--redirects-csv=` | Écrire aussi les propositions de redirection dans ce fichier pour l’étape 3 |
| `--site-url=` | URL de l’ancien site, pour déduire les chemins à partir des URL absolues |
| `--overwrite` | Remplacer les valeurs `seo_meta` existantes non vides ; défaut : **remplir uniquement les champs vides** |
| `--limit=` | Limiter le nombre de lignes sources, utile pour un premier passage |
| `--json` | Rapport exploitable par programme |

Lorsque la simulation convient, retirez `--dry-run` pour appliquer l’import :

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

Par défaut, l’import est **idempotent** et **remplit uniquement les champs vides**. Vous pouvez donc le relancer sans écraser les métadonnées déjà modifiées dans Rankbeam.

## Étape 2 : lire et archiver le rapport de vérification {#step-2-—-read-and-archive-the-verification-report}

Chaque exécution affiche un **rapport de vérification** : les chiffres à valider avant de supprimer quoi que ce soit. Conservez-en une copie durable :

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

Points à contrôler :

- **matched** doit correspondre au nombre de pages censées porter des métadonnées SEO.
- **url-only** constitue la liste des pages sans modèle correspondant : décidez pour chacune s’il faut un modèle, une redirection à l’étape 3 ou aucune action.
- **truncated** répertorie les champs raccourcis pour tenir dans une colonne `seo_meta` : relisez ces titres et descriptions.
- **unmapped** répertorie les données sources sans colonne dans Core 3, **avec chaque valeur `author` distincte**. Les auteurs ne sont pas stockés dans une colonne : ils relèvent de `getSEOAuthor()`. Le rapport vous permet de les replacer volontairement au lieu de découvrir leur perte plusieurs mois après.

## Étape 3 : importer les redirections dans Pro {#step-3-—-import-the-redirects-into-pro}

L’importateur du cœur **n’écrit jamais dans `seo_redirects`**, une table Pro. Il fournit un CSV de structure fixe et versionnée, le **format CSV de redirections v1** : `source_path,target_url,status_code,note`. Importez-le dans Pro en commençant par une simulation :

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Chaque ligne est validée comme dans le formulaire de redirection Filament. Les lignes mal formées, codes de statut invalides, **cibles externes dangereuses**, **sources en double** et règles créant une **boucle de redirection** sont ignorés avec un motif, jamais écrits silencieusement. La simulation valide tout le fichier, boucles et doublons compris, sans rien écrire. Passez `--overwrite` pour remplacer la cible d’une règle existante.

## Étape 4 : vérifier avec `seo:audit --strict` {#step-4-—-verify-with-seo-audit-strict}

Conditionnez la migration au résultat de l’audit gratuit exécuté dans le processus. `--strict` renvoie un code non nul si **une seule** page présente un problème ; il peut donc servir de contrôle bloquant dans la CI ou avant la bascule :

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

L’audit couvre les contrôles des modèles et du résolveur : présence et longueur du titre et de la description, image OG, conflits robots et format canonique. Les contrôles du HTML rendu, des URL canoniques en ligne et le score de 0 à 100 font partie du [scan Pro](/fr/pro/scan-issues) ; lancez-le aussi si vous avez Pro. Consultez l’[audit SEO gratuit](/fr/guide/audit).


Vérifiez ensuite quelques pages réelles dans le navigateur : affichez leur source et confirmez que `<title>`, `<meta name="description">`, canonical, robots et les balises OpenGraph contiennent les valeurs importées.

## Étape 5 : vérifier AVANT de retirer l’ancien package ou les anciennes tables {#step-5-—-verify-before-removing-the-legacy-package-table}

Ne supprimez **pas** la base WordPress, le plugin SEO ni l’ancien package tant que **tous** ces points ne sont pas remplis :

- [ ] L’import a été exécuté pour **chaque** type de contenu, avec un `--model` par exécution.
- [ ] Le rapport archivé indique le nombre **matched** attendu, sans ligne **url-only** inattendue.
- [ ] Chaque valeur d’**auteur sans correspondance** à conserver a été replacée.
- [ ] Les redirections sont importées dans Pro avec `seo-pro:redirects-import` et quelques anciennes URL renvoient réellement une 301 vers les nouvelles.
- [ ] `php artisan seo:audit --strict` renvoie `0`.
- [ ] Avec Pro, `php artisan seo:doctor` ne signale aucune ancienne table `seo` restante ni collision de `config/seo.php`.
- [ ] Des pages rendues ont été vérifiées dans le navigateur.

Comme les importateurs sont idempotents et ne remplissent que les champs vides par défaut, vous pouvez relancer l’étape 1 avant cette validation sans écraser vos modifications. Les anciennes données restent dans WordPress.

## Étape 6 : retirer WordPress {#step-6-—-decommission}

Uniquement après validation de la liste de l’étape 5 : mettez WordPress hors ligne, puis retirez sa base ou ses tables et l’ancien package SEO. Conservez une sauvegarde de la base jusqu’à ce que le bon fonctionnement de la nouvelle pile en production soit établi.

::: tip Retour arrière
Avec le comportement par défaut, les étapes 1 à 4 ne sont pas destructives : `seo_meta` est complété, les redirections sont validées et réversibles en supprimant les règles, et les données WordPress restent intactes. Avant l’étape 6, le retour arrière consiste à *continuer de servir WordPress* ; après l’étape 6, à *restaurer sa sauvegarde*. L’option `--overwrite` autorise le remplacement de valeurs existantes ; conservez les sauvegardes source et destination indiquées au début du guide.
:::

---

Vous venez plutôt d’un package SEO **Laravel** (ralphjsmit, artesaos, Spatie) ? Consultez [Migrer depuis d’autres packages Laravel](/fr/guide/migrate-from-other-packages).

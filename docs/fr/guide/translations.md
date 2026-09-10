---
description: "Les constats d'audit, avertissements et libellés Filament suivent la langue de l'application. Publiez les fichiers de langue pour adapter une chaîne ou contribuer."
---

# Traductions {#translations}

Les chaînes destinées aux utilisateurs – constats d'audit, avertissements sous les champs Filament, libellés, aperçus et rapports – sont des lignes de traduction Laravel. Les packages suivent `app()->getLocale()` : un panel en italien utilise les textes italiens sans configuration supplémentaire.

Les **codes** de problème et d'avertissement, comme `missing_title` ou `title_too_long`, restent stables et ne sont jamais traduits. Seule la phrase destinée à l'utilisateur change.

Les langues livrées sont l'anglais, l'italien dont les anciennes chaînes ont été relues mais dont les modifications doivent être revérifiées, ainsi que des premières traductions en allemand, français, espagnol, portugais brésilien, néerlandais, turc, russe et polonais (Tier 1). Depuis Core 3.16, Filament 1.10 et Pro 2.35, elles comprennent aussi japonais, chinois simplifié (`zh_CN`), chinois traditionnel (`zh_TW`), coréen, grec, ukrainien et tchèque (Tier 2). [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md) donne le statut exact de chaque langue. La relecture par un locuteur natif constitue une étape distincte de la présence technique des fichiers. Il s'agit ici des textes des packages, pas d'une certification de cette documentation.

## Remplacer une chaîne {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Modifiez ensuite `lang/vendor/seo/{locale}/seo.php` et les dossiers voisins pour les autres packages. Les clés conservées remplacent celles du package ; les autres utilisent le fichier du package, puis l'anglais en repli.

## Contribuer une langue {#contribute-a-language}

Copiez le fichier `en` vers votre langue, traduisez les valeurs et conservez chaque `:placeholder`. Exécutez les tests : une clé manquante, une clé supplémentaire, une valeur vide ou un placeholder perdu fait échouer la vérification de parité. Ouvrez ensuite une pull request. Les règles complètes et le glossaire se trouvent dans [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md).

## Éléments volontairement non traduits {#what-is-not-translated-on-purpose}

La présentation CLI est en anglais par défaut. Définissez `seo.cli_locale` ou `SEO_CLI_LOCALE`, ou passez `--display-locale=it`, pour traduire les messages pris en charge et les résumés d'audit. Pro possède son propre réglage `seo-pro.cli_locale`. La langue d'affichage est distincte de la langue du contenu choisie avec `--locale`.

- L'aide des commandes, les diagnostics de maintenance et la sortie de `seo:explain` restent en anglais. Les libellés PASS/WARN/FAIL restent stables.
- Le HTML produit, dont les balises `<meta>` et le JSON-LD, utilise la langue de votre contenu, pas celle du package.
- Les codes de problème, clés JSON et codes de statut restent des identifiants stables. Les libellés humains dans `--json` peuvent être traduits ; les intégrations doivent utiliser les clés et les codes.

## L'autre volet : la langue de votre contenu {#the-other-half-your-content-s-language}

Cette page concerne la langue parlée par le *package*. Les budgets de titre par écriture, la troncature, la casse, les politiques hreflang, `inLanguage`, les moteurs de recherche régionaux et les polices des images OG sont décrits dans [contenu multilingue](/fr/guide/multilingual).

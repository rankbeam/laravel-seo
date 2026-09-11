---
description: "Lancez la démo Rankbeam avec les packages publiés pour voir les métadonnées rendues, le graphe JSON-LD et le sitemap sur de vraies pages."
---

# Lancer la démo {#run-the-demo}

La démo permet de voir Rankbeam sur de vraies pages avant de l'intégrer à votre application. Cette application Laravel préremplie installe les packages **publiés**, sans dépôts de type path ni checkouts voisins. Elle affiche plusieurs pages avec leurs métadonnées SEO complètes, un graphe JSON-LD et un sitemap. Avec une licence, elle exécute aussi l'[audit SEO technique](/fr/pro/scan-issues) de Pro.

## Une commande pour le Core gratuit {#one-command-free-core}

La démo est fournie sous forme d'image Docker dans le dépôt [`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples) :

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

Ouvrez `http://localhost:8080`. Affichez le code source d'une page pour examiner le `<head>` résolu, puis `/sitemap.xml` pour le sitemap généré. Cette configuration utilise le Core gratuit sous licence MIT, installé depuis Packagist.

## Avec Pro pour l'audit {#with-pro-the-audit}

Pro utilise une licence par projet et s'installe depuis son dépôt Composer privé. Fournissez la licence via `COMPOSER_AUTH`, comme secret de build jamais écrit dans une couche de l'image, puis construisez avec l'option Pro :

```bash
export COMPOSER_AUTH='{"http-basic":{"blog.rankbeam.dev":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

Au démarrage, la démo exécute [`seo:doctor`](/fr/pro/headless#setup-health-check) et un premier `seo-pro:scan` sur les pages préremplies. Le rapport de santé, le résumé du scan et le [score de 0 à 100](/fr/pro/scoring) apparaissent dans les logs de Compose.

## Voir le parcours Pro {#see-the-pro-workflow}

Le [parcours scan → correction → rapport](/fr/pro/walkthrough) montre la démo Merchant en fonctionnement : un scan réel, le détail des problèmes, une description enregistrée dans Filament, un nouveau scan et un PDF téléchargeable. Le contenu est identifié comme données d'exemple. Les résultats avant/après proviennent de deux nouveaux scans.

Il n'existe pas encore de démo interactive publique hébergée. Utilisez Docker pour lancer le moteur localement. Le [README de la démo](https://github.com/rankbeam/rankbeam-examples/tree/main/demo) décrit l'installation et le passage entre packages publiés et locaux.

::: tip Vous avez déjà une application ?
Passez directement au [démarrage rapide](/fr/guide/quickstart), de l'installation à un `<head>` complet en quelques étapes.
:::

---
description: "Un rapport PDF en marque blanche : score, tendances, problèmes corrigés et nouveaux, 404 récupérées, variations Search Console et activité des robots IA. Une commande, avec envoi planifié facultatif."
---

# Rapports en marque blanche {#white-label-reports}

Un **rapport PDF** personnalisé pour un site : score global, tendance des problèmes détectés, problèmes **corrigés et nouveaux depuis le dernier rapport**, 404 et liens cassés récupérés, variations Search Console et activité des robots IA. Il se génère avec une commande et peut être **envoyé par e-mail à intervalles planifiés**. Les agences peuvent y ajouter leur logo, leur couleur et la mention « préparé pour {client} » avant de le transmettre au client.

[Téléchargez un rapport d’exemple généré en anglais (PDF, 98 Ko)](/pro-walkthrough/merchant-demo-report.pdf) ou suivez le [parcours scanner → corriger → générer un rapport](/fr/pro/walkthrough). L’exemple utilise le contenu de démonstration Merchant et deux scans réels : un problème corrigé, 19 encore ouverts et aucune donnée Search Console.

[![Première page du rapport généré pour la démonstration Merchant, en anglais.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## Contenu du rapport {#what-s-in-it}

- **Score global** : moyenne des derniers scores par page, selon le [barème](/fr/pro/scoring) publié, A ≥ 90 … F, avec évolution depuis le dernier rapport et **tendance du score global** sur les scans récents. Chaque scan enregistre désormais le score du site sur l’exécution : la tendance repose sur un historique réel par scan. Elle commence au premier scan après la mise à niveau ; les anciennes exécutions sans score sont ignorées.
- **Problèmes détectés par scan** : tendance réelle sur les scans récents terminés, où une baisse est préférable.
- **Problèmes corrigés et nouveaux** : nombre de défauts résolus ou apparus depuis le dernier rapport. Les données proviennent du véritable historique des problèmes, avec leur [cycle de vie](/fr/pro/scan-issues#issue-lifecycle) corrigé/rouvert, dès qu’il couvre une période complète. Sinon, le rapport utilise l’instantané du précédent.
- **Récupération** : liens cassés résolus, 404 **récupérées**, lorsque le chemin renvoie à nouveau 200 de lui-même (voir [`seo-pro:404-recheck`](/fr/pro/production#scheduler)), et 404 **redirigées** depuis le dernier rapport, avec les éléments encore ouverts. Une 404 récupérée correspond à une correction à la source et reste comptée séparément d’une redirection.
- **Search Console** : principales requêtes et pages, avec les **variations** de clics les plus fortes depuis le dernier rapport. La section est omise proprement si GSC n’est pas configuré.
- **Activité des robots IA** : requêtes attribuées par user-agent, sans identité de robot vérifiée, totaux cumulés et, lorsque [l’historique quotidien](/fr/pro/ai-bot-monitor#period-metrics-daily-buckets) couvre la période, **requêtes réelles sur la période et URL distinctes parcourues par chaque robot**. Sinon, le rapport utilise la différence entre compteurs cumulés des instantanés.

## « Depuis le dernier rapport » {#since-the-last-report}

Le rapport compare les périodes **par rapport au rapport précédent**, pas à une date arbitraire. Chaque génération enregistre un instantané léger dans `seo_report_runs` : score, identités des problèmes ouverts, lignes Search Console et compteur de chaque robot. Le rapport suivant compare l’état courant à cet instantané.

Ce mécanisme sert de repli pour les signaux qui n’ont pas leur propre historique : seul le dernier score par page est conservé. L’instantané au moment du rapport permet alors une comparaison fondée sur les données disponibles. Plusieurs signaux possèdent désormais un **véritable historique**, utilisé en priorité : [cycle de vie](/fr/pro/scan-issues#issue-lifecycle) corrigé/rouvert des problèmes, avec comptage réel des corrections et nouveautés après une période complète, [métriques quotidiennes](/fr/pro/search-console#historical-metrics) Search Console et [regroupements quotidiens](/fr/pro/ai-bot-monitor#period-metrics-daily-buckets) des requêtes de robots IA, avec requêtes et URL distinctes par période. Chacun revient à la différence d’instantanés pour le premier rapport après mise à niveau ou si son historique ne couvre pas la période.

Deux conséquences :

- **Le premier rapport établit une référence.** Il affiche l’état courant. Les valeurs « corrigés », « nouveaux », variations et « depuis le dernier rapport » apparaissent à partir du *deuxième*.
- **Vous choisissez la fréquence.** Des rapports mensuels comparent des mois ; des rapports hebdomadaires comparent des semaines. Utilisez `--no-store` pour un aperçu ponctuel qui ne doit pas déplacer la référence.

## Générer un rapport {#generate-a-report}

```bash
php artisan seo-pro:report
```

Sans option, la commande écrit un PDF dans `storage/app/seo-reports/`. Choisissez un autre emplacement ou envoyez-le par e-mail :

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### Options {#options}

| Option | Effet |
| --- | --- |
| `--client=` | Remplacer le libellé du client « préparé pour » |
| `--agency=` | Remplacer le nom d’agence du rapport |
| `--accent=` | Remplacer la couleur d’accent en hexadécimal, par exemple `#3D5AFE` |
| `--logo=` | Remplacer le chemin de l’image du logo |
| `--email=` | Adresse destinataire, répétable ; envoie le rapport par e-mail |
| `--send` | Envoyer aux destinataires configurés |
| `--output=` | Écrire le PDF dans ce fichier ou dossier |
| `--no-store` | Ne pas conserver d’instantané ; la référence de comparaison ne progresse pas |
| `--json` | Produire un résumé exploitable par programme |

## Planifier l’e-mail {#schedule-the-e-mail}

Le package ne se planifie jamais seul : vous décidez de la fréquence. Dans la planification console de l’application, `routes/console.php` ou `app/Console/Kernel.php` :

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Définissez une fois les destinataires par défaut, dans la configuration ou `.env` :

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` utilise ces destinataires ; des options `--email` explicites les remplacent.

## Personnalisation visuelle {#branding}

Les éléments de marque ne sont pas secrets et se trouvent donc dans la configuration. Définissez-les une fois pour tous les rapports. Chaque champ peut être remplacé pour un rapport avec les options ci-dessus, ce qui permet à une installation de produire des rapports portant des libellés clients différents.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Remarques :

- **Logo** : chemin absolu d’un fichier `PNG`/`JPG`/`GIF`/`WEBP`/`SVG`. L’application lit le fichier puis l’intègre au PDF sous forme de data URI ; le moteur n’a donc pas besoin de récupérer cette image sur le réseau. `PNG` ou `JPG` sont les choix les plus sûrs.
- **Couleur d’accent** : validée comme valeur hexadécimale ; une valeur invalide revient au défaut. Elle n’apparaît que comme couleur, jamais comme CSS brut.
- **Nom d’agence** : nom de l’application par défaut, `config('app.name')`.

Le bloc complet se trouve sous `reports` dans `config/seo-pro.php`, avec `paper`, `a4` par défaut, `include_gsc` et le nombre de scans de tendance, lignes GSC et robots à inclure.

## Un site par installation {#one-site-per-install}

Pro scanne l’application dans laquelle il est installé : un rapport décrit donc **cette installation**. Une agence gérant plusieurs sites clients génère un rapport par installation, avec `--client` et la personnalisation pour les identifier. Aucun modèle « sites » multitenant n’est fourni.

## Génération du PDF {#how-it-s-built}

Le moteur par défaut est **dompdf**, en PHP seul, sans Node ni Chromium headless. Un rapport planifié peut donc être rendu dans un worker ou un cron sans binaire système supplémentaire, et Pro reste utilisable en headless. La récupération distante est désactivée dans le moteur ; le logo est intégré au document. Le contenu d’un champ rendu ne peut donc pas déclencher une requête distante.

### Rapports dans tous les systèmes d’écriture : moteur Browsershot {#reports-in-every-script-browsershot-renderer}

Depuis Core 3.20 / Pro 2.40, les moteurs Chrome désactivent JavaScript et bloquent les requêtes de ressources HTTP(S), FTP et WebSocket. Les templates publiés doivent utiliser du HTML/CSS statique avec ressources intégrées. Ces contrôles concernent les ressources de page ; Chrome nécessite toujours un hôte et une sandbox correctement configurés. Le moteur PDF journalise un avertissement indiquant les polices à installer lorsque Fontconfig signale un système d’écriture absent, y compris une écriture minoritaire dans un texte mixte. Une police manquante n’empêche pas Chrome de produire le PDF : examinez le résultat avant de l’envoyer.

dompdf utilise uniquement sa police intégrée, DejaVu Sans, qui couvre le latin, le cyrillique et le grec. Un rapport japonais, thaï ou arabe affiche donc des carrés de caractères manquants. Depuis Pro 2.34, **Chrome headless** via `spatie/browsershot` peut le remplacer. Il s’agit de la même dépendance que celle des images OG du cœur : la machine se configure une seule fois.

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chrome utilise les polices installées sur le serveur. Le template emploie alors la pile du cœur par système d’écriture : `Noto Sans`, famille `Noto Sans CJK` de la langue de page en priorité, thaï, arabe, hébreu, devanagari, emoji couleur et DejaVu Sans comme base latine. Installez les familles nécessaires, avec `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji` sur Debian et Ubuntu, comme pour les [images OG](/fr/guide/multilingual#og-images-in-every-script). `seo:og-images` avertit pendant l’exécution lorsqu’aucune famille installée ne couvre l’écriture d’une page ; le même besoin s’applique aux rapports. Template Blade, données et instantané sont identiques avec les deux moteurs ; seul le rendu change. `ReportGenerator::renderer()` indique le moteur lié.

### Dates et nombres dans la locale du lecteur {#dates-and-numbers-in-the-reader-s-locale}

Le rapport capture `seo-pro.reports.locale` lors de sa génération. Null utilise la locale de l’application. La langue de traduction résolue détermine les libellés PDF et e-mail, l’objet par défaut, les polices et le `lang` HTML. Les locales régionales sans fichier dédié se replient sur la langue de base incluse, puis sur l’anglais. Le chinois simplifié, `zh_CN`, et traditionnel, `zh_TW`, restent distincts.

Dates et nombres suivent la locale demandée via ICU si `ext-intl` est installé. Définissez `seo-pro.reports.format_locale` pour choisir volontairement un autre format régional : `locale=it` avec `format_locale=en_US` donne des libellés italiens et des formats de dates/nombres américains. Sans `ext-intl`, le repli conserve des dates anglaises et des nombres groupés par virgules.

Les e-mails en file gardent la langue, les formats et l’objet capturés même si la configuration du worker change. Choisissez la langue avant de générer le PDF : changer plus tard la locale d’un mailable ne traduit pas sa pièce jointe. Les anciennes charges utiles mises en file avant Pro 2.39 utilisent la configuration du worker, car elles ne contiennent pas ces réglages capturés. Les objets personnalisés, éléments de marque et messages de problème enregistrés restent des données sources.

L’affichage CLI est distinct : `php artisan seo-pro:report --display-locale=it` traduit le résumé de la commande ; la configuration du rapport choisit la langue du PDF et de l’e-mail du client. La CLI utilise l’anglais par défaut, modifiable avec `SEO_PRO_CLI_LOCALE`. Clés JSON et codes restent stables ; les libellés humains peuvent être traduits. Publiez `seo-pro-lang` pour redéfinir les messages de rapports et de workflows dans `lang/vendor/seo-pro/{locale}/seo-pro.php`.

Depuis votre code, résolvez `ReportGenerator` dans le conteneur :

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```

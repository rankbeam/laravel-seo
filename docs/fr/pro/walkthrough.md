---
description: "Suivez un vrai scan Rankbeam Pro : repérez une description manquante, enregistrez la correction dans Filament, relancez le scan et téléchargez le rapport PDF généré pour la démonstration."
---

# Du scan à une correction vérifiée {#from-a-scan-to-a-verified-fix}

Un scan a détecté une description manquante sur un article de démonstration. Nous avons ajouté la description dans Filament, relancé le scan et généré un rapport montrant la correction.

Ces captures proviennent d’une démonstration Merchant locale exécutée le 9 septembre 2026. Le contenu est constitué de données d’exemple ; les deux scans et le rapport ont été générés pour ce parcours. Aucun historique de tendance n’a été prérempli. L’application utilise Laravel 12 et Filament 4, avec le cœur Rankbeam, l’éditeur gratuit et le moteur Pro. Les captures et le PDF d’origine sont en anglais.

**[Télécharger le rapport généré, en anglais (PDF, 98 Ko)](/pro-walkthrough/merchant-demo-report.pdf)**

## Scanner les pages enregistrées {#scan-the-registered-pages}

Après [l’installation de Pro](/fr/pro/installation) et l’enregistrement des cibles, lancez :

```bash
php artisan seo-pro:scan --sync
```

La démonstration enregistre 18 contenus et trois routes. Ce premier scan a traité les 21 cibles sans échec et détecté 20 problèmes : six avertissements et 14 remarques.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="Premier scan terminé : 21 cibles, 20 problèmes, six avertissements et 14 remarques. Interface en anglais." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Les captures ont une résolution 2×. Ouvrez-les pour les examiner en taille réelle.*

## Examiner un problème {#inspect-one-issue}

Dans **SEO Dashboard**, ouvrez **Page issues** à côté de la ligne concernée. Pour « Behind the Scenes: Our Product Photography », le résultat indique le champ `description` manquant, l’URL de la page et le scan qui l’a détecté.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="La fenêtre Page issues identifie Post 5, son URL et le champ description manquant. Interface en anglais." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Enregistrer la description {#save-the-description}

Ouvrez l’article dans **Posts**, remplissez **SEO description** et enregistrez. L’[éditeur Filament gratuit](/fr/guide/filament) affiche le texte saisi dans son aperçu de recherche et indique **Manual** comme source. Dans cet exemple, la description compte 142 caractères ; le titre provient toujours de l’article.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="La description SEO enregistrée et son compteur de 142 caractères. Interface en anglais." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="L’aperçu en direct utilise la description saisie, avec la source Manual. Interface en anglais." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Enregistrer un champ et vérifier sa correction sont deux étapes distinctes. Le score est mis à jour après le scan suivant. Sans Filament, enregistrez la même valeur avec la méthode `saveSEO()` de votre modèle.

## Relancer le scan et vérifier le changement {#rescan-and-check-what-changed}

Relancez la même commande :

```bash
php artisan seo-pro:scan --sync
```

Le tableau de bord marque désormais ce problème précis comme **Fixed**, corrigé. Les 19 autres restent ouverts.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Comparaison enregistrée : aucun nouveau problème, aucune régression, un problème corrigé et 19 encore ouverts. Interface en anglais." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Contrôle | Avant | Après |
|---|---|---|
| Cibles traitées | 21 | 21 |
| Problèmes ouverts | 20 | 19 |
| Avertissements | 6 | 5 |
| Remarques | 14 | 14 |
| Score SEO technique moyen | 92 | 93 |

Le [score](/fr/pro/scoring) reflète les contrôles techniques de Rankbeam. Il ne mesure ni le trafic, ni la position dans les résultats de recherche, ni la présence dans les réponses d’IA. Un contrôle de description réussi ne garantit pas non plus qu’un moteur de recherche affichera cette description.

## Générer le rapport {#generate-the-report}

Pour cette démonstration, nous avons généré un rapport de référence **avant** de modifier l’article, puis un second après le nouveau scan :

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

Le second PDF indique **un problème corrigé**, **aucun nouveau** et **19 ouverts**. Sa tendance contient uniquement les deux scans ci-dessus. Search Console et la journalisation des robots IA étaient désactivés ; ces sections indiquent donc que les données ne sont pas disponibles.

[![Première page du rapport d’exemple généré en anglais : score de 93, un problème corrigé et 19 ouverts.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

Le premier rapport établit la référence de comparaison. Si vous générez un seul rapport après avoir corrigé une page, il ne peut pas montrer de changement par rapport à un rapport antérieur. Utilisez `--no-store` pour un aperçu qui ne doit pas modifier cette référence.

L’exemple utilise le moteur Browsershot. Consultez les [rapports en marque blanche](/fr/pro/reports) pour les prérequis des moteurs, la personnalisation et les envois planifiés.

## Essayer sur votre application {#run-it-on-your-own-app}

Commencez par [Installer Pro](/fr/pro/installation), puis scannez une page dont vous pouvez vérifier la sortie. Pro fonctionne aussi [sans Filament](/fr/pro/headless). Pour essayer d’abord le moteur de métadonnées gratuit, utilisez la [démo Docker](/fr/guide/demo).

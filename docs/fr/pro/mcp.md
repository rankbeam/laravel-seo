---
description: "Un serveur MCP stdio sans dépendance qui permet à un assistant IA de lire, et sur autorisation de modifier, le SEO d’un site Laravel. Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5."
---

# Serveur MCP {#mcp-server}

Le serveur MCP de Rankbeam permet à un assistant IA de **lire et, facultativement, modifier le SEO d’un site** via le [Model Context Protocol](https://modelcontextprotocol.io). Connectez un client MCP, Claude Code / Claude Desktop, Cursor, Codex ou autre, à votre application Laravel : il peut résoudre les métadonnées d’une page, exécuter un audit, lire le score Pro, examiner la politique des robots IA et, si vous l’autorisez, enregistrer des données SEO.

Le serveur stdio est autonome et **sans dépendance**, sans SDK ni nouveau package. Il fonctionne avec PHP 8.2–8.4 (Laravel 11), PHP 8.2–8.5 (Laravel 12), PHP 8.3–8.5 (Laravel 13).

::: tip Fonctionnalité Pro
Le serveur MCP est fourni par `rankbeam/laravel-seo-pro`. Il est **en lecture seule par défaut**. Les modifications nécessitent un indicateur de configuration et une liste de modèles autorisés.
:::

## Ce que l’assistant peut faire {#what-the-assistant-can-do}

### Outils d’analyse, toujours disponibles {#analysis-tools-always-available}

| Outil | Fonction |
| --- | --- |
| `seo_resolve` | Métadonnées entièrement résolues d’un enregistrement : titre, description, canonical, robots, Open Graph et JSON-LD, soit ce que la page afficherait réellement. |
| `seo_audit` | [Audit des métadonnées](/fr/guide/audit) dans le processus pour un enregistrement ou les N premiers : mêmes contrôles que `seo:audit`, en direct et sans file. |
| `seo_score` | Dernier [score SEO Pro](/fr/pro/scoring) enregistré pour un modèle, de 0 à 100 avec sa note. |
| `seo_robots_directives` | [Directives robots.txt gérées pour l’IA](/fr/guide/ai-crawlers) et politique allow/disallow résolue par robot. |
| `validate_schema` | Valider un objet JSON-LD ou le graphe résolu d’un modèle autorisé avec le validateur de données structurées du cœur, selon les exigences de résultats enrichis Google par `@type`. |
| `analyze_robots` | Décision allow/disallow de référence **pour chaque robot IA connu**, avec son origine : réglage par robot, politique par finalité ou défaut. La politique est globale au site. |
| `debug_social_share` | Carte Open Graph et Twitter résolue pour un modèle, après replis, avec conseils sur son état. |
| `check_meta` | Vue ciblée de l’état des métadonnées d’un modèle : titre, description, canonical, robots et og:image résolus, longueur, présence et problèmes d’audit. |

### Outils de contenu du site, toujours disponibles {#site-content-tools-always-available}

Ces outils permettent de « parler à votre site » : l’assistant peut énumérer et rechercher vos pages.

| Outil | Fonction |
| --- | --- |
| `list_pages` | Lister les pages gérées pour le SEO d’un modèle **autorisé**, avec URL et titre effectif ; pagination `limit`/`offset`. |
| `search_pages` | Rechercher dans les pages d’un modèle **autorisé** : [Laravel Scout](https://laravel.com/docs/scout) si le modèle est searchable, sinon SQL `LIKE` sécurisé sur title/name/headline et les métadonnées SEO jointes. Chaque résultat contient URL, titre et extrait. |

### Outils opérationnels, sur activation {#ops-tools-opt-in}

Ces outils lisent l’état des scans et modifient la configuration du site. Comme l’outil d’édition, ils **dépendent de `allow_edits`** : ils sont invisibles et inactifs sur un serveur en lecture seule, son état par défaut.

| Outil | Fonction |
| --- | --- |
| `list_issues` | Problèmes SEO actuellement ouverts, ensemble durable entre exécutions, et en-tête du dernier [scan](/fr/pro/scan-issues) ; filtres `severity` / `type`. |
| `trigger_scan` | Lancer un scan ciblé sur un enregistrement autorisé et renvoyer l’exécution, ou scanner toutes les cibles ; en file par défaut, directement avec `sync: true`. |
| `create_redirect` | Créer une redirection, chemin source ou regex vers une cible, avec statut `301`/`302`/`307`/`308`/`410`, en réutilisant les validateurs du modèle de redirection. |

### Outil d’édition, sur activation {#edit-tool-opt-in}

| Outil | Fonction |
| --- | --- |
| `seo_save_meta` | Écrire les métadonnées, titre, description, canonical, robots, OG, Twitter et JSON-LD, sur un modèle **autorisé** via `saveSEO()`. |

Les outils opérationnels et `seo_save_meta` ne sont **ni annoncés dans `tools/list`, ni exécutables** tant que les modifications ne sont pas activées ; voir [Sécurité](#security). Un serveur en lecture seule ne signale même pas leur existence à l’assistant.

## Connecter un client IA {#wiring-an-ai-client}

Le serveur utilise JSON-RPC sur **stdio** : le client lance une commande Artisan et communique avec elle par les flux d’entrée/sortie. Enregistrez-le dans les clients que vous utilisez ; le même serveur fonctionne avec tous.

::: tip Une commande pour tous les clients
Chaque client ci-dessous lance `php artisan seo-pro:mcp` **depuis la racine de l’application**, afin qu’Artisan puisse la démarrer. Si `php` n’est pas dans le `PATH` du client, cas fréquent sous Windows ou dans une application graphique qui n’hérite pas de l’environnement du shell, fournissez les **chemins absolus de `php` et d’`artisan`**. Artisan démarre depuis le dossier de son propre script ; aucun `cwd` n’est nécessaire.
:::

### Claude Code, CLI {#claude-code-cli}

Une commande suffit à l’enregistrer. Lancez-la **depuis la racine de l’application** :

```bash
claude mcp add rankbeam-seo -- php artisan seo-pro:mcp
```

Vérifiez la connexion :

```bash
claude mcp list
# rankbeam-seo: php artisan seo-pro:mcp - ✔ Connected
```

Sous Windows / Laravel Herd, utilisez les chemins absolus pour fonctionner quel que soit le dossier de lancement :

```bash
claude mcp add rankbeam-seo -- "C:\Users\you\.config\herd\bin\php84\php.exe" "C:\path\to\app\artisan" seo-pro:mcp
```

### Claude Desktop {#claude-desktop}

Modifiez le fichier via **Settings → Developer → Edit Config**, ou ouvrez-le directement :

- **Windows** : `%APPDATA%\Claude\claude_desktop_config.json`
- **macOS** : `~/Library/Application Support/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

Sous **Windows**, utilisez le chemin absolu de `php.exe` et doublez chaque barre oblique inversée dans le JSON :

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "C:\\Users\\you\\.config\\herd\\bin\\php84\\php.exe",
      "args": ["C:\\path\\to\\app\\artisan", "seo-pro:mcp"]
    }
  }
}
```

Quittez complètement Claude Desktop et relancez-le. Les outils apparaissent sous l’icône des outils/connecteurs dans la barre de message.

### Cursor {#cursor}

Créez `.cursor/mcp.json` dans le projet, ou `~/.cursor/mcp.json` pour tous les projets. Indiquez le chemin absolu d’`artisan` pour permettre le lancement depuis n’importe quel dossier :

```json
{
  "mcpServers": {
    "rankbeam-seo": {
      "command": "php",
      "args": ["/absolute/path/to/your/app/artisan", "seo-pro:mcp"]
    }
  }
}
```

Sous Windows, utilisez le chemin absolu de `php.exe` et les barres inversées doublées, comme dans l’exemple Claude Desktop. Activez le serveur dans **Settings → MCP**.

### Codex CLI {#codex-cli}

```bash
codex mcp add rankbeam-seo -- php artisan seo-pro:mcp
codex mcp get rankbeam-seo   # transport: stdio, enabled
```

Comme avec Claude Code, utilisez les chemins absolus de `php` et d’`artisan` sous Windows/Herd.

L’assistant peut maintenant appeler les outils.

## Exemple de conversation {#a-worked-conversation}

Voici un échange réel avec une application de démonstration, un site d’institut médical de 900 pages, dont les sorties d’outils sont abrégées. Les deux modèles de lecture autorisés sont `App\Models\Page` et `App\Models\PublicPage`, et `allow_edits` est activé.

> **Vous :** Comment est le SEO de la page « Accoglienza », Page id 1 ? Corrigez les problèmes évidents.

L’assistant commence par **auditer** la page : `seo_audit { model: "App\\Models\\Page", id: 1 }`.

```json
[
  {
    "id": 1,
    "url": "http://localhost/accoglienza",
    "issue_count": 2,
    "issues": [
      { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 17 characters (recommended min 30).", "context": { "length": 17, "min": 30 } },
      { "code": "description_too_short", "severity": "notice", "field": "description", "message": "Description is only 11 characters (recommended min 70).", "context": { "length": 11, "min": 70 } }
    ]
  }
]
```

Il **résout** les métadonnées courantes avec `seo_resolve { model: "App\\Models\\Page", id: 1 }` pour examiner le rendu effectif. Il trouve un titre de 17 caractères et une description de 11 caractères, sous les minimums conseillés. Il **enregistre une correction** avec `seo_save_meta` :

```json
{
  "model": "App\\Models\\Page",
  "id": 1,
  "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata",
  "description": "Il reparto Accoglienza dell'IDI di Roma: accessi, orari di visita e servizi di supporto ai pazienti."
}
```

L’outil confirme l’écriture et renvoie l’enregistrement nouvellement résolu :

```json
{ "saved": true, "fields": ["title", "description"], "resolved": { "title": "Accoglienza IDI | Istituto Dermopatico dell'Immacolata - IDI", "...": "…" } }
```

L’assistant **relance l’audit** : `issue_count` vaut désormais `0`.

```json
[ { "id": 1, "url": "http://localhost/accoglienza", "issue_count": 0, "issues": [] } ]
```

> **Assistant :** Le titre de 17 caractères et la description de 11 caractères étaient sous les minimums conseillés. J’ai enregistré un titre et une description plus complets et descriptifs. Le nouvel audit ne détecte plus de problème.

L’écriture utilise le même `saveSEO()` que toute sauvegarde par code : la valeur est enregistrée et résolue normalement, via les champs fillable et les casts du modèle. Elle ne passe **pas** par les validateurs de longueur du formulaire Filament. Le nouvel audit ne vérifie donc que ses contrôles automatiques. L’exactitude éditoriale reste à examiner : un audit sans problème ne certifie pas la qualité du texte.

### Interroger le contenu du site {#talking-to-your-site-s-content}

Les outils de contenu permettent de poser des questions **sur le site lui-même**, au-delà d’un enregistrement explicitement nommé.

> **Vous :** Quelles pages mentionnent « immunoterapia », et leurs titres sont-ils corrects ?

L’assistant **recherche** avec `search_pages { model: "App\\Models\\Page", query: "immunoterapia" }` :

```json
{
  "model": "App\\Models\\Page",
  "query": "immunoterapia",
  "driver": "like",
  "count": 3,
  "results": [
    { "id": 42, "url": "http://localhost/ricerca/immunoterapia-oncologica", "title": "Immunoterapia oncologica", "snippet": "Il programma di immunoterapia dell'IDI: terapie, sperimentazioni e accesso…" },
    { "id": 87, "url": "http://localhost/reparti/dermatologia-oncologica", "title": "Dermatologia oncologica", "snippet": "…include protocolli di immunoterapia per i tumori cutanei avanzati…" },
    { "id": 91, "url": "http://localhost/news/nuova-immunoterapia-2026", "title": "Nuova", "snippet": "Al via una nuova immunoterapia…" }
  ]
}
```

Le troisième titre se limite à « Nuova ». Il **contrôle les métadonnées de cette page** avec `check_meta { model: "App\\Models\\Page", id: 91 }` :

```json
{
  "url": "http://localhost/news/nuova-immunoterapia-2026",
  "title": { "value": "Nuova", "length": 5, "present": true },
  "description": { "value": null, "length": 0, "present": false },
  "og_image": { "value": null, "present": false },
  "issue_count": 2,
  "issues": [
    { "code": "title_too_short", "severity": "notice", "field": "title", "message": "Title is only 5 characters (recommended min 30)." },
    { "code": "missing_description", "severity": "warning", "field": "description", "message": "No meta description is set." }
  ]
}
```

> **Assistant :** Trois pages mentionnent « immunoterapia ». Deux semblent correctes, mais **/news/nuova-immunoterapia-2026** a un titre de cinq caractères, « Nuova », et aucune meta description. Souhaitez-vous un titre et une description plus complets ?

Avec `allow_edits`, il peut effectuer la correction via `seo_save_meta`, sous réserve des règles d’autorisation du client. En lecture seule, il s’arrête à la recommandation. Ici, `search_pages` utilise le repli SQL `LIKE`, `"driver": "like"`, car le modèle n’est pas indexé dans Scout. Ajoutez [Laravel Scout](https://laravel.com/docs/scout) pour que le même outil passe directement par votre moteur de recherche.

## Sécurité {#security}

Trois protections sont actives par défaut en lecture seule. Leur assouplissement doit être volontaire.

### 1. Modifications désactivées par défaut {#_1-edits-are-gated-off-by-default}

L’outil d’écriture est invisible et inactif jusqu’à l’activation de l’indicateur :

```php
// config/seo-pro.php
'mcp' => [
    'allow_edits' => true,   // default: false
],
```

Avec `allow_edits` désactivé, son défaut, `seo_save_meta` n’est **pas renvoyé par `tools/list`**. Un `tools/call` le visant échoue avec JSON-RPC `-32602`. L’assistant ne peut pas écrire et ne découvre pas l’outil dans le catalogue. Activez-le uniquement pour un client et une base de confiance.

### 2. Liste des modèles autorisés {#_2-the-model-allowlist}

Chaque outil portant sur un modèle, en lecture **ou** en écriture, ne peut accéder qu’à un modèle `HasSEO` de la liste autorisée. Le client IA ne peut pas viser une classe arbitraire, `User`, facturation ou autre :

```php
'mcp' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

Une classe non autorisée produit un résultat d’erreur lisible par l’assistant, `Model [App\Models\User] is not in the MCP allowlist`, sans accéder à la classe. Si `models` est vide, le serveur utilise `seo.audit.models` / `seo.sitemap.models`. MCP partage ainsi exactement le périmètre déjà utilisé par le package, jamais un périmètre plus large.

### 3. Transport stdio uniquement, sans écoute réseau {#_3-stdio-only-—-nothing-is-exposed-to-the-network}

Le serveur communique **uniquement par stdio** : le client lance le processus et transmet JSON-RPC par ses flux. Il n’ouvre **ni service HTTP, ni port, ni socket réseau**, donc aucun point d’accès distant à authentifier. STDOUT ne contient que le protocole ; les diagnostics passent par STDERR, journalisé par le client. Claude Desktop utilise par exemple `%APPDATA%\Claude\logs\mcp-server-rankbeam-seo.log`. Une ligne de diagnostic ne doit donc pas corrompre le flux de protocole.

::: warning Activer les modifications revient à donner un accès en écriture à la base
`allow_edits` permet à l’assistant connecté de modifier les lignes SEO dans la base utilisée par la commande. Expérimentez sur un environnement local ou de préproduction, limitez les modèles autorisés et désactivez les modifications lorsque vous avez terminé. Le réglage général `'enabled' => false` refuse tout démarrage de la commande. Le transport stdio local n’empêche pas le client IA d’envoyer les résultats des outils à son propre fournisseur ; examinez aussi sa configuration de données.
:::

## Configuration {#configuration}

```php
// config/seo-pro.php
'mcp' => [
    'enabled'     => true,           // master switch; the command refuses to run when false
    'allow_edits' => false,          // expose + permit the ops tools + seo_save_meta
    'models'      => [],             // allowlist; [] = fall back to audit/sitemap models
    'server_name' => 'rankbeam-seo', // reported in the MCP initialize handshake

    // Optional Server Card discovery route (off by default) — see below.
    'server_card' => [
        'enabled'     => false,      // serve GET {path} with the discovery card
        'path'        => '.well-known/mcp/server-card.json',
        'name'        => null,       // reverse-DNS server name (null = derived from app.url)
        'schema_url'  => 'https://modelcontextprotocol.io/schemas/draft/server-card.json',
        'website_url' => null,       // optional homepage/docs URL stamped on the card
    ],
],
```

## Server Card : découverte expérimentale, spécification provisoire {#server-card-discovery-—-experimental-draft-spec}

Une **Server Card** MCP est un petit document JSON à une URL connue qui permet à un client de découvrir le nom, la version et les capacités d’un serveur avant de se connecter. Rankbeam peut en servir une pour signaler aux outils d’agents que le site dispose d’un serveur MCP. Elle est **désactivée par défaut** ; son activation est additive et ne change rien d’autre.

```php
// config/seo-pro.php
'mcp' => [
    'server_card' => [
        'enabled' => true,   // default: false
    ],
],
```

Une fois activée, `GET /.well-known/mcp/server-card.json` renvoie une carte de cette forme :

```json
{
  "$schema": "https://modelcontextprotocol.io/schemas/draft/server-card.json",
  "name": "com.example/rankbeam-seo",
  "version": "1.0.0",
  "title": "Rankbeam SEO MCP server",
  "description": "Read — and optionally edit — this site's SEO over the Model Context Protocol…",
  "_meta": {
    "io.rankbeam.seo/transport": "stdio",
    "io.rankbeam.seo/launch": "php artisan seo-pro:mcp",
    "io.rankbeam.seo/tool_count": 10,
    "io.rankbeam.seo/tools": [ { "name": "seo_resolve", "description": "…" } ]
  }
}
```

La carte répertorie uniquement les outils **actuellement activés**. Un serveur en lecture seule n’y annonce donc jamais les outils opérationnels ou d’édition protégés.

::: warning Spécification encore provisoire
La carte suit la proposition MCP Server Card **en cours**, [SEP-2127](https://github.com/modelcontextprotocol/modelcontextprotocol/pull/2127), encore ouverte et non fusionnée au 10 septembre 2026. Le chemin connu, l’URL `$schema` et les champs exacts ne sont **pas finalisés**. Ils sont donc configurables via `path`, `schema_url`, `name` et `website_url`. Le serveur fonctionne par **stdio**, `php artisan seo-pro:mcp`, sans bloc HTTP `remotes` dans la carte. Celle-ci est une indication de découverte, pas un point d’accès HTTP auquel se connecter. Vérifiez chemin et structure avec votre client avant de vous y fier ; laissez-la désactivée si elle ne vous sert pas.
:::

## Notes de protocole {#protocol-notes}

Un serveur MCP limité aux outils est une petite interface JSON-RPC 2.0, implémentée directement ici : `initialize`, négociation de version et de capacités, `tools/list`, `tools/call` et `ping`. Le serveur annonce la version `2025-06-18` et comprend aussi `2025-03-26` et `2024-11-05`. Il renvoie `-32601` pour une méthode inconnue, `-32700` pour une ligne mal formée et les échecs d’**outil** comme résultats `isError` lisibles par l’assistant, pas comme erreurs de transport. Les notifications sans `id`, par exemple `notifications/initialized`, ne reçoivent aucune réponse.

## Utilisation headless et extensions {#headless-extending}

`SeoPro::mcp()` renvoie le registre des outils, que vous pouvez examiner ou compléter :

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

SeoPro::mcp()->all();                 // enabled tools, keyed by name
SeoPro::mcp()->register($myCustomTool); // any Rankbeam\Seo\Pro\Mcp\McpTool
```

Un outil personnalisé implémente `McpTool` : `name`, `description`, `inputSchema`, `isEnabled`, `handle`. Étendez `AbstractTool` pour réutiliser la résolution des modèles autorisés et conserver les protections des outils intégrés.

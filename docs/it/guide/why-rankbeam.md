---
title: "Cos’è Rankbeam? L’infrastruttura SEO per Laravel"
description: "Rankbeam è un’infrastruttura SEO open-core per Laravel: core gratuito MIT per metadati, canonical, JSON-LD, sitemap e crawler, motore Pro commerciale per il monitoraggio e interfaccia Filament facoltativa."
---

# Cos’è Rankbeam? {#what-is-rankbeam}

**Rankbeam è un’infrastruttura SEO open-core per Laravel: un core gratuito MIT per metadati, URL canonical, anteprime social, JSON-LD collegato, sitemap e controlli dei crawler, con monitoraggio e flussi di lavoro Pro commerciali facoltativi.** Risolve i dati SEO dai modelli e dalla configurazione dell’applicazione, genera gli stessi dati tipizzati in Blade, nell’head di Inertia o tramite un’API JSON e, con Pro, continua a controllarli dopo il deploy.

## La famiglia di pacchetti {#the-package-family}

Rankbeam comprende tre pacchetti con una matrice di compatibilità comune:

| Pacchetto | Licenza | Funzione |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, gratuito** | Core: risoluzione dei metadati, grafo JSON-LD collegato, sitemap XML, controlli dei crawler, `seo:audit` gratuito e importatori |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, gratuito** | Campi Filament 4/5 e anteprime dal vivo che scrivono nel `seo_meta` del core |
| `rankbeam/laravel-seo-pro` | **Commerciale** | Motore operativo: scansioni in coda con punteggio da 0 a 100, gestione redirect, monitor 404 senza IP, crawler dei link interrotti, dati Search Console e assistenza AI con chiave propria |

Il confine è esplicito. L’output SEO della pagina viene generato dal core MIT gratuito; il livello a pagamento aggiunge **audit e monitoraggio** in produzione. Pro è un pacchetto separato e non viene distribuito nel core gratuito.

## A chi serve {#who-it-s-for}

Rankbeam è pensato per applicazioni Laravel in produzione con contenuti dinamici o associati a modelli, dove i dati SEO devono essere **salvati, collegati ai modelli, multilingua, accessibili senza pannello e verificati**. Per poche pagine statiche che richiedono soltanto titolo e descrizione può bastare un piccolo helper per i metatag. La nota su [quando uno stack composto resta adeguato](#what-is-honestly-not-in-the-free-core) descrive questo caso.

## Versioni supportate {#supported-versions}

La matrice è comune alla famiglia:

- **PHP** 8.2–8.4.
- **Laravel** 11, 12 e 13; Laravel 13 richiede PHP 8.3+.
- **Filament** 4 e 5, facoltativo.

## Cosa non sostituisce Rankbeam {#what-rankbeam-doesn-t-replace}

Rankbeam coordina l’output SEO dell’applicazione Laravel. Non è un servizio di monitoraggio delle posizioni, una suite di ricerca delle parole chiave o un prodotto di analytics, e non promette posizionamenti, indicizzazione o citazioni AI. Per generare sitemap XML usa [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap), lasciando contenuti, routing e analytics nell’applicazione.

Per iniziare, [installa il core gratuito](/it/guide/installation). Le sezioni seguenti descrivono una sostituzione reale in produzione. Pro e la lista d’attesa per il lancio sono su [rankbeam.dev](https://rankbeam.dev/it/).

## Perché evitare tre pacchetti con codice di collegamento {#why-not-three-packages-glue}

Un’app Laravel può avere uno **stack SEO** composto da un pacchetto che salva i metadati per modello, un secondo che aggiunge i campi Filament, un terzo che analizza le pagine e codice dell’applicazione che li tiene allineati. I singoli componenti possono funzionare bene; il costo aggiuntivo è mantenere le integrazioni tra loro.

Il caso riportato qui riguarda una sostituzione in produzione di quello stack con la famiglia Rankbeam. I numeri descrivono quell’intervento, non un risultato garantito per ogni applicazione.

## L’applicazione di riferimento {#the-reference-app}

Un sito Laravel di contenuti già in produzione, qui descritto senza identificarlo:

- Sito **ospedaliero e istituzionale**, online da circa tre mesi al momento dell’intervento.
- **Migrato da WordPress**, con circa 900 pagine nella sitemap.
- Circa **20.000 visite al giorno**.
- **Laravel 12**, amministrazione **Filament 4**, frontend Blade e MySQL.

Prima della sostituzione usava questo stack:

| Livello | Pacchetto |
|---|---|
| Metadati salvati per modello nella tabella `seo` | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Campi SEO Filament | `ralphjsmit/laravel-filament-seo` |
| Scanner delle pagine | `backstage/laravel-seo-scanner` |
| Collegamenti tra i componenti | **Circa 30 classi specifiche dell’applicazione** |

I tre pacchetti sono stati rimossi e sostituiti con **core, Pro e Filament** di Rankbeam. Dopo l’esecuzione della suite SEO, l’applicazione si è avviata **senza regressioni SEO rilevate**. Le sezioni seguenti mostrano il codice di integrazione eliminato e quello inizialmente conservato.

## Cosa è stato eliminato {#what-the-swap-deleted}

La sostituzione ha eliminato **12 classi dell’applicazione**: le funzioni equivalenti sono passate alla famiglia di pacchetti.

| Classe eliminata | Funzione precedente | Ora fornita da |
|---|---|---|
| `Services/SeoService.php` | Punto d’ingresso SEO dell’applicazione | Resolver del core e facade `SEO` |
| `Services/SeoWarningEvaluator.php` | Soglie per lunghezza di titolo/descrizione e dimensioni delle immagini | `SEOWarningEvaluator` del core, condiviso da audit, anteprima e scansione |
| `Services/Seo/SeoAssetInspector.php` | Lettura delle dimensioni delle immagini locali | `LocalImageInspector` del core |
| `Jobs/ScanAllPagesSeo.php` | Invio in coda della scansione del sito | [Pipeline di scansione](/it/pro/scan-issues) Pro |
| `Jobs/ScanPageSeo.php` | Scansione per pagina | `PageScanner` Pro |
| `Jobs/ScanPublicPageSeo.php` | Scansione delle pagine pubbliche | Pipeline di scansione Pro |
| `Models/SeoScanBatch.php` | Registrazione delle esecuzioni | `seo_scan_runs` Pro |
| `Filament/Pages/SeoDashboard.php` | Dashboard SEO amministrativa | Plugin `SeoDashboard` Pro |
| `Filament/Widgets/SeoScanProgressWidget.php` | Widget di avanzamento | Widget di scansione Pro |
| `Filament/Widgets/SeoTrendChartWidget.php` | Widget degli andamenti | Widget di scansione Pro |
| `Facades/Seo.php` | Facade dell’app sopra il pacchetto di archiviazione | Facade `SEO` del core |
| `Console/Commands/RecoverLegacySeoMetadata.php` | Recupero una tantum dei metadati | [Importatori](/it/guide/migrate-from-wordpress) del core, `seo:import-from` |

::: info Il codice inizialmente conservato
L’intervento ha **mantenuto** il crawler dei link interrotti dell’applicazione: circa 17 classi tra job, checker, generatore dei seed, resolver delle sorgenti, due modelli, due enum, due eventi, risorsa Filament con tre widget e due comandi. Sono rimasti anche alcuni helper per metadati e schema: `CustomSEO`, `EntitySeoSection`, `DynamicSeoDataResolver`, `SitewideSchema` e `SeoKeywords`, per **circa 22 classi aggiuntive**. Le alternative Rankbeam sono arrivate in seguito: [crawler Pro](/it/pro/production), **modello correlato come destinazione** e **anteprima SERP/social** di Filament per `CustomSEO`/`EntitySeoSection`, e **grafo schema** del core per `SitewideSchema`. L’adozione di quelle funzioni può trasferire al pacchetto anche queste responsabilità, circa tre dozzine di classi considerando l’intero insieme.
:::

Il costo riguarda l’*integrazione*: le classi che fanno sì che una modifica ai metadati si rifletta nello scanner, nella dashboard e nell’head generato. Quel codice appartiene all’applicazione e richiede i suoi test e la sua manutenzione, anche quando i singoli pacchetti sono ben mantenuti.

## Confronto affiancato {#side-by-side}

| Funzione | Stack composto: tre pacchetti e integrazioni | Famiglia Rankbeam |
|---|---|---|
| Metadati salvati per modello | Pacchetto per i metadati | **Core**, `seo_meta`, MIT |
| Archiviazione **per lingua** | Spesso gestita dal codice di integrazione | **Core**, `seo_meta` con colonna locale |
| Campi SEO Filament | Pacchetto Filament-SEO | **`laravel-seo-filament`**, MIT |
| Modifica dei dati SEO di un modello **correlato** | Adattamento del componente del campo | Resolver `target:` dedicato |
| Anteprima **SERP e social** dal vivo | Blade/Alpine specifico dell’app | Anteprima editoriale a schede integrata |
| Rendering headless: Inertia, Livewire e JSON | L’app di riferimento usava Blade; gli altri stack richiedono integrazione | **Un resolver** → Blade, Inertia, Livewire e JSON, [verificati dal contratto](/it/contributing/rendering-contract) |
| Scanner e problemi ordinati per priorità | Pacchetto scanner | **Pro**, [pipeline di scansione](/it/pro/scan-issues) e `IssueRegistry` |
| Punteggio da 0 a 100 | Codice di integrazione o assente | **Pro**, [criteri pubblici e versionati](/it/pro/scoring) |
| Redirect e recupero dei 404 | Altro pacchetto o codice specifico | **Pro**, gestore redirect e monitor 404 senza IP |
| Crawler dei link interrotti | Costruito dall’applicazione | **Pro**, crawler con limiti e ripresa |
| **Grafo** schema JSON-LD | Builder e collegamenti `@id` gestiti dall’app | **Core**, grafo Organization/WebSite/WebPage collegato |
| Sitemap XML | Pacchetto sitemap | **Core**, registro basato su `spatie/laravel-sitemap` |
| Importazione WordPress, Yoast e Rank Math | Script dedicati | **Core**, `seo:import-from` e [runbook](/it/guide/wordpress-migration-runbook) |
| **Manutenzione delle integrazioni** | **L’applicazione** | Famiglia di pacchetti con rilasci coordinati |

## Tre problemi del codice di integrazione {#the-three-things-glue-can-t-do-well}

**1 — Una famiglia con rilasci coordinati.** Tre pacchetti hanno manutentori, changelog e calendari di aggiornamento diversi; il codice di collegamento deve assorbire le divergenze. Core, Pro e Filament di Rankbeam condividono una [matrice di compatibilità](#tested-where-it-runs) e [confini di aggiornamento documentati](/it/reference/configuration), per rendere espliciti i cambiamenti di comportamento.

**2 — La lingua è una colonna dell’archivio.** `seo_meta` è polimorfica **e** organizzata per lingua. I dati multilingua sono una riga per `(model, locale)`, senza bisogno di introdurre un blob serializzato o una tabella di collegamento. La [catena di priorità](/it/concepts/resolver-precedence) legge direttamente la lingua attiva.

**3 — Rendering headless da un solo resolver.** Rankbeam risolve un `SEOData` tipizzato e genera gli *stessi* dati in HTML, nel payload `Head` di Inertia o in un array JSON. Il [contratto di rendering](/it/contributing/rendering-contract) è condiviso da [Blade](/it/guide/blade), [Inertia](/it/guide/inertia-json) con Vue, React o Svelte e [Livewire](/it/guide/livewire). Non serve un pannello di amministrazione: le funzioni Pro sono disponibili anche [senza interfaccia Filament, tramite Artisan](/it/pro/headless).

## Cosa è escluso dal core gratuito {#what-is-honestly-not-in-the-free-core}

Rankbeam è open-core. La divisione tra pacchetti chiarisce cosa installi con `composer require`:

| Pacchetto | Licenza | Contenuto |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, gratuito** | Risoluzione dei metadati, grafo JSON-LD, sitemap, `seo:audit` gratuito e importatori |
| `rankbeam/laravel-seo-filament` | **MIT, gratuito** | Campi e sezioni Filament che scrivono in `seo_meta` |
| `rankbeam/laravel-seo-pro` | **Commerciale** | Scansioni in coda, problemi per priorità e punteggio 0–100, redirect, monitor 404, crawler dei link interrotti, Search Console, assistenza AI e dashboard Filament |

Il pagamento riguarda quindi l’**audit SEO tecnico** e il **monitoraggio del sito**: scansioni, punteggio, redirect, recupero dei 404 e crawler. Motore dei metadati, grafo schema, sitemap e audit gratuito nel processo dell’applicazione sono MIT e restano gratuiti.

Due caratteristiche operative:

- **Nessun controllo della licenza durante l’esecuzione.** Pro viene concesso in licenza per progetto all’installazione. Non contatta il servizio licenze a runtime e non ha un interruttore remoto che possa fermare l’applicazione. La telemetria operativa è *locale*, destinata ai tuoi log e disattivabile; non viene inviata a Rankbeam.
- **AI con la tua chiave.** L’[assistenza AI](/it/pro/ai-assist) facoltativa usa la *tua* chiave Anthropic, OpenAI, Google o quella del modello locale. Rankbeam non inoltra le richieste tramite propri proxy, non rivende token e non ne addebita il consumo. La funzione è disattivata per impostazione predefinita.

::: tip Quando lo stack composto resta adeguato
Per un solo `<title>` e una descrizione su poche pagine statiche basta un generatore di tag. Rankbeam diventa utile quando i dati SEO sono **salvati**, **multilingua**, **associati a modelli**, **accessibili senza pannello** e **verificati**, e le integrazioni tra pacchetti iniziano a richiedere manutenzione propria.
:::

## Migrare da WordPress riducendo il rischio {#the-lowest-risk-switch-off-wordpress}

L’applicazione di riferimento proveniva da una migrazione WordPress di circa 900 pagine, con metadati Yoast o Rank Math da conservare. Il percorso Rankbeam separa importazione, verifica e dismissione:

1. **Coesistenza.** Prepara Rankbeam accanto al sito attivo, senza rimuovere componenti.
2. **Importazione, prima in simulazione.** `seo:import-from yoast`, `rank-math` e `wordpress-csv` leggono titoli, descrizioni, canonical, robots, parole chiave principali e valori social personalizzati. Gli importatori sono **idempotenti** e **compilano solo i campi vuoti per impostazione predefinita**; senza `--overwrite` non sovrascrivono i metadati già impostati, mentre `--dry-run` non scrive nulla.
3. **Passaggio dei redirect.** Il core genera un CSV versionato; `seo-pro:redirects-import` di Pro valida ogni riga prima di scriverla, rifiutando cicli, destinazioni non sicure e duplicati.
4. **Verifica prima di eliminare.** `seo:audit --strict` termina con codice diverso da zero in presenza di problemi e può bloccare la CI o il cambio di produzione. Il database WordPress resta intatto finché non scegli di eliminarlo.

La procedura completa è nel [runbook WordPress](/it/guide/wordpress-migration-runbook); mappatura dei campi e gestione dei token sono in [Migrazione da WordPress](/it/guide/migrate-from-wordpress). Se provieni da un pacchetto SEO **Laravel**, come ralphjsmit, artesaos o Spatie, consulta [il relativo percorso di migrazione](/it/guide/migrate-from-other-packages).

## Come si comporta su siti più grandi? {#does-it-hold-up-at-scale}

Due vincoli dell’applicazione di riferimento — risoluzione SEO su circa 20.000 richieste giornaliere e scansione dei link su circa 900 pagine — hanno benchmark nella suite. Verificano risultati **deterministici**, come numero di query e limiti dei job, anziché tempi di esecuzione adattati a una macchina specifica.

**Cache del resolver: una lettura dalla cache già popolata non interroga il database.** Con la cache facoltativa attiva, il risultato memorizzato evita l’intera catena. Il benchmark esegue 25 risoluzioni dello stesso modello:

| | Query al database |
|---|---|
| Senza cache, con rilettura di `seo_meta` a ogni risoluzione | **≥ 25** |
| Risultato già in cache | **0** |

La cache è **disattivata per impostazione predefinita**. L’invalidazione rimuove le voci interessate quando cambiano `seo_meta`, un campo di contenuto o i valori predefiniti. Vedi [Configurazione: cache](/it/reference/configuration).

**Crawler dei link interrotti: esecuzione suddivisa su 900 pagine.** Il benchmark usa un corpus generato di circa 900 pagine con il job reale:

- Completa il lavoro in **almeno 18 job**, ciascuno limitato a 50 pagine.
- **Nessun job** supera il limite di 50 pagine.
- Controlla **1.800 link** e registra ogni destinazione non funzionante come problema persistente confermato.

Limiti finiti per esecuzione e un tempo massimo per job contengono il lavoro. La validazione SSRF si applica al seed **e a ogni passaggio di redirect**; un lease nel database consente una sola esecuzione attiva per ambito. La [guida alla configurazione in produzione](/it/pro/production) descrive questi controlli.

## Matrice di test {#tested-where-it-runs}

La matrice è comune ai pacchetti:

- **PHP** 8.2–8.4.
- **Laravel** 11, 12 e 13.
- **Filament** 4 e 5.

## Quando conviene unificare lo stack? {#so-—-why-glue-three-packages-together}

Nel caso descritto, coordinare tre pacchetti richiedeva più di una dozzina di classi, calendari di aggiornamento diversi e integrazioni specifiche per rendering e lingua. Trasferire quelle responsabilità a una famiglia compatibile ha ridotto il codice dell’applicazione. Il caso delle circa 900 pagine e 20.000 visite al giorno documenta quel risultato; la convenienza per un’altra app dipende dalle integrazioni che deve mantenere.

Parti dalla [guida rapida](/it/guide/quickstart): dall’installazione con Composer a un `<head>` completo in circa cinque minuti.

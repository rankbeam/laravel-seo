---
description: "Installiere Rankbeam, ergänze ein bestehendes Modell um HasSEO, speichere Metadaten und prüfe die in Blade ausgegebenen Tags."
---

# Schnellstart {#quickstart}

Du benötigst eine bestehende Laravel-11-, -12- oder -13-Anwendung mit funktionierender Datenbank. Unterstützte Kombinationen: Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5. Der Core ist unter der MIT-Lizenz kostenlos. Ein Konto oder eine Pro-Lizenz ist nicht erforderlich.

## Installation {#install}

Führe diese Befehle im Verzeichnis deiner Anwendung aus:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Der Service Provider wird automatisch registriert. Die Migration erstellt die SEO-Tabellen, aber keine Inhaltsmodelle für deine Anwendung.

## Vor dem Beispiel {#before-the-example}

Die folgenden Schritte setzen ein `Post`-Modell, einen gespeicherten Beitrag und eine Route `posts.show` voraus. Deren Blade-View erhält den Beitrag als `$post`. Passe die Namen an deine Anwendung an. Diese Anleitung ergänzt eine vorhandene Seite um SEO; sie baut keinen Blog auf.

Setze `APP_URL` in `.env` auf den öffentlichen Ursprung deiner Website. Für andere Rendering-Stacks gibt es die Anleitungen [Inertia und JSON](/de/guide/inertia-json) und [Livewire](/de/guide/livewire).

## 1. Trait zum Modell hinzufügen {#_1-add-the-trait-to-a-model}

```php
use Illuminate\Database\Eloquent\Model;
use Rankbeam\Seo\Traits\HasSEO;

class Post extends Model
{
    use HasSEO;

    public function getUrlForSEO(): string
    {
        return route('posts.show', $this);
    }
}
```

`getUrlForSEO()` liefert dem Resolver die kanonische URL des Modells. Sie wird für Canonical-Tags, `og:url` und Sitemap-Einträge verwendet.

## 2. Tags im head ausgeben {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` gibt Titel, Meta-Beschreibung, Canonical, Robots, Open Graph, Twitter Card und vorhandenes JSON-LD aus den aufgelösten Daten aus. Solange keine expliziten Werte gespeichert sind, verwendet der Resolver Modellattribute und konfigurierte Vorgaben. Die [Resolver-Priorität](/de/concepts/resolver-precedence) beschreibt die Reihenfolge.

## 3. Explizite Werte setzen {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Explizite Werte haben Vorrang vor allen Fallbacks. Für übersetzte Metadaten übergibst du zusätzlich die Sprache: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Modelle mit Seedern anlegen
Laravels standardmäßiger `DatabaseSeeder` verwendet `WithoutModelEvents`. Dadurch wird auch der automatische Erstellungs-Hook von `HasSEO` deaktiviert. Entferne diesen Trait oder rufe `saveSEO()` im Seeder ausdrücklich auf.
:::

## 4. Ergebnis prüfen {#_4-verify-the-result}

Öffne die öffentliche Beitragsseite und wähle **Seitenquelltext anzeigen**. Prüfe im `<head>`, ob der Titel `Custom SEO Title` enthält, die Beschreibung `Custom meta description` lautet und der Canonical auf die öffentliche Beitrags-URL zeigt. Der konfigurierte Titelsuffix kann auf den Titel folgen.

Gib `@seo($post)` genau einmal pro Seite aus. Erzeugt dein Layout bereits Titel oder Metatags, ersetze diese, damit keine Duplikate entstehen. Bei unerwarteten Werten hilft die [Anleitung zur Auflösung](/de/guide/explain).

## 5. Optional eine Sitemap ergänzen {#_5-add-a-sitemap-optional}

```php
// e.g. in AppServiceProvider::boot()
use App\Models\Post;
use Rankbeam\Seo\Facades\SEO;

SEO::sitemaps()->register('posts', Post::class);
```

```bash
composer require spatie/laravel-sitemap
php artisan seo:sitemap
```

`/sitemap.xml` liefert nun den erzeugten Index. Weitere Optionen stehen in der [Sitemap-Anleitung](/de/guide/sitemaps).

## Nächste Schritte {#where-to-go-next}

- [Resolver-Priorität](/de/concepts/resolver-precedence) — Auswahl der Werte
- [Blade](/de/guide/blade) — alle sieben Direktiven
- [Inertia und JSON](/de/guide/inertia-json) — Headless-Ausgabe
- [Schema-Graph](/de/guide/schema) — verknüpftes JSON-LD
- [Filament-Felder](/de/guide/filament) — Verwaltungsoberfläche

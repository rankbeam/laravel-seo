---
description: "Zainstaluj Rankbeam, dodaj cechę HasSEO do istniejącego modelu, zapisz pola SEO i sprawdź tagi wygenerowane w Blade."
---

# Szybki start {#quickstart}

Zacznij od istniejącej aplikacji Laravel 11, 12 lub 13 z działającą bazą danych. Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5. Rdzeń jest bezpłatny na licencji MIT; nie potrzebujesz konta ani licencji Pro.

## Instalacja {#install}

Uruchom te polecenia w katalogu aplikacji:

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

Dostawca usług jest wykrywany automatycznie. Migracja tworzy tabele SEO; nie tworzy modeli treści Twojej aplikacji.

## Zanim zaczniesz przykład {#before-the-example}

Poniższe kroki zakładają, że masz już model `Post`, zapisany wpis i trasę `posts.show`, której widok Blade otrzymuje ten wpis jako `$post`. Dostosuj nazwy do swojej aplikacji. Ten przewodnik dodaje SEO do istniejącej strony; nie buduje bloga.

W pliku `.env` ustaw `APP_URL` na publiczny adres bazowy witryny. Jeśli używasz innego sposobu renderowania, skorzystaj z [przewodnika Inertia i JSON](/pl/guide/inertia-json) lub [przewodnika Livewire](/pl/guide/livewire).

## 1. Dodaj cechę do modelu {#_1-add-the-trait-to-a-model}

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

`getUrlForSEO()` wskazuje resolverowi kanoniczny URL modelu. Na tej podstawie powstają adresy kanoniczne, `og:url` i wpisy w mapie witryny.

## 2. Wygeneruj sekcję head {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)` generuje tytuł, metaopis, kanoniczny URL, dyrektywę robots, tagi Open Graph i Twitter Card oraz JSON-LD dołączone do rozstrzygniętych danych. Jeśli nie zapisano jeszcze jawnych wartości, wszystko pochodzi z wyliczanych wartości zastępczych (atrybutów samego wpisu) i skonfigurowanych wartości domyślnych. Zobacz [priorytety resolvera](/pl/concepts/resolver-precedence).

## 3. Ustaw jawne wartości {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

Jawne wartości mają pierwszeństwo przed każdą warstwą zastępczą. Dla przetłumaczonych metadanych podaj locale: `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip Zasilasz bazę seederami?
Domyślny `DatabaseSeeder` w Laravel używa cechy `WithoutModelEvents`, która bez ostrzeżenia wyłącza automatyczne tworzenie rekordów przez `HasSEO`. Usuń tę cechę albo jawnie wywołuj `saveSEO()` w seederach.
:::

## 4. Sprawdź wynik {#_4-verify-the-result}

Otwórz publiczną stronę wpisu i wybierz **Wyświetl źródło strony**. W sekcji `<head>` sprawdź, czy tytuł zawiera `Custom SEO Title`, opis ma wartość `Custom meta description`, a adres kanoniczny prowadzi do publicznego URL-a wpisu. Po tytule może występować skonfigurowany sufiks.

Użyj `@seo($post)` tylko raz na stronie. Jeśli układ już generuje tytuł lub tagi meta, zastąp je, aby uniknąć duplikatów. Gdy wartość jest nieoczekiwana, [przewodnik po wyjaśnianiu wyników resolvera](/pl/guide/explain) pomoże ustalić jej źródło.

## 5. Dodaj mapę witryny (opcjonalnie) {#_5-add-a-sitemap-optional}

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

`/sitemap.xml` udostępnia teraz wygenerowany indeks. Pełną listę opcji znajdziesz w [przewodniku po rejestrze map witryny](/pl/guide/sitemaps).

## Co dalej? {#where-to-go-next}

- [Priorytety resolvera](/pl/concepts/resolver-precedence) — jak wybierane są wartości
- [Przewodnik Blade](/pl/guide/blade) — wszystkie siedem dyrektyw
- [Inertia i JSON](/pl/guide/inertia-json) — renderowanie niezależne od interfejsu
- [Graf schematu](/pl/guide/schema) — powiązane JSON-LD
- [Pola Filament](/pl/guide/filament) — interfejs administracyjny w dwóch liniach

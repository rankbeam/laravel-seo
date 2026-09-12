---
description: "Rankbeam을 설치하고 기존 모델에 HasSEO 트레이트를 추가한 다음, SEO 필드를 저장하고 Blade에서 렌더링된 태그를 확인합니다."
---

# 빠른 시작 {#quickstart}

기존 Laravel 11, 12 또는 13 애플리케이션과 정상적으로 작동하는 데이터베이스에서 시작하세요. Laravel 11은 PHP 8.2–8.4, Laravel 12는 PHP 8.2–8.5, Laravel 13은 PHP 8.3–8.5를 지원합니다. 코어는 MIT 라이선스로 무료 제공되며, 계정이나 Pro 라이선스가 필요하지 않습니다.

## 설치 {#install}

애플리케이션 디렉터리에서 다음 명령을 실행하세요.

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

서비스 프로바이더는 자동으로 검색됩니다. 마이그레이션은 SEO 테이블을 생성하며, 애플리케이션의 콘텐츠 모델은 생성하지 않습니다.

## 예제의 전제 조건 {#before-the-example}

아래 단계는 `Post` 모델, 저장된 게시물, 그리고 해당 게시물을 `$post`로 전달받는 Blade 뷰를 사용하는 `posts.show` 라우트가 이미 있다고 가정합니다. 이름은 앱에 맞게 바꾸세요. 이 가이드는 해당 페이지에 SEO를 추가하며, 블로그 자체를 구축하지는 않습니다.

`.env`에서 `APP_URL` 값을 사이트의 공개 출처(origin)로 설정하세요. 다른 렌더링 스택은 [Inertia 및 JSON 가이드](/ko/guide/inertia-json) 또는 [Livewire 가이드](/ko/guide/livewire)를 참고하세요.

## 1. 모델에 트레이트 추가 {#_1-add-the-trait-to-a-model}

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

`getUrlForSEO()`는 모델의 표준 URL을 리졸버에 알려줍니다. 이 값은 canonical 태그, `og:url`, 사이트맵 항목에 사용됩니다.

## 2. head 렌더링 {#_2-render-the-head}

```blade
<!DOCTYPE html>
<html>
<head>
    @seo($post)
</head>
```

`@seo($post)`는 title, 메타 설명, canonical, robots, Open Graph, Twitter Card 태그와 결정된 데이터에 연결된 JSON-LD를 출력합니다. 아직 명시적 값을 저장하지 않았다면 모든 값은 계산된 대체값(게시물 자체 속성)과 설정한 기본값에서 가져옵니다. 자세한 내용은 [리졸버 우선순위](/ko/concepts/resolver-precedence)를 참고하세요.

## 3. 명시적 값 설정 {#_3-set-explicit-values}

```php
$post->saveSEO([
    'title' => 'Custom SEO Title',
    'description' => 'Custom meta description',
]);
```

명시적 값은 모든 대체값 계층보다 우선합니다. 번역된 메타데이터에는 로캘을 전달하세요. `$post->saveSEO(['title' => 'Titre'], 'fr')`.

::: tip 모델을 시딩하나요?
Laravel의 기본 `DatabaseSeeder`는 `WithoutModelEvents` 트레이트를 사용하며, 이 트레이트는 별도 알림 없이 `HasSEO`의 자동 생성 훅을 비활성화합니다. 해당 트레이트를 제거하거나 시더에서 `saveSEO()`를 명시적으로 호출하세요.
:::

## 4. 결과 확인 {#_4-verify-the-result}

게시물의 공개 페이지를 열고 **페이지 소스 보기**를 사용하세요. `<head>`에서 제목에 `Custom SEO Title` 문자열이 포함되어 있는지, 설명이 `Custom meta description`인지, canonical이 공개 게시물 URL을 가리키는지 확인하세요. 설정한 제목 접미사가 제목 뒤에 붙을 수 있습니다.

`@seo($post)`는 페이지당 한 번만 렌더링하세요. 레이아웃이 이미 title이나 메타 태그를 출력한다면 중복을 피하도록 해당 태그를 교체하세요. 예상과 다른 값이 나오면 [값 결정 과정 가이드](/ko/guide/explain)에서 출처를 확인하세요.

## 5. 사이트맵 추가(선택 사항) {#_5-add-a-sitemap-optional}

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

이제 `/sitemap.xml`에서 생성된 인덱스를 제공합니다. 전체 옵션은 [사이트맵 레지스트리 가이드](/ko/guide/sitemaps)를 참고하세요.

## 다음 단계 {#where-to-go-next}

- [리졸버 우선순위](/ko/concepts/resolver-precedence) — 값이 선택되는 방식
- [Blade 가이드](/ko/guide/blade) — 일곱 가지 지시문 전체
- [Inertia 및 JSON](/ko/guide/inertia-json) — 헤드리스 렌더링
- [스키마 그래프](/ko/guide/schema) — 연결된 JSON-LD
- [Filament 필드](/ko/guide/filament) — 두 줄로 추가하는 관리 UI

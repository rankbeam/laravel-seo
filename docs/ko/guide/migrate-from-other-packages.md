---
description: "다른 Laravel SEO 패키지의 API와 저장 구조를 HasSEO 트레이트 및 saveSEO()에 매핑해 Rankbeam으로 이전하세요. 모델별 SEO 데이터는 명령 하나로 가져올 수 있습니다."
---

# 다른 Laravel SEO 패키지에서 마이그레이션 {#migrating-from-other-laravel-seo-packages}

이미 다른 SEO 패키지를 사용하고 있나요? Rankbeam으로의 전환은 전체 재작성보다 하루 정도의 작업을 목표로 설계했습니다. 이 가이드는 일반적인 패키지의 API와 저장 구조를 Rankbeam의 두 기본 요소인 [`HasSEO`](/ko/guide/quickstart) 트레이트와 `saveSEO()`에 매핑합니다. 모델별 SEO 데이터를 저장하는 패키지를 위한 단일 명령 가져오기 도구도 제공합니다.

::: tip WordPress에서 이전한다면
WordPress(Yoast 또는 Rank Math)에서 콘텐츠 사이트를 옮긴다면 전용 [**WordPress에서 마이그레이션**](/ko/guide/migrate-from-wordpress) 가이드를 참고하세요. CSV 가져오기와 실제 데이터베이스 읽기 도구를 설명합니다.
:::

| 기존 패키지 | 저장 위치 | 마이그레이션 경로 |
|---|---|---|
| [`ralphjsmit/laravel-seo`](#from-ralphjsmit-laravel-seo) | `seo` 다형성 테이블 | **`php artisan seo:import-from ralphjsmit`** + 트레이트 교체 |
| [`artesaos/seotools`](#from-artesaos-seotools) | 없음(런타임 + 설정) | 코드 교체 — `saveSEO()` / 계산 getter로 값 설정 |
| [`spatie/*`](#from-spatie-packages) | 없음(schema-org / 사이트맵 빌더) | 보완적인 부분은 유지하고 나머지는 Rankbeam으로 이동 |

**ralphjsmit**만 SEO 데이터를 데이터베이스 테이블에 저장하므로 대량으로 가져올 데이터가 있습니다. 나머지는 런타임 태그 빌더라 읽을 테이블이 없습니다. 요청별 호출을 저장된 `seo_meta`로 바꾸면 됩니다.

---

## `ralphjsmit/laravel-seo`에서 이전 {#from-ralphjsmit-laravel-seo}

`ralphjsmit/laravel-seo`는 모델당 다형성 행 하나를 `seo` 테이블에 저장합니다. 구조가 Rankbeam의 `seo_meta`와 비슷하므로 명확하고 멱등적인 대량 가져오기가 가능합니다.

### 1. 기존 패키지와 함께 Rankbeam 설치 {#_1-install-rankbeam-alongside-it}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan vendor:publish --tag=seo-migrations
php artisan migrate
```

두 패키지는 다른 테이블(`seo`와 `seo_meta`)과 트레이트 네임스페이스를 사용하므로 이전 중 함께 설치할 수 있습니다.

::: warning 설정 파일은 두 개가 아니라 하나입니다
앱에 `ralphjsmit/laravel-seo`에서 게시한 `config/seo.php`가 남아 있다면 Rankbeam 설정을 가립니다. 같은 `seo` 설정 키를 공유하기 때문입니다. 백업한 뒤 삭제하고 `php artisan vendor:publish
--tag=seo-config`로 Rankbeam 설정을 다시 게시하세요.
:::

### 2. 가져오기 실행 {#_2-run-the-importer}

```bash
# Preview first — writes nothing
php artisan seo:import-from ralphjsmit --dry-run

# Then import for real
php artisan seo:import-from ralphjsmit
```

가져오기 도구는 ralphjsmit의 `seo` 테이블을 읽고 각 행의 실제 Eloquent 모델을 찾아 `seo_meta`에 데이터를 기록합니다.

| 옵션 | 효과 |
|---|---|
| `--dry-run` | 가져올 내용을 보고하며 아무것도 기록하지 않습니다. |
| `--model="App\Models\Post"` | 하나 이상의 모델 클래스로 제한합니다(반복 지정 가능). |
| `--locale=fr` | 이 로케일의 행으로 기록합니다(기본값: 앱 로케일). |
| `--table=legacy_seo` | 이름이 변경된 소스 테이블을 읽습니다. |
| `--connection=legacy` | 다른 데이터베이스 연결에서 소스 테이블을 읽습니다. |
| `--limit=100` | 최대 N개 행을 가져옵니다(단계별 이전에 유용). |
| `--overwrite` | 비어 있지 않은 기존 값을 교체합니다(기본값: 빈 필드만 채움). |
| `--json` | 기계 판독용 보고서. |
| `--force` | 확인 프롬프트를 건너뜁니다(스크립트/CI용). |

**멱등적**이므로 다시 실행해도 같은 행을 갱신하고 중복을 만들지 않습니다. 기본적으로 빈 필드만 *채우며*, Rankbeam에서 이미 설정한 SEO 데이터를 덮어쓰지 않습니다. 기존 값을 가져온 값으로 교체하려면 `--overwrite`를 전달하세요.

### 3. 모델의 트레이트 교체 {#_3-swap-the-trait-on-your-models}

ralphjsmit 트레이트를 Rankbeam 트레이트로 교체하세요. 메서드 이름은 조금 다르며, 트레이트가 읽는 테이블은 이제 `seo_meta`입니다.

```php
// Before
use RalphJSmit\Laravel\SEO\Support\HasSEO;

// After
use Rankbeam\Seo\Traits\HasSEO;
```

ralphjsmit의 `getDynamicSEOData()`로 SEO 데이터를 사용자 지정했다면 해당 로직을 Rankbeam의 필드별 계산 getter(`getSEOTitle()`, `getSEODescription()`, `getSEOImage()`, `getUrlForSEO()`, `getSEOAlternates()`)로 옮기세요. [빠른 시작](/ko/guide/quickstart)을 참고하세요. 저장할 덮어쓰기 값에는 `saveSEO()`를 사용합니다.

```php
$post->saveSEO([
    'title' => 'A hand-written SEO title',
    'description' => 'A hand-written meta description.',
    'canonical' => 'https://example.com/posts/my-post',
    'robots' => 'noindex, nofollow',
    'og_image' => 'https://example.com/og/my-post.jpg',
]);
```

### 필드 매핑 {#field-mapping}

가져오기 도구는 필드를 **명시적으로** 매핑합니다. Core 3 스키마에 없는 열을 무작정 복사하지 않습니다.

| ralphjsmit `seo` | Rankbeam `seo_meta` | 참고 |
|---|---|---|
| `model_type` / `model_id` | `seoable_type` / `seoable_id` | 그대로 복사하지 않고 실제 모델에서 **다시 결정**합니다(아래 참고). |
| `title` | `title` | `seo_meta` 열 길이인 70자로 줄이며 초과 값은 보고합니다. |
| `description` | `description` | 160자로 줄이며 초과 값은 보고합니다. |
| `canonical_url` | `canonical` | |
| `robots` | `robots` | 50자로 줄입니다. |
| `image` | `og_image` | 리졸버를 통해 `twitter:image`가 자동으로 상속합니다. |
| `author` | *(가져오지 않음)* | Core 3의 `seo_meta`에는 작성자 열이 없습니다. 글 작성자는 저장된 소셜 메타데이터가 아닌 리졸버 수준의 영역입니다. 작성자가 있는 행은 **집계하고 보고**하므로 어디에 둘지 결정할 수 있습니다(예: `getSEOData` 형태의 계산값). |
| `id`, `created_at`, `updated_at` | *(가져오지 않음)* | 구조용 항목입니다. |

**다형성 유형을 다시 결정하는 이유.** 각 소스 행의 실제 모델을 찾고 그 모델 자체의 `getMorphClass()`에서 `seoable` 키를 가져옵니다. ralphjsmit이 다른 규칙으로 저장했더라도 앱의 *현재* [morph map](https://laravel.com/docs/eloquent-relationships#custom-polymorphic-types)에서 관계가 올바르게 유지됩니다. 이후 모델이 삭제된 행은 건너뛸 수도 있습니다. 건너뜀으로 보고하며 고아 행으로 기록하지 않습니다.

### 보고서 내용 {#what-the-report-tells-you}

`--json` 옵션을 사용하지 않는 실행은 결과 표와 세 가지 검토 섹션을 출력합니다.

- **Truncated** — `seo_meta` 열에 맞추기 위해 줄인 값입니다. 검토하세요.
- **Not imported** — 데이터가 있었지만 Core 3에 저장할 곳이 없는 소스 열(예: `author`).
- **Skipped rows by reason** — 비어 있는 소스 행, 삭제된 모델, 결정할 수 없는 모델 유형 등 이유별로 건너뛴 행.

### 검증 {#verify}

```bash
php artisan seo:audit            # confirm the imported metadata looks right
```

결과를 확인한 뒤 `ralphjsmit/laravel-seo`를 제거하고 해당 `seo` 테이블을 삭제하세요.

---

## `artesaos/seotools`에서 이전 {#from-artesaos-seotools}

`artesaos/seotools`는 **런타임** 태그 빌더입니다. 주로 컨트롤러에서 `SEOMeta`, `OpenGraph`, `TwitterCard`, `JsonLd` 파사드로 요청별 값을 설정하며 `config/seotools.php` 기본값을 사용합니다. 모델별로 저장하지 않으므로 가져올 테이블은 없습니다. 요청별 호출을 저장값 또는 계산값으로 옮기세요.

| artesaos/seotools 호출 | Rankbeam 대응 방식 |
|---|---|
| `SEOMeta::setTitle($t)` | `saveSEO(['title' => $t])` 또는 `getSEOTitle()` |
| `SEOMeta::setDescription($d)` | `saveSEO(['description' => $d])` 또는 `getSEODescription()` |
| `SEOMeta::setCanonical($u)` | `saveSEO(['canonical' => $u])` 또는 `getUrlForSEO()` |
| `SEOMeta::addKeyword(...)` | 대응하는 keywords 메타 태그는 없습니다. 포커스 키워드는 내부 편집 검사용입니다. `saveSEO(['focus_keywords' => [...]])`([감사](/ko/guide/audit) 참고) |
| `OpenGraph::setTitle / setDescription / addImage` | `saveSEO(['og_title' => …, 'og_description' => …, 'og_image' => …])` |
| `TwitterCard::setType / setTitle / setImage` | `saveSEO(['twitter_card' => …, 'twitter_title' => …, 'twitter_image' => …])` |
| `JsonLd::setType(...)` / `JsonLdMulti` | [JSON-LD 스키마 그래프](/ko/guide/schema) |
| `config/seotools.php` 기본값 | `config/seo.php` 사이트 기본값 + [리졸버 우선순위](/ko/concepts/resolver-precedence) |
| 레이아웃의 `{!! SEO::generate() !!}` | `@seo($model)`([Blade](/ko/guide/blade) 참고) |

개념이 바뀝니다. 각 컨트롤러에서 명령형으로 태그를 설정하는 대신, 모델별 SEO 데이터를 `seo_meta`에 한 번 저장하고 Rankbeam 리졸버가 렌더링합니다. `config/seotools.php`에 있던 사이트 전체 대체값은 Rankbeam의 [설정 기본값](/ko/reference/configuration)이 됩니다. 라우트별 정적 페이지에는 `@seoForRoute()`를 사용합니다.

---

## Spatie 패키지에서 이전 {#from-spatie-packages}

`spatie/laravel-seo` 메타데이터 저장 패키지는 없으므로 가져올 것도 없습니다. SEO와 함께 사용하는 Spatie 패키지들은 **보완적인 빌더**이며 필요한 부분만 유지하거나 교체할 수 있습니다.

- **`spatie/schema-org`** — 유연한 메서드 체인 방식의 JSON-LD 빌더입니다. Rankbeam에는 유형이 있는 `Article`, `FAQPage`, `Product`, `BreadcrumbList`, `LocalBusiness`, `Organization` 빌더를 제공하는 자체 [스키마 그래프](/ko/guide/schema)가 있습니다. `seo_meta.schema_jsonld`에 저장하고 중복을 제거해 렌더링합니다. 직접 구성한 `spatie/schema-org` 객체가 있다면 `->toArray()` 출력을 `saveSEO(['schema_jsonld' => $array])`에 전달하거나 Rankbeam 빌더로 다시 표현하세요.
- **`spatie/laravel-sitemap`** — 사이트맵 생성기입니다. Rankbeam의 [사이트맵 레지스트리](/ko/guide/sitemaps)가 이를 기반으로 합니다. 모델을 소스로 등록해 Rankbeam이 통합 사이트맵을 출력하게 하거나, 기존 Spatie 사이트맵을 유지하고 Rankbeam 라우트를 끌 수 있습니다.

다른 런타임/구조체 기반 메타 빌더인 [`romanzipp/laravel-seo`](https://github.com/romanzipp/Laravel-SEO)를 사용했다면 artesaos와 같은 패턴을 따르세요. 요청별 `setTitle`/`addMeta` 호출을 `saveSEO()` 또는 계산 getter로 옮기면 됩니다.

---

## 가져오기 도구 확장 {#extending-the-importer}

`seo:import-from` 명령은 `Rankbeam\Seo\Importing\Contracts\Importer` 구현의 작은 레지스트리를 사용하므로 명령을 수정하지 않고 새 소스를 추가할 수 있습니다. 현재 기본 소스는 `ralphjsmit` 및 WordPress 가져오기 도구(`wordpress-csv`, `yoast`, `rank-math` — [WordPress에서 마이그레이션](/ko/guide/migrate-from-wordpress) 참고)입니다. 서비스 프로바이더에서 자신의 소스를 등록하세요.

```php
use Rankbeam\Seo\Importing\ImporterRegistry;

$this->app->afterResolving(ImporterRegistry::class, function (ImporterRegistry $registry) {
    $registry->register('my-source', \App\Seo\MyImporter::class);
});
```


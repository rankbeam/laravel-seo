---
description: "안정적인 @id로 서로 참조하는 JSON-LD 스키마 그래프를 출력하세요. Organization, WebSite, WebPage, Article을 연결해 모든 페이지에서 일관된 그래프를 렌더링합니다."
---

# 스키마 그래프(JSON-LD) {#schema-graph-json-ld}

검색 엔진은 노드들이 서로 참조할 때 JSON-LD를 가장 잘 해석합니다. Organization은 WebSite를 게시하고, WebSite는 WebPage를 포함하며, WebPage는 Article을 설명합니다. `SchemaGraph`는 바로 이런 구조를 생성합니다. **안정적인 `@id` 값**으로 노드를 서로 연결하므로 모든 페이지에서 일관된 그래프를 출력합니다.

## 페이지 그래프 {#the-page-graph}

```php
use Rankbeam\Seo\Facades\SEO;
use Rankbeam\Seo\Services\Schema\SchemaCollection;
use Rankbeam\Seo\Services\Schema\SchemaGraph;

$seo = SEO::resolve($post);

$graph = new SchemaGraph();

$schemas = SchemaCollection::make()
    ->add($graph->organization())   // @id: {app_url}#organization
    ->add($graph->webSite())        // @id: {app_url}#website, publisher → #organization
    ->add($graph->webPage($seo));   // @id: {page_url}#webpage, isPartOf → #website
```

Blade의 head 또는 body에서 렌더링하세요.

```blade
{!! $schemas->toScript() !!}
```

Organization과 WebSite 데이터는 `config/seo.php`의 `schema.organization`, `schema.website`에서 가져옵니다. WebPage 노드는 결정된 `SEOData`로 채웁니다.

WebPage 노드의 `inLanguage`는 페이지에 적용된 로캘을 BCP 47 형식으로 사용합니다(`it_IT` → `it-IT`). `ArticleSchema::fromModel()`는 저장된 `seo_meta`의 로캘을 사용하고, WebSite 노드는 `schema.website.inLanguage`에 있는 사이트 언어를 단일 코드 또는 목록으로 표시합니다. `schema.in_language`를 `false`로 설정하면 `inLanguage`를 전혀 출력하지 않습니다. [다국어 콘텐츠](/ko/guide/multilingual#inlanguage-in-the-schema-graph)를 참고하세요.

## 유형별 빌더 {#typed-builders}

일반적인 리치 결과 유형을 위한 빌더가 제공됩니다.

| 빌더 | 설명 |
|---|---|
| `ArticleSchema::fromModel($post)` | 모델과 설정에서 날짜, 작성자, 게시자 정보를 가져옴 |
| `ProductSchema` | 판매 조건, 가격, 구매 가능 상태 |
| `BreadcrumbSchema::fromArray([...])` | 순서가 있는 이름/URL 쌍 |
| `BreadcrumbSchema::fromModelAncestors($page)` | 순환 방지 기능을 적용해 `parent` 체인을 순회 |
| `FAQSchema` | 질문/답변 쌍 |
| `LocalBusinessSchema` | 주소, 지리 정보, 영업시간 |
| `OrganizationSchema` | 독립적인 조직 노드 |

전체 기사 페이지 예제:

```php
$article = ArticleSchema::fromModel($post)
    ->setPublisherOrganization(config('seo.schema.publisher.name'));

$schemas = SchemaCollection::make()
    ->add($graph->organization())
    ->add($graph->webSite())
    ->add($graph->webPage($seo))
    ->add($article->toArray())
    ->add(BreadcrumbSchema::fromArray([
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog'],
        ['name' => $post->title, 'url' => "/blog/{$post->slug}"],
    ])->toArray());
```

## 연결된 스키마와 `@seoSchema` {#attached-schema-and-seoschema}

결정된 `SEOData`에 저장된 스키마(예: 명시적 메타데이터와 함께 저장한 스키마)는 `@seoSchema` 지시문이나 `SEO::toArray()`의 `script` 섹션을 통해 렌더링됩니다.

```blade
@seoSchema($post)
```

편집자는 [Filament 필드](/ko/guide/filament#structured-data-schema-org) 패키지의 선택적 **구조화 데이터** 섹션에서 코드 없이 `seo_meta.schema_jsonld`를 채울 수 있습니다. **자동 breadcrumb** 토글과 FAQ / Product 블록을 제공하며, 저장 전에 `SchemaValidator`를 통해 검증합니다.

## 이스케이프 {#escaping}

`SchemaCollection::toScript()`, `toJson()`, 렌더러 경로를 비롯한 모든 JSON-LD 출력은 `JSON_HEX_TAG | JSON_HEX_APOS |
JSON_HEX_QUOT | JSON_HEX_AMP`로 인코딩합니다. 제목이나 콘텐츠에 `</script>` 문자열이 있어도 script 요소를 종료할 수 없습니다. 스키마 배열에 직접 `json_encode`를 적용해 이 보호를 우회하지 마세요.

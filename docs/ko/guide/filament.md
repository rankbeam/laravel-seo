---
description: "무료 laravel-seo-filament 패키지로 모든 Filament 리소스 폼에 두 줄만으로 완전한 SEO 섹션을 추가하세요. HasSEO 트레이트 기반으로 Filament 4.x와 5.x를 지원합니다."
---

# Filament 관리 필드 {#filament-admin-fields}

무료 [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) 패키지는 모든 Filament 리소스 폼에 완전한 SEO 섹션을 추가합니다. **리소스당 두 줄**이면 됩니다. Filament **4.x와 5.x**(Livewire 3 및 4)를 지원합니다. 메타데이터 편집은 무료이며, 아래 예시에 표시된 스캔과 점수는 Pro가 추가합니다.

## 사전 조건 {#prerequisites}

기존 Filament 4 또는 5 패널과 코어 `HasSEO` 트레이트를 사용하는 모델이 필요합니다. 편집기를 추가하기 전에 마이그레이션과 렌더링을 포함한 [코어 빠른 시작](/ko/guide/quickstart)을 완료하세요.

## 설치 {#install}

```bash
composer require rankbeam/laravel-seo-filament
```

리소스의 모델은 코어 `HasSEO` 트레이트를 사용해야 합니다.

## 리소스에 섹션 추가 {#add-the-section-to-a-resource}

```php
use Rankbeam\Seo\Filament\Concerns\HasSEOFields;

class PostResource extends Resource
{
    use HasSEOFields;                       // 1

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title'),
            // ...
            static::seoSection(),           // 2
        ]);
    }
}
```

## 저장 결과 확인 {#check-the-saved-result}

기존 레코드를 여세요. SEO 설명을 입력하고 저장한 뒤 폼을 다시 로드하세요. 값이 유지되고 미리보기에 표시되며, 출처가 **수동 입력**으로 나타나야 합니다. 렌더링된 페이지의 `<head>`를 확인해 같은 설명이 방문자에게 전달되는지 확인하세요.

<figure class="rb-capture"><a href="/filament-seo-section.png"><img src="/filament-seo-section.png" alt="Merchant 데모의 SEO 필드: 제목, 설명, 표준 URL, 소셜 이미지, 검색 미리보기, 최종 값의 출처." width="1792" height="2616" loading="lazy" decoding="async"></a></figure>

*Merchant 데모 예시입니다. 필드는 패널 테마를 사용합니다. 사용 가능한 컨트롤과 글자 수 기준은 설치된 버전 및 설정에 따라 달라집니다.*

섹션에는 다음 항목이 포함됩니다.

- **제목 및 설명**과 실시간 글자 수 표시기 — 기준은 입력 중인 문자 체계에 대한 코어 [길이 정책](/ko/guide/multilingual#title-and-description-budgets-per-script)에서 가져옵니다. 라틴 문자 텍스트는 60/160자, CJK는 약 30/80자이며 그래핌 클러스터(사용자가 한 글자로 인식하는 단위)로 셉니다.
- **포커스 키워드** — 태그 입력입니다. 일반 키워드를 입력하면 코어의 구조화된 `[{keyword, is_primary}]` 형태로 저장됩니다(첫 항목이 기본 키워드). 따라서 `getPrimaryKeyword()`와 `SEOData`가 그대로 읽을 수 있습니다. `seo.keywords.enabled`를 켜면 [`seo:audit`](/ko/guide/audit) 명령과 Pro 스캔이 키워드가 없는 페이지를 표시합니다. 기본적으로 꺼져 있으며 하나의 공통 활성화 조건을 사용합니다. [설정](/ko/reference/configuration#focus-keywords)을 참고하세요.
- **Canonical URL**(비어 있으면 자동, 쿼리 문자열 제거).
- **Robots** 선택(비어 있으면 사이트 기본값).
- **소셜 공유 이미지** 업로드(og:image / twitter:image) — Filament 기본 디스크의 `seo/` 아래에 저장합니다.
- 입력하는 동안 리졸버의 대체값 체인을 실시간으로 반영하는 **검색 스니펫 미리보기**.
- **출처 표시기** — 각 필드의 최종 값을 만든 리졸버 계층: *수동 입력*, *콘텐츠 대체값*, *모델 유형 기본값*, *전역 기본값*, *사이트 설정*, *URL에서 파생*.

## 필드 제한 {#limiting-fields}

```php
static::seoSection(['title', 'description'])
```

`title`, `description`, `focus_keywords`, `canonical`, `robots`, `og_image` 중 원하는 항목을 지정할 수 있습니다.

트레이트 없이도 `SEOFields::make(?array $only)`가 같은 섹션을 직접 반환합니다.

## 값 저장 방식 {#how-values-persist}

섹션은 `seo_meta` 상태 그룹에 바인딩되고 코어 `seoMeta()` 관계를 통해 갱신 또는 생성 방식으로 저장됩니다. 자신의 테이블에 열을 추가하지 않으며, 값은 즉시 [리졸버](/ko/concepts/resolver-precedence)의 계층 6(명시적 값)이 됩니다.

## 여러 언어 {#several-languages}

코어는 [(모델, 로케일)마다 `seo_meta` 행 하나](/ko/guide/multilingual)를 유지합니다. 페이지가 게시되는 로케일을 전달하면 **언어별 탭 하나**가 렌더링됩니다(Filament 1.9).

```php
static::seoSection(locales: ['en', 'it', 'ja']);
// or, without the trait
SEOFields::make(locales: ['en', 'it', 'ja']);
```

또는 패키지 설정에서 모든 리소스에 한 번에 지정하세요.

```bash
php artisan vendor:publish --tag=seo-filament-config
```

```php
// config/seo-filament.php
'locales' => ['en', 'it', 'ja'],
```

각 탭은 자체 행을 편집하며 다음 항목을 따로 가집니다.

- **글자 수 표시기** — 해당 언어의 문자 체계에 맞는 [길이 정책](/ko/guide/multilingual#title-and-description-budgets-per-script)을 사용합니다. 같은 페이지에서 빈 일본어 제목은 `0 / 30`, 영어 탭은 `0 / 60`로 표시됩니다.
- 해당 로케일의 최종 값으로 렌더링한 **미리보기**(검색 결과 / 소셜 카드).
- 해당 로케일 행을 설명하는 **대체값 표시기**.
- 그 버전에서 설정한 필드 수를 나타내는 **배지** — 비어 있는 번역을 쉽게 찾을 수 있습니다.

`ext-intl`가 로드되어 있으면 탭에 패널 언어로 된 언어 이름(`Italiano` / `Italian`)을 표시하고, 그렇지 않으면 코드를 표시합니다. 모든 탭을 함께 검증하고 저장합니다. 아무것도 입력하지 않은 언어에는 빈 자리용 행을 만들지 않습니다.

::: details 사용자 지정 폼 상태 바인딩
로케일이 여러 개면 상태 경로는 `seo_meta.{locale}.title`이며, 하나면 기존 `seo_meta.title`을 유지합니다. 사용자 지정 폼 동작에는 해당 경로를 사용하세요.
:::

<figure class="rb-capture"><a href="/filament-language-tabs.png"><img src="/filament-language-tabs.png" alt="Merchant 데모의 영어, 이탈리아어, 일본어 탭. 일본어 제목과 설명의 길이 기준은 30자와 80자이며 설명은 설정되지 않았습니다." width="2112" height="2564" loading="lazy" decoding="async"></a></figure>

*2026년 9월 9일 Merchant 데모, `locales: ['en', 'it', 'ja']` 사용. 빈 일본어 탭에는 자체 글자 수 표시기가 있습니다. 여기의 영어 제목은 데모 모델의 콘텐츠 대체값에서 가져옵니다. 언어 탭을 추가해도 콘텐츠가 번역되지는 않습니다. 필드 위의 Pro 점수는 언어 탭별 점수가 아니라 레코드의 마지막 스캔 결과입니다.*

### 번역 플러그인과 함께 사용 {#with-a-translatable-plugin}

`lara-zeus/spatie-translatable` **1.x와 Filament 4** 또는 **2.x와 Filament 5**를 함께 사용한다면 Edit 및 Create용 Rankbeam 페이지 어댑터를 사용하세요. 페이지 트레이트 가져오기만 교체하고 플러그인의 리소스/목록 트레이트, 패널 플러그인, `LocaleSwitcher` 동작은 유지하세요.

```php
// In your EditPost page:
use Rankbeam\Seo\Filament\Resources\Pages\EditRecord\Concerns\Translatable;

// In your CreatePost page (a separate file):
use Rankbeam\Seo\Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
```

각 페이지는 여전히 클래스 내부에 `use Translatable;`를 선언합니다. 플러그인은 선택적 애플리케이션 의존성으로 유지됩니다. 최신 패치 버전을 사용하세요. 로컬 연동 픽스처는 플러그인 1.0.4 / Filament 4.13.1 및 플러그인 2.0.1 / Filament 5.8.1을 다룹니다.

언어를 전환해도 저장하지 않은 상위 콘텐츠, SEO 메타데이터, 구조화된 데이터 초안이 편집기에 유지됩니다. 저장 시 방문한 모든 언어를 검증하고 데이터베이스 트랜잭션으로 함께 저장합니다. 검증 오류가 있으면 확인이 필요한 언어가 열립니다. 업로드는 저장할 때 기록됩니다. 페이지를 떠나거나 다시 로드하면 미저장 초안은 사라집니다. 초안을 저장해도 누락된 콘텐츠가 자동 번역되지는 않습니다.

어댑터는 일반적인 전후 훅과 폼 데이터 변환기를 보존합니다. 페이지가 `handleRecordCreation()`, `handleRecordUpdate()`, `callHook()` 또는 트랜잭션 메서드를 재정의한다면 해당 사용자 지정 코드에 어댑터 동작을 통합하고 저장 흐름을 테스트하세요. 데이터베이스 트랜잭션은 파일 시스템 쓰기를 되돌리지 않습니다. 앱의 기존 고아 파일 정리 절차를 유지해야 합니다.

Livewire 3의 사용자 지정 실시간 텍스트 필드에서는 명시적 디바운스보다 `->live()` 또는 `->live(onBlur: true)`를 사용하세요. 명시적 디바운스는 로컬 모델 상태 갱신을 지연해 빠르게 로케일을 바꿀 때 마지막 입력을 잃을 수 있습니다. Rankbeam의 제목 및 설명 필드는 기본 요청 디바운스를 사용합니다.

업스트림 페이지 트레이트만 사용하면 전환 중 폼을 다시 채웁니다. Rankbeam은 이로 인한 의도치 않은 메타데이터 쓰기를 막지만, 해당 트레이트는 SEO 초안을 보존하지 않습니다. Edit/Create 페이지를 어댑터로 전환하세요. 명시적인 `locales:` 탭은 공유 편집기로 유지되며 페이지 전환기보다 우선합니다.

명시적인 로케일 목록이나 페이지 로케일이 없으면 섹션은 앱 로케일을 편집합니다.

## 구조화된 데이터 (schema.org) {#structured-data-schema-org}

선택적 **구조화 데이터** 섹션에서 편집자가 코드 없이 JSON-LD 리치 결과 스키마를 첨부할 수 있습니다. SEO 섹션 옆에 추가하세요.

```php
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // ... your fields ...
        static::seoSection(),
        static::seoSchemaSection(),     // optional
    ]);
}
```

트레이트 없이 `SEOSchemaFields::make()`를 직접 사용할 수도 있습니다.

이 섹션은 [스키마 렌더러](/ko/guide/schema)가 출력하는 동일한 값인 코어 `seo_meta.schema_jsonld` 열에 기록하며, **순수한 UI 바인딩**입니다. 모든 문서는 코어 스키마 빌더로 만들고 저장 전에 코어 `SchemaValidator`로 검증합니다. 자체 스키마 로직은 추가하지 않습니다.

제공 항목은 다음과 같습니다.

- **자동 breadcrumb** — 별도 입력 없이 사용할 수 있도록 앞에 배치한 단일 토글입니다. `BreadcrumbSchema::fromModelAncestors()`를 통해 레코드의 부모 체인에서 `BreadcrumbList`를 도출합니다. 채울 항목 없이 모델의 상위 항목을 따릅니다.
- **Schema 블록** — 반복 입력입니다. 각 블록은 **FAQ**(질문/답변 쌍 → `FAQPage`) 또는 **Product**(이름, 설명, 이미지, 브랜드, SKU, 가격 + 통화, 재고 상태 → `Product`)이며 코어 `FAQSchema` / `ProductSchema` 빌더로 생성합니다.

### 검증 {#validation}

잘못된 JSON-LD를 만들 블록은 코어 검증기의 메시지와 함께 **저장 시 거부됩니다**. 답변이 없는 FAQ 항목이나 이미지 또는 제안 정보가 없는 Product가 예입니다. 이 빌더가 해당 필드를 요구한다는 뜻이며, 모든 Product 검색 기능에 대한 Google 요구 사항 전체를 설명하는 것은 아닙니다. 비워 둔 블록은 무시합니다.

### 저장 내용 {#what-it-stores}

`schema_jsonld`에는 생성된 문서가 저장됩니다. 하나면 단일 객체, 여러 개면 JSON 배열이며 탐색 경로 다음에 입력한 블록이 옵니다. 둘 다 유효한 JSON-LD이며 `@seo` / `renderSchema()`를 통해 변경 없이 렌더링됩니다.

### 관리하지 않는 스키마 {#schema-it-doesn-t-manage}

직접 작성한 `@graph`, 특수한 `@type`, 폼에 없는 필드(리뷰, 평점, GTIN/MPN)를 포함한 Product처럼 코드로 작성했지만 편집기가 표현할 수 없는 스키마는 **그대로 보존됩니다**. 폼을 열고 저장해도 덮어쓰지 않습니다.

## 문제 해결 {#troubleshooting}

- **저장한 필드가 페이지에 없음:** 템플릿이 같은 레코드와 로케일에 대해 `@seo($model)`를 렌더링하는지 확인하세요.
- **필드가 계속 대체값을 사용함:** 활성 언어에 해당 필드의 덮어쓰기 값이 저장되었는지 확인하세요. 출처 표시기가 최종 값의 계층을 알려 줍니다.
- **언어 탭이 없음:** 명시적 `locales:` 인수, 패키지 설정, 페이지 수준 번역 전환기를 확인하세요. 우선순위는 위에 설명되어 있습니다.

::: details Testbench에서 사용자 지정 패널 테스트
orchestra/testbench 안에서 Filament를 부팅한다면 `LivewireServiceProvider` **전에** Filament의 `SupportServiceProvider`를 등록하세요. Filament가 Livewire의 `DataStore`를 다시 바인딩하므로 순서가 틀리면 모든 Livewire 테스트가 `ViewErrorBag::put(): ... null given` 오류로 실패합니다. 실제 앱은 패키지 탐색이 올바른 순서로 프로바이더를 배치하므로 영향받지 않습니다.
:::

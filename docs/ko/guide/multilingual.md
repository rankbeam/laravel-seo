---
description: "Rankbeam이 영어가 아닌 콘텐츠를 처리하는 방식: 문자 체계별 제목·설명 길이 기준, 그래핌을 보존하는 잘라내기, 로케일별 대소문자 처리, hreflang 정규화와 정책, inLanguage, 지역 검색 엔진, 사이트 소유권 확인, OG 이미지 글꼴 및 유니코드 URL."
---

# 다국어 콘텐츠 {#multilingual-content}

[번역](/ko/guide/translations)은 *패키지*가 여러분의 언어를 사용하게 합니다. 이 페이지는 나머지 절반, 즉 패키지가 **콘텐츠의 언어를 이해하도록 하는 것**을 설명합니다. 일본어에 제목 60자 제한을 적용하는 것은 적절하지 않고, 단어 경계에서 자르면 태국어가 깨집니다. 터키어에서는 `İstanbul` 및 `istanbul` 표현이 같은 단어이며, `it_IT` hreflang은 유효하지 않습니다. 한국어 사이트에는 Google뿐 아니라 Naver의 크롤러도 중요합니다. 이는 번역이 아니라 정확성의 문제입니다. 모든 화면과 출력이 일치하도록 코어에서 처리합니다.

기본값과 정책 재정의는 `config/seo.php` 설정에 있습니다. ICU 단어 분할과 설치된 글꼴처럼 일부 기능에는 런타임 의존성이 필요합니다. 번역된 콘텐츠는 애플리케이션에서 제공해야 합니다.

## 콘텐츠 로케일과 인터페이스 로케일 {#content-locale-and-interface-locale}

Core 3.17, Filament 1.11 및 Pro 2.36은 선택한 콘텐츠 로케일을 메타데이터, 계산 훅, 미리보기 URL, 체크리스트 키워드와 AI 요청에 전달합니다. 영어 패널에서도 레이블을 바꾸지 않고 이탈리아어와 일본어를 편집할 수 있습니다.

```php
$italian = $post->seoData('it');
$japanese = $post->seoData('ja');
```

이러한 읽기 작업은 해당 로케일의 메타데이터 행을 선택하고, 임시 로케일 범위 안에서 `getSEOTitle()`, `getSEODescription()`, `getUrlForSEO()`, `getSEOSchema()` 같은 모델 훅을 실행합니다. 훅에서 예외가 발생해도 호출자의 모델 로케일과 애플리케이션 로케일은 보존됩니다. Spatie의 `setLocale()` 및 `getTranslatableAttributes()` 구현 모델에도 격리된 인스턴스 로케일이 적용됩니다. 훅은 여전히 번역된 콘텐츠를 반환해야 합니다. Rankbeam이 일반 데이터베이스 속성을 자동으로 번역하지는 않습니다.

Pro의 모델 기반 AI 메서드와 일괄 채우기는 명시적인 `locale:` 인수를 받습니다. 이를 생략하면 번역 모델에서 재정의한 `seoData()` 기본값으로 콘텐츠 로케일을 결정하고, 없으면 애플리케이션 로케일을 사용합니다. 단일 언어 편집기와 따라가기 모드를 포함해 Filament 작업에는 해당 필드의 로케일이 전달됩니다. 사용자 정의 큐 작업에서는 선택한 로케일을 직렬화한 뒤 작업 실행 시 명시적으로 전달하세요. 워커의 현재 로케일에 의존하지 마세요.

사용자 정의 동기 콘텐츠 리더에서는 `ModelLocale::run($model, $locale, $callback)` 메서드가 격리된 모델을 콜백에 전달하고 `finally` 블록에서 애플리케이션 로케일을 복원합니다. 로케일에 의존하는 읽기는 모두 콜백 안에서 끝내세요. 지연 이터레이터나 클로저를 반환해도 범위가 연장되지 않습니다.

## 문자 체계별 제목과 설명 길이 기준 {#title-and-description-budgets-per-script}

Rankbeam은 라틴 문자 제목/설명에 60/160 그래핌 클러스터, CJK에 30/80 그래핌 클러스터의 편집 기준을 사용합니다. 이는 설정 가능한 근사치이며, 픽셀 측정값이나 검색 엔진이 전체 값을 표시한다는 보장이 아닙니다. Google은 [제목 링크](https://developers.google.com/search/docs/appearance/title-link)나 [메타 설명](https://developers.google.com/search/docs/appearance/snippet)에 고정된 글자 수 제한을 지정하지 않습니다. 표시되는 텍스트는 기기 너비에 따라 잘릴 수 있습니다.

`Rankbeam\Seo\I18n\LengthPolicy` 정책은 전달받은 텍스트에 적용할 기준을 결정합니다.

```php
use Rankbeam\Seo\I18n\LengthPolicy;

$policy = LengthPolicy::for($title, $locale);   // detects the dominant script
$policy->script;          // 'cjk'
$policy->titleMax;        // 30
$policy->descriptionMax;  // 80
$policy->length($title);  // user-perceived characters (graphemes)
$policy->titleTooLong($title);
```

편집기 경고(`SEOWarningEvaluator`), 무료 `seo:audit`, 계산된 설명의 잘라내기, Pro 스캔과 Filament 카운터가 이 정책을 읽으므로 같은 기준을 사용합니다. 경고는 제목 접미사를 포함한 최종 해석 값을 평가하며, 편집기는 저장하지 않은 텍스트도 표시할 수 있습니다. 길이는 바이트나 코드 포인트가 아니라 **그래핌 클러스터(사용자가 한 글자로 인식하는 단위)**를 셉니다. 클러스터 경계는 설치된 유니코드 구현을 따릅니다. 이는 음절 수나 검색 결과의 픽셀 폭을 측정하는 기능이 아닙니다.

행은 `seo.length_policy` 설정에 문자 체계 그룹(`latin`, `cyrillic`, `greek`, `cjk`, `thai`, `arabic`, `hebrew`, `devanagari`)을 키로 저장됩니다. 목록에 없는 그룹에는 `default` 값이 적용됩니다. 행에서 일부 키만 지정하고 나머지는 상속할 수 있습니다.

```php
'length_policy' => [
    'default' => ['title_min' => 30, 'title_max' => 60, 'description_min' => 70, 'description_max' => 160],
    'cjk'     => ['title_min' => 15, 'title_max' => 30, 'description_min' => 35, 'description_max' => 80],
    'thai'    => ['title_max' => 55],   // everything else from `default`
],
```

기본값이 다른 그룹은 `cjk`뿐입니다. 예전에 게시한 설정을 사용하는 업그레이드 설치에서도 별도 변경 없이 내장 `cjk` 행을 적용받습니다.

::: tip 여러 문자 체계가 섞인 제목
감지는 글자 수에 가중치를 적용합니다. CJK 글자는 두 배로 세므로 "Laravel SEO の完全ガイド"는 CJK로 판단하지만 "Laravel SEO for the 東京 developer"는 라틴 문자로 유지합니다. 연도나 가격처럼 문자가 전혀 없는 값에는 페이지 로케일의 문자 체계를 적용합니다.
:::

`SEOWarningEvaluator::TITLE_MAX_LENGTH` / `DESCRIPTION_MAX_LENGTH` 상수는 이를 읽는 코드에서 사용할 수 있도록 라틴 문자 기본값으로 계속 존재합니다.

## 그래핌을 보존하는 문자 체계별 잘라내기 {#grapheme-safe-script-aware-truncation}

계산된 설명(`seo.computed.description_max_length`, 라틴 문자 기준)은 정책에 따라 길이를 조정합니다. CJK 설명에는 절반의 길이를 적용하고 `Rankbeam\Seo\I18n\Truncator` 클래스로 자릅니다.

- 단어 사이에 공백이 있는 텍스트에는 기존 규칙을 유지합니다. 제한 안의 마지막 단어 경계에서 자르되 그 경계가 제한 길이의 60 % 이상 지점에 있어야 합니다. 말줄임표를 넣지 않고 끝의 구두점을 제거합니다. 라틴 문자 텍스트는 이전과 바이트 단위로 동일합니다.
- 한자, 가나, 태국어에는 단어 사이 공백이 없으므로 제한 안의 마지막 문장·절 구두점(。！？、，…)을 우선합니다. 그다음에는 텍스트에 공백이 있다면 공백(한국어)을 사용하고, 없으면 길이에 맞춰 자릅니다.
- 그래핌 클러스터 단위로 자르므로 결합 문자 시퀀스 안에서 잘리지 않습니다. 태국어 모음 기호나 이모지 수식자가 바탕 문자에서 분리되지 않습니다.

## 로케일별 대소문자 처리 {#locale-aware-casing}

`mb_strtolower()` 함수는 로케일을 고려하지 않지만 `Rankbeam\Seo\I18n\CaseFolder` 클래스는 고려합니다.

```php
use Rankbeam\Seo\I18n\CaseFolder;

CaseFolder::lower('İSTANBUL', 'tr');            // "istanbul" — dotted İ → i under Turkish rules
CaseFolder::equals('ΟΔΟΣ', 'οδος', 'el');       // true — final sigma folded
CaseFolder::equals('ΟΔΟΣ', 'οδός', 'el');       // false — the accent is preserved
CaseFolder::equals('Straße', 'STRASSE', 'de');  // true — ß folded to ss
CaseFolder::containsWord('Notizie dalla Città', 'città'); // true — Unicode word boundaries
```

`lower()` 메서드는 표시용이고, `fold()`, `equals()`, `contains()`, `containsWord()` 메서드는 비교용입니다. 코어는 브랜드가 이미 있는 제목에서 접미사를 생략할 때(`seo.title_suffix_skip_when_contains`) 이 기능을 사용합니다. 따라서 터키어 브랜드는 어느 `i` 형태로 쓰여도 일치하고, 악센트가 있는 브랜드에는 올바른 단어 경계를 적용합니다. Pro 키워드 검사도 같은 헬퍼를 기반으로 합니다.

대소문자 폴딩은 악센트를 보존합니다. 악센트가 있는 철자와 없는 철자를 모두 같은 것으로 취급하지 않습니다. 언어별 어간 추출기가 자체 축약 규칙을 적용할 수 있지만, 이는 `CaseFolder` 및 원형 그대로의 일치와 별개입니다.

## hreflang {#hreflang}

Google은 ISO 639-1 두 글자 언어 코드에 선택적인 ISO 15924 문자 체계와 ISO 3166-1 alpha-2 지역을 조합한 `language[-Script][-REGION]` 및 `x-default` 값을 읽습니다. `es-419` 같은 숫자 지역 코드는 BCP47에서는 유효하지만 [Google의 hreflang 규약](https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes)에는 포함되지 않습니다. Laravel 앱은 대신 *로케일*(`it_IT`, `pt_br`)을 전달하는 경우가 많은데, 이곳에서는 밑줄이 유효하지 않습니다. `seo.hreflang` 설정의 세 정책은 모델의 `getSEOAlternates()` 목록이 `<link rel="alternate">` 태그, 사이트맵 `<xhtml:link>` 항목, `llms.txt` 링크 및 감사 입력으로 변환되기 **전에** 적용됩니다. 모두 같은 정책을 사용합니다. `llms.txt` 출력은 “다른 언어” 링크에서 현재 페이지와 `x-default` 항목을 제외합니다.

```php
'hreflang' => [
    'normalize'    => true,   // it_IT → it-IT, zh_hans_cn → zh-Hans-CN
    'include_self' => false,  // append the page itself when the list omits it
    'x_default'    => null,   // e.g. 'en': duplicate that language's alternate as x-default
],
```

- **`normalize`**(기본적으로 켜짐)은 구분자, 대소문자와 등록된 별칭을 조정합니다(`iw_IL` → `he-IL`). 반복된 구분자(`en__US` → `en--US`)는 감사에서 지적할 수 있도록 보존합니다. 전달한 바이트를 그대로 유지하려면 끄세요.
- **`include_self`** 옵션은 페이지 자체의 URL도 코드도 목록에 없을 때 현재 페이지의 로케일과 표준 URL을 추가합니다. Google은 각 언어 버전의 목록에 자기 자신을 포함하도록 요구합니다. 훅이 *다른* 언어만 반환한다면 이 옵션을 켜세요.
- **`x_default`** 옵션은 목록에 `x-default` 항목이 없을 때 그 항목으로 복제할 대체 페이지의 언어를 지정합니다.

빈 목록은 빈 상태로 유지됩니다. 번역이 없는 페이지에는 자기 참조나 `x-default` 항목을 추가하지 않습니다.

무료 감사는 정책이 적용된 목록에 세 가지 검사를 추가합니다.

| 코드 | 심각도 | 의미 |
|---|---|---|
| `hreflang_invalid_code` | 경고 | Google 규약에 속하지 않는 코드(`en-UK`, `jp`, `english`, `es-419`, `fil`)입니다. |
| `hreflang_duplicate_code` | 알림 | 같은 코드가 두 번 나열되었습니다. |
| `hreflang_missing_self` | 경고 | 페이지 자체의 URL이 목록에 없습니다. |

상호 참조, 즉 다른 페이지가 이 페이지로 다시 연결하는지 확인하려면 크롤링이 필요합니다. 이는 Pro 스캔이 담당합니다. 선택적으로 켜는 `check_hreflang_reciprocity` 검사는 SsrfGuard를 거쳐 각 대체 페이지를 가져오고, 다른 페이지가 이 페이지의 URL을 **해당 언어 코드와 함께** 선언하지 않으면 `hreflang_not_reciprocal` 문제를 보고합니다(Pro 2.38 이상, [스캔 문제](/ko/pro/scan-issues#network-codes) 참조). 필요한 경우 공개 헬퍼를 사용할 수 있습니다.

```php
use Rankbeam\Seo\I18n\Hreflang;

Hreflang::fromLocale(app()->getLocale()); // 'it_IT' → 'it-IT'
Hreflang::isValid('pt-BR');               // true
Hreflang::isValid('en-UK');               // false
```

### 세 가지 언어 코드 규약 {#three-language-code-contracts}

Core **3.18 이상**은 애플리케이션 설정과 HTML에서 제공하는 값을 구분합니다.

| 입력 | 애플리케이션 정규화 | HTML 언어 | Google hreflang |
|---|---|---|---|
| `pt_BR` | `pt-BR` | 제공된 형태로는 유효하지 않음 | 제공된 형태로는 유효하지 않음 |
| `de-CH-1901` | 보존 | 등록된 유효한 변형 | 지원하지 않는 변형 |
| `es-419` | 보존 | 유효한 숫자 지역 | 지원하지 않는 숫자 지역 |
| `zh-Hant-TW` | 보존 | 유효 | 유효 |
| `fil` | 보존 | 등록된 유효한 언어 | 두 글자 코드 규약 밖의 값 |
| `iw_IL` | `he-IL` | 밑줄은 유효하지 않음. `iw-IL` 태그는 더 이상 권장되지 않지만 여전히 유효함 | 정규화된 `he-IL` 사용 |
| `en__US` | `en--US` | 유효하지 않음 | 유효하지 않음 |
| `x-default` | 보존 | Rankbeam의 콘텐츠 언어 정책에서 거부 | 유효한 대체 마커 |

```php
use Rankbeam\Seo\I18n\LanguageTag;

LanguageTag::isValidHtml('de-CH-1901');    // true
LanguageTag::isValidHtml('en_US');        // false: inspect the served value
LanguageTag::isValidHtml('');             // true: HTML defines this as unknown
LanguageTag::isValid('x-default');        // true: generic BCP47 private use
LanguageTag::isValidHtml('x-default');    // false: Rankbeam content-language policy
Hreflang::isValid('es-419');              // false: Google compatibility
Hreflang::isValid(Hreflang::fromLocale('pt_BR')); // true: application boundary
```

**Core 3.17 이하에서 마이그레이션:** `Hreflang::isValid()` 및 `parse()` 메서드는 제공된 코드를 엄격하게 검증합니다. 호출자가 Laravel 로케일을 전달한다면 먼저 `fromLocale()` 메서드를 호출하세요. HTML의 `lang` 속성을 검사한다면 공백을 제거하거나 정규화하지 않고 `LanguageTag::isValidHtml()` 메서드를 사용하세요. 더 이상 권장되지 않는 등록 태그도 HTML에서는 유효합니다. 정규화는 IANA에 명시된 권장 별칭만 적용하며, `en-UK` 값을 `en-GB` 뜻이라고 추측하지 않습니다. 감사에서 보고하기 전에 잘못된 항목을 걸러내지 않습니다.

검증기는 **2026-08-08** 날짜의 IANA 레지스트리 정보를 소스 해시 및 재현 가능한 생성기와 함께 포함합니다. RFC 5646 구조, 등록된 하위 태그, extlang 접두사와 중복 변형·확장을 검사합니다. 예외적으로 유지되는 태그와 개인용 범위를 지원합니다. 변형 접두사에 대한 권고는 필수 유효성 규칙이 아닙니다. 확장 네임스페이스와 구조는 검사하지만 CLDR 옵션의 의미와 개인용 값의 의미는 API 범위 밖입니다. ICU나 런타임 다운로드가 필요하지 않습니다. [RFC 5646](https://www.rfc-editor.org/rfc/rfc5646.html)과 [HTML 언어 정의](https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes)를 참고하세요.

Pro **2.38 이상**은 `lang` 값이 없거나 비어 있으면 알 수 없음/누락으로 보고하고, 제공된 바이트가 잘못되었다면 `html_lang_invalid` 문제를 보고합니다. 문자 체계 불일치 검사는 실제 문자 체계 하위 태그나 IANA에 등록된 기본값을 사용합니다. 개인용·확장 페이로드나 익숙하지 않은 언어를 라틴 문자로 간주하지 않습니다. 지원하지 않는 문자 체계 그룹은 판단하지 않습니다. 이 검사는 완전한 언어 감지기가 아닙니다.

상호 참조 검사는 원본 페이지의 유효한 자기 참조 코드를 사용합니다. 자기 참조 코드가 없으면 Google과 호환되는 유효한 HTML 언어를 사용합니다. 다른 언어 코드 아래에 반환 URL이 있어도 통과하지 않습니다. 원본 코드를 확인할 수 없으면 결과는 `hreflang_target_unverified` 상태로 남습니다. 중복 대상 URL은 기존 대체 페이지 수·본문 크기 제한 안에서 한 번만 가져옵니다. SSRF 보호, 리디렉션 거부와 확인하지 못한 실패의 처리는 그대로 적용됩니다.

## 스키마 그래프의 `inLanguage` {#inlanguage-in-the-schema-graph}

`WebPage` 노드는 페이지에서 해석된 로케일(`it_IT` → `it-IT`)을 기반으로 `inLanguage` 값을 포함합니다. `ArticleSchema::fromModel()` 메서드는 저장된 `seo_meta` 로케일에서 이 값을 가져옵니다. `WebSite` 노드는 설정에서 언어를 가져옵니다.

```php
'schema' => [
    'in_language' => true,                       // off = no inLanguage anywhere
    'website' => ['inLanguage' => ['it', 'en']], // one code or a list
],
```

## 지역 검색 엔진 {#regional-search-engines}

`seo:robots-txt` 기능의 크롤러 카탈로그에는 이제 Google/Bing 이외의 지역에서 중요한 일반 웹 검색 크롤러도 포함됩니다. Yandex, Baidu, Naver(`Yeti`), Seznam, Sogou, 360, Cốc Cốc와 DuckDuckGo에는 `search_engine` 목적이 지정되어 있으며 기본적으로 허용됩니다. 이들은 정책과 봇별 재정의 대상이므로 중국에 서비스를 제공하지 않는 쇼핑몰은 두 크롤러의 대역폭 사용을 막을 수 있습니다.

```php
'ai_crawlers' => [
    'policy'    => ['search_engine' => 'allow', /* … */],
    'overrides' => ['baiduspider' => 'disallow', 'sogou' => 'disallow'],
],
```

`SEO::aiCrawlers()->all()` 및 `match()` 목록은 계속 AI 전용입니다. Pro AI 봇 로그와 모든 "AI 크롤러 N개" 집계는 바뀌지 않습니다. 검색 엔진 목록은 `searchEngines()`, `all(true)` 또는 `match($ua, true)` 메서드로 가져오세요. [AI 크롤러 제어](/ko/guide/ai-crawlers#regional-search-engines)를 참고하세요.

::: warning Baidu
크롤러와 소유권 확인 태그를 지원한다고 해서 Baidu의 발견, 색인 또는 순위가 보장되는 것은 아닙니다.
:::

## 사이트 소유권 확인 {#site-verification}

소유권 토큰은 설정된 검색 엔진마다 메타 태그 하나로 모든 페이지에 렌더링됩니다. Google은 어느 페이지의 태그든 허용하며, Yandex, Baidu 및 Naver가 확인하는 루트 페이지에도 태그가 포함됩니다. 비워 둔 키는 출력하지 않습니다.

```php
'verification' => [
    'google'    => env('SEO_VERIFY_GOOGLE'),    // google-site-verification
    'bing'      => env('SEO_VERIFY_BING'),      // msvalidate.01
    'yandex'    => env('SEO_VERIFY_YANDEX'),    // yandex-verification
    'baidu'     => env('SEO_VERIFY_BAIDU'),     // baidu-site-verification
    'naver'     => env('SEO_VERIFY_NAVER'),     // naver-site-verification
    'seznam'    => env('SEO_VERIFY_SEZNAM'),    // seznam-wmt
    'pinterest' => env('SEO_VERIFY_PINTEREST'), // p:domain_verify
    'facebook'  => env('SEO_VERIFY_FACEBOOK'),  // facebook-domain-verification
],
```

값에 토큰 목록을 넣을 수도 있습니다. Google은 속성 소유자별로 하나씩 발급합니다.

## 모든 문자 체계의 OG 이미지 {#og-images-in-every-script}

내장 카드 글꼴은 라틴 문자, 키릴 문자와 그리스 문자를 지원합니다. 그 밖의 모든 문자 체계는 `seo:og-images` 명령을 실행하는 컴퓨터에 설치된 글꼴에 의존합니다. CJK 글꼴은 16 MB 이상이므로 추가 글꼴은 번들에 포함하지 않습니다. 이제 템플릿은 문자 체계별 대체 글꼴 목록(`seo.og_image.font_stack`)을 사용합니다. 한자가 해당 국가의 올바른 글자 모양으로 표시되도록 페이지 언어에 맞는 Noto CJK 글꼴을 맨 앞에 배치합니다. 렌더링할 제목에 필요한 글꼴이 호스트에 없으면 명령이 문자 체계마다 한 번 경고합니다.

```
No installed font covers cjk text — its cards may render as boxes. Install one: apt-get install fonts-noto-cjk
```

Debian/Ubuntu: `apt-get install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`.
자세한 내용은 [생성된 OG 이미지](/ko/guide/og-image#fonts-and-non-latin-scripts)를 참고하세요.

## 여러 언어로 제공하는 `llms.txt` {#llms-txt-in-several-languages}

`seo.llms_txt.alternates` 옵션을 켜면 다른 언어로도 존재하는 페이지의 글머리 항목 끝에 `Also in: [it](…), [de](…)` 링크를 추가합니다. 정책을 적용한 대체 페이지 목록에서 `x-default` 항목과 현재 페이지를 제외한 링크입니다. 기본적으로 꺼져 있습니다.

## 유니코드 URL {#unicode-urls}

Rankbeam은 URL을 슬러그로 바꾸거나 다시 쓰지 않습니다. 따라서 `/città/` 또는 `/検索` 같은 경로는 모든 출력에서 그대로 유지됩니다. 감사는 IDN 호스트(`https://münchen.example/`)나 유니코드 또는 퍼센트 인코딩 경로가 있는 표준 URL을 허용합니다. PHP의 ASCII 전용 `FILTER_VALIDATE_URL` 함수 대신 `Rankbeam\Seo\I18n\Url::isValid()` 메서드를 사용합니다. 표준 URL, hreflang과 사이트맵 항목이 바이트 단위로 일치하도록 URL마다 원시 유니코드 **또는** 퍼센트 인코딩 중 한 가지 표현만 사용하세요. 두 표현을 섞지 마세요.

## 지원 언어와 지원의 의미 {#which-languages-are-supported-and-what-that-means}

패키지에는 아래 17개 로케일의 문자열과 분석 경로가 포함되어 있습니다. 이 표는 기술적 지원 범위를 설명하며, 원어민의 편집 승인이나 설정하지 않은 호스트에서의 렌더링을 보장하지 않습니다. 일본어/중국어 단어 분석에는 사용 가능한 ICU가 필요합니다. ICU를 사용할 수 없으면 영향을 받는 단어 기반 검사는 건너뜁니다. 비라틴 문자 렌더링에는 적절한 글꼴이 필요합니다. 두 저장소의 테스트가 분석 경로를 검증합니다. 코어의 `tests/Feature/I18n/SupportedLanguagesTest.php` 테스트는 로케일 목록, hreflang 코드와 길이 기준을 고정하며, Pro의 `tests/Feature/OnPage/LanguageSupportMatrixTest.php` 테스트는 분석 엔진을 고정합니다. 표의 내용과 동작이 달라지면 CI가 실패합니다.

| 언어 | 로케일 | 제목 / 설명 | 단어 세기 | 키워드 일치 | 가독성 |
|---|---|---|---|---|---|
| 영어 | `en` | 60 / 160 | 공백 | Snowball | Flesch Reading Ease |
| 이탈리아어 | `it` | 60 / 160 | 공백 | Snowball | Gulpease |
| 독일어 | `de` | 60 / 160 | 공백 | Snowball | Wiener Sachtextformel |
| 프랑스어 | `fr` | 60 / 160 | 공백 | Snowball | Kandel-Moles |
| 스페인어 | `es` | 60 / 160 | 공백 | Snowball | Fernández-Huerta |
| 포르투갈어(브라질) | `pt_BR` | 60 / 160 | 공백 | Snowball | Martins |
| 네덜란드어 | `nl` | 60 / 160 | 공백 | Snowball | Flesch-Douma |
| 터키어 | `tr` | 60 / 160 | 공백 | Snowball | Ateşman |
| 러시아어 | `ru` | 60 / 160 | 공백 | Snowball | Oborneva |
| 폴란드어 | `pl` | 60 / 160 | 공백 | Snowball | Pisarek |
| 일본어 | `ja` | 30 / 80 | ICU 사전 | 대소문자 폴딩 후 정확히 일치 | 휴리스틱, **점수 없음** |
| 중국어(간체) | `zh_CN` | 30 / 80 | ICU 사전 | 대소문자 폴딩 후 정확히 일치 | 휴리스틱, **점수 없음** |
| 중국어(번체) | `zh_TW` | 30 / 80 | ICU 사전 | 대소문자 폴딩 후 정확히 일치 | 휴리스틱, **점수 없음** |
| 한국어 | `ko` | 30 / 80 | 공백 | 대소문자 폴딩 후 정확히 일치 | 휴리스틱, **점수 없음** |
| 그리스어 | `el` | 60 / 160 | 공백 | Snowball | LIX |
| 우크라이나어 | `uk` | 60 / 160 | 공백 | 대소문자 폴딩 후 정확히 일치 | LIX |
| 체코어 | `cs` | 60 / 160 | 공백 | Snowball | LIX |

이 표에서 분명히 구분하는 세 가지 사항은 다음과 같습니다.

- **Pro 2.37부터 Snowball을 번들로 제공합니다.** 12개 언어는 선택 패키지와 무관하게 버전을 고정한 3.1.1 알고리즘을 사용합니다. 우크라이나어와 CJK는 원형 그대로의 일치를 사용하며, 패키지가 임의의 접미사 규칙을 만들지 않습니다. 원형 그대로의 일치는 활용형을 놓칠 수 있고, 어간 추출은 서로 다른 단어를 같은 것으로 취급할 수 있습니다. [엔진 제어와 마이그레이션 참고 사항](/ko/pro/on-page-checklist#upgrading-from-pro-2-36)을 참고하세요.
- **"휴리스틱, 점수 없음"과 "LIX"는 다릅니다.** 이 패키지에서 일본어, 중국어와 한국어는 점수를 매기지 않는 방식을 사용합니다. 체크리스트는 문장 길이와 한자 비율을 기반으로 *수준*을 보고하며 점수는 `null` 값입니다. 어떤 설정을 사용하든 참고 정보로만 취급합니다. 그리스어, 우크라이나어와 체코어는 전용 공식이 구현되어 있지 않아 LIX를 사용합니다. LIX에는 음절 수가 필요하지 않지만 임계값이 모든 언어에 맞게 보정된 것은 아닙니다. 모든 공식의 입력에는 추정치가 포함됩니다. [통계 규약](/ko/pro/on-page-checklist#text-statistics-and-api-limits)을 참고하세요.
- **번역은 초안입니다.** `TRANSLATING.md` 파일에서 원어민 검토를 명시한 경우는 예외입니다. 이탈리아어는 검토되었고, 나머지 언어는 검토자를 기다리고 있습니다. 번역 하나를 검토하는 일은 패키지의 해당 언어 기여자로 이름을 올리는 데 가장 적은 비용이 드는 방법입니다.

목록에 없는 로케일은 영어 문자열, 문자 체계별/기본 길이 기준, 원형 그대로의 키워드 일치와 LIX 또는 휴리스틱 가독성으로 대체될 수 있습니다. 이러한 대체 동작은 검증된 언어 지원이 아닙니다. 체크리스트의 `analysis` 블록은 문자 체계, 분할기, 어간 추출기와 가독성 방식을 표시합니다. 레이블뿐 아니라 사용 가능 여부와 건너뛴 판단도 확인하세요.

### 지역에서 중요한 검색 엔진에 도달하기 {#reaching-the-search-engines-that-matter-locally}

언어를 제공하는 일은 텍스트에만 국한되지 않습니다. 크롤러 카탈로그에는 Google과 Bing 외에 Yandex, Baidu, Naver의 Yeti, Seznam, Sogou, 360과 Cốc Cốc가 포함되어 있습니다. `seo.verification` 기능은 이러한 엔진의 사이트 소유권 확인 태그를 렌더링합니다. 한국어 사이트에는 Naver, 체코어 사이트에는 Seznam, 우크라이나어·러시아어 사이트에는 Yandex가 해당합니다. [지역 검색 엔진](#regional-search-engines)과 [사이트 소유권 확인](#site-verification)을 참고하세요.

## 다른 패키지가 추가하는 기능 {#what-the-other-packages-add}

- **laravel-seo-filament**는 실시간 카운터와 SERP 미리보기에서 같은 길이 정책을 사용합니다. 1.9부터 [언어별 `seo_meta` 행](/ko/guide/filament#several-languages)을 편집합니다. 로케일마다 자체 카운터, 미리보기와 대체값 표시가 있는 탭을 제공하거나, 번역 플러그인의 로케일 전환을 따릅니다.
- **laravel-seo-pro**는 스캔의 `title_length` / `description_length` 검사와 AI 지원 프롬프트에서 같은 정책을 사용합니다. 2.34부터 페이지의 언어에 맞춰 분석합니다. 중국어·일본어·태국어용 ICU 단어 분할, Snowball 어간 추출, `CaseFolder` 헬퍼를 통한 로케일별 키워드 일치, 추정 입력값을 사용하는 10개 언어의 공개 가독성 공식, CJK의 명시된 휴리스틱, 그리스어·우크라이나어·체코어의 LIX(방식 명시), 16개 언어의 불용어, `html lang` 및 hreflang 상호 참조 스캔 검사, 페이지 언어를 지정하는 AI 프롬프트, dompdf가 그릴 수 없는 문자 체계용 Chrome 렌더링 보고서를 제공합니다. [온페이지 체크리스트](/ko/pro/on-page-checklist#keyword-matching), [스캔 문제](/ko/pro/scan-issues), [AI 지원](/ko/pro/ai-assist#output-language)과 [보고서](/ko/pro/reports#reports-in-every-script-browsershot-renderer)를 참고하세요.

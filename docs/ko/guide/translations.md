---
description: "Rankbeam의 감사 결과, 편집기 경고, Filament 레이블은 앱 로캘을 따릅니다. 언어 파일을 게시해 문구를 재정의하거나 새로운 언어 번역에 기여할 수 있습니다."
---

# 번역 {#translations}

패키지가 출력하는 모든 사용자용 문자열은 Laravel 언어 항목입니다. 감사 결과, Filament 필드 아래의 실시간 편집기 경고, 레이블, 미리보기, 보고서가 모두 포함됩니다. 패키지는 `app()->getLocale()`를 따르므로 이탈리아어로 실행되는 패널에서는 별도 설정 없이 이탈리아어를 표시합니다.

문제와 경고의 **코드**(`missing_title`, `title_too_long` 등)는 바뀌지 않으며 번역하지도 않습니다. 코드에 연결된 사람이 읽는 문장만 번역합니다.

제공되는 언어는 영어, 이탈리아어(기존 문구는 검토되었으나 변경된 문구는 재검토 필요), 그리고 1차 번역인 독일어, 프랑스어, 스페인어, 브라질 포르투갈어, 네덜란드어, 터키어, 러시아어, 폴란드어(Tier 1)입니다. core 3.16 / Filament 1.10 / Pro 2.35부터는 일본어, 중국어 간체(`zh_CN`), 중국어 번체(`zh_TW`), 한국어, 그리스어, 우크라이나어, 체코어(Tier 2)도 포함됩니다. 로캘별 정확한 상태는 [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md)에 있습니다. 1차 번역이 정식 지원 언어가 되려면 원어민 검토가 필요합니다.

## 문구 재정의 {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

그다음 `lang/vendor/seo/{locale}/seo.php`와 다른 패키지의 인접 언어 폴더를 편집하세요. 남겨 둔 키는 재정의되고, 나머지는 패키지 파일로, 그다음 영어로 폴백합니다.

## 새 언어에 기여 {#contribute-a-language}

`en` 파일을 해당 로캘로 복사하고 값을 번역하되 모든 `:placeholder`를 유지하세요. 테스트 모음을 실행한 다음 풀 리퀘스트를 여세요. 키 누락, 불필요한 키, 빈 값, 자리표시자 누락이 있으면 일치성 테스트가 실패합니다. 전체 규칙과 용어집은 [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md)에 있습니다.

## 의도적으로 번역하지 않는 항목 {#what-is-not-translated-on-purpose}

CLI 표시는 기본적으로 영어입니다. 지원되는 메시지와 감사 요약을 번역하려면 `seo.cli_locale` / `SEO_CLI_LOCALE`를 설정하거나 `--display-locale=it`를 전달하세요. Pro에는 별도의 `seo-pro.cli_locale` 설정이 있습니다. 표시 언어는 `--locale`로 선택하는 콘텐츠 로캘과 별개입니다.

- 명령 도움말, 유지 관리 진단, `seo:explain` 출력은 영어로 유지됩니다. PASS/WARN/FAIL 레이블도 변하지 않습니다.
- 렌더링된 HTML(`<meta>`, JSON-LD)은 패키지 언어가 아니라 콘텐츠 언어를 사용합니다.
- 문제 코드와 JSON 키/상태 코드는 안정적인 식별자로 유지됩니다. `--json` 출력에서 사람이 읽는 레이블은 번역될 수 있으므로, 통합 코드는 키와 코드를 사용해야 합니다.

## 나머지 절반: 콘텐츠의 언어 {#the-other-half-your-content-s-language}

이 페이지는 *패키지*가 사용하는 언어에 관한 설명입니다. 문자 체계별 제목 길이 예산, 잘라내기, 대소문자, hreflang 정책, `inLanguage`, 지역별 검색 엔진, OG 이미지 글꼴처럼 *콘텐츠*의 언어를 이해하는 방법은 [다국어 콘텐츠](/ko/guide/multilingual)에서 설명합니다.

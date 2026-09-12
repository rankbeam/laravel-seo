---
title: Rankbeam이란? Laravel SEO 인프라 설명
description: "Rankbeam은 Laravel용 오픈 코어 SEO 인프라입니다. 무료 MIT 코어는 메타데이터, 표준 URL, JSON-LD, 사이트맵, 크롤러 제어를 제공하며 상용 Pro 모니터링 엔진과 선택적 Filament UI를 더할 수 있습니다."
---

# Rankbeam이란? {#what-is-rankbeam}

**Rankbeam은 Laravel용 오픈 코어 SEO 인프라입니다. 무료 MIT 코어가 메타데이터, 표준 URL, 소셜 카드, 연결된 JSON-LD, 사이트맵, 크롤러 제어를 제공하며 선택적으로 상용 Pro 모니터링과 작업 흐름을 추가할 수 있습니다.** 앱 옆에 붙이는 런타임 태그 도우미에 그치지 않습니다. 자신의 모델과 설정에서 SEO 값을 결정하고 같은 유형화된 데이터를 Blade, Inertia head, JSON API로 렌더링하며, Pro를 사용하면 배포 이후에도 계속 관측합니다.

## 패키지 제품군 {#the-package-family}

Rankbeam은 하나의 지원 매트릭스를 공유하는 세 패키지입니다.

| 패키지 | 라이선스 | 내용 |
|---|---|---|
| [`rankbeam/laravel-seo`](https://github.com/rankbeam/laravel-seo) | **MIT, 무료** | 코어 — 메타 값 결정, 연결된 JSON-LD 스키마 그래프, XML 사이트맵, 크롤러 제어, 무료 `seo:audit`, 가져오기 도구 |
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | **MIT, 무료** | 코어의 `seo_meta`에 기록하는 Filament 4/5 폼 필드와 실시간 미리보기 |
| `rankbeam/laravel-seo-pro` | **상용** | 운영 엔진 — 0–100 점수의 큐 기반 스캔, 리디렉션 관리자, IP를 저장하지 않는 404 모니터, 깨진 링크 크롤러, Search Console 인사이트, 자신의 키를 사용하는 AI 지원 |

경계는 의도적입니다. 렌더링된 페이지가 출력하는 모든 것은 MIT이며 계속 무료입니다. 유료 대상은 운영 환경의 **감사 및 모니터링** 계층입니다. 상용 Pro는 별도 패키지이며 무료 코어 안에 포함되지 않습니다.

## 대상 사용자 {#who-it-s-for}

SEO가 **저장되고 모델에 연결되며 여러 로케일, 헤드리스, 감사가 필요할 때**, 즉 동적 또는 모델 기반 콘텐츠를 가진 운영 Laravel 앱에서 Rankbeam의 가치가 드러납니다. 제목과 설명만 필요한 소수의 정적 페이지에는 작은 런타임 메타 도우미가 더 적합합니다. 아래 [조합형 스택도 여전히 적합한 경우](#what-is-honestly-not-in-the-free-core)에서도 이를 명확히 설명합니다.

## 지원 버전 {#supported-versions}

전체 제품군의 공통 매트릭스입니다.

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13 (Laravel 13은 PHP 8.3+ 필요)
- **Filament** 4 / 5 (선택 사항)

## Rankbeam이 대체하지 않는 것 {#what-rankbeam-doesn-t-replace}

Rankbeam은 Laravel 앱 자체의 SEO 출력을 조율합니다. 호스팅형 순위 추적기, 키워드 조사 도구 모음, 분석 제품이 아니며 순위, 색인 생성, AI 인용을 약속하지 않습니다. XML 사이트맵은 새로 발명하지 않고 [`spatie/laravel-sitemap`](https://packagist.org/packages/spatie/laravel-sitemap)을 사용하며 콘텐츠, 라우팅, 분석은 원래 위치에 둡니다.

처음이라면 [무료 코어를 설치](/ko/guide/installation)하거나 아래의 실제 운영 전환 근거를 읽어 보세요. Pro와 초기 구매자 혜택은 [rankbeam.dev](https://rankbeam.dev/ko/)에서 확인할 수 있습니다.

## 패키지 세 개와 연결 코드를 조합하지 않는 이유 {#why-not-three-packages-glue}

대부분의 Laravel 앱에는 “SEO 패키지” 하나가 아니라 **SEO 스택**이 있습니다. 모델별 메타데이터를 저장하는 패키지, Filament 필드를 추가하는 패키지, 페이지를 스캔하는 패키지, 그리고 셋을 일치시키는 앱 전용 연결 계층입니다. 각 구성 요소 자체는 좋습니다. 비용은 그 사이의 연결부에 있으며, 연결 코드는 계속 직접 유지해야 합니다.

이 페이지는 바로 그런 조합형 스택을 제거하고 Rankbeam 제품군으로 바꾼 실제 운영 전환의 근거입니다. 아래 수치는 홍보를 위해 만든 값이 아닌 측정값입니다.

## 참조 앱 {#the-reference-app}

여기서는 익명화한 실제 운영 Laravel 콘텐츠 사이트입니다.

- 운영 약 3개월인 **병원 / 기관 콘텐츠 사이트**.
- **WordPress에서 이전**, 사이트맵 기준 약 900페이지.
- 하루 약 **20,000 방문**.
- **Laravel 12**, **Filament 4** 관리 화면, Blade 프런트엔드, MySQL.

전환 전 SEO 스택:

| 계층 | 패키지 |
|---|---|
| 메타 저장(모델별 `seo` 테이블) | [`ralphjsmit/laravel-seo`](https://github.com/ralphjsmit/laravel-seo) |
| Filament SEO 필드 | `ralphjsmit/laravel-filament-seo` |
| 페이지 스캐너 | `backstage/laravel-seo-scanner` |
| 나머지 연결부 | **앱 전용 클래스 약 30개** |

세 패키지를 제거하고 Rankbeam **코어 + Pro + Filament**를 설치한 뒤 SEO 테스트를 실행했습니다. 앱은 **SEO 회귀 0건**으로 부팅되었습니다. 다음은 실제 연결 계층의 비용과 제거된 부분입니다.

## 전환으로 제거한 것 {#what-the-swap-deleted}

스캐너 스택을 Rankbeam으로 바꾸면서 **앱 전용 클래스 12개를 완전히 삭제**했습니다. 같은 기능을 패키지 제품군이 제공하므로 앱이 더 이상 관리하지 않아도 됩니다.

| 삭제한 앱 클래스 | 기존 역할 | 현재 제공 주체 |
|---|---|---|
| `Services/SeoService.php` | 앱 SEO 진입점 래퍼 | 코어 리졸버 + `SEO` 파사드 |
| `Services/SeoWarningEvaluator.php` | 제목/설명 길이와 이미지 크기 임계값 | 코어 `SEOWarningEvaluator`(감사, 미리보기, 스캔 공유) |
| `Services/Seo/SeoAssetInspector.php` | 로컬 이미지 크기 검사 | 코어 `LocalImageInspector` |
| `Jobs/ScanAllPagesSeo.php` | 사이트 전체 큐 스캔 배정 | Pro 큐 [스캔 파이프라인](/ko/pro/scan-issues) |
| `Jobs/ScanPageSeo.php` | 페이지별 스캔 | Pro `PageScanner` |
| `Jobs/ScanPublicPageSeo.php` | 공개 페이지별 스캔 | Pro 스캔 파이프라인 |
| `Models/SeoScanBatch.php` | 스캔 실행 상태 기록 | Pro `seo_scan_runs` |
| `Filament/Pages/SeoDashboard.php` | SEO 관리 대시보드 | Pro `SeoDashboard` 플러그인 |
| `Filament/Widgets/SeoScanProgressWidget.php` | 스캔 진행 위젯 | Pro 스캔 위젯 |
| `Filament/Widgets/SeoTrendChartWidget.php` | 스캔 추세 위젯 | Pro 스캔 위젯 |
| `Facades/Seo.php` | 저장 패키지를 감싼 앱 파사드 | 코어 `SEO` 파사드 |
| `Console/Commands/RecoverLegacySeoMetadata.php` | 일회성 메타데이터 복구 | 코어 [가져오기 도구](/ko/guide/migrate-from-wordpress)(`seo:import-from`) |

::: info 나머지에 대한 정확한 집계
전환 당시 앱의 자체 깨진 링크 크롤러는 의도적으로 **유지**했습니다. 스캔 작업, 검사기, 시작 URL 빌더, 소스 리졸버, 모델 두 개, 열거형 두 개, 이벤트 두 개, Filament 리소스와 위젯 세 개, 명령 두 개로 약 17개 클래스입니다. 메타/스키마 도우미 `CustomSEO`, `EntitySeoSection`, `DynamicSeoDataResolver`, `SitewideSchema`, `SeoKeywords`까지 합해 약 **22개 클래스가 더 남았습니다**. Rankbeam의 대체 기능이 나중에 추가되어 첫날에 삭제하지 않았습니다. 자체 크롤러는 [Pro 깨진 링크 크롤러](/ko/pro/production), `CustomSEO`/`EntitySeoSection` 클래스는 Filament **관계 모델 대상** 및 **검색 결과/소셜 미리보기**, `SitewideSchema`는 코어 **스키마 그래프**가 대신합니다. 전체 제품군을 도입하면 총 **약 36개 클래스** 규모의 사용자 지정 영역이 앱이 아닌 패키지의 책임이 됩니다.
:::

어느 패키지가 나쁘다는 뜻이 아닙니다. 메타 변경을 스캐너, 대시보드, 렌더링된 head에 일치시키는 12개 이상의 *연동* 클래스는 업스트림도, 자신의 것 외의 테스트도, 다른 사용자의 버그 보고도 없는 전용 코드라는 뜻입니다.

## 기능 비교 {#side-by-side}

| 기능 | 조합형 스택(패키지 3개 + 연결 코드) | Rankbeam 제품군 |
|---|---|---|
| 모델별 메타 저장 | 메타 패키지 | **코어**(`seo_meta`, MIT) |
| **로케일을 인식하는** 저장 | 보통 연결 코드 담당 | **코어** — 열로 로케일 범위를 지정한 `seo_meta` |
| Filament SEO 필드 | Filament-SEO 패키지 | **`laravel-seo-filament`**(MIT) |
| **관계** 모델의 SEO 편집 | 필드 컴포넌트 직접 래핑 | 전용 `target:` 리졸버 |
| 실시간 **검색 결과 + 소셜** 미리보기 | 직접 만든 Blade/Alpine | 내장 탭형 편집 미리보기 |
| 헤드리스 렌더링(Inertia / Livewire / JSON) | 참조 앱은 Blade 사용; 다른 스택은 연동 필요 | **리졸버 하나** → Blade, Inertia, Livewire, JSON([계약 테스트](/ko/contributing/rendering-contract)) |
| 페이지 스캐너 + 정렬된 문제 | 스캐너 패키지 | **Pro** [스캔 파이프라인](/ko/pro/scan-issues) + `IssueRegistry` |
| 0–100 점수 | 연결 코드 / 없음 | **Pro**의 투명하고 [버전이 지정된 평가 기준](/ko/pro/scoring) |
| 리디렉션 + 404 복구 | 다른 패키지 / 전용 코드 | **Pro** 리디렉션 관리자 + IP를 저장하지 않는 404 모니터 |
| 깨진 링크 크롤러 | 앱이 직접 개발 | **Pro** 범위 제한 및 재개 가능 크롤러 |
| JSON-LD 스키마 **그래프** | 빌더 + 직접 연결한 `@id` | **코어**의 상호 연결된 Organization/WebSite/WebPage 그래프 |
| XML 사이트맵 | 사이트맵 패키지 | **코어** 사이트맵 레지스트리(`spatie/laravel-sitemap` 사용) |
| WordPress / Yoast / Rank Math 가져오기 | 일회성 스크립트 | **코어** `seo:import-from` + [실행 절차](/ko/guide/wordpress-migration-runbook) |
| **연결부 유지 책임** | **자신** | 하나의 릴리스 흐름을 가진 패키지 제품군 |

## 연결 코드로 잘하기 어려운 세 가지 {#the-three-things-glue-can-t-do-well}

**1 — 일관된 제품군과 하나의 릴리스 흐름.** 세 패키지에는 세 유지관리자, 세 변경 이력, 세 업그레이드 주기가 있습니다. 연결 코드는 그 사이의 차이를 흡수해야 합니다. Rankbeam 코어, Pro, Filament는 단일 [지원 매트릭스](#tested-where-it-runs)와 문서화된 [업그레이드 경계](/ko/reference/configuration) 아래 함께 버전을 관리합니다. 동작 변경을 두 패키지의 불일치로 발견하는 대신 한곳에서 안내합니다.

**2 — 관례가 아닌 열로 관리하는 로케일 저장.** `seo_meta`는 저장 계층에서 다형성**이며** 로케일 범위를 가집니다. 다국어 SEO는 직렬화 덩어리나 나중에 추가한 연결 테이블이 아니라 `(model, locale)`별 행입니다. [리졸버 우선순위](/ko/concepts/resolver-precedence)가 활성 로케일을 직접 읽습니다.

**3 — 하나의 리졸버에서 헤드리스 렌더링.** Rankbeam은 유형화된 `SEOData`를 결정하고 *같은* 데이터를 HTML, Inertia `Head` 페이로드, JSON 배열로 렌더링합니다. [Blade](/ko/guide/blade), [Inertia](/ko/guide/inertia-json)(Vue/React/Svelte), [Livewire](/ko/guide/livewire)가 공통 [렌더링 계약](/ko/contributing/rendering-contract)으로 검증됩니다. 관리 패널은 필수가 아니며 모든 Pro 기능은 [artisan에서 헤드리스](/ko/pro/headless)로도 실행됩니다.

## 무료 코어에 포함되지 않는 것 {#what-is-honestly-not-in-the-free-core}

Rankbeam은 오픈 코어이며 경계가 의도적입니다. `composer require`를 실행하기 전에 무엇을 얻는지 정확히 알 수 있습니다.

| 패키지 | 라이선스 | 포함 내용 |
|---|---|---|
| `rankbeam/laravel-seo` | **MIT, 무료** | 메타 값 결정, JSON-LD 스키마 그래프, 사이트맵, 무료 `seo:audit`, 가져오기 도구 |
| `rankbeam/laravel-seo-filament` | **MIT, 무료** | `seo_meta`에 기록하는 Filament 폼 필드/섹션 |
| `rankbeam/laravel-seo-pro` | **상용** | 큐 스캔 + 정렬된 문제 + 0–100 점수, 리디렉션, 404 모니터, 깨진 링크 크롤러, Search Console, AI 지원, Filament 대시보드 |

유료 대상은 스캔, 점수, 리디렉션, 404 복구, 크롤러를 포함한 **기술 SEO 감사** 및 **사이트 모니터링** 도구 모음입니다. 메타데이터 엔진, 스키마 그래프, 사이트맵, 무료 프로세스 내 감사는 MIT이며 무료로 유지됩니다.

확인할 수 있는 두 가지 특성:

- **런타임 라이선스 검사 없음.** Pro는 설치 시 프로젝트별로 라이선스가 적용됩니다. 라이선스 확인을 위해 외부로 통신하지 않으며 앱을 중단시킬 킬 스위치도 없습니다. 자신의 로그를 위한 *로컬* 운영 측정 데이터는 기본 제공하지만 끌 수 있고 Rankbeam으로 보내지 않습니다.
- **자신의 키를 사용하는 AI.** 선택적 [AI 지원](/ko/pro/ai-assist)은 *자신의* Anthropic, OpenAI, Google 또는 로컬 모델 키를 사용합니다. 프록시, 사용량 기준 과금, 재판매를 거치지 않으며 기본적으로 꺼져 있습니다.

::: tip 조합형 스택도 여전히 적합한 경우
소수의 정적 페이지에 `<title>` 하나와 설명만 필요하면 런타임 태그 빌더로 충분합니다. SEO가 **저장되고**, **여러 로케일을 지원하며**, **모델에 연결되고**, **헤드리스로 제공되며**, **감사 대상이 되는** 시점, 즉 패키지 사이 연결 코드가 실제 유지관리 작업이 되는 시점에 Rankbeam의 가치가 드러납니다.
:::

## 위험을 줄이는 WordPress 전환 {#the-lowest-risk-switch-off-wordpress}

참조 앱은 약 900페이지의 WordPress 이전 사례였습니다. 수년간의 Yoast/Rank Math 최적화를 잃을 위험이 큰 대상입니다. Rankbeam은 이를 안전하게 진행할 경로로 다룹니다.

1. **함께 실행.** 운영 사이트 옆에 Rankbeam을 구성하고 아직 아무것도 제거하지 않습니다.
2. **가져오기(먼저 모의 실행).** `seo:import-from yoast` / `rank-math` / `wordpress-csv`가 제목, 설명, 표준 URL, robots, 포커스 키워드, 소셜 덮어쓰기 값을 읽습니다. 도구는 **멱등적**이며 **기본적으로 빈 값만 채웁니다**. `--overwrite`가 없으면 이미 설정한 메타데이터를 보존하고 `--dry-run` 옵션은 아무것도 기록하지 않습니다.
3. **리디렉션 전달.** 코어가 버전이 있는 리디렉션 CSV를 내보내고 Pro의 `seo-pro:redirects-import` 명령이 루프, 안전하지 않은 대상, 중복을 거부하며 모든 행을 검증한 뒤 기록합니다.
4. **삭제 전에 검증.** `seo:audit --strict`는 어느 문제에든 0이 아닌 코드로 종료하는 CI/전환 검사입니다. 기존 WordPress 데이터베이스는 삭제하기로 선택할 때까지 변경하지 않습니다.

전체 순서는 [WordPress 마이그레이션 실행 절차](/ko/guide/wordpress-migration-runbook), 필드별 매핑과 토큰 처리는 [WordPress에서 마이그레이션](/ko/guide/migrate-from-wordpress)에 있습니다. **Laravel** SEO 패키지(ralphjsmit, artesaos, Spatie)에서 옮긴다면 [패키지 마이그레이션 가이드](/ko/guide/migrate-from-other-packages)를 참고하세요.

## 규모가 커져도 동작하나요? {#does-it-hold-up-at-scale}

참조 앱에서 가장 까다로운 두 조건은 하루 약 20k 요청마다 실행되는 리졸버와 약 900페이지 링크 크롤링입니다. 각각 테스트 스위트에 벤치마크가 있습니다. 손으로 조정한 실행 시간 대신 쿼리 수와 작업 범위 제한이라는 **결정론적** 개선을 검증합니다.

**리졸버 캐시 — 준비된 캐시 적중은 DB에 접근하지 않습니다.** 선택적 결과 캐시를 켜면 캐시 적중이 *전체* 우선순위 체인을 건너뜁니다. 벤치마크는 같은 모델의 값을 25번 결정합니다.

| | DB 쿼리 |
|---|---|
| 캐시 없음(매번 `seo_meta` 재조회) | **≥ 25** |
| 준비된 캐시 적중 | **0** |

캐시는 **기본적으로 꺼져 있으며** 규모에 대응하는 수단으로 문서화되어 있습니다. `seo_meta`, 콘텐츠 필드, 기본값이 바뀌면 해당 항목이 무효화됩니다. [설정 → 캐시](/ko/reference/configuration)를 참고하세요.

**깨진 링크 크롤러 — 900페이지에서도 범위 제한.** 크롤러 벤치마크는 생성한 약 900페이지를 실제 작업으로 처리합니다.

- 작업당 페이지 상한 50에서 **범위가 제한된 작업 ≥ 18개**에 걸쳐 완료합니다.
- **어떤 작업도** 50페이지 상한을 넘지 않습니다.
- **1,800개 링크**를 검사하고 모든 끊긴 대상을 지속적으로 보존되는 확정된 깨진 링크 발견 사항으로 만듭니다.

실행별 유한 상한과 작업별 엄격한 시간 예산으로 제한하며, 시작 URL 요청과 **모든 리디렉션 단계**에 SSRF 검증을 적용합니다. DB 임대로 범위별 활성 실행 하나만 허용합니다. 운영 방법은 [운영 환경 설정 가이드](/ko/pro/production)에 있습니다.

## 실행 환경에서 테스트 {#tested-where-it-runs}

세 개가 아닌 제품군 전체의 지원 매트릭스 하나입니다.

- **PHP** 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13)
- **Laravel** 11 / 12 / 13
- **Filament** 4 / 5

## 패키지 세 개를 직접 연결해야 할까요? {#so-—-why-glue-three-packages-together}

조합형 스택을 통합하는 데 전용 클래스 12개 이상, 직접 통제하지 못하는 릴리스 주기, 스택별 렌더링 연동, 수동 로케일 처리가 필요하다면 연결 비용은 작지 않습니다. 일관된 헤드리스 제품군이 로케일을 기본 지원하고 그 연결 코드를 제거하며 실제 900페이지 / 일 20k 방문 앱에서 검증되었다면, 조합형 스택이 언제나 안전한 기본 선택은 아닙니다.

[빠른 시작](/ko/guide/quickstart)으로 시작하세요. `composer require`에서 완전히 렌더링된 `<head>`까지 5분을 목표로 안내합니다.

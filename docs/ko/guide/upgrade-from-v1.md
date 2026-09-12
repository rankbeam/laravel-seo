---
description: "fibonoir/laravel-seo v1에서 rankbeam/laravel-seo v2로 업그레이드하세요. 패키지 이름이 바뀌고 코어 범위가 메타 값 결정, 렌더링, JSON-LD, 사이트맵으로 정리됩니다."
---

# fibonoir/laravel-seo v1에서 업그레이드 {#upgrading-from-fibonoir-laravel-seo-v1}

v2.0.0에서는 패키지 이름이 `rankbeam/laravel-seo`로 바뀌고, 코어가 메타 값 결정, 렌더링, JSON-LD, 사이트맵에 집중하도록 정리되었습니다. 분석기, 스캐너, 리디렉션, 404 모니터, 관리 UI는 별도 패키지로 이동했습니다.

## 1. 패키지 교체 {#_1-swap-the-package}

```bash
composer remove fibonoir/laravel-seo
composer require rankbeam/laravel-seo
```

## 2. 네임스페이스 갱신 {#_2-update-namespaces}

클래스 이름은 그대로이며 루트 네임스페이스만 `Fibonoir\LaravelSEO\*` → `Rankbeam\Seo\*`로 바뀌었습니다. 프로젝트 전체 찾기 및 바꾸기로 처리할 수 있습니다. `SEO` 파사드 별칭과 `@seo` Blade 지시문은 그대로입니다.

## 3. 오래된 게시 파일 삭제 {#_3-delete-stale-published-files}

파일이나 테이블을 제거하기 전에 게시한 설정을 백업하고 영향받는 데이터를 내보내세요. 복원할 수 있는지 확인하세요. 이 가이드는 v1의 리디렉션, 404 또는 스캔 이력을 Pro의 다른 스키마로 마이그레이션하지 않습니다. 아래의 코어 테이블 호환성은 `seo_meta`와 `seo_defaults`에만 적용됩니다.

::: warning 오류 메시지 없이 문제가 생길 수 있습니다
v1의 `seo:install`가 앱에 게시한 파일은 오류 메시지를 하나도 내지 않은 채 v2 패키지와 충돌할 수 있습니다.
:::

- **`config/seo.php`** — v1 또는 v1 설치 프로그램이 남길 수 있는 `ralphjsmit/laravel-seo`에서 게시한 파일이라면 패키지 설정을 가려 `site_name`와 모든 `{site_name}` 템플릿이 null이 될 수 있습니다. 삭제한 뒤 `php artisan vendor:publish --tag=seo-config`로 다시 게시하세요.
- 코어가 더 이상 소유하지 않는 테이블의 **v1 마이그레이션**: `seo_redirects`, `seo_404_logs`, `seo_scan_runs`, `seo_scan_issues`, `seo_analytics_cache`, `seo_internal_links_index`. 마이그레이션 파일을 제거하세요. 운영 환경에 테이블이 있다면 `rankbeam/laravel-seo-pro`를 설치하기 **전에** 삭제하세요. Pro는 다른 스키마로 다시 생성합니다.
- v1의 Filament 3 / Livewire / Vue / React 흐름이 `app/`와 `resources/js` 아래에 **게시한 스텁** — 더 이상 존재하지 않는 클래스를 참조합니다.

두 코어 테이블(`seo_meta`, `seo_defaults`)은 스키마가 호환되므로 업그레이드 후에도 데이터가 유지됩니다.

## 4. 제거된 기능의 새 위치 {#_4-removed-features-and-where-they-went}

| v1 기능 | 현재 위치 |
|---|---|
| Filament SEO 폼 섹션 | [`rankbeam/laravel-seo-filament`](/ko/guide/filament) (무료, MIT) |
| 콘텐츠 분석기(32개 규칙) | 이 마이그레이션은 기존 분석기를 이어받지 않습니다. 기술 SEO 문제 탐지는 `rankbeam/laravel-seo-pro`의 사이트 스캐너에서 제공하며, 수치 SEO 점수는 문제에서 산출하는 Pro 기능입니다. |
| 사이트 전체 스캐너 | `rankbeam/laravel-seo-pro` — 큐 기반 파이프라인과 대시보드 |
| 리디렉션 관리자 | `rankbeam/laravel-seo-pro` — 정규식 검증과 오픈 리디렉션 방지로 강화 |
| 404 모니터 | `rankbeam/laravel-seo-pro` — 개인정보 보호 우선(기본적으로 IP 저장 안 함) |
| GA4 분석, 내부 링크 | `rankbeam/laravel-seo-pro` 백로그 |
| `seo:install` 설치 프로그램 | 제거됨 — 패키지 추가, 설정 게시, 마이그레이션 순서로 설치 |

## 5. 검토할 동작 변경 {#_5-behavior-changes-to-review}

- **`og:image` / `twitter:image`는 항상 절대 URL입니다.** v1은 수동으로 지정한 상대 경로를 그대로 출력했습니다.
- **파생된 표준 URL에서는 쿼리 문자열을 제거합니다.** 명시적으로 지정한 표준 URL은 그대로 보존합니다.
- **사이트맵 자동 탐색은 등록된 소스에 우선권을 줍니다.** 등록된 `sitemap-posts.xml` 옆에 `sitemap-post.xml`가 중복으로 생기지 않습니다.
- **JSON-LD는 `JSON_HEX_*` 이스케이프를 적용합니다.** 원시 script 출력을 후처리한다면 `<` 형태의 이스케이프를 고려하세요.

## 6. 알려진 주의 사항 {#_6-known-gotchas}

- Laravel의 기본 `DatabaseSeeder`는 `WithoutModelEvents`를 사용하므로 시더에서 `HasSEO`의 자동 생성 훅이 비활성화됩니다.
- 라우트 기본 제목 템플릿에 이미 브랜드가 들어 있다면 설정된 `title_suffix`로 템플릿을 끝내세요. 그러면 리졸버가 다시 덧붙이지 않습니다.

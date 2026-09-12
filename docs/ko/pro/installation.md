---
description: "laravel-seo-pro를 설치해 코어에 큐 기반 사이트 스캔과 문제 추적, 리디렉션 관리자, 404 모니터를 추가하세요. 모든 Laravel 11–13 앱에서 실행되며 Filament는 선택 사항입니다."
---

# Pro 설치 {#installing-pro}

`rankbeam/laravel-seo-pro`는 코어 패키지에 큐 기반 사이트 스캔과 문제 추적, 리디렉션 관리자, 404 모니터를 추가합니다. 엔진은 Blade, Inertia, 순수 API 등 **모든 Laravel 11–13 앱**에서 실행됩니다. Filament는 선택적 UI 계층입니다. 설치하면 SEO 대시보드, 리디렉션 관리자, 404 모니터를 패널 페이지로 사용할 수 있습니다. 설치하지 않으면 [artisan 명령](/ko/pro/headless)으로 모든 기능을 관리합니다.

## 요구 사항 {#requirements}

| | |
|---|---|
| PHP | 8.2–8.4 (Laravel 11); 8.2–8.5 (Laravel 12); 8.3–8.5 (Laravel 13) |
| Laravel | 11, 12, 13 |
| `rankbeam/laravel-seo` | ^3.20 (Pro 2.40+가 자동 설치) |
| `filament/filament` | **선택 사항** — 4.x 또는 5.x, 관리 UI용 |
| `rankbeam/laravel-seo-filament` | **선택 사항** — Pro 2.36+에서 SEO 편집기를 사용할 때 ^1.11 |

기존 Laravel 앱과 설정된 데이터베이스로 시작하세요. 먼저 [코어 빠른 시작](/ko/guide/quickstart)을 완료해 모델이 메타데이터를 렌더링하고 코어 테이블이 존재하도록 하세요. 아래 Composer 자격 증명은 Pro 라이선스로 제공됩니다.

결과를 화면으로 보려면 [스캔 → 수정 → 보고서](/ko/pro/walkthrough)를 참고하세요.

## 패키지 설치 {#install-the-package}

Pro는 라이선스에 연결된 비공개 Composer 저장소로 배포됩니다. 저장소를 한 번 추가한 뒤 패키지를 요구하세요. Composer가 라이선스 이메일(사용자 이름)과 라이선스 키(암호)를 요청합니다.

Lemon Squeezy가 공식 판매자로서 결제를 처리합니다. 결제 후 비공개 영수증 페이지에서 다운로드 키와 Composer 안내를 제공합니다. 구매 이메일을 사용자 이름으로 사용하세요. Rankbeam이 패키지 저장소를 호스팅하며 Anystack 계정은 필요하지 않습니다. 영수증 링크와 `auth.json` 파일은 비공개로 유지하세요. 전액 환불은 이후 다운로드와 업데이트 권한을 철회하지만 설치된 애플리케이션을 중단시키지는 않습니다.

```bash
composer config repositories.rankbeam-pro composer https://blog.rankbeam.dev/composer
composer require rankbeam/laravel-seo-pro
```

::: details 비대화형 Composer 인증
CI 또는 비대화형 환경에서는 자격 증명을 미리 저장하세요.

```bash
composer config http-basic.blog.rankbeam.dev you@example.com YOUR-LICENSE-KEY
```

:::

그런 다음 설치 프로그램을 실행하세요.

```bash
php artisan seo-pro:install
```

설치 프로그램은 `config/seo-pro.php`와 Pro 마이그레이션을 게시하고 `migrate`를 실행한 뒤 다음 단계를 출력합니다. 이제 앱 데이터베이스에 코어와 Pro 테이블이 있어야 합니다.

::: details 수동 설치 및 설치 프로그램 플래그
Pro 마이그레이션은 앱에 게시하며 패키지에서 자동 로드하지 않습니다. 같은 작업을 수동으로 수행하려면 다음 단계를 따르세요.

```bash
php artisan vendor:publish --tag=seo-pro-config
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

설치 프로그램은 다시 실행할 수 있습니다. `--no-migrate`는 마이그레이션 없이 파일만 게시합니다. 설정을 포함한 게시 파일을 덮어쓰려는 경우에만 `--force`를 사용하세요.
:::

## 스캔 대상 등록 {#register-scan-targets}

서비스 프로바이더에서 모델 클래스, 이름이 있는 라우트, [사이트맵 레지스트리](/ko/guide/sitemaps)의 전체 항목 중 무엇을 스캔할지 지정하세요.

```php
use App\Models\Post;
use Rankbeam\Seo\Pro\Facades\SeoPro;

public function boot(): void
{
    SeoPro::targets()->register('posts', Post::class);
    // Optional: register named routes that exist in your app.
    // SeoPro::targets()->registerRoutes('static', ['home', 'pricing']);
    // Or discover targets from your registered sitemaps:
    // SeoPro::targets()->fromSitemaps();
}
```

`Post`를 `HasSEO`를 사용하는 자신의 모델로 바꾸세요. 모델 스캔 결과를 보려면 레코드가 하나 이상 필요합니다. 라우트 대상에는 존재하는 라우트 이름을 지정해야 합니다. 모델만 스캔하려면 라우트 등록을 생략하세요.

## 설치 확인 {#verify-your-install}

설정 검사를 실행하세요.

```bash
php artisan seo:doctor
```

코어와 Pro 테이블이 존재하고 애플리케이션 URL이 올바르며 스캔 대상이 나열되는지 확인하세요. 보고된 수정 안내를 따르세요. 아래 인라인 명령을 시험하는 동안 `sync` 큐 경고가 나오는 것은 예상된 동작입니다. 운영 스캔을 예약하기 전에 워커를 설정하세요.

::: details 상태 점검 출력 예시
```
  Rankbeam SEO — health check

  Application
    ✓ app.url is https://shop.example.com
  Database (core)
    ✓ Core tables present (seo_meta, seo_defaults)
  Database (Pro)
    ✓ Pro tables present (redirects, 404 logs, scan runs/issues/results)
  Scanning
    ✓ Scan targets registered: posts, static
    ✓ Scan delta snapshot store present (seo_scan_run_issues)
  Queue
    ! Queue connection is 'sync'
      ↳ Scans run inline on the dispatching request/CLI. Use a real queue …
    ✓ Scan queue: default (seo-pro.scan.queue unset)
  Broken links
    ✓ Broken-link crawler is off (optional)
  AI assist
    ✓ AI assist is off (optional)
  AI bots
    ✓ AI-bot logging is off (optional)
  Search Console
    ✓ Search Console is off (optional)
  Reports
    ✓ Reports on (snapshot store present: seo_report_runs)

  ! Healthy with warnings — 1 warning(s), 11 passed.
```

`seo:doctor` 명령은 네트워크 요청이나 비밀 값 출력 없이 설정과 최근 실행 이력을 검사합니다. 외부 cron 또는 워커가 실행 중임을 증명할 수는 없습니다. 심각한 실패는 0이 아닌 종료 코드를 반환하지만 경고는 그렇지 않습니다. 기계 판독용 결과에는 `--json` 옵션을 사용하세요.
:::

## 첫 스캔 실행 {#five-minute-pro-tour}

```bash
php artisan seo-pro:scan --sync
php artisan seo-pro:scan-status
```

첫 명령은 스캔을 인라인으로 완료하므로 이 초기 확인에는 큐 워커가 필요하지 않습니다. 두 번째 명령은 최근 실행과 결과를 보여 줍니다. 등록된 대상을 처리한 완료 실행이 나와야 합니다. 실패한 대상이 있다면 스캔이 완료되었다고 판단하기 전에 원인을 조사하세요.

보고된 필드 하나를 수정해 저장하고 다시 스캔하세요. [실행 안내](/ko/pro/walkthrough)는 설명 누락 수정과 변경 보고서로 이 과정을 보여 줍니다. [기술 점수](/ko/pro/scoring)는 진단 결과이며 순위 예측이 아닙니다.

## 헤드리스 사용 {#path-b-headless}

패널 없이도 엔진을 사용할 준비가 끝났습니다. [Artisan 명령](/ko/pro/headless)으로 스캔, 문제 확인, 리디렉션 생성, 보고서 생성을 수행할 수 있습니다. 리디렉션 및 404 미들웨어는 기본적으로 자동 등록되며 설정은 `config/seo-pro.php`에 있습니다.

예약 작업에는 [운영 환경 설정](/ko/pro/production)에 따라 큐, 워커, 스케줄러, 보존 정책을 구성하세요.

## Filament 패널 추가 (선택 사항) {#path-a-with-a-filament-panel}

기존 Filament 4 또는 5 패널이 있다면 아래 Pro 플러그인을 등록하세요. 아직 패널이 없다면 먼저 UI 패키지를 설치하고 패널을 만드세요.

```bash
composer require filament/filament rankbeam/laravel-seo-filament
php artisan filament:install --panels
php artisan make:filament-user
```

```php
use Rankbeam\Seo\Pro\Filament\SeoProPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(SeoProPlugin::make());
}
```

**SEO 대시보드**(전체 스캔 동작, 실시간 진행 상황, 원클릭 재스캔을 제공하는 문제 목록), **리디렉션 관리자**, 원클릭 *리디렉션 만들기* 동작이 있는 **404 모니터**가 추가됩니다. `rankbeam/laravel-seo-filament`는 리소스 폼에 [SEO 필드 섹션](/ko/guide/filament)도 제공합니다.

## 문제 해결 {#troubleshooting}

| 결과 | 다음 단계 |
|---|---|
| Composer가 자격 증명을 거부함 | `blog.rankbeam.dev`의 라이선스 이메일과 키를 확인하세요. 자격 증명을 버전 관리에 넣지 마세요. |
| Doctor가 테이블 누락을 보고함 | 코어 빠른 시작을 완료한 뒤 앱과 같은 데이터베이스를 대상으로 `seo-pro:install` 명령과 `migrate`를 실행하세요. |
| 스캔이 대상을 처리하지 않음 | 프로바이더 등록과 모델의 레코드 존재 여부를 확인하세요. |
| 큐 스캔이 대기 상태에 머묾 | 설정된 큐 워커를 시작하거나 `--sync`로 인라인 확인을 수행하세요. |
| 대상 실패 | 재스캔 전에 실행 세부 정보, 라우트 이름, 애플리케이션 URL을 확인하세요. |
| 대시보드가 없음 | 실제 사용하는 패널에 `SeoProPlugin`를 등록하고 접근 게이트를 확인하세요. |

워커 복구와 지속적인 운영은 [운영 환경 설정](/ko/pro/production)을 참고하세요.

## 라이선스 및 환불 {#license}

초기 구매자 라이선스는 €179 일회 결제로, 클라이언트 프로젝트를 포함한 운영 프로젝트 최대 다섯 개와 평생 업데이트를 제공합니다. 해당 프로젝트의 개발 및 스테이징 사본은 별도로 세지 않습니다. 설치/마이그레이션 지원, 60분 설치 상담, 안내된 출시 키트가 포함됩니다. 30일 이내에 영수증 또는 valentinogoxhaj@gmail.com으로 조건 없는 전액 환불을 요청할 수 있습니다. 전액 환불 후에는 Pro 사용을 중단하세요. 라이선스가 적용된 프로젝트를 위해 Pro를 수정할 수 있지만 소스를 공개하거나 독립 패키지 또는 스타터 키트로 재판매할 수는 없습니다. 전체 라이선스 약관은 패키지에 포함되어 있습니다.

클라이언트 프로젝트를 포함해 운영 프로젝트 최대 다섯 개에서 Pro를 사용할 수 있습니다. 해당 프로젝트의 개발, 스테이징, 테스트 사본은 별도로 세지 않습니다. 평생 업데이트에는 향후 Pro 릴리스가 포함되지만 지속적인 개별 구현 작업은 포함되지 않습니다.

60분 설치 및 설정 상담 한 번과 최초 프로젝트 한 개의 메타데이터 마이그레이션이 포함됩니다. 마이그레이션은 지원되는 소스를 대상으로 하며 시작 전에 범위를 확인합니다. 사용자 지정 애플리케이션 변경은 별도 견적입니다. 출시 설정은 같은 프로젝트에서 무료 Core 기능을 사용해 llms.txt, robots.txt의 AI 크롤러 규칙, 봇용 Markdown 응답을 검토하고 구성하는 작업을 포함합니다. 포함된 지원을 예약하려면 hello@rankbeam.dev로 이메일을 보내세요.

주문에는 구매 시 표시된 혜택이 적용됩니다.

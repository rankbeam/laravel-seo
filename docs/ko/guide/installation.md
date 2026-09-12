---
description: Composer로 rankbeam/laravel-seo를 설치하고 설정을 게시한 다음 마이그레이션을 실행합니다. Laravel 11, 12, 13의 요구 사항과 설정 방법을 설명합니다.
---

# 설치 {#installation}

## 요구 사항 {#requirements}

- Laravel 11: PHP 8.2–8.4; Laravel 12: PHP 8.2–8.5; Laravel 13: PHP 8.3–8.5
- Laravel 11, 12 또는 13
- `spatie/laravel-sitemap` ^7.0 또는 ^8.0 — 선택 사항이며 사이트맵을 생성할 때만 필요합니다.

## 패키지 설치 {#install-the-package}

```bash
composer require rankbeam/laravel-seo
php artisan vendor:publish --tag=seo-config
php artisan migrate
```

이것으로 설치가 끝납니다. 서비스 프로바이더와 `SEO` 파사드는 자동으로 검색됩니다. 두 마이그레이션은 패키지가 사용하는 다음 두 테이블만 생성합니다.

| 테이블 | 용도 |
|---|---|
| `seo_meta` | 모델별 명시적 값(morph + 로캘) |
| `seo_defaults` | 전역, 모델 유형, 라우트 기본값 |

## 선택 사항: 사이트맵 {#optional-sitemaps}

사이트맵 생성 기능은 [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap)을 사용합니다.

```bash
composer require spatie/laravel-sitemap
```

소스 등록과 생성 방법은 [사이트맵 레지스트리 가이드](/ko/guide/sitemaps)를 참고하세요.

## v1에서 업그레이드하나요? {#upgrading-from-v1}

앱에서 `fibonoir/laravel-seo` v1을 사용했다면 먼저 [v1에서 업그레이드](/ko/guide/upgrade-from-v1)를 읽어보세요. 벤더, 네임스페이스, 패키지 인터페이스가 모두 변경되었으며, v1에서 게시한 파일이 v2 설정과 충돌할 수 있습니다.

## 함께 사용하는 패키지 {#companion-packages}

| 패키지 | 추가 기능 | 라이선스 |
|---|---|---|
| [`rankbeam/laravel-seo-filament`](https://github.com/rankbeam/laravel-seo-filament) | Filament 4/5 리소스 폼에 SEO 섹션 추가 | MIT |
| [`rankbeam/laravel-seo-pro`](/ko/pro/installation) | 모든 Laravel 앱에서 큐 기반 사이트 스캔, 리디렉션 관리, 404 모니터링 제공. Filament 대시보드는 선택 사항 | 상용 |

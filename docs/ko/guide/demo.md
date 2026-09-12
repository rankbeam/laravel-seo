---
description: "한 명령으로 샘플 데이터가 포함된 Rankbeam 데모 앱을 실행하세요. 경로 저장소 없이 릴리스된 패키지를 사용해 실제 페이지의 메타데이터, JSON-LD 스키마 그래프, 사이트맵을 확인할 수 있습니다."
---

# 데모 실행 {#run-the-demo}

자신의 앱에 먼저 연결하지 않고 실제 페이지에서 Rankbeam의 동작을 확인하는 가장 빠른 방법은 실행 가능한 데모입니다. 샘플 데이터가 포함된 Laravel 앱으로, **릴리스된** 패키지를 설치하며 경로 저장소나 인접 체크아웃은 사용하지 않습니다. 몇 개의 페이지에서 전체 SEO 메타데이터, JSON-LD 스키마 그래프, 사이트맵을 렌더링합니다. 라이선스를 추가하면 Pro [기술 SEO 감사](/ko/pro/scan-issues)도 실행할 수 있습니다.

## 한 명령으로 실행(무료 코어) {#one-command-free-core}

데모는 [`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples) 저장소에서 Docker 이미지로 제공됩니다.

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

`http://localhost:8080`를 여세요. 페이지 소스를 보면 결정된 `<head>`를 확인할 수 있으며, `/sitemap.xml`에서는 생성된 사이트맵을 볼 수 있습니다. 여기서 사용하는 모든 기능은 Packagist에서 설치한 무료 MIT 코어에 포함됩니다.

## Pro 사용(감사) {#with-pro-the-audit}

Pro는 프로젝트별로 라이선스가 부여되며 비공개 Composer 저장소에서 설치합니다. `COMPOSER_AUTH`를 통해 라이선스를 전달하고 Pro 플래그로 빌드하세요. 라이선스는 빌드 시크릿으로 처리되며 이미지 레이어에 기록되지 않습니다.

```bash
export COMPOSER_AUTH='{"http-basic":{"blog.rankbeam.dev":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

데모가 시작되면 [`seo:doctor`](/ko/pro/headless#setup-health-check)와 샘플 페이지에 대한 첫 `seo-pro:scan` 명령을 실행합니다. 상태 보고서, 스캔 요약, [0–100점 점수](/ko/pro/scoring)는 compose 로그에 출력됩니다.

## Pro 작업 흐름 살펴보기 {#see-the-pro-workflow}

[스캔 → 수정 → 보고서 실습](/ko/pro/walkthrough)은 실행 중인 Merchant 데모를 보여줍니다. 실제 스캔, 문제 상세 정보, Filament에서 설명 저장, 재스캔, 다운로드 가능한 PDF까지 확인할 수 있습니다. 콘텐츠는 샘플 데이터로 표시되며, 변경 전후 결과는 새로 수행한 두 번의 스캔에서 가져옵니다.

현재 공개된 대화형 호스팅 데모는 없습니다. Docker로 엔진을 로컬에서 실행하세요. [데모 README](https://github.com/rankbeam/rankbeam-examples/tree/main/demo)에 설정 방법과 릴리스 패키지 및 로컬 패키지 간 전환 방법이 설명되어 있습니다.

::: tip 이미 앱이 있나요?
데모를 건너뛰고 [빠른 시작](/ko/guide/quickstart)으로 바로 진행하세요. 설치부터 완전한 `<head>` 렌더링까지 5분이면 됩니다.
:::

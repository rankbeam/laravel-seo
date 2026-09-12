---
description: "운영 사이트에서 Yoast나 Rank Math를 교체하는 단계별 저위험 절차입니다. 기본적으로 빈 필드만 채우며 모의 실행은 기록하지 않고 WordPress 원본은 변경하지 않습니다."
---

# WordPress → Rankbeam 마이그레이션 실행 절차 {#wordpress-→-rankbeam-migration-runbook}

WordPress SEO 스택(Yoast 또는 Rank Math)을 Rankbeam으로 교체하는 단계별 절차입니다. 가져오기 도구는 기본적으로 대상의 빈 필드를 채우며, `--overwrite`를 지정해야 교체를 명시적으로 허용합니다. 모의 실행은 아무것도 기록하지 않으며 원본 WordPress 데이터베이스는 변경하지 않습니다. 가져오기 전에 원본과 대상 모두 백업하세요.

필드 매핑, 템플릿 토큰 처리, 소스 키를 자세히 설명하는 [WordPress에서 마이그레이션](/ko/guide/migrate-from-wordpress)의 운영용 안내입니다. 그 문서는 *무엇을* 이전하는지, 이 문서는 *어떤 순서로 어떻게* 실행하는지 설명합니다.

::: tip 필요한 것
- 메타데이터 가져오기와 `seo:audit`를 위한 **코어**(`rankbeam/laravel-seo`).
- **리디렉션**도 이전할 경우에만 **Pro**(`rankbeam/laravel-seo-pro`). `seo_redirects` 테이블은 Pro 기능입니다.
- [`HasSEO`](/ko/guide/quickstart) 트레이트를 사용하며 Laravel에서 이미 모델링된 콘텐츠(예: `App\Models\Post`)와, WordPress 슬러그를 모델에 대응시킬 방법. 모델 라우트 키 또는 `--match-by`로 지정한 열을 사용합니다.
:::

## 마이그레이션 구조 {#the-shape-of-the-migration}

WordPress 행의 키는 **URL / 글**이고, Rankbeam `seo_meta` 행은 Eloquent 모델에 연결되는 **다형성** 구조입니다. 가져오기는 각 WordPress 행을 앱 모델 하나와 연결합니다. 세 결과가 가능하며 모든 실행에서 각 수를 보고합니다.

| 결과 | 의미 | 조치 |
|---|---|---|
| **matched** | 행을 모델에 연결하고 `seo_meta`에 기록함 | 없음 |
| **url-only** | 일치하는 모델이 없거나 `--model` 옵션을 지정하지 않음 | 해당 페이지에 모델 또는 리디렉션이 필요한지 결정 |
| **unmapped** | Core 3에 저장할 곳이 없는 데이터가 있음(특히 **작성자**) | 다른 위치로 이전(예: `getSEOAuthor()` 훅) |

---

## 단계 0 — 함께 실행 (아직 전환하지 않음) {#step-0-—-coexist-no-cutover-yet}

실행 중인 사이트 **옆에** Rankbeam을 구성하세요. 모델에 `HasSEO` 트레이트를 추가하고 파사드/지시문으로 태그를 렌더링하되, WordPress 설치나 SEO 플러그인은 아직 제거하지 **마세요**. 이 시점에는 가져온 것도 파괴적인 작업도 없습니다. 새 스택이 부팅되는지만 확인합니다.

전환 중 새 Laravel 앱과 기존 WordPress 사이트를 같은 호스트에서 제공한다면 단계 5까지 경로를 분리하세요.

## 단계 1 — 메타데이터 가져오기 (먼저 모의 실행) {#step-1-—-import-the-metadata-dry-run-first}

항상 `--dry-run` 옵션으로 시작하세요. **아무것도 기록하지 않고** 실제 실행 시 *일어날* 일을 전체 검증 보고서로 출력합니다.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

유용한 옵션입니다. 전체 목록은 `php artisan seo:import-from --help`를 실행해 확인하세요.

| 옵션 | 목적 |
|---|---|
| `--model=` | 대상 모델 FQCN(반복 지정 가능하지만 WordPress 가져오기는 실행당 모델 **하나**를 연결하므로 콘텐츠 유형별로 실행) |
| `--match-by=` | 슬러그와 일치시킬 모델 열(기본값: 라우트 키) |
| `--post-type=` | DB 읽기를 해당 글 유형으로 제한(기본값: `post` + `page`) |
| `--connection=` | WordPress 테이블이 있는 데이터베이스 연결 |
| `--table=` | WordPress 테이블 **접두사**(기본값: `wp_`) |
| `--locale=` | `seo_meta` 행을 기록할 로케일 |
| `--redirects-csv=` | 단계 3의 리디렉션 후보도 이 파일로 출력 |
| `--site-url=` | 절대 URL에서 경로를 도출할 이전 사이트 URL |
| `--overwrite` | 비어 있지 않은 기존 `seo_meta` 교체(기본값: **빈 값만 채움**) |
| `--limit=` | 소스 행 수 제한(첫 실행에 유용) |
| `--json` | 기계 판독용 보고서 |

모의 실행 결과가 올바르면 `--dry-run` 옵션을 제거해 적용하세요.

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

가져오기는 기본적으로 **멱등적**이며 **빈 필드만 채웁니다**. 따라서 다시 실행해도 안전하고 Rankbeam에서 이미 편집한 메타데이터를 덮어쓰지 않습니다.

## 단계 2 — 검증 보고서 읽기 및 보관 {#step-2-—-read-and-archive-the-verification-report}

모든 실행은 **Verification report**를 출력합니다. 무엇이든 제거하기 전에 승인할 수치입니다. 계속 보관할 산출물로 저장하세요.

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

확인할 항목:

- **matched**는 SEO 메타데이터를 가져야 하는 예상 페이지 수와 같아야 합니다.
- **url-only**는 모델과 일치하지 않은 페이지의 작업 목록입니다. 각각 모델, 리디렉션(단계 3), 또는 아무 조치도 필요하지 않은지 결정하세요.
- **truncated**는 `seo_meta` 열에 맞추기 위해 줄인 필드입니다. 해당 제목/설명을 검토하세요.
- **unmapped**는 Core 3 열이 없는 소스 데이터를 나열하며, **서로 다른 모든 `author` 값**을 명시합니다. 작성자는 열에 저장하지 않고 `getSEOAuthor()`에서 다룹니다. 몇 달 뒤 손실을 발견하는 대신 의도적으로 다른 위치에 보관하도록 보고서를 제공합니다.

## 단계 3 — Pro에 리디렉션 가져오기 {#step-3-—-import-the-redirects-into-pro}

코어 가져오기 도구는 Pro 테이블인 **`seo_redirects`에 기록하지 않습니다**. 대신 고정되고 버전이 지정된 형식의 CSV를 제공합니다(**리디렉션 CSV 형식 v1**: `source_path,target_url,status_code,note`). 먼저 모의 실행으로 Pro에 가져오세요.

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

모든 행을 Filament 리디렉션 폼과 정확히 같은 방식으로 검증합니다. 잘못된 행, 유효하지 않은 상태 코드, **안전하지 않은 외부 대상**, **중복 출발 경로**, **리디렉션 루프**를 만드는 규칙은 이유와 함께 건너뛰며 몰래 기록하지 않습니다. 모의 실행은 루프와 중복을 포함한 전체 파일을 검증하고 아무것도 기록하지 않습니다. 기존 규칙의 대상을 교체하려면 `--overwrite`를 전달하세요.

## 단계 4 — `seo:audit --strict`로 검증 {#step-4-—-verify-with-seo-audit-strict}

무료 프로세스 내 감사를 마이그레이션 통과 조건으로 삼으세요. `--strict`는 **어느** 페이지에든 문제가 있으면 0이 아닌 코드로 종료하므로 CI/전환 검사로 사용할 수 있습니다.

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

감사는 모델과 리졸버 검사(제목/설명 존재 및 길이, OG 이미지, robots 충돌, 표준 URL 형식)를 다룹니다. 렌더링된 HTML 및 실제 표준 URL 검사와 0–100 점수는 [Pro 스캔](/ko/pro/scan-issues)의 일부입니다. Pro가 있다면 함께 실행하세요. [무료 SEO 감사](/ko/guide/audit)를 참고하세요.

그런 다음 브라우저에서 실제 페이지 몇 개를 점검하세요. 소스를 열어 `<title>`, `<meta name="description">`, 표준 URL, robots, OpenGraph 태그가 가져온 값을 렌더링하는지 확인하세요.

## 단계 5 — 기존 패키지/테이블을 제거하기 전에 검증 {#step-5-—-verify-before-removing-the-legacy-package-table}

다음을 **모두** 충족하기 전에는 WordPress 데이터베이스, SEO 플러그인, 기존 패키지를 제거하지 **마세요**.

- [ ] **모든** 콘텐츠 유형의 가져오기를 실행했습니다(실행당 `--model` 하나).
- [ ] 보관한 검증 보고서의 **matched** 수가 예상과 같고 예상 밖의 **url-only** 행이 없습니다.
- [ ] 필요한 모든 **매핑되지 않은 작성자** 값을 다른 위치에 보관했습니다.
- [ ] Pro에 리디렉션을 가져왔고(`seo-pro:redirects-import`), 이전 URL 몇 개가 실제로 새 URL로 301 응답합니다.
- [ ] `php artisan seo:audit --strict`가 `0`으로 종료합니다.
- [ ] (Pro) `php artisan seo:doctor`가 남은 기존 `seo` 테이블이나 `config/seo.php` 충돌이 없다고 보고합니다.
- [ ] 브라우저에서 렌더링된 페이지를 표본 점검했습니다.

가져오기 도구는 빈 필드만 채우며 멱등적이므로 이 통과 조건을 충족하기 전에는 언제든 단계 1을 해치지 않고 다시 실행할 수 있습니다. 원본 데이터는 여전히 WordPress에 있습니다.

## 단계 6 — 기존 시스템 종료 {#step-6-—-decommission}

단계 5 체크리스트를 통과한 뒤에만 WordPress 사이트를 오프라인으로 전환하고 데이터베이스/테이블과 기존 SEO 패키지를 제거하세요. 새 스택이 운영 환경에서 올바르게 서비스한다고 확신할 때까지 데이터베이스 백업을 유지하세요.

::: tip 롤백
단계 1–4에는 파괴적인 작업이 없습니다. `seo_meta`는 추가 방식이며, 리디렉션은 검증되고 규칙 삭제로 되돌릴 수 있고, WordPress 데이터는 변경하지 않습니다. 단계 6 이전의 롤백은 *“WordPress를 계속 제공하기”*, 이후의 롤백은 *“WordPress 백업 복원하기”*입니다.
:::

---

**Laravel** SEO 패키지(ralphjsmit, artesaos, Spatie)에서 이전하나요? [다른 Laravel 패키지에서 마이그레이션](/ko/guide/migrate-from-other-packages)을 참고하세요.

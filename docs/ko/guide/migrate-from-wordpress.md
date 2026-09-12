---
description: "Yoast나 Rank Math에서 직접 작성한 SEO 제목, 설명, 표준 URL, robots, 포커스 키워드를 Laravel 모델로 옮기세요. 가져오기 도구의 필드 매핑 참조입니다."
---

# WordPress에서 마이그레이션 {#migrating-from-wordpress}

WordPress에서 콘텐츠 사이트를 옮기나요? Rankbeam은 팀이 Yoast나 Rank Math에서 직접 작성한 제목, 설명, 표준 URL, robots 지시문, 포커스 키워드, 소셜 덮어쓰기 값 등 SEO 메타데이터를 Laravel 모델로 가져올 수 있습니다. 전환 과정에서 오랫동안 쌓은 최적화 작업을 잃지 않도록 합니다.

::: tip 실제 서비스 전환을 진행한다면
이 페이지는 필드 매핑, 토큰, 소스 키를 설명하는 가져오기 도구의 *참조 문서*입니다. 함께 실행, 가져오기, 검증, 기존 시스템 종료로 이어지는 단계별 저위험 **절차**는 [WordPress 마이그레이션 실행 절차](/ko/guide/wordpress-migration-runbook)를 따르세요.
:::

두 경로 모두 같은 `seo:import-from` 명령을 사용합니다.

| 경로 | 소스 | 적합한 용도 |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | WordPress에서 내보낸 스프레드시트 | 대부분의 에이전시 이전; 정확한 URL을 직접 제어 |
| [**데이터베이스**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | 실제 WordPress 데이터베이스 | OpenGraph/Twitter 덮어쓰기 값과 Rank Math 리디렉션까지 온전히 이전 |

둘 다 **멱등적**이며 다시 실행해도 같은 행을 갱신하고 중복을 만들지 않습니다. **`--dry-run`** 옵션을 지원하고 기본적으로 빈 필드만 *채웁니다*. Rankbeam에서 이미 설정한 SEO 데이터를 덮어쓰지 않습니다. 가져온 값으로 기존 값을 교체하려면 **`--overwrite`**를 전달하세요.

## WordPress 행을 `seo_meta` 행으로 연결하는 방식 {#how-wordpress-rows-become-seo-meta-rows}

WordPress 데이터는 Laravel의 다형성 데이터가 아닙니다. WordPress 행은 **URL** 또는 **글 ID**를 키로 사용하지만, Rankbeam의 `seo_meta`는 다형성 구조이며 모든 행이 실제 Eloquent 모델에 연결됩니다. 따라서 가져오기 도구는 각 WordPress 행을 모델과 대응시키고 연결된 행과 URL만 있는 행을 보고서에서 명확히 구분합니다.

- **모델 연결.** `--model="App\Models\Post"`로 대상 모델을 지정합니다. 각 행의 **슬러그**(URL의 마지막 경로 부분 또는 WordPress `post_name`)를 모델과 비교합니다. 기본값은 라우트 키이며 `--match-by=`로 다른 열을 선택할 수 있습니다. 일치하는 행은 `seo_meta`에 기록합니다.
- **URL만 있음.** 모델과 일치하지 않는 행 또는 `--model` 옵션이 없는 실행은 연결할 모델이 없어 `seo_meta` 행이 될 수 없습니다. `url-only` 이유로 건너뛰었다고 보고합니다. 해당 표준 URL은 여전히 [리디렉션 후보](#redirects)가 될 수 있습니다.

WordPress 글과 페이지는 보통 *서로 다른* Laravel 모델에 대응하므로 콘텐츠 유형별로 행 범위를 지정해 한 번씩 실행하세요.

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning 사용자 지정 글 유형은 기본적으로 탐색하지 않습니다
데이터베이스 읽기 도구는 **`post`**와 **`page`** 글 유형만 순회합니다. 테마의 `product`, `event`, `pathology` 등 사용자 지정 글 유형으로 만든 사이트는 `--post-type=` 옵션을 반복해 각각 명시해야 합니다.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. CSV 가져오기 {#_1-csv-import}

CSV 경로는 대부분의 에이전시 이전에 적합합니다. 아래 헤더로 URL당 한 행을 내보내세요. 열 순서는 자유이며 알 수 없는 열은 무시하고 보고합니다.

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

실행하세요.

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| 열 | `seo_meta` 매핑 | 참고 |
|---|---|---|
| `url` | *(일치 키)* | 마지막 경로 부분인 슬러그를 모델과 비교합니다. 필수입니다. |
| `title` | `title` | 70자로 줄이며 초과 값은 보고합니다. |
| `description` | `description` | 160자로 줄입니다. |
| `canonical` | `canonical` | [리디렉션 후보](#redirects)에도 사용합니다. |
| `robots` | `robots` | 그대로 저장하며(예: `noindex, nofollow`), 50자로 줄입니다. |
| `focus_keyword` | `focus_keywords` | 쉼표로 구분하며 첫 키워드가 기본입니다. |

`url` 값이 없거나 열 수가 헤더와 다른 잘못된 행은 건너뛰고 집계합니다.

---

## 2. 데이터베이스 가져오기 (Yoast / Rank Math) {#_2-database-import-yoast-rank-math}

WordPress 데이터베이스가 남아 있다면 SEO 메타데이터를 직접 읽을 수 있습니다. CSV 내보내기에서 보통 빠지는 OpenGraph/Twitter 덮어쓰기 값과 Rank Math 리디렉션도 포함됩니다.

### WordPress 연결 지정 {#point-a-connection-at-wordpress}

`config/database.php`에 WordPress 데이터베이스 연결을 추가하세요.

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

그런 다음 가져오세요. 테이블 접두사의 기본값은 `wp_`이며 `--table=`로 바꿀 수 있습니다.

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

읽기 도구는 게시된 글/페이지인 `{prefix}posts`를 순회하고 `{prefix}postmeta`에서 각 글의 플러그인 메타데이터를 가져옵니다. 글의 `post_name` 슬러그를 모델과 대응시킵니다.

::: tip 기본값이 아닌 테이블 접두사
관리형 WordPress 호스트는 `wp_` 대신 `wppg_`처럼 무작위 접두사를 자주 사용합니다. 덤프의 `CREATE TABLE` 이름을 확인하고 실제 접두사 `--table=wppg_`를 전달해 `{prefix}posts`와 `{prefix}postmeta`를 찾을 수 있게 하세요.
:::

::: tip MySQL 8에 복원한 덤프 읽기
로컬에서 읽기 위해 WordPress 덤프를 MySQL 8+에 넣었다면 `.sql` 파일을 실행하기 전에 엄격한 SQL 모드를 완화하세요. MySQL 8의 기본 `STRICT`/`NO_ZERO_DATE` 모드는 WordPress의 `'0000-00-00'` 날짜·시간 기본값을 거부합니다. 따라서 SEO 가져오기가 실행되기 전에 덤프 가져오기 자체가 `Invalid default value for 'post_date'` 오류로 실패합니다.

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### 필드 매핑 {#field-mapping}

두 가져오기 도구는 필드를 **명시적으로** 매핑합니다. Core 3 열이 없는 키는 새 열을 만들지 않고 *매핑되지 않음*으로 보고합니다.

| Yoast 메타 키 | Rank Math 메타 키 | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** WordPress 기본값과 다른 부분만 저장하므로 일반적인 색인 가능 페이지는 `robots`를 null로 두고 사이트 기본값을 상속합니다. Yoast의 분리된 `noindex` / `nofollow` / 고급 플래그(`noarchive`, `nosnippet`, `noimageindex`)를 문자열 하나로 합칩니다. Rank Math의 직렬화된 `robots` 배열도 같은 방식으로 읽고 `index` / `follow` 기본값을 제거합니다.

**매핑되지 않은 키**는 복사하지 않고 보고합니다. 첨부 이미지 ID(`*-image-id`), 키워드/SEO 점수(`linkdex`, `content_score`, `rank_math_seo_score`), 기본 카테고리 선택, Rank Math의 리치 스니펫 스키마 표시가 해당합니다. [스키마 그래프](/ko/guide/schema)가 더 풍부하고 유형이 지정된 대안입니다.

::: warning 표준 URL은 그대로 가져옵니다
명시적 표준 URL(`rank_math_canonical_url` / `_yoast_wpseo_canonical`)은 **저장된 그대로** 복사합니다. 관리형/스테이징 호스트에서 흔한 `https://oldsite-staging.example.com/page/`처럼 *이전* 도메인의 절대 URL로 고정했다면 가져온 뒤에도 그곳을 가리킵니다. 가져오기 도구는 호스트를 바꾸지 않습니다. `--site-url` 옵션은 [리디렉션 후보](#redirects)와 CSV 행 연결에 필요한 요청 *경로*를 절대 URL에서 도출하지만 저장된 표준 URL 값은 바꾸지 **않습니다**. 도메인을 옮긴 뒤 가져온 표준 URL을 검토해 호스트를 갱신하거나, 값을 비워 리졸버의 자기 참조 표준 URL을 사용하세요. 대부분의 페이지에는 명시적 표준 URL이 없어 영향받지 않습니다. Yoast와 Rank Math는 렌더링 시 자동으로 표준 URL을 만듭니다.
:::

### 템플릿 토큰 {#template-tokens}

Yoast와 Rank Math는 제목과 설명을 토큰이 있는 **템플릿**으로 저장합니다. Yoast는 `%%title%%`, Rank Math는 `%title%`를 사용합니다. 가져오기 도구는 **도출할 수 있는 토큰은 값으로 바꾸고 나머지는 제거**하므로 원시 `%%token%%` 문자열이 저장되지 않습니다.

| 토큰 | 변환 결과 |
|---|---|
| `%%title%%` / `%title%` | WordPress 글 제목 |
| `%%sitename%%` / `%sitename%` | `wp_options`의 블로그 이름(데이터베이스 가져오기) |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%` 등 | *제거*(비우고 주변 구분자 정리) |

토큰을 변환한 실행은 보고서에 이를 표시합니다. **가져온 제목을 검토**해 원하는 문장인지 확인하고, 도출할 수 없는 토큰에 의존했던 일부 제목을 조정하세요.

---

## 리디렉션 {#redirects}

`seo_redirects`는 [Rankbeam **Pro**](/ko/pro/installation) 기능이므로 코어 가져오기 도구는 그 테이블에 직접 기록하지 않습니다. 대신 `--redirects-csv=`를 전달하면 Pro 리디렉션 테이블과 같은 열 `source_path,target_url,status_code,note` 형식을 가진 **CSV를 출력**하므로 Pro에 가져올 수 있습니다.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

리디렉션 후보의 출처:

- **CSV 가져오기** — `canonical` 값이 자신의 `url` 값과 **다른 경로**를 가리키면 이전 경로에서 표준 URL로 가는 `301` 리디렉션이 됩니다. 같은 경로인 자기 참조 표준 URL은 루프가 되므로 출력하지 *않습니다*.
- **Rank Math 데이터베이스** — `{prefix}rank_math_redirections` 테이블의 활성 규칙입니다. **정확히 일치하는** 규칙만 출력합니다. 정규식/포함/시작/끝 규칙은 단일 경로에 대응하지 않으므로 건너뜀으로 보고합니다.
- **Yoast 무료 버전**에는 리디렉션 테이블이 없습니다. Yoast Premium에만 있으며 스키마는 무료 패키지에 포함되지 않습니다. Yoast 리디렉션에는 CSV 경로를 사용하세요.

후보는 **권고 사항**입니다. CSV를 검토한 뒤 [`seo-pro:redirects-import`](/ko/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro)로 Pro에 가져오세요. 이 명령은 모든 행을 검증하고 루프, 안전하지 않은 대상, 중복을 거부합니다. CSV 형태는 안정적인 계약인 **리디렉션 CSV 형식 v1**입니다: `source_path,target_url,status_code,note`.

---

## 보고서 내용 {#what-the-report-tells-you}

`--json` 옵션을 사용하지 않는 실행은 결과 표(생성 / 갱신 / 변경 없음 / 건너뜀 / 탐색), **Verification report**, 검토 섹션을 출력합니다.

- **Verification report** — 승인 전에 한눈에 확인할 구분입니다. **matched**(모델에 연결된 행), **url-only**(일치하는 모델 없음), 잘림/매핑되지 않음 집계입니다.
- **Truncated** — `seo_meta` 열에 맞추기 위해 줄인 값.
- **Not imported** — 데이터가 있지만 Core 3에 저장할 곳이 없는 소스 키이며, **서로 다른 모든 `author` 값도 포함**합니다. 작성자는 저장 열이 아니라 [`getSEOAuthor()`](/ko/concepts/resolver-precedence)에서 다룹니다. 모르게 사라지도록 두지 않고 다른 위치로 옮길 데이터를 보고합니다.
- **Redirect candidates** — 기록한 개수와 파일.
- **Skipped rows by reason** — URL만 있는 행, SEO 메타데이터가 없는 글, 정확 일치가 아닌 리디렉션 규칙.
- **Warnings** — 예를 들어 템플릿 토큰을 변환했다는 안내.

`--json` 옵션을 추가하면 위 내용을 기계 판독용으로 제공합니다. `verification` 블록에는 matched/url-only 수와 모든 작성자 값이 포함됩니다.

### 검증 {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict`는 어느 페이지에든 문제가 있으면 0이 아닌 코드로 종료합니다. [무료 SEO 감사](/ko/guide/audit)를 참고하세요. 함께 실행 → 가져오기 → 검증 → 기존 시스템 종료의 전체 순서는 [WordPress 마이그레이션 실행 절차](/ko/guide/wordpress-migration-runbook)를 따르세요.

---

**Laravel** SEO 패키지(ralphjsmit, artesaos, Spatie)에서 이전하나요? [다른 Laravel 패키지에서 마이그레이션](/ko/guide/migrate-from-other-packages)을 참고하세요.

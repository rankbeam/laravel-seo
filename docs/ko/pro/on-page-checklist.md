---
description: "포커스 키워드를 반영하는 통과/경고/실패 온페이지 체크리스트입니다. 제목, URL, 도입 문단, 메타데이터, 길이, 이미지, 가독성을 신호등 방식으로 확인하세요."
---

# 온페이지 체크리스트 — 키워드 기반 통과/경고/실패 {#the-on-page-checklist-—-keyword-aware-pass-warn-fail}

온페이지 체크리스트는 RankMath나 Yoast 사용자가 기대하는 실시간 편집 흐름입니다. 포커스 키워드를 선택하면 “이 페이지가 해당 키워드에 최적화되었는가?”를 신호등 목록으로 확인합니다. 제목, URL, 도입 문단, 메타 설명의 키워드와 길이, 이미지, 내부 링크, **가독성**을 검사합니다.

모델, [리졸버](/ko/concepts/resolver-precedence), 페이지 자체 문구를 사용해 큐나 네트워크 없이 **요청 내에서** 실행하며 의도적으로 **수치 점수를 만들지 않습니다**.

::: tip 체크리스트 ≠ 점수
체크리스트는 **통과 / 경고 / 실패만** 표시하며 [Pro SEO 점수](/ko/pro/scoring)와 완전히 분리됩니다. 점수 평가 기준과 코드를 공유하지 않고 점수를 바꿀 수도 없습니다. 편집 안내는 수치 평가와 별개입니다. 특히 키워드 밀도와 가독성은 아래 설명처럼 **권고 사항**입니다.
:::

## 검사 항목 {#what-it-checks}

| 검사 | 그룹 | 확인 내용 |
|---|---|---|
| `keyword_in_title` | keyword | SEO 제목에 포커스 키워드가 있음. |
| `keyword_in_description` | keyword | 메타 설명에 포커스 키워드가 있음. |
| `keyword_in_url` | keyword | URL 슬러그에 포커스 키워드가 있음. |
| `keyword_in_first_paragraph` | keyword | 도입 문단에 포커스 키워드가 있음. |
| `keyword_density` | keyword | **권고.** 반복 밀도가 자연스러운지 검토(목표 비율 없음, 아래 참고). |
| `title_length` | meta | 제목이 편집기 및 스캔과 같은 범위에 있음. 코어 [길이 정책](/ko/guide/multilingual#title-and-description-budgets-per-script)에 따라 라틴 30–60, CJK 약 15–30(Pro 2.33). |
| `description_length` | meta | 설명이 같은 범위에 있음. 라틴 70–160, CJK 약 35–80. |
| `content_length` | content | 본문 분량이 충분함(설정 기반 단어 수 구간). |
| `readability` | content | **권고.** 선택한 공식(10개 언어), LIX 대체 방식, 또는 일본어·중국어·한국어의 점수 없는 명시적 휴리스틱으로 추정한 가독성 수준. |
| `has_image` | media | 콘텐츠에 이미지가 하나 이상 있음. |
| `internal_links` | links | 관련 내부 페이지로 연결함. |

포커스 키워드가 없으면 키워드 검사는 통과나 실패가 아닌 **건너뜀**이 되며 추가 안내를 표시합니다. [포커스 키워드 필드](/ko/guide/filament) 또는 `saveSEO(['focus_keywords' => …])`로 추가하세요.

### 키워드 일치 {#keyword-matching}

키워드와 본문은 **대소문자 접기와 어간 추출** 후 비교합니다. 따라서 “espresso grinder”는 “espresso grinders”와 일치합니다. 분석 로케일을 사용하면 터키어 “İstanbul”과 “istanbul”, 그리스어 “ΟΔΟΣ”와 “οδος”, 독일어 “Straße”와 “STRASSE”도 일치합니다(코어 `CaseFolder`). 분석할 로케일을 `SeoPro::checklistFor($post, 'it')` 또는 `--locale=it`로 전달하세요.

Pro 2.36.1부터 키워드, 동의어, 필드 텍스트는 어간 추출 전에 같은 토크나이저를 사용합니다. 연속된 **완전한 토큰**이 일치해야 하므로 `cat` 토큰은 `education` 토큰과 일치하지 않습니다. 일본어 구문도 본문과 같은 ICU 단어 경계를 사용합니다. 아포스트로피와 하이픈은 토큰을 나누므로 `meta-tag`는 `meta tag`와 일치하고 직선/곡선 아포스트로피는 동일하게 동작합니다. 결합 문자는 해당 글자에 붙어 있습니다. 대소문자 접기는 악센트를 유지하며 특정 언어의 어간 추출기가 추가 축약을 할 수 있습니다.

출현 수는 위치마다 일치하는 가장 긴 키워드/동의어를 선택해 해당 구간을 한 번 셉니다. 중복 동의어나 겹치는 짧은 대안으로 밀도가 부풀지 않습니다. 예를 들어 키워드 `seo`와 동의어 `seo tools`는 `seo tools seo`에서 두 번 나타납니다. 띄어쓰기가 없는 문자 체계의 사전 기반 단어 경계에는 여전히 ICU가 필요합니다. 정규식 대체 방식은 그 경계를 제공할 수 없습니다.

Pro 2.37부터 어간 추출은 **내장 Snowball 3.1.1 부분집합**을 사용합니다. 추가 Composer 패키지가 필요하지 않고 런타임에 다운로드하지 않습니다. PHP 8.2도 계속 지원합니다.

| 엔진 | 사용 조건 | 언어 |
| --- | --- | --- |
| `snowball` | 기본값; 기존 `auto` 설정도 같은 내장 엔진 선택 | en, it, de, fr, es, pt, nl, ru, tr, el, pl, cs |
| `builtin` | 명시적 `seo-pro.checklist.analysis.stemmer = builtin` | 영어에만 이전의 가벼운 굴절 어간 추출기 사용; 다른 언어는 변형 없이 일치 |
| `identity` | 지원하지 않는 언어 또는 명시적 `none` 모드 | 우크라이나어, 일본어, 중국어, 한국어, 태국어 및 내장 부분집합 밖의 언어 |

비교 양쪽에 같은 엔진을 적용합니다. 어간 추출은 접미사 축약 알고리즘이며 동의어 사전이나 언어적 동등성 보장이 아닙니다. 예를 들어 그리스어 알고리즘은 변형 없는 일치에서는 다른 것으로 다루는 악센트 유무 형태를 일치시킬 수 있습니다. 전체 토큰 경계는 여전히 `cat` 토큰이 `education` 토큰과 일치하지 않게 합니다.

#### Pro 2.36에서 업그레이드 {#upgrading-from-pro-2-36}

기존 `auto` 설정은 `wamania/php-stemmer` 설치 여부와 관계없이 이제 일관되게 내장 알고리즘을 사용합니다. 알고리즘 갱신으로 일치 결과가 바뀔 수 있고 터키어, 그리스어, 폴란드어, 체코어에 어간 추출이 추가되었으므로 편집 제안을 다시 확인하세요. 선택적 래퍼의 추가 카탈루냐어, 덴마크어, 핀란드어, 노르웨이어, 루마니아어, 스웨덴어 알고리즘은 이 부분집합에 없으며 이제 변형 없이 일치시킵니다.

이전 영어 전용 대체 방식은 `SEO_PRO_CHECKLIST_STEMMER=builtin`, 모든 언어의 대소문자 접기 후 동일 형태 일치는 `none` 값으로 설정하세요. 변경 후 설정 캐시를 다시 만드세요. 이 옵션들은 기존 선택적 래퍼의 다국어 알고리즘을 재현하지 않습니다. 정확히 같은 결과를 유지하려면 이전 Pro 릴리스를 유지해야 합니다. 저장된 SEO 메타데이터는 재작성하지 않습니다.

내장 어댑터는 PHP 8.2, 8.3, 8.4에서 고정된 공식 어휘/출력 쌍 600,395개를 통과합니다. 이는 알고리즘 준수를 입증하며 원어민 편집 승인이 아닙니다. 소스 해시, 문법만 조정한 PHP 8.2 적용 사항, 업스트림 라이선스가 패키지에 포함됩니다. 소스 배포본의 `THIRD-PARTY-NOTICES.md`를 참고하세요.

### 단어 분할 {#word-segmentation}

단어 수, 키워드 밀도, 가독성 통계에는 단어가 필요합니다. 띄어쓰기 문자 체계에는 안정적인 문자/숫자 토큰 경계를 사용하는 정규식을 적용합니다. 중국어, 일본어, 태국어는 사전 기반 분할이 필요해 정규식이 문단을 한 “단어”로 볼 수 있습니다. **ext-intl**이 로드되면 해당 구간을 ICU 사전 기반 경계 반복자 `IntlBreakIterator::createWordInstance`에 넘겨 東京タワーは東京のランドマークです 같은 문장을 단어로 나눕니다. ICU가 없거나 꺼져 있거나 초기화할 수 없으면 관련 콘텐츠 길이, 가독성, 키워드 검사를 건너뛰고 설치/설정 메시지를 표시합니다. 신뢰할 수 없는 개수를 실패로 바꾸지 않습니다. 제목 길이와 띄어쓰기 문자 체계 일치 등 무관한 검사는 계속 실행합니다. `seo-pro.checklist.analysis.segmenter = regex`는 사전 분할이 필요한 텍스트를 같은 사용 불가 상태로 강제합니다.

`analysis` 블록에는 `word_count_status`(`available` 또는 `unavailable`)와 `segmentation_reason`(`null`, `missing_intl`, `disabled`, `initialization_failed`)가 있습니다. 하위 토크나이저는 호환성을 위해 대체 토큰을 유지하므로 이를 단어로 해석하기 전에 상태를 확인하세요.

렌더링된 페이지 스캔은 콘텐츠 부족 판정 대신 점수에 반영하지 않는 `word_segmentation_unavailable` 알림을 내보냅니다. 이전에 확정된 콘텐츠 부족 문제는 다시 검사할 수 있을 때까지 열려 있습니다. 이 불완전한 스캔은 페이지 점수를 갱신하지 않습니다. 기존 점수는 원래 `scored_at` 값을 유지하며 첫 스캔에는 분할이 작동할 때까지 점수가 없습니다. PHP `ext-intl` 확장을 설치하고 `auto` 분할기를 켠 뒤 재스캔해 검사를 재개하세요.

### 페이지를 분석한 엔진 {#which-engines-analysed-the-page}

모든 체크리스트에는 본문의 주된 문자 체계, 토크나이저(`intl` / `regex`), 어간 추출기(`snowball` / `builtin` / `identity`), 가독성 방식(`formula` / `heuristic` / `lix`)을 담은 `analysis` 블록이 있습니다. `toArray()` / `--json`, Filament 모달 하단, `seo-pro:checklist`의 마지막 줄에 표시합니다.

```
Analysis: locale ja · script cjk · tokenizer intl (ICU dictionary) · stemmer identity · readability heuristic
```

하단은 실제 사용한 엔진을 알려 줍니다. ext-intl 부재 시 정규식 분할, 어간 추출 비활성 시 변형 없는 일치도 포함됩니다.

### 키워드 밀도는 권고입니다 {#keyword-density-is-advisory}

체크리스트는 순위에 이상적인 키워드 밀도를 정의하지 않습니다. 이 검사는 인지를 돕는 **권고**이며 개수를 보여 줄 뿐 실패하지 않고 **전체 페이지 상태에 영향을 주지 않습니다**. 비율을 목표로 삼기보다 반복이 자연스러운지 검토하세요.

### 가독성은 권고입니다 {#readability-is-advisory}

체크리스트는 분석 로케일에 맞는 방식으로 가독성을 추정합니다. 현재 구현한 공식과 대체 방식은 다음과 같습니다.

| 로케일 | 공식 | 출처 |
| --- | --- | --- |
| 영어(`en`) | Flesch Reading Ease | Flesch 1948 |
| 이탈리아어(`it`) | Gulpease Index | Lucisano & Piemontese 1988 |
| 스페인어(`es`) | Fernández-Huerta | Fernández Huerta 1959 |
| 프랑스어(`fr`) | Kandel-Moles | Kandel & Moles 1958 |
| 독일어(`de`) | erste Wiener Sachtextformel | Bamberger & Vanecek 1984 |
| 포르투갈어(`pt`, `pt_BR`) | 브라질 포르투갈어용 Flesch 변형 | Martins et al. 1996 |
| 네덜란드어(`nl`) | Flesch-Douma | Douma 1960 |
| 러시아어(`ru`) | Oborneva의 Flesch 변형 | Оборнева 2006 |
| 터키어(`tr`) | Ateşman | Ateşman 1997 |
| 폴란드어(`pl`) | Pisarek(교육 연수 지수, 정규화) | Pisarek 1969 |
| 일본어, 중국어, 한국어(`ja`, `zh`, `ko`) | **휴리스틱, 점수 없음** — 아래 참고 | — |
| 그리스어, 우크라이나어, 체코어(`el`, `uk`, `cs`) | 전용 공식이 없어 LIX 대체 사용; 해당 언어에 보정되지 않음 | Björnsson 1968 |
| 그 밖의 언어 | LIX(Läsbarhetsindex) — 보정되지 않은 대체 방식 | Björnsson 1968 |

표시되는 **0–100 점수(높을수록 쉬움)**는 패키지의 관례입니다. Flesch 계열과 Gulpease 결과는 범위를 제한하고 Wiener 등급, Pisarek, LIX 지수는 이 척도에 매핑합니다. 언어가 다른데 점수가 같다고 읽기 난이도도 같다는 뜻은 **아닙니다**. 공식의 계수는 공개 연구에서 가져왔지만 Rankbeam의 토큰, 문장, 음절 추정 전체를 하나의 측정 도구로 검증하지는 않았습니다. 이해도나 검색 순위를 예측하지 않습니다.

Pro 2.37.1부터 터키어와 러시아어는 인접 모음을 별도 음절로 셉니다(`saat`: 2; `поэт`: 2). 다른 음절 추정기에는 여전히 한계가 있습니다. 모음 묶음이 일부 모음 연속과 묵음을 놓칩니다. 영어에는 작은 예외 맵이 있지만 발음 사전은 아닙니다. 예를 들어 스페인어 `país`와 프랑스어 `monde`의 수가 틀릴 수 있습니다. 익숙하지 않은 단어와 고유명사는 직접 검토하세요.

#### 텍스트 통계 및 API 한계 {#text-statistics-and-api-limits}

HTML 블록 태그와 `br` 요소는 텍스트를 나누며 인라인 강조는 단어에 붙습니다. 일반 HTML 소스의 줄바꿈은 공백으로 합쳐지고 일반 텍스트와 `pre`는 줄 경계를 유지합니다. script, style, noscript 내용은 제외합니다. CSS 가시성이나 렌더링된 페이지는 평가하지 않습니다. 엔터티는 한 번 디코딩합니다. 공식 통계에서는 문자/숫자 구간을 단어로 세고 문장부호만 있는 구간은 세지 않습니다. 하이픈과 아포스트로피는 단어를 나눕니다. 숫자는 토큰이지만 음절을 추정하지 않습니다. 글자 수는 원문에서 세며 어간 추출이나 독일어 `ß` → `ss` 대소문자 접기로 길이를 바꾸지 않습니다.

문장 추정은 종결 `. ! ? 。 ！ ？`와 블록/줄 경계에서 나누고, 종결 부호 없는 마지막 조각도 포함합니다. 소수와 `Dr.`, `Prof.`, `e.g.` 같은 소수의 일반 영어 약어는 보호합니다. 따라서 제목과 목록 항목도 문장으로 셀 수 있습니다. 다른 약어, 인용문, 숫자, 혼합 문자 체계, 문장부호가 적은 텍스트에는 특히 주의해야 합니다. 선택 로케일은 방식을 결정할 뿐 모든 문장이 해당 언어인지 탐지하지 않습니다.

직접 계산기의 `toArray()`는 `assessment` 블록을 추가합니다.

```json
{
  "status": "computed",
  "method": "formula",
  "formula": "flesch_reading_ease",
  "inputs_estimated": true,
  "score_scale": "normalized_0_100",
  "grade_level_estimated": true
}
```

`method`는 `formula`, `lix`, `heuristic`, `unavailable` 상태를 구별합니다. 공식 메타데이터 없이 수동으로 만든 결과는 `unspecified`입니다. 빈 입력/문장부호만 있는 입력은 `insufficient`이며 `isValid()`는 false입니다. 기존 `score: 0` 값은 난이도 점수가 아닌 사용 불가 표시값입니다. 기존 영어/이탈리아어 학년 표시는 근사치이며 다른 언어와 휴리스틱/LIX 결과에는 더 이상 그 학년 표시를 부여하지 않습니다. `calculateFleschKincaid()`는 호환성을 위해 공개 메서드 이름을 유지하지만 Flesch-Kincaid 학년이 아닌 **Flesch Reading Ease**를 계산합니다.

공식 테스트는 이름이 있는 10개 공식과 LIX 모두에 대해 독립적으로 센 입력과 예상 산술 결과를 고정합니다. 원어민 편집 품질이 아닌 계산 동작을 검증합니다. 가독성은 Pro SEO 점수와 분리됩니다.

::: warning 일본어, 중국어, 한국어: 표시된 휴리스틱이며 수치 점수는 아닙니다
Rankbeam은 이 언어들에 점수 없는 방식을 구현합니다. 계산기는 패키지 고유 경험칙으로 **수준**을 반환합니다. 문자 수 기준 평균 문장 길이(ja ≤ 40/60/80, zh ≤ 30/45/60) 또는 단어 수(ko ≤ 12/18/25)를 사용하고, 일본어는 한자 비율이 약 45%를 넘으면 패키지 난이도 구간을 한 단계 올립니다. `heuristic: true`로 표시하며 **점수는 null**입니다. 메시지에 “휴리스틱”이라고 명시하고 **`readability.advisory` 값과 관계없이 이 언어에서는 권고로 유지**합니다. 경험칙은 정보를 제공할 뿐 전체 체크리스트 상태를 제한하지 않습니다. `ja`/`zh`의 단어 수에는 작동하는 ICU 분할이 필요하며 사용할 수 없으면 관련 검사를 건너뜁니다.
:::

키워드 밀도처럼 가독성은 **기본적으로 권고**입니다. 작성자에게 알려 주지만 **전체 페이지 상태를 제한하지 않습니다**. Yoast도 가독성과 SEO 분석을 같은 방식으로 구분합니다. 최소 단어 수 미만이면 **건너뜁니다**. 짧은 페이지는 가독성이 아닌 `content_length` 검사의 역할입니다. 읽기 어려운 페이지를 실패 처리하려면 판정에 반영하도록 켜세요.

```php
// config/seo-pro.php → 'checklist'
'readability' => [
    'min_words' => 50,     // below this → skipped (too little copy to judge)
    'advisory'  => true,   // false → a 'difficult' page fails the checklist
],
```

## 체크리스트 읽기 {#reading-the-checklist}

### 헤드리스 {#headless}

Pro 2.36은 요청한 콘텐츠 로케일의 최종 메타데이터, `getContentForSEO()`, 포커스 키워드를 읽으며 체크리스트 표시는 운영자 언어를 유지합니다. 로케일이 명시되지 않으면 번역 모델의 `seoData()` 기본값을 따릅니다. Filament 동작은 필드 언어 탭 또는 페이지 로케일 전환기를 따릅니다.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$checklist = SeoPro::checklistFor($post);          // or ($post, 'it') for a locale

$checklist->status();        // 'pass' | 'warn' | 'fail' (advisory + skips ignored)
$checklist->summary();       // ['passed' => 6, 'warnings' => 2, 'failures' => 1, 'skipped' => 0]
$checklist->failures();      // CheckResult[]
$checklist->recommendations(); // failures first, then warnings
$checklist->toArray();       // JSON-ready payload (no score key)
```

각 `CheckResult`는 `id`, `group`, `label`, `status`, `message`, 선택적 `recommendation`, `advisory` 플래그를 가집니다.

### 명령 {#command}

```bash
php artisan seo-pro:checklist "App\Models\Post" 42
php artisan seo-pro:checklist "App\Models\Post" 42 --json     # machine-readable
php artisan seo-pro:checklist "App\Models\Post" 42 --strict   # non-zero exit on any failure (CI)
php artisan seo-pro:checklist "App\Models\Post" 42 --locale=it
```

### 편집기에서 (Filament, 선택 사항) {#in-the-editor-filament-optional}

[`rankbeam/laravel-seo-filament`](/ko/guide/filament)를 설치하면 포커스 키워드 필드에 **온페이지 체크리스트** 동작이 나타납니다. 클릭하면 레코드의 저장된 콘텐츠에 대한 동일한 통과/경고/실패 검사를 모달로 보여 줍니다. Filament 패키지는 Pro에 의존하지 않습니다. AI 제안과 같은 단방향 확장 훅으로 동작을 추가하므로 헤드리스 설치는 영향받지 않습니다.

## 설정 {#configuration}

```php
// config/seo-pro.php → 'checklist'
'checklist' => [
    'enabled' => true,             // shows the Filament action; headless API always works

    'content' => [
        'min_words' => 200,        // below this → fail (too thin)
        'good_words' => 600,       // below this → warn; at/above → pass
    ],

    'internal_links' => [
        'min' => 2,                // internal links needed to pass
    ],

    'readability' => [
        'min_words' => 50,         // below this → skipped
        'advisory' => true,        // false → a hard-to-read page fails the checklist
    ],

    // The check registry. Each entry implements
    // Rankbeam\Seo\Pro\OnPage\Rules\Check and is resolved from the container
    // (custom checks get the Stemmer/StopWords toolkit injected). Remove a
    // line to drop a check, reorder to reorder, or append your own.
    'rules' => [
        \Rankbeam\Seo\Pro\OnPage\Rules\Keyword\KeywordInTitleCheck::class,
        // …
    ],
],
```

### 사용자 지정 검사 작성 {#writing-a-custom-check}

```php
use Rankbeam\Seo\Pro\OnPage\ChecklistContext;
use Rankbeam\Seo\Pro\OnPage\CheckResult;
use Rankbeam\Seo\Pro\OnPage\Rules\AbstractCheck;

class KeywordInSubheadingCheck extends AbstractCheck
{
    public function id(): string { return 'keyword_in_subheading'; }
    public function group(): string { return 'keyword'; }
    public function label(): string { return 'Focus keyword in a subheading'; }

    public function run(ChecklistContext $context): CheckResult
    {
        $keyword = $context->primaryKeyword();

        if ($keyword === null) {
            return $this->skipNoKeyword();
        }

        return str_contains($context->htmlContent, '<h2')
            ? $this->pass('Found a subheading.')
            : $this->warn('No subheading found.', 'Add an H2 that includes the keyword.');
    }
}
```

클래스를 `seo-pro.checklist.rules`에 추가해 등록하세요. 체크리스트는 점수에 반영하지 않는 별도 네임스페이스이므로 [스캔 문제 코드](/ko/pro/scan-issues) id를 재사용해서는 **안 됩니다**.

## 콘텐츠 읽기 방식 {#how-the-content-is-read}

`SeoPro::checklistFor($model)`는 다음을 분석합니다.

- **제목 / 설명** — 편집기 표시기와 스캔이 측정하는 같은 *최종* 값이므로 결과가 어긋나지 않습니다.
- **콘텐츠** — `$model->getContentForSEO()`(코어 `HasSEO` 접근자, 기본값 `content` / `body` / `text`). 모델에서 재정의해 실제 본문을 가리키세요.
- **URL** — `$model->getUrlForSEO()`.
- **포커스 키워드** — 저장된 `seo_meta.focus_keywords`.

순수 분석이며 페이지를 요청하거나 데이터를 기록하지 않습니다.

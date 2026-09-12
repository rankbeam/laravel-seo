---
description: "명령 하나로 점수, 문제 추세, 해결·신규 문제, 복구된 404, Search Console 변화, AI 봇 활동을 담은 브랜드 화이트라벨 PDF를 생성하고 선택적으로 예약 이메일을 보내세요."
---

# 화이트라벨 보고서 {#white-label-reports}

사이트 하나의 전체 점수, 발견 문제 추세, **지난 보고서 이후 해결된 문제와 신규 문제**, 복구된 404와 깨진 링크, Search Console의 주요 변화, AI 봇 활동을 담은 브랜드 **PDF 보고서**입니다. 명령 하나로 생성하고 선택적으로 **예약 이메일로 보낼 수 있습니다**. 에이전시용으로 설계되어 로고, 색상, “{client}용 보고서” 표시를 넣어 클라이언트에게 전달할 수 있습니다.

[생성된 영어 예시 보고서 내려받기 (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf) 또는 [스캔 → 수정 → 보고서 안내](/ko/pro/walkthrough)를 참고하세요. 예시는 시드로 넣은 Merchant 콘텐츠와 새로 실행한 두 스캔을 사용합니다. 해결된 문제 1개, 미해결 19개가 표시되며 Search Console 데이터는 없습니다.

[![생성된 Merchant 데모 보고서의 첫 페이지.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## 포함 내용 {#what-s-in-it}

- **전체 점수** — 최근 페이지별 점수의 평균(공개된 [평가 기준](/ko/pro/scoring): A ≥ 90 … F), 지난 보고서 대비 변화, 최근 스캔의 **전체 점수 추세**입니다. 이제 각 스캔이 실행에 사이트 점수를 기록하므로 추세는 실제 스캔별 이력입니다. 업그레이드 후 첫 스캔부터 채워지며, 점수가 없는 과거 실행은 건너뜁니다.
- **스캔별 발견 문제** — 최근 완료된 스캔의 실제 추세입니다. 적을수록 좋습니다.
- **해결 및 신규 문제** — 지난 보고서 이후 없앤 결함 수와 새로 나타난 결함 수입니다. 문제에 해결/재개 [수명 주기](/ko/pro/scan-issues#issue-lifecycle)가 생겼으므로, 해당 방식으로 한 기간 전체가 지나면 실제 문제 이력에서 읽고 그렇지 않으면 이전 보고서 스냅샷에서 읽습니다.
- **복구** — 해결된 깨진 링크, 지난 보고서 이후 **복구된** 404(경로 자체가 다시 200을 반환; [`seo-pro:404-recheck`](/ko/pro/production#scheduler) 참고), **리디렉션된** 404, 남은 미해결 항목입니다. 복구된 404는 실제 원본 경로 수정이며 리디렉션과 따로 셉니다.
- **Search Console** — 상위 검색어와 페이지, 그리고 지난 보고서 대비 클릭 변화가 가장 큰 **주요 변화**입니다. GSC가 설정되지 않았으면 정상적으로 건너뜁니다.
- **AI 봇 활동** — 검증된 봇 신원이 아닌 user-agent로 분류한 요청, 누적 합계, 그리고 일별 [버킷 이력](/ko/pro/ai-bot-monitor#period-metrics-daily-buckets)이 기간을 포함할 때 **이번 기간의 실제 접속 횟수와 각 봇이 크롤링한 고유 URL 수**입니다. 그렇지 않으면 스냅샷의 누적 값 차이를 사용합니다.

## “지난 보고서 이후” {#since-the-last-report}

보고서는 임의의 날짜가 아니라 **이전 보고서와 기간별 비교**를 수행합니다. 생성할 때마다 점수, 미해결 문제 식별 정보, Search Console 행, 봇별 접속 횟수를 경량 스냅샷(`seo_report_runs`)으로 저장합니다. 다음 보고서가 현재 상태와 그 스냅샷을 비교합니다.

이는 자체 이력을 보존하지 않는 신호의 대체 방법입니다. 페이지별 점수는 최신 값만 유지하므로 보고서 생성 시 스냅샷을 남겨야 정확한 비교가 가능합니다. 이제 일부 신호는 **실제** 이력을 보존하므로 보고서는 이를 우선하고 스냅샷은 대체 수단으로만 사용합니다. 문제의 해결/재개 [수명 주기](/ko/pro/scan-issues#issue-lifecycle)는 전체 기간을 기록한 뒤 실제 해결/신규 수를 제공하며, Search Console은 [일별 지표](/ko/pro/search-console#historical-metrics), AI 봇 접속은 [일별 버킷](/ko/pro/ai-bot-monitor#period-metrics-daily-buckets)을 유지해 실제 기간별 접속 및 고유 URL 수를 제공합니다. 업그레이드 후 첫 보고서이거나 이력이 기간 전체를 포함하지 않으면 각 신호는 스냅샷 차이로 돌아갑니다.

따라서 다음 두 원칙이 적용됩니다.

- **첫 보고서는 기준선입니다.** 현재 상태를 표시하며 “해결”, “신규”, 주요 변화, “지난 보고서 이후” 수치는 *두 번째* 보고서부터 채워집니다.
- **주기는 직접 정합니다.** 월간 생성이면 한 달, 주간 생성이면 한 주의 차이를 다룹니다. 기준선을 바꾸면 안 되는 임시 미리보기에는 `--no-store`를 사용하세요.

## 보고서 생성 {#generate-a-report}

```bash
php artisan seo-pro:report
```

옵션이 없으면 `storage/app/seo-reports/`에 PDF를 기록합니다. 다른 위치를 지정하거나 이메일로 보내세요.

```bash
# Write to a specific file or directory
php artisan seo-pro:report --output=/tmp/acme-october.pdf

# E-mail it to one or more recipients (the PDF is attached)
php artisan seo-pro:report --email=client@acme.com --email=pm@agency.com

# One-off preview that does NOT store a snapshot (deltas won't advance)
php artisan seo-pro:report --no-store --output=/tmp/preview.pdf

# Machine-readable summary
php artisan seo-pro:report --json
```

### 옵션 {#options}

| 옵션 | 효과 |
| --- | --- |
| `--client=` | 보고서 대상 클라이언트 표시 변경 |
| `--agency=` | 보고서의 에이전시 이름 변경 |
| `--accent=` | 강조색 변경(16진수, 예: `#3D5AFE`) |
| `--logo=` | 로고 이미지 경로 변경 |
| `--email=` | 수신자 주소(반복 지정 가능); 보고서를 이메일로 전송 |
| `--send` | 설정된 수신자에게 이메일 전송 |
| `--output=` | PDF를 이 파일 또는 디렉터리에 기록 |
| `--no-store` | 스냅샷을 저장하지 않음(기간 비교 기준이 이동하지 않음) |
| `--json` | 기계 판독용 요약 출력 |

## 이메일 예약 {#schedule-the-e-mail}

패키지가 스스로 예약하지 않으며 주기는 앱에서 정합니다. 앱의 콘솔 일정(`routes/console.php` 또는 `app/Console/Kernel.php`)에 등록하세요.

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

설정 또는 `.env`에 기본 수신자를 한 번 지정하세요.

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send`는 이 수신자를 사용하며 명시적 `--email` 옵션이 우선합니다.

## 브랜드 설정 {#branding}

브랜드 정보는 비밀이 아니므로 설정에 둡니다. 한 번 지정하면 모든 보고서에 적용됩니다. 위 명령 옵션으로 필드마다 보고서별 변경이 가능하므로 한 설치에서 여러 클라이언트 보고서를 만들 때 유용합니다.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

참고:

- **로고** — `PNG`/`JPG`/`GIF`/`WEBP`/`SVG` 파일의 절대 경로입니다. 앱이 파일을 읽어 PDF에 data URI로 포함하므로 렌더러가 네트워크로 이미지를 가져올 필요가 없습니다. `PNG` 또는 `JPG`가 가장 안전합니다.
- **강조색** — 16진수 리터럴로 검증하며 잘못된 값은 기본값으로 돌아갑니다. 원시 CSS가 아닌 색상으로만 사용됩니다.
- **에이전시 이름** — 기본값은 앱 이름(`config('app.name')`)입니다.

전체 설정은 `config/seo-pro.php`의 `reports` 아래에 있습니다. `paper`(기본값 `a4`), `include_gsc`, 포함할 추세 실행 / GSC 행 / 봇 수 등을 지정합니다.

## 설치당 사이트 하나 {#one-site-per-install}

Pro는 설치된 애플리케이션 하나를 스캔하므로 보고서는 **해당 설치**를 설명합니다. 여러 클라이언트 사이트를 운영하는 에이전시는 설치별로 보고서를 생성하며 `--client` / 브랜드 옵션으로 구별합니다. 여러 테넌트를 위한 “사이트” 모델은 없습니다.

## 생성 방식 {#how-it-s-built}

PDF는 기본적으로 순수 PHP인 **dompdf**로 렌더링합니다. Node나 헤드리스 Chromium이 없어도 되므로 시스템 바이너리 없이 큐 워커 또는 cron에서 예약 보고서를 만들 수 있으며 Pro는 헤드리스로 유지됩니다. 렌더러의 원격 요청은 꺼져 있고 유일한 이미지인 로고는 내장되므로 렌더링 필드의 내용이 네트워크 요청을 유발하지 않습니다.

### 모든 문자 체계의 보고서 (Browsershot 렌더러) {#reports-in-every-script-browsershot-renderer}

코어 3.20 / Pro 2.40부터 Chrome 렌더러는 JavaScript를 끄고 HTTP(S), FTP, WebSocket 자산 요청을 차단합니다. 게시한 템플릿은 내장 자산을 사용하는 정적 HTML/CSS여야 합니다. 이 제어는 페이지 자산에 관한 것이며 Chrome 호스트와 샌드박스도 올바르게 설정해야 합니다. Fontconfig가 혼합 텍스트의 소수 문자 체계를 포함해 필요한 글꼴 누락을 보고하면 PDF 렌더러가 설치 방법을 안내하는 경고를 기록합니다. 글꼴이 없어도 Chrome은 PDF를 생성하므로 보내기 전에 결과를 확인하세요.

dompdf는 내장 글꼴(DejaVu Sans: 라틴, 키릴, 그리스 문자)만 그리므로 일본어, 태국어, 아랍어 클라이언트의 보고서에는 글자 대신 네모가 나타납니다. Pro 2.34부터는 대신 `spatie/browsershot` 패키지를 통해 **헤드리스 Chrome**으로 렌더링할 수 있습니다. 코어 OG 이미지와 같은 의존성을 사용하므로 서버를 한 번 설정하면 됩니다.

```php
// config/seo-pro.php → 'reports'
'renderer' => 'browsershot',   // default 'dompdf'
'browsershot' => [
    'chrome_path' => null,      // null = reuse seo.og_image.chrome_path
    'node_binary' => null,      //   …  seo.og_image.node_binary
    'npm_module_path' => null,  //   …  seo.og_image.npm_module_path
    'no_sandbox' => null,       //   …  seo.og_image.no_sandbox
    'timeout' => 90,
],
'locale' => null,               // report language; null captures the app locale
'format_locale' => null,        // optional regional date/number format
```

Chrome은 서버에 설치된 글꼴을 사용합니다. 템플릿은 코어의 문자 체계별 글꼴 스택(`Noto Sans`, 페이지 언어의 `Noto Sans CJK` 글꼴 우선, 태국어, 아랍어, 히브리어, 데바나가리, 컬러 이모지, 라틴 문자의 기준인 DejaVu Sans)을 사용합니다. [OG 이미지](/ko/guide/multilingual#og-images-in-every-script)와 동일하게 필요한 글꼴을 설치하세요. Debian과 Ubuntu에서는 `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji`를 사용합니다. `seo:og-images`는 실행 중 페이지의 문자 체계에 설치된 글꼴이 없으면 경고하며, 보고서에도 같은 진단이 적용됩니다. 두 엔진의 Blade 템플릿, 데이터, 스냅샷은 동일합니다. 래스터화 엔진만 바뀌며 `ReportGenerator::renderer()`로 연결된 엔진을 확인할 수 있습니다.

### 독자 로케일의 날짜와 숫자 {#dates-and-numbers-in-the-reader-s-locale}

보고서는 생성 시 `seo-pro.reports.locale` 값을 캡처하며 null이면 앱 로케일을 사용합니다. 결정된 번역 언어가 PDF 및 이메일 표시, 기본 제목, 글꼴 선택, HTML `lang` 속성을 제어합니다. 자체 번역 파일이 없는 지역 로케일은 기본 언어, 다음으로 영어를 사용합니다. 중국어 간체(`zh_CN`)와 번체(`zh_TW`)는 구분됩니다.

`ext-intl` 확장이 설치되어 있으면 날짜와 숫자는 ICU를 통해 요청한 로케일을 따릅니다. 다른 지역 형식을 명시적으로 선택하려면 `seo-pro.reports.format_locale` 값을 설정하세요. `locale=it`와 `format_locale=en_US`는 이탈리아어 표시와 미국식 날짜/숫자 형식을 만듭니다. `ext-intl` 확장이 없으면 기존의 영어 날짜와 쉼표로 자릿수를 묶는 숫자 형식을 유지합니다.

큐 이메일은 워커 설정이 바뀌어도 캡처한 언어, 형식, 제목을 유지합니다. PDF를 생성하기 전에 언어를 선택하세요. 나중에 mailable의 로케일을 바꿔도 첨부 파일은 번역되지 않습니다. Pro 2.39 이전의 오래된 큐 페이로드는 캡처한 설정이 없어 워커 설정을 사용합니다. 사용자 지정 제목, 브랜드 정보, 저장된 문제 메시지는 원본 데이터로 유지됩니다.

CLI 표시는 별개입니다. `php artisan seo-pro:report --display-locale=it`는 명령 요약을 번역하며, 클라이언트 PDF/이메일 언어는 보고서 설정이 선택합니다. CLI 기본 언어는 영어이고 `SEO_PRO_CLI_LOCALE`로 바꿀 수 있습니다. JSON 키와 코드는 안정적으로 유지되며 사람이 읽는 표시만 번역될 수 있습니다. `seo-pro-lang` 리소스를 게시해 `lang/vendor/seo-pro/{locale}/seo-pro.php`의 보고서 및 작업 흐름 메시지를 덮어쓰세요.

코드에서는 컨테이너에서 `ReportGenerator`를 가져오세요.

```php
use Rankbeam\Seo\Pro\Reports\Branding;
use Rankbeam\Seo\Pro\Reports\ReportGenerator;

$report = app(ReportGenerator::class)->generate(
    Branding::fromConfig()->withOverrides(['prepared_for' => 'Acme Outdoor Co.']),
);

$report->pdf;        // raw PDF bytes
$report->data;       // the assembled ReportData
$report->run;        // the persisted SEOReportRun snapshot
```


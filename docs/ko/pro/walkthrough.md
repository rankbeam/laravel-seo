---
description: "실제 Rankbeam Pro 스캔에서 누락된 설명을 확인하고, Filament에서 수정한 뒤 재스캔하는 과정을 따라가세요. 생성된 예시 PDF 보고서도 내려받을 수 있습니다."
---

# 스캔에서 수정 검증까지 {#from-a-scan-to-a-verified-fix}

스캔에서 데모 글의 설명 누락을 발견했습니다. Filament에서 설명을 추가하고 다시 스캔한 뒤, 수정 결과를 보여 주는 보고서를 생성했습니다.

이 화면은 2026년 9월 9일 로컬에서 실행한 Merchant 데모를 캡처한 것입니다. 콘텐츠는 시드로 넣은 예시 데이터이며, 두 스캔과 보고서는 이 안내를 위해 생성했습니다. 과거 추세를 미리 채워 넣지 않았습니다. 앱은 Laravel 12와 Filament 4를 사용하며 Rankbeam 코어, 무료 편집기, Pro 엔진을 설치했습니다.

**[생성된 보고서 내려받기 (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf)**

## 등록된 페이지 스캔 {#scan-the-registered-pages}

[Pro를 설치](/ko/pro/installation)하고 스캔 대상을 등록한 뒤 실행하세요.

```bash
php artisan seo-pro:scan --sync
```

데모에는 콘텐츠 레코드 18개와 라우트 세 개가 등록되어 있습니다. 첫 스캔은 21개 대상을 실패 없이 모두 완료했으며, 경고 6개와 알림 14개로 총 20개 문제를 발견했습니다.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="첫 번째 완료 스캔: 대상 21개, 문제 20개, 경고 6개, 알림 14개." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*스크린샷은 2× 해상도로 캡처했습니다. 이미지를 열면 전체 크기로 확인할 수 있습니다.*

## 문제 하나 살펴보기 {#inspect-one-issue}

**SEO Dashboard**에서 해당 행 옆의 **Page issues**를 여세요. “Behind the Scenes: Our Product Photography” 글에서는 누락된 `description`, 페이지 URL, 문제를 감지한 스캔을 확인할 수 있습니다.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Page issues 대화상자에 Post 5, 해당 URL, 누락된 설명 필드가 표시되어 있습니다." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## 설명 저장 {#save-the-description}

**Posts**에서 글을 열고 **SEO description**을 입력한 뒤 저장하세요. [무료 Filament 편집기](/ko/guide/filament)는 검색 미리보기에 입력한 텍스트를 표시하고 출처를 **Manual**로 나타냅니다. 이 예시의 설명은 142자이며, 제목은 계속 글에서 가져옵니다.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="저장한 SEO 설명과 142자를 표시하는 글자 수 표시기." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="실시간 미리보기에 입력한 설명이 사용되며 Manual로 표시되어 있습니다." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

필드 저장과 수정 검증은 별개의 단계입니다. 스캔 점수는 다음 스캔 후 갱신됩니다. Filament가 없다면 모델의 `saveSEO()` 메서드로 같은 값을 저장하세요.

## 재스캔 후 변경 확인 {#rescan-and-check-what-changed}

같은 명령을 다시 실행하세요.

```bash
php artisan seo-pro:scan --sync
```

대시보드에서 이 문제의 상태가 **Fixed**로 표시됩니다. 나머지 19개 문제는 여전히 열려 있습니다.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="기록된 스캔 비교: 신규 문제 0개, 재발 0개, 해결 1개, 미해결 19개." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| 항목 | 이전 | 이후 |
|---|---|---|
| 완료한 대상 | 21 | 21 |
| 미해결 문제 | 20 | 19 |
| 경고 | 6 | 5 |
| 알림 | 14 | 14 |
| 평균 기술 SEO 점수 | 92 | 93 |

[점수](/ko/pro/scoring)는 Rankbeam의 기술 검사를 반영합니다. 트래픽, 검색 게재순위, AI 답변 포함 여부를 측정하지 않습니다. 설명 검사를 통과해도 검색 엔진이 그 설명을 표시한다는 보장은 없습니다.

## 보고서 생성 {#generate-the-report}

이 데모에서는 글을 수정하기 **전에** 기준 보고서를 생성하고, 재스캔 후 두 번째 보고서를 생성했습니다.

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

두 번째 PDF에는 **해결 1개**, **신규 0개**, **미해결 19개**가 표시됩니다. 추세에는 위의 두 스캔만 포함됩니다. Search Console과 AI 봇 로깅은 꺼져 있었으므로 해당 섹션에는 데이터를 사용할 수 없다고 표시됩니다.

[![생성된 예시 보고서 첫 페이지: 점수 93, 해결한 문제 1개, 미해결 문제 19개.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

첫 보고서가 비교 기준을 설정합니다. 페이지를 수정한 뒤 보고서를 한 번만 생성하면 이전 보고서 대비 변화를 보여 줄 수 없습니다. 비교 기준을 갱신하지 않는 미리보기에는 `--no-store`를 사용하세요.

예시는 Browsershot 렌더러를 사용합니다. 렌더러 요구 사항, 브랜드 설정, 예약 전달은 [화이트라벨 보고서](/ko/pro/reports)를 참고하세요.

## 자신의 앱에서 실행 {#run-it-on-your-own-app}

[Pro 설치](/ko/pro/installation)부터 시작한 뒤 출력 결과를 직접 확인할 수 있는 페이지를 스캔하세요. Pro는 [Filament 없이도](/ko/pro/headless) 실행됩니다. 무료 메타데이터 렌더러를 먼저 체험하려면 [Docker 데모](/ko/guide/demo)를 사용하세요.

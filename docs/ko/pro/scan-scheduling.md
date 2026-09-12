---
description: "전체 SEO 스캔을 예약하고 지난 스캔 이후 새로 발생하거나 재발하거나 해결된 문제를 확인하세요. 대시보드와 선택적 이메일에 영향도순으로 표시합니다."
---

# 스캔 예약 및 변화 비교 {#scan-scheduling-delta}

전체 SEO 스캔을 **예약 실행**하고 **지난 스캔 이후의 변화**, 즉 새로 발생하거나 재발하거나 해결된 문제를 확인하세요. 대시보드와 선택적 요약 이메일에서 영향도순으로 보여 줍니다.

함께 작동하는 두 기능을 한 페이지에서 설명합니다. 변화 비교가 있어야 예약 스캔 결과를 받아볼 가치가 생기기 때문입니다.

## 지난 스캔 이후의 변화 {#what-changed-since-the-last-scan}

완료된 각 스캔은 **미해결 문제 집합**을 경량 스냅샷(`seo_scan_run_issues`)으로 고정합니다. 두 실행의 스냅샷을 비교하면 정확한 세 가지 차이를 얻습니다.

- **신규(New)** — 이전에는 열려 있지 않았지만 지금 열려 있고, 그보다 앞선 어떤 스캔에서도 열린 적 없는 문제입니다. 처음 발견한 문제를 뜻합니다.
- **재발(Regressed)** — 해결되었다가 **다시 발생한** 문제입니다. 심각도가 높아졌다는 뜻은 아닙니다. 문제 유형별 심각도는 고정되어 있으므로 의미 있는 회귀는 재발이며, 문제 [수명 주기](/ko/pro/scan-issues#issue-lifecycle)의 다시 열기와 같습니다.
- **수정됨(Fixed)** — 이전 스캔에서 열려 있었지만 지금은 사라진 문제입니다.

각 그룹을 [영향도순](#impact-ordering)으로 정렬해 중요한 항목을 먼저 보여 줍니다.

### 문제 테이블 대신 스냅샷을 사용하는 이유 {#why-a-snapshot-not-the-issues-table}

문제에는 해결/재개 [수명 주기](/ko/pro/scan-issues#issue-lifecycle)가 있습니다. 스캔이 반복되면 같은 행을 갱신하며, 문제가 계속 열려 있을 때마다 `scan_run_id`를 최신 실행으로 다시 기록합니다. 지속적인 문제 이력에는 적합하지만, 실제 테이블만으로는 *실행 N이 끝났을 때 어떤 문제가 열려 있었는지* 알 수 없습니다. 계속되는 문제는 최신 실행만 가리키기 때문입니다.

그래서 각 실행은 안정적인 **문제 지문** `issue_type | target | field`를 키로 미해결 집합을 스냅샷에 저장합니다. [화이트라벨 보고서](/ko/pro/reports)가 차이를 계산할 때 사용하는 것과 같은 식별 기준입니다. 이후 고정된 두 지문 집합에 단순 집합 연산을 적용하므로 연속 실행뿐 아니라 **어떤** 두 실행도 올바르게 비교할 수 있습니다.

### 경계 상황을 정확히 표시하는 방식 {#edge-cases-handled-honestly}

- **페이지가 스캔 대상에서 빠짐.** 미해결 문제는 재스캔되지 않으므로 열린 상태를 유지하고 매 실행의 스냅샷에 다시 포함됩니다. 거짓 “해결”이 아니라 **계속 미해결**로 표시합니다. 더 이상 살펴보지 않는다는 이유로 해결되었다고 주장하지 않습니다.
- **스캔 사이에 검사를 끔.** 해당 문제가 더 이상 생성되지 않으므로 수명 주기는 해결로 표시하고 미해결 집합에서 제거합니다. 따라서 **해결**로 나타납니다. 스캐너의 현재 판단을 반영하는 것이며, 문제 수준에서 “직접 수정함”과 “검사를 끔”을 구별할 방법은 없습니다.
- **업그레이드 후 첫 스캔.** 기능 도입 이전 실행에는 스냅샷이 없으므로 비교 기준으로 선택하지 않습니다. 첫 스냅샷 스캔은 사이트 전체를 “신규”로 보고하지 않고 **기준선**, 즉 차이 없는 현재 상태로 표시합니다. 두 번째 스냅샷 스캔부터 변화 비교가 작동합니다.

### 대시보드 {#on-the-dashboard}

[SEO 대시보드](/ko/pro/installation)의 **“마지막 스캔 이후 변경 사항”** 위젯은 최근 완료된 두 스캔을 비교해 신규 / 재발 / 해결 수와 각 그룹의 주요 문제를 영향도순으로 보여 줍니다. 스냅샷 스캔이 두 개 쌓이기 전에는 짧은 기준선 안내를 표시합니다.

## 영향도 정렬 {#impact-ordering}

큰 문제가 먼저 보이도록 각 변화 그룹을 **영향도** 점수로 정렬합니다.

```
impact = severity_weight × page_importance
```

- **severity_weight**는 공개된 [점수 평가 기준](/ko/pro/scoring)을 재사용합니다. critical은 `40`, warning은 `15`, notice는 `5`입니다. 심각도는 결함의 중요성에 대한 제품의 명시적 판단이므로 새 척도를 만들지 않고 이를 사용합니다.
- **page_importance**는 **실제 검색 수요**, 즉 [Search Console](/ko/pro/search-console)에서 해당 페이지가 얻는 노출 수로 결정합니다. 페이지 간 차이를 실제로 구분하는 신호이기 때문입니다.

  ```
  page_importance = 1 + demand_weight·demand + priority_weight·priority
  demand   = log1p(page impressions) / log1p(busiest page's impressions)   ∈ [0,1]
  priority = the page's configured per-class sitemap priority              ∈ [0,1]
  ```

  노출 수는 로그 척도를 적용하고 가장 트래픽이 많은 페이지를 기준으로 정규화합니다. 트래픽이 10×라고 중요도가 10×인 것은 아닙니다. 따라서 작은 블로그와 큰 카탈로그에서 같은 방식으로 동작합니다. 사이트맵 `<priority>`는 **약한 보조** 신호입니다. 기본적으로 비어 있고 설정해도 보통 동일한 값이어서 주된 정렬 기준이 될 수 없습니다. `seo.sitemap.models`에 유형별 우선순위를 설정한 경우 소폭 영향을 줍니다.

**Search Console이 없어도 사용할 수 있습니다.** 동기화한 GSC 이력과 설정된 우선순위가 없으면 모든 페이지의 `page_importance`가 `1`이며 영향도는 순수한 **심각도 순서**가 됩니다. 만들어 낸 데이터가 아닌 합리적인 기본값입니다. 수요 가중치를 사용하려면 [GSC 이력](/ko/pro/search-console#historical-metrics)을 `seo-pro:gsc-sync`로 동기화하세요.

가중치와 기간은 `seo-pro.scan.delta.impact` 아래에서 조정하세요.

## 스캔 예약 {#scheduling-a-scan}

패키지는 **기본적으로 아무것도 예약하지 않습니다**. 다음과 같이 켜세요.

```php
// config/seo-pro.php
'schedule' => [
    'enabled' => true,          // env SEO_PRO_SCHEDULE_ENABLED
    'frequency' => 'weekly',    // daily | weekly | monthly | hourly
    'time' => '03:00',          // for daily/weekly/monthly
    'timezone' => null,         // null = app timezone
    // ...
],
```

또는 완전한 cron 표현식으로 직접 제어할 수 있습니다. `frequency`보다 우선합니다.

```php
'cron' => '0 3 * * 1',   // env SEO_PRO_SCHEDULE_CRON
```

이것으로 끝입니다. 패키지는 Laravel 스케줄러에 `seo-pro:scan` 명령을 등록하고 `withoutOverlapping` 메서드로 예약된 **명령**의 중복 실행을 막습니다. 이 스케줄러 잠금은 큐 작업의 전체 수명 주기를 보호하지 않습니다. 등록은 스케줄러/콘솔 문맥에서만 실행되므로 **웹 요청 부하는 없습니다**.

::: warning 실행 중인 스케줄러 필요
Laravel 스케줄러가 실행되지 않으면 패키지 예약은 작동하지 않습니다. 표준 한 줄 cron(`* * * * * php artisan schedule:run`) 또는 개발 환경의 `php artisan
schedule:work`가 필요합니다. [운영 환경 설정](/ko/pro/production#scheduler)을 참고하세요.
:::

직접 연결하려면 `schedule.enabled`를 꺼 두고 자신의 콘솔 커널에서 명령을 예약하세요. 변화 비교와 요약은 그대로 작동합니다.

```php
$schedule->command('seo-pro:scan --notify')->weekly();
```

`--sync`는 대상별 큐 작업을 만들지 않고 스캔을 인라인으로 실행합니다. 큐 워커가 없는 작은 사이트에는 적합하지만 운영 환경에서는 꺼 두세요.

## 요약 이메일 {#summary-e-mail}

예약 스캔이 끝날 때 **“지난 스캔 이후의 변화”** 이메일을 받도록 설정할 수 있습니다. 신규 / 재발 / 해결 문제를 영향도순으로 정리한 브랜드 HTML 요약입니다.

```php
'schedule' => [
    // ...
    'notify' => [
        'enabled' => true,                       // env SEO_PRO_SCHEDULE_NOTIFY
        'recipients' => ['seo@agency.test'],     // falls back to reports.recipients
        'subject' => 'SEO scan summary',
        'only_on_change' => true,                // skip when nothing changed
    ],
],
```

[화이트라벨 보고서](/ko/pro/reports)의 브랜드 및 메일 설정을 재사용하므로 에이전시 이름, 로고, 강조색이 이어집니다. 수신자를 따로 지정하지 않으면 보고서 수신자를 사용합니다. `only_on_change`는 변화가 없는 스캔의 이메일을 생략합니다. 첫 기준선 스캔은 항상 전송합니다.

요약은 `--notify`로 시작한 실행에만 전송됩니다. `notify.enabled`가 켜져 있으면 스케줄러가 이를 자동 추가합니다. `--notify` **없이** 수동으로 실행한 `seo-pro:scan` 명령은 누구에게도 이메일을 보내지 않습니다.

::: tip 다른 채널을 사용하려면
이메일 대신 Slack, 웹훅, 사용자 지정 요약을 원한다면 `Rankbeam\Seo\Pro\Events\SeoScanCompleted` 이벤트를 구독하세요. 완료된 실행당 한 번 발생하며 실행 정보를 전달하므로 `Rankbeam\Seo\Pro\Scanning\Delta\ScanRunDelta`로 변화를 구성해 원하는 채널로 보낼 수 있습니다.
:::

## 보존 {#retention}

스냅샷은 해당 실행과 함께 연쇄 삭제되므로 [`seo-pro:scan-prune`](/ko/pro/production#scheduler)가 자동으로 오래된 항목을 정리합니다. 새로 예약할 것은 없습니다. 실행에 미해결 문제가 없어야 정리하므로 최근 실행의 스냅샷은 항상 비교에 사용할 수 있습니다.

`seo-pro.scan.delta.snapshot => false`로 스냅샷 저장을 완전히 끌 수 있습니다. 변화 비교와 요약도 사라집니다.

---
description: "seo:explain으로 각 SEO 필드를 설정한 리졸버 계층과 덮어쓴 값을 확인하세요. 네트워크나 라이선스가 필요 없는 읽기 전용 명령으로 예상과 다른 제목이나 robots 태그를 디버깅할 수 있습니다."
---

# 값 결정 과정 확인(`seo:explain`) {#explain-the-resolution-seo-explain}

Rankbeam은 [계층별 우선순위 체인](/ko/concepts/resolver-precedence)을 통해 페이지의 SEO 값을 결정합니다. 설정, 데이터베이스 기본값(전역 / 모델 유형 / 라우트), 모델에서 계산한 값, 명시적 `seo_meta` 순으로 처리한 뒤 후처리(제목 접미사, canonical, 이미지 절대 URL 변환)와 [색인 방지](/ko/guide/indexing-guard)를 적용합니다. 렌더링된 `<title>` 또는 `robots` 태그가 예상과 다르다면, **`seo:explain` 명령으로 각 필드를 설정한 계층과 덮어쓴 값을 정확히 확인할 수 있습니다.**

읽기 전용이며 네트워크나 라이선스가 필요하지 않습니다. 병합 로직을 다시 구현하지도 않습니다. 출처 정보는 리졸버 자체의 계층별 기여 값에서, 최종 값은 실제 리졸버에서 가져오므로 설명이 실제 렌더링 결과와 어긋나지 않습니다.

## 사용법 {#usage}

```bash
# Explain a specific record
php artisan seo:explain "App\Models\Post" 42

# Explain the first record of a model
php artisan seo:explain "App\Models\Post"

# With a route-defaults layer and a locale
php artisan seo:explain "App\Models\Post" 42 --route=posts.show --locale=de

# Machine-readable
php artisan seo:explain "App\Models\Post" 42 --json
```

모델은 [`HasSEO`](/ko/guide/quickstart) 트레이트를 사용해야 합니다.

## 출력 읽기 {#reading-the-output}

```
SEO resolution — Post #42  (locale: en, route: posts.show)
Layers, low → high: config · global · model-type · route · computed · explicit

 Field         Final value                    Set by            Overrode
 title         My Post | Acme                 computed          —
                 ↳ title suffix ' | Acme' appended
 description   A hand-written summary…        explicit          computed: "An auto excerpt…"
 canonical     https://acme.com/blog/my-post  post-processing   —
                 ↳ derived from model getUrlForSEO() (query string stripped)
 robots        noindex,nofollow               explicit          config: index,follow
                 ↳ indexing guard forced 'noindex,nofollow' (environment 'staging' …)
 og_image      https://acme.com/share.jpg     explicit          config: /default-og.jpg
                 ↳ absolutized from '/share.jpg'
```

- **Set by** — 적용된 계층, 즉 null이 아닌 값을 설정한 계층 중 우선순위가 가장 높은 계층입니다. 어떤 계층도 필드를 설정하지 않았지만 값이 *유도된* 경우에는 `post-processing`로 표시합니다. 요청/모델 URL에서 만든 canonical, canonical에서 만든 og:url, 절대 URL로 변환한 이미지가 여기에 해당합니다.
- **Overrode** — 값을 제공했지만 우선순위가 낮아 적용되지 않은 모든 계층을 순서대로 표시합니다. 어떤 값이 가려졌는지 확인할 수 있습니다.
- **↳ notes** — 계층 병합 후 값을 바꾼 후처리입니다. 제목 접미사, canonical 쿼리 문자열 제거, og:url 유도, 이미지 절대 URL 변환, 그리고 모든 계층보다 우선해 `noindex`를 강제하는 색인 방지가 포함됩니다.

::: tip og:type과 twitter:card
두 필드는 null이 아닌 프레임워크 기본값(`website` / `summary_large_image`)을 갖습니다. 따라서 이를 설정한 가장 높은 계층(보통 `computed`)이 `config`보다 우선합니다. 저장된 `seo_meta` 행이 없는 페이지는 이 필드에 아무 값도 제공하지 않으므로, `article`처럼 계산된 `og:type` 값이 비어 있는 `website`에 가려지지 않습니다. 이는 실제 병합 동작을 그대로 반영합니다.
:::

## 사이트 수준 값 결정 {#site-level-resolution}

[사이트 설정 출처 기록에 관한 보충 설명](/ko/concepts/resolver-precedence)에 따라, `seo:explain` 명령은 출처가 자주 혼동되는 사이트 전체 값도 보고합니다. **어느 출처에서 canonical 호스트, 사이트 이름, 기본 로캘을 설정했는지** 확인할 수 있습니다.

```
Site-level resolution
 Value           Resolved     Source
 Site name       Acme         env (APP_NAME)
 Default locale  en           config (app.locale)
 Canonical host  acme.com     programmatic (model getUrlForSEO())
```

특히 canonical 호스트를 확인하는 것이 중요합니다. 잘못된 호스트(노출된 `localhost`, `https` 사이트의 `http://`, 모델 URL과 다른 앱 URL)는 자기 참조 canonical 오류의 대표적인 원인입니다.

## JSON 출력 {#json-output}

`--json` 옵션은 도구나 CI에서 사용할 수 있도록 전체 추적 정보를 출력합니다. `target`, 필드별 `winner` / `losers` / `final` / `notes`, `site_level` 기록이 포함됩니다.

```json
{
  "target": { "model": "App\\Models\\Post", "id": 42, "route": "posts.show", "locale": "en" },
  "fields": {
    "title": {
      "final": "My Post | Acme",
      "winner": { "layer": "computed", "value": "My Post" },
      "losers": [],
      "notes": ["title suffix ' | Acme' appended"]
    }
  },
  "site_level": {
    "canonical_host": { "value": "acme.com", "source": "programmatic (model getUrlForSEO())" }
  }
}
```

## 관련 문서 {#see-also}

- [리졸버 우선순위](/ko/concepts/resolver-precedence) — `seo:explain` 명령이 추적하는 전체 체인.
- [무료 SEO 감사](/ko/guide/audit) — `seo:audit`는 *무엇이 잘못됐는지* 찾고, `seo:explain` 명령은 *왜 해당 값이 나왔는지* 보여줍니다.

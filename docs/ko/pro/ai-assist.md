---
description: "자체 API 키로 선택적으로 사용하는 AI 지원: 제목과 메타 설명 제안, 스캔 문제의 쉬운 설명, 한 번의 클릭으로 설명 다시 쓰기, schema.org 제안. 기본적으로 꺼져 있습니다."
---

# AI 지원 {#ai-assist}

**자체 API 키**로 선택적으로 사용하는 AI 지원입니다. 제목과 메타 설명 제안, 스캔 문제를 쉬운 말로 풀어 주는 설명, 한 번의 클릭으로 적용하는 **설명 다시 쓰기**, **구조화 데이터(schema.org) 제안**을 제공합니다. **기본적으로 꺼져 있으며**, 플래그가 꺼져 있으면 AI 코드 경로는 전혀 실행되지 않습니다.

설계의 기준은 세 가지입니다.

- **여러분의 키와 제공자.** 요청은 *여러분의 서버*에서 *여러분이* 설정한 제공자(Anthropic, OpenAI, Google 또는 로컬/OpenAI 호환 서버)로 직접 전송됩니다. 요금이 발생하면 여러분의 계정으로 청구됩니다. 패키지는 요청을 중계하거나 사용량에 따라 과금하거나 재판매하지 않으며, 어디에도 텔레메트리를 보내지 않습니다.
- **대화형 제안은 명시적으로 수락해야 합니다.** 제안을 선택하면 양식에 채워지고, 대시보드 수정은 적용해야 저장됩니다. Pro 2.42부터 일괄 CLI는 기본적으로 비공개 초안을 저장합니다. 의도적으로 즉시 기록하려는 경우에만 `--auto-apply` 옵션을 사용하세요.
- **실패해도 핵심 작업은 계속됩니다.** 키 누락, 잘못된 키, 계정 잔액 소진, 요청 속도 제한 또는 시간 초과는 화면 안에 메시지로 표시됩니다. 저장, 렌더링 또는 스캔을 막지 않습니다.

## 제공자 한눈에 보기 {#providers-at-a-glance}

계정 접근 권한, 데이터 요구 사항과 비용을 기준으로 선택하세요. 네 연동은 같은 작업을 제공하지만 지원 모델, 출력 형식, 속도와 품질은 다를 수 있습니다.

| 제공자 | 패키지 기본 모델 | 구조화된 출력 | 예시 비용 | 용도 |
|---|---|---|---|---|
| **로컬**(Ollama / LM Studio / vLLM) | `llama3.1`(직접 설정) | 최선 노력 방식(`response_format`) | 자체 호스팅 추론은 **API 요금 $0**, 인프라 비용은 별도 | 데이터 전송 대상 제어 |
| **OpenAI** | `gpt-5.5` | 선택한 모델이 지원하는 경우 Structured Outputs | 아래 예시 가정에서 제안당 약 $0.005 | 기존 OpenAI 계정 활용 |
| **Anthropic** | `claude-opus-4-8` | 지원되는 경우 `output_config.format` | 같은 가정에서 제안당 약 $0.015 | 기존 Anthropic 계정 활용 |
| **Google** | `gemini-2.5-flash` | 지원되는 경우 `responseSchema` | 같은 가정에서 제안당 약 $0.0005 | Google 계정 활용. 모델 할당량과 가격 확인 필요 |

이 이름들은 패키지에 포함된 설정을 설명하며, 현재 여러분의 계정에서 사용할 수 있음을 보장하지 않습니다. 비용은 패키지의 예시 가정에 따른 것으로, 현재 가격을 검증한 값이 아닙니다. 연동 동작과 공개된 시험에서 관찰한 사항은 다음과 같습니다.

- **구조화된 출력.** 지원되는 OpenAI, Google 및 Anthropic 경로에는 JSON 스키마를 전달합니다. 유효하지 않은 응답은 명확한 실패로 처리합니다. 로컬 서버에는 `response_format` 값을 최선 노력 방식으로 전달합니다. 서버가 이를 무시하면 유연한 파싱으로 유효한 목록 또는 실패를 반환하며, 부분적인 출력은 적용하지 않습니다.
- **추론은 토큰 사용량을 바꿉니다.** 설명에 인용된 Gemini 시험에서는 설명 하나에 숨겨진 추론 토큰 약 500개와 표시되는 토큰 약 100개를 사용했습니다. 시험한 Anthropic 호출은 숨겨진 추론 토큰을 보고하지 않았습니다. 이는 해당 모델 계열 전체의 보편적인 특성이 아닙니다. 숨겨진 토큰도 출력으로 과금될 수 있으며, 아래 추론 최소 한도가 있는 이유이기도 합니다.
- **모델을 설정할 수 있습니다.** `SEO_PRO_AI_MODEL` 설정에 어댑터의 API 및 매개변수와 호환되는 사용 가능한 모델을 지정하세요. 예로는 `claude-haiku-4-5`, `gpt-5.4-mini`, `gemma-3-12b-it` 등이 있습니다. 전체 컬렉션에 사용하기 전에 지원 여부와 출력 품질을 확인하세요.

## 설정 {#setup}

기능을 켜고 환경 변수에 제공자 키를 넣으세요. 클라우드 제공자를 바꾸려면 제공자와 키를 수정하고 모델 재정의 값도 확인하세요. 로컬 어댑터에는 서버 URL도 필요합니다.

::: code-group

```dotenv [Local (Ollama / LM Studio)]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=local
SEO_PRO_AI_MODEL=llama3.1          # a model the server has pulled
SEO_PRO_AI_LOCAL_BASE_URL=http://localhost:11434/v1
SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true   # required for a localhost server
# no API key needed for a local server
```

```dotenv [OpenAI]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=openai
SEO_PRO_AI_API_KEY=sk-...
# optional: SEO_PRO_AI_MODEL=gpt-5.4-mini  (default: gpt-5.5)
```

```dotenv [Anthropic]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=anthropic
SEO_PRO_AI_API_KEY=sk-ant-...
# optional: SEO_PRO_AI_MODEL=claude-haiku-4-5  (default: claude-opus-4-8)
```

```dotenv [Google]
SEO_PRO_AI_ENABLED=true
SEO_PRO_AI_PROVIDER=google
SEO_PRO_AI_API_KEY=AIza...        # AI Studio key: aistudio.google.com/apikey
# use a paid (billing-enabled) key for real use — the free tier is heavily rate-limited
# optional: SEO_PRO_AI_MODEL=gemma-3-12b-it  (default: gemini-2.5-flash)
```

:::

::: tip 제공자 API 키는 Claude / ChatGPT 구독과 별개입니다
Claude Code, Claude.ai 또는 ChatGPT **구독**은 **API** 사용료를 충당하지 않습니다. `SEO_PRO_AI_API_KEY` 값에는 제공자 개발자 콘솔에서 발급한 *사용량 기반 API 키*(또는 Google AI Studio 키)가 필요하며, 별도의 크레딧 잔액이 있어야 합니다. 구독만 있거나 잔액이 없는 계정은 인증되더라도 **크레딧/할당량 부족** 오류를 반환합니다. [문제 해결](#troubleshooting)을 참고하세요.
:::

설정 파일(`config/seo-pro.php` 파일의 `ai` 블록)은 `timeout`, `max_input_chars`, `max_output_tokens`, `token_budgets`, `reasoning_models` + `reasoning_min_output_tokens`, `suggestion_count`, `bulk_model`(일괄 채우기용 저비용 등급, [비용](#cheaper-bulk-generation) 참조), `retry`, `pricing` 표와 `local` 하위 블록을 제공합니다. 모두 [제한과 조정](#limits-and-tuning)에서 설명합니다.

::: warning 설정을 캐시할 때의 키 처리
설정에는 키 자체가 아니라 환경 변수의 **이름**(`api_key_env`)만 저장합니다. 따라서 `php artisan config:cache` 명령은 `bootstrap/cache/config.php` 파일에 키를 기록하지 않습니다. 대신 설정이 캐시되어 있으면 `.env` 파일을 읽지 않으므로, 서버의 실제 환경 변수로 `SEO_PRO_AI_API_KEY` 값을 설정하세요.
:::

## 로컬 추론과 클라우드 옵션 {#running-at-0-and-the-cheapest-paid-option}

- **자체 호스팅 추론에는 제공자의 토큰당 API 요금이 없습니다.** 하드웨어, 전기와 운영에는 여전히 비용이 듭니다. 설정한 추론 서버와 그 의존성이 모두 네트워크 안에 있어야 콘텐츠도 네트워크 안에 머뭅니다.
- **Google의 할당량과 가격은 모델·등급별로 다릅니다.** AI Studio 키(`aistudio.google.com/apikey`, `AIza…` 형식)로 무료 등급 시험이 가능할 수 있습니다. 필요에 따라 결제를 활성화하기 전에 제한이 작업량에 맞는지 확인하세요. 패키지 기본값은 `gemini-2.5-flash` 모델입니다. Gemini와 Gemma 모델이 모두 같은 사고 기능을 갖지는 않습니다. `reasoning_models` 설정은 이름 패턴을 적용하며, 기능을 검사하지 않습니다.

추론 실행 위치를 제어하려면 다음과 같이 설정하세요.

- **로컬 / OpenAI 호환.** **Ollama**, **LM Studio**, **vLLM**, **LocalAI** 같은 OpenAI Chat Completions 호환 서버나 **OpenRouter** 같은 원격 게이트웨이에 `provider=local` 설정을 사용하세요. `SEO_PRO_AI_LOCAL_BASE_URL` 값에 API 루트를 지정하고(`/chat/completions` 경로가 추가됨) 사용 가능한 `SEO_PRO_AI_MODEL` 모델을 선택하세요. 원격 게이트웨이는 네트워크 밖에서 데이터를 받으며 요금을 청구할 수 있습니다. 어댑터 이름이 `local`이라고 해서 로컬 추론이라는 뜻은 아닙니다.

::: warning 로컬 `base_url` 검증 — localhost는 명시적으로 허용해야 합니다
`base_url` 값은 권한이 필요한 설정이며, 다른 모든 외부 가져오기와 같은 `SsrfGuard` 검증을 거칩니다. http/https만 허용하고 사용자 정보를 금지하며, 기본적으로 **공개** 주소로 해석되어야 합니다. 따라서 실수로 설정되거나 악의적으로 지정된 `base_url` 값을 내부 서비스 탐색에 사용할 수 없습니다. 실제 로컬 서버는 사설 주소인 `127.0.0.1` 주소에 있으므로, 로컬 제공자는 `seo-pro.ai.local.allow_local_addresses`(`SEO_PRO_AI_LOCAL_ALLOW_PRIVATE=true`) 설정으로 명시적으로 허용해야 합니다. 공개 게이트웨이(OpenRouter)에서는 이 옵션을 꺼 두세요. 요청 경로는 고정되어 있고 리디렉션을 따르지 않으므로 다른 호스트로 키가 전달되지 않습니다.
:::

::: tip 지원되는 Ollama 모델의 사고 기능 제어
로컬 사고 모델은 기본 시간 제한을 초과할 수 있습니다. 모델과 서버 버전이 지원한다면 `seo-pro.ai.local.extra_body` 설정의 `['think' => false]` 값으로 제안 생성 시 사고 기능을 끌 수 있습니다. 지원 여부는 다르므로 [Ollama 문서](https://docs.ollama.com/capabilities/thinking)를 확인하세요. 필요하면 `seo-pro.ai.timeout` 값을 늘리세요. 일부 모델이 거부하므로 패키지는 `temperature` 값을 보내지 않습니다.
:::

## 비용 {#cost}

패키지는 추가 수수료를 붙이지 않습니다. 제공자에게 직접 지불하며, 자체 호스팅 추론에는 제공자 API 요금이 없습니다. 인프라 비용은 별도입니다. 중요한 수치는 대화형 사용의 **제안당** 비용과 전체 컬렉션의 **일괄 채우기** 비용입니다.

`seo-pro.ai.pricing` 표(1,000,000토큰당 USD)는 추정 토큰 수를 일괄 채우기 확인 메시지에 표시할 달러 금액으로 환산합니다. 이는 **패키지에 포함된 추정 가정**이며 현재 공개 가격을 검증한 값이 아닙니다. 정확한 추정을 위해 **제공자가 현재 공개한 가격으로 재정의하세요**.

| 모델 패턴 | 입력 $/1M | 출력 $/1M |
|---|---|---|
| `claude-opus-*` | 15.00 | 75.00 |
| `claude-sonnet-*` | 3.00 | 15.00 |
| `claude-haiku-*` | 1.00 | 5.00 |
| `gpt-5*mini*` | 0.50 | 1.50 |
| `gpt-5*` | 5.00 | 15.00 |
| `gemini-2.5-pro*` | 1.25 | 10.00 |
| `gemini-*flash*` | 0.15 | 0.60 |

공개된 예시는 시험한 페이지에서 제목 하나와 설명 하나를 생성할 때 사용한 토큰 수에 위 가정을 적용합니다. 마지막 열은 지원되는 어댑터에 예시의 50% 배치 할인을 적용합니다. 현재 가격 견적이 아닙니다.

| 제공자 / 모델 | 제안 한 쌍당 약 | 레코드 1,000개당 약(일괄 채우기) | 레코드 1,000개당 약(`--batch`) |
|---|---|---|---|
| 로컬 `gemma`/`llama`(Ollama) | **API 요금 $0** | **API 요금 $0** | 해당 없음(어댑터 미구현) |
| Google `gemini-2.5-flash`(유료) | 약 $0.001 | 약 $0.40 | 해당 없음(Rankbeam 어댑터 미구현) |
| OpenAI `gpt-5.5` | 약 $0.008 | 약 $5.25 | **약 $2.63**(50% 할인) |
| Anthropic `claude-opus-4-8` | 약 $0.03 | 약 $20 | **약 $10**(50% 할인) |

명령은 추정치를 약 ±50%로 표시하지만 **이는 지출 한도나 보장된 오차 범위가 아닙니다**. 실제 입력, 출력과 가격에 따라 총액이 달라집니다. 추정은 표시되는 출력을 모델링하므로, 과금되는 숨겨진 추론이 있으면 비용이 추정치를 넘을 수 있습니다. 가격 항목이 없는 모델에는 토큰 추정치만 표시합니다.

### 더 저렴한 일괄 생성 {#cheaper-bulk-generation}

`seo-pro.ai.bulk_model`(`SEO_PRO_AI_BULK_MODEL`) 설정으로 **일괄 채우기 전용** 모델(`seo-pro:ai-fill` / `SeoPro::aiFill()`)을 지정할 수 있습니다. Filament와 `seo-pro:ai-suggest` 명령은 계속 `model` 모델을 사용합니다. 값이 null이면 일괄 채우기도 `model` 모델을 사용합니다. 추정은 선택한 모델의 가격 패턴을 사용합니다. 작업량을 늘리기 전에 대표적인 출력물을 평가하세요. 저렴한 모델이라고 해서 자동으로 적합한 것은 아닙니다.

```dotenv
SEO_PRO_AI_MODEL=claude-opus-4-8        # interactive: highest quality
SEO_PRO_AI_BULK_MODEL=claude-haiku-4-5  # bulk-fill: cheap tier
```

패키지 예시는 **anthropic** `claude-haiku-4-5`, **openai** `gpt-5.5-mini`, **google** `gemini-2.5-flash` 또는 더 작은 **local** 모델을 사용합니다. 제공자가 실제로 제공하지 않는 모델 이름도 가격 패턴에 일치할 수 있습니다. 설정하기 전에 실제 모델 ID, API 호환성과 가격을 확인하세요.

**100페이지 채우기**는 페이지마다 제목과 설명이 모두 없으면 제공자 호출 200회가 됩니다. 패키지의 `pricing` 기본값과 추정기의 호출당 토큰 모델(호출당 입력 600 + 출력 150토큰)을 적용한 품질 모델과 `bulk_model` 저비용 등급의 비교는 다음과 같습니다.

| 제공자 | 품질 모델 — 100페이지 | `bulk_model` 저비용 등급 — 100페이지 |
|---|---|---|
| **Anthropic** | `claude-opus-4-8` ≈ **$4.05** | `claude-haiku-4-5` ≈ **$0.27** |
| **OpenAI** | `gpt-5.5` ≈ **$1.05** | `gpt-5.5-mini` ≈ **$0.11** |
| **Google** | `gemini-2.5-pro` ≈ **$0.45** | `gemini-2.5-flash` ≈ **$0.04** |
| **로컬**(Ollama / vLLM) | 모든 모델 — **API 요금 $0** | 모든 모델 — **API 요금 $0** |

이는 예시 추정치이며 ±50% 범위를 보장하지 않고 숨겨진 추론도 반영하지 않습니다. 추정치에 의존하기 전에 선택한 제공자의 공개 가격으로 `seo-pro.ai.pricing` 설정을 갱신하세요.

## 출력 언어 {#output-language}

모든 프롬프트는 페이지의 언어와 BCP-47 코드를 명시합니다. 예를 들면 *"발췌문에 다른 언어가 있더라도 페이지 언어인 브라질 포르투갈어(pt-BR)로"*라고 지정합니다. 모델에 보내는 페이지 컨텍스트에는 `Language:` 행이 포함됩니다(Pro 2.34). 그전에는 "원본 콘텐츠와 같은 언어로"라고만 지정하여 짧거나 코드가 섞인 발췌문을 모델이 보고 추측해야 했습니다. 발췌문에 영어 브랜드 이름이 있는 터키어 페이지의 결과가 영어로 돌아올 수 있었습니다. 로케일은 페이지 메타데이터를 해석한 로케일이며, 페이지에 로케일이 없으면 앱 로케일을 사용합니다. [길이 기준](/ko/guide/multilingual#title-and-description-budgets-per-script)을 선택하는 로케일과 같으므로 일본어 페이지에는 *일본어로* 약 30자 제목을 요청합니다.

Pro 2.36부터 명시적인 콘텐츠 로케일이 메타데이터 행, 콘텐츠 훅과 프롬프트 언어를 함께 제어합니다. 작업자의 인터페이스 언어는 바뀌지 않습니다.

```php
$ai = app(\Rankbeam\Seo\Pro\Ai\SeoSuggestionService::class);
$titles = $ai->suggestTitles($post, locale: 'it');
$descriptions = $ai->suggestDescriptions($post, locale: 'ja');
$rewrite = $ai->rewriteDescription($post, locale: 'it');
$schema = $ai->suggestSchemaType($post, locale: 'it');
$request = $ai->suggestionRequest($post, 'title', locale: 'ja');
```

기존 위치 인수는 바뀌지 않습니다. `locale:` 인수를 생략하면 모델의 `seoData()` 기본값을 사용하므로, 별도의 번역 모델에서 자기 언어를 선언할 수 있습니다. 발췌문은 `getContentForSEO()` 메서드가 비어 있지 않은 콘텐츠를 반환하면 이를 사용하고, 아니면 설정된 콘텐츠 필드로 대체합니다. Filament 1.11은 단일 로케일 모드와 페이지 전환 모드를 포함해 선택한 탭의 로케일을 자동으로 전달합니다.

```bash
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --locale=it
php artisan seo-pro:suggest-schema "App\Models\Post" 42 --locale=ja
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --locale=it --batch
```

일괄 `plan()`, `fill()`, `submitBatchFill()` 메서드도 마지막 `locale:` 인수를 받습니다. `FillProgress(..., locale: 'it')` 객체를 만들 때와 실행을 제출할 때 같은 로케일을 사용하세요. 각 배치 항목은 콘텐츠 로케일을 기록합니다. 결과 수집은 저장된 로케일을 사용하고 쓰기 전에 메타데이터 행을 다시 확인합니다. 명시적 로케일 실행에는 별도 체크포인트 파일을 사용하며 처리 완료 마커도 언어를 구분합니다. 같은 명령을 다시 실행해 수집하세요. 사용자 정의 작업은 콘텐츠 로케일을 직렬화하고 전달해야 합니다.

Pro 2.36 이전에 만든 체크포인트에는 콘텐츠 로케일이 기록되어 있지 않습니다. 미완료 기존 배치는 보존되지만 자동 수집은 거부됩니다. `--fresh` 옵션으로 폐기하기 전에 제공자 결과와 의도한 로케일을 대조하여 정리하세요. 그렇지 않으면 대체 제출이 같은 작업에 다시 요금을 발생시킬 수 있습니다. 처리된 레코드가 있는 기존 순차 체크포인트 역시 초기화 전에 대조·정리가 필요합니다.

### 언어별 평가 {#per-language-evaluation}

Pro 소스 저장소에는 17개 로케일의 입력 페이지 170개와 선택적으로 실행하는 평가 도구가 있습니다. 입력 페이지에는 구조 검사와 기본 언어 휴리스틱 검사가 적용되어 있으며, 독립적인 원어민 승인은 아직 이루어지지 않았습니다.

평가 도구는 **제목과 설명 모두**를 검사하고 그래핌 클러스터 길이와 언어·문자 체계 근거를 기록하며, 단언 검사 전에 제공자의 각 응답을 보존합니다. 짧은 제목, 여러 언어가 섞인 텍스트, 중국어와 일본어가 공유하는 문자는 판단이 불확실할 수 있습니다. 기본 언어를 포르투갈어로 추정했다고 해서 브라질식 용례가 확인되는 것은 아니며, 부분적인 중국어 문자 검사도 지역별 문장 품질을 인증하지 않습니다.

실제 실행에는 `SEO_PRO_AI_EVAL=1` 설정, 명시적인 `SEO_PRO_AI_EVAL_LOCALES` 선택과 `SEO_PRO_AI_EVAL_RUN` ID가 필요합니다. 제공자 요금이 발생할 수 있으며 기본적으로 실행하지 않습니다. 각 실행은 근거를 제공자, 요청/반환 모델, 픽스처·요청·코드 해시와 타임스탬프에 연결합니다. 실패한 시도도 보존합니다. 재개 시 저장된 응답을 재사용합니다. 중단된 요청은 이미 제공자에게 도달했을 수 있으므로 명시적으로 재시도해야 합니다.

근거는 소스 테스트 환경의 `storage/app/seo-ai-evals/<run-id>/` 경로에 저장됩니다. 픽스처의 `README.md` 파일은 버전이 있는 스키마와 명령을 설명합니다. 원어민 검토자는 별도 검토 기록에서 정확한 출력 해시를 대상으로 점수를 매깁니다. 자동 검사를 통과했다고 해서 원어민 승인을 받았거나 게시 가능한 문안이 보장되는 것은 아닙니다.

## 제한과 조정 {#limits-and-tuning}

모든 조정 항목은 `config/seo-pro.php` 파일의 `ai` 블록에 있습니다.

- **`timeout`**(기본 `15`초, 환경 변수 `SEO_PRO_AI_TIMEOUT`) — Filament 제안 모달이 열릴 때 수행하는 동기 호출의 제한이기도 하므로 사용성을 위해 짧게 유지합니다. **느린 추론 모델이나 로컬 모델은 15초를 넘어** 시간 초과될 수 있습니다. 그런 모델을 사용한다면 `SEO_PRO_AI_TIMEOUT` 설정으로 늘리고 위의 Ollama `think => false` 도움말도 참고하세요. 시간 초과가 발생해도 핵심 작업은 계속됩니다. 화면 안에 오류를 표시할 뿐 저장을 막지 않습니다.
- **`max_input_chars`**(기본 `6000`) — 요청마다 보내는 페이지 콘텐츠(HTML을 제거한 일반 텍스트) 양에 대한 비용·개인정보 보호 제한입니다.
- **`max_output_tokens`**(기본 `1000`) — 생성 토큰의 기본 상한입니다. 여기에 도달한 응답은 별도의 `truncated` 실패를 반환하며, 알림 없이 반쪽짜리 답을 내놓지 않습니다.
- **`token_budgets`** — 작업별 출력 상한입니다(`suggestions` 800, `explanation` 600, `rewrite` 300, `schema_suggestion` 700). 어느 작업도 전체 기본 한도를 필요로 하지 않지만 추론 최소 한도를 추가로 적용합니다.
- **`reasoning_models`** + **`reasoning_min_output_tokens`**(기본 `2000`) — 이름이 패턴(`*gemma*`, `gemini-2.5-*`, `o1*`/`o3*`/`o4*`)에 일치하는 모델은 출력 한도를 최소 한도까지 높입니다. 사고 모델은 표시되는 출력을 만들기 전에 숨겨진 토큰을 사용하므로 한도가 작으면 응답이 잘리기 때문입니다.
- **`suggestion_count`**(기본 `3`) — 요청할 제목/설명 대안의 개수입니다.
- **`retry`** — *일시적인* 실패만 자동 재시도합니다. [응답 처리 방식](#how-replies-are-handled)을 참고하세요.

## Filament에서 사용 {#in-filament}

선택적인 Filament 패키지(`rankbeam/laravel-seo-filament` >= 1.1)를 설치한 상태에서 AI 지원을 켜면 다음 기능이 추가됩니다.

- **AI로 제안받기**: SEO 섹션을 사용하는 모든 리소스의 편집 페이지에서 SEO 제목과 설명 필드에 표시됩니다. 모달에 생성된 대안과 글자 수를 표시하며, 하나를 선택하면 검토할 수 있도록 필드에 채웁니다.
- **설명 보기(AI)**: 대시보드 문제 표에서 문제와 구체적인 수정 방법을 쉬운 말로 짧게 설명합니다.
- **설명 다시 쓰기(AI)**: 대시보드 문제 표에서 설명 작업 옆에 표시됩니다. 페이지 설명 길이 기준 안에 들어가는 개선된 메타 설명 하나를 제안합니다. 라틴 문자 160자, CJK 약 80자이며 코어의 [길이 정책](/ko/guide/multilingual#title-and-description-budgets-per-script)을 따릅니다. 모달에서 검토한 뒤 **다시 쓴 설명 적용**을 누르면 페이지의 `seo_meta` 레코드에 기록합니다. 적용하기 전에는 아무것도 기록하지 않습니다.
- **구조화 데이터 제안(AI)**: 대시보드 문제 표에서 가장 적합한 schema.org 리치 결과 유형(Product, Article 또는 Breadcrumb)을 제안하고 이를 위해 만든 JSON-LD를 보여 줍니다. **구조화 데이터 적용**을 누르면 페이지의 `seo_meta.schema_jsonld` 필드에 추가합니다. 선택적인 [구조화 데이터 편집기](/ko/guide/filament#structured-data-schema-org)가 관리하는 것과 같은 열이므로 그 편집기에서 다시 불러와 편집할 수 있습니다. 작성자나 이미지가 없는 Article처럼 불완전한 제안은 누락 필드와 함께 표시하며 **적용하지 않습니다**.

마지막 두 기능은 범위가 제한된 수정입니다. [범위가 제한된 수정](#bounded-fixes-propose-never-auto-apply)을 참고하세요.

## 범위가 제한된 수정(제안만 하고 자동 적용하지 않음) {#bounded-fixes-propose-never-auto-apply}

두 지원 작업은 일반 제안보다 한 단계 더 나아가 한 번의 클릭으로 적용할 수 있는 *제약된* 값 하나를 만듭니다. 그래도 둘 다 **제안만 합니다**. 명시적으로 수락하기 전에는 아무것도 저장하지 않습니다.

- **설명 다시 쓰기**(`SeoSuggestionService::rewriteDescription($model, $issue?)`)는 **항상 페이지 문자 체계에 대한 코어 길이 정책의 기준 안에 있는 메타 설명** 하나를 반환합니다(라틴 문자 160, CJK 약 80). 제목·설명 제안 프롬프트와 같은 기준이며, 페이지 자체의 해석된 값을 바탕으로 선택합니다. 모델이 기준을 넘으면 정해진 규칙에 따라 문장 경계, 이어서 단어 경계에서 텍스트를 잘라냅니다. 따라서 수락한 설명 자체가 `description_too_long` 경고를 발생시키지 않습니다. 스캔 문제를 전달하면 *너무 김* 또는 *누락* 같은 문제에 맞게 다시 쓰도록 유도합니다.
- **구조화 데이터 제안**(`SeoSuggestionService::suggestSchemaType($model)`)은 모델에 **유형 추천과 말단 필드 값**만 요청하며 원시 JSON-LD를 요청하지 않습니다. 그런 다음 결정론적 코드가 코어 스키마 빌더(`ProductSchema` / `ArticleSchema` / `BreadcrumbSchema`)로 문서를 조립하고 코어의 `SchemaValidator` 검증기로 검사합니다. 따라서 모델이 지어낸 `@type`, `@context` 또는 구조가 페이지에 도달하지 않습니다. 조립된 문서에 필수 필드가 없으면 *불완전*으로 표시하고 적용을 보류합니다. 실제 시험에서 한 제공자는 내용이 적은 페이지에 `Article` 유형을 제안했지만 작성자와 이미지가 없어 올바르게 보류되었고, 다른 제공자들은 유형 제안 자체를 하지 않았습니다.

## 헤드리스 {#headless}

스크립트와 Filament를 사용하지 않는 앱에서도 같은 기능을 JSON으로 사용할 수 있습니다.

```bash
# title + description suggestions for a model
php artisan seo-pro:ai-suggest "App\Models\Post" 42

# one field only
php artisan seo-pro:ai-suggest "App\Models\Post" 42 --field=description

# explain a scan issue (IDs from seo-pro:scan-status)
php artisan seo-pro:ai-suggest --issue=17

# suggest a schema.org type + built, validated JSON-LD for a model
php artisan seo-pro:suggest-schema "App\Models\Post" 42
```

출력에는 제안(또는 추천 유형, 생성한 JSON-LD와 검증 통과 여부), 사용한 모델과 **요청별 토큰 사용량**(입력 / 출력 / 추론)이 포함됩니다. 제공자의 요율로 비용을 계산하는 데 도움이 되지만 토큰 수가 청구서는 아닙니다. 실패하면 JSON 응답 봉투에 오류를 넣고 0이 아닌 종료 코드로 끝납니다. 모든 지원 인터페이스와 마찬가지로 `seo-pro:suggest-schema` 명령도 **제안 전용**입니다. 문서를 출력하고 아무것도 기록하지 않습니다.

## 누락된 메타데이터 일괄 채우기 {#bulk-fill-missing-metadata}

**Pro 2.42에서는 CLI 기본값이 바뀝니다.** `seo-pro:ai-fill` 명령은 생성한 필드마다 비공개 초안을 하나씩 저장합니다. 승인할 때까지 게시된 SEO 메타데이터는 바뀌지 않습니다. 기존 값과 계산된 대체값은 건너뜁니다. 현재 유효한 대기 초안이 있으면 다시 생성하지 않고 재사용합니다.

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description
php artisan seo-pro:ai-review
php artisan seo-pro:ai-review DRAFT_ID
php artisan seo-pro:ai-review DRAFT_ID --approve --reviewer="editor@example.com"
php artisan seo-pro:ai-review DRAFT_ID --reject --reviewer="editor@example.com"
```

`seo-pro:ai-review` 명령은 첫 100개 대기 초안을 JSON으로 나열합니다. ID를 조회하면 값과 비공개 근거를 읽을 수 있습니다. 승인과 거부는 AI가 꺼져 있어도 동작하며 제공자를 호출하지 않습니다. 승인에는 작업자 레이블이 필요합니다. 원본 레코드나 대상 메타데이터가 바뀌었거나 레코드가 삭제된 초안은 거부합니다. 레이블은 작업자가 자신을 누구라고 명시했는지 기록할 뿐, 실질적인 사람의 검토를 증명하지 않습니다. 기본값이 아닌 데이터베이스를 설정했다면 `--connection=NAME` 옵션을 사용하세요.

`--auto-apply` 옵션은 여전히 누락된 필드의 즉시 게시를 명시적으로 복원합니다. `--force` 옵션은 확인 메시지를 건너뛰며 **검토를 대신하지 않습니다**. `--dry-run` 옵션은 초안이나 메타데이터를 저장하지 않고 값을 생성해 출력합니다. 그래도 제공자를 호출하므로 비용이 발생할 수 있습니다. `--field`, `--limit`, `--locale` 옵션은 생성 범위를 제한합니다. 업그레이드 후 예약 명령을 의도에 맞게 갱신하세요.

### 대규모 실행: 속도 조절, 비용 추정과 중단 후 재개 {#at-scale-pacing-a-cost-estimate-and-crash-resume}

`seo-pro.ai.fill.throttle_ms` 기본값은 200밀리초입니다. `confirm_over` 기준(기본 레코드 100개)에 도달하면 `seo-pro.ai.pricing` 설정을 기반으로 한 추정치를 보여 주고 생성 전에 확인합니다. 체크포인트는 중단되어도 완료된 필드를 유지합니다. 제공자가 요청을 수락한 뒤 시간 초과가 발생하면 중복 과금될 수 있습니다. `--fresh` 옵션을 사용하기 전에 상태가 불확실한 작업을 대조하여 정리하세요. 같은 조건의 일괄 작업은 한 번에 하나만 실행하세요.

```php
use Rankbeam\Seo\Pro\Facades\SeoPro;

$summary = SeoPro::aiFill()->fill([\App\Models\Post::class], 'all', limit: 50, review: true);
```

사용자 정의 연동에서는 초안을 준비하도록 `review: true` 인수를 전달하세요. 하위 PHP API는 호환성을 위해 `apply: true, review: false` 기본값을 유지하므로 기존 호출은 계속 즉시 기록합니다. `apply: false` 옵션은 저장하지 않고 미리 봅니다. 요약 키 `filled` 값은 검토 모드에서 초안으로 준비한 레코드를 포함해 처리된 레코드 수를 셉니다. CLI에서는 이를 `staged` 레이블로 표시합니다.

### 배치 모드(50% 저렴) {#batch-mode-50-cheaper}

`--batch` 옵션은 지원되는 Anthropic 또는 OpenAI 비동기 엔드포인트를 사용합니다. 해당 제공자가 문서에 명시한 할인이 추정에 반영되지만 현재 모델 가격을 확인하세요. Google과 로컬 어댑터는 순차 생성으로 대체합니다. 지금 제출한 뒤 나중에 같은 명령을 실행해 초안을 수집하세요.

```bash
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
# Re-run the same command to collect drafts.
php artisan seo-pro:ai-fill "App\Models\Post" --field=description --batch
```

제출과 수집 사이에 제공자, 로케일과 게시 모드를 바꾸지 마세요. 검토 모드와 `--auto-apply` 모드는 별도 체크포인트를 사용합니다. 같은 조건의 배치가 미완료 상태이면 CLI는 다른 모드의 시작을 거부합니다. 제출 여부가 불확실하면 대조·정리를 위해 중단합니다. 부분 성공은 보존하며 일시적인 실패는 재시도할 수 있습니다. 수집 시 누락 필드를 다시 확인하고, 승인 시에는 제출 전에 기록한 원본 스냅샷도 확인합니다. `seo-pro.ai.fill.batch.request_timeout` 기본값은 120초입니다. 예약된 수집도 기본적으로 초안을 저장합니다.

## 생성 출처 기록, 마이그레이션과 데이터 필터링 {#origin-review-and-filtering}

**Core 3.21 및 Pro 2.42**가 필요합니다. 코어는 마이그레이션을 자동으로 불러옵니다. 저장되는 제안을 생성하기 전에 Pro 마이그레이션을 게시하고 SEO 모델이 사용하는 각 데이터베이스에서 마이그레이션을 실행하세요.

```bash
php artisan vendor:publish --tag=seo-pro-migrations
php artisan migrate
```

비공개 `seo_ai_proposals` 테이블은 Laravel 암호화 캐스트로 생성된 값과 제공자·모델·요청 근거를 저장합니다. `APP_KEY` 값과 백업을 안전하게 보관하세요. 이를 잃으면 이러한 값을 읽을 수 없습니다. 레코드 식별자, 상태와 결정 메타데이터는 일반 데이터베이스 열로 남습니다. 양식을 버리면 양식의 대안은 `offered` 또는 `selected` 상태로 남을 수 있습니다. 자동 삭제는 없습니다. 애플리케이션의 보존 정책을 정하고, 대기 중인 초안과 `seo_meta.ai_provenance` 항목이 여전히 참조하는 근거를 보존하며, 데이터베이스 내보내기와 검토 명령 출력에 대한 접근을 제한하세요.

수락한 양식 제안, 대시보드 수정과 일괄 생성 값에는 필드 출처가 포함됩니다. 이후 Eloquent로 편집하면 `origin: ai` 값을 유지하고 `edited: true` 상태를 설정합니다. 이는 값이 바뀌었다는 뜻이지 사람이 검증했다는 뜻이 아닙니다. 필드를 비우면 마커가 제거됩니다. 코어의 HTML, 배열, JSON 및 Inertia 출력은 필드 이름, 출처와 편집 상태만 공개하며, 해당하는 경우 사용자 정의 `rankbeam:ai-origin` 메타 태그를 사용합니다. 생성 ID와 제공자 상세 정보는 비공개로 유지됩니다. 원시 `SEOMeta` 모델을 공개 API로 노출하지 마세요.

이 기능은 이후 지원되는 저장 경로를 기록하며, 과거 콘텐츠나 모든 편집 버전을 기록하지 않습니다. 직접 SQL, 쿼리 빌더 갱신과 사용자 정의 렌더러는 이러한 제어를 우회할 수 있습니다. 독립적으로 작성한 대체 값이어서 출처를 초기화하는 것이 타당하다면 명시적으로 초기화하세요. 일반 편집은 출처를 유지합니다. 사용자 정의 마커는 표준화된 워터마크, 변조 방지 출처 증명 또는 제50조 준수 주장에 해당하지 않습니다. 실제 제공자 출력 품질과 제공자 자체 표시는 별도로 평가해야 합니다.

선택적으로 `AiPromptFilter` 인터페이스를 구현하고 `seo-pro.ai.context_filter` 설정을 지정할 수 있습니다. 동기 또는 배치 제출 전에 조립된 사용자 프롬프트를 필터링합니다. 실패하면 제출을 막고 민감한 내용을 제거한 오류를 반환합니다. 시스템 지시는 바뀌지 않습니다. 기본값은 `null`이며 **민감한 데이터를 자동으로 가리지 않습니다**. 다음 예시는 알고 있는 값 하나만 치환합니다. 애플리케이션에 맞는 규칙을 구현하고 테스트하세요.

```php
namespace App\Support;

use Rankbeam\Seo\Pro\Ai\AiPromptFilter;

final class RedactAiContext implements AiPromptFilter
{
    public function filter(string $prompt): string
    {
        return str_replace('internal@example.com', '[redacted]', $prompt);
    }
}

// Configure seo-pro.ai.context_filter with this class in config/seo-pro.php.
// Runtime equivalent:
config(['seo-pro.ai.context_filter' => RedactAiContext::class]);
```

## 응답 처리 방식 {#how-replies-are-handled}

모든 호출은 제공자와 무관한 하나의 응답 봉투를 반환하므로 제공자 간에, 그리고 나중에 추가되는 제공자에서도 동작이 같습니다.

- **제공자가 지원하면 구조화된 출력을 사용합니다.** OpenAI(기본 Structured Outputs), Google(Gemini `responseSchema`), Anthropic(`output_config.format`)은 API가 JSON 형태를 강제합니다. 유효한 JSON이 아닌 응답은 명확한 실패로 처리하며 텍스트를 긁어내어 사용하지 않습니다. 로컬/OpenAI 호환 서버에도 `response_format` 형식을 최선 노력 방식으로 요청합니다. 이 필드를 무시하는 서버라도 사용 가능한 텍스트를 반환하면 유연한 파싱을 대체 방식으로 적용합니다. 어느 경우든 완전한 목록이나 명확한 실패를 반환하며, 일부만 파싱한 응답은 반환하지 않습니다.
- **응답 잘림은 조치 가능한 명시적 오류입니다.** 출력 토큰 상한에서 응답이 잘리면 `seo-pro.ai.max_output_tokens` 값을 높이라고 안내하는 `truncated` 오류를 받습니다. 알림 없이 줄인 제목을 받지 않습니다. 이는 **추론/사고 모델**에서 가장 흔하며, 해당 모델에는 더 높은 `reasoning_min_output_tokens` 최소 한도를 자동 적용합니다.
- **일시적인 실패는 자동으로 재시도합니다.** `429` 속도 제한이나 `5xx` 오류는 상한이 있는 지수 백오프로 재시도합니다. `Retry-After` 헤더가 있으면 따르되 악의적인 값이 요청을 정지시키지 못하도록 제한합니다. **지속되는 원인이 명확한** 실패는 재시도하지 않습니다. 잘못된 키, 잘못된 요청, 너무 큰 페이로드, **시간 초과**, **크레딧/할당량 부족**이 해당합니다. 잔액 없는 계정을 재시도해 봐야 백오프 대기만 낭비합니다. `retry` 블록으로 재시도를 조정하거나 끌 수 있습니다. 끄려면 `max_attempts` 값을 `0`으로 설정하세요.
- **오류에는 유형이 있으며 민감한 내용을 제거합니다.** 각 실패에는 안정적인 코드(`unauthorized`, `quota_exceeded`, `rate_limited`, `timeout`, `content_too_large`, `bad_request`, `truncated`, `content_filtered`, `provider_error` 등)가 포함되며, 일시적인 실패에는 `retryable` 플래그가 있습니다. 메시지는 민감한 내용을 제거한 짧은 문자열입니다. **일반 애플리케이션 경로에서는 제공자의 원시 응답 본문을 표시하거나 기록하지 않습니다. 위에서 설명한 선택적 평가 도구는 근거로 응답을 보존합니다.** Filament에서는 스타일이 적용된 모달 부분 템플릿에 실패를 표시하고, 흔한 실패 유형에 맞는 다음 단계 안내를 제공합니다([문제 해결](#troubleshooting) 참조).

## 문제 해결 {#troubleshooting}

모든 실패는 화면 안에 표시되며 핵심 작업을 중단하지 않습니다. 유형 코드와 민감한 내용을 제거한 메시지를 포함합니다. 흔한 실패와 직접적인 해결 방법은 다음과 같습니다.

| 증상(오류 코드) | 의미 | 해결 방법 |
|---|---|---|
| **`quota_exceeded`** — *"제공자 계정의 크레딧 또는 할당량이 부족합니다"* | 키는 유효하지만 **API 계정에 크레딧/할당량이 없습니다**. 속도 제한이 아니므로 재시도해도 해결되지 않습니다. 제공자별 원문은 Anthropic의 *"credit balance is too low"*, OpenAI의 *"exceeded your current quota… check your plan and billing"*(`insufficient_quota`), Google의 *"prepayment credits are depleted"*입니다. | 제공자 콘솔에서 크레딧을 추가하거나 결제를 활성화하세요. 또는 제공자 API 요금이 없는 **로컬** 모델로 전환하세요. Claude/ChatGPT **구독**은 **API** 사용료를 충당하지 않습니다. |
| **`unauthorized`** — *"인증에 실패했습니다"* | 키가 없거나 잘못되었거나 설정한 제공자에 유효하지 않습니다. | `seo-pro.ai.api_key_env` 설정이 지정한 환경 변수(기본 `SEO_PRO_AI_API_KEY`)의 키가 설정되어 있고 현재 유효하며 `SEO_PRO_AI_PROVIDER` 제공자와 일치하는지 확인하세요. |
| **`rate_limited`** — *"제공자의 요청 속도 제한에 도달했습니다"* | 실제로 발생한 **일시적인** 속도 제한입니다. 먼저 자동 재시도합니다. | 기다린 뒤 다시 시도하거나 서버 용량을 고려해 **로컬** 모델로 옮기세요. 낮은 등급에서 일괄 실행할 때는 `seo-pro.ai.fill.throttle_ms` 값을 높이세요. |
| **`timeout`** — *"요청 시간이 초과되었습니다"* | 제공자가 `seo-pro.ai.timeout` 제한(기본 15초) 안에 응답하지 않았습니다. **느린 로컬 추론 모델**에서 흔합니다. | `SEO_PRO_AI_TIMEOUT` 설정으로 제한을 늘리세요. Ollama에서는 `seo-pro.ai.local.extra_body` 설정의 `['think' => false]` 값도 지정하세요. |
| **`truncated`** — *"max_output_tokens 제한에 도달했습니다"* | 숨겨진 추론을 포함할 수 있는 출력 한도에 응답이 도달했습니다. | `seo-pro.ai.max_output_tokens` 값을 높이거나(추론 모델은 2000 이상이 필요할 수 있음), 모델이 `reasoning_models` 패턴에 일치하여 최소 한도가 적용되는지 확인하세요. |
| **`content_too_large`**(HTTP 413) | 전송한 페이지 콘텐츠가 제공자 제한을 초과했습니다. | `seo-pro.ai.max_input_chars` 값을 낮춰 더 짧은 발췌문을 보내세요. |
| **`bad_request`** | 잘못된 요청입니다. 주로 계정이 접근할 수 없는 **모델 이름** 또는 지원하지 않는 매개변수가 원인입니다. | `SEO_PRO_AI_MODEL` 값이 설정한 제공자에서 해당 키/서버로 접근할 수 있는 모델인지 확인하세요. |
| **`content_filtered`** | 제공자의 안전 필터가 응답을 거부했습니다. | 콘텐츠와 제공자의 안내를 검토하세요. 거부된 요청을 자동으로 반복하지 마세요. |

::: tip 로컬 추론에도 정상 동작하는 서버가 필요합니다
자체 호스팅 추론에 `SEO_PRO_AI_PROVIDER=local` 설정을 사용하면 클라우드 제공자의 크레딧 문제를 피할 수 있습니다. 하드웨어, 모델, API 호환성, 시간 제한과 용량은 여전히 중요합니다. 이 어댑터로 설정한 원격 게이트웨이는 키와 결제를 요구할 수 있습니다.
:::

## 서버 밖으로 전송되는 정보 {#what-leaves-your-server}

명시적인 작업(작업 버튼 클릭 또는 명령 호출)을 수행할 때만, 설정한 제공자에게만 다음 정보를 보냅니다.

- *제안*: 모델의 클래스 기본 이름과 키(예: "Post #3"), 현재 해석된 제목과 설명, 표준 URL, `max_input_chars` 제한(기본 6000자)을 적용한 일반 텍스트 콘텐츠 발췌문(HTML 제거).
- *문제 설명*: 문제 유형, 심각도, 필드, 메시지와 대상 URL. 모델을 사용할 수 있으면 해당 모델의 클래스/키, 해석된 제목/설명, 표준 URL과 길이가 제한된 일반 텍스트 콘텐츠 발췌문도 포함합니다.
- *설명 다시 쓰기*: 제안과 같은 최소 페이지 컨텍스트와, 전달한 경우 스캔 문제의 유형 및 메시지.
- *구조화 데이터 제안*: 제안과 같은 최소 페이지 컨텍스트. 모델은 유형과 말단 필드 값만 반환하고 JSON-LD는 로컬에서 조립합니다.

패키지는 방문자 데이터, IP 주소, 요청 헤더 또는 자격 증명을 의도적으로 프롬프트에 수집하지 않으며 전체 HTML을 보내지 않습니다. **콘텐츠 필드와 발췌문 자체에 민감한 정보가 들어 있을 수 있습니다.** 애플리케이션이 노출하는 내용을 검토하세요. 제공자 자격 증명은 요청 인증에 사용합니다. 데이터 처리에 관한 기준 문서는 Pro 저장소의 SECURITY.md입니다.

---
description: "Projděte skutečný sken Rankbeamu Pro, zkontrolujte chybějící popis, uložte opravu ve Filamentu, zopakujte sken a stáhněte vygenerovaný ukázkový report PDF."
---

# Od skenu k ověřené opravě {#from-a-scan-to-a-verified-fix}

Sken našel u ukázkového článku chybějící popis. Doplnili jsme jej ve Filamentu, zopakovali sken a vygenerovali report zobrazující opravu.

Snímky pocházejí z běžícího místního dema Merchant z 9. září 2026. Obsah tvoří ukázková data vložená seederem; oba skeny i report vznikly pro tohoto průvodce. Žádný historický trend nebyl předvyplněn. Aplikace používá Laravel 12 a Filament 4 spolu s Core Rankbeamu, bezplatným editorem a jádrem Pro.

**[Stáhnout vygenerovaný report (PDF, 98 KB)](/pro-walkthrough/merchant-demo-report.pdf)**

## Skenování registrovaných stránek {#scan-the-registered-pages}

Po [instalaci Pro](/cs/pro/installation) a registraci cílů skenování spusťte:

```bash
php artisan seo-pro:scan --sync
```

Demo registruje 18 obsahových záznamů a tři trasy. První sken dokončil všech 21 cílů bez selhání a našel 20 problémů: šest upozornění a 14 oznámení.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-before.png"><img src="/pro-walkthrough/scan-before.png" alt="První dokončený sken: 21 cílů, 20 problémů, šest upozornění a 14 oznámení." width="1792" height="1368" loading="lazy" decoding="async"></a></figure>

*Snímky jsou pořízené v rozlišení 2×. Otevřete je pro zobrazení v plné velikosti.*

## Prohlédnutí jednoho problému {#inspect-one-issue}

V přehledu **SEO Dashboard** otevřete u příslušného řádku seznam problémů **Page issues**. U článku „Behind the Scenes: Our Product Photography“ nález ukazuje chybějící `description`, URL stránky a sken, který problém odhalil.

<figure class="rb-capture"><a href="/pro-walkthrough/issue-description.png"><img src="/pro-walkthrough/issue-description.png" alt="Dialog Page issues identifikuje příspěvek Post 5, jeho URL a chybějící pole popisu." width="1792" height="590" loading="lazy" decoding="async"></a></figure>

## Uložení popisu {#save-the-description}

Otevřete článek v sekci příspěvků **Posts**, vyplňte popis SEO v poli **SEO description** a uložte jej. [Bezplatný editor Filament](/cs/guide/filament) zobrazí zadaný text v náhledu vyhledávání a označí jeho zdroj jako **Manual**, tedy ruční hodnotu. V tomto příkladu má popis 142 znaků; titulek stále pochází z článku.

<div class="rb-capture-pair">
<figure class="rb-capture"><a href="/pro-walkthrough/editor-description.png"><img src="/pro-walkthrough/editor-description.png" alt="Uložený popis SEO a počítadlo s hodnotou 142 znaků." width="1164" height="520" loading="lazy" decoding="async"></a></figure>
<figure class="rb-capture"><a href="/pro-walkthrough/editor-preview.png"><img src="/pro-walkthrough/editor-preview.png" alt="Živý náhled používá zadaný popis označený jako Manual, tedy ruční hodnota." width="812" height="940" loading="lazy" decoding="async"></a></figure>
</div>

Uložení pole a ověření opravy jsou samostatné kroky. Skóre se aktualizuje po příštím skenu. Bez Filamentu uložte stejnou hodnotu pomocí metody `saveSEO()` modelu.

## Nový sken a kontrola změn {#rescan-and-check-what-changed}

Spusťte stejný příkaz znovu:

```bash
php artisan seo-pro:scan --sync
```

Přehled nyní označuje tento konkrétní problém jako **Fixed**, tedy opravený. Ostatních 19 problémů zůstává otevřených.

<figure class="rb-capture"><a href="/pro-walkthrough/scan-delta.png"><picture><source media="(max-width: 600px)" srcset="/pro-walkthrough/scan-delta-mobile.png"><img src="/pro-walkthrough/scan-delta.png" alt="Zaznamenané porovnání skenů: žádný nový problém ani regrese, jeden opravený a 19 stále otevřených." width="2112" height="582" loading="lazy" decoding="async"></picture></a></figure>

| Kontrola | Před | Po |
|---|---|---|
| Dokončené cíle | 21 | 21 |
| Otevřené problémy | 20 | 19 |
| Upozornění | 6 | 5 |
| Oznámení | 14 | 14 |
| Průměrné technické skóre SEO | 92 | 93 |

[Skóre](/cs/pro/scoring) odráží technické kontroly Rankbeamu. Neměří návštěvnost, pozici ve vyhledávání ani zařazení do odpovědí AI. Úspěšná kontrola popisu také nezaručuje, že vyhledávač tento popis zobrazí.

## Vygenerování reportu {#generate-the-report}

Pro tuto ukázku jsme vygenerovali výchozí report **před** úpravou článku a druhý po opakovaném skenu:

```bash
# After the first scan, before making changes:
php artisan seo-pro:report --output=storage/app/seo-reports/baseline.pdf

# After saving the fix and rescanning:
php artisan seo-pro:report --output=storage/app/seo-reports/after-fix.pdf
```

Druhý PDF report ukazuje **jeden opravený**, **žádný nový** a **19 otevřených** problémů. Trend obsahuje pouze dva výše uvedené skeny. Search Console i záznam návštěv AI robotů byly vypnuté, proto příslušné části uvádějí, že data nejsou dostupná.

[![První stránka vygenerovaného ukázkového reportu: skóre 93, jeden opravený problém a 19 otevřených.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

První report stanoví výchozí stav pro porovnání. Pokud vygenerujete jediný report až po opravě stránky, nemůže ukázat změnu vůči dřívějšímu reportu. Pro náhled, který nemá tento výchozí stav posunout, použijte `--no-store`.

Ukázka používá renderer Browsershot. Požadavky rendererů, vizuální identitu a plánované doručování popisují [reporty s vlastní značkou](/cs/pro/reports).

## Vyzkoušejte to ve vlastní aplikaci {#run-it-on-your-own-app}

Začněte [Instalací Pro](/cs/pro/installation) a potom oskenujte stránku, jejíž výstup můžete ověřit. Pro funguje také [bez Filamentu](/cs/pro/headless). Pokud chcete nejprve vyzkoušet bezplatné vykreslování metadat, použijte [demo v Dockeru](/cs/guide/demo).

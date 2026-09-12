---
description: "Spusťte připravenou ukázkovou aplikaci Rankbeam jedním příkazem: vydané balíčky bez path repozitářů, vykreslená metadata, graf JSON-LD a mapa webu na skutečných stránkách."
---

# Spuštění ukázky {#run-the-demo}

Nejrychleji uvidíte Rankbeam na skutečných stránkách prostřednictvím spustitelné ukázky, bez předchozího zapojování do vlastní aplikace. Jde o aplikaci Laravelu s připravenými daty, která instaluje **vydané** balíčky — bez path repozitářů a sousedních checkoutů — a vykresluje několik stránek s úplnými SEO metadaty, grafem JSON-LD a mapou webu. Po přidání licence spustí i [technický SEO audit](/cs/pro/scan-issues) Pro.

## Jeden příkaz (bezplatné jádro) {#one-command-free-core}

Ukázka je distribuovaná jako obraz Dockeru v repozitáři
[`rankbeam-examples`](https://github.com/rankbeam/rankbeam-examples):

```bash
git clone https://github.com/rankbeam/rankbeam-examples
cd rankbeam-examples/demo
docker compose up --build
```

Otevřete `http://localhost:8080`. Ve zdrojovém kódu libovolné stránky uvidíte vyhodnocený `<head>`. Na `/sitemap.xml` najdete vygenerovanou mapu webu. Vše používá bezplatné jádro MIT nainstalované z Packagistu.

## S Pro (audit) {#with-pro-the-audit}

Pro má licenci podle projektů a instaluje se ze soukromého repozitáře Composer. Předejte licenci přes `COMPOSER_AUTH` — tajný údaj při sestavení, který se nikdy nezapisuje do vrstvy obrazu — a sestavte s příznakem Pro:

```bash
export COMPOSER_AUTH='{"http-basic":{"blog.rankbeam.dev":{"username":"you@example.com","password":"YOUR-LICENSE-KEY"}}}'
WITH_PRO=true docker compose up --build
```

Při spuštění ukázka provede [`seo:doctor`](/cs/pro/headless#setup-health-check) a první `seo-pro:scan` nad připravenými stránkami. Přehled stavu, souhrn skenování a [skóre 0–100](/cs/pro/scoring) se vypíší do záznamů Compose.

## Prohlédněte si pracovní postup Pro {#see-the-pro-workflow}

[Průvodce skenování → oprava → report](/cs/pro/walkthrough) ukazuje spuštěnou ukázku Merchant: skutečný sken, podrobnosti problému, uložený popis ve Filamentu, opakovaný sken a PDF ke stažení. Obsah je označený jako ukázková data a výsledky před a po pocházejí ze dvou nových skenů.

Veřejná interaktivní hostovaná ukázka zatím neexistuje. Engine spusťte místně pomocí Dockeru. [README ukázky](https://github.com/rankbeam/rankbeam-examples/tree/main/demo) popisuje nastavení i přepínání mezi vydanými a místními balíčky.

::: tip Už máte aplikaci?
Přejděte rovnou na [rychlý start](/cs/guide/quickstart): od instalace k úplně vykreslenému `<head>` za pět minut.
:::

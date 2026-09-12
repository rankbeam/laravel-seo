---
description: "Navažte indexovatelnost na prostředí Laravelu, aby staging ani místní kopie nepronikly do vyhledávání — mimo povolená prostředí se vynutí noindex a zablokují roboti."
---

# Ochrana před indexací (pojistka pro neprodukční prostředí) {#indexing-guard-non-production-safety-net}

Stagingová nebo místní kopie webu, která se dostane do Googlu, patří mezi nejčastější a nejškodlivější chyby SEO: duplicitní obsah soupeří se skutečnými stránkami, soukromé prostředí je v indexu a úklid pomocí nástroje pro odstranění URL trvá týdny. Typickou příčinou je `noindex` závislé jen na zapomenuté hodnotě v `.env` nebo pravidlo pro roboty přepsané při nasazení.

**Ochrana před indexací** tomuto scénáři předchází už svým návrhem. Indexovatelnost váže na *prostředí* Laravelu místo příznaku, na který musí někdo pamatovat: pokud aplikace běží mimo seznam povolených prostředí, každá stránka dostane vynucené `noindex,nofollow`, spravovaný `robots.txt` zablokuje všechny roboty a `seo:audit` na tento stav výrazně upozorní.

Jde o bezplatnou funkci balíčku Core.

## Co se děje při aktivní ochraně {#what-it-does-when-active}

Pokud `app()->environment()` **není** v `seo.indexing_guard.allowed_environments` a ochrana je zapnutá, automaticky nastanou čtyři věci:

1. **Resolver vynutí `noindex,nofollow` na každé stránce.** Toto pravidlo stojí *nad* celým [řetězcem priorit](/cs/concepts/resolver-precedence) a přepíše i výslovnou hodnotu `robots` uloženou pro konkrétní stránku v `seo_meta`.
2. **HTTP hlavička `X-Robots-Tag: noindex,nofollow`** se odešle s každou odpovědí procházející aplikací — viz níže [Odpovědi mimo HTML](#non-html-responses-pdfs-feeds-images).
3. **`SEO::robotsTxt()->build()` vytvoří `robots.txt` zakazující vše** (a také `ai.txt`): prosté `User-agent: *` / `Disallow: /`. Platí to pro příkaz `seo:robots-txt` i volitelnou [dynamickou trasu](/cs/guide/ai-crawlers).
4. **`seo:audit` zobrazí výrazné upozornění**, aby vás při čtení reportu nepřekvapilo, že je všude noindex.

V povolených prostředích (ve výchozím nastavení `production`) je ochrana zcela **nečinná**: výstup se nemění a vykreslení zůstává totožné bajt po bajtu.

## Odpovědi mimo HTML (PDF, kanály, obrázky) {#non-html-responses-pdfs-feeds-images}

Vynucená **meta značka** `robots` se dostane jen k robotům, kteří zpracovávají HTML. PDF, kanál RSS/Atom, obrázek ani jiná odpověď mimo HTML žádný `<head>` nemají. Aktivní ochrana proto prostřednictvím globálního middlewaru odesílá stejnou direktivu také v HTTP hlavičce:

```http
X-Robots-Tag: noindex,nofollow
```

Hlavička i meta značka vycházejí ze stejného zdroje, takže si nemohou odporovat. Hlavička je **v rámci ochrany ve výchozím nastavení zapnutá** (samotná ochrana vyžaduje zapnutí a v povolených prostředích je nečinná). Pokud chcete ponechat pouze meta značku, vypněte ji:

```php
'indexing_guard' => [
    'send_header' => env('SEO_INDEXING_GUARD_HEADER', true),
],
```

Middleware se registruje **jen při zapnuté ochraně**. S vypnutou ochranou tedy balíček do zásobníku middlewaru nic nepřidává.

::: warning Statické soubory obcházejí PHP
Soubor, který webový server vrací přímo z `public/`, do Laravelu vůbec nevstoupí, a tuto hlavičku proto nedostane. Takové soubory chraňte na okraji infrastruktury (konfigurací webového serveru nebo CDN). Ochrana pokrývá vše, co prochází aplikací.
:::

## Proč přepisuje výslovnou hodnotu robots {#why-it-overrides-an-explicit-robots-value}

V ostatních částech Rankbeamu vyhrává výslovně uložená hodnota — právě k tomu slouží řetězec priorit. Ochrana je jedinou záměrnou výjimkou a stojí *nad* vrstvou výslovných hodnot, protože riziko zde existuje jen jedním směrem:

- Stagingová databáze bývá kopií produkční databáze. Stránka s uloženým `index,follow` by si proto tuto direktivu přinesla i na staging a požádala o indexaci.
- **Chybná indexace stagingu je vážný problém; chybné nastavení `noindex` na stagingu nic nezmění.** Ochrana tak tvoří nepřekročitelnou hranici pro uložené hodnoty právě v prostředích, která indexovat nechcete.

## Zapnutí {#enabling-it}

Ochrana je po instalaci **vypnutá**. Instalace ani aktualizace balíčku tedy bez vašeho souhlasu nezmění výstup neprodukčního prostředí. Platí stejná zásada zachování bajtově totožného výstupu až do výslovného zapnutí jako u [`blank_is_unset`](/cs/concepts/resolver-precedence) v resolveru a generovaných OG obrázků. Zapnete ji jedním řádkem:

```dotenv
SEO_INDEXING_GUARD=true
```

S výchozím seznamem povolených prostředí zůstává `production` beze změny, takže ochranu můžete ponechat zapnutou ve sdílené konfiguraci. Ověřte, že seznam obsahuje všechna prostředí, která chcete indexovat. Zapnutí **důrazně doporučujeme**; pro Core 4 se zvažuje zapnutí ve výchozím nastavení.

Vypnete ji opět jedním řádkem:

```dotenv
SEO_INDEXING_GUARD=false
```

## Výběr prostředí, která se smějí indexovat {#choosing-which-environments-may-index}

Ve výchozím nastavení je povoleno pouze `production`. Seznam přepíšete proměnnou prostředí s hodnotami oddělenými čárkami:

```dotenv
# Let a public preview environment index too
SEO_INDEXING_GUARD_ALLOWED="production,prod-eu"
```

Nebo v `config/seo.php`:

```php
'indexing_guard' => [
    'enabled' => env('SEO_INDEXING_GUARD', false),
    'allowed_environments' => ['production', 'prod-eu'],
],
```

Položky se porovnávají pomocí `Str::is()`, takže fungují i **zástupné znaky**: `'prod*'` odpovídá `production` i `prod-eu`:

```php
'allowed_environments' => ['prod*'],
```

**Prázdný** seznam znamená, že se nesmí indexovat *žádné* prostředí — ochrana je aktivní všude, tedy v bezpečnějším směru. Pozor: prázdná hodnota proměnné `SEO_INDEXING_GUARD_ALLOWED`, včetně samotných mezer, se nahradí hodnotou `['production']`, aby překlep nemohl nepozorovaně vyřadit produkci z indexu. Pokud opravdu chcete ochranu „všude“, zapište do konfigurace výslovně `[]`.

## Ověření {#verifying-it}

`seo:audit` zobrazí upozornění a ve výstupu `--json` uvede strojově čitelný stav:

```bash
php artisan seo:audit --json
```

```json
{
    "indexing_guard": {
        "active": true,
        "environment": "staging",
        "allowed_environments": ["production"],
        "directive": "noindex,nofollow"
    },
    "pages": [ /* ... */ ]
}
```

A takto vypadá obsluhovaný nebo vygenerovaný `robots.txt` v chráněném prostředí:

```
# robots.txt — managed by Rankbeam
# Indexing guard ACTIVE: this app is running in the "staging" environment,
# which is not in seo.indexing_guard.allowed_environments. Every crawler is
# disallowed so this non-production site stays out of search results.
# https://rankbeam.dev/guide/indexing-guard

User-agent: *
Disallow: /
```

## Rozsah {#scope}

Ochrana řídí **direktivy pro indexaci**: meta značku `robots`, hlavičku `X-Robots-Tag` a `robots.txt`. Nemění titulky, popisy, kanonické URL ani schéma. Je nezávislá na [zásadách vykreslování robots](/cs/concepts/resolver-precedence) (`seo.robots.emit_default`): protože se `noindex,nofollow` liší od výchozí hodnoty webu, značka se vykreslí vždy.

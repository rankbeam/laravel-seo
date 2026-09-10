---
description: "Audit-Meldungen, Editorhinweise und Filament-Beschriftungen folgen der App-Locale. Veröffentliche die Sprachdateien, um Texte anzupassen, oder steuere eine Sprache bei."
---

# Übersetzungen {#translations}

Die für Nutzer bestimmten Texte der Pakete sind Laravel-Spracheinträge: Audit-Meldungen, Live-Hinweise unter Filament-Feldern, Beschriftungen, Vorschauen und Berichte. Die Pakete folgen `app()->getLocale()`. Ein italienisch eingestelltes Panel zeigt Italienisch, ohne zusätzliche Einrichtung.

Problem- und Warnungs**codes** wie `missing_title` oder `title_too_long` ändern sich nie und werden nicht übersetzt. Nur der zugehörige lesbare Satz wird übersetzt.

Mitgeliefert werden Englisch, Italienisch mit bereits geprüften älteren Texten und erneut zu prüfenden Änderungen sowie erste Fassungen in Deutsch, Französisch, Spanisch, brasilianischem Portugiesisch, Niederländisch, Türkisch, Russisch und Polnisch (Tier 1). Seit Core 3.16, Filament 1.10 und Pro 2.35 kommen Japanisch, vereinfachtes Chinesisch (`zh_CN`), traditionelles Chinesisch (`zh_TW`), Koreanisch, Griechisch, Ukrainisch und Tschechisch hinzu (Tier 2). Den genauen Stand pro Locale enthält [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md). Erst eine muttersprachliche Prüfung macht aus einer ersten Fassung eine entsprechend geprüfte Sprache.

## Einen Text überschreiben {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Bearbeite anschließend `lang/vendor/seo/{locale}/seo.php` und die benachbarten Verzeichnisse der anderen Pakete. Die enthaltenen Schlüssel überschreiben die Paketwerte. Für alle übrigen greift zuerst die Paketdatei, dann Englisch.

## Eine Sprache beisteuern {#contribute-a-language}

Kopiere die Datei `en` in deine Locale, übersetze die Werte und erhalte jeden `:placeholder`. Führe die Tests aus und öffne einen Pull Request. Der Paritätstest schlägt bei fehlenden oder zusätzlichen Schlüsseln, leeren Werten und verlorenen Platzhaltern fehl. Vollständige Regeln und Glossar stehen in [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md).

## Was absichtlich nicht übersetzt wird {#what-is-not-translated-on-purpose}

Die CLI-Ausgabe verwendet standardmäßig Englisch. Setze `seo.cli_locale` beziehungsweise `SEO_CLI_LOCALE` oder übergib `--display-locale=it`, um unterstützte Meldungen und Audit-Zusammenfassungen zu übersetzen. Pro besitzt eine eigene Einstellung `seo-pro.cli_locale`. Die Anzeigesprache ist unabhängig von der mit `--locale` gewählten Inhalts-Locale.

- Befehlshilfe, Wartungsdiagnosen und die Ausgabe von `seo:explain` bleiben Englisch; PASS/WARN/FAIL bleiben stabile Bezeichnungen.
- Gerendertes HTML, etwa `<meta>` und JSON-LD, verwendet die Sprache deiner Inhalte, nicht die Paketsprache.
- Problemcodes, JSON-Schlüssel und Statuscodes bleiben stabile Kennungen. Lesbare Beschriftungen in `--json` können übersetzt werden; Integrationen sollten Schlüssel und Codes auswerten.

## Die andere Seite: die Sprache deiner Inhalte {#the-other-half-your-content-s-language}

Diese Seite behandelt die Sprache des *Pakets*. Wie es die Sprache deiner *Inhalte* verarbeitet, erklärt [Mehrsprachige Inhalte](/de/guide/multilingual): Titelbudgets je Schriftsystem, Kürzung, Groß-/Kleinschreibung, Hreflang-Regeln, `inLanguage`, regionale Suchmaschinen und Schriftarten für OG-Bilder.

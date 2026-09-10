---
description: "Los resultados de auditoría, avisos del editor y etiquetas Filament de Rankbeam siguen el idioma de la aplicación. Publica los archivos de idioma para personalizarlos."
---

# Traducciones {#translations}

Los textos dirigidos al usuario que emiten los paquetes —resultados de auditoría, avisos bajo los campos Filament, etiquetas, vistas previas e informes— son líneas de idioma de Laravel. Los paquetes siguen `app()->getLocale()`: un panel en italiano muestra italiano sin configuración adicional.

Los **códigos** de problemas y avisos (`missing_title`, `title_too_long`, etc.) no cambian ni se traducen. Solo se traduce el texto que acompaña al código.

Se incluyen inglés, italiano —los textos anteriores están revisados; los modificados requieren nueva revisión— y primeras versiones en alemán, francés, español, portugués brasileño, neerlandés, turco, ruso y polaco (nivel 1). Desde core 3.16 / Filament 1.10 / Pro 2.35 también se incluyen japonés, chino simplificado (`zh_CN`), chino tradicional (`zh_TW`), coreano, griego, ucraniano y checo (nivel 2). El estado exacto de cada idioma figura en [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md); la revisión nativa es lo que convierte una primera versión en un idioma con soporte.

## Personalizar un texto {#override-a-string}

```bash
php artisan vendor:publish --tag=seo-lang            # core (3.13+)
php artisan vendor:publish --tag=seo-filament-lang   # Filament fields (1.6+)
php artisan vendor:publish --tag=seo-pro-lang        # Pro (2.31+)
```

Después edita `lang/vendor/seo/{locale}/seo.php` y las carpetas correspondientes a los demás paquetes. Las claves que mantengas sustituyen los textos del paquete; las restantes usan el archivo del paquete y después el inglés como respaldo.

## Contribuir un idioma {#contribute-a-language}

Copia el archivo `en` al idioma elegido, traduce los valores, conserva cada `:placeholder`, ejecuta la suite y abre una pull request. La prueba de paridad falla si falta una clave, sobra otra, hay un valor vacío o se pierde un marcador. Las reglas completas y el glosario están en [TRANSLATING.md](https://github.com/rankbeam/laravel-seo/blob/master/TRANSLATING.md).

## Qué se mantiene sin traducir {#what-is-not-translated-on-purpose}

La presentación de CLI utiliza inglés por defecto. Configura `seo.cli_locale` / `SEO_CLI_LOCALE`, o pasa `--display-locale=it`, para traducir los mensajes compatibles y los resúmenes de auditoría. Pro tiene su propia opción `seo-pro.cli_locale`. El idioma de presentación es independiente del idioma del contenido seleccionado con `--locale`.

- La ayuda de los comandos, los diagnósticos de mantenimiento y la salida de `seo:explain` siguen en inglés; las etiquetas PASS/WARN/FAIL permanecen estables.
- El HTML generado (`<meta>`, JSON-LD) utiliza el idioma de tu contenido, nunca el del paquete.
- Los códigos de problemas, las claves JSON y los códigos de estado son identificadores estables. Las etiquetas legibles de la salida `--json` pueden traducirse; las integraciones deben usar claves y códigos.

## La otra parte: el idioma de tu contenido {#the-other-half-your-content-s-language}

Esta página trata del idioma que utiliza el *paquete*. Para saber cómo interpreta el idioma de tu *contenido* —límites de títulos por sistema de escritura, truncamiento, mayúsculas, políticas hreflang, `inLanguage`, buscadores regionales y fuentes de imágenes OG—, consulta [Contenido multilingüe](/es/guide/multilingual).

---
description: "Informe PDF con tu marca: puntuación, tendencias, problemas nuevos y resueltos, 404 recuperados, Search Console y bots de IA. Generación por comando y correo programado opcional."
---

# Informes con marca propia {#white-label-reports}

Un **informe PDF de un sitio** con puntuación global, tendencia de problemas, cambios desde el informe anterior, 404 y enlaces recuperados, Search Console y actividad de bots. Se genera con un comando y puede enviarse por correo según una programación. Añade logo, color y la etiqueta «preparado para» del cliente.

[Descarga un informe generado en inglés: PDF, 98 KB](/pro-walkthrough/merchant-demo-report.pdf) o sigue el [recorrido scan → corrección → informe](/es/pro/walkthrough). El ejemplo usa contenido Merchant de demostración y dos scans nuevos: un problema resuelto, 19 abiertos y sin datos Search Console.

[![Primera página del informe Merchant original en inglés.](/pro-walkthrough/report-preview.png)](/pro-walkthrough/merchant-demo-report.pdf)

## Contenido {#what-s-in-it}

- **Puntuación global**: media de los últimos resultados por página según las [reglas publicadas](/es/pro/scoring), A ≥ 90 … F, cambio desde el informe anterior y tendencia de scans recientes. Cada ejecución guarda la puntuación del sitio. La tendencia empieza con el primer scan posterior a la actualización; las ejecuciones antiguas sin puntuación se omiten.
- **Problemas por scan**: tendencia real de scans completados; menos problemas supone una reducción de hallazgos.
- **Problemas resueltos y nuevos** desde el informe anterior. Usa el [historial de resolución/reapertura](/es/pro/scan-issues#issue-lifecycle) cuando cubre un período completo, o la instantánea anterior como respaldo.
- **Recuperaciones**: enlaces rotos resueltos, 404 cuya ruta vuelve a responder 200 por sí misma —[`seo-pro:404-recheck`](/es/pro/production#scheduler)— y 404 redirigidos, además de los abiertos. Un 404 recuperado es una corrección en el origen y se cuenta por separado de una redirección.
- **Search Console**: consultas y páginas principales y mayores cambios de clics. Se omite de forma explícita si GSC no está configurado.
- **Actividad de bots IA**: peticiones atribuidas por user-agent, no identidad verificada; totales históricos y, si las [agrupaciones diarias](/es/pro/ai-bot-monitor#period-metrics-daily-buckets) cubren la ventana, peticiones y URL distintas reales del período. Si no, se usa la diferencia entre instantáneas de totales.

## Desde el informe anterior {#since-the-last-report}

La comparación es contra el **informe previo**, no una fecha arbitraria. Cada generación guarda una instantánea ligera en `seo_report_runs`: puntuación, identidades de problemas abiertos, filas Search Console y contadores por bot. El siguiente informe compara el estado actual con esa instantánea.

Esto da historial a señales que solo conservan el estado más reciente, como las puntuaciones por página. Cuando hay historial propio, el informe lo prefiere: ciclo de vida de problemas, [métricas diarias GSC](/es/pro/search-console#historical-metrics) y [agrupaciones diarias de bots](/es/pro/ai-bot-monitor#period-metrics-daily-buckets). Cada señal vuelve a la diferencia de instantáneas si el historial no cubre el período o en el primer informe tras actualizar.

Dos consecuencias:

- **El primer informe es la referencia inicial.** Muestra el estado actual. Los cambios, problemas nuevos/resueltos y cifras «desde el anterior» aparecen desde el segundo.
- **Tú eliges la frecuencia.** Informes mensuales comparan meses; semanales comparan semanas. Usa `--no-store` para una vista previa puntual que no deba modificar la referencia.

## Generar un informe {#generate-a-report}

```bash
php artisan seo-pro:report
```

Sin opciones, escribe el PDF en `storage/app/seo-reports/`. Puedes elegir otro destino o enviarlo:

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

### Opciones {#options}

| Opción | Efecto |
| --- | --- |
| `--client=` | Sustituye la etiqueta del cliente destinatario |
| `--agency=` | Sustituye el nombre de agencia |
| `--accent=` | Sustituye el color de acento, hexadecimal como `#3D5AFE` |
| `--logo=` | Sustituye la ruta del logo |
| `--email=` | Destinatario repetible; envía el informe por correo |
| `--send` | Envía a los destinatarios configurados |
| `--output=` | Archivo o directorio de destino |
| `--no-store` | No guarda instantánea ni avanza la referencia |
| `--json` | Resumen procesable por herramientas |

## Programar el correo {#schedule-the-e-mail}

El paquete no programa el informe automáticamente. Registra la frecuencia en `routes/console.php` o `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('seo-pro:report --send')->monthly();
```

Define una vez los destinatarios predeterminados en configuración o `.env`:

```dotenv
SEO_PRO_REPORT_RECIPIENTS="client@acme.com,pm@agency.com"
```

`--send` los utiliza; las opciones explícitas `--email` los sustituyen.

## Marca {#branding}

La marca no es secreta y se guarda en configuración para todos los informes. Puedes sustituir cualquier campo con las opciones del comando, por ejemplo si etiquetas informes para varios clientes.

```dotenv
SEO_PRO_REPORT_AGENCY="Blue Whale Studio"
SEO_PRO_REPORT_LOGO="/var/www/brand/logo.png"
SEO_PRO_REPORT_ACCENT="#3D5AFE"
SEO_PRO_REPORT_CLIENT="Acme Outdoor Co."
SEO_PRO_REPORT_CONTACT="hello@bluewhale.studio · bluewhale.studio"
SEO_PRO_REPORT_FOOTER="Confidential — prepared for Acme Outdoor Co."
```

Notas:

- **Logo**: ruta absoluta a `PNG`/`JPG`/`GIF`/`WEBP`/`SVG`. La aplicación lee el archivo y lo incrusta como URI de datos, de modo que el renderizador no necesita descargarlo. PNG o JPG son las opciones más compatibles.
- **Color**: se valida como literal hexadecimal. Un valor inválido usa el predeterminado; solo se trata como color, nunca como CSS sin procesar.
- **Agencia**: por defecto, `config('app.name')`.

El bloque completo está en `reports` de `config/seo-pro.php`, incluido `paper`, `a4` por defecto, `include_gsc` y los límites de ejecuciones de tendencia, filas GSC y bots.

## Un sitio por instalación {#one-site-per-install}

Pro analiza la aplicación en la que está instalado y el informe describe **esa instalación**. Una agencia con varios sitios genera un informe en cada uno; `--client` y las opciones de marca lo etiquetan. No existe un modelo multitenant de sitios.

## Cómo se genera {#how-it-s-built}

Por defecto usa **dompdf**, en PHP, sin Node ni Chromium, para ejecutar el informe desde un worker o cron sin esos binarios. El renderizador tiene desactivadas las descargas remotas y el logo se incrusta, de modo que un campo renderizado no puede iniciar una petición de recursos.

### Escrituras e idiomas con Browsershot {#reports-in-every-script-browsershot-renderer}

Desde core 3.20 / Pro 2.40, los renderizadores Chrome desactivan JavaScript y bloquean recursos HTTP(S), FTP y WebSocket. Las plantillas publicadas deben usar HTML/CSS estático con recursos incrustados. Estos controles afectan a los recursos de la página; Chrome sigue necesitando un host y sandbox bien configurados. Si Fontconfig detecta una escritura sin fuente, incluso minoritaria dentro de texto mixto, el renderizador registra un aviso con instrucciones. Chrome puede generar el PDF aunque falte la fuente: inspecciónalo antes de enviarlo.

dompdf usa la fuente incrustada DejaVu Sans, que cubre latino, cirílico y griego. Con japonés, tailandés o árabe pueden aparecer cuadros en lugar de caracteres. Desde Pro 2.34 puedes usar **Chrome sin interfaz** mediante `spatie/browsershot`, la misma dependencia que las imágenes OG del núcleo:

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

Chrome utiliza las fuentes instaladas en el servidor. La plantilla emplea la pila por escritura del núcleo: Noto Sans, familia Noto Sans CJK correspondiente al idioma primero, tailandés, árabe, hebreo, devanagari, emoji de color y DejaVu Sans para latino. Instala las familias necesarias con `apt install fonts-noto-cjk fonts-noto-core fonts-noto-color-emoji` en Debian/Ubuntu, igual que para [imágenes OG](/es/guide/multilingual#og-images-in-every-script). Los avisos de fuentes de `seo:og-images` también orientan los requisitos de los informes. Plantilla Blade, datos e instantánea son los mismos con ambos motores; solo cambia el renderizador. `ReportGenerator::renderer()` indica cuál está activo.

### Fechas y números según el idioma del lector {#dates-and-numbers-in-the-reader-s-locale}

El informe captura `seo-pro.reports.locale` al generarse; null usa el idioma de la aplicación. El idioma de traducción resuelto controla etiquetas PDF/correo, asunto predeterminado, fuentes y HTML `lang`. Los idiomas regionales sin archivo propio usan el idioma base incluido y después inglés. El chino simplificado `zh_CN` y tradicional `zh_TW` permanecen separados.

Con `ext-intl`, fechas y números siguen el idioma solicitado mediante ICU. `seo-pro.reports.format_locale` permite elegir un formato regional diferente: `locale=it` y `format_locale=en_US` producen etiquetas italianas con fechas y números estadounidenses. Sin `ext-intl`, se conserva el respaldo de fechas inglesas y números agrupados con comas.

El correo en cola conserva idioma, formato y asunto capturados aunque cambie la configuración del worker. Elige el idioma antes de generar el PDF: cambiar después el locale del mailable no traduce el adjunto. Los payloads antiguos, anteriores a Pro 2.39, usan la configuración del worker porque no capturaban esos ajustes. Asuntos personalizados, marca y mensajes guardados de problemas siguen siendo datos de origen.

La presentación CLI es independiente. `php artisan seo-pro:report --display-locale=it` traduce el resumen del comando; la configuración del informe selecciona el idioma del PDF/correo. CLI usa inglés por defecto y admite `SEO_PRO_CLI_LOCALE`. Las claves JSON y códigos permanecen estables; las etiquetas legibles pueden traducirse. Publica `seo-pro-lang` para personalizar mensajes en `lang/vendor/seo-pro/{locale}/seo-pro.php`.

Desde código, resuelve `ReportGenerator` mediante el contenedor:

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

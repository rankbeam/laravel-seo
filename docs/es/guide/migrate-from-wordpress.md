---
description: "Importa a tus modelos Laravel los títulos, descripciones, canonical, robots, palabras clave y metadatos sociales escritos en Yoast o Rank Math. Referencia de campos del importador."
---

# Migrar desde WordPress {#migrating-from-wordpress}

Si trasladas un sitio de contenido de WordPress a Laravel, Rankbeam puede importar los metadatos SEO que tu equipo escribió en Yoast o Rank Math: títulos, descripciones, canonical, directivas robots, palabras clave objetivo y valores sociales personalizados. Así puedes conservar ese trabajo durante la migración.

::: tip ¿Vas a realizar el cambio definitivo?
Esta página es la referencia del importador: correspondencia de campos, variables de plantilla y claves de origen. Para el procedimiento ordenado —coexistir, importar, verificar y retirar el sistema anterior—, sigue el [procedimiento de migración de WordPress](/es/guide/wordpress-migration-runbook).
:::

Hay dos vías, ambas mediante `seo:import-from`:

| Vía | Origen | Uso recomendado |
|---|---|---|
| [**CSV**](#_1-csv-import) `wordpress-csv` | Hoja de cálculo exportada de WordPress | La mayoría de las migraciones de agencia; controlas las URL exactas |
| [**Base de datos**](#_2-database-import-yoast-rank-math) `yoast` / `rank-math` | Base de datos de WordPress | Mayor fidelidad, incluidos valores OpenGraph/Twitter personalizados y redirecciones de Rank Math |

Ambas son **idempotentes**: al repetirlas actualizan las mismas filas sin crear duplicados. Admiten **`--dry-run`** y, por defecto, solo rellenan campos vacíos, conservando el SEO que ya hayas guardado en Rankbeam. Pasa **`--overwrite`** para sustituir los valores existentes por los importados.

## Cómo se convierten las filas de WordPress en filas de `seo_meta` {#how-wordpress-rows-become-seo-meta-rows}

Las filas de WordPress se identifican mediante una **URL** o un **ID de publicación**. `seo_meta` es polimórfico: cada fila pertenece a un modelo Eloquent real. El importador busca un modelo para cada fila de WordPress e informa de cuáles se vincularon y cuáles quedaron solo como URL:

- **Vinculada a un modelo.** Indica el modelo de destino con `--model="App\Models\Post"`. El **slug** de cada fila, obtenido del último segmento de la URL o de `post_name` en WordPress, se compara con la clave de ruta del modelo o con la columna indicada mediante `--match-by=`. Las coincidencias se escriben en `seo_meta`.
- **Solo URL.** Si una fila no coincide con ningún modelo, o ejecutas el comando sin `--model`, no puede convertirse en una fila `seo_meta` porque no tiene un modelo al que vincularse. Se informa como omitida con motivo `url-only`. Su canonical aún puede generar una [redirección candidata](#redirects).

Las entradas y páginas de WordPress suelen corresponder a modelos Laravel distintos. Ejecuta una importación por tipo de contenido y limita las filas:

```bash
php artisan seo:import-from yoast --model="App\Models\Post" --post-type=post
php artisan seo:import-from yoast --model="App\Models\Page" --post-type=page
```

::: warning Los tipos de contenido personalizados no se leen por defecto
Los lectores de base de datos recorren únicamente los tipos **`post`** y **`page`**. Si el sitio usa tipos personalizados, como `product`, `event` o `pathology`, debes indicarlos explícitamente repitiendo `--post-type=`:

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Pathology" \
  --post-type=pathology --post-type=clinic
```
:::

---

## 1. Importación CSV {#_1-csv-import}

La vía CSV cubre la mayoría de las migraciones de agencia. Exporta una fila por URL con esta cabecera. Las columnas pueden ir en cualquier orden; las desconocidas se ignoran y se incluyen en el informe:

```csv
url,title,description,canonical,robots,focus_keyword
https://oldsite.com/blog/my-post/,"My SEO Title","My meta description.",https://newsite.com/blog/my-post,"index, follow","laravel seo"
```

Ejecuta:

```bash
# Preview first — writes nothing
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post" \
  --dry-run

# Then import for real
php artisan seo:import-from wordpress-csv \
  --file=storage/migrations/seo-export.csv \
  --model="App\Models\Post"
```

| Columna | Destino en `seo_meta` | Detalles |
|---|---|---|
| `url` | Clave de correspondencia | Obligatoria. El slug, último segmento de ruta, se compara con el modelo. |
| `title` | `title` | Se recorta a 70 caracteres; los valores demasiado largos se notifican. |
| `description` | `description` | Se recorta a 160 caracteres. |
| `canonical` | `canonical` | También determina las [redirecciones candidatas](#redirects). |
| `robots` | `robots` | Se guarda tal cual, por ejemplo `noindex, nofollow`, y se recorta a 50 caracteres. |
| `focus_keyword` | `focus_keywords` | Valores separados por comas; el primero es la palabra clave principal. |

Las filas mal formadas se omiten y se cuentan: aquellas sin `url` o cuyo número de columnas no coincide con la cabecera.

---

## 2. Importación desde base de datos: Yoast y Rank Math {#_2-database-import-yoast-rank-math}

Si conservas la base de datos de WordPress, el importador puede leer directamente los metadatos SEO, incluidos los valores personalizados OpenGraph/Twitter y, para Rank Math, las redirecciones que suelen perderse en una exportación CSV.

### Configurar una conexión a WordPress {#point-a-connection-at-wordpress}

Añade la base de datos de WordPress como conexión en `config/database.php`:

```php
'connections' => [
    // ...
    'wordpress' => [
        'driver' => 'mysql',
        'host' => env('WP_DB_HOST', '127.0.0.1'),
        'database' => env('WP_DB_DATABASE', 'wordpress'),
        'username' => env('WP_DB_USERNAME'),
        'password' => env('WP_DB_PASSWORD'),
        'prefix' => '', // the table prefix is passed with --table=, see below
    ],
],
```

Después importa. El prefijo predeterminado es `wp_`; cámbialo con `--table=`:

```bash
# Yoast SEO
php artisan seo:import-from yoast \
  --connection=wordpress --model="App\Models\Post" --dry-run

# Rank Math
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" --table=wp_
```

El lector recorre `{prefix}posts`, toma las entradas y páginas publicadas y obtiene los metadatos del plugin desde `{prefix}postmeta`. Compara el slug `post_name` con tu modelo.

::: tip Prefijo de tablas personalizado
Los alojamientos WordPress gestionados suelen usar prefijos aleatorios, como `wppg_` en vez de `wp_`. Revisa los nombres de `CREATE TABLE` en el volcado y pasa el prefijo real, por ejemplo `--table=wppg_`, para localizar `{prefix}posts` y `{prefix}postmeta`.
:::

::: tip Leer un volcado restaurado en MySQL 8
Si restauras un volcado de WordPress en MySQL 8 o superior para leerlo localmente, flexibiliza el modo SQL estricto antes de cargar el `.sql`. Los valores de fecha predeterminados `'0000-00-00'` de WordPress no son aceptados por los modos predeterminados `STRICT`/`NO_ZERO_DATE` de MySQL 8, de modo que la restauración falla con `Invalid default value for 'post_date'` antes de ejecutar la importación SEO:

```sql
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';
```
:::

### Correspondencia de campos {#field-mapping}

Ambos importadores asignan los campos **explícitamente**. Una clave sin columna correspondiente en Core 3 se notifica como no asignada; no se inventa un destino.

| Clave de Yoast | Clave de Rank Math | `seo_meta` |
|---|---|---|
| `_yoast_wpseo_title` | `rank_math_title` | `title` |
| `_yoast_wpseo_metadesc` | `rank_math_description` | `description` |
| `_yoast_wpseo_canonical` | `rank_math_canonical_url` | `canonical` |
| `_yoast_wpseo_meta-robots-*` | `rank_math_robots` | `robots` |
| `_yoast_wpseo_focuskw` | `rank_math_focus_keyword` | `focus_keywords` |
| `_yoast_wpseo_opengraph-title` | `rank_math_facebook_title` | `og_title` |
| `_yoast_wpseo_opengraph-description` | `rank_math_facebook_description` | `og_description` |
| `_yoast_wpseo_opengraph-image` | `rank_math_facebook_image` | `og_image` |
| `_yoast_wpseo_twitter-title` | `rank_math_twitter_title` | `twitter_title` |
| `_yoast_wpseo_twitter-description` | `rank_math_twitter_description` | `twitter_description` |
| `_yoast_wpseo_twitter-image` | `rank_math_twitter_image` | `twitter_image` |
| — | `rank_math_twitter_card_type` | `twitter_card` |

**Robots.** Solo se guardan las diferencias respecto a los valores predeterminados de WordPress. Una página indexable normal deja `robots` en null y hereda el valor del sitio. Las opciones separadas de Yoast —`noindex`, `nofollow` y las avanzadas `noarchive`, `nosnippet`, `noimageindex`— se combinan en una cadena. El array serializado `robots` de Rank Math se lee de forma equivalente, omitiendo los valores predeterminados `index` / `follow`.

**Claves sin asignar**, notificadas pero no copiadas: ID de imágenes adjuntas (`*-image-id`), puntuaciones SEO/de palabras clave (`linkdex`, `content_score`, `rank_math_seo_score`), selecciones de categoría principal y marcadores de datos estructurados de Rank Math. El [grafo de datos estructurados](/es/guide/schema) ofrece una alternativa tipada más completa para estos últimos.

::: warning Los canonical se importan tal cual
Un canonical explícito (`rank_math_canonical_url` / `_yoast_wpseo_canonical`) se copia **exactamente como está guardado**. Si apunta al dominio anterior, por ejemplo `https://oldsite-staging.example.com/page/`, seguirá apuntando allí después de importar; el importador nunca cambia el host. `--site-url` obtiene rutas de petición a partir de URL absolutas para las [redirecciones candidatas](#redirects) y la correspondencia de filas CSV, pero **no** reescribe canonical guardados. Tras cambiar de dominio, revisa los canonical importados y actualiza el host o vacíalos para usar el canonical propio del resolvedor. La mayoría de las páginas carecen de canonical explícito y no se ven afectadas: Yoast y Rank Math lo generan al renderizar.
:::

### Variables de plantilla {#template-tokens}

Yoast y Rank Math guardan títulos y descripciones como **plantillas**: Yoast usa `%%title%%` y Rank Math `%title%`. El importador **resuelve las variables que puede deducir y elimina las restantes**, para no guardar una cadena `%%token%%` sin procesar:

| Variable | Resultado |
|---|---|
| `%%title%%` / `%title%` | Título de la publicación de WordPress |
| `%%sitename%%` / `%sitename%` | Nombre del blog en `wp_options`, en la importación de base de datos |
| `%%sep%%` / `%sep%` | `-` |
| `%%page%%`, `%%primary_category%%`, etc. | Se eliminan y se ajustan los separadores circundantes |

Si se resuelve alguna variable, el informe lo indica. **Revisa los títulos importados** y ajusta los que dependían de variables que no pudieron resolverse.

---

## Redirecciones {#redirects}

`seo_redirects` pertenece a [Rankbeam **Pro**](/es/pro/installation), por lo que el importador del núcleo no escribe directamente en esa tabla. Pasa `--redirects-csv=` para **generar un CSV** con las columnas `source_path,target_url,status_code,note`, que después puedes importar en Pro.

```bash
php artisan seo:import-from rank-math \
  --connection=wordpress --model="App\Models\Post" \
  --redirects-csv=storage/migrations/redirects.csv
```

Origen de las redirecciones candidatas:

- **Importación CSV**: una fila cuyo `canonical` apunta a una ruta distinta de su `url` produce una redirección `301` desde la ruta anterior al canonical. No se emite si ambas rutas coinciden, porque formaría un bucle.
- **Base de datos Rank Math**: reglas activas de `{prefix}rank_math_redirections`. Solo se emiten reglas de **coincidencia exacta**. Las de regex/contains/start/end se notifican como omitidas porque no corresponden a una sola ruta.
- **Yoast gratuito** no tiene tabla de redirecciones. Solo Yoast Premium la incluye y su esquema no forma parte del paquete gratuito. Usa CSV para las redirecciones de Yoast.

Las candidatas son **propuestas que debes revisar**. Revisa el CSV e impórtalo con [`seo-pro:redirects-import`](/es/guide/wordpress-migration-runbook#step-3-—-import-the-redirects-into-pro), que valida cada fila y rechaza bucles, destinos inseguros y duplicados. El **formato CSV de redirecciones v1** es un contrato estable: `source_path,target_url,status_code,note`.

---

## Qué muestra el informe {#what-the-report-tells-you}

Sin `--json`, el comando muestra una tabla de resultados —created / updated / unchanged / skipped / scanned—, un **informe de verificación** y secciones para revisar:

- **Verification report**: filas vinculadas a modelos (**matched**), filas sin modelo (**url-only**) y totales de valores truncados o sin asignar.
- **Truncated**: valores recortados para caber en una columna `seo_meta`.
- **Not imported**: claves con datos en el origen pero sin destino en Core 3, **incluido cada valor distinto de `author`**. El autor no es una columna guardada; corresponde a [`getSEOAuthor()`](/es/concepts/resolver-precedence). El informe muestra qué datos debes trasladar por otra vía.
- **Redirect candidates**: cuántas se escribieron y en qué archivo.
- **Skipped rows by reason**: filas solo URL, publicaciones sin metadatos SEO y reglas de redirección no exactas.
- **Warnings**: por ejemplo, que se resolvieron variables de plantilla.

Añade `--json` para obtener toda esta información en un formato procesable. El bloque `verification` incluye los totales matched/url-only y todos los valores de autor.

### Verificar {#verify}

```bash
php artisan seo:audit --model="App\Models\Post" --strict   # CI/cutover gate
```

`--strict` devuelve un código de salida distinto de cero si cualquier página tiene un problema. Consulta [Auditoría SEO gratuita](/es/guide/audit). Para el proceso completo —coexistir → importar → verificar → retirar—, sigue el [procedimiento de migración de WordPress](/es/guide/wordpress-migration-runbook).

---

¿Vienes de un paquete SEO de **Laravel**, como ralphjsmit, artesaos o Spatie? Consulta [Migrar desde otros paquetes Laravel](/es/guide/migrate-from-other-packages).

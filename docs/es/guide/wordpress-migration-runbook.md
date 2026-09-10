---
description: "Procedimiento para sustituir Yoast o Rank Math: los importadores rellenan campos vacíos por defecto, los dry runs no escriben y la base de WordPress no se modifica."
---

# Procedimiento de migración de WordPress a Rankbeam {#wordpress-→-rankbeam-migration-runbook}

Este procedimiento sustituye un stack SEO de WordPress, Yoast o Rank Math, por Rankbeam. Por defecto, los importadores rellenan los campos vacíos del destino; `--overwrite` permite sustituir valores explícitamente. Los dry runs no escriben y la base de datos de WordPress no se modifica. Haz una copia de seguridad del origen y del destino antes de importar.

Es el complemento operativo de [Migrar desde WordPress](/es/guide/migrate-from-wordpress), que detalla los campos, las variables de plantilla y las claves de origen. Aquella página explica qué se importa; esta indica cómo hacerlo y en qué orden.

::: tip Qué necesitas
- **Core** (`rankbeam/laravel-seo`) para importar metadatos y ejecutar `seo:audit`.
- **Pro** (`rankbeam/laravel-seo-pro`) solo si también migras **redirecciones**: la tabla `seo_redirects` pertenece a Pro.
- El contenido ya representado mediante modelos Laravel, por ejemplo `App\Models\Post`, con el trait [`HasSEO`](/es/guide/quickstart) y una forma de asociar el slug de WordPress a un modelo: su clave de ruta o la columna indicada con `--match-by`.
:::

## Estructura de la migración {#the-shape-of-the-migration}

Las filas de WordPress se identifican por **URL / publicación**; las de `seo_meta` son **polimórficas** y están vinculadas a un modelo Eloquent. El importador busca el modelo correspondiente. Cada ejecución informa de tres resultados posibles:

| Resultado | Significado | Acción |
|---|---|---|
| **matched** | La fila se vinculó a un modelo y se escribió `seo_meta` | Ninguna |
| **url-only** | No hubo coincidencia con un modelo o no se indicó `--model` | Decide si esa página necesita un modelo o una redirección |
| **unmapped** | Había datos sin destino en Core 3, especialmente **author** | Trasládalos a su lugar correspondiente, como `getSEOAuthor()` |

---

## Paso 0: coexistir sin cambiar todavía el sitio público {#step-0-—-coexist-no-cutover-yet}

Prepara Rankbeam junto al sitio en funcionamiento. Añade `HasSEO` a los modelos y genera las etiquetas mediante la fachada o directiva, pero **no retires todavía** WordPress ni su plugin SEO. En este punto no has importado ni eliminado nada; solo compruebas que el nuevo stack arranca.

Si sirves la aplicación Laravel y el WordPress anterior desde el mismo host durante el cambio, mantenlos en rutas separadas hasta el paso 5.

## Paso 1: importar metadatos, primero con dry run {#step-1-—-import-the-metadata-dry-run-first}

Empieza siempre con `--dry-run`: **no escribe nada** y muestra el informe completo de lo que ocurriría.

```bash
# Yoast, from the live WordPress database (configure the connection first)
php artisan seo:import-from yoast \
  --connection=wordpress \
  --model="App\Models\Post" \
  --redirects-csv=storage/app/redirects.csv \
  --dry-run

# Rank Math is identical — just swap the source key
php artisan seo:import-from rank-math --connection=wordpress --model="App\Models\Post" --dry-run

# From a CSV export instead of the live DB
php artisan seo:import-from wordpress-csv --file=storage/app/wp-export.csv --model="App\Models\Post" --dry-run
```

Opciones útiles; consulta la lista completa con `php artisan seo:import-from --help`:

| Opción | Finalidad |
|---|---|
| `--model=` | Nombre completo de clase del modelo de destino. Es repetible, pero los importadores WordPress vinculan **un** modelo por ejecución; ejecuta una por tipo de contenido. |
| `--match-by=` | Columna del modelo con la que comparar el slug; por defecto, su clave de ruta |
| `--post-type=` | Tipos de contenido que leen los lectores de base de datos; por defecto `post` y `page` |
| `--connection=` | Conexión de base de datos que contiene las tablas WordPress |
| `--table=` | **Prefijo** de tablas WordPress; por defecto `wp_` |
| `--locale=` | Idioma en el que se escriben las filas `seo_meta` |
| `--redirects-csv=` | Archivo de redirecciones candidatas para el paso 3 |
| `--site-url=` | URL del sitio anterior, para obtener rutas a partir de URL absolutas |
| `--overwrite` | Sustituye `seo_meta` existente no vacío; por defecto, **solo rellena campos vacíos** |
| `--limit=` | Límite de filas de origen, útil para una primera prueba |
| `--json` | Informe procesable por herramientas |

Si el dry run da el resultado esperado, elimina `--dry-run` para aplicarlo:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --redirects-csv=storage/app/redirects.csv
```

La importación es **idempotente** y, por defecto, **solo rellena campos vacíos**. Puedes repetirla sin sobrescribir los metadatos que ya editaste en Rankbeam mientras no añadas `--overwrite`.

## Paso 2: leer y archivar el informe de verificación {#step-2-—-read-and-archive-the-verification-report}

Cada ejecución muestra un **Verification report** con los resultados que debes revisar antes de eliminar nada. Guárdalo como evidencia:

```bash
php artisan seo:import-from yoast --connection=wordpress --model="App\Models\Post" --json > storage/app/import-report.json
```

Comprueba lo siguiente:

- **matched** debe coincidir con el número de páginas que esperas importar con metadatos SEO.
- **url-only** enumera las páginas sin modelo coincidente. Decide si cada una necesita un modelo, una redirección del paso 3 o ninguna acción.
- **truncated** muestra los campos recortados para caber en `seo_meta`; revisa esos títulos y descripciones.
- **unmapped** enumera datos sin columna correspondiente en Core 3, **incluido cada valor distinto de `author`**. Los autores corresponden a `getSEOAuthor()`, no a una columna guardada. El informe permite trasladarlos deliberadamente.

## Paso 3: importar las redirecciones en Pro {#step-3-—-import-the-redirects-into-pro}

El importador del núcleo **nunca escribe `seo_redirects`**, que pertenece a Pro. Genera un CSV con estructura fija y versionada: **formato CSV de redirecciones v1**, `source_path,target_url,status_code,note`. Impórtalo en Pro empezando por un dry run:

```bash
php artisan seo-pro:redirects-import storage/app/redirects.csv --dry-run
php artisan seo-pro:redirects-import storage/app/redirects.csv
```

Cada fila se valida igual que en el formulario de redirecciones de Filament. Las filas mal formadas, códigos de estado inválidos, **destinos externos inseguros**, **orígenes duplicados** y reglas que formarían **bucles** se omiten con un motivo, sin escribirse silenciosamente. El dry run valida el archivo completo, incluidos bucles y duplicados, sin escribir. Pasa `--overwrite` para sustituir el destino de una regla existente.

## Paso 4: verificar con `seo:audit --strict` {#step-4-—-verify-with-seo-audit-strict}

Usa la auditoría gratuita dentro del proceso como condición para el cambio. `--strict` devuelve un código distinto de cero si **cualquier** página tiene un problema, por lo que sirve en CI o como comprobación previa:

```bash
php artisan seo:audit --model="App\Models\Post" --strict
```

La auditoría comprueba el modelo y el resolvedor: presencia y longitud de título/descripción, imagen OG, conflictos robots y formato canonical. Las comprobaciones del HTML renderizado y canonical activos, además de la puntuación de 0 a 100, pertenecen al [scan de Pro](/es/pro/scan-issues). Ejecútalo también si tienes Pro. Consulta [Auditoría SEO gratuita](/es/guide/audit).

Después revisa varias páginas reales en el navegador. Mira el código fuente y confirma que `<title>`, `<meta name="description">`, canonical, robots y OpenGraph contienen los valores importados.

## Paso 5: verificar antes de retirar el paquete o las tablas anteriores {#step-5-—-verify-before-removing-the-legacy-package-table}

**No elimines** la base de WordPress, su plugin SEO ni el paquete anterior hasta que se cumpla todo lo siguiente:

- [ ] Se importaron **todos** los tipos de contenido, con un `--model` por ejecución.
- [ ] El informe archivado muestra el total **matched** esperado y ninguna fila **url-only** inesperada.
- [ ] Todos los valores **author sin asignar** que necesitas se trasladaron a su destino.
- [ ] Las redirecciones se importaron en Pro con `seo-pro:redirects-import` y varias URL antiguas devuelven realmente un 301 hacia las nuevas.
- [ ] `php artisan seo:audit --strict` termina con código `0`.
- [ ] En Pro, `php artisan seo:doctor` no informa de una tabla `seo` anterior ni de una colisión de `config/seo.php`.
- [ ] Se revisaron páginas renderizadas en el navegador.

Con el comportamiento predeterminado de rellenar solo campos vacíos, puedes repetir el paso 1 antes de esta comprobación sin sobrescribir los valores editados; los datos originales siguen en WordPress. Esto no se aplica si autorizas sustituciones con `--overwrite`.

## Paso 6: retirar WordPress {#step-6-—-decommission}

Solo cuando pase la lista del paso 5, deja de servir WordPress y retira su base de datos/tablas y el paquete SEO anterior. Conserva una copia de seguridad hasta confirmar que el nuevo stack funciona correctamente en producción.

::: tip Reversión
Con las opciones predeterminadas, los pasos 1–4 añaden datos sin sobrescribir los existentes en `seo_meta`; las redirecciones se validan y pueden retirarse, y WordPress no se modifica. `--overwrite` puede reemplazar datos del destino, por lo que debes conservar también su copia de seguridad. Antes del paso 6 puedes seguir sirviendo WordPress; después necesitarás restaurar su copia.
:::

---

¿Vienes de un paquete SEO de **Laravel**, como ralphjsmit, artesaos o Spatie? Consulta [Migrar desde otros paquetes Laravel](/es/guide/migrate-from-other-packages).

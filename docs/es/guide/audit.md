---
description: "Comprueba metadatos con seo:audit: una tabla por página, gratis, sin cola, licencia ni acceso a la red."
---

# Auditoría SEO gratuita (`seo:audit`)

`php artisan seo:audit` responde a una pregunta: **¿qué problemas tienen ahora los metadatos de mis páginas?** Recorre los modelos `HasSEO` en el proceso actual, **sin cola, licencia ni red**, y muestra un estado **pass / warn / fail** por página y un resumen.

```bash
php artisan seo:audit
```

```
+--------------+--------+----------------------------+
| Page         | Status | Findings                   |
+--------------+--------+----------------------------+
| Post #1      | PASS   | —                          |
| Post #2      | WARN   | notice title_too_short     |
| Post #3      | FAIL   | critical missing_title     |
|              |        | warning missing_description|
+--------------+--------+----------------------------+

3 page(s) — 1 passed, 1 warned, 1 failed
4 issue(s) — 1 critical, 1 warning, 2 notice
```

## Qué comprueba {#what-it-checks}

Solo ejecuta la clase **metadata**: comprobaciones derivadas del modelo y del [resolvedor](/es/concepts/resolver-precedence), sin descargar la página.

| Comprobación | Códigos |
|---|---|
| Título y descripción presentes, incluidos respaldos | `missing_title`, `missing_description` |
| Imagen OG presente, incluidos respaldos | `missing_og_image` |
| Longitud del título y la descripción | `title_too_long`, `title_too_short`, `description_too_long`, `description_too_short` |
| Títulos y descripciones duplicados entre páginas | `duplicate_title`, `duplicate_description` |
| Robots contradictorios y noindex que conviene revisar | `robots_conflict_indexing`, `robots_conflict_following`, `noindex_warning` |
| Canonical: formato, otro dominio, URL compartida o insegura | `invalid_canonical`, `cross_domain_canonical`, `shared_canonical`, `insecure_canonical` |
| Preparación para respuestas (AEO): datos estructurados de artículos | `aeo_missing_author`, `aeo_article_missing_date` |
| Palabra clave objetivo definida, tras activación | `missing_focus_keyword` |
| Alternativas hreflang del registro del núcleo, si la página declara alguna | `hreflang_invalid_code`, `hreflang_duplicate_code`, `hreflang_missing_self` |

Muchos códigos también aparecen en Pro, pero los registros son independientes. El núcleo usa `hreflang_missing_self` y Pro usa `hreflang_missing_self_reference`. `hreflang_duplicate_code` tiene nivel `notice` en el núcleo y `warning` en Pro. Un nombre compartido no garantiza la misma cobertura ni gravedad. `blank_explicit_override` pertenece al registro del núcleo. Las longitudes siguen el [presupuesto por escritura](/es/guide/multilingual#title-and-description-budgets-per-script): 60/160 grafemas para texto latino y aproximadamente 30/80 para CJK. Se mide el **valor resuelto con el sufijo del título incluido**. El [editor Filament](/es/guide/filament) usa la misma política, aunque también puede mostrar texto aún sin guardar.

Las comprobaciones hreflang usan la lista después de aplicar `seo.hreflang`, como las etiquetas y el sitemap. La reciprocidad requiere rastreo y se comprueba en Pro.

Las comprobaciones **AEO** solo se aplican a artículos JSON-LD (`Article`, `BlogPosting`, `NewsArticle`, …) sin entidad `author` o sin `datePublished` / `dateModified`. Examinar autoría y fechas permite identificar la procedencia y cronología explícitas. Si no hay un artículo declarado, no se genera este aviso. Son recomendaciones de nivel `notice`, excluidas del score Pro de 0 a 100.

## Qué no comprueba — límites del comando {#what-it-does-not-check-—-the-capability-boundary}

Una auditoría local de metadatos no cubre todo el análisis Pro. Cada ejecución recuerda que no incluye:

- **Comprobaciones del HTML servido:** `missing_h1`, `multiple_h1`, `missing_image_alt`, `thin_content` y `mixed_content` requieren el contenido real de la página.
- **Comprobaciones de red del canonical:** `canonical_target_broken` / `_redirect` / `_noindex` necesitan una petición saliente protegida.
- **El score numérico de 0 a 100:** Pro lo guarda con una rúbrica versionada en el resultado del análisis; consulta [score SEO (EN)](/pro/scoring).

Estas funciones pertenecen a **Pro**. Consulta el [registro completo de problemas (EN)](/pro/scan-issues).

## Elegir qué modelos auditar {#choosing-what-to-audit}

El comando usa `seo.audit.models`, con respaldo en `seo.sitemap.models`:

```php
// config/seo.php
'audit' => [
    'models' => [
        \App\Models\Post::class,
        \App\Models\Page::class,
    ],
],
```

También puedes indicar modelos explícitos:

```bash
php artisan seo:audit --model="App\Models\Post" --model="App\Models\Page"
```

## Opciones {#options}

| Opción | Efecto |
|---|---|
| `--model=` | Clase con `HasSEO`; repetible, reemplaza la configuración. |
| `--locale=` | Idioma de resolución; por defecto, el de la aplicación. |
| `--limit=` | Máximo de registros por modelo; `0` significa todos. |
| `--issues-only` | Mostrar solo páginas con al menos un problema. |
| `--strict` | Devolver un código de salida no nulo si aparece cualquier problema, para CI. |
| `--json` | Generar JSON con páginas, resumen y cobertura en lugar de la tabla. |

### Comprobación en CI {#ci-gate}

Con `--strict`, la auditoría funciona como comprobación del build:

```bash
php artisan seo:audit --strict
```

Devuelve `1` si alguna página tiene advertencias o fallos, y `0` si todas las páginas auditadas pasan.

### JSON {#json}

```bash
php artisan seo:audit --json
```

```json
{
  "pages": [
    {
      "model": "App\\Models\\Post",
      "key": 3,
      "label": "Post #3",
      "url": "https://example.com/blog/...",
      "status": "fail",
      "issues": [
        { "code": "missing_title", "severity": "critical", "field": "title", "message": "Page is missing a title tag." }
      ]
    }
  ],
  "summary": { "pages": 3, "passed": 1, "warned": 1, "failed": 1, "issues": 4, "by_severity": { "critical": 1, "warning": 1, "notice": 2 } },
  "skipped": [],
  "coverage": { "executes": "metadata", "note": "...", "reference": "https://rankbeam.dev/pro/scan-issues" }
}
```

## Palabras clave objetivo {#focus-keywords}

El aviso `missing_focus_keyword` está **desactivado por defecto**. Aparece tras activar el flujo de palabras clave:

```php
// config/seo.php
'keywords' => [
    'enabled' => true,
],
```

Pro lee el mismo ajuste, por lo que auditoría, análisis y editor comparten la activación. Usa el [campo Filament](/es/guide/filament) o `$model->saveSEO(['focus_keywords' => [['keyword' => 'laravel seo', 'is_primary' => true]]])`.

## Explicar valores inesperados con `seo:explain` {#when-a-value-isn-t-what-you-expect-seo-explain}

`seo:audit` muestra **qué está mal**. [`seo:explain` (EN)](/guide/explain) explica **de dónde viene el valor**: configuración, valor predeterminado, cálculo o dato explícito; qué reemplazó y qué tratamiento posterior recibió, como sufijo, limpieza del canonical o protección de indexación. Úsalo cuando una etiqueta o un resultado te sorprenda:

```bash
php artisan seo:explain "App\Models\Post" 42
```

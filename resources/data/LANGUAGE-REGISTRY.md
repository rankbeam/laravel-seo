# Language-tag validation data

`language-registry.json` contains derived identifiers, registered extlang prefixes,
preferred aliases and Suppress-Script facts from IANA's Language Subtag Registry
(File-Date 2026-08-08), plus the registered extension singleton identifiers from
the Language Tag Extensions Registry (File-Date 2014-04-02). Source URLs and SHA256
pins are embedded in the JSON. No registry prose is copied.

Reproduce from downloaded source files, with no network access in the generator:

```sh
python tools/update-language-registry.py --subtags /path/language-subtag-registry --extensions /path/language-tag-extensions-registry --check
```

Omit `--check` to regenerate. Changed upstream hashes deliberately fail until the
registry changes and fixtures are reviewed and the pins updated. Generated data
and this provenance file remain in Composer distributions; the tool and tests do
not. No runtime download, ICU requirement or new Composer dependency.

`LanguageTag` checks RFC 5646 structure, registration, extlang prefixes and
duplicate variant/extension subtags. Deprecated registered tags remain valid.
Private-use ranges are supported. Variant Prefix recommendations are not hard
validity gates. Extension namespace registration and payload structure are
checked; CLDR key/value semantics and private-use meaning are outside this API.
The HTML helper accepts empty (unknown) language but rejects `x-default` as a
Rankbeam content-language policy; generic BCP47 private use permits that spelling.

`Hreflang` has a separate Google Search contract: ISO 639-1 language, optional
registered ISO 15924 script and optional ISO 3166-1 alpha-2 region, or x-default.
It does not extend Google's contract to all BCP47 variants, numeric regions or
three-letter languages. The language and country lists remain explicit in
`Hreflang.php`; neither list is inferred from Google's interface translations.

Primary references:

- https://www.rfc-editor.org/rfc/rfc5646.html
- https://html.spec.whatwg.org/multipage/dom.html#the-lang-and-xml:lang-attributes
- https://developers.google.com/search/docs/specialty/international/localized-versions#supported-language-and-region-codes

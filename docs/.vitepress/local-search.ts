// VitePress 1.6 builds every locale index with the global MiniSearch options.
// Search-only aliases let extraction use the document path without changing
// stored display titles or relying on mutable locale state during parallel builds.
export const localSearchMiniSearch = {
  options: {
    fields: ['searchTitle', 'searchTitles', 'searchText'],
    extractField: (document: Record<string, unknown>, field: string) => {
      // Keep this function self-contained: VitePress serializes it to the client.
      const source = ({ searchTitle: 'title', searchTitles: 'titles', searchText: 'text' } as Record<string, string>)[field]
      const value = document[source ?? field]
      if (!source) return value
      const locale = String(document.id).match(/^\/(tr|el|ja|zh-CN|ko)(?:\/|#|$)/)?.[1]
      if (!locale) return value
      if (locale === 'ko') {
        // Korean uses spaces. Normalize search-only fields for composed Hangul
        // and fullwidth identifiers; preserve original stored display values.
        if (Array.isArray(value)) return value.map(part => String(part).normalize('NFKC'))
        return typeof value === 'string' ? value.normalize('NFKC') : value
      }
      if (locale === 'ja' || locale === 'zh-CN') {
        // The global builder still uses MiniSearch's punctuation tokenizer.
        // Insert word boundaries only in search fields, leaving display intact.
        const segmenter = new Intl.Segmenter(locale, { granularity: 'word' })
        const words = (part: string) => [...segmenter.segment(part.normalize('NFKC'))]
          .filter(segment => segment.isWordLike).map(segment => segment.segment).join(' ')
        if (Array.isArray(value)) return value.map(part => words(String(part)))
        return typeof value === 'string' ? words(value) : value
      }
      const normalize = (part: string) => locale === 'tr'
        ? part.toLocaleLowerCase('tr')
        : part.toLocaleLowerCase('el').normalize('NFD').replace(/\p{M}/gu, '').replace(/ς/g, 'σ')
      if (Array.isArray(value)) return value.map(part => normalize(String(part)))
      return typeof value === 'string' ? normalize(value) : value
    },
  },
  // Preserve VitePress's 4/1/2 weighting under the new field names.
  searchOptions: { boost: { searchTitle: 4, searchTitles: 1, searchText: 2 } },
}

export const turkishSearchMiniSearch = {
  ...localSearchMiniSearch,
  options: {
    ...localSearchMiniSearch.options,
    // Query normalization must agree with Turkish document extraction. In
    // particular I/ı and İ/i are distinct pairs; do not strip global accents.
    processTerm: (term: string) => term.toLocaleLowerCase('tr'),
  },
}

export const greekSearchMiniSearch = {
  ...localSearchMiniSearch,
  options: {
    ...localSearchMiniSearch.options,
    // Greek searches commonly omit tonos; σ/ς are forms of the same letter.
    // Normalize index and query alike, preserving the original display fields.
    processTerm: (term: string) => term.toLocaleLowerCase('el').normalize('NFD').replace(/\p{M}/gu, '').replace(/ς/g, 'σ'),
  },
}

export const japaneseSearchMiniSearch = {
  ...localSearchMiniSearch,
  options: {
    ...localSearchMiniSearch.options,
    // Self-contained because VitePress serializes functions into the client.
    // Match both Japanese word boundaries and the global builder's default
    // punctuation split (notably snake_case and hyphenated ASCII identifiers).
    tokenize: (text: string) => [...new Intl.Segmenter('ja', { granularity: 'word' }).segment(text.normalize('NFKC'))]
      .filter(segment => segment.isWordLike)
      .flatMap(segment => segment.segment.split(/[\n\r\p{Z}\p{P}]+/u)).filter(Boolean),
  },
}


export const simplifiedChineseSearchMiniSearch = {
  ...localSearchMiniSearch,
  options: {
    ...localSearchMiniSearch.options,
    // Self-contained for VitePress serialization. Match the global builder's
    // Chinese word boundaries, width normalization and punctuation split.
    tokenize: (text: string) => [...new Intl.Segmenter('zh-CN', { granularity: 'word' }).segment(text.normalize('NFKC'))]
      .filter(segment => segment.isWordLike)
      .flatMap(segment => segment.segment.split(/[\n\r\p{Z}\p{P}]+/u)).filter(Boolean),
  },
}


export const koreanSearchMiniSearch = {
  ...localSearchMiniSearch,
  options: {
    ...localSearchMiniSearch.options,
    // Self-contained for VitePress serialization. Match normalized extraction
    // and the global builder's punctuation tokenizer without guessing stems.
    tokenize: (text: string) => text.normalize('NFKC').split(/[\n\r\p{Z}\p{P}]+/u).filter(Boolean),
  },
}

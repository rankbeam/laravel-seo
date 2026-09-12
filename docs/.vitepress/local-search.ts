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
      const locale = String(document.id).match(/^\/(tr|el)(?:\/|#|$)/)?.[1]
      if (!locale) return value
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

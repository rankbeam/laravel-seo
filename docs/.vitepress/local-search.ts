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
      if (!source || !/^\/tr(?:\/|#|$)/.test(String(document.id))) return value
      if (Array.isArray(value)) return value.map(part => String(part).toLocaleLowerCase('tr'))
      return typeof value === 'string' ? value.toLocaleLowerCase('tr') : value
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

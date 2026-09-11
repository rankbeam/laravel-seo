import { fileURLToPath } from 'node:url'

// VitePress 1.6 does not expose these accessible names in themeConfig.
// Transform its component sources at build time, with an exact-match guard.
// No dependency files are edited; SSR and client navigation use the same text.
const messagesPath = fileURLToPath(new URL('./manual-messages.ts', import.meta.url)).replaceAll('\\', '/')
const substitutions = {
  'VPNavBarHamburger.vue': ['aria-label="mobile navigation"', ':aria-label="rankbeamManual(rankbeamLang)?.mobileNavigation ?? \'mobile navigation\'"'],
  'VPSidebar.vue': ['Sidebar Navigation', "{{ rankbeamManual(rankbeamLang)?.sidebarNavigation ?? 'Sidebar Navigation' }}"],
  'VPDocFooter.vue': ['>Pager</span>', ">{{ rankbeamManual(rankbeamLang)?.pager ?? 'Pager' }}</span>"],
  'VPNavBarMenu.vue': ['Main Navigation', "{{ rankbeamManual(rankbeamLang)?.mainNavigation ?? 'Main Navigation' }}"],
} as const

export function translateThemeSource(source: string, filename: string): string | null {
  const replacement = substitutions[filename as keyof typeof substitutions]
  if (!replacement) return null
  const [old, next] = replacement
  if (source.split(old).length !== 2 || !/<script[^>]*setup[^>]*>/.test(source)) {
    throw new Error(`VitePress accessibility surface changed: review ${filename} before updating the adapter`)
  }
  return source.replace(old, next).replace(/(<script[^>]*setup[^>]*>)/, `$1\nimport { useData as useRankbeamData } from 'vitepress'\nimport { manualFor as rankbeamManual } from ${JSON.stringify(messagesPath)}\nconst { lang: rankbeamLang } = useRankbeamData()\n`)
}

export function localizedThemeAccessibility() {
  return {
    name: 'rankbeam-theme-accessibility',
    enforce: 'pre' as const,
    transform(source: string, id: string) {
      const normalized = id.replaceAll('\\', '/')
      if (normalized.endsWith('/vitepress/dist/client/theme-default/composables/langs.js')) {
        return { code: hydrateLanguageFragments(source), map: null }
      }
      if (!normalized.includes('/vitepress/dist/client/theme-default/components/') || normalized.includes('?')) return
      const code = translateThemeSource(source, normalized.split('/').at(-1)!)
      if (code !== null) return { code, map: null }
    },
  }
}

// A URL fragment is unavailable to SSR. Defer it until mount so Vue patches
// language links instead of retaining their fragment-free server attributes.
export function hydrateLanguageFragments(source: string): string {
  const replacements = [
    ["import { computed } from 'vue';", "import { computed, onMounted, ref } from 'vue';"],
    ["    const currentLang = computed", "    const mounted = ref(false);\n    onMounted(() => { mounted.value = true; });\n    const currentLang = computed"],
    [' + hash.value', " + (mounted.value ? hash.value : '')"],
  ]
  for (const [before, after] of replacements) {
    if (source.split(before).length !== 2) throw new Error('VitePress language fragment contract changed; review the adapter')
    source = source.replace(before, after)
  }
  return source
}

<script setup lang="ts">
import { computed } from 'vue'
import { useData } from 'vitepress'
import { manualFor } from '../../manual-messages'
import { noScriptNavigation } from '../../no-script-navigation'
const { theme, site, page, lang } = useData()
const markup = computed(() => {
  const copy = manualFor(lang.value)
  return copy ? noScriptNavigation(theme.value, site.value.locales, page.value.relativePath, lang.value, copy) : ''
})
</script>

<template><noscript v-if="markup" v-html="markup" /></template>

<style>
.rb-noscript-menu summary { cursor: pointer; padding: 12px; font-size: 14px; }
.rb-noscript-menu nav {
  position: fixed; inset: 64px 16px 16px; z-index: 100;
  overflow: auto; padding: 24px; border: 1px solid var(--vp-c-divider);
  border-radius: 8px; background: var(--vp-c-bg); color: var(--vp-c-text-1);
  min-width: 0; white-space: normal; overflow-wrap: anywhere;
  font-size: 14px; line-height: 1.7;
}
.rb-noscript-menu p, .rb-noscript-menu ul { margin: 0 0 16px; }
.rb-noscript-menu ul ul { padding-inline-start: 16px; }
.rb-noscript-menu a { display: inline-block; padding-block: 8px; color: var(--vp-c-brand-1); }
</style>

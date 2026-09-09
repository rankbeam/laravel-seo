<script setup lang="ts">
import { computed } from 'vue'
import { useData } from 'vitepress'
const { page, frontmatter } = useData()
const source = computed(() => '/' + page.value.relativePath.replace(/^[^/]+\//, '').replace(/(^|\/)index\.md$/, '$1').replace(/\.md$/, ''))
</script>

<template>
  <aside v-if="frontmatter.translationNotice" class="rb-translation-notice">
    {{ frontmatter.translationNotice }} <a :href="source">{{ frontmatter.sourceLabel }}</a>
  </aside>
</template>

<style scoped>
.rb-translation-notice { margin: 0 0 24px; padding: 12px 16px; border-left: 3px solid var(--vp-c-brand-1); background: var(--vp-c-bg-soft); font-size: 14px; line-height: 1.6; }
a { color: var(--vp-c-brand-1); text-decoration: underline; }
</style>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue'
import { useData, useRoute } from 'vitepress'
import { manualFor } from '../../manual-messages'

const { lang } = useData()
const copy = computed(() => manualFor(lang.value))
const announcement = ref('')
const errorTarget = shallowRef<Element | null>(null)
const timers = new Set<ReturnType<typeof setTimeout>>()
const route = useRoute()
watch(() => route.path, () => {
  errorTarget.value = null
  announcement.value = ''
})

async function copyCode(event: MouseEvent) {
  const button = event.target
  const messages = copy.value
  if (!messages || !(button instanceof HTMLButtonElement) || !button.matches('div[class*="language-"] > button.copy')) return
  // Handle this locale before VitePress's window handler, including rejected
  // clipboard promises. Other editions keep the default handler unchanged.
  event.preventDefault()
  event.stopPropagation()
  const parent = button.parentElement
  const code = parent?.querySelector('pre code')
  if (!parent || !code) return
  const clone = code.cloneNode(true) as HTMLElement
  clone.querySelectorAll('.vp-copy-ignore, .diff.remove').forEach(node => node.remove())
  let text = clone.textContent ?? ''
  if (/language-(shellscript|shell|bash|sh|zsh)/.test(parent.className)) text = text.replace(/^ *(\$|>) /gm, '').trim()
  announcement.value = ''
  errorTarget.value = null
  try {
    await navigator.clipboard.writeText(text)
    button.classList.add('copied')
    announcement.value = messages.copied
    const timer = setTimeout(() => {
      button.classList.remove('copied')
      timers.delete(timer)
    }, 2000)
    timers.add(timer)
  } catch {
    button.classList.remove('copied')
    // Leave the code selected so the reader can use the browser's Copy action.
    const selection = window.getSelection()
    const range = document.createRange()
    range.selectNodeContents(code)
    selection?.removeAllRanges()
    selection?.addRange(range)
    announcement.value = messages.copyFailed
    errorTarget.value = parent
  }
}

onMounted(() => document.addEventListener('click', copyCode, true))
onUnmounted(() => {
  document.removeEventListener('click', copyCode, true)
  for (const timer of timers) clearTimeout(timer)
})
</script>

<template>
  <template v-if="copy">
    <p class="rb-copy-status" role="status" aria-live="polite">{{ announcement }}</p>
    <Teleport v-if="errorTarget" :to="errorTarget">
      <p class="rb-copy-error">{{ copy.copyFailed }}</p>
    </Teleport>
    <noscript v-html="copy.noScript" />
  </template>
</template>

<style>
html[lang='nl'] {
  --vp-code-copy-copied-text-content: 'Gekopieerd';
}
html[lang='tr'] {
  --vp-code-copy-copied-text-content: 'Kopyalandı';
}
.rb-copy-status {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
.vp-doc .rb-copy-error {
  padding: 0 24px 16px;
  color: var(--rb-console-ink);
  font-family: var(--vp-font-family-base);
  font-size: 14px;
}
</style>

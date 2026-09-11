import type { Theme } from 'vitepress'
import DefaultTheme from 'vitepress/theme'
import Home from './components/Home.vue'
import './custom.css'
import { h } from 'vue'
import TranslationNotice from './components/TranslationNotice.vue'
import LocalizedCopy from './components/LocalizedCopy.vue'
import NoScriptNavigation from './components/NoScriptNavigation.vue'

// Default VitePress theme + the rankbeam.dev design system (see custom.css).
// <Home> is the landing page used by docs/index.md, which is a landing page in
// all but name — the default `layout: home` could not carry it.
export default {
  extends: DefaultTheme,
  Layout: () => h(DefaultTheme.Layout, null, { 'doc-before': () => h(TranslationNotice), 'doc-after': () => h(LocalizedCopy), 'nav-bar-content-after': () => h(NoScriptNavigation) }),
  enhanceApp({ app }) {
    app.component('Home', Home)
  },
} satisfies Theme

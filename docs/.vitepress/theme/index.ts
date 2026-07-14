import type { Theme } from 'vitepress'
import DefaultTheme from 'vitepress/theme'
import Home from './components/Home.vue'
import './custom.css'

// Default VitePress theme + the rankbeam.dev design system (see custom.css).
// <Home> is the landing page used by docs/index.md, which is a landing page in
// all but name — the default `layout: home` could not carry it.
export default {
  extends: DefaultTheme,
  enhanceApp({ app }) {
    app.component('Home', Home)
  },
} satisfies Theme

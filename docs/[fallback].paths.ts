import fs from 'node:fs'
import path from 'node:path'
import { docsRoot, englishPages, localeInfo } from './.vitepress/localization'

export default {
  paths() {
    return Object.entries(localeInfo).flatMap(([locale, info]) => englishPages().filter(source =>
      !fs.existsSync(path.join(docsRoot, locale, source))).map(source => {
      const url = '/' + source.replace(/(^|\/)index\.md$/, '$1').replace(/\.md$/, '')
      return {
        params: { fallback: locale + '/' + source.replace(/\.md$/, '') },
        content: `---\ntitle: ${JSON.stringify(info.english)}\ndescription: ${JSON.stringify(info.fallback)}\nfallbackFor: ${JSON.stringify(url)}\nsearch: false\n---\n\n# ${info.english}\n\n${info.fallback}\n\n[${info.open}](${url})\n`,
      }
    }))
  },
}

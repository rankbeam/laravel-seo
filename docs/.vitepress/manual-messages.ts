// Additional theme surfaces absent from the page/layout catalogs.
// Codex copy review 2026-09-11; no independent native/human certification.
export const manualMessages = {
  nl: {
    imageAlt: 'Rankbeam — open-core SEO-infrastructuur voor Laravel',
    copyTitle: 'Code kopiëren',
    copied: 'Gekopieerd',
    copyFailed: 'Kopiëren is niet gelukt. Selecteer de code en kopieer die handmatig.',
    noScript: 'Zonder JavaScript kun je de code selecteren en handmatig kopiëren.',
    noScriptMenu: 'Menu',
    noScriptHelp: 'JavaScript staat uit. Gebruik deze links om te navigeren; selecteer code om die handmatig te kopiëren.',
    language: 'Taal',
    mobileNavigation: 'Mobiele navigatie',
    extraNavigation: 'Extra navigatie',
    mainNavigation: 'Hoofdnavigatie',
    sidebarNavigation: 'Navigatie in de zijbalk',
    pager: 'Paginanavigatie',
    permalink: 'Vaste link naar',
    notFound: {
      title: 'PAGINA NIET GEVONDEN',
      quote: 'Maar als je niet van richting verandert en blijft zoeken, kom je misschien uit waar je naartoe gaat.',
      linkLabel: 'naar de homepage',
      linkText: 'Naar de homepage',
    },
  },
  "tr": {
    "imageAlt": "Rankbeam — Laravel için open-core SEO altyapısı",
    "copyTitle": "Kodu kopyala",
    "copied": "Kopyalandı",
    "copyFailed": "Kopyalanamadı. Kodu seçip elle kopyalayın.",
    "noScript": "JavaScript olmadan kodu seçip elle kopyalayabilirsiniz.",
    "noScriptMenu": "Menü",
    "noScriptHelp": "JavaScript devre dışı. Gezinmek için bu bağlantıları kullanın; kodu elle kopyalamak için seçin.",
    "language": "Dil",
    "mobileNavigation": "Mobil gezinme",
    "extraNavigation": "Ek gezinme",
    "mainNavigation": "Ana gezinme",
    "sidebarNavigation": "Kenar çubuğu gezinmesi",
    "pager": "Sayfalar arasında gezinme",
    "permalink": "Kalıcı bağlantı:",
    "notFound": {
      "title": "SAYFA BULUNAMADI",
      "quote": "Ama yönünüzü değiştirmeden aramaya devam ederseniz, sonunda gitmekte olduğunuz yere varabilirsiniz.",
      "linkLabel": "ana sayfaya git",
      "linkText": "Ana sayfaya dön"
    }
  },
} as const

export function manualFor(locale: string) {
  return manualMessages[locale as keyof typeof manualMessages]
}

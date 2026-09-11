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
} as const

export function manualFor(locale: string) {
  return manualMessages[locale as keyof typeof manualMessages]
}

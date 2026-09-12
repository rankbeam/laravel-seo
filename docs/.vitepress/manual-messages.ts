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
  "pl": {
    "imageAlt": "Rankbeam — infrastruktura SEO open-core dla Laravel",
    "copyTitle": "Kopiuj kod",
    "copied": "Skopiowano",
    "copyFailed": "Kopiowanie nie powiodło się. Zaznacz kod i skopiuj go ręcznie.",
    "noScript": "Bez JavaScript możesz zaznaczyć kod i skopiować go ręcznie.",
    "noScriptMenu": "Menu",
    "noScriptHelp": "JavaScript jest wyłączony. Użyj tych linków do nawigacji; zaznacz kod, aby skopiować go ręcznie.",
    "language": "Język",
    "mobileNavigation": "Nawigacja mobilna",
    "extraNavigation": "Dodatkowa nawigacja",
    "mainNavigation": "Nawigacja główna",
    "sidebarNavigation": "Nawigacja w panelu bocznym",
    "pager": "Nawigacja między stronami",
    "permalink": "Stały link do",
    "notFound": {
      "title": "NIE ZNALEZIONO STRONY",
      "quote": "Jeśli jednak nie zmienisz kierunku i będziesz dalej szukać, możesz dotrzeć tam, dokąd zmierzasz.",
      "linkLabel": "przejdź do strony głównej",
      "linkText": "Wróć do strony głównej"
    }
  },
  "ru": {
    "imageAlt": "Rankbeam — SEO-инфраструктура для Laravel с открытым ядром",
    "copyTitle": "Копировать код",
    "copied": "Скопировано",
    "copyFailed": "Не удалось скопировать. Выделите код и скопируйте его вручную.",
    "noScript": "Без JavaScript можно выделить код и скопировать его вручную.",
    "noScriptMenu": "Меню",
    "noScriptHelp": "JavaScript отключён. Используйте эти ссылки для навигации; выделите код, чтобы скопировать его вручную.",
    "language": "Язык",
    "mobileNavigation": "Мобильная навигация",
    "extraNavigation": "Дополнительная навигация",
    "mainNavigation": "Основная навигация",
    "sidebarNavigation": "Навигация в боковой панели",
    "pager": "Переход между страницами",
    "permalink": "Постоянная ссылка на",
    "notFound": {
      "title": "СТРАНИЦА НЕ НАЙДЕНА",
      "quote": "Но если вы не измените направление и продолжите искать, то можете оказаться там, куда направляетесь.",
      "linkLabel": "перейти на главную страницу",
      "linkText": "На главную страницу"
    }
  },
} as const

export function manualFor(locale: string) {
  return manualMessages[locale as keyof typeof manualMessages]
}

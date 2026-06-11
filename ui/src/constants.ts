
export const APP_NAME: string = 'Zástavy a vlajky'
export const URL_BASE: string = import.meta.env.VITE_URL_BASE;
export const URL_BASE_API: string = import.meta.env.VITE_URL_BASE_API;

// type PageName = typeof PAGE_TIMELINE | typeof PAGE_ACTIVITIES | typeof PAGE_PROGRESS

export interface Page {
    NAME: string
    URL: string
    ROUTE: string
    ICON: any
}

export const PAGE_HOME: Page = {
    NAME: 'Tovar',
    URL: URL_BASE_API + '/homes',
    ROUTE: 'public.index',
    ICON: ''
}

export const PAGE_ORDER: Page = {
    NAME: 'Objednávky',
    URL: URL_BASE_API + '/orders',
    ROUTE: 'orders.index',
    ICON: ''
}

export const PAGE_CUSTOMER: Page = {
    NAME: 'Zákazníci',
    URL: URL_BASE_API + '/customers',
    ROUTE: 'customers.index',
    ICON: ''
}

export const PAGE_USER: Page = {
    NAME: 'Použivatelia',
    URL: URL_BASE_API + '/users',
    ROUTE: 'users.index',
    ICON: ''
}

export const PAGE_PRODUCT: Page = {
    NAME: 'Produkty',
    URL: URL_BASE_API + '/products',
    ROUTE: 'products.index',
    ICON: ''
}

export const PAGE_STOCK: Page = {
    NAME: 'Sklad',
    URL: URL_BASE_API + '/stocks',
    ROUTE: 'stocks.index',
    ICON: ''
}

export const PAGE_ANNOUNCEMENT: Page = {
    NAME: 'Oznamy',
    URL: URL_BASE_API + '/announcements',
    ROUTE: 'announcements.index',
    ICON: ''
}

export const PAGE_CATEGORY: Page = {
    NAME: 'Kategórie',
    URL: URL_BASE_API + '/categories',
    ROUTE: 'category.index',
    ICON: ''
}

export const PAGE_SHIPPING_METHOD: Page = {
    NAME: 'Doprava',
    URL: URL_BASE_API + '/shipping-methods',
    ROUTE: 'shipping-methods.index',
    ICON: ''
}

export const PAGE_PAYMENT_METHOD: Page = {
    NAME: 'Platby',
    URL: URL_BASE_API + '/payment-methods',
    ROUTE: 'payment-methods.index',
    ICON: ''
}

export const PAGE_COUPON: Page = {
    NAME: 'Kupóny',
    URL: URL_BASE_API + '/coupons',
    ROUTE: 'coupons.index',
    ICON: ''
}

export const PAGE_KONTAKT: Page = {
    NAME: 'Kontakt',
    URL: '',
    ROUTE: 'public.contactUs',
    ICON: ''
}

export const NAV_ITEMS: Page[] = [
    {
        NAME: 'Obchodné podmienky',
        ROUTE: 'public.obchodne.podmienky',
        URL: '',
        ICON: '',
    },
    {
        NAME: 'Ochrana osobných údajov',
        ROUTE: 'public.ochranaOsobnychUdajov',
        URL: '',
        ICON: '',
    },
    {
        NAME: 'Kontakt',
        ROUTE: 'public.contactUs',
        URL: '',
        ICON: '',
    }
]

export const NAV_MAIN_PAGES: Page[] = [
    {
        NAME: 'Objednávky',
        ROUTE: PAGE_ORDER.ROUTE,
        URL:  PAGE_ORDER.URL,
        ICON: 'badge',
    },
    {
        NAME: 'Produkty',
        ROUTE: PAGE_PRODUCT.ROUTE,
        URL: PAGE_PRODUCT.URL,
        ICON: '',
    },
    {
        NAME: 'Zákazníci',
        ROUTE: PAGE_CUSTOMER.ROUTE,
        URL: PAGE_CUSTOMER.URL,
        ICON: '',
    },
    {
        NAME: 'Sklad',
        ROUTE: PAGE_STOCK.ROUTE,
        URL:  PAGE_STOCK.URL,
        ICON: '',
    }
]


// export const NAV_ITEMS = [
//   {
//     page: PAGE_TIMELINE,
//     icon: ICON_CLOCK,
//     route_name: '',
//   },
//   {
//     page: PAGE_ACTIVITIES,
//     icon: ICON_LIST_BULLET
//   },
//   {
//     page: PAGE_PROGRESS,
//     icon: ICON_CHART_BAR
//   }
// ]



// import { ICON_CLOCK, ICON_LIST_BULLET, ICON_CHART_BAR } from './icons'
// import { generatePeriodSelectOptions } from './functions'




// export const BUTTON_TYPE_PRIMARY = 'primary'
// export const BUTTON_TYPE_SUCCESS = 'success'
// export const BUTTON_TYPE_WARNING = 'warning'
// export const BUTTON_TYPE_NEUTRAL = 'neutral'
// export const BUTTON_TYPE_DANGER = 'danger'

// export const BUTTON_TYPES = [
//   BUTTON_TYPE_PRIMARY,
//   BUTTON_TYPE_SUCCESS,
//   BUTTON_TYPE_WARNING,
//   BUTTON_TYPE_NEUTRAL,
//   BUTTON_TYPE_DANGER
// ]

// export const MILLISECONDS_IN_SECOND = 1000
// export const SECONDS_IN_MINUTE = 60
// export const MINUTES_IN_HOUR = 60
// export const HOURS_IN_DAY = 24
// export const MIDNIGHT_HOUR = 0
// export const SECONDS_IN_HOUR = SECONDS_IN_MINUTE * MINUTES_IN_HOUR
// export const SECONDS_IN_DAY = HOURS_IN_DAY * SECONDS_IN_HOUR

// export const PERIOD_SELECT_OPTIONS = generatePeriodSelectOptions()

// export const LOW_PERCENT = 33
// export const MEDIUM_PERCENT = 66
// export const HUNDRED_PERCENT = 100

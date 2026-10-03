import { PAGE_STOCK } from '../constants';

const stock = [
    {
        path: '/sklad/prijemky',
        name: 'stocks.receipts.index',
        components: { default: () => import('../components/stock/StockReceiptIndex.vue') },
        meta: { title: 'Príjemky', superAdminOnly: true },
    },
    {
        path: '/sklad/odpis/create',
        name: 'stocks.writeoff',
        components: { default: () => import('../components/stock/StockWriteoff.vue') },
        meta: { title: 'Odpis zo skladu', superAdminOnly: true },
    },
    {
        path: '/sklad/prijemky/:receiptId(\\d+)',
        name: 'stocks.receipts.show',
        components: { default: () => import('../components/stock/StockReceiptShow.vue') },
        meta: { title: 'Príjemka', superAdminOnly: true },
    },
    {
        path: '/sklad',
        name: PAGE_STOCK.ROUTE,
        components: {
            default: () => import('../components/stock/StockIndex.vue'),
        },
        meta: {
            title: 'Sklad - zoznam tovaru',
            superAdminOnly: true,
        },
    },
    {
        path: '/sklad/create',
        name: 'stocks.create',
        components: {
            default: () => import('../components/stock/StockCreate.vue'),
        },
        meta: {
            title: 'Príjem / odpis tovaru',
            superAdminOnly: true,
        },
    },
    {
        // Číselné obmedzenie, aby /sklad/create nespadlo do detailu.
        path: '/sklad/:variantId(\\d+)',
        name: 'stocks.show',
        components: {
            default: () => import('../components/stock/StockShow.vue'),
        },
        meta: {
            title: 'Sklad - pohyby položky',
            superAdminOnly: true,
        },
    },
];

export default stock;

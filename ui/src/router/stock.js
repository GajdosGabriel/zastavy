import { PAGE_STOCK } from '../constants';
const stock = [
    {
        path: '/sklad',
        name: PAGE_STOCK.ROUTE,
        components: {
            default: () => import('../components/stock/StockIndex.vue'),
        },
        meta: {
            title: 'Sklad - zoznam tovaru'
        }
    },
    {
        path: '/sklad/create',
        name: 'stocks.create',
        components: {
            default: () => import('../components/stock/StockCreate.vue'),
        },
        meta: {
            title: 'nový príjem tovaru'
        }
    }
]

export default stock;

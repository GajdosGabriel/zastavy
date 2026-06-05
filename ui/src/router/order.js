import { PAGE_ORDER } from '../constants';

const order = [
    {
        path: '/objednavky',
        name: PAGE_ORDER.ROUTE,
        components: {
            default: () => import('../components/order/OrderIndex.vue'),
        },
        meta: {
            title: 'Objednávky'
        }
    },
    {
        path: '/objednavky/:orderId/show',
        name: 'orders.show',
        components: {
            default: () => import('../components/order/OrderShow.vue'),
        },
        meta: {
            title: 'Zobraziť objednávku'
        }
    },
    {
        path: '/objednavky/:orderId/edit',
        name: 'orders.edit',
        components: {
            default: () => import('../components/order/OrderEdit.vue'),
        },
        meta: {
            title: 'Upraviť objednávku'
        }
    },
    {
        path: '/objednavky/:orderId/expedicia',
        name: 'orders.shipping.edit',
        components: {
            default: () => import('../components/order/OrderShippingEdit.vue'),
        },
        meta: {
            title: 'Expedícia objednávky'
        }
    },
    {
        path: '/objednavky/create',
        name: 'orders.create',
        components: {
            default: () => import('../components/order/OrderCreate.vue'),
        },
        meta: {
            title: 'Nová objednávka'
        }
    },
];

export default order;

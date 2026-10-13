import { PAGE_ORDER } from '../constants';

const order = [
    {
        path: '/objednavky',
        name: PAGE_ORDER.ROUTE,
        components: {
            default: () => import('../components/order/OrderIndex.vue'),
        },
        meta: {
            title: 'Objednávky',
            requiresAuth: true,
        }
    },
    {
        path: '/objednavky/:orderId/show',
        name: 'orders.show',
        components: {
            default: () => import('../components/order/OrderShow.vue'),
        },
        meta: {
            title: 'Zobraziť objednávku',
            requiresAuth: true,
        }
    },
    {
        path: '/objednavky/:orderId/edit',
        name: 'orders.edit',
        components: {
            default: () => import('../components/order/OrderEdit.vue'),
        },
        meta: {
            title: 'Upraviť objednávku',
            requiresAuth: true,
        }
    },
    {
        path: '/objednavky/:orderId/expedicia',
        name: 'orders.shipping.edit',
        components: {
            default: () => import('../components/order/OrderShippingEdit.vue'),
        },
        meta: {
            title: 'Expedícia objednávky',
            requiresAuth: true,
        }
    },
    {
        path: '/objednavky/:orderId/vratenie/create',
        name: 'orders.returns.create',
        components: {
            default: () => import('../components/order/OrderReturnCreate.vue'),
        },
        meta: { title: 'Nové vrátenie tovaru', requiresAuth: true }
    },
    {
        path: '/objednavky/:orderId/vratenie/:returnId',
        name: 'orders.returns.show',
        components: {
            default: () => import('../components/order/OrderReturnShow.vue'),
        },
        meta: { title: 'Detail vrátenia', requiresAuth: true }
    },
    {
        path: '/objednavky/create',
        name: 'orders.create',
        components: {
            default: () => import('../components/order/OrderCreate.vue'),
        },
        meta: {
            title: 'Nová objednávka',
            requiresAuth: true,
        }
    },
];

export default order;

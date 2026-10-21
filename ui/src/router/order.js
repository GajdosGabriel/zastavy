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
        path: '/objednavky/z-emailu',
        name: 'orders.email-drafts',
        components: {
            default: () => import('../components/order/EmailOrderDraftIndex.vue'),
        },
        meta: {
            title: 'Prijaté e-maily',
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
        // Expedícia je súčasťou detailu objednávky; stará adresa (záložky, odkazy
        // v e-mailoch obsluhe) otvorí detail rovno v režime prípravy dodacieho listu.
        path: '/objednavky/:orderId/expedicia',
        name: 'orders.shipping.edit',
        redirect: (to) => ({ name: 'orders.show', params: { orderId: to.params.orderId }, query: { expedicia: 1 } }),
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

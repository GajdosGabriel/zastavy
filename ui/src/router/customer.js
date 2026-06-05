import { PAGE_CUSTOMER } from '../constants';
const customer = [
    {
        path: '/zakaznici',
        name: PAGE_CUSTOMER.ROUTE,
        components: {
            default: () => import('../components/customer/CustomerIndex.vue'),
        },
        meta: {
            title: 'Zákazníci'
        }
    },
    {
        path: '/zakaznici/:customerId/show',
        name: 'customers.show',
        components: {
            default: () => import('../components/customer/CustomerShow.vue'),
        }
    },
    {
        path: '/zakaznici/:customerId/objednavky',
        name: 'customers.orders',
        components: {
            default: () => import('../components/customer/CustomerShow.vue'),
        },
        meta: {
            title: 'Objednávky zákazníka'
        }
    },
    {
        path: '/zakaznici/:customerId/edit',
        name: 'customers.edit',
        components: {
            default: () => import('../components/customer/CustomerEdit.vue'),
        }
    },
    {
        path: '/zakaznici/create',
        name: 'customers.create',
        components: {
            default: () => import('../components/customer/CustomerCreate.vue'),
        },
        meta: {
            title: 'Nový zákazník'
        }
    },
]

export default customer;

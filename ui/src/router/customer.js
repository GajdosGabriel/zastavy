import { PAGE_CUSTOMER } from '../constants';

const customer = [
    {
        path: '/zakaznici',
        name: PAGE_CUSTOMER.ROUTE,
        components: {
            default: () => import('../components/customer/CustomerIndex.vue'),
        },
        meta: {
            title: 'Zákazníci',
            superAdminOnly: true,
        },
    },
    {
        path: '/zakaznici/:customerId/show',
        name: 'customers.show',
        components: {
            default: () => import('../components/customer/CustomerShow.vue'),
        },
        meta: {
            superAdminOnly: true,
        },
    },
    {
        path: '/zakaznici/:customerId/objednavky',
        name: 'customers.orders',
        components: {
            default: () => import('../components/customer/CustomerShow.vue'),
        },
        meta: {
            title: 'Objednávky zákazníka',
            superAdminOnly: true,
        },
    },
    {
        path: '/zakaznici/:customerId/edit',
        name: 'customers.edit',
        components: {
            default: () => import('../components/customer/CustomerEdit.vue'),
        },
        meta: {
            superAdminOnly: true,
        },
    },
    {
        path: '/zakaznici/duplicity',
        name: 'customers.duplicates',
        components: {
            default: () => import('../components/customer/CustomerDuplicates.vue'),
        },
        meta: {
            title: 'Duplicitní zákazníci',
            superAdminOnly: true,
        },
    },
    {
        path: '/zakaznici/create',
        name: 'customers.create',
        components: {
            default: () => import('../components/customer/CustomerCreate.vue'),
        },
        meta: {
            title: 'Nový zákazník',
            superAdminOnly: true,
        },
    },
];

export default customer;

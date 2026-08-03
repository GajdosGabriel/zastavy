const admin = [
    {
        path: '/admin/doprava',
        name: 'shipping-methods.index',
        components: {
            default: () => import('../components/admin/ShippingMethodIndex.vue'),
        },
        meta: {
            title: 'Spôsoby dopravy',
            superAdminOnly: true,
        },
    },
    {
        path: '/admin/platby',
        name: 'payment-methods.index',
        components: {
            default: () => import('../components/admin/PaymentMethodIndex.vue'),
        },
        meta: {
            title: 'Spôsoby platby',
            superAdminOnly: true,
        },
    },
    {
        path: '/admin/kupony',
        name: 'coupons.index',
        components: {
            default: () => import('../components/admin/CouponIndex.vue'),
        },
        meta: {
            title: 'Kupóny',
            superAdminOnly: true,
        },
    },
    {
        path: '/admin/vlastnosti',
        name: 'attributes.index',
        components: {
            default: () => import('../components/admin/AttributeIndex.vue'),
        },
        meta: {
            title: 'Vlastnosti produktov',
            superAdminOnly: true,
        },
    },
    {
        path: '/admin/export-pouzivatelov',
        name: 'users.export.index',
        components: {
            default: () => import('../components/admin/UserExportIndex.vue'),
        },
        meta: {
            title: 'Export používateľov',
            superAdminOnly: true,
        },
    },
];

export default admin;

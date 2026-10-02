import { PAGE_USER } from '../constants';

const user = [
    {
        path: '/users',
        name: PAGE_USER.ROUTE,
        components: {
            default: () => import('../components/user/UserIndex.vue'),
        },
        meta: {
            title: 'Používatelia',
            superAdminOnly: true,
        },
    },
    {
        path: '/users/:userId/show',
        name: 'users.show',
        components: {
            default: () => import('../components/user/UserShow.vue'),
        },
        meta: {
            title: 'Používateľ',
            superAdminOnly: true,
        },
    },
    {
        path: '/users/:userId/edit',
        name: 'users.edit',
        components: {
            default: () => import('../components/user/UserEdit.vue'),
        },
        meta: {
            title: 'Upraviť používateľa',
            superAdminOnly: true,
        },
    },
    {
        path: '/users/create',
        name: 'users.create',
        components: {
            default: () => import('../components/user/UserCreate.vue'),
        },
        meta: {
            title: 'Nový používateľ',
            superAdminOnly: true,
        },
    },
];

export default user;

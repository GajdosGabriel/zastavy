import { PAGE_USER } from '../constants';

const user = [
    {
        path: '/users',
        name: PAGE_USER.ROUTE,
        components: {
            default: () => import('../components/user/UserIndex.vue'),
        },
        meta: {
            title: 'Pouzivatelia',
            superAdminOnly: true,
        },
    },
];

export default user;

import { PAGE_ANNOUNCEMENT } from '../constants';

const announcement = [
    {
        path: '/oznamy',
        name: PAGE_ANNOUNCEMENT.ROUTE,
        components: {
            default: () => import('../components/announcement/AnnouncementIndex.vue'),
        },
        meta: {
            title: 'Oznamy',
            superAdminOnly: true,
        },
    },
];

export default announcement;

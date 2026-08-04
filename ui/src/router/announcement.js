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
    {
        path: '/oznamy/create',
        name: 'announcements.create',
        components: {
            default: () => import('../components/announcement/AnnouncementForm.vue'),
        },
        meta: {
            title: 'Nový oznam',
            superAdminOnly: true,
        },
    },
    {
        path: '/oznamy/:announcementId/edit',
        name: 'announcements.edit',
        components: {
            default: () => import('../components/announcement/AnnouncementForm.vue'),
        },
        meta: {
            title: 'Upraviť oznam',
            superAdminOnly: true,
        },
    },
];

export default announcement;

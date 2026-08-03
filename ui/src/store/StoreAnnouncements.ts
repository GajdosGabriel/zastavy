import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import { PAGE_ANNOUNCEMENT, URL_BASE_API } from '../constants';
import useErrors from './StoreErrors';
import usePaginator from './StorePaginator';

export interface AnnouncementStatus {
    value: string;
    label: string;
}

export interface Announcement {
    id: number | null;
    placement: string;
    title: string;
    body: string;
    style_class: string;
    sort_order: number;
    published_from: string | null;
    published_until: string | null;
    status: AnnouncementStatus | string;
    [key: string]: any;
}

const emptyAnnouncement = (): Announcement => ({
    id: null,
    placement: 'top',
    title: '',
    body: '',
    style_class: 'bg-sky-700 text-gray-100',
    sort_order: 10,
    published_from: '',
    published_until: '',
    status: {
        value: 'active',
        label: 'Aktívny',
    },
});

interface AnnouncementsState {
    url: string;
    announcements: Announcement[];
    active: Announcement[];
    announcement: Announcement;
    statuses: AnnouncementStatus[];
    placements: any[];
    styleClasses: any[];
}

export const useAnnouncements = defineStore('announcements', {
    state: (): AnnouncementsState => ({
        url: PAGE_ANNOUNCEMENT.URL,
        announcements: [],
        active: [],
        announcement: emptyAnnouncement(),
        statuses: [],
        placements: [],
        styleClasses: [],
    }),

    getters: {
        getAnnouncements: (s): Announcement[] => s.announcements,
        getActiveTopAnnouncements: (s): Announcement[] => s.active.filter((item) => item.placement === 'top'),
        getActiveBottomAnnouncements: (s): Announcement[] => s.active.filter((item) => item.placement === 'bottom'),
        getStatuses: (s): AnnouncementStatus[] => s.statuses,
        getPlacements: (s): any[] => s.placements,
        getStyleClasses: (s): any[] => s.styleClasses,
    },

    actions: {
        payload() {
            const a = this.announcement;
            return {
                ...a,
                status: (a.status as AnnouncementStatus)?.value || a.status,
                published_from: a.published_from || null,
                published_until: a.published_until || null,
            };
        },

        async fetchAnnouncements(): Promise<void> {
            try {
                const paginator = usePaginator();
                const response = await axiosInstance.get(this.url);
                this.announcements = response.data.data;
                this.statuses = response.data.meta?.statuses || this.statuses;
                this.placements = response.data.meta?.placements || this.placements;
                this.styleClasses = response.data.meta?.style_classes || this.styleClasses;
                paginator.setPaginator(response.data.meta);
                paginator.setLinks(response.data.links);
            } catch (error) {
                useErrors().setErrors(error);
            }
        },

        async fetchActiveAnnouncements(placement: string | null = null): Promise<void> {
            try {
                const query = placement ? `?placement=${encodeURIComponent(placement)}` : '';
                const response = await axiosInstance.get(`${URL_BASE_API}/announcements/active${query}`);
                const announcements = response.data.data || [];

                if (placement) {
                    this.active = [
                        ...this.active.filter((item) => item.placement !== placement),
                        ...announcements,
                    ];
                } else {
                    this.active = announcements;
                }
            } catch (error) {
                useErrors().setErrors(error);
            }
        },

        async saveAnnouncement(): Promise<boolean> {
            try {
                const response = this.announcement.id
                    ? await axiosInstance.put(`${PAGE_ANNOUNCEMENT.URL}/${this.announcement.id}`, this.payload())
                    : await axiosInstance.post(PAGE_ANNOUNCEMENT.URL, this.payload());

                this.announcement = response.data.data;
                this.statuses = response.data.meta?.statuses || this.statuses;
                this.placements = response.data.meta?.placements || this.placements;
                this.styleClasses = response.data.meta?.style_classes || this.styleClasses;
                await this.fetchAnnouncements();
                this.resetAnnouncement();

                return true;
            } catch (error) {
                useErrors().setErrors(error);

                return false;
            }
        },

        /**
         * Rýchle zapnutie/vypnutie oznamu priamo v zozname — text zostáva
         * uložený, mení sa len status, takže sa dá kedykoľvek vrátiť späť.
         */
        async toggleAnnouncement(announcement: Announcement): Promise<void> {
            const current = (announcement.status as AnnouncementStatus)?.value ?? announcement.status;
            const next = current === 'active' ? 'hidden' : 'active';

            try {
                await axiosInstance.put(announcement.endpoints.update, {
                    ...announcement,
                    status: next,
                    published_from: announcement.published_from || null,
                    published_until: announcement.published_until || null,
                });
                await this.fetchAnnouncements();
            } catch (error) {
                useErrors().setErrors(error);
            }
        },

        editAnnouncement(announcement: Announcement): void {
            this.announcement = {
                ...emptyAnnouncement(),
                ...announcement,
                status: announcement.status || emptyAnnouncement().status,
            };
        },

        async destroyAnnouncement(announcement: Announcement): Promise<void> {
            if (!window.confirm('Skutočne vymazať oznam?')) {
                return;
            }

            try {
                await axiosInstance.delete(announcement.endpoints.destroy);
                await this.fetchAnnouncements();
            } catch (error) {
                useErrors().setErrors(error);
            }
        },

        resetAnnouncement(): void {
            this.announcement = emptyAnnouncement();
        },

        // Vlastná akcia (nezamieňať s paginátorovým setPaginator voľaným vo fetchAnnouncements).
        setPaginator(url: string): void {
            this.url = url;
            this.fetchAnnouncements();
        },
    },
});

export default useAnnouncements;

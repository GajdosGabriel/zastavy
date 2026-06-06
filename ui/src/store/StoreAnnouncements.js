import { computed, reactive } from "vue";
import axiosInstance from "../axiosInstance";
import { PAGE_ANNOUNCEMENT, URL_BASE_API } from "../constants";
import useErrors from "./StoreErrors";
import usePaginator from "./StorePaginator";

const { setErrors } = useErrors();
const { setPaginator, setLinks } = usePaginator();

const emptyAnnouncement = () => ({
    id: null,
    placement: "top",
    title: "",
    body: "",
    style_class: "bg-sky-700 text-gray-100",
    sort_order: 10,
    published_from: "",
    published_until: "",
    status: {
        value: "active",
        label: "Aktívny",
    },
});

const state = reactive({
    url: PAGE_ANNOUNCEMENT.URL,
    announcements: [],
    active: [],
    announcement: emptyAnnouncement(),
    statuses: [],
    placements: [],
    styleClasses: [],
});

const payload = () => ({
    ...state.announcement,
    status: state.announcement.status?.value || state.announcement.status,
    published_from: state.announcement.published_from || null,
    published_until: state.announcement.published_until || null,
});

const actions = {
    fetchAnnouncements: async () => {
        try {
            const response = await axiosInstance.get(state.url);
            state.announcements = response.data.data;
            state.statuses = response.data.meta?.statuses || state.statuses;
            state.placements = response.data.meta?.placements || state.placements;
            state.styleClasses = response.data.meta?.style_classes || state.styleClasses;
            setPaginator(response.data.meta);
            setLinks(response.data.links);
        } catch (error) {
            setErrors(error);
        }
    },

    fetchActiveAnnouncements: async (placement = null) => {
        try {
            const query = placement ? `?placement=${encodeURIComponent(placement)}` : "";
            const response = await axiosInstance.get(`${URL_BASE_API}/announcements/active${query}`);
            const announcements = response.data.data || [];

            if (placement) {
                state.active = [
                    ...state.active.filter((item) => item.placement !== placement),
                    ...announcements,
                ];
            } else {
                state.active = announcements;
            }
        } catch (error) {
            setErrors(error);
        }
    },

    saveAnnouncement: async () => {
        try {
            const response = state.announcement.id
                ? await axiosInstance.put(`${PAGE_ANNOUNCEMENT.URL}/${state.announcement.id}`, payload())
                : await axiosInstance.post(PAGE_ANNOUNCEMENT.URL, payload());

            state.announcement = response.data.data;
            state.statuses = response.data.meta?.statuses || state.statuses;
            state.placements = response.data.meta?.placements || state.placements;
            state.styleClasses = response.data.meta?.style_classes || state.styleClasses;
            await actions.fetchAnnouncements();
            actions.resetAnnouncement();

            return true;
        } catch (error) {
            setErrors(error);

            return false;
        }
    },

    editAnnouncement: (announcement) => {
        state.announcement = {
            ...emptyAnnouncement(),
            ...announcement,
            status: announcement.status || emptyAnnouncement().status,
        };
    },

    destroyAnnouncement: async (announcement) => {
        if (!window.confirm("Skutočne vymazať oznam?")) {
            return;
        }

        try {
            await axiosInstance.delete(announcement.endpoints.destroy);
            await actions.fetchAnnouncements();
        } catch (error) {
            setErrors(error);
        }
    },

    resetAnnouncement: () => {
        state.announcement = emptyAnnouncement();
    },

    setPaginator: (url) => {
        state.url = url;
        actions.fetchAnnouncements();
    },
};

const getters = {
    getAnnouncements: computed(() => state.announcements),
    getActiveTopAnnouncements: computed(() => state.active.filter((item) => item.placement === "top")),
    getActiveBottomAnnouncements: computed(() => state.active.filter((item) => item.placement === "bottom")),
    getStatuses: computed(() => state.statuses),
    getPlacements: computed(() => state.placements),
    getStyleClasses: computed(() => state.styleClasses),
};

export default () => ({
    state,
    ...actions,
    ...getters,
});

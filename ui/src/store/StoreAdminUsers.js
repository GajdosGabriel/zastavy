import axiosInstance from "../axiosInstance";
import { computed, reactive } from "vue";
import usePaginator from "./StorePaginator";
import useQuery from "./StoreQuery";
import { PAGE_USER } from "../constants";
import useErrors from "./StoreErrors";

const { setPaginator, setLinks } = usePaginator();
const { getQueryStringUrl } = useQuery();
const { setErrors } = useErrors();

const defaultState = {
    url: PAGE_USER.URL,
    users: [],
    user: {},
    roles: [],
    statuses: [],
};

const state = reactive(defaultState);

const getters = {
    getUsers: computed(() => state.users),
    getUser: computed(() => state.user),
    getRoles: computed(() => state.roles),
    getStatuses: computed(() => state.statuses),
};

const actions = {
    fetchUsers: async () => {
        try {
            const response = await axiosInstance.get(state.url + getQueryStringUrl.value);

            state.users = response.data.data;
            setPaginator(response.data.meta);
            setLinks(response.data.links);
        } catch (error) {
            state.users = [];
            setErrors(error);
        }
    },

    fetchUser: async (id) => {
        try {
            const response = await axiosInstance.get(`${PAGE_USER.URL}/${id}`);

            state.user = response.data.data;
            state.roles = response.data.meta?.roles || [];
            state.statuses = response.data.meta?.statuses || [];
        } catch (error) {
            state.user = {};
            setErrors(error);
        }
    },

    updateUser: async () => {
        try {
            const payload = {
                ...state.user,
                status: state.user.status?.value || state.user.status,
                customer_id: state.user.customer_id || null,
                roles: state.user.roles || [],
            };

            const response = await axiosInstance.put(`${PAGE_USER.URL}/${state.user.id}`, payload);

            state.user = response.data.data;
            state.roles = response.data.meta?.roles || state.roles;
            state.statuses = response.data.meta?.statuses || state.statuses;
            await actions.fetchUsers();

            return true;
        } catch (error) {
            setErrors(error);

            return false;
        }
    },

    setPaginator: (url) => {
        state.url = url;
        actions.fetchUsers();
    },

    resetUrl: () => {
        state.url = PAGE_USER.URL;
    },

    resetUser: () => {
        state.user = {};
    },
};

export default () => ({
    state,
    ...getters,
    ...actions,
});

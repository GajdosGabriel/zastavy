import axiosInstance from "../axiosInstance";
import { computed, reactive } from "vue";
import usePaginator from "./StorePaginator";
import useQuery from "./StoreQuery";
import { PAGE_USER } from "../constants";
import useErrors from "./StoreErrors";
import useUsers from "./StoreUsers";

const { setPaginator, setLinks } = usePaginator();
const { getQueryStringUrl } = useQuery();
const { setErrors } = useErrors();
const { getUser: getAuthUser } = useUsers();

const defaultState = {
    url: PAGE_USER.URL,
    users: [],
    user: {},
    roles: [],
    statuses: [],
    portalPermissions: [],
    customers: [],
};

const state = reactive(defaultState);

const getters = {
    getUsers: computed(() => state.users),
    getUser: computed(() => state.user),
    getRoles: computed(() => state.roles),
    getStatuses: computed(() => state.statuses),
    getPortalPermissions: computed(() => state.portalPermissions),
    getCustomers: computed(() => state.customers),
    canManageRoles: computed(() => getAuthUser.value?.roles?.some(role => ["super-admin"].includes(role))),
    canManagePermissions: computed(() => getAuthUser.value?.roles?.some(role => ["admin", "super-admin"].includes(role))),
    isPortalUser: computed(() => !!state.user?.customer_id),
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
            state.portalPermissions = response.data.meta?.portal_permissions || [];
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
            };

            if (getters.canManageRoles.value) {
                payload.roles = state.user.roles || [];
            } else {
                delete payload.roles;
            }

            if (getters.canManagePermissions.value && getters.isPortalUser.value) {
                payload.permissions = state.user.permissions || [];
            } else {
                delete payload.permissions;
            }

            const response = await axiosInstance.put(`${PAGE_USER.URL}/${state.user.id}`, payload);

            state.user = response.data.data;
            state.roles = response.data.meta?.roles || state.roles;
            state.statuses = response.data.meta?.statuses || state.statuses;
            state.portalPermissions = response.data.meta?.portal_permissions || state.portalPermissions;
            await actions.fetchUsers();

            return true;
        } catch (error) {
            setErrors(error);

            return false;
        }
    },

    fetchCreateOptions: async () => {
        try {
            const response = await axiosInstance.get(`${PAGE_USER.URL}/create`);
            state.roles = response.data.meta?.roles || [];
            state.statuses = response.data.meta?.statuses || [];
            state.portalPermissions = response.data.meta?.portal_permissions || [];
            state.customers = response.data.meta?.customers || [];
            state.user = { roles: [], permissions: [] };
        } catch (error) {
            setErrors(error);
        }
    },

    storeUser: async () => {
        try {
            const payload = {
                ...state.user,
                status: state.user.status?.value || state.user.status,
            };

            if (getters.canManageRoles.value) {
                payload.roles = state.user.roles || [];
            } else {
                delete payload.roles;
            }

            if (getters.canManagePermissions.value) {
                payload.permissions = state.user.permissions || [];
            } else {
                delete payload.permissions;
            }

            const response = await axiosInstance.post(PAGE_USER.URL, payload);
            state.user = response.data.data;
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

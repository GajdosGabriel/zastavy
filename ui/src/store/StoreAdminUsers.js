import axiosInstance from "../axiosInstance";
import { computed, reactive } from "vue";
import usePaginator from "./StorePaginator";
import useQuery from "./StoreQuery";
import { PAGE_USER } from "../constants";

const { setPaginator, setLinks } = usePaginator();
const { getQueryStringUrl } = useQuery();

const defaultState = {
    url: PAGE_USER.URL,
    users: [],
};

const state = reactive(defaultState);

const getters = {
    getUsers: computed(() => state.users),
};

const actions = {
    fetchUsers: async () => {
        const response = await axiosInstance.get(state.url + getQueryStringUrl.value);

        state.users = response.data.data;
        setPaginator(response.data.meta);
        setLinks(response.data.links);
    },

    setPaginator: (url) => {
        state.url = url;
        actions.fetchUsers();
    },

    resetUrl: () => {
        state.url = PAGE_USER.URL;
    },
};

export default () => ({
    state,
    ...getters,
    ...actions,
});

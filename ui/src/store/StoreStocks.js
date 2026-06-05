import { reactive, computed } from "vue";
import axiosInstance from "../axiosInstance";
import usePaginator from './StorePaginator';
import useErrors from './StoreErrors';
import useQuery from './StoreQuery';
import templateStock from "../models/templateStock";
import { PAGE_STOCK } from "../constants";

const { setPaginator, setLinks } = usePaginator();
const { setErrors } = useErrors();
const { state: q, setQuery, removeQuery } = useQuery();

const defaultState = reactive({
    searchUrl: "",
    url: PAGE_STOCK.URL,
    stocks: [],
    create: {
        product_id: "",
        price: "",
        quantity: "",
    }
});
const state = reactive(defaultState);

const getters = {
    getStocks: computed(() => templateStock(state.stocks)),
};
const actions = {
    fetchStocks: async () => {
        try {
            const response = await axiosInstance.get(state.url + q.stringForUrl);
            state.stocks = await response.data.data;
            setPaginator(response.data.meta);
            setLinks(response.data.links);
        } catch (e) {
            setErrors(e);
        }
    },

    storeStock: async () => {
        try {
            const response = await axiosInstance.post(PAGE_STOCK.URL, state.create);
            actions.fetchStocks();
            state.create = {};
        } catch (e) {
            setErrors(e);
        }
    },

    updateStock: async (data) => {
        try {
            const response = await axiosInstance.put(PAGE_STOCK.URL + data.id, data);
            actions.fetchStocks();
        } catch (e) {
            setErrors(e);
        }
    },

    destroyStock: async (id) => {
        if (!window.confirm("Skutočne vymazať!")) {
            return;
        }
        await axiosInstance.delete(PAGE_STOCK.URL + '/' + id);
        actions.fetchStocks();
    },

    setPaginator: (url) => {
        state.url = url;
        actions.fetchStocks();
    },
    fetchSearchInput: (val) => {
        state.searchUrl = val;
        actions.fetchStocks();
    },
};

export default () => ({
    state,
    ...actions,
    ...getters,
});

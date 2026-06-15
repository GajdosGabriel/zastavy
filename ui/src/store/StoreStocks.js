import { reactive, computed, ref } from "vue";
import axiosInstance from "../axiosInstance";
import usePaginator from './StorePaginator';
import useErrors from './StoreErrors';
import useQuery from './StoreQuery';
import { PAGE_STOCK } from "../constants";

const { setPaginator, setLinks } = usePaginator();
const { setErrors } = useErrors();
const { state: q } = useQuery();

const state = reactive({
    url: PAGE_STOCK.URL,
    stocks: [],
    summary: [],
    selectedProductId: null,
    create: {
        product_id: null,
        quantity: '',
        price: '',
        note: '',
    },
});

const getters = {
    getStocks: computed(() => state.stocks),
    getSummary: computed(() => state.summary),
    getSelectedProductId: computed(() => state.selectedProductId),
    getSelectedProduct: computed(() =>
        state.summary.find(p => p.product_id === state.selectedProductId) ?? null
    ),
};

const actions = {
    fetchStocks: async () => {
        try {
            const parts = [
                q.stringForUrl ? q.stringForUrl.slice(1) : '',
                state.selectedProductId ? `byProduct=${state.selectedProductId}` : '',
            ].filter(Boolean);
            const qs = parts.length ? `?${parts.join('&')}` : '';
            const response = await axiosInstance.get(state.url + qs);
            state.stocks = response.data.data;
            setPaginator(response.data.meta);
            setLinks(response.data.links);
        } catch (e) {
            setErrors(e);
        }
    },

    fetchSummary: async () => {
        try {
            const response = await axiosInstance.get(PAGE_STOCK.URL + '/summary');
            state.summary = response.data.data;
        } catch (e) {
            setErrors(e);
        }
    },

    selectProduct: (productId) => {
        state.selectedProductId = state.selectedProductId === productId ? null : productId;
        actions.fetchStocks();
    },

    storeStock: async () => {
        try {
            await axiosInstance.post(PAGE_STOCK.URL, state.create);
            state.create = { product_id: null, quantity: '', price: '', note: '' };
            await actions.fetchSummary();
        } catch (e) {
            setErrors(e);
        }
    },

    destroyStock: async (id) => {
        if (!window.confirm('Skutočne vymazať pohyb?')) return;
        await axiosInstance.delete(PAGE_STOCK.URL + '/' + id);
        actions.fetchStocks();
        actions.fetchSummary();
    },

    setPaginator: (url) => {
        state.url = url;
        actions.fetchStocks();
    },

    resetUrl: () => {
        state.url = PAGE_STOCK.URL;
        state.selectedProductId = null;
    },
};

export default () => ({
    state,
    ...actions,
    ...getters,
});

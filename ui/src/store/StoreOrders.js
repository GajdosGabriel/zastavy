import { reactive, computed } from "vue";
import axios from 'axios';
import axiosInstance from "../axiosInstance";
import useUsers from "./StoreUsers";
import useOrderProducts from "./StoreOrderProducts";
import usePaginator from './StorePaginator';
import useErrors from './StoreErrors';
import useQuery from './StoreQuery';
import { PAGE_ORDER } from "../constants";

import templateOrder from "../models/templateOrder";
import templateCustomer from "../models/templateCustomer";


const { setPaginator, setLinks } = usePaginator();
const { setErrors } = useErrors();
const { setOrderProducts } = useOrderProducts();
const { state: q, setQuery, removeQuery } = useQuery();


const defaultState = reactive({
    statement: {
        markSelected: false,
    },
    isLoading: false,
    url: PAGE_ORDER.URL,
    orders: [],
    order: {},
    customer: {},
    statistics: {
        orders: {},
        products: [],
        undelivered_products: [],
    },
});
const state = reactive(defaultState);
let ordersRequestId = 0;

const getters = {
    customer: computed(() => templateCustomer(state.customer)),
    getOrders: computed(() => state.orders),
    getOrder: computed(() => templateOrder(state.order)),
    getOrderStatistics: computed(() => state.statistics),
    isOrderFinished: computed(() => state.order.isFinished),
};

const mutations = {};

const actions = {
    fetchOrders: async () => {
        const requestId = ++ordersRequestId;
        state.isLoading = true;

        try {
            const response = await axiosInstance.get(state.url + q.stringForUrl);

            if (requestId !== ordersRequestId) {
                return;
            }

            state.orders = await response.data.data;
            setPaginator(response.data.meta);
            setLinks(response.data.links);
        } catch (e) {
            setErrors(e);
        } finally {
            if (requestId === ordersRequestId) {
                state.isLoading = false;
            }
        }
    },

    fetchOrderStatistics: async () => {
        try {
            const response = await axiosInstance.get(PAGE_ORDER.URL + '/statistics' + q.stringForUrl);
            state.statistics = response.data.data;
        } catch (e) {
            setErrors(e);
        }
    },

    fetchOrder: async (id) => {
        try {
            const response = await axiosInstance.get(PAGE_ORDER.URL + '/' + id);
            state.order = await response.data;
            setOrderProducts(response.data.orderProducts);
            state.customer = await response.data.customer;
        } catch (e) {
            setErrors(e);
        }
        // When order was opened
        if (state.order.isOpened === 0) {
            await actions.updateOrder({
                id: id,
                isOpened: 1,
            });
            useUsers().fetchUser();
        }
    },

    updateOrder: async (data) => {
        try {
            const response = await axiosInstance.put(PAGE_ORDER.URL + '/' + data.id, data);
            actions.fetchOrders();
        } catch (e) {
            setErrors(e);
        }
    },

    storeOrder: async (payload) => {
        try {
            const response = await axiosInstance.post(PAGE_ORDER.URL, payload);
            const order = response.data.data;

            if (order) {
                state.orders.unshift(order);
                state.order = order;
                state.customer = order.customer || {};
            }

            return order;
        } catch (e) {
            setErrors(e);
            return null;
        }
    },

    destroyOrder: async (url) => {
        if (!window.confirm("Skutočne vymazať!")) {
            return;
        }
        try {
            await axiosInstance.delete(url).then((res) => {
                const index = state.orders.findIndex(item => item.id === res.data.id);
                if (index !== -1) {
                    state.orders.splice(index, 1);
                };
            });
        } catch (e) {
            setErrors(e);
        }

    },

    setOrders: (orders) => {
        state.orders = orders;
    },

    setPaginator: (url) => {
        state.url = url;
        actions.fetchOrders();
    },
    resetUrl: () => {
        state.url = PAGE_ORDER.URL;
    },

    setOrder: (order) => {
        state.order = order;

        const index = state.orders.findIndex((item) => item.id === order.id);

        if (index !== -1) {
            state.orders.splice(index, 1, order);
        }
    },

    resetOrder: () => {
        state.order = {};
    },

    clickToMark: async (order) => {
        try {
            await axiosInstance.post(order.mark.endpoint).then((res) => {
                function replaceItem(array, oldItem, newItem) {
                    return array.reduce((acc, curr) => {
                        if (curr.id === oldItem.id) {
                            acc.push(newItem);
                        } else {
                            acc.push(curr);
                        }
                        return acc;
                    }, []);
                }
                state.orders = replaceItem(state.orders, order, res.data.data);
            });
        } catch (e) {
            setErrors(e);
        }
    },
    fetchMarkSelected: () => {

        if (state.statement.markSelected) {
            removeQuery("isMarked=true")
            state.statement.markSelected = false

        } else {
            setQuery("isMarked=true")
            state.statement.markSelected = true

        };

        actions.fetchOrders();
    },
};

export default () => ({
    state,
    ...actions,
    ...getters,
    ...mutations,
});



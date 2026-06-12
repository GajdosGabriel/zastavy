import { computed, reactive, readonly } from "vue";
import axiosInstance from "../axiosInstance";
import StoreOrders from "./StoreOrders";
import useErrors from './StoreErrors';

const { setErrors } = useErrors();

const state = reactive({
    returns: [],
    currentReturn: null,
});

const getters = {
    getReturns: computed(() => state.returns),
    getCurrentReturn: computed(() => state.currentReturn),
};

const actions = {
    fetchReturns: async (orderId) => {
        try {
            const response = await axiosInstance.get(`/orders/${orderId}/returns`);
            state.returns = response.data.data;
        } catch (e) {
            setErrors(e);
        }
    },

    fetchReturn: async (orderId, returnId) => {
        try {
            const response = await axiosInstance.get(`/orders/${orderId}/returns/${returnId}`);
            state.currentReturn = response.data.data;
        } catch (e) {
            setErrors(e);
        }
    },

    storeReturn: async (orderId, payload) => {
        try {
            const response = await axiosInstance.post(`/orders/${orderId}/returns`, payload);
            const newReturn = response.data.data;
            state.returns.unshift(newReturn);
            return newReturn;
        } catch (e) {
            setErrors(e);
        }
    },

    updateReturn: async (orderId, returnId, payload) => {
        try {
            const response = await axiosInstance.put(`/orders/${orderId}/returns/${returnId}`, payload);
            const updated = response.data.data;
            state.currentReturn = updated;
            const idx = state.returns.findIndex(r => r.id === returnId);
            if (idx !== -1) state.returns[idx] = updated;
            return updated;
        } catch (e) {
            setErrors(e);
        }
    },

    processReturn: async (orderId, returnId) => {
        try {
            const response = await axiosInstance.post(`/orders/${orderId}/returns/${returnId}/process`);
            const updated = response.data.data;
            state.currentReturn = updated;
            const idx = state.returns.findIndex(r => r.id === returnId);
            if (idx !== -1) state.returns[idx] = updated;

            const updatedOrder = response.data.order?.data ?? response.data.order;
            if (updatedOrder) {
                StoreOrders().setOrder(updatedOrder);
            }

            return updated;
        } catch (e) {
            setErrors(e);
        }
    },

    cancelReturn: async (orderId, returnId) => {
        try {
            const response = await axiosInstance.post(`/orders/${orderId}/returns/${returnId}/cancel`);
            const updated = response.data.data;
            state.currentReturn = updated;
            const idx = state.returns.findIndex(r => r.id === returnId);
            if (idx !== -1) state.returns[idx] = updated;
            return updated;
        } catch (e) {
            setErrors(e);
        }
    },

    deleteReturn: async (orderId, returnId) => {
        try {
            await axiosInstance.delete(`/orders/${orderId}/returns/${returnId}`);
            state.returns = state.returns.filter(r => r.id !== returnId);
            return true;
        } catch (e) {
            setErrors(e);
        }
    },
};

export default () => ({
    state: readonly(state),
    ...actions,
    ...getters,
});

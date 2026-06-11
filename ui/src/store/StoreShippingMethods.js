import { computed, reactive } from "vue";
import axiosInstance from "../axiosInstance";
import useErrors from "./StoreErrors";

const { setErrors } = useErrors();

const BASE_URL = '/admin/shipping-methods';

const empty = () => ({
    id: null,
    name: '',
    price: '',
    free_from_price: '',
    active: true,
    sort_order: 99,
});

const state = reactive({
    shippingMethods: [],
    trashedMethods: [],
    shippingMethod: empty(),
});

const actions = {
    fetchShippingMethods: async () => {
        try {
            const { data } = await axiosInstance.get(BASE_URL);
            state.shippingMethods = data.data;
            state.trashedMethods  = data.trashed ?? [];
        } catch (e) {
            setErrors(e);
        }
    },

    saveShippingMethod: async () => {
        try {
            const { id, ...payload } = state.shippingMethod;
            if (id) {
                await axiosInstance.put(`${BASE_URL}/${id}`, payload);
            } else {
                await axiosInstance.post(BASE_URL, payload);
            }
            await actions.fetchShippingMethods();
            actions.resetShippingMethod();
            return true;
        } catch (e) {
            setErrors(e);
            return false;
        }
    },

    editShippingMethod: (method) => {
        state.shippingMethod = { ...empty(), ...method };
    },

    destroyShippingMethod: async (method) => {
        if (!window.confirm('Skutočne vymazať spôsob dopravy?')) return;
        try {
            await axiosInstance.delete(`${BASE_URL}/${method.id}`);
            await actions.fetchShippingMethods();
        } catch (e) {
            setErrors(e);
        }
    },

    restoreShippingMethod: async (method) => {
        try {
            await axiosInstance.post(`${BASE_URL}/${method.id}/restore`);
            await actions.fetchShippingMethods();
        } catch (e) {
            setErrors(e);
        }
    },

    resetShippingMethod: () => {
        state.shippingMethod = empty();
    },
};

const getters = {
    getShippingMethods: computed(() => state.shippingMethods),
    getTrashedMethods:  computed(() => state.trashedMethods),
};

export default () => ({
    state,
    ...actions,
    ...getters,
});

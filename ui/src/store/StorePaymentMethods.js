import { computed, reactive } from "vue";
import axiosInstance from "../axiosInstance";
import useErrors from "./StoreErrors";

const { setErrors } = useErrors();

const BASE_URL = '/admin/payment-methods';

const empty = () => ({
    id: null,
    name: '',
    fee: '',
    type: 'bank_transfer',
    active: true,
    sort_order: 99,
});

const TYPES = [
    { value: 'card',             label: 'Platobná karta' },
    { value: 'bank_transfer',    label: 'Bankový prevod' },
    { value: 'cash_on_delivery', label: 'Dobierka' },
];

const state = reactive({
    paymentMethods: [],
    trashedMethods: [],
    paymentMethod: empty(),
});

const actions = {
    fetchPaymentMethods: async () => {
        try {
            const { data } = await axiosInstance.get(BASE_URL);
            state.paymentMethods = data.data;
            state.trashedMethods  = data.trashed ?? [];
        } catch (e) {
            setErrors(e);
        }
    },

    savePaymentMethod: async () => {
        try {
            const { id, ...payload } = state.paymentMethod;
            if (id) {
                await axiosInstance.put(`${BASE_URL}/${id}`, payload);
            } else {
                await axiosInstance.post(BASE_URL, payload);
            }
            await actions.fetchPaymentMethods();
            actions.resetPaymentMethod();
            return true;
        } catch (e) {
            setErrors(e);
            return false;
        }
    },

    editPaymentMethod: (method) => {
        state.paymentMethod = { ...empty(), ...method };
    },

    destroyPaymentMethod: async (method) => {
        if (!window.confirm('Skutočne vymazať spôsob platby?')) return;
        try {
            await axiosInstance.delete(`${BASE_URL}/${method.id}`);
            await actions.fetchPaymentMethods();
        } catch (e) {
            setErrors(e);
        }
    },

    restorePaymentMethod: async (method) => {
        try {
            await axiosInstance.post(`${BASE_URL}/${method.id}/restore`);
            await actions.fetchPaymentMethods();
        } catch (e) {
            setErrors(e);
        }
    },

    resetPaymentMethod: () => {
        state.paymentMethod = empty();
    },
};

const getters = {
    getPaymentMethods: computed(() => state.paymentMethods),
    getTrashedMethods: computed(() => state.trashedMethods),
    getPaymentTypes:   computed(() => TYPES),
};

export default () => ({
    state,
    ...actions,
    ...getters,
});

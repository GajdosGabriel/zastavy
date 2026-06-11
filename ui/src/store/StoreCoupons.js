import { computed, reactive } from "vue";
import axiosInstance from "../axiosInstance";
import useErrors from "./StoreErrors";

const { setErrors } = useErrors();

const BASE_URL = '/admin/coupons';

const empty = () => ({
    id: null,
    code: '',
    type: 'percent',
    value: '',
    min_order_price: '',
    usage_limit: '',
    valid_from: '',
    valid_to: '',
    active: true,
});

const state = reactive({
    coupons: [],
    trashedCoupons: [],
    coupon: empty(),
});

const generateCode = () => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 8; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return code;
};

const actions = {
    fetchCoupons: async () => {
        try {
            const { data } = await axiosInstance.get(BASE_URL);
            state.coupons        = data.data;
            state.trashedCoupons = data.trashed ?? [];
        } catch (e) {
            setErrors(e);
        }
    },

    saveCoupon: async () => {
        try {
            const { id, ...payload } = state.coupon;
            const body = {
                ...payload,
                min_order_price: payload.min_order_price || null,
                usage_limit:     payload.usage_limit     || null,
                valid_from:      payload.valid_from      || null,
                valid_to:        payload.valid_to        || null,
            };
            if (id) {
                await axiosInstance.put(`${BASE_URL}/${id}`, body);
            } else {
                await axiosInstance.post(BASE_URL, body);
            }
            await actions.fetchCoupons();
            actions.resetCoupon();
            return true;
        } catch (e) {
            setErrors(e);
            return false;
        }
    },

    editCoupon: (coupon) => {
        state.coupon = { ...empty(), ...coupon };
    },

    destroyCoupon: async (coupon) => {
        if (!window.confirm('Skutočne vymazať kupón?')) return;
        try {
            await axiosInstance.delete(`${BASE_URL}/${coupon.id}`);
            await actions.fetchCoupons();
        } catch (e) {
            setErrors(e);
        }
    },

    restoreCoupon: async (coupon) => {
        try {
            await axiosInstance.post(`${BASE_URL}/${coupon.id}/restore`);
            await actions.fetchCoupons();
        } catch (e) {
            setErrors(e);
        }
    },

    generateCode: () => {
        state.coupon.code = generateCode();
    },

    resetCoupon: () => {
        state.coupon = empty();
    },
};

const getters = {
    getCoupons:       computed(() => state.coupons),
    getTrashedCoupons: computed(() => state.trashedCoupons),
};

export default () => ({
    state,
    ...actions,
    ...getters,
});

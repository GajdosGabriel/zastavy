import { computed, reactive } from "vue";
import axiosInstance from "../axiosInstance";

const state = reactive({
    shippingMethods: [],
    paymentMethods: [],
    selectedShippingId: null,
    selectedPaymentId: null,
    couponMode: null,
    couponCode: '',
    couponData: null,
    couponError: null,
    couponLoading: false,
});

const getters = {
    getShippingMethods: computed(() => state.shippingMethods),
    getPaymentMethods:  computed(() => state.paymentMethods),
    getSelectedShipping: computed(() =>
        state.shippingMethods.find(m => m.id === state.selectedShippingId) ?? null
    ),
    getSelectedPayment: computed(() =>
        state.paymentMethods.find(m => m.id === state.selectedPaymentId) ?? null
    ),
    getCouponMode:   computed(() => state.couponMode),
    getCouponData:   computed(() => state.couponData),
    getCouponError:  computed(() => state.couponError),
    getCouponCode:   computed(() => state.couponCode),
    isCouponLoading: computed(() => state.couponLoading),
    getWantsCoupon:  computed(() => state.couponMode === 'get'),

    shippingPrice: (cartTotal) => {
        const method = state.shippingMethods.find(m => m.id === state.selectedShippingId);
        if (!method) return 0;
        if (method.free_from_price !== null && cartTotal >= parseFloat(method.free_from_price)) return 0;
        return parseFloat(method.price) || 0;
    },

    paymentFee: computed(() => {
        const method = state.paymentMethods.find(m => m.id === state.selectedPaymentId);
        return method ? parseFloat(method.fee) || 0 : 0;
    }),

    discountAmount: computed(() => state.couponData?.discount ?? 0),
};

const actions = {
    fetchShippingMethods: async () => {
        const { data } = await axiosInstance.get('/shipping-methods');
        state.shippingMethods = data.data ?? [];
        if (state.shippingMethods.length && !state.selectedShippingId) {
            state.selectedShippingId = state.shippingMethods[0].id;
        }
    },

    fetchPaymentMethods: async () => {
        const { data } = await axiosInstance.get('/payment-methods');
        state.paymentMethods = data.data ?? [];
        if (state.paymentMethods.length && !state.selectedPaymentId) {
            state.selectedPaymentId = state.paymentMethods[0].id;
        }
    },

    selectShipping: (id) => { state.selectedShippingId = id; },
    selectPayment:  (id) => { state.selectedPaymentId = id; },

    setCouponMode: (mode) => {
        state.couponMode = mode;
        if (mode === 'get') {
            state.couponCode  = '';
            state.couponData  = null;
            state.couponError = null;
        }
    },

    setCouponCode: (code) => {
        state.couponCode = code;
        state.couponData = null;
        state.couponError = null;
    },

    validateCoupon: async (cartTotal) => {
        if (!state.couponCode.trim()) return;
        state.couponLoading = true;
        state.couponError = null;
        state.couponData = null;
        try {
            const { data } = await axiosInstance.post('/coupons/validate', {
                code: state.couponCode,
                cart_total: cartTotal,
            });
            state.couponData = data.data;
        } catch (e) {
            state.couponError = e?.response?.data?.message ?? 'Kupón je neplatný.';
        } finally {
            state.couponLoading = false;
        }
    },

    clearCoupon: () => {
        state.couponCode = '';
        state.couponData = null;
        state.couponError = null;
    },

    reset: () => {
        state.selectedShippingId = state.shippingMethods[0]?.id ?? null;
        state.selectedPaymentId  = state.paymentMethods[0]?.id  ?? null;
        state.couponMode = null;
        actions.clearCoupon();
    },
};

export default () => ({
    state,
    ...getters,
    ...actions,
});

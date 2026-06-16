import { computed, reactive, watch } from "vue";
import axiosInstance from "../axiosInstance";
import useErrors from './StoreErrors';
import useCustomer from './StoreCustomers';
import useCheckoutOptions from './StoreCheckoutOptions';

const { getCustomer, resetCustomer } = useCustomer();
const { setErrors } = useErrors();

const CART_STORAGE_KEY = 'form';
const CUSTOMER_STORAGE_KEY = 'customer';

const defaulState = {
    checkout: {
        grandTotal: 0,
        grandQuantity: 0,
    },
    carts: [],
    note: '',
};

const state = reactive(defaulState);

const toPositiveNumber = (value, fallback = 0) => {
    const number = Number(value);
    return Number.isFinite(number) && number > 0 ? number : fallback;
};

const normalizeCartItem = (item) => {
    const minOrder = toPositiveNumber(item?.min_order, 1);
    const inputOrder = Math.max(toPositiveNumber(item?.input_order, minOrder), minOrder);

    return {
        ...item,
        active_price: toPositiveNumber(item?.active_price),
        input_order: inputOrder,
        min_order: minOrder,
    };
};

const readJsonStorage = (key, fallback) => {
    try {
        const value = localStorage.getItem(key);
        return value ? JSON.parse(value) : fallback;
    } catch {
        localStorage.removeItem(key);
        return fallback;
    }
};

const getters = {
    getCheckout: computed(() => state.checkout),
    getCarts: computed(() => state.carts)
};

const mutations = {
    grandCalculate: () => {
        state.checkout.grandTotal = state.carts.reduce((sum, item) => {
            return sum + (toPositiveNumber(item.active_price) * toPositiveNumber(item.input_order));
        }, 0);

        state.checkout.grandQuantity = state.carts.reduce((sum, item) => {
            return sum + toPositiveNumber(item.input_order);
        }, 0);
    }
};

const actions = {
    submitCartToIndex: (data) => {
        const cartItem = normalizeCartItem(data);
        const existingItem = state.carts.find(item => item.id === cartItem.id);

        if (existingItem) {
            existingItem.input_order = toPositiveNumber(existingItem.input_order) + cartItem.input_order;
        } else {
            state.carts.push(cartItem);
        }
    },

    updateCartQuantity: (cart, value) => {
        const existingItem = state.carts.find(item => item.id === cart.id);

        if (!existingItem) {
            return;
        }

        existingItem.input_order = Math.max(
            toPositiveNumber(value, existingItem.min_order),
            toPositiveNumber(existingItem.min_order, 1)
        );
    },

    setlocalStorage: () => {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(state.carts));
    },

    setlocalStorageCustomer: () => {
        localStorage.setItem(CUSTOMER_STORAGE_KEY, JSON.stringify(getCustomer.value));
    },

    getlocalStorage: () => {
        const carts = readJsonStorage(CART_STORAGE_KEY, []);
        state.carts = Array.isArray(carts) ? carts.map(normalizeCartItem) : [];
        mutations.grandCalculate();
    },

    removeCart: (cart) => {
        state.carts = state.carts.filter((item) => item.id != cart.id);
    },

    resetCarts: () => {
        state.carts = [];
    },

    storeCheckout: async () => {
        const { state: optionsState, getCouponCode, getWantsCoupon } = useCheckoutOptions();
        try {
            await axiosInstance.post("/checkouts", {
                customer: getCustomer.value,
                orderProducts: state.carts,
                note: state.note || null,
                shipping_method_id: optionsState.selectedShippingId,
                payment_method_id:  optionsState.selectedPaymentId,
                coupon_code:        getCouponCode.value || null,
                wants_coupon:       getWantsCoupon.value,
            });
            localStorage.removeItem(CUSTOMER_STORAGE_KEY);
            state.carts = [];
            state.note = '';
            resetCustomer();
            useCheckoutOptions().reset();
            return true;
        } catch (e) {
            setErrors(e);
            return false;
        }
    }
};

export default () => ({
    state,
    ...actions,
    ...getters,
    ...mutations,
});

watch(state, () => {
    mutations.grandCalculate();
    actions.setlocalStorage();
});

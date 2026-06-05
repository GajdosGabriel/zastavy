import { computed, reactive, readonly, watch } from "vue";
import axiosInstance from "../axiosInstance";
import useErrors from './StoreErrors';
import useCustomer from './StoreCustomers';
import useOrder from './StoreOrders';
import { URL_BASE, URL_BASE_API } from "../constants";

const { getCustomer, resetCustomer } = useCustomer();
const { setErrors } = useErrors();
const { state: order } = useOrder();

const defaulState = {
    checkout: {},
    carts: [],
};


const state = reactive(defaulState);

const getters = {
    getCheckout: computed(() => state.checkout),
    getCarts: computed(() => state.carts)
};

const mutations = {

    grandCalculate: () => {

        state.checkout.grandTotal = state.carts?.reduce((sum, item) => {
            return (sum += Number(item.active_price * item.input_order));
        }, 0);

        state.checkout.grandQuantity = state.carts?.reduce((sum, item) => {
            return (sum += Number(item.input_order));
        }, 0);
    }
};
const actions = {

    submitCartToIndex: (data) => {
        const existingItem = state.carts.find(item => item.id === data.id);

        if (existingItem) {
            existingItem.input_order += data.input_order; // Pripočíta množstvo
        } else {
            state.carts.push({ ...data }); // Pridá celý objekt s pôvodným počtom kusov
        }
    },

    setlocalStorage: () => {
        let form_serialized = JSON.stringify(state.carts);
        localStorage.setItem('form', form_serialized);
    },
    setlocalStorageCustomer: () => {
        let form_serialized = JSON.stringify(getCustomer.value);
        localStorage.setItem('customer', form_serialized);
    },

    getlocalStorage: () => {
        state.carts = JSON.parse(localStorage.getItem('form')) || [];
    },

    removeCart: (cart) => {
        state.carts = state.carts.filter((item) => item.id != cart.id);
    },

    resetCarts: () => {
        state.carts = [];
    },

    storeCheckout: async () => {
        try {
            await axiosInstance.post("/checkouts", {
                customer: getCustomer.value,
                orderProducts: state.carts,
                orderNotice: order.order.notice,
            });
            actions.setlocalStorageCustomer();
        } catch (e) {
            if (e.response) {
                setErrors(e);
            }
        }


        // Clear carts after store
        state.carts = [];
        resetCustomer();
    }
};

export default () => ({
    // state: readonly(state),
    state,
    ...actions,
    ...getters,
    ...mutations,
});


watch(state, () => {
    mutations.grandCalculate();
    actions.setlocalStorage();
});
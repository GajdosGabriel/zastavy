import { computed, reactive, readonly } from "vue";
import axiosInstance from "../axiosInstance";
import StoreOrders from "./StoreOrders";
import StoreOrderProducts from "./StoreOrderProducts";
import useErrors from './StoreErrors';
const { setErrors } = useErrors();

const defaulState = {
    shipping: '',
};

const state = reactive(defaulState);

const getters = {
    shipping: computed(() => state.shipping),
};
const actions = {
    getShippings: async () => {
        // let response = await axios.get("/carts");
        // state.carts = response.data.data;
    },
    storeShipping: async (order, options = {}) => {
        try {
            const response = await axiosInstance.post(
                "/orders/" + order.id + "/shippings",
                options
            );

            state.shipping = response.data.data;

            const updatedOrder = response.data.order?.data ?? response.data.order;

            if (updatedOrder) {
                StoreOrders().setOrder(updatedOrder);
                StoreOrderProducts().setOrderProducts(updatedOrder.orderProducts ?? []);
            }
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

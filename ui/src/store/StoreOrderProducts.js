import { reactive, computed, watch } from "vue";
import axiosInstance from "../axiosInstance";



const defaultState = reactive({
    statement: {
        grandQuantity: 0,
        grandTotal: 0,
    },
    orderProducts: [],
    orderProduct: {},
});

const state = reactive(defaultState);

const getters = {
    getStatement: computed(() => state.statement),
    getOrderProducts: computed(() => state.orderProducts),
    getOrderProduct: computed(() => state.orderProduct),

};

const mutators = {
    findProduct: (productId) => {
        state.products.find((item) => item.id == productId);
        alert(productId)

    },
    grandCalculate: () => {

        state.statement.grandTotal = state.orderProducts.reduce((sum, item) => {
            return (sum += Number(item.price * item.quantity));
        }, 0);

        state.statement.grandQuantity = state.orderProducts.reduce((sum, item) => {
            return (sum += Number(item.quantity));
        }, 0);
    }
};

const actions = {

    fetchOrderProducts: async (id) => {
        let response = await axiosInstance.get("/orders/" + id + "/orderProducts");
        state.orderProducts = await response.data.data;
    },

    // const getOrder = async (id) => {
    //     let response = await axiosInstance.get("/orders/" + id);
    //     state.order = await response.data;
    // };

    updateOrderProducts: async (item) => {
        await axiosInstance.put(item.endpoints.update, item);
    },

    setOrderProducts: (items) => {
        state.orderProducts = items;
    },

    
    addOrderProduct: () => {
        state.orderProducts.push(state.orderProducts[0]);
    },


    destroyOrderProducts: async (item) => {
        if (!window.confirm("Skutočne vymazať!")) {
            return;
        }
        await axiosInstance.delete(item.endpoints.destroy);

        actions.fetchOrderProducts(item.order_id)
    }
}



export default () => ({
    state,
    ...actions,
    ...getters,
    ...mutators,
});

watch(state, () => {
    mutators.grandCalculate();
});


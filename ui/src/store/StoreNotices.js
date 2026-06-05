import { reactive, computed } from "vue";
import axiosInstance from "../axiosInstance";
import useErrors from './StoreErrors';

const { setErrors } = useErrors();

const defaultState = reactive({
});
const state = reactive(defaultState);

const getters = {
    notice: computed(() => state.notice),
};
const actions = {
    storeShippingNotice: async (data, request) => {
        try {
            const response = await axiosInstance.post("/shippings/" + data.id + "/notices", request);
        } catch (e) {
            setErrors(e);
        }
    },
};

export default () => ({
    state,
    ...actions,
    ...getters,
});

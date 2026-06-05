import { computed, reactive } from "vue";
import axiosInstance from "../axiosInstance";
import { PAGE_CATEGORY } from "../constants";
const { setErrors } = useErrors();


const defaulState = {
    categories: [],
    category: {},
};
import useErrors from './StoreErrors';

const state = reactive(defaulState);

const getters = {
    categories: computed(() => state.categories),
};
const actions = {
    fetchCategories: async () => {
        try {
            const response = await axiosInstance.get(PAGE_CATEGORY.URL);
            state.categories = response.data;
        } catch (e) { setErrors(e); }
    },

    storeCategory: async () => {
        try {
            await axiosInstance.post(PAGE_CATEGORY.URL, state.category);
        } catch (e) {
            setErrors(e);
        }

        // Clear carts after store
        state.category = {};

        actions.fetchCategories();
    },

    destroyCategory: async (url) => {
        if (!window.confirm("Skutočne vymazať!")) {
            return;
        }
        await axiosInstance.delete(url);
        actions.fetchCategories();
    },
};

export default () => ({
    // state: readonly(state),
    state,
    ...actions,
    ...getters,
});

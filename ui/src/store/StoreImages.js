import axiosInstance from "../axiosInstance";
import { computed, reactive } from "vue";
import useErrors from './StoreErrors';

const { setErrors } = useErrors();

const defaultState = {
    images: [],
    image: '',

};

const state = reactive(defaultState);

const getters = {
    getImages: computed(() => state.images),
    getImage: computed(() => state.image)
};
const actions = {
    setImages: (images) => {
        state.images = images;
    },

    destroyImage: async (productId, imageId) => {
        if (!window.confirm("Skutočne vymazať!")) {
            return;
        }

        try {
            await axiosInstance.delete('/product/' + productId + '/image/' + imageId);
            // actions.getProducts();
        } catch (e) {
            setErrors(e);
        }

    }
};

export default () => ({
    state,
    ...actions,
    ...getters,
});

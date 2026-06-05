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

    storeImages: async (productId, images) => {
        const formData = new FormData();

        images.forEach((image) => {
            formData.append('images[]', image);
        });

        try {
            const response = await axiosInstance.post('/product/' + productId + '/image', formData);

            const product = response.data.data ?? response.data;

            state.images = product.images;

            return product;
        } catch (e) {
            setErrors(e);
        }
    },

    destroyImage: async (productId, imageId) => {
        if (!window.confirm("Skutočne vymazať!")) {
            return;
        }

        try {
            await axiosInstance.delete('/product/' + productId + '/image/' + imageId);
            state.images = state.images.filter((image) => image.id !== imageId);

            return true;
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

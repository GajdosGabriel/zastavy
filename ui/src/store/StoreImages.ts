import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import useErrors from './StoreErrors';

export interface ProductImage {
    id: number;
    path: string;
    [key: string]: any;
}

interface ImagesState {
    images: ProductImage[];
    image: string;
}

export const useImages = defineStore('images', {
    state: (): ImagesState => ({
        images: [],
        image: '',
    }),

    getters: {
        getImages: (s): ProductImage[] => s.images,
        getImage: (s): string => s.image,
    },

    actions: {
        setImages(images: ProductImage[]): void {
            this.images = images;
        },

        async storeImages(productId: number | string, images: File[]): Promise<any> {
            const formData = new FormData();

            images.forEach((image) => {
                formData.append('images[]', image);
            });

            try {
                const response = await axiosInstance.post('/product/' + productId + '/image', formData);

                const product = response.data.data ?? response.data;

                this.images = product.images;

                return product;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async reorderImages(productId: number | string, ids: number[]): Promise<boolean | undefined> {
            try {
                const response = await axiosInstance.post(`/product/${productId}/image/reorder`, { ids });
                const product = response.data.data ?? response.data;
                this.images = product.images;
                return true;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async destroyImage(productId: number | string, imageId: number): Promise<boolean | undefined> {
            if (!window.confirm("Skutočne vymazať!")) {
                return;
            }

            try {
                await axiosInstance.delete('/product/' + productId + '/image/' + imageId);
                this.images = this.images.filter((image) => image.id !== imageId);

                return true;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },
    },
});

export default useImages;

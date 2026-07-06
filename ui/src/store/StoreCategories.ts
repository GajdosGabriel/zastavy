import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import useErrors from './StoreErrors';
import { PAGE_CATEGORY } from '../constants';

export interface Category {
    id: number;
    name: string;
    [key: string]: any;
}

interface CategoriesState {
    categories: Category[];
    category: Partial<Category>;
}

export const useCategories = defineStore('categories', {
    state: (): CategoriesState => ({
        categories: [],
        category: {},
    }),

    actions: {
        async fetchCategories(): Promise<void> {
            try {
                const response = await axiosInstance.get(PAGE_CATEGORY.URL);
                this.categories = response.data;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async storeCategory(): Promise<void> {
            try {
                await axiosInstance.post(PAGE_CATEGORY.URL, this.category);
            } catch (e) {
                useErrors().setErrors(e);
            }

            this.category = {};
            this.fetchCategories();
        },

        async destroyCategory(url: string): Promise<void> {
            if (!window.confirm('Skutočne vymazať!')) {
                return;
            }
            await axiosInstance.delete(url);
            this.fetchCategories();
        },
    },
});

export default useCategories;

import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import useErrors from './StoreErrors';

const BASE_URL = '/admin/shipping-methods';

export interface ShippingMethodForm {
    id: number | null;
    name: string;
    price: number | string;
    free_from_price: number | string | null;
    active: boolean;
    sort_order: number;
}

const empty = (): ShippingMethodForm => ({
    id: null,
    name: '',
    price: '',
    free_from_price: '',
    active: true,
    sort_order: 99,
});

interface ShippingMethodsState {
    shippingMethods: ShippingMethodForm[];
    trashedMethods: ShippingMethodForm[];
    shippingMethod: ShippingMethodForm;
}

export const useShippingMethods = defineStore('shippingMethods', {
    state: (): ShippingMethodsState => ({
        shippingMethods: [],
        trashedMethods: [],
        shippingMethod: empty(),
    }),

    getters: {
        getShippingMethods: (s): ShippingMethodForm[] => s.shippingMethods,
        getTrashedMethods: (s): ShippingMethodForm[] => s.trashedMethods,
    },

    actions: {
        async fetchShippingMethods(): Promise<void> {
            try {
                const { data } = await axiosInstance.get(BASE_URL);
                this.shippingMethods = data.data;
                this.trashedMethods = data.trashed ?? [];
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async saveShippingMethod(): Promise<boolean> {
            try {
                const { id, ...payload } = this.shippingMethod;
                if (id) {
                    await axiosInstance.put(`${BASE_URL}/${id}`, payload);
                } else {
                    await axiosInstance.post(BASE_URL, payload);
                }
                await this.fetchShippingMethods();
                this.resetShippingMethod();
                return true;
            } catch (e) {
                useErrors().setErrors(e);
                return false;
            }
        },

        editShippingMethod(method: ShippingMethodForm): void {
            this.shippingMethod = { ...empty(), ...method };
        },

        async destroyShippingMethod(method: ShippingMethodForm): Promise<void> {
            if (!window.confirm('Skutočne vymazať spôsob dopravy?')) return;
            try {
                await axiosInstance.delete(`${BASE_URL}/${method.id}`);
                await this.fetchShippingMethods();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async restoreShippingMethod(method: ShippingMethodForm): Promise<void> {
            try {
                await axiosInstance.post(`${BASE_URL}/${method.id}/restore`);
                await this.fetchShippingMethods();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        resetShippingMethod(): void {
            this.shippingMethod = empty();
        },
    },
});

export default useShippingMethods;

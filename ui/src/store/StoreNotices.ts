import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import useErrors from './StoreErrors';

export const useNotices = defineStore('notices', {
    actions: {
        async storeShippingNotice(shipping: { id: number }, request: Record<string, any>): Promise<void> {
            try {
                await axiosInstance.post(`/shippings/${shipping.id}/notices`, request);
            } catch (e) {
                useErrors().setErrors(e);
            }
        },
    },
});

export default useNotices;

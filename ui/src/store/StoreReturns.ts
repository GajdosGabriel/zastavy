import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import StoreOrders from './StoreOrders';
import useErrors from './StoreErrors';

export interface OrderReturn {
    id: number;
    [key: string]: any;
}

interface ReturnsState {
    returns: OrderReturn[];
    currentReturn: OrderReturn | null;
}

export const useReturns = defineStore('returns', {
    state: (): ReturnsState => ({
        returns: [],
        currentReturn: null,
    }),

    getters: {
        getReturns: (s): OrderReturn[] => s.returns,
        getCurrentReturn: (s): OrderReturn | null => s.currentReturn,
    },

    actions: {
        async fetchReturns(orderId: number | string): Promise<void> {
            try {
                const response = await axiosInstance.get(`/orders/${orderId}/returns`);
                this.returns = response.data.data;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async fetchReturn(orderId: number | string, returnId: number | string): Promise<void> {
            try {
                const response = await axiosInstance.get(`/orders/${orderId}/returns/${returnId}`);
                this.currentReturn = response.data.data;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async storeReturn(orderId: number | string, payload: any): Promise<OrderReturn | undefined> {
            try {
                const response = await axiosInstance.post(`/orders/${orderId}/returns`, payload);
                const newReturn = response.data.data;
                this.returns.unshift(newReturn);
                return newReturn;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async updateReturn(orderId: number | string, returnId: number | string, payload: any): Promise<OrderReturn | undefined> {
            try {
                const response = await axiosInstance.put(`/orders/${orderId}/returns/${returnId}`, payload);
                const updated = response.data.data;
                this.currentReturn = updated;
                const idx = this.returns.findIndex((r) => r.id === returnId);
                if (idx !== -1) this.returns[idx] = updated;
                return updated;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async processReturn(orderId: number | string, returnId: number | string, notifyCustomer = false): Promise<OrderReturn | undefined> {
            try {
                const response = await axiosInstance.post(`/orders/${orderId}/returns/${returnId}/process`, {
                    notify_customer: notifyCustomer,
                });
                const updated = response.data.data;
                this.currentReturn = updated;
                const idx = this.returns.findIndex((r) => r.id === returnId);
                if (idx !== -1) this.returns[idx] = updated;

                const updatedOrder = response.data.order?.data ?? response.data.order;
                if (updatedOrder) {
                    StoreOrders().setOrder(updatedOrder);
                }

                return updated;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async cancelReturn(orderId: number | string, returnId: number | string): Promise<OrderReturn | undefined> {
            try {
                const response = await axiosInstance.post(`/orders/${orderId}/returns/${returnId}/cancel`);
                const updated = response.data.data;
                this.currentReturn = updated;
                const idx = this.returns.findIndex((r) => r.id === returnId);
                if (idx !== -1) this.returns[idx] = updated;
                return updated;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async deleteReturn(orderId: number | string, returnId: number | string): Promise<boolean | undefined> {
            try {
                await axiosInstance.delete(`/orders/${orderId}/returns/${returnId}`);
                this.returns = this.returns.filter((r) => r.id !== returnId);
                return true;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },
    },
});

export default useReturns;

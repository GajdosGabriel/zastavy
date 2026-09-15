import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import StoreOrders from "./StoreOrders";
import StoreOrderProducts from "./StoreOrderProducts";
import useErrors from './StoreErrors';

interface ShippingsState {
    shipping: any;
    pending: Record<string, { hash: string; key: string }>;
    busy: Record<string, boolean>;
}

export const useShippings = defineStore('shippings', {
    state: (): ShippingsState => ({
        shipping: '',
        pending: {},
        busy: {},
    }),

    actions: {
        async getShippings(): Promise<void> {
            // ponechané ako placeholder (pôvodne prázdne)
        },

        async storeShipping(order: { id: number | string }, options: Record<string, any> = {}): Promise<any> {
            const id = String(order.id);
            if (this.busy[id]) return;
            const hash = JSON.stringify(options);
            if (this.pending[id]?.hash !== hash) this.pending[id] = { hash, key: crypto.randomUUID() };
            this.busy[id] = true;
            try {
                const response = await axiosInstance.post(
                    "/orders/" + order.id + "/shippings",
                    { ...options, idempotency_key: this.pending[id].key }
                );

                delete this.pending[id];
                this.shipping = response.data.data;

                const updatedOrder = response.data.order?.data ?? response.data.order;

                if (updatedOrder) {
                    StoreOrders().setOrder(updatedOrder);
                    StoreOrderProducts().setOrderProducts(updatedOrder.orderProducts ?? []);
                }

                return response.data;
            } catch (e) {
                useErrors().setErrors(e);
            } finally {
                this.busy[id] = false;
            }
        },
    },
});

export default useShippings;

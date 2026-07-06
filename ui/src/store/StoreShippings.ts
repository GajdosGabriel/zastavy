import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import StoreOrders from "./StoreOrders";
import StoreOrderProducts from "./StoreOrderProducts";
import useErrors from './StoreErrors';

interface ShippingsState {
    shipping: any;
}

export const useShippings = defineStore('shippings', {
    state: (): ShippingsState => ({
        shipping: '',
    }),

    actions: {
        async getShippings(): Promise<void> {
            // ponechané ako placeholder (pôvodne prázdne)
        },

        async storeShipping(order: { id: number | string }, options: Record<string, any> = {}): Promise<any> {
            try {
                const response = await axiosInstance.post(
                    "/orders/" + order.id + "/shippings",
                    options
                );

                this.shipping = response.data.data;

                const updatedOrder = response.data.order?.data ?? response.data.order;

                if (updatedOrder) {
                    StoreOrders().setOrder(updatedOrder);
                    StoreOrderProducts().setOrderProducts(updatedOrder.orderProducts ?? []);
                }

                return response.data;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },
    },
});

export default useShippings;

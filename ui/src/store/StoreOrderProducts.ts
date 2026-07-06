import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";

interface OrderProductsState {
    orderProducts: any[];
}

export const useOrderProducts = defineStore("orderProducts", {
    state: (): OrderProductsState => ({
        orderProducts: [],
    }),

    getters: {
        getOrderProducts: (state): any[] => state.orderProducts,
        getStatement: (state) => ({
            grandTotal: state.orderProducts.reduce(
                (sum, item) => sum + Number(item.price * item.quantity),
                0
            ),
            grandQuantity: state.orderProducts.reduce(
                (sum, item) => sum + Number(item.quantity),
                0
            ),
        }),
    },

    actions: {
        async fetchOrderProducts(id: number | string): Promise<void> {
            const response = await axiosInstance.get(
                "/orders/" + id + "/orderProducts"
            );
            this.orderProducts = response.data.data;
        },

        async updateOrderProducts(item: Record<string, any>): Promise<void> {
            await axiosInstance.put(item.endpoints.update, item);
        },

        setOrderProducts(items: any[]): void {
            this.orderProducts = items;
        },

        addOrderProduct(orderId: number | string): void {
            const tempId = "__new__" + Date.now();
            this.orderProducts.push({
                id: tempId,
                isNew: true,
                order_id: orderId,
                product_id: null,
                quantity: 1,
                price: 0,
                storno: 0,
                stockSum: 0,
                name: "",
                unit_value: "",
                shipping_required_quantity: 1,
                shipping_remaining_quantity: 1,
                shipping_percentage: 0,
                endpoints: {
                    store: `/orders/${orderId}/orderProducts`,
                    update: `/orders/${orderId}/orderProducts/${tempId}`,
                    destroy: `/orders/${orderId}/orderProducts/${tempId}`,
                },
            });
        },

        removeOrderProduct(id: number | string): void {
            const idx = this.orderProducts.findIndex((p) => p.id === id);
            if (idx !== -1) this.orderProducts.splice(idx, 1);
        },

        async saveNewOrderProduct(item: Record<string, any>): Promise<any> {
            const response = await axiosInstance.post(item.endpoints.store, {
                product_id: item.product_id,
                quantity: item.quantity,
                price: item.price,
            });
            const saved = response.data.data;
            const idx = this.orderProducts.findIndex((p) => p.id === item.id);
            if (idx !== -1) this.orderProducts.splice(idx, 1, saved);
            return saved;
        },

        async destroyOrderProducts(item: Record<string, any>): Promise<void> {
            if (!window.confirm("Skutočne vymazať!")) {
                return;
            }
            await axiosInstance.delete(item.endpoints.destroy);
            this.fetchOrderProducts(item.order_id);
        },
    },
});

export default useOrderProducts;

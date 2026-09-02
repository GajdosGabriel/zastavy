import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import useUsers from "./StoreUsers";
import useOrderProducts from "./StoreOrderProducts";
import usePaginator from "./StorePaginator";
import useErrors from "./StoreErrors";
import useQuery from "./StoreQuery";
import { PAGE_ORDER } from "../constants";

interface OrdersState {
    statement: { markSelected: boolean };
    isLoading: boolean;
    url: string;
    orders: any[];
    order: Record<string, any>;
    customer: Record<string, any>;
    statuses: any[];
    statistics: {
        orders: Record<string, any>;
        products: any[];
        undelivered_products: any[];
    };
}

let ordersRequestId = 0;

export const useOrders = defineStore("orders", {
    state: (): OrdersState => ({
        statement: {
            markSelected: false,
        },
        isLoading: false,
        url: PAGE_ORDER.URL,
        orders: [],
        order: {},
        customer: {},
        statuses: [],
        statistics: {
            orders: {},
            products: [],
            undelivered_products: [],
        },
    }),

    getters: {
        getOrders: (state): any[] => state.orders,
        getOrder: (state): Record<string, any> => state.order,
        getOrderStatistics: (state) => state.statistics,
        getStatuses: (state): any[] => state.statuses,
        isOrderFinished: (state): boolean => state.order.isFinished,
    },

    actions: {
        async fetchOrders(): Promise<void> {
            const { setPaginator, setLinks } = usePaginator();
            const q = useQuery();

            const requestId = ++ordersRequestId;
            this.isLoading = true;

            try {
                const response = await axiosInstance.get(this.url + q.stringForUrl);

                if (requestId !== ordersRequestId) {
                    return;
                }

                this.orders = response.data.data;
                this.statuses = response.data.meta?.statuses || this.statuses;
                setPaginator(response.data.meta);
                setLinks(response.data.links);
            } catch (e) {
                useErrors().setErrors(e);
            } finally {
                if (requestId === ordersRequestId) {
                    this.isLoading = false;
                }
            }
        },

        async fetchOrderStatistics(): Promise<void> {
            const q = useQuery();
            try {
                const response = await axiosInstance.get(
                    PAGE_ORDER.URL + "/statistics" + q.stringForUrl
                );
                this.statistics = response.data.data;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async fetchOrder(id: number | string): Promise<void> {
            const { setOrderProducts } = useOrderProducts();
            try {
                const response = await axiosInstance.get(PAGE_ORDER.URL + "/" + id);
                this.order = response.data;
                setOrderProducts(response.data.orderProducts);
                this.customer = response.data.customer;
            } catch (e) {
                useErrors().setErrors(e);
            }
            // When order was opened
            if (this.order.isOpened === 0) {
                await this.updateOrder({
                    id: id,
                    isOpened: 1,
                });
                useUsers().fetchUser();
            }
        },

        /**
         * Prílohy sú za autentifikáciou, takže sa nedajú stiahnuť obyčajným
         * odkazom — súbor si vypýtame ako blob s tokenom a uložíme cez odkaz.
         */
        async downloadAttachment(orderId: number | string, attachment: Record<string, any>): Promise<void> {
            try {
                const response = await axiosInstance.get(
                    `${PAGE_ORDER.URL}/${orderId}/attachments/${attachment.id}`,
                    { responseType: "blob" }
                );

                const url = URL.createObjectURL(response.data);
                const link = document.createElement("a");
                link.href = url;
                link.download = attachment.name;
                link.click();
                URL.revokeObjectURL(url);
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async uploadAttachments(orderId: number | string, files: File[] | FileList): Promise<void> {
            const form = new FormData();
            Array.from(files ?? []).forEach((file) => form.append("attachments[]", file));

            try {
                await axiosInstance.post(`${PAGE_ORDER.URL}/${orderId}/attachments`, form);
                await this.fetchOrder(orderId);
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async deleteAttachment(orderId: number | string, attachmentId: number): Promise<void> {
            try {
                await axiosInstance.delete(`${PAGE_ORDER.URL}/${orderId}/attachments/${attachmentId}`);
                await this.fetchOrder(orderId);
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async updateOrder(data: Record<string, any>): Promise<void> {
            try {
                await axiosInstance.put(PAGE_ORDER.URL + "/" + data.id, data);
                this.fetchOrders();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async storeOrder(payload: Record<string, any>): Promise<any> {
            try {
                const response = await axiosInstance.post(PAGE_ORDER.URL, payload);
                const order = response.data.data;

                if (order) {
                    this.orders.unshift(order);
                    this.order = order;
                    this.customer = order.customer || {};
                }

                return order;
            } catch (e) {
                useErrors().setErrors(e);
                return null;
            }
        },

        async destroyOrder(url: string): Promise<void> {
            if (!window.confirm("Skutočne vymazať!")) {
                return;
            }
            try {
                await axiosInstance.delete(url).then((res) => {
                    const index = this.orders.findIndex((item) => item.id === res.data.id);
                    if (index !== -1) {
                        this.orders.splice(index, 1);
                    }
                });
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        setOrders(orders: any[]): void {
            this.orders = orders;
        },

        setPaginator(url: string): void {
            this.url = url;
            this.fetchOrders();
        },

        resetUrl(): void {
            this.url = PAGE_ORDER.URL;
        },

        setOrder(order: Record<string, any>): void {
            this.order = order;

            const index = this.orders.findIndex((item) => item.id === order.id);

            if (index !== -1) {
                this.orders.splice(index, 1, order);
            }
        },

        resetOrder(): void {
            this.order = {};
        },

        async clickToMark(order: Record<string, any>): Promise<void> {
            try {
                await axiosInstance.post(order.mark.endpoint).then((res) => {
                    this.orders = this.orders.reduce((acc, curr) => {
                        acc.push(curr.id === order.id ? res.data.data : curr);
                        return acc;
                    }, [] as any[]);
                });
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        fetchMarkSelected(): void {
            const { setQuery, removeQuery } = useQuery();

            if (this.statement.markSelected) {
                removeQuery("isMarked=true");
                this.statement.markSelected = false;
            } else {
                setQuery("isMarked=true");
                this.statement.markSelected = true;
            }

            this.fetchOrders();
        },
    },
});

export default useOrders;

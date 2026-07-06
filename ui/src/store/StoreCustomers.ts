import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import usePaginator from "./StorePaginator";
import useQuery from "./StoreQuery";
import useOrders from "./StoreOrders";
import useErrors from "./StoreErrors";
import { PAGE_CUSTOMER } from "../constants";
import { AxiosResponse } from "axios";
import { ApiResponse } from "../types";

interface Customer {
    id: string;
    name: string;
    company: string;
    street: string;
    postcode: number;
    city: string;
    email: string;
    phone: string;
    ico: number;
    dic: number;
    ic_dic: string;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    status: any;
    [key: string]: any;
}

interface CustomersState {
    statement: { markSelected: boolean };
    url: string;
    customers: Customer[];
    customer: Customer;
    statuses: any[];
}

export const useCustomers = defineStore("customers", {
    state: (): CustomersState => ({
        statement: {
            markSelected: false,
        },
        url: PAGE_CUSTOMER.URL,
        customers: [],
        customer: {} as Customer,
        statuses: [],
    }),

    getters: {
        getCustomers: (state): Customer[] => state.customers,
        getCustomer: (state): Customer => state.customer,
        getStatuses: (state): any[] => state.statuses,
    },

    actions: {
        async fetchCustomers(): Promise<void> {
            const { setPaginator, setLinks } = usePaginator();
            const q = useQuery();

            try {
                const response = await axiosInstance.get<ApiResponse<Customer[]>>(
                    this.url + q.getQueryStringUrl
                );

                this.customers = response.data.data;
                setPaginator(response.data.meta);
                setLinks(response.data.links);
            } catch (error) {
                this.customers = [];
                useErrors().setErrors(error);
            }
        },

        async fetchCustomer(id: number | string): Promise<void> {
            const response = await axiosInstance.get(PAGE_CUSTOMER.URL + "/" + id);
            this.customer = response.data.data;
            this.statuses = response.data.meta?.statuses || [];
        },

        async fetchCustomerOrders(customerId: number | string): Promise<void> {
            const { setOrders } = useOrders();
            const { setPaginator, setLinks } = usePaginator();

            try {
                setOrders([]);
                const response: AxiosResponse<ApiResponse<any[]>> = await axiosInstance.get(
                    PAGE_CUSTOMER.URL + "/" + customerId + "/order"
                );
                setOrders(response.data.data);
                setPaginator(response.data.meta);
                setLinks(response.data.links);
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async findCustomerByIco(): Promise<{ customer: Customer; source: string }> {
            const ico = String(this.customer.ico || "").replace(/\D/g, "");

            if (!ico) {
                throw new Error("Zadajte IČO.");
            }

            const response = await axiosInstance.get("/checkouts/" + ico);
            const company = response.data.data;
            const source: string = response.data.source;

            const fromDb = source === "database" || source === "database_with_internet";

            this.customer = {
                ...this.customer,
                company: company.company ?? this.customer.company,
                street: company.street ?? this.customer.street,
                postcode: company.postcode ?? this.customer.postcode,
                city: company.city ?? this.customer.city,
                ico: company.ico ?? ico,
                dic: company.dic ?? this.customer.dic,
                ic_dic: company.ic_dic ?? this.customer.ic_dic,
                name: fromDb ? (company.name || "") : "",
                email: fromDb ? (company.email || "") : "",
                phone: fromDb ? (company.phone || "") : "",
            };

            return {
                customer: this.customer,
                source,
            };
        },

        async updateCustomer(): Promise<void> {
            try {
                const payload = {
                    ...this.customer,
                    status: this.customer.status?.value || this.customer.status,
                };

                await axiosInstance.put(
                    PAGE_CUSTOMER.URL + "/" + this.customer.id,
                    payload
                );
            } catch (e) {
                useErrors().setErrors(e);
            }
            this.fetchCustomers();
        },

        async storeCustomer(): Promise<void> {
            try {
                const response = await axiosInstance.post(PAGE_CUSTOMER.URL, this.customer);
                this.customer = response.data.data;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async destroyCustomer(url: string): Promise<void> {
            if (!window.confirm("Vymazať zákazníka!")) {
                return;
            }

            await axiosInstance.delete(url).then((res) => {
                const index = this.customers.findIndex((item) => item.id === res.data.id);
                if (index !== -1) {
                    this.customers.splice(index, 1);
                }
            });
        },

        setCustomer(data: Customer): void {
            this.customer = data;
        },

        setPaginator(data: string): void {
            this.url = data;
            this.fetchCustomers();
        },

        async clickToMark(val: string): Promise<void> {
            await axiosInstance.post(val).then((res) => {
                const index = this.customers.findIndex((item) => item.id === res.data.id);
                if (index !== -1) {
                    this.customers.splice(index, 1, res.data);
                }
            });
        },

        resetCustomer(): void {
            this.customer = {} as Customer;
        },
    },
});

export default useCustomers;

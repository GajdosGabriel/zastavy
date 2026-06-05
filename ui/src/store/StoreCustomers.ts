import axiosInstance from "../axiosInstance";
import { computed, reactive, watch } from "vue";
import router from "../router";
import usePaginator from './StorePaginator';
import useQuery from './StoreQuery';
import useOrders from './StoreOrders';
import { PAGE_CUSTOMER } from "../constants";
import { AxiosResponse } from "axios";
import { ApiResponse } from "../types";
import { URL_BASE_API } from "../constants";

interface Customer {
    id: string
    name: string
    company: string
    street: string
    postcode: number
    city: string
    email: string
    phone: string
    ico: number
    dic: number
    ic_dic: string
    created_at: string
    updated_at: string
    deleted_at: string | null
};

interface Statement {
    spinner: boolean;
    markSelected: boolean;
};


const { setOrders } = useOrders();
const { setPaginator, setLinks } = usePaginator();
const { getQueryStringUrl, setQuery, removeQuery } = useQuery();

const defaultState = {
    statement: {
        markSelected: false,
    } as Statement,
    url: PAGE_CUSTOMER.URL as string,
    customers: [] as Customer[],
    customer: {} as Customer
};

const state = reactive(defaultState);

const getters = {
    getCustomers: computed<Customer[]>(() => state.customers),
    getCustomer: computed<Customer>(() => state.customer),
    getNumberOfCustomers: () => {
        return computed<number>(() => state.customers.length);
    },
};
const actions = {
    fetchCustomers: async () => {
        try {
            const response = await axiosInstance.get<ApiResponse<Customer[]>>(
                state.url + getQueryStringUrl.value
            );

            state.customers = response.data.data;
            setPaginator(response.data.meta);
            setLinks(response.data.links);
        } finally {

        }
    },

    fetchCustomer: async (id: number) => {
        try {
            const response = await axiosInstance.get<Customer>(PAGE_CUSTOMER.URL + '/' + id);
            state.customer = response.data;
        } finally {
        }
    },

    fetchCustomerOrders: async (customerId: number) => {
        setOrders([]);
        const response: AxiosResponse<Customer> = await axiosInstance.get(PAGE_CUSTOMER.URL + '/' + customerId + '/order');
        setOrders(response.data.data);
    },

    findCustomerByIco: async () => {
        let response = await axiosInstance.get("/checkouts/" + state.customer.ico);
        state.customer = response.data.data;
    },

    updateCustomer: async () => {
        try {
            await axiosInstance.put(
                PAGE_CUSTOMER.URL + '/' + state.customer.id,
                state.customer
            );
            // state.customer = response.data;
        } catch (e) {
            if (e.response.status === 422) {
                // state.errors = e.response.data.errors;
            }
        }
        actions.fetchCustomers();
    },
    storeCustomer: async () => {
        try {
            const response = await axiosInstance.post(PAGE_CUSTOMER.URL, state.customer);
            state.customer = response.data.data;
            // router.push({ name: "customers.index" });
        } catch (e) {
            // if (e.response.status === 422) {
            //     state.errors = e.response.data.errors;
            // }
        } finally {
        }
    },
    destroyCustomer: async (url: string) => {
        if (!window.confirm("Vymazať zákazníka!")) {
            return;
        }

        try {
            await axiosInstance.delete(url).then((res) => {
                const index = state.customers.findIndex(item => item.id === res.data.id);
                if (index !== -1) {
                    state.customers.splice(index, 1);
                }
            });
        } finally {

        }

    },
    setCustomer: (data): void => {
        state.customer = data
    },

    setPaginator: (data): void => {
        state.url = data;
        actions.fetchCustomers();
    },

    clickToMark: async (val: string) => {
        await axiosInstance.post(val).then((res) => {
            const index = state.customers.findIndex(item => item.id === res.data.id);
            if (index !== -1) {
                state.customers.splice(index, 1, res.data);
            }
        });
    },
    resetCustomer: () => {
        state.customer = {} as Customer
    }
};

export default () => ({
    state,
    ...getters,
    ...actions,
});

watch(getQueryStringUrl, (val) => {
    actions.fetchCustomers();
});

import axiosInstance from "../axiosInstance";
import { computed, reactive, watch } from "vue";
import router from "../router";
import usePaginator from './StorePaginator';
import useQuery from './StoreQuery';
import useOrders from './StoreOrders';
import useErrors from './StoreErrors';
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
    status: any
};

interface Statement {
    spinner: boolean;
    markSelected: boolean;
};


const { setOrders } = useOrders();
const { setErrors } = useErrors();
const { setPaginator, setLinks } = usePaginator();
const { getQueryStringUrl, setQuery, removeQuery } = useQuery();

const defaultState = {
    statement: {
        markSelected: false,
    } as Statement,
    url: PAGE_CUSTOMER.URL as string,
    customers: [] as Customer[],
    customer: {} as Customer,
    statuses: []
};

const state = reactive(defaultState);

const getters = {
    getCustomers: computed<Customer[]>(() => state.customers),
    getCustomer: computed<Customer>(() => state.customer),
    getStatuses: computed(() => state.statuses),
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
            const response = await axiosInstance.get(PAGE_CUSTOMER.URL + '/' + id);
            state.customer = response.data.data;
            state.statuses = response.data.meta?.statuses || [];
        } finally {
        }
    },

    fetchCustomerOrders: async (customerId: number) => {
        try {
            setOrders([]);
            const response: AxiosResponse<ApiResponse<any[]>> = await axiosInstance.get(PAGE_CUSTOMER.URL + '/' + customerId + '/order');
            setOrders(response.data.data);
            setPaginator(response.data.meta);
            setLinks(response.data.links);
        } catch (e) {
            setErrors(e);
        }
    },

    findCustomerByIco: async () => {
        const ico = String(state.customer.ico || '').replace(/\D/g, '');

        if (!ico) {
            throw new Error('Zadajte IČO.');
        }

        const response = await axiosInstance.get("/checkouts/" + ico);
        const company = response.data.data;

        state.customer = {
            ...state.customer,
            company: company.company ?? state.customer.company,
            street: company.street ?? state.customer.street,
            postcode: company.postcode ?? state.customer.postcode,
            city: company.city ?? state.customer.city,
            ico: company.ico ?? ico,
            dic: company.dic ?? state.customer.dic,
            ic_dic: company.ic_dic ?? state.customer.ic_dic,
            name: state.customer.name || company.name || '',
            email: state.customer.email || company.email || '',
            phone: state.customer.phone || company.phone || '',
        };

        return {
            customer: state.customer,
            source: response.data.source,
        };
    },

    updateCustomer: async () => {
        try {
            const payload = {
                ...state.customer,
                status: state.customer.status?.value || state.customer.status,
            };

            await axiosInstance.put(
                PAGE_CUSTOMER.URL + '/' + state.customer.id,
                payload
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

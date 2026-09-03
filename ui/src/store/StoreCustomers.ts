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

/** Jedna výhrada z post-kontroly údajov (CustomerReviewResource). */
interface ReviewIssue {
    index: number;
    field: string;
    label: string;
    severity: "error" | "warning" | "notice";
    severity_label: string;
    source: "rule" | "registry" | "ai";
    source_label: string;
    message: string;
    current: string | null;
    suggested: string | null;
    applicable: boolean;
}

interface CustomerReview {
    id: number;
    customer_id: number;
    score: number | null;
    summary: string | null;
    severity: string | null;
    issues: ReviewIssue[];
    applied: {
        index: number;
        field: string;
        label: string;
        from: string | null;
        to: string | null;
        source: string;
        source_label: string;
        at: string;
    }[];
    reviewed_at: string | null;
    resolved_at: string | null;
    pending: boolean;
    last_error: string | null;
    endpoints: { show: string; run: string; apply: string; revert: string; resolve: string };
}

interface CustomersState {
    statement: { markSelected: boolean };
    url: string;
    customers: Customer[];
    customer: Customer;
    statuses: any[];
    review: CustomerReview | null;
    reviewLoading: boolean;
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
        review: null,
        reviewLoading: false,
    }),

    getters: {
        getCustomers: (state): Customer[] => state.customers,
        getCustomer: (state): Customer => state.customer,
        getStatuses: (state): any[] => state.statuses,
        getReview: (state): CustomerReview | null => state.review,
        isReviewLoading: (state): boolean => state.reviewLoading,
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
                this.statuses = response.data.meta?.statuses || this.statuses;
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

        /**
         * Posudok údajov zákazníka.
         *
         * Ťahá sa zvlášť od zákazníka a zámerne: formulár sa má otvoriť aj
         * vtedy, keď kontrola ešte nedobehla alebo keď je vypnutá.
         */
        async fetchReview(customerId: number | string): Promise<void> {
            this.reviewLoading = true;

            try {
                const response = await axiosInstance.get(
                    PAGE_CUSTOMER.URL + "/" + customerId + "/review"
                );
                this.review = response.data.data ?? null;
            } catch (e) {
                this.review = null;
            } finally {
                this.reviewLoading = false;
            }
        },

        /** Pustí kontrolu hneď — „pozri sa na tento riadok teraz". */
        async runReview(customerId: number | string): Promise<string> {
            this.reviewLoading = true;

            try {
                const response = await axiosInstance.post(
                    PAGE_CUSTOMER.URL + "/" + customerId + "/review"
                );
                this.review = response.data.data ?? null;
                await this.fetchCustomer(customerId);

                return "";
            } catch (e: any) {
                return e.response?.data?.message || "Kontrolu sa nepodarilo spustiť.";
            } finally {
                this.reviewLoading = false;
            }
        },

        /**
         * Prijme vybrané návrhy.
         *
         * Posielajú sa iba poradové čísla výhrad, nie hodnoty — čo sa zapíše,
         * rozhoduje server podľa toho, čo kontrola naozaj navrhla.
         */
        async applyReviewSuggestions(customerId: number | string, indexes: number[]): Promise<string> {
            this.reviewLoading = true;

            try {
                const response = await axiosInstance.put(
                    PAGE_CUSTOMER.URL + "/" + customerId + "/review",
                    { issues: indexes }
                );

                this.review = response.data.data ?? null;

                // Formulár drží rozpracované údaje zákazníka; po zápise musí
                // ukázať to, čo je naozaj v databáze, inak by uloženie
                // formulára opravu hneď prepísalo späť.
                if (response.data.customer) {
                    this.customer = { ...this.customer, ...response.data.customer };
                }

                return "";
            } catch (e: any) {
                return e.response?.data?.message || "Návrh sa nepodarilo použiť.";
            } finally {
                this.reviewLoading = false;
            }
        },

        /**
         * Vráti automatickú opravu späť.
         *
         * Server odmietne vrátenie, ak hodnotu medzitým zmenil človek — jeho
         * zmena má prednosť pred pôvodnou hodnotou z auditu.
         */
        async revertReviewChanges(customerId: number | string, indexes: number[]): Promise<string> {
            this.reviewLoading = true;

            try {
                const response = await axiosInstance.post(
                    PAGE_CUSTOMER.URL + "/" + customerId + "/review/revert",
                    { applied: indexes }
                );

                this.review = response.data.data ?? null;

                if (response.data.customer) {
                    this.customer = { ...this.customer, ...response.data.customer };
                }

                return "";
            } catch (e: any) {
                return e.response?.data?.message || "Zmenu sa nepodarilo vrátiť.";
            } finally {
                this.reviewLoading = false;
            }
        },

        /** Odbaví posudok — „viem o tom, nechaj tak". */
        async resolveReview(customerId: number | string): Promise<void> {
            this.reviewLoading = true;

            try {
                const response = await axiosInstance.delete(
                    PAGE_CUSTOMER.URL + "/" + customerId + "/review"
                );
                this.review = response.data.data ?? null;
            } catch (e) {
                useErrors().setErrors(e);
            } finally {
                this.reviewLoading = false;
            }
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
            this.review = null;
        },
    },
});

export default useCustomers;

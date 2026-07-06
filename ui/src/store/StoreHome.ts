import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import useImages from "./StoreImages";
import useErrors from './StoreErrors';
import usePaginator from './StorePaginator';
import useQuery from './StoreQuery';
import { PAGE_HOME } from "../constants";

export interface HomeProduct {
    id: string;
    name: string;
    slug: string;
    description: string;
    quantity: number;
    weight: number;
    price: number;
    sale_price: number;
    discount: number;
    vat: number;
    image_id: number;
    published: boolean;
    unit_value: string;
    min_order: number;
    created_at: string;
    deleted_at: string;
    updated_at: string;
    endpoints: {
        update: string;
        destroy: string;
    };
}

interface HomeState {
    searchUrl: string;
    url: string;
    products: HomeProduct[];
    product: HomeProduct;
}

export const useHome = defineStore('home', {
    state: (): HomeState => ({
        searchUrl: "",
        url: PAGE_HOME.URL,
        products: [],
        product: { sale_price: 0 } as HomeProduct,
    }),

    getters: {
        getProducts: (s): HomeProduct[] => s.products,
        getProduct: (s): HomeProduct => s.product,
    },

    actions: {
        async fetchProducts(): Promise<void> {
            try {
                const q = useQuery();
                const paginator = usePaginator();
                const response = await axiosInstance.get(this.url + q.stringForUrl);
                this.products = await response.data.data;
                paginator.setPaginator(response.data.meta);
                paginator.setLinks(response.data.links);
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async fetchProduct(id: number): Promise<void> {
            try {
                const response = await axiosInstance.get(PAGE_HOME.URL + '/' + id);
                this.product = await response.data;
                useImages().setImages(response.data.images);
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        // Vlastná akcia (nezamieňať s paginátorovým setPaginator voľaným vo fetchProducts).
        setPaginator(url: string): void {
            this.url = url;
            this.fetchProducts();
        },

        setProduct(data: HomeProduct): void {
            this.product = data;
        },
    },
});

export default useHome;

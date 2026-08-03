import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import useImages from "./StoreImages";
import useErrors from './StoreErrors';
import usePaginator from './StorePaginator';
import useQuery from './StoreQuery';
import type { Product } from "./StoreProducts";
import { PAGE_HOME } from "../constants";

export type HomeProduct = Product;

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
        product: { variants: [] } as unknown as HomeProduct,
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

        /**
         * Zmena filtra musí zahodiť odkaz na konkrétnu stránku z paginátora,
         * inak by sa query reťazec lepil na URL, ktorá už `?page=` obsahuje.
         */
        applyFilters(): void {
            this.url = PAGE_HOME.URL;
            this.fetchProducts();
        },
    },
});

export default useHome;

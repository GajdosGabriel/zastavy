import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import useImages from "./StoreImages";
import useErrors from './StoreErrors';
import usePaginator from './StorePaginator';
import useQuery from './StoreQuery';
import type { Product } from "./StoreProducts";
import { createLatestRequest } from '../models/latestRequest';
import { PAGE_HOME } from "../constants";

export type HomeProduct = Product;

interface HomeState {
    listRequests: ReturnType<typeof createLatestRequest>;
    detailRequests: ReturnType<typeof createLatestRequest>;
    searchUrl: string;
    url: string;
    products: HomeProduct[];
    product: HomeProduct;
}

export const useHome = defineStore('home', {
    state: (): HomeState => ({
        listRequests: createLatestRequest(),
        detailRequests: createLatestRequest(),
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
            const q = useQuery();
            const paginator = usePaginator();
            await this.listRequests.run(
                () => axiosInstance.get(this.url + q.stringForUrl),
                (response: { data: any }) => {
                    this.products = response.data.data;
                    paginator.setPaginator(response.data.meta);
                    paginator.setLinks(response.data.links);
                },
                (error: unknown) => useErrors().setErrors(error),
            );
        },

        async fetchProduct(id: number): Promise<void> {
            await this.detailRequests.run(
                () => axiosInstance.get(PAGE_HOME.URL + '/' + id),
                (response: { data: any }) => {
                    this.product = response.data;
                    useImages().setImages(response.data.images);
                },
                (error: unknown) => useErrors().setErrors(error),
            );
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

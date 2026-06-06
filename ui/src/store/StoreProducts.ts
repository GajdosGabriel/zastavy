import axiosInstance from "../axiosInstance";
import { computed, reactive, watch } from "vue";
import useImages from "./StoreImages";
import useErrors from './StoreErrors';
import usePaginator from './StorePaginator';
import useQuery from './StoreQuery';
import { PAGE_PRODUCT } from "../constants";
import templateProduct from "../models/templateProduct";
import { ApiResponse } from "../types";
import { formatDecimal } from "../models/functions";

interface Product {
    id: string
    code: string
    name: string
    slug: string
    description: string
    quantity: number
    weight: number
    price: number
    sale_price: number
    discount: number
    vat: number
    image_id: number
    published: boolean
    unit_value: 'ks' | '1' | 'kg' | 'l' | 'm' | 'm2' | 'm3' | 'cm' | 'cm2' | 'cm3' | 'mm' | 'mm2' | 'mm3' | 'g' | 'mg' | 't' | 'l' | 'ml' | 'm3' | 'm2' | 'm' | 'cm' | 'cm2' | 'cm3' | 'mm' | 'mm2' | 'mm3' | 'g' | 'mg' | 't' | 'l' | 'ml' | 'm3' | 'm2' | 'm' | 'cm' | 'cm2' | 'cm3' | 'mm' | 'mm2' | 'mm3' | 'g' | 'mg' | 't' | 'l' | 'ml' | 'm3' | 'm2' | 'm' | 'cm' | 'cm2' | 'cm3' | 'mm' | 'mm2' | 'mm3' | 'g' | 'mg' | 't' | 'l' | 'ml' | 'm3' | 'm2' | 'm' | 'cm' | 'cm2' | 'cm3' | 'mm' | 'mm2' | 'mm3' | 'g' | 'mg' | 't' | 'l' | 'ml' | 'm3' | 'm2' | 'm' | 'cm' | 'cm2' | 'cm3' | 'mm' | 'mm2' | 'mm3' | 'g' | 'mg' | 't' | 'l' | 'ml' | 'm3' | 'm2' | 'm' | 'cm' | 'cm2' | 'cm3' | 'mm' | 'mm2' | 'mm3' | 'g' | 'mg' | 't' | 'l' | 'ml' | 'm3' | 'm2' | 'm' | 'cm' | 'cm2' | 'cm3' | 'mm' | 'mm2' | 'mm3' | 'g' | 'mg' | 't' | 'l' | 'm'
    min_order: number
    created_at: string
    deleted_at: string
    updated_at: string
    endpoints: {
        update: string
        destroy: string
    }
};



const { setPaginator, setLinks } = usePaginator();

const { setImages } = useImages();
const { setErrors } = useErrors();
const { state: q, setQuery } = useQuery();

const defaultState = {
    searchUrl: "" as string,
    url: PAGE_PRODUCT.URL as string,
    products: [] as Product[],
    product: {
        code: '',
        sale_price: 0,
        unit_value: 'ks',
        min_order: 1,
        vat: 23,
        published: false,
    } as Product,
};

const state = reactive(defaultState);

const getters = {
    getProducts: computed<Product[]>(() => state.products),
    getProduct: computed<Product>(() => state.product),
};

const productPayload = () => ({
    ...state.product,
    code: state.product.code?.trim().toUpperCase(),
    status: state.product.status?.value || state.product.status,
});

const actions = {
    fetchProducts: async () => {
        try {
            const response = await axiosInstance.get(state.url + q.stringForUrl);
            state.products = await (response).data.data;
            setPaginator(response.data.meta);
            setLinks(response.data.links);
        } catch (e) {
            setErrors(e);
        }
    },

    fetchProduct: async (id: number) => {
        try {
            const response = await axiosInstance.get(PAGE_PRODUCT.URL + '/' + id);
            state.product = await response.data;
            setImages(response.data.images);
        } catch (e) {
            setErrors(e);
        }
    },

    updateProduct: async () => {
        try {
            const response = await axiosInstance.put(
                state.product.endpoints.update,
                productPayload()
            ).then((res) => {
                const index = state.products.findIndex(item => item.id === res.data.data.id);
                if (index !== -1) {
                    state.products.splice(index, 1, res.data.data);
                }
            });
        } catch (e) {
            setErrors(e);
        }
    },

    storeProduct: async () => {
        try {
            const response = await axiosInstance.post(state.url, productPayload());
            actions.fetchProducts();

            return response.data.data ?? response.data;
        } catch (e) {
            setErrors(e);
        }
    },

    destroyProduct: async (url) => {
        if (!window.confirm("Skutočne vymazať!")) {
            return;
        }
        try {
            await axiosInstance.delete(url).then((res) => {
                const index = state.products.findIndex(item => item.id === res.data.id);
                if (index !== -1) {
                    state.products.splice(index, 1);
                }
            });
        }
        catch (e) {
            setErrors(e);
        }
    },

    fetchSearchInput: (val) => {
        state.searchUrl = val;
        actions.fetchProducts();
    },

    setPaginator: (url) => {
        state.url = url;
        actions.fetchProducts();
    },

    setProduct: (data) => {
        state.product = data;
    },
};

export default () => ({
    state,
    ...actions,
    ...getters,
});


watch(
    () => state.product,
    ({ discount, price, sale_price }) => {
        // Orezanie zľavy do povoleného rozsahu
        state.product.discount = Math.min(100, Math.max(0, discount));

        // Prepočet sale_price len ak je väčší ako 0
        if (sale_price > 0) {
            state.product.sale_price = price - (price * state.product.discount) / 100;
        }
    },
    { deep: true }
);

import axiosInstance from "../axiosInstance";
import { computed, reactive } from "vue";
import useImages from "./StoreImages";
import useErrors from './StoreErrors';
import usePaginator from './StorePaginator';
import useQuery from './StoreQuery';
import { PAGE_HOME } from "../constants";


interface Product {
    id: string
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
    url: PAGE_HOME.URL as string,
    products: [] as Product[],
    product: {
        sale_price: 0,
    } as Product,
};

const state = reactive(defaultState);

const getters = {
    getProducts: computed<Product[]>(() => state.products),
    getProduct: computed<Product>(() => state.product),
};

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
            const response = await axiosInstance.get(PAGE_HOME.URL + '/' + id);
            state.product = await response.data;
            setImages(response.data.images);
        } catch (e) {
            setErrors(e);
        }
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


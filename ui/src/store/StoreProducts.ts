import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import { computed, ref, watch } from "vue";
import useImages from "./StoreImages";
import useErrors from './StoreErrors';
import usePaginator from './StorePaginator';
import useQuery from './StoreQuery';
import { PAGE_PRODUCT } from "../constants";

export interface Product {
    id: string;
    code: string;
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
    status?: any;
    active_price?: number;
    input_order?: number;
    thumb?: string;
    images?: any[];
    categories?: any[];
    endpoints: {
        update: string;
        destroy: string;
    };
    [key: string]: any;
}

const defaultProduct = (): Product => ({
    id: '',
    code: '',
    name: '',
    slug: '',
    description: '',
    quantity: 0,
    weight: 0,
    price: 0,
    sale_price: 0,
    discount: 0,
    vat: 23,
    image_id: 0,
    published: false,
    unit_value: 'ks',
    min_order: 1,
    created_at: '',
    deleted_at: '',
    updated_at: '',
    active_price: 0,
    input_order: 1,
    thumb: '',
    images: [],
    categories: [],
    endpoints: {
        update: '',
        destroy: '',
    },
});

// Setup-store (composition API) — má vlastný watch na `product` (form normalizácia),
// ktorý v options-store definícii nie je možné vytvoriť.
export const useProducts = defineStore('products', () => {
    let productRequestId = 0;

    const products = ref<Product[]>([]);
    const product = ref<Product>(defaultProduct());
    const url = ref<string>(PAGE_PRODUCT.URL);
    const searchUrl = ref<string>("");

    const getProducts = computed<Product[]>(() => products.value);
    const getProduct = computed<Product>(() => product.value);

    const productPayload = () => ({
        ...product.value,
        code: product.value.code?.trim().toUpperCase(),
        status: product.value.status?.value || product.value.status,
    });

    const fetchProducts = async (): Promise<void> => {
        try {
            const { state: q } = useQuery();
            const paginator = usePaginator();
            const response = await axiosInstance.get(url.value + q.stringForUrl);
            products.value = await response.data.data;
            paginator.setPaginator(response.data.meta);
            paginator.setLinks(response.data.links);
        } catch (e) {
            useErrors().setErrors(e);
        }
    };

    const fetchProduct = async (id: number | string): Promise<void> => {
        const requestId = ++productRequestId;
        product.value = defaultProduct();
        useImages().setImages([]);

        try {
            const response = await axiosInstance.get(PAGE_PRODUCT.URL + '/' + id);

            if (requestId !== productRequestId) {
                return;
            }

            product.value = await response.data;
            useImages().setImages(response.data.images);
        } catch (e) {
            useErrors().setErrors(e);
        }
    };

    const updateProduct = async (): Promise<void> => {
        try {
            await axiosInstance.put(product.value.endpoints.update, productPayload()).then((res) => {
                const index = products.value.findIndex(item => item.id === res.data.data.id);
                if (index !== -1) {
                    products.value.splice(index, 1, res.data.data);
                }
            });
        } catch (e) {
            useErrors().setErrors(e);
        }
    };

    const storeProduct = async (): Promise<any> => {
        try {
            const response = await axiosInstance.post(url.value, productPayload());
            fetchProducts();

            return response.data.data ?? response.data;
        } catch (e) {
            useErrors().setErrors(e);
        }
    };

    const destroyProduct = async (deleteUrl: string): Promise<void> => {
        if (!window.confirm("Skutočne vymazať!")) {
            return;
        }
        try {
            await axiosInstance.delete(deleteUrl).then((res) => {
                const index = products.value.findIndex(item => item.id === res.data.id);
                if (index !== -1) {
                    products.value.splice(index, 1);
                }
            });
        } catch (e) {
            useErrors().setErrors(e);
        }
    };

    const fetchSearchInput = (val: string): void => {
        searchUrl.value = val;
        fetchProducts();
    };

    const setPaginator = (newUrl: string): void => {
        url.value = newUrl;
        fetchProducts();
    };

    const setProduct = (data: Product): void => {
        product.value = data;
    };

    const resetProduct = (): void => {
        productRequestId++;
        product.value = defaultProduct();
        useImages().setImages([]);
    };

    watch(
        product,
        ({ discount, price, sale_price }) => {
            // Orezanie zľavy do povoleného rozsahu
            product.value.discount = Math.min(100, Math.max(0, discount));

            // Prepočet sale_price len ak je väčší ako 0
            if (sale_price > 0) {
                product.value.sale_price = price - (price * product.value.discount) / 100;
            }
        },
        { deep: true }
    );

    return {
        products,
        product,
        url,
        searchUrl,
        getProducts,
        getProduct,
        fetchProducts,
        fetchProduct,
        updateProduct,
        storeProduct,
        destroyProduct,
        fetchSearchInput,
        setPaginator,
        setProduct,
        resetProduct,
    };
});

export default useProducts;

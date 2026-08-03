import { defineStore } from "pinia";
import axiosInstance from "../axiosInstance";
import { computed, ref } from "vue";
import useImages from "./StoreImages";
import useErrors from './StoreErrors';
import usePaginator from './StorePaginator';
import useQuery from './StoreQuery';
import { PAGE_PRODUCT } from "../constants";

export interface AttributeValue {
    id: number;
    attribute_id: number;
    code: string;
    value: string;
    slug: string;
    color: string | null;
    sort_order: number;
    facet_slug?: string;
}

export interface ProductVariant {
    id: number | null;
    product_id?: number | string;
    code: string;
    ean: string | null;
    name: string | null;
    price: number | string;
    sale_price: number | string | null;
    discount: number | string | null;
    active_price?: number | string;
    quantity: number | null;
    weight: number | string | null;
    min_order: number;
    is_default: boolean;
    published: boolean;
    sort_order: number;
    is_in_stock?: boolean;
    status?: any;
    thumb?: string;
    attribute_values?: AttributeValue[];
    endpoints?: Record<string, string>;
    permissions?: Record<string, any>;
    [key: string]: any;
}

export interface Product {
    id: string;
    code: string;
    name: string;
    slug: string;
    description: string;
    vat: number;
    image_id: number;
    published: boolean;
    unit_value: string;
    featured?: boolean;
    // Tovar na zákazku — nesleduje sa sklad a karta nehlási vypredané.
    made_to_order?: boolean;
    created_at: string;
    deleted_at: string;
    updated_at: string;
    status?: any;
    thumb?: string;
    images?: any[];
    categories?: any[];
    // Cena a sklad sú odvodené z variantov — na produkte len ako rozsah.
    price_from?: number | null;
    price_to?: number | null;
    total_quantity?: number | null;
    is_in_stock?: boolean;
    variants_count?: number;
    variants?: ProductVariant[];
    default_variant?: ProductVariant | null;
    attributes_taxonomy?: any[];
    endpoints: {
        update: string;
        destroy: string;
        variants?: string;
    };
    [key: string]: any;
}

export const defaultVariant = (): ProductVariant => ({
    id: null,
    code: '',
    ean: null,
    name: null,
    price: 0,
    sale_price: null,
    discount: null,
    quantity: null,
    weight: null,
    min_order: 1,
    is_default: false,
    published: true,
    sort_order: 0,
    attribute_values: [],
});

const defaultProduct = (): Product => ({
    id: '',
    code: '',
    name: '',
    slug: '',
    description: '',
    vat: 23,
    image_id: 0,
    published: false,
    unit_value: 'ks',
    featured: false,
    made_to_order: false,
    created_at: '',
    deleted_at: '',
    updated_at: '',
    thumb: '',
    images: [],
    categories: [],
    price_from: null,
    price_to: null,
    total_quantity: null,
    is_in_stock: false,
    variants_count: 0,
    variants: [],
    default_variant: null,
    attributes_taxonomy: [],
    endpoints: {
        update: '',
        destroy: '',
    },
});

const variantUrl = (productId: number | string, variantId?: number | string | null): string =>
    `${PAGE_PRODUCT.URL}/${productId}/variants` + (variantId ? `/${variantId}` : '');

// Setup-store (composition API) — potrebuje vlastný lokálny stav mimo `state`
// (productRequestId proti pretekaniu odpovedí pri rýchlom prepínaní produktov).
export const useProducts = defineStore('products', () => {
    let productRequestId = 0;

    const products = ref<Product[]>([]);
    const product = ref<Product>(defaultProduct());
    const url = ref<string>(PAGE_PRODUCT.URL);
    const searchUrl = ref<string>("");

    const getProducts = computed<Product[]>(() => products.value);
    const getProduct = computed<Product>(() => product.value);
    const getVariants = computed<ProductVariant[]>(() => product.value.variants ?? []);

    const productPayload = () => ({
        name: product.value.name,
        code: product.value.code?.trim().toUpperCase(),
        description: product.value.description,
        vat: product.value.vat,
        unit_value: product.value.unit_value,
        published: product.value.published,
        featured: product.value.featured,
        made_to_order: product.value.made_to_order ?? false,
        categories: product.value.categories?.map((c: any) => c.id ?? c) ?? [],
        status: product.value.status?.value || product.value.status,
    });

    const fetchProducts = async (): Promise<void> => {
        try {
            const q = useQuery();
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

    // --- Varianty -----------------------------------------------------------

    const variantPayload = (variant: ProductVariant) => ({
        code: variant.code?.trim() || null,
        ean: variant.ean?.toString().trim() || null,
        price: Number(variant.price) || 0,
        sale_price: variant.sale_price === '' || variant.sale_price === null
            ? null
            : Number(variant.sale_price),
        discount: variant.discount === '' || variant.discount === null
            ? null
            : Number(variant.discount),
        quantity: variant.quantity === '' || variant.quantity === null
            ? null
            : Number(variant.quantity),
        weight: variant.weight === '' || variant.weight === null
            ? null
            : Number(variant.weight),
        min_order: Number(variant.min_order) || 1,
        is_default: !!variant.is_default,
        published: !!variant.published,
        sort_order: Number(variant.sort_order) || 0,
        // Backend rozlišuje "prázdna kombinácia" od "kľúč neposlaný", preto vždy pole.
        attribute_values: (variant.attribute_values ?? []).map((v: any) => v.id ?? v),
    });

    const fetchVariants = async (productId: number | string): Promise<void> => {
        try {
            const response = await axiosInstance.get(variantUrl(productId));
            product.value.variants = response.data.data;
        } catch (e) {
            useErrors().setErrors(e);
        }
    };

    const storeVariant = async (productId: number | string, variant: ProductVariant): Promise<any> => {
        try {
            const response = await axiosInstance.post(variantUrl(productId), variantPayload(variant));
            await fetchVariants(productId);
            return response.data.data ?? response.data;
        } catch (e) {
            useErrors().setErrors(e);
            return null;
        }
    };

    const updateVariant = async (productId: number | string, variant: ProductVariant): Promise<any> => {
        try {
            const response = await axiosInstance.put(
                variantUrl(productId, variant.id),
                variantPayload(variant)
            );
            await fetchVariants(productId);
            return response.data.data ?? response.data;
        } catch (e) {
            useErrors().setErrors(e);
            return null;
        }
    };

    const destroyVariant = async (productId: number | string, variant: ProductVariant): Promise<boolean> => {
        if (!window.confirm(`Skutočne zmazať variant ${variant.code}?`)) {
            return false;
        }
        try {
            await axiosInstance.delete(variantUrl(productId, variant.id));
            await fetchVariants(productId);
            return true;
        } catch (e) {
            useErrors().setErrors(e);
            return false;
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

    return {
        products,
        product,
        url,
        searchUrl,
        getProducts,
        getProduct,
        getVariants,
        fetchProducts,
        fetchProduct,
        updateProduct,
        storeProduct,
        destroyProduct,
        fetchVariants,
        storeVariant,
        updateVariant,
        destroyVariant,
        fetchSearchInput,
        setPaginator,
        // Alias pre stránkovanie v zozname produktov.
        setUrl: setPaginator,
        setProduct,
        resetProduct,
    };
});

export default useProducts;

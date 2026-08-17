import { defineStore } from "pinia";
import { computed, ref, watch } from "vue";
import axiosInstance from "../axiosInstance";
import useErrors from "./StoreErrors";
import useCustomer from "./StoreCustomers";
import useCheckoutOptions from "./StoreCheckoutOptions";

const CART_STORAGE_KEY = "form";
const CUSTOMER_STORAGE_KEY = "customer";

const toPositiveNumber = (value: any, fallback = 0): number => {
    const number = Number(value);
    return Number.isFinite(number) && number > 0 ? number : fallback;
};

/**
 * Kľúč položky košíka je variant, nie produkt — dva rozmery tej istej vlajky
 * sú dve samostatné položky. Staršie košíky v localStorage variant nemajú,
 * tie sa kľúčujú produktom a server im doplní predvolený variant.
 */
const cartKey = (item: any): string =>
    item?.variant_id ? `v${item.variant_id}` : `p${item?.product_id ?? item?.id}`;

const normalizeCartItem = (item: any) => {
    const minOrder = toPositiveNumber(item?.min_order, 1);
    const inputOrder = Math.max(
        toPositiveNumber(item?.input_order, minOrder),
        minOrder
    );

    return {
        ...item,
        product_id: item?.product_id ?? item?.id ?? null,
        variant_id: item?.variant_id ?? null,
        variant_name: item?.variant_name ?? null,
        active_price: toPositiveNumber(item?.active_price),
        input_order: inputOrder,
        min_order: minOrder,
        key: cartKey(item),
    };
};

/**
 * Položka košíka z produktu a zvoleného variantu.
 */
export const cartItemFrom = (product: any, variant: any, quantity: number) => {
    const minOrder = toPositiveNumber(variant?.min_order, 1);

    return normalizeCartItem({
        product_id: product?.id,
        variant_id: variant?.id ?? null,
        name: product?.name,
        variant_name: variant?.name ?? null,
        slug: product?.slug,
        description: product?.description,
        thumb: variant?.thumb || product?.thumb,
        unit_value: product?.unit_value,
        vat: product?.vat,
        active_price: variant?.active_price ?? variant?.price,
        min_order: minOrder,
        input_order: Math.max(toPositiveNumber(quantity, minOrder), minOrder),
    });
};

const readJsonStorage = (key: string, fallback: any) => {
    try {
        const value = localStorage.getItem(key);
        return value ? JSON.parse(value) : fallback;
    } catch {
        localStorage.removeItem(key);
        return fallback;
    }
};

// Setup-store (defineStore s funkciou): povoľuje interné `watch` pre side-effecty
// (perzistencia košíka/zákazníka do localStorage) – v options-store by to nešlo.
export const useCheckouts = defineStore("checkouts", () => {
    const carts = ref<any[]>([]);
    const note = ref("");

    const getCarts = computed(() => carts.value);

    // Nahrádza pôvodný `grandCalculate` + module-level `watch` – počíta sa reaktívne.
    const getCheckout = computed(() => ({
        grandTotal: carts.value.reduce(
            (sum, item) =>
                sum +
                toPositiveNumber(item.active_price) *
                    toPositiveNumber(item.input_order),
            0
        ),
        grandQuantity: carts.value.reduce(
            (sum, item) => sum + toPositiveNumber(item.input_order),
            0
        ),
    }));

    const setlocalStorage = (): void => {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(carts.value));
    };

    const setlocalStorageCustomer = (): void => {
        localStorage.setItem(
            CUSTOMER_STORAGE_KEY,
            JSON.stringify(useCustomer().getCustomer)
        );
    };

    const submitCartToIndex = (data: any): void => {
        const cartItem = normalizeCartItem(data);
        const existingItem = carts.value.find((item) => item.key === cartItem.key);

        if (existingItem) {
            existingItem.input_order =
                toPositiveNumber(existingItem.input_order) + cartItem.input_order;
        } else {
            carts.value.push(cartItem);
        }
    };

    const addVariantToCart = (product: any, variant: any, quantity: number): void => {
        submitCartToIndex(cartItemFrom(product, variant, quantity));
    };

    const updateCartQuantity = (cart: any, value: any): void => {
        const existingItem = carts.value.find((item) => item.key === cart.key);

        if (!existingItem) {
            return;
        }

        existingItem.input_order = Math.max(
            toPositiveNumber(value, existingItem.min_order),
            toPositiveNumber(existingItem.min_order, 1)
        );
    };

    const getlocalStorage = (): void => {
        const stored = readJsonStorage(CART_STORAGE_KEY, []);
        carts.value = Array.isArray(stored) ? stored.map(normalizeCartItem) : [];
    };

    const removeCart = (cart: any): void => {
        carts.value = carts.value.filter((item) => item.key !== cart.key);
    };

    const resetCarts = (): void => {
        carts.value = [];
    };

    // notifyCustomer sa posiela iba pri objednávke zadanej internou obsluhou —
    // verejný e-shop potvrdenie zákazníkovi vždy odosiela (server to aj vynucuje).
    const storeCheckout = async (
        { notifyCustomer = true }: { notifyCustomer?: boolean } = {}
    ): Promise<string | boolean> => {
        const options = useCheckoutOptions();
        try {
            const response = await axiosInstance.post("/checkouts", {
                customer: useCustomer().getCustomer,
                notify_customer: notifyCustomer,
                // Server si cenu aj tak berie z databázy — posielame len identitu a počet.
                orderProducts: carts.value.map((item) => ({
                    id: item.product_id,
                    variant_id: item.variant_id,
                    input_order: item.input_order,
                })),
                note: note.value || null,
                shipping_method_id: options.selectedShippingId,
                payment_method_id: options.selectedPaymentId,
                coupon_code: options.getCouponCode || null,
                wants_coupon: options.getWantsCoupon,
            });
            localStorage.removeItem(CUSTOMER_STORAGE_KEY);
            carts.value = [];
            note.value = "";
            useCustomer().resetCustomer();
            options.reset();
            return response.data?.uuid ?? true;
        } catch (e) {
            useErrors().setErrors(e);
            return false;
        }
    };

    // Side-effecty pôvodne v module-level `watch(state, ...)` – teraz vnútri setup-store.
    watch(carts, () => setlocalStorage(), { deep: true });
    watch(
        () => useCustomer().getCustomer,
        () => setlocalStorageCustomer(),
        { deep: true }
    );

    return {
        carts,
        note,
        getCarts,
        getCheckout,
        submitCartToIndex,
        addVariantToCart,
        updateCartQuantity,
        setlocalStorage,
        setlocalStorageCustomer,
        getlocalStorage,
        removeCart,
        resetCarts,
        storeCheckout,
    };
});

export default useCheckouts;

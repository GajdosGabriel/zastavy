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

const normalizeCartItem = (item: any) => {
    const minOrder = toPositiveNumber(item?.min_order, 1);
    const inputOrder = Math.max(
        toPositiveNumber(item?.input_order, minOrder),
        minOrder
    );

    return {
        ...item,
        active_price: toPositiveNumber(item?.active_price),
        input_order: inputOrder,
        min_order: minOrder,
    };
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
        const existingItem = carts.value.find((item) => item.id === cartItem.id);

        if (existingItem) {
            existingItem.input_order =
                toPositiveNumber(existingItem.input_order) + cartItem.input_order;
        } else {
            carts.value.push(cartItem);
        }
    };

    const updateCartQuantity = (cart: any, value: any): void => {
        const existingItem = carts.value.find((item) => item.id === cart.id);

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
        carts.value = carts.value.filter((item) => item.id != cart.id);
    };

    const resetCarts = (): void => {
        carts.value = [];
    };

    const storeCheckout = async (): Promise<string | boolean> => {
        const options = useCheckoutOptions();
        try {
            const response = await axiosInstance.post("/checkouts", {
                customer: useCustomer().getCustomer,
                orderProducts: carts.value,
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

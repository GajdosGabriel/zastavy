import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';

export interface ShippingMethod {
    id: number;
    name: string;
    price: number | string;
    free_from_price: number | string | null;
}

export interface PaymentMethod {
    id: number;
    name: string;
    fee: number | string;
}

export interface CouponData {
    id: number;
    code: string;
    type: 'percent' | 'fixed';
    value: number;
    discount: number;
}

export type CouponMode = 'get' | 'have' | null;

interface CheckoutOptionsState {
    shippingMethods: ShippingMethod[];
    paymentMethods: PaymentMethod[];
    selectedShippingId: number | null;
    selectedPaymentId: number | null;
    couponMode: CouponMode;
    couponCode: string;
    couponData: CouponData | null;
    couponError: string | null;
    couponLoading: boolean;
}

export const useCheckoutOptions = defineStore('checkoutOptions', {
    state: (): CheckoutOptionsState => ({
        shippingMethods: [],
        paymentMethods: [],
        selectedShippingId: null,
        selectedPaymentId: null,
        couponMode: null,
        couponCode: '',
        couponData: null,
        couponError: null,
        couponLoading: false,
    }),

    getters: {
        // Pass-through gettery ponechané, aby ostalo zachované verejné API storu
        // a konzumenti sa menili minimálne.
        getShippingMethods: (s): ShippingMethod[] => s.shippingMethods,
        getPaymentMethods: (s): PaymentMethod[] => s.paymentMethods,
        getSelectedShipping: (s): ShippingMethod | null =>
            s.shippingMethods.find(m => m.id === s.selectedShippingId) ?? null,
        getSelectedPayment: (s): PaymentMethod | null =>
            s.paymentMethods.find(m => m.id === s.selectedPaymentId) ?? null,
        getCouponMode: (s): CouponMode => s.couponMode,
        getCouponData: (s): CouponData | null => s.couponData,
        getCouponError: (s): string | null => s.couponError,
        getCouponCode: (s): string => s.couponCode,
        isCouponLoading: (s): boolean => s.couponLoading,
        getWantsCoupon: (s): boolean => s.couponMode === 'get',

        paymentFee: (s): number => {
            const method = s.paymentMethods.find(m => m.id === s.selectedPaymentId);
            return method ? parseFloat(String(method.fee)) || 0 : 0;
        },

        discountAmount: (s): number => s.couponData?.discount ?? 0,
    },

    actions: {
        // shippingPrice bol pôvodne "funkčný getter" (getter s argumentom) — v Pinia
        // patrí ako metóda/akcia, keďže berie vstup a nie je to cache-ovaná computed.
        shippingPrice(cartTotal: number): number {
            const method = this.shippingMethods.find(m => m.id === this.selectedShippingId);
            if (!method) return 0;
            if (method.free_from_price !== null && cartTotal >= parseFloat(String(method.free_from_price))) return 0;
            return parseFloat(String(method.price)) || 0;
        },

        async fetchShippingMethods(): Promise<void> {
            const { data } = await axiosInstance.get('/shipping-methods');
            this.shippingMethods = data.data ?? [];
            if (this.shippingMethods.length && !this.selectedShippingId) {
                this.selectedShippingId = this.shippingMethods[0].id;
            }
        },

        async fetchPaymentMethods(): Promise<void> {
            const { data } = await axiosInstance.get('/payment-methods');
            this.paymentMethods = data.data ?? [];
            if (this.paymentMethods.length && !this.selectedPaymentId) {
                this.selectedPaymentId = this.paymentMethods[0].id;
            }
        },

        selectShipping(id: number): void {
            this.selectedShippingId = id;
        },

        selectPayment(id: number): void {
            this.selectedPaymentId = id;
        },

        setCouponMode(mode: CouponMode): void {
            this.couponMode = mode;
            if (mode === 'get') {
                this.couponCode = '';
                this.couponData = null;
                this.couponError = null;
            }
        },

        setCouponCode(code: string): void {
            this.couponCode = code;
            this.couponData = null;
            this.couponError = null;
        },

        async validateCoupon(cartTotal: number): Promise<void> {
            if (!this.couponCode.trim()) return;
            this.couponLoading = true;
            this.couponError = null;
            this.couponData = null;
            try {
                const { data } = await axiosInstance.post('/coupons/validate', {
                    code: this.couponCode,
                    cart_total: cartTotal,
                });
                this.couponData = data.data;
            } catch (e: any) {
                this.couponError = e?.response?.data?.message ?? 'Kupón je neplatný.';
            } finally {
                this.couponLoading = false;
            }
        },

        clearCoupon(): void {
            this.couponCode = '';
            this.couponData = null;
            this.couponError = null;
        },

        reset(): void {
            this.selectedShippingId = this.shippingMethods[0]?.id ?? null;
            this.selectedPaymentId = this.paymentMethods[0]?.id ?? null;
            this.couponMode = null;
            this.clearCoupon();
        },
    },
});

export default useCheckoutOptions;

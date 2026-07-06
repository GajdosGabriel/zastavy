import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import useErrors from './StoreErrors';

const BASE_URL = '/admin/coupons';

export type CouponType = 'percent' | 'fixed';

export interface CouponForm {
    id: number | null;
    code: string;
    type: CouponType;
    value: number | string;
    min_order_price: number | string | null;
    usage_limit: number | string | null;
    valid_from: string | null;
    valid_to: string | null;
    active: boolean;
}

const empty = (): CouponForm => ({
    id: null,
    code: '',
    type: 'percent',
    value: '',
    min_order_price: '',
    usage_limit: '',
    valid_from: '',
    valid_to: '',
    active: true,
});

// Pomocná funkcia — premenovaná z generateCode, aby nekolidovala s rovnomennou akciou.
const randomCode = (): string => {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 8; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return code;
};

interface CouponsState {
    coupons: CouponForm[];
    trashedCoupons: CouponForm[];
    coupon: CouponForm;
}

export const useCoupons = defineStore('coupons', {
    state: (): CouponsState => ({
        coupons: [],
        trashedCoupons: [],
        coupon: empty(),
    }),

    getters: {
        getCoupons: (s): CouponForm[] => s.coupons,
        getTrashedCoupons: (s): CouponForm[] => s.trashedCoupons,
    },

    actions: {
        async fetchCoupons(): Promise<void> {
            try {
                const { data } = await axiosInstance.get(BASE_URL);
                this.coupons = data.data;
                this.trashedCoupons = data.trashed ?? [];
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async saveCoupon(): Promise<boolean> {
            try {
                const { id, ...payload } = this.coupon;
                const body = {
                    ...payload,
                    min_order_price: payload.min_order_price || null,
                    usage_limit: payload.usage_limit || null,
                    valid_from: payload.valid_from || null,
                    valid_to: payload.valid_to || null,
                };
                if (id) {
                    await axiosInstance.put(`${BASE_URL}/${id}`, body);
                } else {
                    await axiosInstance.post(BASE_URL, body);
                }
                await this.fetchCoupons();
                this.resetCoupon();
                return true;
            } catch (e) {
                useErrors().setErrors(e);
                return false;
            }
        },

        editCoupon(coupon: CouponForm): void {
            this.coupon = { ...empty(), ...coupon };
        },

        async destroyCoupon(coupon: CouponForm): Promise<void> {
            if (!window.confirm('Skutočne vymazať kupón?')) return;
            try {
                await axiosInstance.delete(`${BASE_URL}/${coupon.id}`);
                await this.fetchCoupons();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async restoreCoupon(coupon: CouponForm): Promise<void> {
            try {
                await axiosInstance.post(`${BASE_URL}/${coupon.id}/restore`);
                await this.fetchCoupons();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        generateCode(): void {
            this.coupon.code = randomCode();
        },

        resetCoupon(): void {
            this.coupon = empty();
        },
    },
});

export default useCoupons;

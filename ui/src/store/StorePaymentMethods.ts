import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import useErrors from './StoreErrors';

const BASE_URL = '/admin/payment-methods';

export type PaymentType = 'card' | 'bank_transfer' | 'cash_on_delivery';

export interface PaymentMethodForm {
    id: number | null;
    name: string;
    fee: number | string;
    type: PaymentType;
    active: boolean;
    sort_order: number;
}

export interface PaymentTypeOption {
    value: PaymentType;
    label: string;
}

const empty = (): PaymentMethodForm => ({
    id: null,
    name: '',
    fee: '',
    type: 'bank_transfer',
    active: true,
    sort_order: 99,
});

const TYPES: PaymentTypeOption[] = [
    { value: 'card', label: 'Platobná karta' },
    { value: 'bank_transfer', label: 'Bankový prevod' },
    { value: 'cash_on_delivery', label: 'Dobierka' },
];

interface PaymentMethodsState {
    paymentMethods: PaymentMethodForm[];
    trashedMethods: PaymentMethodForm[];
    paymentMethod: PaymentMethodForm;
}

export const usePaymentMethods = defineStore('paymentMethods', {
    state: (): PaymentMethodsState => ({
        paymentMethods: [],
        trashedMethods: [],
        paymentMethod: empty(),
    }),

    getters: {
        getPaymentMethods: (s): PaymentMethodForm[] => s.paymentMethods,
        getTrashedMethods: (s): PaymentMethodForm[] => s.trashedMethods,
        getPaymentTypes: (): PaymentTypeOption[] => TYPES,
    },

    actions: {
        async fetchPaymentMethods(): Promise<void> {
            try {
                const { data } = await axiosInstance.get(BASE_URL);
                this.paymentMethods = data.data;
                this.trashedMethods = data.trashed ?? [];
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async savePaymentMethod(): Promise<boolean> {
            try {
                const { id, ...payload } = this.paymentMethod;
                if (id) {
                    await axiosInstance.put(`${BASE_URL}/${id}`, payload);
                } else {
                    await axiosInstance.post(BASE_URL, payload);
                }
                await this.fetchPaymentMethods();
                this.resetPaymentMethod();
                return true;
            } catch (e) {
                useErrors().setErrors(e);
                return false;
            }
        },

        editPaymentMethod(method: PaymentMethodForm): void {
            this.paymentMethod = { ...empty(), ...method };
        },

        async destroyPaymentMethod(method: PaymentMethodForm): Promise<void> {
            if (!window.confirm('Skutočne vymazať spôsob platby?')) return;
            try {
                await axiosInstance.delete(`${BASE_URL}/${method.id}`);
                await this.fetchPaymentMethods();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async restorePaymentMethod(method: PaymentMethodForm): Promise<void> {
            try {
                await axiosInstance.post(`${BASE_URL}/${method.id}/restore`);
                await this.fetchPaymentMethods();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        resetPaymentMethod(): void {
            this.paymentMethod = empty();
        },
    },
});

export default usePaymentMethods;

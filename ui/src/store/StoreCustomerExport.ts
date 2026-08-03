import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import useErrors from './StoreErrors';
import { PAGE_CUSTOMER_EXPORT } from '../constants';

export interface ExportAttribute {
    value: string;
    label: string;
}

interface CustomerExportState {
    attributes: ExportAttribute[];
    selected: string[];
    onlyWithEmail: boolean;
    exporting: boolean;
}

// Predvoľba pokrýva najčastejší dôvod exportu — rozposlanie e-mailu.
const DEFAULT_SELECTED = ['name', 'email'];

export const useCustomerExport = defineStore('customerExport', {
    state: (): CustomerExportState => ({
        attributes: [],
        selected: [],
        onlyWithEmail: false,
        exporting: false,
    }),

    getters: {
        getAttributes: (s): ExportAttribute[] => s.attributes,
        getSelected: (s): string[] => s.selected,
        getOnlyWithEmail: (s): boolean => s.onlyWithEmail,
        isExporting: (s): boolean => s.exporting,
    },

    actions: {
        async fetchAttributes(): Promise<void> {
            try {
                const response = await axiosInstance.get(`${PAGE_CUSTOMER_EXPORT.URL}/attributes`);
                this.attributes = response.data.data;

                const available = this.attributes.map((attribute) => attribute.value);
                this.selected = DEFAULT_SELECTED.filter((value) => available.includes(value));
            } catch (error) {
                this.attributes = [];
                useErrors().setErrors(error);
            }
        },

        toggleAttribute(value: string): void {
            if (this.selected.includes(value)) {
                this.selected = this.selected.filter((item) => item !== value);
            } else {
                this.selected.push(value);
            }
        },

        selectAllAttributes(): void {
            this.selected = this.attributes.map((attribute) => attribute.value);
        },

        clearAttributes(): void {
            this.selected = [];
        },

        setOnlyWithEmail(value: boolean): void {
            this.onlyWithEmail = value;
        },

        async exportCustomers(): Promise<boolean> {
            this.exporting = true;

            try {
                const response = await axiosInstance.get(PAGE_CUSTOMER_EXPORT.URL, {
                    params: {
                        // Poradie zaškrtnutia určuje poradie stĺpcov v CSV.
                        attributes: this.selected,
                        only_with_email: this.onlyWithEmail ? 1 : 0,
                    },
                    responseType: 'blob',
                });

                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', `zakaznici_${Date.now()}.csv`);
                document.body.appendChild(link);
                link.click();
                link.remove();
                window.URL.revokeObjectURL(url);

                return true;
            } catch (error) {
                useErrors().setErrors(error);
                return false;
            } finally {
                this.exporting = false;
            }
        },
    },
});

export default useCustomerExport;

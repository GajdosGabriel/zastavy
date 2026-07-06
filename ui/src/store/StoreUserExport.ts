import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import useErrors from './StoreErrors';
import { PAGE_USER_EXPORT } from '../constants';

export interface ExportAttribute {
    value: string;
    label: string;
}

interface UserExportState {
    attributes: ExportAttribute[];
    selected: string[];
    exporting: boolean;
}

export const useUserExport = defineStore('userExport', {
    state: (): UserExportState => ({
        attributes: [],
        selected: [],
        exporting: false,
    }),

    getters: {
        getAttributes: (s): ExportAttribute[] => s.attributes,
        getSelected: (s): string[] => s.selected,
        isExporting: (s): boolean => s.exporting,
    },

    actions: {
        async fetchAttributes(): Promise<void> {
            try {
                const response = await axiosInstance.get(`${PAGE_USER_EXPORT.URL}/attributes`);
                this.attributes = response.data.data;
                this.selected = this.attributes.some((attribute) => attribute.value === 'email')
                    ? ['email']
                    : [];
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

        async exportUsers(): Promise<boolean> {
            this.exporting = true;

            try {
                const response = await axiosInstance.get(PAGE_USER_EXPORT.URL, {
                    params: { attributes: this.selected },
                    responseType: 'blob',
                });

                const url = window.URL.createObjectURL(new Blob([response.data]));
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', `pouzivatelia_${Date.now()}.csv`);
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

export default useUserExport;

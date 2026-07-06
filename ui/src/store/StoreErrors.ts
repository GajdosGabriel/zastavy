import { defineStore } from "pinia";

interface ErrorsState {
    errors: any[];
    fieldErrors: Record<string, any>;
}

export const useErrors = defineStore("errors", {
    state: (): ErrorsState => ({
        errors: [],
        fieldErrors: {},
    }),

    getters: {
        getErrors: (state): any[] => state.errors,
        getFieldErrors: (state): Record<string, any> => state.fieldErrors,
    },

    actions: {
        setErrors(error: any): void {
            this.resetErrors();

            const fieldErrors = error?.response?.data?.errors ?? {};
            this.fieldErrors = fieldErrors;

            const fieldMessages = Object.values(fieldErrors).flat();

            if (fieldMessages.length > 0) {
                fieldMessages.forEach((msg) => this.errors.push(msg));
            } else {
                const message =
                    error?.response?.data?.message ??
                    error?.message ??
                    "Nastala neočakávaná chyba.";
                this.errors.push(message);
            }
        },

        clearFieldError(key: string): void {
            const msgs = Array.isArray(this.fieldErrors[key])
                ? this.fieldErrors[key]
                : this.fieldErrors[key]
                ? [this.fieldErrors[key]]
                : [];
            if (msgs.length) {
                this.errors = this.errors.filter((e) => !msgs.includes(e));
                delete this.fieldErrors[key];
            }
        },

        removeError(index: number): void {
            this.errors = this.errors.filter((_, i) => i !== index);
        },

        resetErrors(): void {
            this.errors = [];
            this.fieldErrors = {};
        },
    },
});

export default useErrors;

import { defineStore } from "pinia";

interface FlashState {
    message: string;
    key: number;
}

let timer: ReturnType<typeof setTimeout> | undefined;

/**
 * Potvrdenie úspešnej akcie. Store je globálny, takže hláška prežije
 * presmerovanie na zoznam — zobrazí ju BaseLayout a sama zmizne.
 */
export const useFlash = defineStore("flash", {
    state: (): FlashState => ({ message: "", key: 0 }),

    actions: {
        success(message: string): void {
            clearTimeout(timer);
            this.message = message;
            this.key++;
            timer = setTimeout(() => this.clear(), 6000);
        },

        clear(): void {
            clearTimeout(timer);
            this.message = "";
        },
    },
});

export default useFlash;

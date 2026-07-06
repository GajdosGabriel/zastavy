import { defineStore } from "pinia";
import { Page } from "../constants";

const publicMainNavigation: Page[] = [
    {
        NAME: "Obchodné podmienky",
        ROUTE: "public.obchodne.podmienky",
        URL: "/obchodne-podmienky",
        ICON: "",
    },
    {
        NAME: "Kontakt",
        ROUTE: "public.contactUs",
        URL: "/kontakt",
        ICON: "",
    },
];

interface NavigationState {
    main: Page[];
}

export const useNavigation = defineStore("navigation", {
    state: (): NavigationState => ({
        main: [...publicMainNavigation],
    }),

    getters: {
        getMainNavigation: (state): Page[] => state.main,
    },

    actions: {
        setMainNavigation(items?: Page[]): void {
            this.main = items?.length ? items : [...publicMainNavigation];
        },

        resetNavigation(): void {
            this.main = [...publicMainNavigation];
        },
    },
});

export default useNavigation;

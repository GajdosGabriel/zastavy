import { computed, reactive } from "vue";

const publicMainNavigation = [
    {
        NAME: "Kontakt",
        ROUTE: "public.contactUs",
        URL: "/kontakt",
        ICON: "",
    },
];

const defaultState = {
    main: [...publicMainNavigation],
};

const state = reactive(defaultState);

const getters = {
    getMainNavigation: computed(() => state.main),
};

const actions = {
    setMainNavigation: (items) => {
        state.main = items?.length ? items : [...publicMainNavigation];
    },

    resetNavigation: () => {
        state.main = [...publicMainNavigation];
    },
};

export default () => ({
    state,
    ...getters,
    ...actions,
});

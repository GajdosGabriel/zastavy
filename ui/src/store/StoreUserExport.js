import axiosInstance from "../axiosInstance";
import { reactive, computed } from "vue";
import useErrors from "./StoreErrors";
import { PAGE_USER_EXPORT } from "../constants";

const { setErrors } = useErrors();

const defaultState = {
    attributes: [],
    selected: [],
    exporting: false,
};

const state = reactive(defaultState);

const getters = {
    getAttributes: computed(() => state.attributes),
    getSelected: computed(() => state.selected),
    isExporting: computed(() => state.exporting),
};

const actions = {
    fetchAttributes: async () => {
        try {
            const response = await axiosInstance.get(`${PAGE_USER_EXPORT.URL}/attributes`);
            state.attributes = response.data.data;
            state.selected = state.attributes.some((attribute) => attribute.value === "email")
                ? ["email"]
                : [];
        } catch (error) {
            state.attributes = [];
            setErrors(error);
        }
    },

    toggleAttribute: (value) => {
        if (state.selected.includes(value)) {
            state.selected = state.selected.filter((item) => item !== value);
        } else {
            state.selected.push(value);
        }
    },

    selectAllAttributes: () => {
        state.selected = state.attributes.map((attribute) => attribute.value);
    },

    clearAttributes: () => {
        state.selected = [];
    },

    exportUsers: async () => {
        state.exporting = true;

        try {
            const response = await axiosInstance.get(PAGE_USER_EXPORT.URL, {
                params: { attributes: state.selected },
                responseType: "blob",
            });

            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement("a");
            link.href = url;
            link.setAttribute("download", `pouzivatelia_${Date.now()}.csv`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);

            return true;
        } catch (error) {
            setErrors(error);
            return false;
        } finally {
            state.exporting = false;
        }
    },
};

export default () => ({
    state,
    ...getters,
    ...actions,
});

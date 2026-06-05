
import { computed, reactive } from "vue";


const defaultState = {
    meta: {},
    links: {},
};

const state = reactive(defaultState);

const getters = {
    getPaginator: computed(() => state.meta),
    getLinks: computed(() => state.links),
};
const actions = {
    setPaginator: (meta) => {
        state.meta = meta;
    },
    setLinks: (links) => {
        state.links = links;
    },

    resetPaginator: () => {
        state.meta = null;
        state.links = null;
    }
};

export default () => ({
    state,
    ...getters,
    ...actions,
});

import { reactive, computed } from "vue";


const defaultState = reactive({
    query: [],
    stringForUrl: [],
});
const state = reactive(defaultState);

const getters = {
    getQuery: computed(() => state.query),
    getQueryStringUrl: computed(() => state.stringForUrl),
    getQueryLength: computed(() => state.query.length)
};

const mutators = {
    transformQueryToString: () => {
        state.stringForUrl = state.query.length > 0 
        ? `?${state.query.join('&')}` 
        : "";
    }

};

const actions = {
    setQuery: (data) => {
        state.query.push(data);
        mutators.transformQueryToString();
    },
    removeQuery: (data) => {
        state.query = state.query.filter(object => object.key !== data.key);
        mutators.transformQueryToString();
    },

    resetQuery: () => {
        state.query = [];
        state.stringForUrl = [];
        mutators.transformQueryToString();
    },

};

export default () => ({
    state,
    ...actions,
    ...getters,
});

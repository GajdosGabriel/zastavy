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

const normalizeQuery = (data) => {
    if (typeof data === 'string') {
        return data;
    }

    return data.key + encodeURIComponent(data.value ?? '');
};

const normalizeQueryKey = (data) => {
    const query = normalizeQuery(data);
    const separatorIndex = query.indexOf('=');

    return separatorIndex === -1 ? query : query.slice(0, separatorIndex + 1);
};

const actions = {
    setQuery: (data) => {
        const query = normalizeQuery(data);
        const key = normalizeQueryKey(data);

        state.query = state.query.filter(item => normalizeQueryKey(item) !== key);

        if (!query.endsWith('=')) {
            state.query.push(query);
        }

        mutators.transformQueryToString();
    },
    removeQuery: (data) => {
        const key = normalizeQueryKey(data);
        state.query = state.query.filter(item => normalizeQueryKey(item) !== key);
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

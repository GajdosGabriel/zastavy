import { defineStore } from "pinia";

type QueryInput = string | { key: string; value?: any };

interface QueryState {
    query: string[];
    stringForUrl: string;
}

const normalizeQuery = (data: QueryInput): string => {
    if (typeof data === "string") {
        return data;
    }

    return data.key + encodeURIComponent(data.value ?? "");
};

const normalizeQueryKey = (data: QueryInput): string => {
    const query = normalizeQuery(data);
    const separatorIndex = query.indexOf("=");

    return separatorIndex === -1 ? query : query.slice(0, separatorIndex + 1);
};

export const useQuery = defineStore("query", {
    state: (): QueryState => ({
        query: [],
        stringForUrl: "",
    }),

    getters: {
        getQuery: (state): string[] => state.query,
        getQueryStringUrl: (state): string => state.stringForUrl,
        getQueryLength: (state): number => state.query.length,
    },

    actions: {
        transformQueryToString(): void {
            this.stringForUrl =
                this.query.length > 0 ? `?${this.query.join("&")}` : "";
        },

        setQuery(data: QueryInput): void {
            const query = normalizeQuery(data);
            const key = normalizeQueryKey(data);

            this.query = this.query.filter(
                (item) => normalizeQueryKey(item) !== key
            );

            if (!query.endsWith("=")) {
                this.query.push(query);
            }

            this.transformQueryToString();
        },

        removeQuery(data: QueryInput): void {
            const key = normalizeQueryKey(data);
            this.query = this.query.filter(
                (item) => normalizeQueryKey(item) !== key
            );
            this.transformQueryToString();
        },

        resetQuery(): void {
            this.query = [];
            this.stringForUrl = "";
        },
    },
});

export default useQuery;

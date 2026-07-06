import { defineStore } from "pinia";

interface PaginatorState {
    meta: Record<string, any> | null;
    links: Record<string, any> | null;
}

export const usePaginator = defineStore("paginator", {
    state: (): PaginatorState => ({
        meta: {},
        links: {},
    }),

    getters: {
        getPaginator: (state) => state.meta,
        getLinks: (state) => state.links,
    },

    actions: {
        setPaginator(meta: Record<string, any>): void {
            this.meta = meta;
        },
        setLinks(links: Record<string, any>): void {
            this.links = links;
        },
        resetPaginator(): void {
            this.meta = null;
            this.links = null;
        },
    },
});

export default usePaginator;

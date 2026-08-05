import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import usePaginator from './StorePaginator';
import useErrors from './StoreErrors';
import useQuery from './StoreQuery';
import { PAGE_STOCK } from '../constants';

export interface StockRow {
    id: number;
    [key: string]: any;
}

export interface StockSummaryRow {
    product_id: number;
    [key: string]: any;
}

interface StockCreateForm {
    // Príjem ide vždy na konkrétnu skladovú položku, teda variant.
    product_variant_id: number | null;
    quantity: number | string;
    price: number | string;
    note: string;
}

interface StocksState {
    url: string;
    stocks: StockRow[];
    summary: StockSummaryRow[];
    variantSummary: StockSummaryRow | null;
    selectedVariantId: number | null;
    create: StockCreateForm;
}

const emptyCreate = (): StockCreateForm => ({
    product_variant_id: null,
    quantity: '',
    price: '',
    note: '',
});

export const useStocks = defineStore('stocks', {
    state: (): StocksState => ({
        url: PAGE_STOCK.URL,
        stocks: [],
        summary: [],
        variantSummary: null,
        selectedVariantId: null,
        create: emptyCreate(),
    }),

    getters: {
        getStocks: (s): StockRow[] => s.stocks,
        getSummary: (s): StockSummaryRow[] => s.summary,
        getSelectedVariantId: (s): number | null => s.selectedVariantId,
        getVariantSummary: (s): StockSummaryRow | null => s.variantSummary,
    },

    actions: {
        async fetchStocks(): Promise<void> {
            try {
                const q = useQuery();
                const paginator = usePaginator();
                const parts = [
                    q.stringForUrl ? q.stringForUrl.slice(1) : '',
                    this.selectedVariantId ? `byVariant=${this.selectedVariantId}` : '',
                ].filter(Boolean);
                // this.url môže už niesť ?page=N z paginátora — filtre sa musia pripojiť cez &.
                const separator = this.url.includes('?') ? '&' : '?';
                const qs = parts.length ? separator + parts.join('&') : '';
                const response = await axiosInstance.get(this.url + qs);
                this.stocks = response.data.data;
                paginator.setPaginator(response.data.meta);
                paginator.setLinks(response.data.links);
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async fetchSummary(): Promise<void> {
            try {
                const response = await axiosInstance.get(PAGE_STOCK.URL + '/summary');
                this.summary = response.data.data;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async fetchVariantSummary(variantId: number): Promise<void> {
            try {
                const response = await axiosInstance.get(`${PAGE_STOCK.URL}/summary/${variantId}`);
                this.variantSummary = response.data.data;
            } catch (e) {
                this.variantSummary = null;
                useErrors().setErrors(e);
            }
        },

        // Prepne zoznam pohybov na jednu skladovú položku (detail /sklad/:id) alebo na všetky.
        selectVariant(variantId: number | null): void {
            this.selectedVariantId = variantId;
            this.url = PAGE_STOCK.URL;
        },

        async storeStock(): Promise<void> {
            try {
                await axiosInstance.post(PAGE_STOCK.URL, this.create);
                this.create = emptyCreate();
                await this.fetchSummary();
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        async destroyStock(id: number): Promise<void> {
            if (!window.confirm('Skutočne vymazať pohyb?')) return;
            await axiosInstance.delete(PAGE_STOCK.URL + '/' + id);
            this.fetchStocks();
            if (this.selectedVariantId) {
                this.fetchVariantSummary(this.selectedVariantId);
            } else {
                this.fetchSummary();
            }
        },

        // Vlastná akcia (nezamieňať s paginátorovým setPaginator voľaným vo fetchStocks).
        setPaginator(url: string): void {
            this.url = url;
            this.fetchStocks();
        },

        resetUrl(): void {
            this.url = PAGE_STOCK.URL;
            this.selectedVariantId = null;
            this.variantSummary = null;
        },
    },
});

export default useStocks;

import { defineStore } from 'pinia';
import axiosInstance from '../axiosInstance';
import usePaginator from './StorePaginator';
import useErrors from './StoreErrors';
import useQuery from './StoreQuery';
import { confirmDialog } from '../models/confirmDialog';
import { PAGE_STOCK } from '../constants';

export interface StockRow {
    id: number;
    [key: string]: any;
}

export interface StockSummaryRow {
    product_id: number;
    [key: string]: any;
}

export interface StockVariantOption {
    id: number;
    code: string;
    label: string;
    unit_value: string | null;
    tracked_quantity: number | null;
    balance: number;
    [key: string]: any;
}

export interface StockSummaryMeta {
    variants: number;
    below_zero: number;
    total_value: number;
    total_in: number;
    total_out: number;
    total_writeoff: number;
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
    summaryMeta: StockSummaryMeta | null;
    variants: StockVariantOption[];
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
        summaryMeta: null,
        variants: [],
        variantSummary: null,
        selectedVariantId: null,
        create: emptyCreate(),
    }),

    getters: {
        getStocks: (s): StockRow[] => s.stocks,
        getSummary: (s): StockSummaryRow[] => s.summary,
        getSummaryMeta: (s): StockSummaryMeta | null => s.summaryMeta,
        getVariants: (s): StockVariantOption[] => s.variants,
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
                this.summaryMeta = response.data.meta ?? null;
            } catch (e) {
                useErrors().setErrors(e);
            }
        },

        // Plochý zoznam všetkých variantov pre formulár príjmu — nestránkuje sa,
        // inak by sa dala naskladniť len prvá stránka produktov.
        async fetchVariants(): Promise<void> {
            try {
                const response = await axiosInstance.get(PAGE_STOCK.URL + '/variants');
                this.variants = response.data.data;
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

        // Vracia úspech — formulár nesmie odnavigovať preč, keď zápis zlyhal.
        async storeStock(): Promise<boolean> {
            try {
                useErrors().resetErrors();
                await axiosInstance.post(PAGE_STOCK.URL, this.create);
                this.create = emptyCreate();
                await this.fetchSummary();
                return true;
            } catch (e) {
                useErrors().setErrors(e);
                return false;
            }
        },

        async destroyStock(id: number): Promise<void> {
            const confirmed = await confirmDialog({
                title: 'Vymazať pohyb?',
                message: 'Pohyb sa odstráni a stav na sklade sa prepočíta.',
                confirmLabel: 'Vymazať',
            });

            if (!confirmed) return;

            try {
                await axiosInstance.delete(PAGE_STOCK.URL + '/' + id);
            } catch (e) {
                useErrors().setErrors(e);
                return;
            }

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

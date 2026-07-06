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
    product_id: number | null;
    quantity: number | string;
    price: number | string;
    note: string;
}

interface StocksState {
    url: string;
    stocks: StockRow[];
    summary: StockSummaryRow[];
    selectedProductId: number | null;
    create: StockCreateForm;
}

const emptyCreate = (): StockCreateForm => ({
    product_id: null,
    quantity: '',
    price: '',
    note: '',
});

export const useStocks = defineStore('stocks', {
    state: (): StocksState => ({
        url: PAGE_STOCK.URL,
        stocks: [],
        summary: [],
        selectedProductId: null,
        create: emptyCreate(),
    }),

    getters: {
        getStocks: (s): StockRow[] => s.stocks,
        getSummary: (s): StockSummaryRow[] => s.summary,
        getSelectedProductId: (s): number | null => s.selectedProductId,
        getSelectedProduct: (s): StockSummaryRow | null =>
            s.summary.find((p) => p.product_id === s.selectedProductId) ?? null,
    },

    actions: {
        async fetchStocks(): Promise<void> {
            try {
                const { state: q } = useQuery();
                const paginator = usePaginator();
                const parts = [
                    q.stringForUrl ? q.stringForUrl.slice(1) : '',
                    this.selectedProductId ? `byProduct=${this.selectedProductId}` : '',
                ].filter(Boolean);
                const qs = parts.length ? `?${parts.join('&')}` : '';
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

        selectProduct(productId: number): void {
            this.selectedProductId = this.selectedProductId === productId ? null : productId;
            this.fetchStocks();
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
            this.fetchSummary();
        },

        // Vlastná akcia (nezamieňať s paginátorovým setPaginator voľaným vo fetchStocks).
        setPaginator(url: string): void {
            this.url = url;
            this.fetchStocks();
        },

        resetUrl(): void {
            this.url = PAGE_STOCK.URL;
            this.selectedProductId = null;
        },
    },
});

export default useStocks;

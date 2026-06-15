<script setup>
import { onMounted, ref } from "vue";
import useStocks from "../../store/StoreStocks";
import useUser from "../../store/StoreUsers";
import useQuery from "../../store/StoreQuery";
import PaginationComponent from "../plugins/pagination.vue";
import tableRow from "./component/tableRow.vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import spinnerTable from "../icons/spinnerTable.vue";
import loadingStore from "../../store/StoreLoading";
import { PAGE_STOCK } from "../../constants";

const { state, fetchStocks, fetchSummary, setPaginator, selectProduct, getStocks, getSummary, getSelectedProductId, getSelectedProduct } = useStocks();
const { getUserCan } = useUser();
const { setQuery, removeQuery } = useQuery();

const searchInput = ref("");

const onSearch = (val) => {
    searchInput.value = val;
    if (val) {
        setQuery({ key: 'bySearchInput=', value: val });
    } else {
        removeQuery({ key: 'bySearchInput=', value: '' });
    }
    state.url = PAGE_STOCK.URL;
    fetchStocks();
};

onMounted(() => {
    fetchSummary();
    fetchStocks();
});

const paginatorUrl = (url) => setPaginator(url);

const onClickProduct = (productId) => selectProduct(productId);

const balanceClass = (balance) => {
    if (balance > 10) return 'text-green-700 font-bold';
    if (balance > 0)  return 'text-amber-600 font-bold';
    return 'text-red-600 font-bold';
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{
                    title: 'Sklad',
                    buttonLink: getUserCan?.['stocks.create']
                        ? { name: 'Príjem tovaru', spinner: true, link: '/sklad/create', icon: 'plus' }
                        : null
                }" />

                <!-- Stav skladu -->
                <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-700">Stav skladu</h2>
                        <p class="mt-0.5 text-xs text-gray-400">Kliknutím na produkt zobrazíte jeho pohyby</p>
                    </div>

                    <div v-if="!getSummary.length" class="px-5 py-10 text-center text-sm text-gray-400">
                        Žiadne záznamy
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Produkt</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Prijaté</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Expedované</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Na sklade</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="item in getSummary"
                                    :key="item.product_id"
                                    class="cursor-pointer transition hover:bg-blue-50"
                                    :class="getSelectedProductId === item.product_id ? 'bg-blue-50 ring-1 ring-inset ring-blue-300' : ''"
                                    @click="onClickProduct(item.product_id)"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ item.name }}</div>
                                        <div class="text-xs text-gray-400">{{ item.code }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-600">
                                        {{ item.total_in }} <span class="text-xs text-gray-400">{{ item.unit_value }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-600">
                                        {{ item.total_out }} <span class="text-xs text-gray-400">{{ item.unit_value }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span :class="balanceClass(item.balance)">
                                            {{ item.balance }} <span class="text-xs font-normal">{{ item.unit_value }}</span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pohyby -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-3 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-700">
                                Pohyby
                                <span v-if="getSelectedProduct" class="ml-1 font-normal text-blue-600">— {{ getSelectedProduct.name }}</span>
                            </h2>
                        </div>
                        <button
                            v-if="getSelectedProductId"
                            type="button"
                            class="text-xs text-gray-400 hover:text-gray-700"
                            @click="onClickProduct(getSelectedProductId)"
                        >
                            × Zrušiť filter
                        </button>
                    </div>

                    <!-- Filter -->
                    <div class="border-b border-gray-100 px-5 py-3">
                        <div class="filter-field max-w-sm">
                            <label class="filter-label" for="stock-search">Hľadanie</label>
                            <div class="filter-control">
                                <input
                                    id="stock-search"
                                    v-model="searchInput"
                                    type="text"
                                    class="filter-input"
                                    placeholder="Názov alebo kód produktu"
                                    @input="onSearch($event.target.value)"
                                />
                                <button v-if="searchInput" type="button" class="filter-clear" @click="onSearch('')">×</button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="thead">
                                <tr>
                                    <th class="thead_th">Typ</th>
                                    <th class="thead_th">Produkt</th>
                                    <th class="thead_th">Odberateľ / Poznámka</th>
                                    <th class="thead_th">Čas</th>
                                    <th class="thead_th text-right">Množstvo</th>
                                    <th class="thead_th"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <spinnerTable v-if="loadingStore.isLoading" />
                                <tableRow v-else v-for="item in getStocks" :key="item.id" :item="item" />
                                <tr v-if="!loadingStore.isLoading && !getStocks.length">
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400">Žiadne pohyby</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3">
                        <pagination-component @setUrl="paginatorUrl" />
                    </div>
                </div>

            </div>
        </template>
    </BaseLayout>
</template>

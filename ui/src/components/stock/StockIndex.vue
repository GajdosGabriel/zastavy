<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { useStocks } from "../../store/StoreStocks";
import { useUsers as useUser } from "../../store/StoreUsers";
import useQuery from "../../store/StoreQuery";
import PaginationComponent from "../plugins/pagination.vue";
import FilterSearch from "../plugins/filterSearch.vue";
import FilterLabel from "../plugins/filterLabel.vue";
import tableRow from "./component/tableRow.vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import spinnerTable from "../icons/spinnerTable.vue";
import loadingStore from "../../store/StoreLoading";
import { PAGE_STOCK } from "../../constants";

const store = useStocks();
const { getStocks, getSummary, getSummaryMeta } = storeToRefs(store);
const { fetchStocks, fetchSummary, setPaginator, resetUrl } = store;
const { getUserCan } = storeToRefs(useUser());
const { setQuery, removeQuery, resetQuery } = useQuery();
const router = useRouter();

const searchInput = ref("");
const onlyProblems = ref(false);

// Typ pohybu — filtre sa navzájom vylučujú, klik na aktívny ho zruší.
const typeLabels = reactive([
    { name: 'Príjem', key: 'byType=', value: 'incoming', active: false },
    { name: 'Odpis', key: 'byType=', value: 'writeoff', active: false },
    { name: 'Expedícia', key: 'byType=', value: 'outgoing', active: false },
]);

const reload = () => {
    // Filtre vždy vracajú zoznam na prvú stránku — inak by paginátor ukazoval prázdno.
    store.url = PAGE_STOCK.URL;
    fetchStocks();
};

const applySearch = (term) => {
    term
        ? setQuery({ key: 'bySearchInput=', value: term })
        : removeQuery({ key: 'bySearchInput=' });

    reload();
};

const onClickType = (label) => {
    const wasActive = label.active;
    typeLabels.forEach(item => (item.active = false));
    label.active = !wasActive;

    label.active
        ? setQuery({ key: label.key, value: label.value })
        : removeQuery({ key: label.key });

    reload();
};

const onClearFilters = () => {
    searchInput.value = "";
    typeLabels.forEach(item => (item.active = false));
    resetQuery();
    reload();
};

const hasFilters = computed(() =>
    !!searchInput.value || typeLabels.some(item => item.active)
);

onMounted(() => {
    // Návrat z detailu položky — zoznam sa vracia na všetky pohyby.
    resetUrl();
    resetQuery();
    fetchSummary();
    fetchStocks();
});

const paginatorUrl = (url) => setPaginator(url);

const onClickVariant = (variantId) =>
    router.push({ name: 'stocks.show', params: { variantId } });

const balanceClass = (balance) => {
    if (balance > 10) return 'text-green-700 font-bold';
    if (balance > 0)  return 'text-amber-600 font-bold';
    return 'text-red-600 font-bold';
};

// Rozdiel medzi stavom z pohybov a tým, čo o sklade vie e-shop.
const mismatch = (item) =>
    item.tracked_quantity !== null && item.tracked_quantity !== item.balance;

const visibleSummary = computed(() =>
    onlyProblems.value
        ? getSummary.value.filter(item => item.balance <= 0 || mismatch(item))
        : getSummary.value
);

const money = (value) => Number(value ?? 0).toFixed(2);
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{
                    title: 'Sklad',
                    buttonLink: getUserCan?.['stocks.create']
                        ? { name: 'Príjem / odpis', spinner: true, link: '/sklad/create', icon: 'plus' }
                        : null
                }" />

                <!-- Prehľadové čísla -->
                <div v-if="getSummaryMeta" class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Skladové položky</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ getSummaryMeta.variants }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">V mínuse</div>
                        <div class="mt-1 text-2xl font-bold" :class="getSummaryMeta.below_zero ? 'text-red-600' : 'text-green-700'">
                            {{ getSummaryMeta.below_zero }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Hodnota skladu</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ money(getSummaryMeta.total_value) }} €</div>
                        <div class="mt-0.5 text-xs text-gray-400">z nákupných cien príjmov</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Odpísané</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900">{{ getSummaryMeta.total_writeoff }}</div>
                    </div>
                </div>

                <!-- Stav skladu -->
                <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50 px-5 py-3">
                        <div>
                            <h2 class="text-sm font-semibold text-gray-700">Stav skladu</h2>
                            <p class="mt-0.5 text-xs text-gray-400">
                                Nedostatkové položky sú hore. Kliknutím na položku otvoríte jej pohyby.
                            </p>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-gray-600">
                            <input v-model="onlyProblems" type="checkbox" class="rounded border-gray-300" />
                            Len problémové
                        </label>
                    </div>

                    <div v-if="!visibleSummary.length" class="px-5 py-10 text-center text-sm text-gray-400">
                        Žiadne záznamy
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Produkt</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Prijaté</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Expedované</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Odpísané</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Na sklade</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">V e-shope</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Hodnota</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="item in visibleSummary"
                                    :key="item.product_variant_id"
                                    class="cursor-pointer transition hover:bg-blue-50"
                                    @click="onClickVariant(item.product_variant_id)"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ item.name }}</div>
                                        <div v-if="item.variant_name" class="text-xs font-medium text-blue-700">
                                            {{ item.variant_name }}
                                        </div>
                                        <div class="text-xs text-gray-400">{{ item.code }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-600">
                                        {{ item.total_in }} <span class="text-xs text-gray-400">{{ item.unit_value }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm text-gray-600">
                                        {{ item.total_out }} <span class="text-xs text-gray-400">{{ item.unit_value }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm" :class="item.total_writeoff ? 'text-red-600' : 'text-gray-300'">
                                        {{ item.total_writeoff || '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span :class="balanceClass(item.balance)">
                                            {{ item.balance }} <span class="text-xs font-normal">{{ item.unit_value }}</span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-sm">
                                        <span v-if="item.tracked_quantity === null" class="text-xs text-gray-400">
                                            nesleduje sa
                                        </span>
                                        <span
                                            v-else
                                            :class="mismatch(item) ? 'font-bold text-amber-600' : 'text-gray-600'"
                                            :title="mismatch(item) ? 'Nesúhlasí so stavom z pohybov' : ''"
                                        >
                                            {{ item.tracked_quantity }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <span v-if="item.avg_price !== null" class="text-gray-700">
                                            {{ money(item.stock_value) }} €
                                        </span>
                                        <span v-else class="text-gray-300">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pohyby -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-700">Pohyby</h2>
                    </div>

                    <!-- Filter -->
                    <div class="space-y-3 border-b border-gray-100 px-5 py-3">
                        <div class="max-w-sm">
                            <FilterSearch
                                v-model="searchInput"
                                history-key="stockSearchHistory"
                                placeholder="Názov, kód produktu alebo variantu"
                                @search="applySearch"
                            />
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <FilterLabel
                                v-for="label in typeLabels"
                                :key="label.value"
                                :label="label"
                                @labelemit="onClickType"
                            />
                            <FilterLabel
                                v-if="hasFilters"
                                :label="{ name: 'Zrušiť filtre', key: 'resetFilter' }"
                                @labelemit="onClearFilters"
                            />
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
                                    <th class="thead_th text-right">Cena</th>
                                    <th class="thead_th"></th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                <spinnerTable v-if="loadingStore.isLoading" />
                                <tableRow v-else v-for="item in getStocks" :key="item.id" :item="item" />
                                <tr v-if="!loadingStore.isLoading && !getStocks.length">
                                    <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-400">Žiadne pohyby</td>
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

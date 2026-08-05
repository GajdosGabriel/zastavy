<script setup>
import { computed, onMounted, watch } from "vue";
import { useRoute } from "vue-router";
import { storeToRefs } from "pinia";
import { useStocks } from "../../store/StoreStocks";
import useQuery from "../../store/StoreQuery";
import PaginationComponent from "../plugins/pagination.vue";
import tableRow from "./component/tableRow.vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import spinnerTable from "../icons/spinnerTable.vue";
import loadingStore from "../../store/StoreLoading";

const route = useRoute();
const store = useStocks();
const { getStocks, getVariantSummary } = storeToRefs(store);
const { fetchStocks, fetchVariantSummary, selectVariant, setPaginator } = store;
const { resetQuery } = useQuery();

const buttonBack = { name: "Späť na sklad", link: "stocks.index", icon: "arrow-left" };

const variantId = computed(() => Number(route.params.variantId));

const title = computed(() => {
    const summary = getVariantSummary.value;
    if (!summary) return "Pohyby skladu";
    return summary.variant_name ? `${summary.name} — ${summary.variant_name}` : summary.name;
});

const balanceClass = computed(() => {
    const balance = getVariantSummary.value?.balance ?? 0;
    if (balance > 10) return "text-green-700";
    if (balance > 0) return "text-amber-600";
    return "text-red-600";
});

const load = (id) => {
    selectVariant(id);
    fetchVariantSummary(id);
    fetchStocks();
};

onMounted(() => {
    // Vyhľadávanie z prehľadu skladu sa nesmie preniesť do detailu položky.
    resetQuery();
    load(variantId.value);
});

watch(variantId, (id) => load(id));

const paginatorUrl = (url) => setPaginator(url);
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Pohyby skladu', buttonLink: buttonBack }" />

                <!-- Hlavička položky -->
                <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                        <h2 class="text-base font-semibold text-gray-800">{{ title }}</h2>
                        <p v-if="getVariantSummary?.code" class="mt-0.5 text-xs text-gray-400">
                            Kód: {{ getVariantSummary.code }}
                        </p>
                    </div>

                    <div v-if="getVariantSummary" class="grid gap-px bg-gray-100 sm:grid-cols-3">
                        <div class="bg-white px-5 py-4">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Prijaté</div>
                            <div class="mt-1 text-2xl font-bold text-green-700">
                                {{ getVariantSummary.total_in }}
                                <span class="text-sm font-normal text-gray-400">{{ getVariantSummary.unit_value }}</span>
                            </div>
                        </div>
                        <div class="bg-white px-5 py-4">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Expedované</div>
                            <div class="mt-1 text-2xl font-bold text-blue-700">
                                {{ getVariantSummary.total_out }}
                                <span class="text-sm font-normal text-gray-400">{{ getVariantSummary.unit_value }}</span>
                            </div>
                        </div>
                        <div class="bg-white px-5 py-4">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Na sklade</div>
                            <div class="mt-1 text-2xl font-bold" :class="balanceClass">
                                {{ getVariantSummary.balance }}
                                <span class="text-sm font-normal text-gray-400">{{ getVariantSummary.unit_value }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pohyby -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                        <h2 class="text-sm font-semibold text-gray-700">Pohyby položky</h2>
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
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400">
                                        Položka zatiaľ nemá žiadne pohyby
                                    </td>
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

<script setup>
import { computed } from "vue";
import useOrders from "../../store/StoreOrders";

const { getOrderStatistics } = useOrders();

const orders = computed(() => getOrderStatistics.value.orders || {});
const products = computed(() => getOrderStatistics.value.products || []);
const undeliveredProducts = computed(() => getOrderStatistics.value.undelivered_products || []);

const number = (value) => Number(value || 0).toLocaleString("sk-SK");
const price = (value) => `${Number(value || 0).toLocaleString("sk-SK", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})} €`;

const summaryItems = computed(() => [
    { label: "Objednávky", value: orders.value.order_count },
    { label: "Vybavené", value: orders.value.finished_count },
    { label: "Nevybavené", value: orders.value.unfinished_count },
    { label: "Neprečítané", value: orders.value.unopened_count },
    { label: "Objednané ks", value: orders.value.ordered_quantity },
    { label: "Nedodané ks", value: orders.value.remaining_quantity },
]);
</script>

<template>
    <section class="mb-5 space-y-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div v-for="item in summaryItems" :key="item.label"
                class="rounded-md border border-slate-200 bg-white p-3 shadow-sm">
                <div class="text-xs font-semibold uppercase text-slate-500">{{ item.label }}</div>
                <div class="mt-1 text-2xl font-bold text-slate-900">{{ number(item.value) }}</div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-bold text-slate-800">
                    Objednaný tovar
                </div>
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-600">Tovar</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-600">Obj.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-600">Dod.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-600">Chýba</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-600">Spolu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="product in products" :key="product.product_id || product.name">
                            <td class="px-4 py-2 text-sm font-semibold text-slate-800">{{ product.name }}</td>
                            <td class="px-4 py-2 text-right text-sm text-slate-600">
                                {{ number(product.ordered_quantity) }} {{ product.unit_value }}
                            </td>
                            <td class="px-4 py-2 text-right text-sm text-slate-600">
                                {{ number(product.shipped_quantity) }} {{ product.unit_value }}
                            </td>
                            <td class="px-4 py-2 text-right text-sm font-bold text-red-700">
                                {{ number(product.remaining_quantity) }} {{ product.unit_value }}
                            </td>
                            <td class="px-4 py-2 text-right text-sm font-semibold text-slate-800">
                                {{ price(product.total) }}
                            </td>
                        </tr>
                        <tr v-if="!products.length">
                            <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">
                                Bez položiek
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto rounded-md border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3 text-sm font-bold text-slate-800">
                    Tovar, ktorý nie je dodaný
                </div>
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-100">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-600">Tovar</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-600">Obj.</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-600">Chýba</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold uppercase text-slate-600">Objednávky</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="product in undeliveredProducts" :key="product.product_id || product.name">
                            <td class="px-4 py-2 text-sm font-semibold text-slate-800">{{ product.name }}</td>
                            <td class="px-4 py-2 text-right text-sm text-slate-600">
                                {{ number(product.required_quantity) }} {{ product.unit_value }}
                            </td>
                            <td class="px-4 py-2 text-right text-sm font-bold text-red-700">
                                {{ number(product.remaining_quantity) }} {{ product.unit_value }}
                            </td>
                            <td class="px-4 py-2 text-right text-sm text-slate-600">
                                {{ number(product.order_count) }}
                            </td>
                        </tr>
                        <tr v-if="!undeliveredProducts.length">
                            <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">
                                Všetko je dodané
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>

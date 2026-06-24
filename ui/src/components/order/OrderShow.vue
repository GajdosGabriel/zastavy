<script setup>
import BaseLayout from "../layout/BaseLayout.vue";
import useOrders from "../../store/StoreOrders";
import useOrderProducts from "../../store/StoreOrderProducts";
import useProducts from "../../store/StoreProducts";
import useReturns from "../../store/StoreReturns";
import { useRoute, useRouter } from "vue-router";
import { computed, onMounted } from "vue";
import productTableRow from "../orderProducts/productTableRow.vue";
import { formatDecimal } from "../../models/functions";
import shippingButton from "./component/shippingButton.vue";
import OrderCustomerCard from "./component/OrderCustomerCard.vue";
import buttonSubmitComponent from '../layout/page/ButtonSubmit.vue';
import buttonLink from '../layout/page/ButtonLink.vue';


const {
    getOrder,
    fetchOrder,
    customer,
} = useOrders();

const {
    getOrderProducts,
    getStatement,
    addOrderProduct,
} = useOrderProducts();

const { fetchProducts } = useProducts();
const { fetchReturns, getReturns } = useReturns();

const router = useRouter();
const { params: { orderId } } = useRoute();

onMounted(() => {
    fetchOrder(orderId);
    fetchProducts();
    fetchReturns(orderId);
});

const STATUS_STYLES = {
    pending:   'bg-amber-100 text-amber-700',
    processed: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
};
const STATUS_LABELS = {
    pending:   'Čaká',
    processed: 'Spracované',
    cancelled: 'Zrušené',
};

const hasShippedItems = computed(() =>
    (getOrder.value?.orderProducts ?? []).some(item => Number(item.stockSum ?? 0) > 0)
);

const buttonSubmit = { name: 'Uložiť', spinner: false }
const buttonBack = { name: 'Späť', spinner: true, link: 'orders.index', icon: 'arrow-left' }
const buttonHeader = { name: 'Upraviť', spinner: true, link: '/objednavky/'+ orderId +'/edit' }
</script>

<template>

    <BaseLayout>

        <template #main>

            <h1 class="page-heading">Zobrazenie objednávky
                <buttonLink :item="buttonHeader" class="text-sm" />
            </h1>


            <div class="page-body col-span-12">

                <div class="mb-4">
                    <OrderCustomerCard :customer="customer" :user="getOrder.user" :order="getOrder" />
                </div>
                <div class="flex flex-col">
                    <div class="-my-2 sm:-mx-6 lg:-mx-8">
                        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                            <div class="shadow border-b border-gray-200 sm:rounded-lg">
                                <table class="min-w-full divide-y border-2 border-gray-500">
                                    <thead class="thead">
                                        <tr>
                                            <th class="thead_th">Name</th>
                                            <th class="thead_th">Množstvo</th>
                                            <th class="thead_th">Cena</th>
                                            <th class="thead_th">DPH</th>
                                            <th class="thead_th">s DPH</th>
                                            <th class="thead_th">Exped.</th>
                                            <th class="thead_th">Storno</th>
                                            <th class="thead_th">Panel</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <product-table-row v-for="item in getOrderProducts" :key="item.id"
                                            :item="item"></product-table-row>
                                    </tbody>

                                    <tfoot class="bg-gray-200 divide-gray-200">
                                        <tr>
                                            <td class="tbody_td font-semibold">
                                                Rekapitulácia objednávky:
                                            </td>
                                            <td class="tbody_td" colspan="3">
                                                Položiek: {{ getStatement.grandQuantity }}
                                            </td>
                                            <td class="tbody_td font-semibold">
                                                Cena:
                                                {{
                                                    formatDecimal(getStatement.grandTotal) +
                                                    " €"
                                                }}
                                            </td>
                                            <td colspan="2" class="tbody_td font-semibold text-3xl">
                                                <shipping-button :order="getOrder" />
                                            </td>
                                            <td colspan="1" class="tbody_td font-semibold" @click="addOrderProduct(orderId)">
                                                <span class="text-3xl cursor-pointer">+</span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vrátenia -->
                <div class="mt-4 rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-2.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                            Vrátenia tovaru
                            <span v-if="getReturns.length" class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-gray-600">
                                {{ getReturns.length }}
                            </span>
                        </span>
                        <button v-if="hasShippedItems"
                            @click="router.push({ name: 'orders.returns.create', params: { orderId } })"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                            + Vrátiť tovar
                        </button>
                    </div>

                    <div v-if="!getReturns.length" class="px-5 py-5 text-center text-sm text-gray-400">
                        Žiadne vrátenia
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">#</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Dôvod</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Položiek</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Dátum</th>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Status</th>
                                <th class="px-5 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="ret in getReturns" :key="ret.id" class="hover:bg-gray-50">
                                <td class="px-5 py-3 text-sm text-gray-500">{{ ret.id }}</td>
                                <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ ret.reason_label }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600">{{ ret.items?.length ?? 0 }}</td>
                                <td class="px-5 py-3 text-sm text-gray-500">{{ ret.created_at }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="STATUS_STYLES[ret.status] ?? 'bg-gray-100 text-gray-500'">
                                        {{ STATUS_LABELS[ret.status] ?? ret.status }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button
                                        @click="router.push({ name: 'orders.returns.show', params: { orderId, returnId: ret.id } })"
                                        class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                                        Detail
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-between mt-5">
                    <buttonLink :item="buttonBack" />
                    <buttonSubmitComponent :item="buttonSubmit" />
                </div>
            </div>
        </template>
    </BaseLayout>
</template>

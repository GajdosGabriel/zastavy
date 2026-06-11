<script setup lang="ts">
import { onMounted, watch } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useCustomers from "../../store/StoreCustomers";
import useOrders from "../../store/StoreOrders";
import { useRoute } from "vue-router";
import tableRowOrders from "../order/component/tableRowOrders.vue";
import templateOrder from "../../models/templateOrder";
import buttonRouterLink from "../layout/page/ButtonLink.vue";

const route = useRoute();
const { fetchCustomer, getCustomer, fetchCustomerOrders } = useCustomers();
const { getOrders } = useOrders();

onMounted(() => { document.title = "Detail zákazníka"; });

watch(
    () => route.params.customerId,
    (customerId) => {
        fetchCustomer(customerId);
        fetchCustomerOrders(customerId);
    },
    { immediate: true }
);

const buttonBack = { name: 'Späť', link: '/zakaznici', icon: 'arrow-left' };
const buttonEdit = () => ({ name: 'Upraviť', link: `/zakaznici/${route.params.customerId}/edit`, icon: 'plus' });
const buttonNew  = { name: 'Nový', link: '/zakaznici/create', icon: 'plus' };
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">

                <!-- Header -->
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Zákazník</p>
                        <h1 class="text-xl font-bold text-gray-900">{{ getCustomer.company || getCustomer.name || '—' }}</h1>
                    </div>
                    <div class="flex gap-2">
                        <buttonRouterLink :item="buttonBack" class="text-sm" />
                        <buttonRouterLink v-if="getCustomer?.id" :item="buttonEdit()" class="text-sm" />
                        <buttonRouterLink :item="buttonNew" class="text-sm" />
                    </div>
                </div>

                <!-- Detail karta -->
                <div class="mb-4 rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="grid divide-y divide-gray-100 px-5 py-1 sm:grid-cols-3 sm:divide-x sm:divide-y-0">

                        <!-- Firma -->
                        <div class="py-3 sm:pr-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Firma</p>
                            <p class="text-sm font-semibold text-gray-900">{{ getCustomer.company || '—' }}</p>
                            <p class="text-sm text-gray-600">{{ getCustomer.street }}</p>
                            <p class="text-sm text-gray-600">{{ getCustomer.postcode }} {{ getCustomer.city }}</p>
                            <div class="mt-1.5 flex gap-3 text-xs text-gray-500">
                                <span v-if="getCustomer.ico">IČO: {{ getCustomer.ico }}</span>
                                <span v-if="getCustomer.dic">DIČ: {{ getCustomer.dic }}</span>
                            </div>
                        </div>

                        <!-- Kontakt -->
                        <div class="py-3 sm:px-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Kontakt</p>
                            <p class="text-sm font-semibold text-gray-900">{{ getCustomer.name || '—' }}</p>
                            <a v-if="getCustomer.phone" :href="`tel:${getCustomer.phone}`"
                               class="block text-sm text-blue-700 hover:underline">{{ getCustomer.phone }}</a>
                            <a v-if="getCustomer.email" :href="`mailto:${getCustomer.email}`"
                               class="block text-sm text-blue-700 hover:underline">{{ getCustomer.email }}</a>
                        </div>

                        <!-- Meta -->
                        <div class="py-3 sm:pl-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Info</p>
                            <div class="space-y-1 text-sm text-gray-600">
                                <div><span class="text-gray-400">Zaregistrovaný:</span> {{ getCustomer.created_at || '—' }}</div>
                                <div><span class="text-gray-400">Objednávky:</span> {{ getOrders?.length ?? 0 }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Objednávky -->
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-2.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Objednávky</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="thead">
                                <tr>
                                    <th class="thead_th">Id</th>
                                    <th class="thead_th"></th>
                                    <th class="thead_th">Názov</th>
                                    <th class="thead_th">Cena</th>
                                    <th class="thead_th">Status</th>
                                    <th class="thead_th">Expedícia</th>
                                    <th class="thead_th">Panel</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tableRowOrders v-for="order in templateOrder(getOrders)" :key="order.id" :order="order" />
                                <tr v-if="!getOrders?.length">
                                    <td colspan="7" class="px-6 py-6 text-center text-sm text-gray-400">Žiadne objednávky</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    <buttonRouterLink :item="buttonBack" />
                </div>

            </div>
        </template>
    </BaseLayout>
</template>

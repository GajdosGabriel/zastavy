<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axiosInstance from '../../axiosInstance';
import BaseLayout from '../layout/BaseLayout.vue';
import { formatDecimal } from '../../models/functions';

const route = useRoute();
const order = ref(null);
const loading = ref(true);
const notFound = ref(false);

onMounted(async () => {
    try {
        const response = await axiosInstance.get(`/public-orders/${route.params.uuid}`);
        order.value = response.data.data;
    } catch {
        notFound.value = true;
    } finally {
        loading.value = false;
    }
});

const hasPrice = (products) => products?.some(p => p.price);
</script>

<template>
    <BaseLayout>
        <template #main>
            <section class="col-span-12 px-4 pb-12">

                <!-- Loading -->
                <div v-if="loading" class="flex items-center justify-center py-24">
                    <svg class="h-8 w-8 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                    </svg>
                </div>

                <!-- Not found -->
                <div v-else-if="notFound" class="mx-auto max-w-xl rounded-md border border-red-200 bg-red-50 p-8 text-center">
                    <p class="text-lg font-semibold text-red-700">Objednávka sa nenašla</p>
                    <p class="mt-2 text-sm text-gray-600">Odkaz mohol expirovat alebo je nesprávny.</p>
                    <router-link :to="{ name: 'public.index' }" class="mt-4 inline-block text-sm text-blue-700 hover:underline">Späť na úvodnú stránku</router-link>
                </div>

                <!-- Order detail -->
                <div v-else class="mx-auto max-w-3xl space-y-6">

                    <!-- Header -->
                    <div class="rounded-md border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 bg-gray-50 px-6 py-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h1 class="text-xl font-semibold text-gray-900">Detail objednávky</h1>
                                    <p v-if="order.serial_number" class="mt-1 text-sm text-gray-500">
                                        Číslo objednávky: <span class="font-medium text-gray-700">{{ order.serial_number }}</span>
                                    </p>
                                </div>
                                <span class="text-sm text-gray-400">
                                    {{ new Date(order.created_at).toLocaleDateString('sk-SK') }}
                                </span>
                            </div>
                        </div>

                        <div class="grid gap-6 px-6 py-5 sm:grid-cols-2">
                            <!-- Customer info -->
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Zákazník</p>
                                <p class="font-semibold text-gray-900">{{ order.customer.company || order.customer.name }}</p>
                                <p v-if="order.customer.ico" class="text-sm text-gray-500">IČO: {{ order.customer.ico }}</p>
                                <p v-if="order.customer.dic" class="text-sm text-gray-500">DIČ: {{ order.customer.dic }}</p>
                                <p v-if="order.customer.ic_dic" class="text-sm text-gray-500">IČ DPH: {{ order.customer.ic_dic }}</p>
                                <p v-if="order.customer.email" class="mt-1 text-sm text-gray-700">{{ order.customer.email }}</p>
                                <p v-if="order.customer.phone" class="text-sm text-gray-700">{{ order.customer.phone }}</p>
                            </div>

                            <!-- Address -->
                            <div v-if="order.customer.street || order.customer.city">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Doručovacia adresa</p>
                                <p v-if="order.customer.street" class="text-sm text-gray-700">{{ order.customer.street }}</p>
                                <p class="text-sm text-gray-700">{{ order.customer.postcode }} {{ order.customer.city }}</p>
                            </div>
                        </div>

                        <!-- Shipping + payment -->
                        <div v-if="order.shipping_method || order.payment_method" class="border-t border-gray-100 px-6 py-4">
                            <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
                                <span v-if="order.shipping_method">
                                    Doprava: <span class="font-medium text-gray-800">{{ order.shipping_method.name }}</span>
                                </span>
                                <span v-if="order.payment_method">
                                    Platba: <span class="font-medium text-gray-800">{{ order.payment_method.name }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Products -->
                    <div class="rounded-md border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 bg-gray-50 px-6 py-3">
                            <h2 class="text-sm font-semibold text-gray-700">Objednané položky</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-400">Tovar</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-400">Množstvo</th>
                                        <th v-if="hasPrice(order.order_products)" class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-400">Cena/ks</th>
                                        <th v-if="hasPrice(order.order_products)" class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-400">Spolu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 bg-white">
                                    <tr v-for="(item, i) in order.order_products" :key="i">
                                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ item.name }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">{{ item.quantity }} ks</td>
                                        <td v-if="hasPrice(order.order_products)" class="px-4 py-3 text-right text-sm text-gray-700">
                                            {{ item.price ? formatDecimal(item.price) + ' €' : '—' }}
                                        </td>
                                        <td v-if="hasPrice(order.order_products)" class="px-6 py-3 text-right text-sm font-semibold text-gray-900">
                                            {{ item.total ? formatDecimal(item.total) + ' €' : '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="hasPrice(order.order_products)" class="border-t border-gray-200 bg-gray-50">
                                    <tr v-if="order.shipping_price > 0">
                                        <td colspan="3" class="px-6 py-2 text-right text-sm text-gray-500">
                                            Poštovné{{ order.shipping_method ? ' (' + order.shipping_method.name + ')' : '' }}
                                        </td>
                                        <td class="px-6 py-2 text-right text-sm text-gray-600">{{ formatDecimal(order.shipping_price) }} €</td>
                                    </tr>
                                    <tr v-if="order.payment_fee > 0">
                                        <td colspan="3" class="px-6 py-2 text-right text-sm text-gray-500">
                                            {{ order.payment_method?.name ?? 'Poplatok za platbu' }}
                                        </td>
                                        <td class="px-6 py-2 text-right text-sm text-gray-600">{{ formatDecimal(order.payment_fee) }} €</td>
                                    </tr>
                                    <tr v-if="order.discount_amount > 0">
                                        <td colspan="3" class="px-6 py-2 text-right text-sm text-green-600">Zľava</td>
                                        <td class="px-6 py-2 text-right text-sm font-semibold text-green-600">−{{ formatDecimal(order.discount_amount) }} €</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-6 py-3 text-right text-base font-semibold text-gray-900">Celková suma</td>
                                        <td class="px-6 py-3 text-right text-base font-semibold text-gray-900">{{ formatDecimal(order.grand_total) }} €</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Note -->
                    <div v-if="order.note" class="rounded-md border border-yellow-100 bg-yellow-50 px-6 py-4">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-yellow-700">Poznámka</p>
                        <p class="text-sm text-gray-700">{{ order.note }}</p>
                    </div>

                    <!-- Back -->
                    <div class="text-center">
                        <router-link :to="{ name: 'public.index' }" class="text-sm text-blue-700 hover:underline">
                            ← Späť na úvodnú stránku
                        </router-link>
                    </div>

                </div>
            </section>
        </template>
    </BaseLayout>
</template>

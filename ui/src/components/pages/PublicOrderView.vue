<script setup>
import ReorderButton from '../sales/ReorderButton.vue';
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axiosInstance from '../../axiosInstance';
import BaseLayout from '../layout/BaseLayout.vue';
import { formatPrice, formatFileSize } from '../../models/functions';
import PaymentInstructions from './order/PaymentInstructions.vue';
import OrderStatusSteps from './order/OrderStatusSteps.vue';
import buttonLink from '../layout/page/ButtonLink.vue';

const route = useRoute();
const order = ref(null);
const loading = ref(true);
const notFound = ref(false);

// Token z odkazu v e-maile. Bez neho sa detail dá čítať, ale adresa meniť nie —
// preposlaný odkaz na objednávku nesmie dávať právo prepísať, kam sa pošle.
const token = computed(() => route.query.token ?? null);

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

// Pri súkromnej osobe je „firma“ v adrese doručenia jej meno — nevypisujeme ho dvakrát.
const showDeliveryCompany = computed(() => {
    const delivery = order.value?.delivery;
    return Boolean(delivery?.company) && delivery.company.trim() !== (delivery.name ?? '').trim();
});
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
                    <p class="mt-2 text-sm text-gray-600">Odkaz mohol expirovať alebo je nesprávny.</p>
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
                                <span class="text-sm text-gray-500">
                                    {{ new Date(order.created_at).toLocaleDateString('sk-SK') }}
                                </span>
                            </div>
                            <OrderStatusSteps :status="order.status" class="mt-4" />
                        </div>

                        <div class="grid gap-6 px-6 py-5 sm:grid-cols-2">
                            <!-- Customer info -->
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Zákazník</p>
                                <p class="font-semibold text-gray-900">{{ order.customer.company || order.customer.name }}</p>
                                <p v-if="order.customer.ico" class="text-sm text-gray-500">IČO: {{ order.customer.ico }}</p>
                                <p v-if="order.customer.dic" class="text-sm text-gray-500">DIČ: {{ order.customer.dic }}</p>
                                <p v-if="order.customer.ic_dic" class="text-sm text-gray-500">IČ DPH: {{ order.customer.ic_dic }}</p>
                                <p v-if="order.customer.email" class="mt-1 text-sm text-gray-700">{{ order.customer.email }}</p>
                                <p v-if="order.customer.phone" class="text-sm text-gray-700">{{ order.customer.phone }}</p>
                            </div>

                            <!-- Adresa doručenia -->
                            <div v-if="order.delivery">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Adresa doručenia</p>
                                <p v-if="showDeliveryCompany" class="text-sm font-semibold text-gray-900">{{ order.delivery.company }}</p>
                                <p v-if="order.delivery.name" class="text-sm" :class="showDeliveryCompany ? 'text-gray-700' : 'font-semibold text-gray-900'">{{ order.delivery.name }}</p>
                                <p v-if="order.delivery.street" class="text-sm text-gray-700">{{ order.delivery.street }}</p>
                                <p class="text-sm text-gray-700">{{ order.delivery.postcode }} {{ order.delivery.city }}</p>
                                <p v-if="order.delivery.phone" class="text-sm text-gray-500">Tel.: {{ order.delivery.phone }}</p>
                                <p v-if="order.delivery.note" class="text-sm text-gray-500">{{ order.delivery.note }}</p>

                                <!-- Fakturačná adresa len keď sa líši — inak je to ten istý údaj dvakrát. -->
                                <template v-if="order.delivery.is_custom && (order.customer.street || order.customer.city)">
                                    <p class="mb-1 mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Fakturačná adresa</p>
                                    <p class="text-sm text-gray-500">
                                        {{ [order.customer.street, [order.customer.postcode, order.customer.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') }}
                                    </p>
                                </template>

                                <router-link
                                    v-if="order.can_edit_delivery && token"
                                    :to="{ name: 'public.order.deliveryAddress', params: { uuid: order.uuid }, query: { token } }"
                                    class="mt-3 inline-block text-sm text-blue-700 hover:underline"
                                >
                                    Zmeniť adresu doručenia
                                </router-link>
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

                    <PaymentInstructions :payment="order.payment_instructions" />

                    <!-- Products -->
                    <div class="rounded-md border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 bg-gray-50 px-6 py-3">
                            <h2 class="text-sm font-semibold text-gray-700">Objednané položky</h2>
                        </div>
                        <!-- Mobil: položky ako riadky, tabuľka by sa nezmestila -->
                        <ul class="divide-y divide-gray-100 sm:hidden">
                            <li v-for="(item, i) in order.order_products" :key="'m' + i" class="px-4 py-3">
                                <p class="text-sm font-medium text-gray-900">{{ item.name }}</p>
                                <div class="mt-0.5 flex justify-between gap-3 text-sm">
                                    <span class="text-gray-600">{{ item.quantity }} ks<template v-if="item.price"> × {{ formatPrice(item.price) }} €</template></span>
                                    <span v-if="item.total" class="font-semibold text-gray-900">{{ formatPrice(item.total) }} €</span>
                                </div>
                            </li>
                        </ul>
                        <dl v-if="hasPrice(order.order_products)" class="space-y-1 border-t border-gray-200 bg-gray-50 px-4 py-3 text-sm sm:hidden">
                            <div v-if="order.shipping_price > 0" class="flex justify-between text-gray-600"><dt>Poštovné{{ order.shipping_method ? ' (' + order.shipping_method.name + ')' : '' }}</dt><dd>{{ formatPrice(order.shipping_price) }} €</dd></div>
                            <div v-if="order.payment_fee > 0" class="flex justify-between text-gray-600"><dt>{{ order.payment_method?.name ?? 'Poplatok za platbu' }}</dt><dd>{{ formatPrice(order.payment_fee) }} €</dd></div>
                            <div v-if="order.adjustment_amount" class="flex justify-between text-gray-600"><dt>{{ order.price_adjustment?.label || (order.adjustment_amount < 0 ? 'Zľava' : 'Prirážka') }}</dt><dd>{{ formatPrice(order.adjustment_amount) }} €</dd></div>
                            <div v-if="order.discount_amount > 0" class="flex justify-between text-green-700"><dt>Zľava</dt><dd>−{{ formatPrice(order.discount_amount) }} €</dd></div>
                            <div class="flex justify-between pt-1 text-base font-semibold text-gray-900"><dt>Celková suma</dt><dd>{{ formatPrice(order.grand_total) }} €</dd></div>
                        </dl>

                        <div class="hidden overflow-x-auto sm:block">
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-500">Tovar</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Množstvo</th>
                                        <th v-if="hasPrice(order.order_products)" class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Cena/ks</th>
                                        <th v-if="hasPrice(order.order_products)" class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-500">Spolu</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 bg-white">
                                    <tr v-for="(item, i) in order.order_products" :key="i">
                                        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ item.name }}</td>
                                        <td class="px-4 py-3 text-right text-sm text-gray-700">{{ item.quantity }} ks</td>
                                        <td v-if="hasPrice(order.order_products)" class="px-4 py-3 text-right text-sm text-gray-700">
                                            {{ item.price ? formatPrice(item.price) + ' €' : '—' }}
                                        </td>
                                        <td v-if="hasPrice(order.order_products)" class="px-6 py-3 text-right text-sm font-semibold text-gray-900">
                                            {{ item.total ? formatPrice(item.total) + ' €' : '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot v-if="hasPrice(order.order_products)" class="border-t border-gray-200 bg-gray-50">
                                    <tr v-if="order.shipping_price > 0">
                                        <td colspan="3" class="px-6 py-2 text-right text-sm text-gray-500">
                                            Poštovné{{ order.shipping_method ? ' (' + order.shipping_method.name + ')' : '' }}
                                        </td>
                                        <td class="px-6 py-2 text-right text-sm text-gray-600">{{ formatPrice(order.shipping_price) }} €</td>
                                    </tr>
                                    <tr v-if="order.payment_fee > 0">
                                        <td colspan="3" class="px-6 py-2 text-right text-sm text-gray-500">
                                            {{ order.payment_method?.name ?? 'Poplatok za platbu' }}
                                        </td>
                                        <td class="px-6 py-2 text-right text-sm text-gray-600">{{ formatPrice(order.payment_fee) }} €</td>
                                    </tr>
                                    <tr v-if="order.adjustment_amount"><td class="px-6 py-2 text-sm" colspan="3">{{ order.price_adjustment?.label || (order.adjustment_amount < 0 ? 'Zľava' : 'Prirážka') }}</td><td class="px-6 py-2 text-right text-sm font-semibold">{{ formatPrice(order.adjustment_amount) }} €</td></tr>
                                    <tr v-if="order.discount_amount > 0">
                                        <td colspan="3" class="px-6 py-2 text-right text-sm text-green-600">Zľava</td>
                                        <td class="px-6 py-2 text-right text-sm font-semibold text-green-600">−{{ formatPrice(order.discount_amount) }} €</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-6 py-3 text-right text-base font-semibold text-gray-900">Celková suma</td>
                                        <td class="px-6 py-3 text-right text-base font-semibold text-gray-900">{{ formatPrice(order.grand_total) }} €</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <div v-if="order.attachments?.length" class="rounded-md border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 bg-gray-50 px-6 py-3">
                            <h2 class="text-sm font-semibold text-gray-700">Prílohy</h2>
                        </div>
                        <ul class="divide-y divide-gray-50">
                            <li v-for="file in order.attachments" :key="file.id" class="flex items-center justify-between gap-3 px-6 py-3">
                                <a :href="file.download" class="truncate text-sm font-medium text-blue-700 hover:underline">{{ file.name }}</a>
                                <span class="shrink-0 text-xs text-gray-500">{{ formatFileSize(file.size) }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Note -->
                    <div v-if="order.note" class="rounded-md border border-yellow-100 bg-yellow-50 px-6 py-4">
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-yellow-700">Poznámka</p>
                        <p class="text-sm text-gray-700">{{ order.note }}</p>
                    </div>

                    <!-- Kontakt + akcie -->
                    <p class="text-center text-sm text-gray-700">
                        Máte otázku k objednávke? Zavolajte na
                        <a href="tel:+421905320616" class="font-semibold text-blue-800 hover:underline">0905 320 616</a>
                        alebo napíšte na
                        <a href="mailto:obchod@zastavy-vlajky.sk" class="font-semibold text-blue-800 hover:underline">obchod@zastavy-vlajky.sk</a>.
                    </p>
                    <div class="flex flex-wrap items-start justify-center gap-2">
                        <buttonLink class="text-sm" :item="{ name: 'Späť na úvodnú stránku', link: { name: 'public.index' }, icon: 'arrow-left' }" />
                        <ReorderButton :endpoint="'/public-orders/'+order.uuid+'/reorder'" />
                    </div>

                </div>
            </section>
        </template>
    </BaseLayout>
</template>

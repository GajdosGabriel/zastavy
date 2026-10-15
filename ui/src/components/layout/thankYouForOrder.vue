<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import BaseLayout from './BaseLayout.vue';
import axiosInstance from '../../axiosInstance';
import { formatPrice } from '../../models/functions';
import PaymentInstructions from '../pages/order/PaymentInstructions.vue';

const route = useRoute();
const orderToken = route.query.token || null;
// Obsluha môže pri odoslaní e-mail zákazníkovi vypnúť (notified=0).
const emailSent = route.query.notified !== '0';

// Číslo, suma a platobné údaje — zákazník ich potrebuje hneď, nie až v e-maile.
const order = ref(null);

onMounted(async () => {
    if (!orderToken) return;
    try {
        order.value = (await axiosInstance.get(`/public-orders/${orderToken}`)).data.data;
    } catch {
        // Bez detailu stránka stále poďakuje — číslo nájde zákazník v e-maile.
    }
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <section class="col-span-12 px-4 pb-12">
                <div class="mx-auto max-w-3xl rounded-md border border-green-200 bg-white shadow-sm">
                    <div class="border-b border-green-100 bg-green-50 px-6 py-6 text-center">
                        <div
                            class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-green-700 text-white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20"
                                fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h1 class="text-2xl font-semibold text-gray-900">
                            Ďakujeme za objednávku
                        </h1>

                        <!-- Číslo a suma -->
                        <p v-if="order" class="mt-3 text-lg text-gray-900">
                            Objednávka č. <strong>{{ order.serial_number }}</strong>
                            <span class="mx-1 text-gray-500" aria-hidden="true">·</span>
                            <strong>{{ formatPrice(order.grand_total) }} €</strong>
                        </p>
                        <p v-else-if="orderToken" class="mx-auto mt-3 h-7 w-64 animate-pulse rounded bg-green-100" aria-hidden="true"></p>

                        <p class="mt-2 text-gray-700">
                            {{ emailSent
                                ? 'Objednávka bola úspešne odoslaná a jej rekapituláciu sme poslali na zadaný e-mail.'
                                : 'Objednávka bola úspešne odoslaná.' }}
                        </p>

                        <router-link v-if="orderToken"
                            :to="{ name: 'public.order.show', params: { uuid: orderToken } }"
                            class="mt-5 inline-flex items-center justify-center rounded-md bg-blue-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Zobraziť moju objednávku
                        </router-link>
                    </div>

                    <div class="space-y-6 px-6 py-6">
                        <PaymentInstructions :payment="order?.payment_instructions" />

                        <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <h2 class="mb-3 text-base font-semibold text-gray-900">Čo bude nasledovať</h2>
                            <ul class="space-y-2 text-sm text-gray-700">
                                <li class="flex gap-2">
                                    <span class="font-semibold text-green-700">1.</span>
                                    <span>Skontrolujeme dostupnosť položiek a pripravíme objednávku na vybavenie.</span>
                                </li>
                                <li class="flex gap-2">
                                    <span class="font-semibold text-green-700">2.</span>
                                    <span>Ak bude potrebné niečo doplniť, ozveme sa vám e-mailom alebo telefonicky.</span>
                                </li>
                                <li class="flex gap-2">
                                    <span class="font-semibold text-green-700">3.</span>
                                    <span>Po vybavení dostanete informáciu o ďalšom postupe alebo doručení.</span>
                                </li>
                            </ul>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div v-if="emailSent" class="rounded-md border border-gray-200 p-4">
                                <h2 class="mb-2 text-base font-semibold text-gray-900">E-mail s potvrdením</h2>
                                <p class="text-sm text-gray-700">
                                    Ak správu nevidíte, skontrolujte aj priečinok spam alebo hromadnú poštu.
                                </p>
                            </div>
                            <div class="rounded-md border border-gray-200 p-4" :class="{ 'md:col-span-2': !emailSent }">
                                <h2 class="mb-2 text-base font-semibold text-gray-900">Potrebujete niečo zmeniť?</h2>
                                <p class="text-sm text-gray-700">
                                    Zavolajte na <a href="tel:+421905320616" class="font-semibold text-blue-800 hover:underline">0905 320 616</a>
                                    alebo napíšte na <a href="mailto:obchod@zastavy-vlajky.sk" class="font-semibold text-blue-800 hover:underline">obchod@zastavy-vlajky.sk</a><template v-if="order">
                                    a uveďte číslo objednávky {{ order.serial_number }}</template>.
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-gray-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                                <router-link :to="{ name: 'public.obchodne.podmienky' }"
                                    class="inline-block py-1 text-blue-800 hover:underline">
                                    Obchodné podmienky
                                </router-link>
                                <router-link :to="{ name: 'public.ochranaOsobnychUdajov' }"
                                    class="inline-block py-1 text-blue-800 hover:underline">
                                    Ochrana osobných údajov
                                </router-link>
                                <router-link :to="{ name: 'public.contactUs' }" class="inline-block py-1 text-blue-800 hover:underline">
                                    Kontakt
                                </router-link>
                            </div>

                            <router-link :to="{ name: 'public.index' }"
                                class="inline-flex w-auto items-center justify-center rounded-md border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Prejsť na začiatok
                            </router-link>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </BaseLayout>
</template>

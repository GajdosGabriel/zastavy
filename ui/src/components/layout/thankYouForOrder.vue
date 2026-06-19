<script setup>
import { useRoute } from 'vue-router';
import BaseLayout from './BaseLayout.vue';

const route = useRoute();
const orderToken = route.query.token || null;
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
                        <p class="mt-2 text-gray-700">
                            Objednávka bola úspešne odoslaná a jej rekapituláciu sme poslali na zadaný e-mail.
                        </p>
                    </div>

                    <div class="space-y-6 px-6 py-6">
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
                            <div class="rounded-md border border-gray-200 p-4">
                                <h2 class="mb-2 text-base font-semibold text-gray-900">E-mail s potvrdením</h2>
                                <p class="text-sm text-gray-700">
                                    Ak správu nevidíte, skontrolujte aj priečinok spam alebo hromadnú poštu.
                                </p>
                            </div>
                            <div class="rounded-md border border-gray-200 p-4">
                                <h2 class="mb-2 text-base font-semibold text-gray-900">Potrebujete niečo zmeniť?</h2>
                                <p class="text-sm text-gray-700">
                                    Kontaktujte nás čo najskôr a uveďte údaje, pod ktorými bola objednávka odoslaná.
                                </p>
                            </div>
                        </div>

                        <div v-if="orderToken" class="rounded-md border border-blue-100 bg-blue-50 p-4 text-center">
                            <p class="mb-3 text-sm text-gray-700">Chcete si prezrieť detaily objednávky alebo ju zdieľať?</p>
                            <router-link
                                :to="{ name: 'public.order.show', params: { uuid: orderToken } }"
                                class="inline-flex items-center justify-center rounded-md bg-blue-700 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Zobraziť moju objednávku
                            </router-link>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-gray-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm">
                                <router-link :to="{ name: 'public.obchodne.podmienky' }"
                                    class="text-blue-800 hover:underline">
                                    Obchodné podmienky
                                </router-link>
                                <router-link :to="{ name: 'public.ochranaOsobnychUdajov' }"
                                    class="text-blue-800 hover:underline">
                                    Ochrana osobných údajov
                                </router-link>
                                <router-link :to="{ name: 'public.contactUs' }" class="text-blue-800 hover:underline">
                                    Kontakt
                                </router-link>
                            </div>

                            <router-link :to="{ name: 'public.index' }"
                                class="inline-flex w-auto items-center justify-center rounded-md bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Prejsť na začiatok
                            </router-link>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </BaseLayout>
</template>

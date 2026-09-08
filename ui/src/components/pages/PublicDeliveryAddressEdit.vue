<script setup>
/**
 * Verejná zmena adresy doručenia — stránka za odkazom z potvrdzovacieho e-mailu.
 *
 * Prístup stráži token z odkazu, nie prihlásenie. Neplatný token, expirovaný
 * odkaz aj neexistujúca objednávka vyzerajú rovnako — zámerne, aby sa uuid
 * objednávok nedalo hádať.
 */
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import axiosInstance from '../../axiosInstance';
import BaseLayout from '../layout/BaseLayout.vue';
import DeliveryAddressFields from '../forms/DeliveryAddressFields.vue';
import { emptyDeliveryAddress } from '../../store/StoreCheckouts';

const route = useRoute();

const loading = ref(true);
const saving = ref(false);
const notFound = ref(false);
const savedMessage = ref('');
const conflictMessage = ref('');
const fieldErrors = ref({});

const info = ref(null);
const address = ref(emptyDeliveryAddress());
const deliverToOtherAddress = ref(false);

const token = computed(() => route.query.token ?? '');
const endpoint = computed(() => `/public-orders/${route.params.uuid}/delivery-address`);

const fillFrom = (data) => {
    info.value = data;
    deliverToOtherAddress.value = Boolean(data.delivery?.is_custom);
    address.value = {
        ...emptyDeliveryAddress(),
        company: data.delivery?.is_custom ? (data.delivery.company ?? '') : '',
        name: data.delivery?.name ?? '',
        street: data.delivery?.is_custom ? (data.delivery.street ?? '') : '',
        postcode: data.delivery?.is_custom ? (data.delivery.postcode ?? '') : '',
        city: data.delivery?.is_custom ? (data.delivery.city ?? '') : '',
        phone: data.delivery?.is_custom ? (data.delivery.phone ?? '') : '',
        note: data.delivery?.note ?? '',
    };
};

onMounted(async () => {
    try {
        const response = await axiosInstance.get(endpoint.value, { params: { token: token.value } });
        fillFrom(response.data.data);
    } catch {
        notFound.value = true;
    } finally {
        loading.value = false;
    }
});

const submit = async () => {
    saving.value = true;
    savedMessage.value = '';
    conflictMessage.value = '';
    fieldErrors.value = {};

    try {
        const response = await axiosInstance.put(endpoint.value, {
            token: token.value,
            // Vypnutý prepínač znamená „doručiť na fakturačnú adresu" — server
            // prázdny `delivery` prečíta ako zmazanie odtlačku.
            delivery: deliverToOtherAddress.value ? address.value : null,
        });
        fillFrom(response.data.data);
        savedMessage.value = response.data.message || 'Adresu doručenia sme upravili.';
    } catch (error) {
        if (error.response?.status === 422) {
            fieldErrors.value = error.response.data.errors ?? {};
        } else if (error.response?.status === 409) {
            conflictMessage.value = error.response.data.message;
        } else {
            conflictMessage.value = 'Zmenu sa nepodarilo uložiť. Skúste to prosím znova.';
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <section class="col-span-12 px-4 pb-12">

                <div v-if="loading" class="flex items-center justify-center py-24">
                    <svg class="h-8 w-8 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                    </svg>
                </div>

                <div v-else-if="notFound" class="mx-auto max-w-xl rounded-md border border-red-200 bg-red-50 p-8 text-center">
                    <p class="text-lg font-semibold text-red-700">Odkaz už neplatí</p>
                    <p class="mt-2 text-sm text-gray-600">
                        Odkaz na zmenu adresy je neplatný alebo mu vypršala platnosť.
                        Napíšte nám na <a href="mailto:obchod@zastavy-vlajky.sk" class="text-blue-700 hover:underline">obchod@zastavy-vlajky.sk</a>
                        a adresu opravíme.
                    </p>
                    <router-link :to="{ name: 'public.index' }" class="mt-4 inline-block text-sm text-blue-700 hover:underline">
                        Späť na úvodnú stránku
                    </router-link>
                </div>

                <div v-else class="mx-auto max-w-3xl space-y-5">
                    <div class="rounded-md border border-gray-200 bg-white px-6 py-5 shadow-sm">
                        <h1 class="text-xl font-semibold text-gray-900">Adresa doručenia</h1>
                        <p v-if="info.serial_number" class="mt-1 text-sm text-gray-500">
                            Objednávka č. <span class="font-medium text-gray-700">{{ info.serial_number }}</span>
                        </p>
                        <p v-if="info.changed_at" class="mt-2 text-xs text-gray-400">
                            Naposledy zmenené {{ info.changed_at }}{{ info.changed_by ? ` — ${info.changed_by}` : '' }}
                        </p>
                    </div>

                    <!-- Objednávka už odišla — adresa je história -->
                    <div v-if="!info.can_edit" class="rounded-md border border-amber-200 bg-amber-50 px-6 py-5">
                        <p class="text-sm font-semibold text-amber-800">Objednávka je už v expedícii</p>
                        <p class="mt-1 text-sm text-amber-700">
                            Adresu už online zmeniť nevieme. Ozvite sa nám na
                            <a href="mailto:obchod@zastavy-vlajky.sk" class="underline">obchod@zastavy-vlajky.sk</a>
                            alebo na 0905 320 616 a dohodneme sa.
                        </p>
                        <div class="mt-4 rounded border border-amber-100 bg-white px-4 py-3 text-sm text-gray-700">
                            <p v-if="info.delivery.company" class="font-semibold">{{ info.delivery.company }}</p>
                            <p v-if="info.delivery.name">{{ info.delivery.name }}</p>
                            <p v-if="info.delivery.street">{{ info.delivery.street }}</p>
                            <p>{{ info.delivery.postcode }} {{ info.delivery.city }}</p>
                        </div>
                    </div>

                    <template v-else>
                        <p v-if="savedMessage" class="rounded-md border border-green-200 bg-green-50 px-5 py-3 text-sm font-semibold text-green-800">
                            {{ savedMessage }}
                        </p>
                        <p v-if="conflictMessage" class="rounded-md border border-red-200 bg-red-50 px-5 py-3 text-sm font-semibold text-red-700">
                            {{ conflictMessage }}
                        </p>

                        <DeliveryAddressFields
                            :modelValue="address"
                            v-model:enabled="deliverToOtherAddress"
                            :fieldErrors="fieldErrors"
                            :billing="info.billing"
                            title="Kam máme tovar doručiť"
                        />

                        <div class="flex items-center justify-between">
                            <router-link :to="{ name: 'public.order.show', params: { uuid: info.uuid }, query: { token } }"
                                         class="text-sm text-blue-700 hover:underline">
                                ← Späť na objednávku
                            </router-link>
                            <button
                                type="button"
                                @click="submit"
                                :disabled="saving"
                                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-400"
                            >
                                <svg v-if="saving" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                {{ saving ? 'Ukladám…' : 'Uložiť adresu' }}
                            </button>
                        </div>
                    </template>
                </div>
            </section>
        </template>
    </BaseLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import BaseLayout from '../layout/BaseLayout.vue';
import CustomerFormFields from '../forms/CustomerFormFields.vue';
import RequiredMark from '../forms/RequiredMark.vue';
import axios from '../../axiosInstance';
import useCheckouts from '../../store/StoreCheckouts';
import useCustomers from '../../store/StoreCustomers';
import useErrors from '../../store/StoreErrors';
import { salesError } from '../../models/sales';
import { formatFileSize } from '../../models/functions';

const router = useRouter();

const checkoutsStore = useCheckouts();
const { getCarts, attachments } = storeToRefs(checkoutsStore);
const { getlocalStorage, addAttachments, removeAttachment } = checkoutsStore;

const customersStore = useCustomers();
const { getCustomer } = storeToRefs(customersStore);
const { setCustomer } = customersStore;

const errorsStore = useErrors();
const { getFieldErrors } = storeToRefs(errorsStore);

// Rovnaké polia a povinnosť, aké čaká SalesQuoteController::store.
const CUSTOMER_FIELDS = ['company', 'name', 'email', 'phone', 'street', 'postcode', 'city', 'ico', 'dic', 'ic_dic'];
const REQUIRED_FIELDS = ['company', 'name', 'email', 'phone', 'street', 'postcode', 'city'];

const brief = ref('');
const busy = ref(false);
const error = ref('');
const highlightRequired = ref(false);
const attachmentInput = ref(null);
const attachmentErrors = ref([]);

const parseStoredCustomer = () => {
    try {
        return JSON.parse(localStorage.getItem('customer')) || {};
    } catch {
        localStorage.removeItem('customer');
        return {};
    }
};

// Údaje rozpísané v košíku (a naopak) sa zdieľajú — zákazník ich nepíše dvakrát.
onMounted(() => {
    getlocalStorage();
    setCustomer(parseStoredCustomer());
    errorsStore.resetErrors();
});

const briefError = computed(() => {
    const err = getFieldErrors.value?.brief;
    if (err) return Array.isArray(err) ? err[0] : err;
    return highlightRequired.value && !brief.value.trim() ? 'Popíšte, čo potrebujete naceniť.' : '';
});

const missingFields = () => [
    ...REQUIRED_FIELDS.filter((field) => !String(getCustomer.value?.[field] ?? '').trim()),
    ...(brief.value.trim() ? [] : ['brief']),
];

const onPickAttachments = (event) => {
    attachmentErrors.value = addAttachments(event.target.files);
    // Reset inputu, aby sa dal ten istý súbor po odobratí vybrať znova.
    event.target.value = '';
};

const onBriefInput = () => errorsStore.clearFieldError('brief');

async function submit() {
    if (busy.value) return;
    error.value = '';

    if (missingFields().length) {
        highlightRequired.value = true;
        error.value = 'Vyplňte povinné polia označené hviezdičkou.';
        return;
    }

    busy.value = true;
    errorsStore.resetErrors();
    try {
        const data = new FormData();
        CUSTOMER_FIELDS.forEach((field) => data.append('customer[' + field + ']', String(getCustomer.value?.[field] ?? '').trim()));
        data.append('brief', brief.value);
        getCarts.value.forEach((item, i) => {
            if (item.variant_id) {
                data.append('items[' + i + '][variant_id]', item.variant_id);
                data.append('items[' + i + '][quantity]', item.input_order);
            }
        });
        attachments.value.forEach((file, i) => data.append('attachments[' + i + ']', file));

        const response = await axios.post('/quote-requests', data);
        attachments.value = [];
        await router.push('/ponuka/' + response.data.uuid + '#token=' + response.data.token);
    } catch (e) {
        if (e.response?.status === 422) {
            errorsStore.setErrors(e);
        } else {
            error.value = salesError(e);
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <!-- Hlavička -->
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="page-heading mb-0">Cenová ponuka</h1>
                    <router-link
                        :to="{ name: 'public.index' }"
                        class="inline-flex items-center gap-1.5 text-sm text-blue-700 hover:text-blue-900"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Späť do katalógu
                    </router-link>
                </div>

                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Ľavý stĺpec: zadanie + kontaktné údaje -->
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Zadanie -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Zadanie</h2>
                            </div>
                            <div class="px-6 py-5">
                                <p class="mb-4 text-sm text-gray-600">
                                    Popíšte rozmery, množstvo, materiál a požadovaný termín. Položky z košíka priložíme k dopytu.
                                    Odoslaním zatiaľ nevzniká objednávka.
                                </p>

                                <label for="quote-brief" class="mb-1.5 block text-sm font-semibold text-gray-700">
                                    Čo potrebujete naceniť <RequiredMark />
                                </label>
                                <textarea
                                    id="quote-brief"
                                    v-model="brief"
                                    maxlength="10000"
                                    rows="6"
                                    placeholder="Napr. 20 ks vlajok 150 × 100 cm, obojstranná tlač, dodanie do 15. 11."
                                    class="w-full rounded border px-3 py-2 focus:outline-none focus:ring-1"
                                    :class="briefError
                                        ? 'border-red-500 ring-1 ring-red-500 bg-red-50'
                                        : 'border-gray-300 focus:border-blue-400 focus:ring-blue-400'"
                                    @input="onBriefInput"
                                />
                                <p v-if="briefError" class="mt-1 text-xs font-semibold text-red-600">{{ briefError }}</p>

                                <!-- Podklady (logo, návrh, rozmery) -->
                                <div class="mt-4">
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">Podklady</label>
                                    <p class="mb-2 text-xs text-gray-500">
                                        Logo, návrh, rozmery. Max. 5 súborov, každý do 10 MB
                                        (pdf, jpg, png, svg, ai, eps, cdr, psd, zip, doc, xls).
                                    </p>

                                    <input
                                        ref="attachmentInput"
                                        type="file"
                                        multiple
                                        class="hidden"
                                        accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.svg,.ai,.eps,.cdr,.psd,.zip,.rar,.doc,.docx,.xls,.xlsx"
                                        @change="onPickAttachments"
                                    />
                                    <button
                                        type="button"
                                        :disabled="attachments.length >= 5"
                                        class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:border-blue-400 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="attachmentInput?.click()"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
                                        </svg>
                                        Pridať súbor
                                    </button>

                                    <ul v-if="attachments.length" class="mt-3 space-y-1.5">
                                        <li
                                            v-for="(file, index) in attachments"
                                            :key="file.name + file.size"
                                            class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
                                        >
                                            <span class="truncate text-gray-700">{{ file.name }}</span>
                                            <span class="flex shrink-0 items-center gap-2">
                                                <span class="text-xs text-gray-400">{{ formatFileSize(file.size) }}</span>
                                                <button
                                                    type="button"
                                                    class="rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                                                    title="Odstrániť prílohu"
                                                    @click="removeAttachment(index)"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </span>
                                        </li>
                                    </ul>

                                    <p v-for="message in attachmentErrors" :key="message" class="mt-1.5 text-xs text-red-600">
                                        {{ message }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Kontaktné / fakturačné údaje -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Kontaktné údaje</h2>
                            </div>
                            <div class="px-6 py-5">
                                <CustomerFormFields
                                    :fieldErrors="getFieldErrors"
                                    :requiredFields="REQUIRED_FIELDS"
                                    :highlightRequired="highlightRequired"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Pravý stĺpec: položky z košíka + odoslanie -->
                    <aside class="space-y-4">
                        <div class="sticky top-4 space-y-4">
                            <div v-if="getCarts.length" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                                    <h2 class="text-sm font-semibold text-gray-700">Položky z košíka ({{ getCarts.length }})</h2>
                                </div>
                                <div class="divide-y divide-gray-50 px-5 py-3">
                                    <div v-for="product in getCarts" :key="product.key" class="py-1.5 text-sm leading-snug text-gray-600">
                                        {{ product.name }}
                                        <span v-if="product.variant_name" class="text-gray-500">({{ product.variant_name }})</span>
                                        <span class="text-gray-400">× {{ product.input_order }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                                    <h2 class="text-sm font-semibold text-gray-700">Odoslanie dopytu</h2>
                                </div>
                                <div class="px-5 py-4">
                                    <p class="text-sm text-gray-600">
                                        Dopyt nacení obsluha. Po odoslaní dostanete odkaz, na ktorom ponuku skontrolujete a prijmete.
                                    </p>

                                    <p v-if="error" role="alert" class="mt-3 text-sm font-semibold text-red-600">{{ error }}</p>

                                    <button
                                        type="button"
                                        @click="submit"
                                        :disabled="busy"
                                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-400"
                                    >
                                        <svg v-if="busy" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        {{ busy ? 'Odosielam...' : 'Odoslať dopyt' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>

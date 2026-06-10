<script setup>
import BaseLayout from "../layout/BaseLayout.vue";
import { onMounted, ref } from "vue";
import useCheckouts from "../../store/StoreCheckouts";
import useCustomers from "../../store/StoreCustomers";
import useOrders from "../../store/StoreOrders";
import useErrors from "../../store/StoreErrors";
import router from "../../router";
import search from "../icons/search.vue";
import { formatDecimal } from "../../models/functions";
import RequiredMark from "../forms/RequiredMark.vue";
import FormInput from "../forms/FormInput.vue";
import ButtonSubmit from "../layout/page/ButtonSubmit.vue";


const {
      removeCart,
      storeCheckout,
      getCarts,
      getCheckout,
      getlocalStorage,
      resetCarts,
      updateCartQuantity,
} = useCheckouts();

const { getCustomer, setCustomer, findCustomerByIco, } = useCustomers();
const { getOrder } = useOrders();
const { getFieldErrors } = useErrors();
const isSearchingCompany = ref(false);
const isSubmitting = ref(false);
const icoSearchMessage = ref("");
const highlightMissingRequired = ref(false);

const isRequiredMissing = (field) => {
      return highlightMissingRequired.value && !String(getCustomer.value?.[field] ?? '').trim();
};

const fieldError = (field) => {
      const errors = getFieldErrors.value?.[`customer.${field}`] ?? getFieldErrors.value?.[field] ?? [];

      return Array.isArray(errors) ? errors[0] : errors;
};

const icoError = () => {
      if (String(getCustomer.value?.ico ?? '').length > 8) {
            return 'IČO môže mať maximálne 8 číslic.';
      }

      return fieldError('ico');
};


const parseStoredCustomer = () => {
      try {
            return JSON.parse(localStorage.getItem('customer')) || {};
      } catch {
            localStorage.removeItem('customer');
            return {};
      }
};

const shortDescription = (product) => {
      return String(product.description || '').substring(0, 25);
};

const productTotal = (product) => {
      return formatDecimal(Number(product.active_price || 0) * Number(product.input_order || 0));
};

const onlyDigits = (event) => {
      getCustomer.value.ico = String(event.target.value || '').replace(/\D/g, '');
};

const padIco = () => {
      const ico = String(getCustomer.value?.ico ?? '').trim();
      if (ico.length > 0 && ico.length < 8) {
            getCustomer.value.ico = ico.padStart(8, '0');
      }
};


onMounted(() => {
      getlocalStorage();

      if (getCarts.value.length) {
            setCustomer(parseStoredCustomer());
      }

});

const clickEmptyBasket = () => {
      if (!window.confirm("Skutočne vyprázniť košík!")) {
            return;
      }
      resetCarts();
};


const onClickIco = async () => {
      icoSearchMessage.value = "";
      padIco();

      if (icoError()) {
            return;
      }

      isSearchingCompany.value = true;

      try {
            const response = await findCustomerByIco();
            highlightMissingRequired.value = ['internet', 'database_with_internet'].includes(response?.source);
            icoSearchMessage.value = "Údaje firmy boli doplnené.";
      } catch (error) {
            highlightMissingRequired.value = false;
            icoSearchMessage.value = error.response?.data?.message || error.message || "Firmu sa nepodarilo nájsť.";
      } finally {
            isSearchingCompany.value = false;
      }
};

const onClickForm = async () => {
      if (isSubmitting.value) {
            return;
      }

      if (!getCarts.value.length) {
            return alert("Objednávka je prázdna!");
      }

      isSubmitting.value = true;
      const wasStored = await storeCheckout();
      isSubmitting.value = false;

      if (wasStored) {
            router.push({ name: "public.thankYouForOrder.show" });
      }
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">

                <!-- Hlavička -->
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="page-heading mb-0">Váš košík</h1>
                    <router-link
                        :to="{ name: 'public.index' }"
                        class="inline-flex items-center gap-1.5 text-sm text-blue-700 hover:text-blue-900"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        {{ getCarts.length ? 'Pokračovať v nákupe' : 'Začnite nakupovať' }}
                    </router-link>
                </div>

                <!-- Prázdny košík -->
                <div v-if="!getCarts.length" class="rounded-xl border border-dashed border-gray-300 bg-white py-20 text-center shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-4 h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="text-lg font-semibold text-gray-400">Košík je prázdny</p>
                    <p class="mt-1 text-sm text-gray-400">Vyberte produkty z nášho katalógu</p>
                </div>

                <!-- Obsah košíka -->
                <div v-else class="grid gap-6 lg:grid-cols-3">

                    <!-- Ľavý stĺpec: produkty + formulár -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Tabuľka produktov -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Tovar</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Cena/ks</th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Množstvo</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Spolu</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="product in getCarts" :key="product.id" class="transition hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <img
                                                    :src="product.thumb"
                                                    :alt="product.name"
                                                    class="h-12 w-12 shrink-0 rounded-lg border border-gray-200 object-cover"
                                                />
                                                <div>
                                                    <p class="font-semibold text-gray-900">{{ product.name }}</p>
                                                    <p class="text-xs text-gray-400">{{ shortDescription(product) }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap text-gray-700">
                                            {{ formatDecimal(product.active_price) }} €
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="inline-flex items-center gap-1.5">
                                                <input
                                                    type="number"
                                                    v-model.number="product.input_order"
                                                    class="w-16 rounded-lg border border-gray-300 px-2 py-1 text-center text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                    :min="product.min_order"
                                                    @change="updateCartQuantity(product, product.input_order)"
                                                    required
                                                />
                                                <span class="text-xs text-gray-500">{{ product.unit_value }}</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm font-semibold whitespace-nowrap text-gray-900">
                                            {{ productTotal(product) }} €
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                type="button"
                                                @click="removeCart(product)"
                                                class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                                                title="Odstrániť"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="flex justify-end border-t border-gray-100 px-4 py-2">
                                <button
                                    type="button"
                                    @click="clickEmptyBasket"
                                    class="inline-flex items-center gap-1.5 text-xs text-gray-400 transition hover:text-red-600"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                    Vyprázdniť košík
                                </button>
                            </div>
                        </div>

                        <!-- Fakturačné údaje -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Fakturačné údaje</h2>
                            </div>
                            <div class="px-6 py-5">

                                <!-- IČO vyhľadávanie -->
                                <div class="mb-6 rounded-lg border border-blue-100 bg-blue-50 p-4">
                                    <label class="mb-2 block text-sm font-semibold text-blue-900">Rýchle doplnenie — vyhľadajte firmu podľa IČO</label>
                                    <div class="flex gap-2">
                                        <FormInput
                                            :invalid="!!icoError()"
                                            v-model="getCustomer.ico"
                                            placeholder="IČO organizácie"
                                            inputmode="numeric"
                                            pattern="[0-9]*"
                                            @input="onlyDigits"
                                            @blur="padIco"
                                            @keyup.enter="onClickIco"
                                        />
                                        <button
                                            type="button"
                                            @click="onClickIco"
                                            :disabled="isSearchingCompany"
                                            class="whitespace-nowrap rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:bg-gray-400"
                                        >
                                            {{ isSearchingCompany ? 'Hľadám...' : 'Vyhľadať firmu' }}
                                        </button>
                                    </div>
                                    <p v-if="icoError()" class="mt-2 text-xs font-semibold text-red-600">{{ icoError() }}</p>
                                    <p v-if="icoSearchMessage" class="mt-2 text-xs text-blue-700">{{ icoSearchMessage }}</p>
                                </div>

                                <!-- Formulár -->
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <div class="sm:col-span-2 lg:col-span-3">
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Názov firmy <RequiredMark /></label>
                                        <FormInput v-model="getCustomer.company" :invalid="isRequiredMissing('company')" :error="fieldError('company')" placeholder="Názov firmy" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Ulica a číslo <RequiredMark /></label>
                                        <FormInput v-model="getCustomer.street" :invalid="isRequiredMissing('street')" placeholder="Ulica a číslo" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">PSČ <RequiredMark /></label>
                                        <FormInput v-model="getCustomer.postcode" :invalid="isRequiredMissing('postcode')" placeholder="PSČ" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Mesto <RequiredMark /></label>
                                        <FormInput v-model="getCustomer.city" :invalid="isRequiredMissing('city')" placeholder="Mesto" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Kontaktné meno <RequiredMark /></label>
                                        <FormInput v-model="getCustomer.name" :invalid="isRequiredMissing('name')" placeholder="Meno objednávateľa" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Email <RequiredMark /></label>
                                        <FormInput v-model="getCustomer.email" type="email" :invalid="isRequiredMissing('email') || !!fieldError('email')" :error="fieldError('email')" placeholder="Email" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Telefón <RequiredMark /></label>
                                        <FormInput v-model="getCustomer.phone" :invalid="isRequiredMissing('phone')" placeholder="Telefón" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">IČO</label>
                                        <FormInput v-model="getCustomer.ico" :invalid="!!icoError()" :error="icoError()" inputmode="numeric" pattern="[0-9]*" placeholder="IČO" @input="onlyDigits" @blur="padIco" @keyup.enter="onClickIco" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">DIČ</label>
                                        <FormInput v-model="getCustomer.dic" placeholder="DIČ" />
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">IČ DPH</label>
                                        <FormInput v-model="getCustomer.ic_dic" placeholder="IČ DPH" />
                                    </div>
                                    <div class="sm:col-span-2 lg:col-span-3">
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">Poznámka k objednávke</label>
                                        <FormInput v-model="getOrder.notice" placeholder="Poznámka" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pravý stĺpec: súhrn objednávky -->
                    <aside class="space-y-4">
                        <div class="sticky top-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Súhrn objednávky</h2>
                            </div>
                            <div class="px-5 py-4">
                                <div class="space-y-2 text-sm">
                                    <div v-for="product in getCarts" :key="product.id" class="flex items-start justify-between gap-2">
                                        <span class="text-gray-600 leading-snug">
                                            {{ product.name }}
                                            <span class="text-gray-400">× {{ product.input_order }}</span>
                                        </span>
                                        <span class="shrink-0 font-medium text-gray-900">{{ productTotal(product) }} €</span>
                                    </div>
                                </div>

                                <div class="my-4 border-t border-gray-100"></div>

                                <div class="flex justify-between text-sm text-gray-500">
                                    <span>Počet položiek</span>
                                    <span class="font-medium text-gray-700">{{ getCarts.length }}</span>
                                </div>
                                <div class="flex justify-between text-sm text-gray-500 mt-1">
                                    <span>Celkové množstvo</span>
                                    <span class="font-medium text-gray-700">{{ getCheckout.grandQuantity }} ks</span>
                                </div>

                                <div class="my-4 border-t border-gray-200"></div>

                                <div class="flex items-center justify-between">
                                    <span class="text-base font-semibold text-gray-900">Celkom s DPH</span>
                                    <span class="text-xl font-bold text-blue-700">{{ formatDecimal(getCheckout.grandTotal) }} €</span>
                                </div>

                                <button
                                    type="button"
                                    @click="onClickForm"
                                    :disabled="isSubmitting || !getCarts.length"
                                    class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-400"
                                >
                                    <svg v-if="isSubmitting" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    {{ isSubmitting ? 'Odosielam...' : 'Odoslať objednávku' }}
                                </button>
                            </div>
                        </div>
                    </aside>
                </div>

            </div>
        </template>
    </BaseLayout>
</template>


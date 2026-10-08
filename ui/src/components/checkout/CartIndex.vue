<script setup>
import CustomOrderItem from '../forms/CustomOrderItem.vue';
import OrderPriceAdjustment from '../forms/OrderPriceAdjustment.vue';
import { adjustmentAmount } from '../../models/orderPricing';
import useCheckoutOptions from '../../store/StoreCheckoutOptions';
import BaseLayout from "../layout/BaseLayout.vue";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import useCheckouts, { CUSTOMER_STORAGE_KEY } from "../../store/StoreCheckouts";
import useCustomers from "../../store/StoreCustomers";
import { useUsers } from "../../store/StoreUsers";
import useErrors from "../../store/StoreErrors";
import router from "../../router";
import { formatDecimal, formatPrice, formatFileSize, formatSubstring } from "../../models/functions";
import { htmlToText } from "../../models/html";
import CustomerFormFields from "../forms/CustomerFormFields.vue";
import DeliveryAddressFields from "../forms/DeliveryAddressFields.vue";
import ShippingPaymentSelector from "../forms/ShippingPaymentSelector.vue";
import EmailOrderImport from "./EmailOrderImport.vue";

const checkoutsStore = useCheckouts();
const { getCarts, getCheckout, note, attachments, delivery, deliverToOtherAddress, customerDraft } = storeToRefs(checkoutsStore);
const {
      removeCart,
      storeCheckout,
      getlocalStorage,
      resetCarts,
      updateCartQuantity,
      addAttachments,
      removeAttachment,
} = checkoutsStore;

const customersStore = useCustomers();
const { getCustomer } = storeToRefs(customersStore);
const isPrivatePerson = ref(false);
watch(isPrivatePerson, (value) => { if (value) getCustomer.value.company = ''; });
const { setCustomer } = customersStore;
const { getFieldErrors } = storeToRefs(useErrors());
const { getUser } = storeToRefs(useUsers());
const isSubmitting = ref(false);

const isStaff = computed(() => getUser.value?.roles?.some(role => ['super-admin', 'admin', 'manager', 'sales', 'warehouse'].includes(role)));
const { priceAdjustment } = storeToRefs(checkoutsStore);
const checkoutOptions = useCheckoutOptions();
const adjustment = computed(() => isStaff.value ? adjustmentAmount(getCheckout.value.grandTotal, priceAdjustment.value, checkoutOptions.discountAmount) : 0);
const isSuperAdmin = computed(() => Boolean(getUser.value?.roles?.includes("super-admin")));
const notifyCustomer = ref(true);
const showSubmitModal = ref(false);
const emailImport = ref(null);

const parseStoredCustomer = () => {
      try {
            return JSON.parse(localStorage.getItem(CUSTOMER_STORAGE_KEY)) || {};
      } catch {
            localStorage.removeItem(CUSTOMER_STORAGE_KEY);
            return {};
      }
};

const attachmentInput = ref(null);
const attachmentErrors = ref([]);

const onPickAttachments = (event) => {
      attachmentErrors.value = addAttachments(event.target.files);
      // Reset inputu, aby sa dal ten istý súbor po odobratí vybrať znova.
      event.target.value = "";
};

// Najnižší prah dopravy zdarma spomedzi spôsobov dopravy.
const freeShippingInfo = computed(() => {
      const withFree = [...(checkoutOptions.getShippingMethods ?? [])]
            .filter((method) => method.free_from_price !== null && method.free_from_price !== undefined && Number(method.free_from_price) > 0)
            .sort((a, b) => Number(a.free_from_price) - Number(b.free_from_price));
      if (!withFree.length) return null;
      const threshold = Number(withFree[0].free_from_price);
      return {
            threshold,
            methodName: withFree[0].name,
            remaining: Math.max(0, threshold - Number(getCheckout.value?.grandTotal ?? 0)),
      };
});

const shortDescription = (product) => formatSubstring(htmlToText(product.description), 60);
const productTotal = (product) => formatPrice(Number(product.active_price || 0) * Number(product.input_order || 0));

onMounted(() => {
      getlocalStorage();
      // Návrat do košíka v tej istej relácii: rozpísané údaje (aj doplnené z e-mailu)
      // majú prednosť pred localStorage, kam sa údaje obsluhy vôbec nezapisujú.
      if (customerDraft.value && Object.keys(customerDraft.value.customer).length) {
            setCustomer({ ...customerDraft.value.customer });
            isPrivatePerson.value = customerDraft.value.isPrivatePerson;
      } else if (getCarts.value.length) {
            setCustomer(parseStoredCustomer());
      }
});

onBeforeUnmount(() => {
      customerDraft.value = { customer: { ...getCustomer.value }, isPrivatePerson: isPrivatePerson.value };
});

const clickEmptyBasket = () => {
      if (!window.confirm("Skutočne vyprázniť košík!")) return;
      resetCarts();
};

const onClickForm = async () => {
      if (isSubmitting.value) {
            return;
      }

      if (!getCarts.value.length) {
            return alert("Objednávka je prázdna!");
      }

      // Super-admin zadáva objednávku za zákazníka — nech si sám zvolí,
      // či mu má odísť potvrdzovací e-mail.
      if (isSuperAdmin.value) {
            notifyCustomer.value = true;
            showSubmitModal.value = true;
            return;
      }

      await submitOrder(true);
};

const submitOrder = async (sendNotification = notifyCustomer.value) => {
      if (isSubmitting.value) {
            return;
      }

      isSubmitting.value = true;
      const result = await storeCheckout({ notifyCustomer: Boolean(sendNotification) });
      isSubmitting.value = false;
      showSubmitModal.value = false;

      if (result) {
            const uuid = typeof result === 'string' ? result : null;
            router.push({
                  name: "public.thankYouForOrder.show",
                  query: uuid ? { token: uuid } : {},
            });
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

                <CustomOrderItem v-if="isStaff" @add="checkoutsStore.submitCartToIndex($event)" />
                <EmailOrderImport v-if="isSuperAdmin" ref="emailImport" @applied="isPrivatePerson = !$event.company" />
                <div v-if="isSuperAdmin && !getCarts.length" class="mb-4 rounded-lg border border-blue-100 bg-blue-50 p-4">
                    <button type="button" class="text-sm font-semibold text-blue-800 underline" @click="emailImport?.open()">Doplniť z e-mailu pomocou AI</button>
                </div>
                <OrderPriceAdjustment v-if="isStaff && getCarts.length" v-model="priceAdjustment" :subtotal="getCheckout.grandTotal" :coupon="checkoutOptions.discountAmount" />
                <!-- Prázdny košík -->
                <div v-if="!getCarts.length" class="rounded-xl border border-dashed border-gray-300 bg-white py-20 text-center shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-4 h-16 w-16 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <p class="text-lg font-semibold text-gray-400">Košík je prázdny</p>
                    <p class="mt-1 text-sm text-gray-400">Vyberte produkty z nášho katalógu</p>
                    <router-link
                        :to="{ name: 'public.index' }"
                        class="mt-6 inline-flex items-center rounded-md bg-blue-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-900"
                    >
                        Prejsť do katalógu
                    </router-link>
                </div>

                <!-- Obsah košíka -->
                <div v-else class="grid gap-6 lg:grid-cols-3">

                    <!-- Ľavý stĺpec: produkty + formulár -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Doprava zdarma: suma a podmienky vidno hneď, nie až pri výbere dopravy -->
                        <div v-if="freeShippingInfo" class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">
                            <template v-if="freeShippingInfo.remaining > 0">
                                Doprava zdarma pri objednávke od <strong>{{ formatPrice(freeShippingInfo.threshold) }} €</strong>
                                ({{ freeShippingInfo.methodName }}). Do dopravy zdarma vám chýba <strong>{{ formatPrice(freeShippingInfo.remaining) }} €</strong>.
                            </template>
                            <template v-else>
                                Máte nárok na dopravu zdarma ({{ freeShippingInfo.methodName }}).
                            </template>
                        </div>

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
                                    <tr v-for="product in getCarts" :key="product.key" class="transition hover:bg-gray-50">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <img
                                                    :src="product.thumb"
                                                    width="48" height="48" loading="lazy"
                                                    :alt="product.name"
                                                    class="h-12 w-12 shrink-0 rounded-lg border border-gray-200 object-cover"
                                                />
                                                <div>
                                                    <p class="font-semibold text-gray-900">{{ product.name }}</p>
                                                    <p v-if="product.variant_name" class="text-xs font-medium text-blue-700">
                                                        {{ product.variant_name }}
                                                    </p>
                                                    <p class="text-xs text-gray-400">{{ shortDescription(product) }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right text-sm whitespace-nowrap text-gray-700">
                                            {{ formatPrice(product.active_price) }} €
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            <div class="inline-flex items-center gap-1.5">
                                                <input
                                                    type="number"
                                                    inputmode="numeric"
                                                    step="1"
                                                    :aria-label="`Množstvo: ${product.name}${product.variant_name ? ' – ' + product.variant_name : ''} (${product.unit_value || 'ks'})`"
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
                                <fieldset class="mb-4 flex gap-6 text-sm"><legend class="sr-only">Typ zákazníka</legend><label class="flex items-center gap-2"><input type="radio" :value="false" v-model="isPrivatePerson" />Firma</label><label class="flex items-center gap-2"><input type="radio" :value="true" v-model="isPrivatePerson" />Súkromná osoba</label></fieldset>
                                <CustomerFormFields
                                    :hideCompany="isPrivatePerson"
                                    :fieldErrors="getFieldErrors"
                                    :requiredFields="(isPrivatePerson ? [] : ['company']).concat(['name', 'email', 'phone', 'street', 'postcode', 'city'])"
                                >
                                    <template #quick-fill-action>
                                        <button v-if="isSuperAdmin" type="button" class="text-sm font-semibold text-blue-800 underline hover:text-blue-950" @click="emailImport?.open()">Doplniť z e-mailu pomocou AI</button>
                                    </template>
                                </CustomerFormFields>
                                <div class="mt-4">
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">Poznámka k objednávke</label>
                                    <input v-model="note" type="text" aria-label="Poznámka k objednávke" autocomplete="off" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Poznámka" />
                                </div>

                                <!-- Prílohy k objednávke (logo, návrh, podklady) -->
                                <div class="mt-4">
                                    <label for="cart-attachments" class="mb-1.5 block text-sm font-semibold text-gray-700">Prílohy</label>
                                    <p class="mb-2 text-xs text-gray-500">
                                        Podklady k výrobe – logo, návrh, rozmery. Max. 5 súborov, každý do 10 MB
                                        (pdf, jpg, png, svg, ai, eps, cdr, psd, zip, doc, xls).
                                    </p>

                                    <input
                                        ref="attachmentInput"
                                        id="cart-attachments"
                                        type="file"
                                        multiple
                                        class="hidden"
                                        accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.svg,.ai,.eps,.cdr,.psd,.zip,.rar,.doc,.docx,.xls,.xlsx"
                                        @change="onPickAttachments"
                                    />
                                    <button
                                        type="button"
                                        @click="attachmentInput?.click()"
                                        :disabled="attachments.length >= 5"
                                        class="inline-flex items-center gap-2 rounded-lg border border-dashed border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 transition hover:border-blue-400 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
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
                                                    @click="removeAttachment(index)"
                                                    class="rounded p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                                                    title="Odstrániť prílohu"
                                                >
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </span>
                                        </li>
                                    </ul>

                                    <p v-for="error in attachmentErrors" :key="error" class="mt-1.5 text-xs text-red-600">
                                        {{ error }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Doručovacia adresa -->
                        <DeliveryAddressFields
                            :modelValue="delivery"
                            v-model:enabled="deliverToOtherAddress"
                            :fieldErrors="getFieldErrors"
                            :billing="getCustomer"
                        />
                    </div>

                    <!-- Pravý stĺpec: doprava, platba, kupón, súhrn -->
                    <aside class="space-y-4">
                        <div class="sticky top-4 space-y-4">
                            <!-- Zoznam produktov v súhrne -->
                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                                    <h2 class="text-sm font-semibold text-gray-700">
                                        Produkty ({{ getCarts.length }})
                                    </h2>
                                </div>
                                <div class="divide-y divide-gray-50 px-5 py-3">
                                    <div v-for="product in getCarts" :key="product.key" class="flex items-start justify-between gap-2 py-1.5 text-sm">
                                        <span class="text-gray-600 leading-snug">
                                            {{ product.name }}
                                            <span v-if="product.variant_name" class="text-gray-500">({{ product.variant_name }})</span>
                                            <span class="text-gray-400">× {{ product.input_order }}</span>
                                        </span>
                                        <span class="shrink-0 font-medium text-gray-900">{{ productTotal(product) }} €</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Doprava + platba + kupón + rekapitulácia -->
                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                                    <h2 class="text-sm font-semibold text-gray-700">Doprava a platba</h2>
                                </div>
                                <div class="px-5 py-4">
                                    <ShippingPaymentSelector :cartTotal="getCheckout.grandTotal" :adjustment="adjustment" />

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
                        </div>
                    </aside>
                </div>

            </div>

            <!-- Modal: odoslanie objednávky (len super-admin) -->
            <Teleport to="body">
                <div v-if="showSubmitModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
                    <div class="w-full max-w-sm rounded bg-white p-5 shadow-lg">
                        <h3 class="mb-3 text-lg font-semibold text-gray-800">Odoslať objednávku</h3>
                        <p class="mb-4 text-sm text-gray-600">
                            Vytvoriť objednávku pre {{ getCustomer.company || getCustomer.name || 'zákazníka' }}?
                        </p>
                        <label class="mb-5 flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" v-model="notifyCustomer" class="rounded" />
                            Poslať zákazníkovi e-mail o objednávke
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showSubmitModal = false"
                                class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                                Zrušiť
                            </button>
                            <button type="button" @click="submitOrder()" :disabled="isSubmitting"
                                class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-blue-400">
                                <svg v-if="isSubmitting" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Potvrdiť
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </template>
    </BaseLayout>
</template>


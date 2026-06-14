<script setup>
import { computed, onMounted, ref } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import useCustomers from "../../store/StoreCustomers";
import useOrders from "../../store/StoreOrders";
import useProducts from "../../store/StoreProducts";
import useUser from "../../store/StoreUsers";
import useErrors from "../../store/StoreErrors";
import router from "../../router";
import axiosInstance from "../../axiosInstance";
import { formatDecimal } from "../../models/functions";
import SpinnerButton from "../icons/spinnerButton.vue";
import loadingStore from "../../store/StoreLoading";
import CustomerFormFields from "../forms/CustomerFormFields.vue";

const { getCustomer, setCustomer, fetchCustomer } = useCustomers();
const { storeOrder, state: orderState } = useOrders();
const { fetchProducts, getProducts } = useProducts();
const { getUser } = useUser();
const { getFieldErrors, setErrors, resetErrors } = useErrors();

const orderProducts = ref([]);
const selectedProductId = ref("");
const productSearch = ref("");
const notifyCustomer = ref(true);
const showSaveModal = ref(false);
const isSubmitting = ref(false);
const highlightMissingRequired = ref(false);

const customerSearch = ref("");
const customerSearchResults = ref([]);
const customerSearchLoading = ref(false);
const showCustomerDropdown = ref(false);
const selectedUserId = ref(null);
let searchTimer = null;

const applyUser = (user) => {
    selectedUserId.value = user.id;
    const fullName = [user.firstName, user.lastName].filter(Boolean).join(" ") || user.username || "";
    if (fullName) getCustomer.value.name = fullName;
    if (user.email) getCustomer.value.email = user.email;
    if (user.phone) getCustomer.value.phone = user.phone;
};

const onCustomerSearchInput = () => {
    clearTimeout(searchTimer);
    if (!customerSearch.value.trim()) {
        customerSearchResults.value = [];
        showCustomerDropdown.value = false;
        return;
    }
    customerSearchLoading.value = true;
    searchTimer = setTimeout(async () => {
        try {
            const res = await axiosInstance.get(`/customers?bySearchInput=${encodeURIComponent(customerSearch.value)}&per_page=8`);
            customerSearchResults.value = res.data?.data ?? [];
            showCustomerDropdown.value = true;
        } finally {
            customerSearchLoading.value = false;
        }
    }, 300);
};

const selectCustomerFromSearch = async (customer) => {
    customerSearch.value = customer.company || customer.name || "";
    showCustomerDropdown.value = false;
    customerSearchResults.value = [];
    selectedUserId.value = null;
    await fetchCustomer(customer.id);
    const users = getCustomer.value?.users ?? [];
    if (users.length === 1) applyUser(users[0]);
};

const shippingMethods = ref([]);
const paymentMethods  = ref([]);
const selectedShippingId = ref(null);
const selectedPaymentId  = ref(null);

const buttonBack = { name: "Späť", spinner: true, link: "/objednavky", icon: "arrow-left" };
const requiredCustomerFields = ["company", "name", "email", "phone", "street", "postcode", "city"];

const isSuperAdmin = computed(() => getUser.value?.roles?.includes("super-admin"));

const filteredProducts = computed(() => {
    const search = productSearch.value.trim().toLowerCase();
    const products = getProducts.value || [];
    if (!search) return products;
    return products.filter((product) =>
        [product.code, product.name, product.description]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(search))
    );
});

const selectedProduct = computed(() =>
    (getProducts.value || []).find((product) => String(product.id) === String(selectedProductId.value))
);

const grandQuantity = computed(() => orderProducts.value.reduce((sum, item) => sum + Number(item.input_order || 0), 0));
const grandTotal = computed(() => orderProducts.value.reduce((sum, item) =>
    sum + Number(item.active_price || 0) * Number(item.input_order || 0), 0));

const selectedShipping = computed(() => shippingMethods.value.find(m => m.id === selectedShippingId.value) ?? null);
const selectedPayment  = computed(() => paymentMethods.value.find(m => m.id === selectedPaymentId.value) ?? null);

const shippingPrice = computed(() => {
    if (!selectedShipping.value) return 0;
    const free = parseFloat(selectedShipping.value.free_from_price ?? -1);
    if (free >= 0 && grandTotal.value >= free) return 0;
    return parseFloat(selectedShipping.value.price ?? 0);
});

const paymentFee = computed(() => parseFloat(selectedPayment.value?.fee ?? 0));
const grandTotalWithExtras = computed(() => grandTotal.value + shippingPrice.value + paymentFee.value);

const isCustomerComplete = computed(() =>
    requiredCustomerFields.every((field) => String(getCustomer.value?.[field] ?? "").trim())
);

const addProduct = () => {
    const product = selectedProduct.value;
    if (!product) return;

    const existing = orderProducts.value.find((item) => item.id === product.id);
    const minOrder = Number(product.min_order || 1);

    if (existing) {
        existing.input_order = Number(existing.input_order || 0) + minOrder;
    } else {
        orderProducts.value.push({
            ...product,
            input_order: Number(product.input_order || minOrder),
        });
    }

    selectedProductId.value = "";
};

const removeProduct = (product) => {
    orderProducts.value = orderProducts.value.filter((item) => item.id !== product.id);
};

const normalizeQuantity = (product) => {
    const minOrder = Number(product.min_order || 1);
    product.input_order = Math.max(minOrder, Number(product.input_order || minOrder));
};

const openSaveModal = () => {
    if (!orderProducts.value.length) {
        return alert("Pridajte aspoň jednu položku objednávky.");
    }

    if (!isCustomerComplete.value) {
        highlightMissingRequired.value = true;
        setErrors({ message: "Vyplňte všetky povinné údaje zákazníka označené hviezdičkou." });
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }

    highlightMissingRequired.value = false;
    resetErrors();

    if (isSuperAdmin.value) {
        notifyCustomer.value = true;
        showSaveModal.value = true;
        return;
    }

    confirmSave(false);
};

const confirmSave = async (sendNotification = notifyCustomer.value) => {
    if (isSubmitting.value) return;

    isSubmitting.value = true;

    const order = await storeOrder({
        customer: getCustomer.value,
        orderProducts: orderProducts.value,
        note: orderState.order.note || null,
        notify_customer: Boolean(sendNotification),
        shipping_method_id: selectedShippingId.value,
        payment_method_id:  selectedPaymentId.value,
    });

    isSubmitting.value = false;
    showSaveModal.value = false;

    if (order?.id) {
        router.push({ name: "orders.show", params: { orderId: order.id } });
    }
};

onMounted(async () => {
    fetchProducts();
    orderProducts.value = [];

    const user = getUser.value;
    if (!isSuperAdmin.value && user?.customer_id) {
        await fetchCustomer(user.customer_id);
        const users = getCustomer.value?.users ?? [];
        if (users.length === 1) applyUser(users[0]);
    } else {
        setCustomer({});
    }

    const [sm, pm] = await Promise.all([
        axiosInstance.get('/shipping-methods'),
        axiosInstance.get('/payment-methods'),
    ]);
    shippingMethods.value = sm.data?.data ?? [];
    paymentMethods.value  = pm.data?.data ?? [];

    const defShipping = shippingMethods.value.find(m => m.name?.toLowerCase().includes('slovenská pošta') || m.name?.toLowerCase().includes('slovenska posta'));
    if (defShipping) selectedShippingId.value = defShipping.id;

    const defPayment = paymentMethods.value.find(m => m.name?.toLowerCase().includes('faktúra') || m.name?.toLowerCase().includes('faktura'));
    if (defPayment) selectedPaymentId.value = defPayment.id;
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Nová objednávka', buttonLink: buttonBack }" />

                <div class="grid gap-6 lg:grid-cols-3">

                    <!-- Ľavý stĺpec: produkty + zákazník -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Položky objednávky -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Položky objednávky</h2>
                            </div>
                            <div class="p-5">
                                <!-- Výber produktu -->
                                <div class="mb-4 grid gap-3 md:grid-cols-[1fr_2fr_auto]">
                                    <input
                                        v-model="productSearch"
                                        type="text"
                                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        placeholder="Filtrovať podľa kódu alebo názvu"
                                    />
                                    <select v-model="selectedProductId" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="">Vyberte produkt</option>
                                        <option v-for="product in filteredProducts" :key="product.id" :value="product.id">
                                            {{ product.code }} – {{ product.name }} ({{ formatDecimal(product.active_price) }} €)
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700"
                                        @click="addProduct"
                                    >
                                        Pridať
                                    </button>
                                </div>

                                <!-- Tabuľka produktov -->
                                <div class="overflow-x-auto">
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
                                            <tr v-for="product in orderProducts" :key="product.id" class="transition hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-3">
                                                        <img
                                                            v-if="product.thumb"
                                                            :src="product.thumb"
                                                            :alt="product.name"
                                                            class="h-12 w-12 shrink-0 rounded-lg border border-gray-200 object-cover"
                                                        />
                                                        <div>
                                                            <p class="font-semibold text-gray-900">{{ product.name }}</p>
                                                            <p class="text-xs text-gray-400">{{ product.code }}</p>
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
                                                            :min="product.min_order || 1"
                                                            class="w-20 rounded-lg border border-gray-300 px-2 py-1 text-center text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                            @input="normalizeQuantity(product)"
                                                        />
                                                        <span class="text-xs text-gray-500">{{ product.unit_value }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm font-semibold whitespace-nowrap text-gray-900">
                                                    {{ formatDecimal(Number(product.active_price || 0) * Number(product.input_order || 0)) }} €
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <button
                                                        type="button"
                                                        @click="removeProduct(product)"
                                                        class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                                                        title="Odstrániť"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </td>
                                            </tr>
                                            <tr v-if="!orderProducts.length">
                                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-400">
                                                    Objednávka zatiaľ nemá položky.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Zákazník -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Zákazník</h2>
                            </div>
                            <div class="px-6 py-5">

                                <!-- Vyhľadávanie zákazníka (len super-admin) -->
                                <div v-if="isSuperAdmin" class="mb-5 relative">
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">Vyhľadať zákazníka</label>
                                    <div class="relative">
                                        <input
                                            v-model="customerSearch"
                                            type="text"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 pr-8 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            placeholder="Hľadajte podľa firmy, mesta, IČO, emailu…"
                                            @input="onCustomerSearchInput"
                                            @blur="() => { setTimeout(() => { showCustomerDropdown = false; }, 150); }"
                                            @focus="showCustomerDropdown = customerSearchResults.length > 0"
                                        />
                                        <svg v-if="customerSearchLoading" class="absolute right-2.5 top-2.5 h-4 w-4 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                    </div>
                                    <div
                                        v-if="showCustomerDropdown && customerSearchResults.length"
                                        class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg"
                                    >
                                        <button
                                            v-for="c in customerSearchResults"
                                            :key="c.id"
                                            type="button"
                                            class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-blue-50"
                                            @mousedown.prevent="selectCustomerFromSearch(c)"
                                        >
                                            <span class="font-semibold text-gray-900">{{ c.company || c.name }}</span>
                                            <span class="text-xs text-gray-400">{{ c.city }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Kontakty zákazníka -->
                                <div v-if="getCustomer?.users?.length" class="mb-5">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                                        Kontakt{{ getCustomer.users.length > 1 ? ' — vyberte osobu' : '' }}
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="user in getCustomer.users"
                                            :key="user.id"
                                            type="button"
                                            @click="applyUser(user)"
                                            :class="[
                                                'flex flex-col rounded-lg border px-3 py-2 text-left text-sm transition',
                                                selectedUserId === user.id
                                                    ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                                                    : 'border-gray-200 bg-white hover:border-blue-300 hover:bg-blue-50'
                                            ]"
                                        >
                                            <span class="font-semibold text-gray-900">
                                                {{ [user.firstName, user.lastName].filter(Boolean).join(' ') || user.username }}
                                            </span>
                                            <span class="text-xs text-gray-500">{{ user.email }}</span>
                                            <span v-if="user.phone" class="text-xs text-gray-400">{{ user.phone }}</span>
                                        </button>
                                    </div>
                                </div>

                                <CustomerFormFields
                                    :fieldErrors="getFieldErrors"
                                    :highlightRequired="highlightMissingRequired"
                                    :requiredFields="requiredCustomerFields"
                                />
                                <div class="mt-4">
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">Poznámka k objednávke</label>
                                    <input
                                        v-model="orderState.order.note"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        placeholder="Poznámka"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pravý stĺpec: zhrnutie + doprava + platba + odoslať -->
                    <aside class="space-y-4">
                        <div class="sticky top-4 space-y-4">

                            <!-- Zoznam produktov v súhrne -->
                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                                    <h2 class="text-sm font-semibold text-gray-700">
                                        Produkty ({{ orderProducts.length }})
                                    </h2>
                                </div>
                                <div class="divide-y divide-gray-50 px-5 py-3">
                                    <div
                                        v-for="product in orderProducts"
                                        :key="product.id"
                                        class="flex items-start justify-between gap-2 py-1.5 text-sm"
                                    >
                                        <span class="text-gray-600 leading-snug">
                                            {{ product.name }}
                                            <span class="text-gray-400">× {{ product.input_order }}</span>
                                        </span>
                                        <span class="shrink-0 font-medium text-gray-900">
                                            {{ formatDecimal(Number(product.active_price || 0) * Number(product.input_order || 0)) }} €
                                        </span>
                                    </div>
                                    <div v-if="!orderProducts.length" class="py-3 text-sm text-gray-400 text-center">
                                        Žiadne položky
                                    </div>
                                </div>
                            </div>

                            <!-- Doprava + platba + rekapitulácia + tlačidlo -->
                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                                    <h2 class="text-sm font-semibold text-gray-700">Doprava a platba</h2>
                                </div>
                                <div class="px-5 py-4 space-y-5">

                                    <!-- Doprava -->
                                    <div v-if="shippingMethods.length">
                                        <h3 class="mb-2 text-sm font-semibold text-gray-700">Spôsob dopravy</h3>
                                        <div class="space-y-2">
                                            <label
                                                v-for="method in shippingMethods"
                                                :key="method.id"
                                                :class="[
                                                    'flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 transition',
                                                    selectedShippingId === method.id
                                                        ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                                                        : 'border-gray-200 bg-white hover:border-gray-300'
                                                ]"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <input
                                                        type="radio"
                                                        :value="method.id"
                                                        v-model="selectedShippingId"
                                                        class="accent-blue-600"
                                                    />
                                                    <span class="text-sm font-medium text-gray-800">{{ method.name }}</span>
                                                </div>
                                                <span class="text-sm font-semibold text-gray-900">
                                                    <template v-if="method.free_from_price !== null && grandTotal >= parseFloat(method.free_from_price)">
                                                        <span class="text-green-600">Zdarma</span>
                                                    </template>
                                                    <template v-else>
                                                        {{ method.price > 0 ? `${formatDecimal(method.price)} €` : 'Zdarma' }}
                                                    </template>
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Platba -->
                                    <div v-if="paymentMethods.length">
                                        <h3 class="mb-2 text-sm font-semibold text-gray-700">Spôsob platby</h3>
                                        <div class="space-y-2">
                                            <label
                                                v-for="method in paymentMethods"
                                                :key="method.id"
                                                :class="[
                                                    'flex cursor-pointer items-center justify-between rounded-lg border px-4 py-3 transition',
                                                    selectedPaymentId === method.id
                                                        ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500'
                                                        : 'border-gray-200 bg-white hover:border-gray-300'
                                                ]"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <input
                                                        type="radio"
                                                        :value="method.id"
                                                        v-model="selectedPaymentId"
                                                        class="accent-blue-600"
                                                    />
                                                    <span class="text-sm font-medium text-gray-800">{{ method.name }}</span>
                                                </div>
                                                <span class="text-sm font-semibold" :class="method.fee > 0 ? 'text-gray-700' : 'text-green-600'">
                                                    {{ method.fee > 0 ? `+ ${formatDecimal(method.fee)} €` : 'Zdarma' }}
                                                </span>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- Rekapitulácia -->
                                    <div class="rounded-lg border border-gray-100 bg-gray-50 px-4 py-4 space-y-2 text-sm">
                                        <div class="flex justify-between text-gray-600">
                                            <span>Produkty ({{ grandQuantity }} ks)</span>
                                            <span class="font-medium text-gray-800">{{ formatDecimal(grandTotal) }} €</span>
                                        </div>
                                        <div v-if="selectedShipping" class="flex justify-between text-gray-600">
                                            <span>Doprava ({{ selectedShipping.name }})</span>
                                            <span class="font-medium" :class="shippingPrice === 0 ? 'text-green-600' : 'text-gray-800'">
                                                {{ shippingPrice === 0 ? 'Zdarma' : `${formatDecimal(shippingPrice)} €` }}
                                            </span>
                                        </div>
                                        <div v-if="paymentFee > 0" class="flex justify-between text-gray-600">
                                            <span>Poplatok za platbu</span>
                                            <span class="font-medium text-gray-800">{{ formatDecimal(paymentFee) }} €</span>
                                        </div>
                                        <div class="border-t border-gray-200 pt-2 flex justify-between">
                                            <span class="font-semibold text-gray-900">Celkom</span>
                                            <span class="text-lg font-bold text-blue-700">{{ formatDecimal(grandTotalWithExtras) }} €</span>
                                        </div>
                                    </div>

                                    <!-- Tlačidlo uložiť -->
                                    <button
                                        type="button"
                                        @click="openSaveModal"
                                        :disabled="isSubmitting || !orderProducts.length"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-400"
                                    >
                                        <svg v-if="isSubmitting" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        {{ isSubmitting ? 'Ukladám...' : 'Uložiť objednávku' }}
                                    </button>
                                </div>
                            </div>

                        </div>
                    </aside>
                </div>
            </div>

            <Teleport to="body">
                <div v-if="showSaveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
                    <div class="w-full max-w-sm rounded bg-white p-5 shadow-lg">
                        <h3 class="mb-3 text-lg font-semibold text-gray-800">Uložiť objednávku</h3>
                        <p class="mb-4 text-sm text-gray-600">
                            Vytvoriť objednávku pre {{ getCustomer.company || getCustomer.name }}?
                        </p>
                        <label class="mb-5 flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="notifyCustomer" type="checkbox" class="rounded" />
                            Poslať notifikáciu používateľovi
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700" @click="showSaveModal = false">
                                Zrušiť
                            </button>
                            <button type="button" @click="confirmSave()"
                                :disabled="loadingStore.isLoading"
                                class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:bg-blue-400 disabled:cursor-not-allowed">
                                <SpinnerButton v-if="loadingStore.isLoading" />
                                Potvrdiť
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </template>
    </BaseLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonLink from "../layout/page/ButtonLink.vue";
import OrderCustomerCard from "./component/OrderCustomerCard.vue";
import useOrders from "../../store/StoreOrders";
import useShippings from "../../store/StoreShippings";
import useOrderProducts from "../../store/StoreOrderProducts";
import useReturns from "../../store/StoreReturns";
import SpinnerButton from "../icons/spinnerButton.vue";
import loadingStore from "../../store/StoreLoading";
import axiosInstance from "../../axiosInstance";
import useErrors from "../../store/StoreErrors";

const { getOrder, fetchOrder, customer } = useOrders();
const { storeShipping } = useShippings();
const { updateOrderProducts } = useOrderProducts();
const { fetchReturns, getReturns } = useReturns();
const { setErrors } = useErrors();

const router = useRouter();
const { params: { orderId } } = useRoute();

const notifyCustomer = ref(true);
const shippingItems = ref({});
const excludedItemIds = ref([]);
const showCheckoutModal = ref(false);
const stornoEdits = reactive({});
const stornoSaving = reactive({});

const buttonBack = { name: 'Späť', spinner: true, link: '/objednavky/' + orderId + '/show', icon: 'arrow-left' };

const orderProducts = computed(() => getOrder.value?.orderProducts ?? []);
const shippingPercentage = computed(() => Number(getOrder.value?.shipping_percentage ?? 0));
const remainingQuantity = computed(() => Number(getOrder.value?.shipping_remaining_quantity ?? 0));
const shippedQuantity = computed(() => Number(getOrder.value?.stock_expedition ?? 0));
const requiredQuantity = computed(() => Number(getOrder.value?.shipping_required_quantity ?? 0));
const statusLabel = computed(() => getOrder.value?.shipping_status_label ?? (getOrder.value?.isFinished ? "Vybavená" : "Nevybavená"));

// All products enriched — no filtering
const allProducts = computed(() => orderProducts.value.map((item) => ({
    ...item,
    shipped:        Number(item.stockSum ?? 0),
    stornoQuantity: Number(item.storno ?? 0),
    required:       Number(item.shipping_required_quantity ?? Math.max(0, item.quantity - (item.storno ?? 0))),
    remaining:      Number(item.shipping_remaining_quantity ?? Math.max(0, item.quantity - (item.storno ?? 0) - Number(item.stockSum ?? 0))),
    maxStorno:      Math.max(0, item.quantity - Number(item.stockSum ?? 0)),
})));

// Only items with remaining > 0 can be added to a delivery note
const shippableProducts = computed(() => allProducts.value
    .filter((item) => item.remaining > 0 && !excludedItemIds.value.includes(item.id)));

const selectedQuantity = computed(() => Object.values(shippingItems.value)
    .reduce((sum, q) => sum + Number(q || 0), 0));

const remainingAfterShipping = computed(() => Math.max(0, remainingQuantity.value - selectedQuantity.value));
const canConfirmShipping = computed(() => selectedQuantity.value > 0 && !getOrder.value?.isFinished);

const shippedRows = computed(() => (getOrder.value?.shippings ?? []).flatMap((shipping) => {
    const stocks = shipping.stocks?.length ? shipping.stocks : [];
    if (!stocks.length) return [{ shipping, stock: null }];
    return stocks.map((stock) => ({ shipping, stock }));
}));

const resetShippingItems = (fillRemaining = true) => {
    if (fillRemaining) excludedItemIds.value = [];
    shippingItems.value = allProducts.value.reduce((items, item) => {
        items[item.id] = fillRemaining && item.remaining > 0 && !excludedItemIds.value.includes(item.id)
            ? item.remaining : 0;
        return items;
    }, {});
};

const normalizeQuantity = (item) => {
    const value = Number(shippingItems.value[item.id] || 0);
    shippingItems.value[item.id] = Math.min(item.remaining, Math.max(0, Math.floor(value)));
};

const isExcluded = (item) => excludedItemIds.value.includes(item.id);

const toggleExcludeItem = (item) => {
    if (isExcluded(item)) {
        excludedItemIds.value = excludedItemIds.value.filter(id => id !== item.id);
        shippingItems.value[item.id] = item.remaining;
    } else {
        shippingItems.value[item.id] = 0;
        excludedItemIds.value = [...excludedItemIds.value, item.id];
    }
};

const openCheckoutModal = () => {
    if (!canConfirmShipping.value) return alert("Zadajte aspoň jednu položku na expedovanie.");
    notifyCustomer.value = true;
    showCheckoutModal.value = true;
};

const closeCheckoutModal = () => { showCheckoutModal.value = false; };

const confirmShipping = async () => {
    await storeShipping(getOrder.value, {
        notify_customer: notifyCustomer.value,
        items: allProducts.value.map((item) => ({
            order_product_id: item.id,
            quantity: excludedItemIds.value.includes(item.id) ? 0 : Number(shippingItems.value[item.id] || 0),
        })),
    });
    closeCheckoutModal();
    router.push({ name: "orders.show", params: { orderId } });
};

// Storno helpers
const initStornoEdits = () => {
    allProducts.value.forEach(item => {
        if (!(item.id in stornoEdits)) {
            stornoEdits[item.id] = item.stornoQuantity;
        }
    });
};

const stornoRemaining = (item) => {
    stornoEdits[item.id] = item.maxStorno;
};

const saveStorno = async (item) => {
    const newStorno = Number(stornoEdits[item.id] ?? 0);
    if (newStorno === item.stornoQuantity) return;
    stornoSaving[item.id] = true;
    try {
        await axiosInstance.put(item.endpoints.update, { ...item, storno: newStorno });
        await fetchOrder(orderId);
    } catch (e) {
        setErrors(e);
    } finally {
        stornoSaving[item.id] = false;
    }
};

onMounted(async () => {
    await Promise.all([fetchOrder(orderId), fetchReturns(orderId)]);
    resetShippingItems(true);
    initStornoEdits();
});

watch(allProducts, () => {
    resetShippingItems(true);
    initStornoEdits();
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <h1 class="page-heading">
                Expedícia objednávky
                <buttonLink :item="buttonBack" class="text-sm" />
            </h1>

            <div class="page-body col-span-12">

                <!-- Poznámka zákazníka -->
                <div v-if="getOrder.note || getOrder.wants_coupon"
                     class="mb-4 flex items-start gap-3 rounded-lg border-2 border-amber-400 bg-amber-50 px-5 py-4 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div class="space-y-1">
                        <div class="text-sm font-bold uppercase tracking-wide text-amber-700">Poznámka zákazníka</div>
                        <div v-if="getOrder.note" class="text-base text-amber-900">{{ getOrder.note }}</div>
                        <div v-if="getOrder.wants_coupon" class="text-sm font-semibold text-amber-700">
                            Zákazník chce získať zľavový kupón na ďalší nákup
                        </div>
                    </div>
                </div>

                <!-- Zákazník + objednávka -->
                <div class="mb-4">
                    <OrderCustomerCard :customer="customer" :user="getOrder.user" :order="getOrder" />
                </div>

                <!-- Súhrn expedície + nový dodací list -->
                <div class="mb-4 grid gap-4 lg:grid-cols-3">
                    <div class="border-2 border-gray-300 bg-white p-4 shadow lg:col-span-2">
                        <div class="grid gap-3 sm:grid-cols-4">
                            <div class="rounded border border-gray-200 bg-gray-50 p-3">
                                <div class="text-xs uppercase text-gray-500">Stav</div>
                                <div class="font-semibold text-gray-900">{{ statusLabel }}</div>
                            </div>
                            <div class="rounded border border-gray-200 bg-gray-50 p-3 text-center">
                                <div class="text-xs uppercase text-gray-500">Vybavené</div>
                                <div class="font-semibold text-gray-900">{{ shippingPercentage }} %</div>
                            </div>
                            <div class="rounded border border-gray-200 bg-gray-50 p-3 text-center">
                                <div class="text-xs uppercase text-gray-500">Expedované</div>
                                <div class="font-semibold text-gray-900">{{ shippedQuantity }}/{{ requiredQuantity }} ks</div>
                            </div>
                            <div class="rounded border border-gray-200 bg-gray-50 p-3 text-center">
                                <div class="text-xs uppercase text-gray-500">Zostáva</div>
                                <div class="font-semibold text-gray-900">{{ remainingQuantity }} ks</div>
                            </div>
                        </div>

                        <div class="mt-4 h-3 overflow-hidden rounded-full bg-gray-200">
                            <div class="h-full rounded-full transition-all"
                                :class="getOrder.isFinished ? 'bg-green-600' : shippedQuantity > 0 ? 'bg-amber-500' : 'bg-red-500'"
                                :style="{ width: `${shippingPercentage}%` }"></div>
                        </div>
                    </div>

                    <div class="border-2 border-gray-300 bg-white p-4 shadow">
                        <div class="mb-3 text-sm font-semibold text-gray-900">Nový dodací list</div>
                        <div class="mb-2 flex justify-between gap-2 text-sm">
                            <span class="text-gray-500">Teraz expedovať</span>
                            <span class="font-semibold">{{ selectedQuantity }} ks</span>
                        </div>
                        <div class="mb-4 flex justify-between gap-2 text-sm">
                            <span class="text-gray-500">Po expedícii ostane</span>
                            <span class="font-semibold">{{ remainingAfterShipping }} ks</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="resetShippingItems(false)"
                                class="rounded bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200">
                                Vyčistiť
                            </button>
                            <button type="button" @click="resetShippingItems(true)"
                                class="rounded bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">
                                Všetko zostávajúce
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Hlavná tabuľka — VŠETKY produkty -->
                <div class="mb-4 overflow-x-auto border-2 border-gray-400 bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th text-left">Produkt</th>
                                <th class="thead_th text-center">Objednané</th>
                                <th class="thead_th text-center">Expedované</th>
                                <th class="thead_th text-center">Ostáva</th>
                                <th class="thead_th text-center">Storno</th>
                                <th class="thead_th text-center">Teraz expedovať</th>
                                <th class="thead_th text-center">Panel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in allProducts" :key="item.id"
                                class="transition"
                                :class="isExcluded(item)
                                    ? 'bg-gray-100 opacity-60'
                                    : item.remaining === 0 && item.stornoQuantity === 0
                                        ? 'bg-green-50 opacity-70'
                                        : item.stornoQuantity > 0 && item.remaining === 0
                                            ? 'bg-gray-100 opacity-60'
                                            : 'hover:bg-gray-50'">

                                <td class="tbody_td">
                                    <div class="flex items-center gap-3">
                                        <img v-if="item.thumb" :src="item.thumb" :alt="item.name"
                                            class="h-10 w-10 rounded border border-gray-200 object-cover" />
                                        <div>
                                            <div class="font-semibold text-gray-900">{{ item.name }}</div>
                                            <div class="text-xs text-gray-500">{{ item.unit_value }}</div>
                                        </div>
                                    </div>
                                </td>

                                <td class="tbody_td text-center">{{ item.quantity }}</td>

                                <td class="tbody_td text-center">
                                    <span :class="item.shipped > 0 ? 'font-semibold text-green-700' : 'text-gray-400'">
                                        {{ item.shipped }}
                                    </span>
                                </td>

                                <td class="tbody_td text-center">
                                    <span :class="item.remaining > 0 ? 'font-semibold text-amber-700' : 'text-gray-400'">
                                        {{ item.remaining }}
                                    </span>
                                </td>

                                <!-- Storno column -->
                                <td class="tbody_td text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <input
                                            v-model.number="stornoEdits[item.id]"
                                            type="number"
                                            min="0"
                                            :max="item.maxStorno"
                                            :disabled="item.maxStorno === 0"
                                            class="w-16 rounded border border-gray-300 px-1 py-1 text-center text-sm disabled:bg-gray-100 disabled:text-gray-400"
                                        />
                                        <div class="flex flex-col gap-1">
                                            <!-- Storno zvyšok -->
                                            <button v-if="item.maxStorno > 0 && stornoEdits[item.id] !== item.maxStorno"
                                                type="button"
                                                @click="stornoRemaining(item)"
                                                class="rounded bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-700 hover:bg-orange-100"
                                                title="Stornovať celý zostatok">
                                                Všetko
                                            </button>
                                            <!-- Uložiť -->
                                            <button v-if="stornoEdits[item.id] !== item.stornoQuantity"
                                                type="button"
                                                @click="saveStorno(item)"
                                                :disabled="stornoSaving[item.id]"
                                                class="rounded bg-blue-600 px-2 py-0.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                                                {{ stornoSaving[item.id] ? '...' : 'Uložiť' }}
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="item.stornoQuantity > 0" class="mt-0.5 text-xs text-gray-400">
                                        uložené: {{ item.stornoQuantity }}
                                    </div>
                                </td>

                                <!-- Teraz expedovať -->
                                <td class="tbody_td text-center">
                                    <input
                                        v-if="item.remaining > 0"
                                        v-model.number="shippingItems[item.id]"
                                        type="number" min="0" :max="item.remaining"
                                        :disabled="isExcluded(item)"
                                        class="w-24 rounded border px-2 py-1 text-center transition"
                                        :class="isExcluded(item)
                                            ? 'border-gray-200 bg-gray-100 text-gray-400 line-through'
                                            : 'border-gray-300'"
                                        @input="normalizeQuantity(item)"
                                    />
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>

                                <td class="tbody_td text-center">
                                    <button v-if="item.remaining > 0"
                                        type="button" @click="toggleExcludeItem(item)"
                                        class="rounded px-3 py-1 text-xs font-semibold transition"
                                        :class="isExcluded(item)
                                            ? 'bg-blue-50 text-blue-700 hover:bg-blue-100'
                                            : 'bg-orange-50 text-orange-700 hover:bg-orange-100'">
                                        {{ isExcluded(item) ? 'Expedovať' : 'Neexpedovať' }}
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="!allProducts.length">
                                <td colspan="7" class="tbody_td py-8 text-center text-gray-500">
                                    Táto objednávka nemá žiadne položky.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Expedované dodacie listy -->
                <div class="mb-5 overflow-x-auto border-2 border-gray-300 bg-white shadow">
                    <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold text-gray-900">
                        Už expedované dodacie listy k objednávke
                    </div>
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th text-center">Dodací list</th>
                                <th class="thead_th text-center">Dátum</th>
                                <th class="thead_th text-left">Produkt</th>
                                <th class="thead_th text-center">Počet</th>
                                <th class="thead_th text-center">Notifikácia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="row in shippedRows" :key="`${row.shipping.id}-${row.stock?.id ?? 'empty'}`" class="hover:bg-gray-50">
                                <td class="tbody_td text-center font-semibold">DL #{{ row.shipping.id }}</td>
                                <td class="tbody_td text-center">{{ row.shipping.created_at }}</td>
                                <td class="tbody_td">{{ row.stock?.name ?? '-' }}</td>
                                <td class="tbody_td text-center font-semibold">{{ row.stock?.quantity ?? 0 }}</td>
                                <td class="tbody_td text-center">
                                    <span v-if="row.shipping.notices?.length"
                                        class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">
                                        Odoslaná
                                    </span>
                                    <span v-else
                                        class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                        Bez emailu
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!shippedRows.length">
                                <td colspan="5" class="tbody_td py-6 text-center text-gray-500">
                                    K objednávke zatiaľ nie je vytvorený žiadny dodací list.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Vrátenia tovaru -->
                <div class="mb-5 overflow-x-auto border-2 bg-white shadow"
                    :class="getReturns.length ? 'border-orange-300' : 'border-gray-300'">
                    <div class="border-b border-gray-200 px-4 py-3 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-900">
                            Vrátenia tovaru
                            <span v-if="getReturns.length"
                                class="ml-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700">
                                {{ getReturns.length }}
                            </span>
                        </span>
                        <router-link v-if="shippedQuantity > 0"
                            :to="{ name: 'orders.returns.create', params: { orderId } }"
                            class="text-sm font-semibold text-red-600 hover:text-red-800">
                            + Nové vrátenie
                        </router-link>
                    </div>

                    <div v-if="!getReturns.length" class="px-4 py-5 text-center text-sm text-gray-400">
                        K tejto objednávke zatiaľ nie je zaznamenané žiadne vrátenie.
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th text-center">Vrátenie</th>
                                <th class="thead_th text-center">Dátum</th>
                                <th class="thead_th text-left">Dôvod</th>
                                <th class="thead_th text-left">Položky</th>
                                <th class="thead_th text-center">Stav</th>
                                <th class="thead_th text-center"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="ret in getReturns" :key="ret.id" class="hover:bg-orange-50">
                                <td class="tbody_td text-center font-semibold text-gray-700">#{{ ret.id }}</td>
                                <td class="tbody_td text-center text-sm text-gray-500">{{ ret.created_at }}</td>
                                <td class="tbody_td text-sm text-gray-900">{{ ret.reason_label }}</td>
                                <td class="tbody_td">
                                    <div v-for="item in ret.items" :key="item.id" class="text-sm text-gray-700">
                                        {{ item.product_name }}
                                        <span class="font-semibold">× {{ item.quantity }}</span>
                                    </div>
                                </td>
                                <td class="tbody_td text-center">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="{
                                            'bg-amber-100 text-amber-800': ret.status === 'pending',
                                            'bg-green-100 text-green-800': ret.status === 'processed',
                                            'bg-gray-100 text-gray-500': ret.status === 'cancelled',
                                        }">
                                        {{ { pending: 'Čaká na spracovanie', processed: 'Spracované — tovar na sklade', cancelled: 'Zrušené' }[ret.status] ?? ret.status }}
                                    </span>
                                </td>
                                <td class="tbody_td text-center">
                                    <router-link
                                        :to="{ name: 'orders.returns.show', params: { orderId, returnId: ret.id } }"
                                        class="rounded bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200">
                                        Detail
                                    </router-link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap justify-between gap-2">
                    <buttonLink :item="buttonBack" />
                    <button type="button" @click="openCheckoutModal" :disabled="!canConfirmShipping"
                        class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-gray-400">
                        Vytvoriť dodací list
                    </button>
                </div>
            </div>

            <!-- Modal: Vytvoriť dodací list -->
            <Teleport to="body">
                <div v-if="showCheckoutModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
                    <div class="w-full max-w-sm rounded bg-white p-5 shadow-lg">
                        <h3 class="mb-3 text-lg font-semibold text-gray-800">Vytvoriť dodací list</h3>
                        <p class="mb-4 text-sm text-gray-600">
                            Expedovať {{ selectedQuantity }} ks?
                        </p>
                        <label class="mb-5 flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" v-model="notifyCustomer" class="rounded" />
                            Informovať zákazníka o expedícii
                        </label>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="closeCheckoutModal"
                                class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                                Zrušiť
                            </button>
                            <button type="button" @click="confirmShipping"
                                :disabled="loadingStore.isLoading"
                                class="inline-flex items-center gap-2 rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed">
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

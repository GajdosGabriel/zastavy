<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonLink from "../layout/page/ButtonLink.vue";
import useOrders from "../../store/StoreOrders";
import useShippings from "../../store/StoreShippings";
import SpinnerButton from "../icons/spinnerButton.vue";
import loadingStore from "../../store/StoreLoading";

const {
    getOrder,
    fetchOrder,
    customer,
} = useOrders();

const { storeShipping } = useShippings();

const router = useRouter();
const {
    params: { orderId },
} = useRoute();

const notifyCustomer = ref(true);
const shippingItems = ref({});
const excludedItemIds = ref([]);
const showCheckoutModal = ref(false);

const buttonBack = { name: 'Späť', spinner: true, link: '/objednavky/' + orderId + '/show', icon: 'arrow-left' };

const orderProducts = computed(() => getOrder.value?.orderProducts ?? []);
const shippingPercentage = computed(() => Number(getOrder.value?.shipping_percentage ?? 0));
const remainingQuantity = computed(() => Number(getOrder.value?.shipping_remaining_quantity ?? 0));
const shippedQuantity = computed(() => Number(getOrder.value?.stock_expedition ?? 0));
const requiredQuantity = computed(() => Number(getOrder.value?.shipping_required_quantity ?? 0));
const statusLabel = computed(() => getOrder.value?.shipping_status_label ?? (getOrder.value?.isFinished ? "Vybavená" : "Nevybavená"));

const availableProducts = computed(() => orderProducts.value
    .map((item) => ({
        ...item,
        shipped: Number(item.stockSum ?? 0),
        stornoQuantity: Number(item.storno ?? 0),
        required: Number(item.shipping_required_quantity ?? Math.max(0, item.quantity - item.storno)),
        remaining: Number(item.shipping_remaining_quantity ?? Math.max(0, item.quantity - item.storno - item.stockSum)),
    }))
    .filter((item) => item.remaining > 0));

const shippableProducts = computed(() => availableProducts.value
    .filter((item) => !excludedItemIds.value.includes(item.id)));

const selectedQuantity = computed(() => Object.values(shippingItems.value)
    .reduce((sum, quantity) => sum + Number(quantity || 0), 0));

const remainingAfterShipping = computed(() => Math.max(0, remainingQuantity.value - selectedQuantity.value));
const canConfirmShipping = computed(() => selectedQuantity.value > 0 && !getOrder.value?.isFinished);

const shippedRows = computed(() => (getOrder.value?.shippings ?? []).flatMap((shipping) => {
    const stocks = shipping.stocks?.length ? shipping.stocks : [];

    if (!stocks.length) {
        return [{
            shipping,
            stock: null,
        }];
    }

    return stocks.map((stock) => ({
        shipping,
        stock,
    }));
}));

const resetShippingItems = (fillRemaining = true) => {
    if (fillRemaining) {
        excludedItemIds.value = [];
    }

    shippingItems.value = availableProducts.value.reduce((items, item) => {
        items[item.id] = fillRemaining && !excludedItemIds.value.includes(item.id) ? item.remaining : 0;
        return items;
    }, {});
};

const normalizeQuantity = (item) => {
    const value = Number(shippingItems.value[item.id] || 0);
    shippingItems.value[item.id] = Math.min(item.remaining, Math.max(0, Math.floor(value)));
};

const removeShippingItem = (item) => {
    shippingItems.value[item.id] = 0;

    if (!excludedItemIds.value.includes(item.id)) {
        excludedItemIds.value = [...excludedItemIds.value, item.id];
    }
};

const openCheckoutModal = () => {
    if (!canConfirmShipping.value) {
        return alert("Zadajte aspoň jednu položku na expedovanie.");
    }

    notifyCustomer.value = true;
    showCheckoutModal.value = true;
};

const closeCheckoutModal = () => {
    showCheckoutModal.value = false;
};

const confirmShipping = async () => {
    await storeShipping(getOrder.value, {
        notify_customer: notifyCustomer.value,
        items: availableProducts.value.map((item) => ({
            order_product_id: item.id,
            quantity: excludedItemIds.value.includes(item.id) ? 0 : Number(shippingItems.value[item.id] || 0),
        })),
    });

    closeCheckoutModal();
    router.push({ name: "orders.show", params: { orderId } });
};

onMounted(async () => {
    await fetchOrder(orderId);
    resetShippingItems(true);
});

watch(availableProducts, () => resetShippingItems(true));
</script>

<template>
    <BaseLayout>
        <template #main>
            <h1 class="page-heading">
                Expedícia objednávky
                <buttonLink :item="buttonBack" class="text-sm" />
            </h1>

            <div class="page-body col-span-12">
                <div class="mb-4 grid gap-4 lg:grid-cols-3">
                    <div class="border-2 border-gray-300 bg-white p-4 shadow lg:col-span-2">
                        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="text-sm text-gray-500">Objednávka</div>
                                <div class="text-xl font-semibold text-gray-900">{{ getOrder.serial_number }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500">Zákazník</div>
                                <div class="font-semibold text-gray-900">{{ customer.company || customer.name }}</div>
                                <div class="text-sm text-gray-600">{{ customer.city }}</div>
                            </div>
                        </div>

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
                                <div class="text-xs uppercase text-gray-500">Dodané</div>
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

                <div class="mb-4 overflow-x-auto border-2 border-gray-400 bg-white shadow">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="thead">
                            <tr>
                                <th class="thead_th text-left">Produkt</th>
                                <th class="thead_th text-center">Objednané</th>
                                <th class="thead_th text-center">Storno</th>
                                <th class="thead_th text-center">Expedované</th>
                                <th class="thead_th text-center">Ostáva</th>
                                <th class="thead_th text-center">Teraz expedovať</th>
                                <th class="thead_th text-center">Panel</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="item in shippableProducts" :key="item.id" class="hover:bg-gray-50">
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
                                <td class="tbody_td text-center">{{ item.stornoQuantity }}</td>
                                <td class="tbody_td text-center">{{ item.shipped }}</td>
                                <td class="tbody_td text-center font-semibold">{{ item.remaining }}</td>
                                <td class="tbody_td text-center">
                                    <input v-model.number="shippingItems[item.id]" type="number" min="0" :max="item.remaining"
                                        class="w-24 rounded border border-gray-300 px-2 py-1 text-center"
                                        @input="normalizeQuantity(item)" />
                                </td>
                                <td class="tbody_td text-center">
                                    <button type="button" @click="removeShippingItem(item)"
                                        class="rounded bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 hover:bg-red-100">
                                        Vymazať
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!shippableProducts.length">
                                <td colspan="7" class="tbody_td py-8 text-center text-gray-500">
                                    Táto objednávka nemá žiadne položky na expedovanie.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

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
                                    <span v-if="row.shipping.notices?.length" class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">
                                        Odoslaná
                                    </span>
                                    <span v-else class="rounded bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
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

                <div class="flex flex-wrap justify-between gap-2">
                    <buttonLink :item="buttonBack" />
                    <button type="button" @click="openCheckoutModal" :disabled="!canConfirmShipping"
                        class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-gray-400">
                        Vytvoriť dodací list
                    </button>
                </div>
            </div>

            <Teleport to="body">
                <div v-if="showCheckoutModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
                    <div class="w-full max-w-sm rounded bg-white p-5 shadow-lg">
                        <h3 class="mb-3 text-lg font-semibold text-gray-800">
                            Checkout
                        </h3>
                        <p class="mb-4 text-sm text-gray-600">
                            Vytvoriť dodací list pre {{ selectedQuantity }} ks?
                        </p>
                        <label class="mb-5 flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" v-model="notifyCustomer" class="rounded" />
                            Informovať customera o expedícii
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

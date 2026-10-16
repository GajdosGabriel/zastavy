<script setup>
import { computed, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { formatPrice } from "../../../models/functions";
import useOrderProducts from "../../../store/StoreOrderProducts";
import productTableRow from "../../orderProducts/productTableRow.vue";
import CustomOrderItem from "../../forms/CustomOrderItem.vue";

// Jedna tabuľka položiek objednávky v troch režimoch:
//  - view:    prehľad s expedíciou (objednané/storno, expedované, ostáva, ceny),
//  - prepare: výber kusov do nového dodacieho listu (lišta s akciou je prilepená dole),
//  - edit:    úprava položiek — množstvo, cena, storno, pridanie a zmazanie.
const props = defineProps({
    order: { type: Object, required: true },
    mode: { type: String, default: "view" },
});
const emit = defineEmits(["update:mode", "prepare", "edited"]);

const orderProductsStore = useOrderProducts();
const { getOrderProducts } = storeToRefs(orderProductsStore);
const { addOrderProduct } = orderProductsStore;

const items = computed(() => (props.order?.orderProducts ?? []).map((item) => ({
    ...item,
    shipped: Number(item.stockSum ?? 0),
    stornoQuantity: Number(item.storno ?? 0),
    remaining: Number(item.shipping_remaining_quantity ?? Math.max(0, item.quantity - (item.storno ?? 0) - Number(item.stockSum ?? 0))),
    unitPrice: Number(item.price ?? 0),
    lineTotal: Number(item.total ?? Number(item.price ?? 0) * Number(item.quantity ?? 0)),
})));

const permissions = computed(() => props.order?.permissions ?? {});
const pendingShippings = computed(() => (props.order?.shippings ?? []).filter((s) => s.is_preparing));
const remainingQuantity = computed(() => Number(props.order?.shipping_remaining_quantity ?? 0));
const shippedQuantity = computed(() => Number(props.order?.stock_expedition ?? 0));
const requiredQuantity = computed(() => Number(props.order?.shipping_required_quantity ?? 0));
const shippingPercentage = computed(() => Number(props.order?.shipping_percentage ?? 0));

// Nový DL sa dá vystaviť, len keď nič nečaká na odoslanie a je čo expedovať.
const canPrepare = computed(() => permissions.value.ship?.allowed && !pendingShippings.value.length
    && remainingQuantity.value > 0 && !props.order?.isFinished && !props.order?.isStorned);
const canEdit = computed(() => !!permissions.value.manageItems?.allowed);

// --- Príprava dodacieho listu ---
const shippingItems = ref({});
const excludedItemIds = ref([]);
const isExcluded = (item) => excludedItemIds.value.includes(item.id);

const resetShippingItems = (fillRemaining = true) => {
    if (fillRemaining) excludedItemIds.value = [];
    shippingItems.value = items.value.reduce((acc, item) => {
        acc[item.id] = fillRemaining && item.remaining > 0 && !isExcluded(item) ? item.remaining : 0;
        return acc;
    }, {});
};

const normalizeQuantity = (item) => {
    const value = Number(shippingItems.value[item.id] || 0);
    shippingItems.value[item.id] = Math.min(item.remaining, Math.max(0, Math.floor(value)));
};

const toggleExcludeItem = (item) => {
    if (isExcluded(item)) {
        excludedItemIds.value = excludedItemIds.value.filter((id) => id !== item.id);
        shippingItems.value[item.id] = item.remaining;
    } else {
        shippingItems.value[item.id] = 0;
        excludedItemIds.value = [...excludedItemIds.value, item.id];
    }
};

const selectedQuantity = computed(() => Object.values(shippingItems.value).reduce((sum, q) => sum + Number(q || 0), 0));
const remainingAfterShipping = computed(() => Math.max(0, remainingQuantity.value - selectedQuantity.value));
const selection = () => items.value.map((item) => ({
    order_product_id: item.id,
    quantity: isExcluded(item) ? 0 : Number(shippingItems.value[item.id] || 0),
}));

watch(items, () => resetShippingItems(true), { immediate: true });
// Režim prípravy nemá zmysel, keď DL už nemožno vystaviť (napr. práve vznikol).
watch(canPrepare, (allowed) => { if (!allowed && props.mode === "prepare") emit("update:mode", "view"); });

const setMode = (mode) => {
    if (mode === "prepare") resetShippingItems(true);
    // Po úprave položiek treba čerstvé dáta — počty na expedíciu, súčty aj históriu.
    if (props.mode === "edit" && mode !== "edit") emit("edited");
    emit("update:mode", mode);
};

const showPrepare = computed(() => props.mode === "prepare" && canPrepare.value);
const columns = computed(() => showPrepare.value ? 8 : 6);

const adjustment = computed(() => Number(props.order?.adjustment_amount ?? 0));
</script>

<template>
    <div class="mb-4 rounded-lg border border-gray-200 bg-white shadow-sm">
        <!-- Hlavička: súhrn expedície + prepínanie režimov -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-gray-100 px-5 py-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Položky a expedícia</span>
            <span class="text-sm text-gray-600">
                Expedované <span class="font-semibold text-gray-900">{{ shippedQuantity }}/{{ requiredQuantity }} ks</span>
                · zostáva <span class="font-semibold text-gray-900">{{ remainingQuantity }} ks</span>
            </span>
            <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200" :title="shippingPercentage + ' %'">
                <div class="h-full rounded-full" :style="{ width: shippingPercentage + '%' }"
                    :class="order.isFinished ? 'bg-green-600' : shippedQuantity > 0 ? 'bg-amber-500' : 'bg-red-500'"></div>
            </div>

            <div class="ml-auto flex flex-wrap gap-2">
                <template v-if="mode === 'view'">
                    <button v-if="canEdit" type="button" @click="setMode('edit')"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                        Upraviť položky / storno
                    </button>
                    <button v-if="canPrepare" type="button" @click="setMode('prepare')"
                        class="rounded bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                        Pripraviť dodací list
                    </button>
                </template>
                <template v-else-if="showPrepare">
                    <button type="button" @click="resetShippingItems(false)"
                        class="rounded bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-200">Vyčistiť</button>
                    <button type="button" @click="resetShippingItems(true)"
                        class="rounded bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Všetko zostávajúce</button>
                    <button type="button" @click="setMode('view')"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Zrušiť prípravu</button>
                </template>
                <button v-else-if="mode === 'edit'" type="button" @click="setMode('view')"
                    class="rounded bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-900">
                    Hotovo s úpravami
                </button>
            </div>
        </div>

        <!-- Úprava položiek -->
        <template v-if="mode === 'edit'">
            <p class="border-b border-gray-100 bg-amber-50 px-5 py-2 text-sm text-amber-800">
                Každú položku uložte v ponuke riadku. Zákazník o zmene nedostane e-mail sám — pošlite ho v Histórii objednávky.
            </p>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y">
                    <thead class="thead">
                        <tr>
                            <th class="thead_th">Názov</th>
                            <th class="thead_th">Množstvo</th>
                            <th class="thead_th">Cena</th>
                            <th class="thead_th">DPH</th>
                            <th class="thead_th">s DPH</th>
                            <th class="thead_th">Exped.</th>
                            <th class="thead_th">Storno</th>
                            <th class="thead_th"><span class="sr-only">Akcie</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <product-table-row v-for="item in getOrderProducts" :key="item.id" :item="item" />
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-start gap-4 px-5 pb-2">
                <button type="button" @click="addOrderProduct(order.id)"
                    class="my-4 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    + Pridať produkt
                </button>
                <CustomOrderItem class="flex-1" @add="addOrderProduct(order.id, $event)" />
            </div>
        </template>

        <!-- Prehľad / príprava dodacieho listu -->
        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="thead">
                    <tr>
                        <th v-if="showPrepare" class="thead_th w-10 text-center"><span class="sr-only">Zahrnúť</span></th>
                        <th class="thead_th text-left">Produkt</th>
                        <th class="thead_th text-center">Objednané</th>
                        <th class="thead_th text-center">Expedované</th>
                        <th class="thead_th text-center">Ostáva</th>
                        <th v-if="showPrepare" class="thead_th text-center">Teraz pripraviť</th>
                        <th class="thead_th text-right">Cena/ks</th>
                        <th class="thead_th text-right">Spolu s DPH</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="item in items" :key="item.id"
                        :class="showPrepare && isExcluded(item) ? 'bg-gray-100 opacity-60'
                            : item.remaining === 0 && item.stornoQuantity === 0 && item.shipped > 0 ? 'bg-green-50'
                            : item.remaining === 0 && item.stornoQuantity > 0 ? 'bg-gray-50 text-gray-500' : ''">
                        <td v-if="showPrepare" class="tbody_td text-center">
                            <input v-if="item.remaining > 0" type="checkbox" class="h-4 w-4 rounded"
                                :checked="!isExcluded(item)" @change="toggleExcludeItem(item)"
                                :title="isExcluded(item) ? 'Zahrnúť do dodacieho listu' : 'Vynechať z dodacieho listu'" />
                        </td>
                        <td class="tbody_td">
                            <div class="flex items-center gap-3">
                                <img v-if="item.thumb" :src="item.thumb" :alt="item.name" class="h-10 w-10 rounded border border-gray-200 object-cover" />
                                <div>
                                    <div class="font-semibold text-gray-900">{{ item.name }}</div>
                                    <div v-if="item.variant_name" class="text-xs font-medium text-blue-700">{{ item.variant_name }}</div>
                                    <div v-if="item.made_to_order" class="text-xs font-semibold text-amber-700">na zákazku</div>
                                </div>
                            </div>
                        </td>
                        <td class="tbody_td text-center whitespace-nowrap">
                            {{ item.quantity }} {{ item.unit_value }}
                            <div v-if="item.stornoQuantity > 0" class="text-xs font-semibold text-red-700">storno {{ item.stornoQuantity }}</div>
                        </td>
                        <td class="tbody_td text-center">
                            <span :class="item.shipped > 0 ? 'font-semibold text-green-700' : 'text-gray-500'">{{ item.shipped }}</span>
                        </td>
                        <td class="tbody_td text-center">
                            <span :class="item.remaining > 0 ? 'font-semibold text-amber-700' : 'text-gray-500'">{{ item.remaining }}</span>
                        </td>
                        <td v-if="showPrepare" class="tbody_td text-center">
                            <input v-if="item.remaining > 0" v-model.number="shippingItems[item.id]"
                                type="number" min="0" :max="item.remaining" :disabled="isExcluded(item)"
                                :aria-label="'Teraz pripraviť: ' + item.name"
                                class="w-24 rounded border px-2 py-1 text-center"
                                :class="isExcluded(item) ? 'border-gray-200 bg-gray-100 text-gray-500 line-through' : 'border-gray-300'"
                                @input="normalizeQuantity(item)" />
                            <span v-else class="text-xs text-gray-500">—</span>
                        </td>
                        <td class="tbody_td text-right whitespace-nowrap">{{ formatPrice(item.unitPrice) }} €</td>
                        <td class="tbody_td text-right whitespace-nowrap">{{ formatPrice(item.lineTotal) }} €</td>
                    </tr>
                    <tr v-if="!items.length">
                        <td :colspan="columns" class="tbody_td py-8 text-center text-gray-500">Táto objednávka nemá žiadne položky.</td>
                    </tr>
                </tbody>
            </table>

            <div class="space-y-1 border-t border-gray-100 px-5 py-3 text-right text-sm">
                <p v-if="order.discount_amount">Zľavový kupón: −{{ formatPrice(order.discount_amount) }} €</p>
                <p v-if="adjustment">{{ order.price_adjustment?.label || (adjustment < 0 ? 'Zľava' : 'Prirážka') }}: {{ formatPrice(adjustment) }} €</p>
                <p class="text-gray-600">Doprava: {{ formatPrice(order.shipping_price || 0) }} € · Platba: {{ formatPrice(order.payment_fee || 0) }} €</p>
                <p class="text-lg font-bold">Celkom: {{ formatPrice(order.grand_total || 0) }} €</p>
            </div>
        </div>

        <!-- Hlavná akcia prípravy je prilepená dole, aby bola vždy na dosah -->
        <div v-if="showPrepare"
            class="sticky bottom-0 z-10 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-b-lg border-t-2 border-blue-600 bg-white px-5 py-3 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]">
            <span class="text-sm text-gray-600">Teraz pripraviť <span class="font-semibold text-gray-900">{{ selectedQuantity }} ks</span></span>
            <span class="text-sm text-gray-600">ostane na neskôr <span class="font-semibold text-gray-900">{{ remainingAfterShipping }} ks</span></span>
            <button type="button" :disabled="selectedQuantity <= 0" @click="emit('prepare', selection())"
                class="ml-auto rounded bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:bg-gray-400">
                Pripraviť dodací list
            </button>
        </div>
    </div>
</template>

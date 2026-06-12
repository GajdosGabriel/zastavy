<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonLink from "../layout/page/ButtonLink.vue";
import useOrders from "../../store/StoreOrders";
import useReturns from "../../store/StoreReturns";

const { getOrder, fetchOrder } = useOrders();
const { storeReturn } = useReturns();

const router = useRouter();
const { params: { orderId } } = useRoute();

const reason = ref('not_accepted');
const note = ref('');
const submitting = ref(false);
const quantities = reactive({});

const buttonBack = { name: 'Späť', link: `/objednavky/${orderId}/show`, icon: 'arrow-left' };

const REASONS = [
    { value: 'not_accepted', label: 'Neprevzatá zásielka' },
    { value: 'damaged',      label: 'Poškodený tovar' },
    { value: 'wrong_item',   label: 'Nesprávny tovar' },
    { value: 'other',        label: 'Iný dôvod' },
];

onMounted(async () => {
    await fetchOrder(orderId);
    initQuantities();
});

const shippedItems = computed(() =>
    (getOrder.value?.orderProducts ?? [])
        .filter(item => Number(item.stockSum ?? 0) > 0)
        .map(item => ({
            ...item,
            shipped: Number(item.stockSum ?? 0),
        }))
);

function initQuantities() {
    shippedItems.value.forEach(item => {
        quantities[item.id] = item.shipped;
    });
}

const selectedItems = computed(() =>
    shippedItems.value.filter(item => Number(quantities[item.id] ?? 0) > 0)
);

const canSubmit = computed(() => selectedItems.value.length > 0 && !submitting.value);

async function submit() {
    if (!canSubmit.value) return;
    submitting.value = true;

    const payload = {
        reason: reason.value,
        note: note.value || null,
        items: selectedItems.value.map(item => ({
            order_product_id: item.id,
            quantity: Number(quantities[item.id]),
        })),
    };

    const result = await storeReturn(orderId, payload);
    submitting.value = false;

    if (result) {
        router.push({ name: 'orders.returns.show', params: { orderId, returnId: result.id } });
    }
}
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">

                <!-- Header -->
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Objednávka č. {{ getOrder?.serial_number }}</p>
                        <h1 class="text-xl font-bold text-gray-900">Nové vrátenie tovaru</h1>
                    </div>
                    <buttonLink :item="buttonBack" class="text-sm" />
                </div>

                <form @submit.prevent="submit" class="space-y-4">

                    <!-- Dôvod + poznámka -->
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-2.5">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Dôvod vrátenia</span>
                        </div>
                        <div class="px-5 py-4 space-y-4">
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <label
                                    v-for="r in REASONS"
                                    :key="r.value"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border-2 px-3 py-2.5 text-sm transition"
                                    :class="reason === r.value
                                        ? 'border-blue-500 bg-blue-50 text-blue-800 font-semibold'
                                        : 'border-gray-200 text-gray-700 hover:border-gray-300'"
                                >
                                    <input type="radio" v-model="reason" :value="r.value" class="hidden" />
                                    {{ r.label }}
                                </label>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-400">Poznámka (nepovinné)</label>
                                <textarea v-model="note" rows="2"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"
                                    placeholder="Napr. zákazník odmietol prevziať..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Položky -->
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-5 py-2.5">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Položky na vrátenie</span>
                        </div>

                        <div v-if="shippedItems.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                            Žiadne expedované položky na vrátenie.
                        </div>

                        <table v-else class="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr>
                                    <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Tovar</th>
                                    <th class="px-5 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-400">Expedované</th>
                                    <th class="px-5 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-400">Vrátiť</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="item in shippedItems" :key="item.id">
                                    <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ item.name }}</td>
                                    <td class="px-5 py-3 text-center text-sm text-gray-600">
                                        {{ item.shipped }} {{ item.unit_value ?? 'ks' }}
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <input
                                            type="number"
                                            v-model.number="quantities[item.id]"
                                            :min="0"
                                            :max="item.shipped"
                                            class="w-20 rounded border border-gray-300 px-2 py-1 text-center text-sm focus:border-blue-500 focus:outline-none"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Súhrn + odoslanie -->
                    <div v-if="selectedItems.length" class="rounded-lg border border-blue-200 bg-blue-50 px-5 py-3">
                        <p class="text-sm text-blue-800">
                            <span class="font-semibold">Súhrn:</span>
                            vrátenie {{ selectedItems.length }} {{ selectedItems.length === 1 ? 'položky' : 'položiek' }},
                            spolu {{ selectedItems.reduce((s, i) => s + Number(quantities[i.id]), 0) }} ks
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <buttonLink :item="buttonBack" class="text-sm" />
                        <button
                            type="submit"
                            :disabled="!canSubmit"
                            class="rounded-lg px-5 py-2 text-sm font-semibold transition"
                            :class="canSubmit
                                ? 'bg-red-600 text-white hover:bg-red-700'
                                : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                        >
                            {{ submitting ? 'Ukladám...' : 'Vytvoriť vrátenie' }}
                        </button>
                    </div>

                </form>
            </div>
        </template>
    </BaseLayout>
</template>

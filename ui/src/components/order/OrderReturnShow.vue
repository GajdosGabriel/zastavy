<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import BaseLayout from "../layout/BaseLayout.vue";
import buttonLink from "../layout/page/ButtonLink.vue";
import useReturns from "../../store/StoreReturns";

const { fetchReturn, getCurrentReturn, updateReturn, processReturn, cancelReturn, deleteReturn } = useReturns();

const router = useRouter();
const { params: { orderId, returnId } } = useRoute();

const editing = ref(false);
const confirming = ref(null);
const submitting = ref(false);
const showProcessModal = ref(false);
const notifyCustomer = ref(true);

const editReason = ref('');
const editNote = ref('');
const editQuantities = reactive({});

const buttonBack = { name: 'Späť', link: `/objednavky/${orderId}/show`, icon: 'arrow-left' };

const REASONS = [
    { value: 'not_accepted', label: 'Neprevzatá zásielka' },
    { value: 'damaged',      label: 'Poškodený tovar' },
    { value: 'wrong_item',   label: 'Nesprávny tovar' },
    { value: 'other',        label: 'Iný dôvod' },
];

const STATUS_STYLES = {
    pending:   'bg-amber-100 text-amber-800',
    processed: 'bg-green-100 text-green-800',
    cancelled: 'bg-gray-100 text-gray-500',
};

const STATUS_LABELS = {
    pending:   'Čaká',
    processed: 'Spracované',
    cancelled: 'Zrušené',
};

onMounted(() => fetchReturn(orderId, returnId));

const ret = getCurrentReturn;

function startEdit() {
    editReason.value = ret.value.reason;
    editNote.value = ret.value.note ?? '';
    ret.value.items.forEach(item => {
        editQuantities[item.order_product_id] = item.quantity;
    });
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
}

async function saveEdit() {
    submitting.value = true;
    const payload = {
        reason: editReason.value,
        note: editNote.value || null,
        items: ret.value.items.map(item => ({
            order_product_id: item.order_product_id,
            quantity: Number(editQuantities[item.order_product_id] ?? item.quantity),
        })),
    };
    await updateReturn(orderId, returnId, payload);
    submitting.value = false;
    editing.value = false;
}

async function doProcess() {
    submitting.value = true;
    await processReturn(orderId, returnId, notifyCustomer.value);
    submitting.value = false;
    showProcessModal.value = false;
}

async function doCancel() {
    submitting.value = true;
    await cancelReturn(orderId, returnId);
    submitting.value = false;
    confirming.value = null;
}

async function doDelete() {
    submitting.value = true;
    await deleteReturn(orderId, returnId);
    submitting.value = false;
    router.push(`/objednavky/${orderId}/show`);
}
</script>

<template>
    <BaseLayout>
        <template #main>
            <div v-if="!ret" class="page-body col-span-12 text-gray-400 text-sm py-10 text-center">Načítavam...</div>

            <div v-else class="page-body col-span-12">

                <!-- Header -->
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Objednávka č. {{ ret.order_id }}</p>
                        <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900">
                            Vrátenie #{{ ret.id }}
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="STATUS_STYLES[ret.status] ?? 'bg-gray-100 text-gray-500'">
                                {{ STATUS_LABELS[ret.status] ?? ret.status }}
                            </span>
                        </h1>
                    </div>
                    <buttonLink :item="buttonBack" class="text-sm" />
                </div>

                <!-- Spracované banner -->
                <div v-if="ret.status === 'processed'"
                    class="mb-4 flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-5 py-3 text-sm text-green-800">
                    <svg class="h-5 w-5 shrink-0 text-green-600" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <span class="font-semibold">Vrátenie spracované</span>
                        <span v-if="ret.processed_at"> · {{ ret.processed_at }}</span>
                        <span v-if="ret.processed_by"> · {{ ret.processed_by }}</span>
                        — tovar bol vrátený na sklad.
                    </div>
                </div>

                <!-- Detail karty -->
                <div class="mb-4 rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-2.5 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Detail</span>
                        <button v-if="ret.status === 'pending' && !editing"
                            @click="startEdit"
                            class="text-xs font-semibold text-blue-600 hover:text-blue-800">Upraviť</button>
                    </div>

                    <div class="px-5 py-4 space-y-3">

                        <!-- Dôvod (edit) -->
                        <div v-if="editing" class="space-y-3">
                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Dôvod</p>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    <label v-for="r in REASONS" :key="r.value"
                                        class="flex cursor-pointer items-center gap-2 rounded-lg border-2 px-3 py-2 text-sm transition"
                                        :class="editReason === r.value
                                            ? 'border-blue-500 bg-blue-50 text-blue-800 font-semibold'
                                            : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                        <input type="radio" v-model="editReason" :value="r.value" class="hidden" />
                                        {{ r.label }}
                                    </label>
                                </div>
                            </div>
                            <div>
                                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Poznámka</p>
                                <textarea v-model="editNote" rows="2"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none"></textarea>
                            </div>
                        </div>

                        <!-- Dôvod (view) -->
                        <div v-else class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <p class="text-xs text-gray-400">Dôvod</p>
                                <p class="font-medium text-gray-900">{{ ret.reason_label }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Vytvoril</p>
                                <p class="font-medium text-gray-900">{{ ret.created_by ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400">Dátum</p>
                                <p class="font-medium text-gray-900">{{ ret.created_at }}</p>
                            </div>
                            <div v-if="ret.note" class="col-span-full">
                                <p class="text-xs text-gray-400">Poznámka</p>
                                <p class="text-gray-700">{{ ret.note }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Položky -->
                <div class="mb-4 rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-2.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Položky</span>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead>
                            <tr>
                                <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">Tovar</th>
                                <th class="px-5 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-400">Expedované</th>
                                <th class="px-5 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-400">Vrátiť</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="item in ret.items" :key="item.id">
                                <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ item.product_name }}</td>
                                <td class="px-5 py-3 text-center text-sm text-gray-500">
                                    {{ item.shipped_quantity }} {{ item.product_unit }}
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <input v-if="editing"
                                        type="number"
                                        v-model.number="editQuantities[item.order_product_id]"
                                        :min="0"
                                        :max="item.shipped_quantity"
                                        class="w-20 rounded border border-gray-300 px-2 py-1 text-center text-sm focus:border-blue-500 focus:outline-none"
                                    />
                                    <span v-else class="font-semibold text-sm text-gray-900">
                                        {{ item.quantity }} {{ item.product_unit }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Edit actions -->
                    <div v-if="editing" class="flex justify-end gap-2 px-5 py-3 border-t border-gray-100">
                        <button @click="cancelEdit"
                            class="rounded px-4 py-1.5 text-sm font-semibold text-gray-600 hover:bg-gray-100">
                            Zrušiť
                        </button>
                        <button @click="saveEdit" :disabled="submitting"
                            class="rounded bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                            {{ submitting ? 'Ukladám...' : 'Uložiť zmeny' }}
                        </button>
                    </div>
                </div>

                <!-- Akcie -->
                <div v-if="ret.status === 'pending' && !editing" class="flex flex-wrap items-center gap-3">
                    <template v-if="confirming === 'cancel'">
                        <div class="flex items-center gap-2 rounded-lg border border-gray-300 bg-gray-50 px-4 py-2 text-sm text-gray-700">
                            <span class="font-semibold">Zrušiť vrátenie?</span>
                            <button @click="doCancel" :disabled="submitting"
                                class="ml-2 rounded bg-gray-600 px-3 py-1 font-semibold text-white hover:bg-gray-700 disabled:opacity-50">
                                {{ submitting ? '...' : 'Áno, zrušiť' }}
                            </button>
                            <button @click="confirming = null" class="rounded px-3 py-1 text-gray-600 hover:bg-gray-100">Nie</button>
                        </div>
                    </template>
                    <template v-else-if="confirming === 'delete'">
                        <div class="flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800">
                            <span class="font-semibold">Zmazať vrátenie natrvalo?</span>
                            <button @click="doDelete" :disabled="submitting"
                                class="ml-2 rounded bg-red-600 px-3 py-1 font-semibold text-white hover:bg-red-700 disabled:opacity-50">
                                {{ submitting ? '...' : 'Zmazať' }}
                            </button>
                            <button @click="confirming = null" class="rounded px-3 py-1 text-red-700 hover:bg-red-100">Nie</button>
                        </div>
                    </template>
                    <template v-else>
                        <button @click="showProcessModal = true"
                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                            Vrátiť na sklad
                        </button>
                        <button @click="confirming = 'cancel'"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Zrušiť vrátenie
                        </button>
                        <button @click="confirming = 'delete'"
                            class="ml-auto rounded-lg px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">
                            Zmazať
                        </button>
                    </template>
                </div>

                <div class="mt-4">
                    <buttonLink :item="buttonBack" class="text-sm" />
                </div>
            </div>
        </template>
    </BaseLayout>

    <!-- Modal: Vrátiť na sklad -->
    <Teleport to="body">
        <div v-if="showProcessModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-1 text-lg font-semibold text-gray-800">Vrátiť tovar na sklad?</h3>
                <p class="mb-1 text-sm text-gray-500">
                    Tovar bude zaúčtovaný späť na sklad a objednávka bude čakať na ďalšiu expedíciu.
                </p>
                <p v-if="ret" class="mb-4 text-sm font-medium text-amber-700">
                    Dôvod: {{ ret.reason_label }}
                </p>
                <label class="mb-5 flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" v-model="notifyCustomer" class="rounded" />
                    Informovať zákazníka emailom o vrátení
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showProcessModal = false"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                        Zrušiť
                    </button>
                    <button type="button" @click="doProcess" :disabled="submitting"
                        class="rounded bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">
                        {{ submitting ? '...' : 'Vrátiť na sklad' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import ReorderButton from '../sales/ReorderButton.vue';
import BaseLayout from "../layout/BaseLayout.vue";
import useOrders from "../../store/StoreOrders";
import { useProducts } from "../../store/StoreProducts";
import { useReturns } from "../../store/StoreReturns";
import { useRoute, useRouter } from "vue-router";
import { storeToRefs } from "pinia";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import axiosInstance from "../../axiosInstance";
import useErrors from "../../store/StoreErrors";
import { formatFileSize } from "../../models/functions";
import OrderCustomerCard from "./component/OrderCustomerCard.vue";
import OrderItemsTable from "./component/OrderItemsTable.vue";
import OrderHistory from "./component/OrderHistory.vue";
import ShippingDialog from "./component/ShippingDialog.vue";
import buttonLink from '../layout/page/ButtonLink.vue';

// Jedna stránka objednávky: prehľad, úprava položiek a storno, expedícia
// (dodacie listy) aj história s e-mailami zákazníkovi. Bývalá stránka
// Expedícia (/objednavky/:id/expedicia) presmeruje sem s ?expedicia=1.

const ordersStore = useOrders();
const { getOrder, customer } = storeToRefs(ordersStore);
const { fetchOrder, downloadAttachment, uploadAttachments, deleteAttachment } = ordersStore;

const { fetchProducts } = useProducts();
const returnsStore = useReturns();
const { getReturns } = storeToRefs(returnsStore);
const { fetchReturns } = returnsStore;

const router = useRouter();
const route = useRoute();
const orderId = route.params.orderId;

// Store ešte drží objednávku z vytvorenia (bez položiek) alebo z predošlého
// detailu — kým nedobehne načítanie, ukazujeme skeleton namiesto prázdnej objednávky.
const isLoaded = ref(false);
const itemsMode = ref("view");
const itemsSection = ref(null);
const history = ref(null);

onMounted(async () => {
    fetchProducts();
    fetchReturns(orderId);
    try {
        await fetchOrder(orderId);
    } finally {
        isLoaded.value = true;
    }
    if (route.query.expedicia) {
        itemsMode.value = "prepare";
        await nextTick();
        itemsSection.value?.scrollIntoView({ behavior: "smooth", block: "start" });
    }
});

const reload = async () => {
    await fetchOrder(orderId);
    history.value?.load();
};

const orderProducts = computed(() => getOrder.value?.orderProducts ?? []);
const madeToOrderNames = computed(() => orderProducts.value.filter(item => item.made_to_order).map(item => item.name));
const pendingShippings = computed(() => (getOrder.value?.shippings ?? []).filter(s => s.is_preparing));
const hasShippedItems = computed(() => orderProducts.value.some(item => Number(item.stockSum ?? 0) > 0));
const canShip = computed(() => !!getOrder.value?.permissions?.ship?.allowed);

// --- Dodacie listy ---
const dialogItems = ref(null);
const dialogShipping = ref(null);
const closeDialog = () => { dialogItems.value = null; dialogShipping.value = null; };
// Po expedícii sa vraciame na zoznam (na tú istú stranu a filter), kde čaká ďalšia objednávka.
const backToList = () => router.push(ordersStore.listRoute ?? { name: 'orders.index' });
const onShippingDone = () => {
    closeDialog();
    backToList();
};
const cancelPreparation = async (shipping) => {
    if (!window.confirm('Zrušiť prípravu dodacieho listu?')) return;
    try {
        await axiosInstance.delete('/orders/' + orderId + '/shippings/' + shipping.id);
        await reload();
    } catch (e) {
        useErrors().setErrors(e);
    }
};

// Po úprave položiek načítame čerstvé počty a ponúkneme e-mail o zmene.
const onItemsEdited = async () => {
    await reload();
};

const STATUS_STYLES = {
    pending:   'bg-amber-100 text-amber-700',
    processed: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
};
const STATUS_LABELS = {
    pending:   'Čaká',
    processed: 'Spracované',
    cancelled: 'Zrušené',
};

const attachmentInput = ref(null);

const onUploadAttachments = async (event) => {
    await uploadAttachments(orderId, event.target.files);
    event.target.value = "";
};

const onDeleteAttachment = (file) => {
    if (!window.confirm(`Zmazať prílohu ${file.name}?`)) return;
    deleteAttachment(orderId, file.id);
};

const heading = computed(() => getOrder.value?.serial_number ? 'Objednávka č. ' + getOrder.value.serial_number : 'Objednávka');
const buttonBack = { name: 'Späť', spinner: true, link: ordersStore.listRoute ?? 'orders.index', icon: 'arrow-left' }
const buttonHeader = { name: 'Upraviť', spinner: true, link: '/objednavky/'+ orderId +'/edit' }

watch(() => route.query.expedicia, (value) => { if (value && isLoaded.value) itemsMode.value = "prepare"; });
</script>

<template>

    <BaseLayout>

        <template #main>

            <h1 class="page-heading">{{ isLoaded ? heading : 'Objednávka' }}
                <buttonLink :item="buttonHeader" class="text-sm" />
            </h1>


            <div v-if="!isLoaded" class="page-body col-span-12 animate-pulse space-y-4" aria-busy="true">
                <div class="h-24 rounded-lg bg-gray-200"></div>
                <div class="h-64 rounded-lg bg-gray-200"></div>
            </div>

            <div v-else class="page-body col-span-12">

                <!-- Sekundárne akcie objednávky -->
                <div class="mb-4 flex flex-wrap items-start gap-2">
                    <router-link v-if="getOrder.permissions?.update?.allowed" :to="'/vyroba/'+orderId"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Komunikácia so zákazníkom
                        <span v-if="getOrder.unread_messages" class="ml-2 rounded-full bg-red-600 px-2 text-xs font-bold text-white"
                            :title="'Neprečítané správy zákazníka: '+getOrder.unread_messages">{{ getOrder.unread_messages }}</span>
                    </router-link>
                    <ReorderButton :endpoint="'/orders/'+orderId+'/reorder'" />
                </div>

                <!-- Poznámka zákazníka a veci, na ktoré sa pri vybavení nesmie zabudnúť -->
                <div v-if="getOrder.note || getOrder.wants_coupon || madeToOrderNames.length"
                     class="mb-4 flex items-start gap-3 rounded-lg border-2 border-amber-400 bg-amber-50 px-5 py-4 shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <div class="space-y-1">
                        <div class="text-sm font-bold uppercase tracking-wide text-amber-700">Poznámka zákazníka</div>
                        <div v-if="getOrder.note" class="text-base text-amber-900">{{ getOrder.note }}</div>
                        <div v-if="getOrder.wants_coupon" class="text-sm font-semibold text-amber-700">
                            Zákazník chce získať zľavový kupón na ďalší nákup
                        </div>
                        <div v-if="madeToOrderNames.length" class="text-sm font-semibold text-amber-700">
                            Obsahuje tovar na zákazku: {{ madeToOrderNames.join(', ') }}
                        </div>
                    </div>
                </div>

                <!-- Čaká na prepravcu — najnaliehavejšia úloha, preto hore -->
                <section v-if="pendingShippings.length" class="mb-4 rounded-lg border-2 border-blue-300 bg-blue-50 p-5 shadow-sm">
                    <h2 class="font-semibold text-blue-900">Pripravené na odoslanie</h2>
                    <p class="text-sm text-blue-800">Balík je pripravený pre prepravcu. Keď ho prepravca prevezme, potvrďte odoslanie. Sklad sa odpíše až potom.</p>
                    <div v-for="shipping in pendingShippings" :key="shipping.id" class="mt-3">
                        <p class="font-semibold">Dodací list #{{ shipping.id }} · {{ shipping.created_at }}</p>
                        <p v-for="item in shipping.prepared_items" :key="item.order_product_id" class="mt-1 text-sm">{{ item.name }} — {{ item.quantity }} {{ item.unit }}</p>
                        <div v-if="canShip" class="mt-3 flex flex-wrap gap-3">
                            <button type="button" class="rounded bg-blue-700 px-4 py-2 font-semibold text-white hover:bg-blue-800" @click="dialogShipping = shipping">Potvrdiť odoslanie</button>
                            <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 hover:bg-gray-50" @click="cancelPreparation(shipping)">Zrušiť prípravu</button>
                        </div>
                    </div>
                </section>

                <div class="mb-4">
                    <OrderCustomerCard :customer="customer" :user="getOrder.user" :order="getOrder" />
                </div>

                <div ref="itemsSection" class="scroll-mt-4">
                    <OrderItemsTable v-model:mode="itemsMode" :order="getOrder"
                        @prepare="dialogItems = $event" @edited="onItemsEdited" />
                </div>

                <!-- Čo sa s objednávkou stalo a čo o tom vie zákazník -->
                <OrderHistory v-if="getOrder.permissions?.update?.allowed" ref="history" :order-id="orderId" />

                <!-- Prílohy zákazníka (podklady k výrobe) -->
                <div class="mt-4 rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-2.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Prílohy
                            <span v-if="getOrder.attachments?.length" class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-gray-600">
                                {{ getOrder.attachments.length }}
                            </span>
                        </span>
                        <div>
                            <input ref="attachmentInput" type="file" multiple class="hidden" @change="onUploadAttachments" />
                            <button
                                @click="attachmentInput?.click()"
                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                + Pridať prílohu
                            </button>
                        </div>
                    </div>

                    <div v-if="!getOrder.attachments?.length" class="px-5 py-5 text-center text-sm text-gray-500">
                        Žiadne prílohy
                    </div>

                    <ul v-else class="divide-y divide-gray-50">
                        <li v-for="file in getOrder.attachments" :key="file.id"
                            class="flex items-center justify-between gap-3 px-5 py-3">
                            <button type="button" @click="downloadAttachment(orderId, file)"
                                class="truncate text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                {{ file.name }}
                            </button>
                            <span class="flex shrink-0 items-center gap-3">
                                <span class="text-xs text-gray-500">{{ formatFileSize(file.size) }}</span>
                                <span class="text-xs text-gray-500">{{ file.created_at }}</span>
                                <button type="button" @click="onDeleteAttachment(file)"
                                    class="text-xs font-semibold text-red-500 hover:text-red-700">
                                    Zmazať
                                </button>
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Vrátenia — vrátiť sa dá len expedovaný tovar, inak sekcia nemá význam -->
                <div v-if="getReturns.length || hasShippedItems" class="mt-4 rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-2.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Vrátenia tovaru
                            <span v-if="getReturns.length" class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-gray-600">
                                {{ getReturns.length }}
                            </span>
                        </span>
                        <button v-if="hasShippedItems && getOrder.permissions?.manageReturns?.allowed"
                            @click="router.push({ name: 'orders.returns.create', params: { orderId } })"
                            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                            + Vrátiť tovar
                        </button>
                    </div>

                    <div v-if="!getReturns.length" class="px-5 py-5 text-center text-sm text-gray-500">
                        Žiadne vrátenia
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead>
                                <tr>
                                    <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">#</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Dôvod</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Položky</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Dátum</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Stav</th>
                                    <th class="px-5 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="ret in getReturns" :key="ret.id" class="hover:bg-gray-50">
                                    <td class="px-5 py-3 text-sm text-gray-500">{{ ret.id }}</td>
                                    <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ ret.reason_label }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-600">
                                        <div v-for="item in ret.items" :key="item.id">{{ item.product_name }} <span class="font-semibold">× {{ item.quantity }}</span></div>
                                    </td>
                                    <td class="px-5 py-3 text-sm text-gray-500">{{ ret.created_at }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="STATUS_STYLES[ret.status] ?? 'bg-gray-100 text-gray-500'">
                                            {{ STATUS_LABELS[ret.status] ?? ret.status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <button
                                            @click="router.push({ name: 'orders.returns.show', params: { orderId, returnId: ret.id } })"
                                            class="text-xs font-semibold text-blue-600 hover:text-blue-800">
                                            Detail
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-5 flex justify-between">
                    <buttonLink :item="buttonBack" />
                </div>
            </div>

            <ShippingDialog :order="getOrder" :items="dialogItems" :shipping="dialogShipping"
                @close="closeDialog" @done="onShippingDone" />
        </template>
    </BaseLayout>
</template>

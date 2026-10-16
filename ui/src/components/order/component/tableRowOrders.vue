<script setup>
import { computed, ref } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";
import TruncatedText from "../../layout/TruncatedText.vue";
import iconStar from "../../../components/icons/star.vue";
import noticeLabel from "./noticeLabel.vue";
import shippingButton from "./shippingButton.vue";
import useOrders from "../../../store/StoreOrders";
import { formatPrice } from "../../../models/functions";

const props = defineProps(["order"]);

const { destroyOrder, updateOrder, clickToMark } = useOrders();

const showStornoModal = ref(false);
const notifyCustomer = ref(true);
const submitting = ref(false);

const openStornoModal = () => {
    notifyCustomer.value = true;
    showStornoModal.value = true;
};

const confirmStorno = async () => {
    submitting.value = true;
    await updateOrder({ id: props.order.id, makeStorned: true, notify_customer: notifyCustomer.value });
    submitting.value = false;
    showStornoModal.value = false;
};

const archiveOrder = () => updateOrder({ id: props.order.id, status: "archived" });

const actionMap = {
    view:    { to: { name: "orders.show",  params: { orderId: props.order.id } } },
    update:  { to: { name: "orders.edit",  params: { orderId: props.order.id } } },
    storno:  { onClick: openStornoModal },
    delete:  { onClick: () => destroyOrder(props.order.endpoints.destroy) },
    archive: { onClick: archiveOrder },
};

// Vrátiť sa dá len tovar, ktorý už bol expedovaný
const canCreateReturn = computed(() => Number(props.order.stock_expedition ?? 0) > 0);

const dropdownItems = computed(() => {
    // Cesty UI patria do routera, nie do API. Expedícia je súčasťou „Zobraziť“
    // (príprava DL je tlačidlo v riadku), preto vlastnú položku nemá.
    const customerId = props.order.customer?.id;
    const fixed = customerId
        ? [{ label: "Zákazník", to: { name: "customers.orders", params: { customerId } } }]
        : [];

    if (!Object.keys(props.order.endpoints).length) return fixed;

    const permissionItems = Object.entries(props.order.permissions || {})
        .filter(([key, perm]) => perm.allowed && actionMap[key])
        .map(([key, perm]) => ({ key, label: perm.label, ...actionMap[key] }));

    const returnItem = canCreateReturn.value
        ? [{ label: "Vrátenie tovaru", to: { name: "orders.returns.create", params: { orderId: props.order.id } } }]
        : [];

    // Storno a Zmazať idú na koniec menu ako danger položky, oddelené od bežných akcií.
    const dangerKeys = ["storno", "delete"];
    const safe = permissionItems.filter(item => !dangerKeys.includes(item.key));
    const danger = permissionItems.filter(item => dangerKeys.includes(item.key)).map(item => ({ ...item, danger: true }));

    return [...fixed, ...returnItem, ...safe, ...danger];
});

const customerName = computed(() => props.order.customer.company || props.order.customer.name || "");

// "Obec Lula / Lula" — mesto zobrazíme len ak už nie je obsiahnuté v názve zákazníka.
const showCity = computed(() => {
    const city = (props.order.customer.city || "").trim().toLowerCase();
    return city && !customerName.value.toLowerCase().includes(city);
});

const rowStateTitle = computed(() => {
    if (props.order.isStorned) return "Stornovaná objednávka";
    if (props.order.isFinished) return "Vybavená objednávka";
    if (!props.order.isOpened) return "Neotvorená objednávka";
    return undefined;
});
</script>

<template>
    <!-- Pod md sa riadok skladá ako karta (flex + order), od md je to bežný riadok tabuľky. -->
    <tr class="flex flex-wrap items-start border-b border-gray-200 px-2 py-3 transition hover:bg-gray-50 md:table-row md:p-0 md:align-top" :class="[
        { 'bg-green-50': !order.isOpened },
        { 'bg-blue-50': order.isFinished },
        { 'bg-gray-200': order.isStorned },
    ]">
        <td class="tbody_td order-1 whitespace-nowrap font-semibold !text-gray-700 max-md:!px-2 max-md:!py-1">
            <div class="flex items-center gap-1.5">
                <router-link :to="{ name: 'orders.show', params: { orderId: order.id } }" class="hover:text-blue-700">
                    {{ order.serial_number }}
                </router-link>
                <span v-if="order.wants_coupon"
                    class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white"
                    title="Zákazník požaduje zľavový kupón">K</span>
                <span v-if="rowStateTitle" class="sr-only">{{ rowStateTitle }}</span>
            </div>
        </td>

        <td class="tbody_td order-2 w-10 max-md:!px-0 max-md:!py-0">
            <button type="button" @click="clickToMark(order)"
                class="mx-auto flex h-8 w-8 items-center justify-center rounded hover:bg-gray-100 md:-my-1.5"
                :aria-pressed="!!order.mark.isActive"
                :aria-label="order.mark.isActive ? 'Zrušiť označenie' : 'Označiť objednávku'"
                :title="order.mark.isActive ? 'Zrušiť označenie' : 'Označiť objednávku'">
                <icon-star :status="order.mark.isActive" />
            </button>
        </td>

        <td class="tbody_td order-4 basis-full cursor-pointer hover:text-blue-800 max-md:!px-2 max-md:!py-1 md:min-w-64">
            <router-link :to="{ name: 'orders.show', params: { orderId: order.id } }">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-gray-900"
                    :class="order.customer.company ? 'font-bold' : 'font-semibold'">
                    <TruncatedText :text="customerName" />
                    <span v-if="order.has_made_to_order" class="rounded bg-violet-600 px-2 text-xs font-semibold text-white">
                        Na zákazku
                    </span>
                    <span v-if="order.note" class="rounded bg-amber-600 px-2 text-xs font-semibold text-white">
                        Poznámka
                    </span>
                </div>
                <div v-if="showCity" class="text-gray-500">
                    <TruncatedText :text="order.customer.city" />
                </div>
            </router-link>
        </td>

        <td class="tbody_td order-5 whitespace-nowrap font-bold !text-gray-900 max-md:!px-2 max-md:!py-1 md:text-right">
            {{ formatPrice(order.grand_total ?? order.price_sum) }} €
        </td>

        <td class="tbody_td order-6 max-md:!px-2 max-md:!py-1 md:min-w-36">
            <shipping-button :order="order" :show-shipping-action="false" />
            <div class="mt-2 text-xs text-gray-500">{{ order.created_at_human }}</div>
        </td>

        <td class="tbody_td order-7 basis-full max-md:!px-2 max-md:!py-1 md:min-w-44" title="zoznam expedícií">
            <div class="flex flex-col gap-2">
                <div v-if="order.shippings.length" class="flex flex-wrap gap-2">
                    <notice-label v-for="shipping in order.shippings" :key="shipping.id" :shipping="shipping" />
                </div>
                <div v-else class="text-xs text-gray-500">
                    Bez expedície
                </div>
            </div>
        </td>

        <td class="tbody_td order-3 w-12 max-md:ml-auto max-md:!px-2 max-md:!py-0">
            <panel-dropdown :items="dropdownItems" />
        </td>
    </tr>

    <!-- Storno modal -->
    <Teleport to="body">
        <div v-if="showStornoModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
                <h3 class="mb-1 text-lg font-semibold text-gray-800">Stornovať objednávku?</h3>
                <p class="mb-4 text-sm text-gray-500">
                    Objednávka č. <strong>{{ order.serial_number }}</strong> —
                    neexpedované položky budú označené ako stornované.
                </p>
                <label class="mb-5 flex cursor-pointer items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" v-model="notifyCustomer" class="rounded" />
                    Informovať zákazníka emailom o storne
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showStornoModal = false"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                        Zrušiť
                    </button>
                    <button type="button" @click="confirmStorno" :disabled="submitting"
                        class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50">
                        {{ submitting ? '...' : 'Potvrdiť storno' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

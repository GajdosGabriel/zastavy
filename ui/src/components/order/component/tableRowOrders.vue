<script setup>
import { computed, ref } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";
import iconStar from "../../../components/icons/star.vue";
import noticeLabel from "./noticeLabel.vue";
import shippingButton from "./shippingButton.vue";
import useOrders from "../../../store/StoreOrders";

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

const actionMap = {
    update: { to: { name: "orders.edit", params: { orderId: props.order.id } } },
    storno: { onClick: openStornoModal },
    delete: { onClick: () => destroyOrder(props.order.endpoints.destroy) },
};

const dropdownItems = computed(() => {
    const fixed = (props.order.links || []).map(link => ({
        label: link.label,
        to: { path: link.path },
    }));

    if (!Object.keys(props.order.endpoints).length) return fixed;

    const permissionItems = Object.entries(props.order.permissions || {})
        .filter(([key, perm]) => perm.allowed && actionMap[key])
        .map(([key, perm]) => ({ label: perm.label, ...actionMap[key] }));

    return [...fixed, ...permissionItems];
});
</script>

<template>
    <tr class="border-b border-gray-200 align-top transition hover:bg-gray-50" :class="[
        { 'bg-green-50': !order.isOpened },
        { 'bg-blue-50': order.isFinished },
        { 'bg-gray-200': order.isStorned },
    ]">
        <td class="tbody_td whitespace-nowrap font-semibold">
            <div class="flex items-center gap-1.5">
                <router-link :to="{ name: 'orders.shipping.edit', params: { orderId: order.id } }" class="hover:text-blue-700">
                    {{ order.serial_number }}
                </router-link>
                <span v-if="order.wants_coupon"
                    class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white"
                    title="Zákazník požaduje zľavový kupón">K</span>
            </div>
        </td>

        <td class="tbody_td w-10">
            <icon-star :status="order.mark.isActive" class="mx-auto cursor-pointer" @click="clickToMark(order)" />
        </td>

        <td class="tbody_td min-w-64 relative cursor-pointer hover:text-blue-800">
            <router-link :to="{ name: 'orders.shipping.edit', params: { orderId: order.id } }">
                <div v-if="order.customer.company" class="font-bold">
                    {{ order.customer.company.substring(0, 30) }}
                </div>
                <div v-else class="font-semibold">
                    {{ order.customer.name }}
                </div>
                <div class="font-semibold text-gray-600">
                    {{ order.customer.city }}
                    <span v-if="order.note"
                        class="absolute right-2 rounded bg-amber-500 px-2 text-xs text-white">
                        Poznámka
                    </span>
                </div>
            </router-link>
        </td>

        <td class="tbody_td whitespace-nowrap text-right font-bold">
            {{ order.price_sum }} €
        </td>

        <td class="tbody_td min-w-36">
            <div class="mb-2 text-xs text-gray-500">{{ order.created_at_human }}</div>
            <shipping-button :order="order" :show-shipping-action="false" />
        </td>

        <td class="tbody_td min-w-44" title="zoznam expedícií">
            <div class="flex flex-col gap-2">
                <shipping-button :order="order" :show-pack-status="false" />
                <div v-if="order.shippings.length" class="flex flex-wrap gap-2">
                    <notice-label v-for="shipping in order.shippings" :key="shipping.id" :shipping="shipping" />
                </div>
                <div v-else class="text-xs text-gray-400">
                    Bez expedície
                </div>
            </div>
        </td>

        <td class="tbody_td w-12">
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

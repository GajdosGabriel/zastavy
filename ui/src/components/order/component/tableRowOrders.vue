<script setup>
import { computed } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";
import iconStar from "../../../components/icons/star.vue";
import noticeLabel from "./noticeLabel.vue";
import shippingButton from "./shippingButton.vue";
import useOrders from "../../../store/StoreOrders";

const props = defineProps(["order"]);

const { destroyOrder, updateOrder, clickToMark } = useOrders();

const actionMap = {
    update: { to: { name: "orders.edit", params: { orderId: props.order.id } } },
    storno: { onClick: () => updateOrder({ id: props.order.id, makeStorned: true }) },
    delete: { onClick: () => destroyOrder(props.order.endpoints.destroy) },
};

const dropdownItems = computed(() => {
    if (!Object.keys(props.order.endpoints).length) return [];

    return Object.entries(props.order.permissions || {})
        .filter(([key, perm]) => perm.allowed && actionMap[key])
        .map(([key, perm]) => ({ label: perm.label, ...actionMap[key] }));
});
</script>

<template>
    <tr class="border-b border-gray-200 align-top transition hover:bg-gray-50" :class="[
        { 'bg-green-50': !order.isOpened },
        { 'bg-blue-50': order.isFinished },
        { 'bg-gray-200': order.isStorned },
    ]">
        <td class="tbody_td whitespace-nowrap font-semibold">
            <router-link :to="{ name: 'orders.shipping.edit', params: { orderId: order.id } }" class="hover:text-blue-700">
                {{ order.serial_number }}
            </router-link>
        </td>

        <td class="tbody_td w-10">
            <icon-star :status="order.mark.isActive" class="mx-auto cursor-pointer" @click="clickToMark(order)" />
        </td>

        <td class="tbody_td min-w-64 relative cursor-pointer hover:text-blue-800">
            <router-link :to="{ name: 'customers.orders', params: { customerId: order.customer.id } }">
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
</template>

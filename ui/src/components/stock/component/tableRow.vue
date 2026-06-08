<script setup>
import { computed } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";
import useStocks from "../../../store/StoreStocks";

const props = defineProps(["item"]);
const { destroyStock } = useStocks();

const dropdownItems = computed(() => {
    const permissions = props.item.permissions || {};

    if (!permissions.delete?.allowed) {
        return [];
    }

    return [
        {
            label: permissions.delete.label,
            onClick: () => destroyStock(props.item.id),
        },
    ];
});
</script>

<template>
    <tr class="border-2 border-gray-400">
        <td class="tbody_td">
            {{ item.shipping_id }}
        </td>
        <td class="tbody_td font-semibold">
            {{ item.name }}
        </td>

        <td class="tbody_td font-semibold">
            {{ item.shipping_created_at_human }}
        </td>

        <td class="tbody_td font-semibold">
            <router-link :to="{ name: 'orders.show', params: { orderId: item.order_id } }">
                <span class="font-bold">{{ item.company }}</span>
            </router-link>
        </td>

        <td class="tbody_td">
            <span class="font-bold">{{ item.quantity }}</span>
            {{ item.product_unit_value }}
        </td>
        <td class="tbody_td font-semibold">
            <!-- voľné -->
        </td>
        <td class="tbody_td">
            <panel-dropdown :items="dropdownItems" />
        </td>
    </tr>
</template>

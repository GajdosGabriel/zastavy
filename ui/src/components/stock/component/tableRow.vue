<script setup>
import { computed } from "vue";
import PanelDropdown from "../../layout/PanelDropdown.vue";
import useStocks from "../../../store/StoreStocks";

const props = defineProps(["item"]);
const { destroyStock } = useStocks();

const isIncoming = computed(() => props.item.type === 'incoming');

const dropdownItems = computed(() => {
    if (!props.item.permissions?.delete?.allowed) return [];
    return [{ label: props.item.permissions.delete.label, onClick: () => destroyStock(props.item.id) }];
});
</script>

<template>
    <tr class="hover:bg-gray-50 transition">
        <td class="px-4 py-3 whitespace-nowrap">
            <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="isIncoming
                    ? 'bg-green-100 text-green-700'
                    : 'bg-blue-100 text-blue-700'"
            >
                {{ isIncoming ? 'Príjem' : 'Expedícia' }}
            </span>
        </td>
        <td class="px-4 py-3">
            <div class="font-semibold text-gray-900 text-sm">{{ item.name }}</div>
            <div v-if="item.code" class="text-xs text-gray-400">{{ item.code }}</div>
        </td>
        <td class="px-4 py-3 text-sm text-gray-600">
            <template v-if="!isIncoming && item.company">
                <router-link
                    :to="{ name: 'orders.show', params: { orderId: item.order_id } }"
                    class="font-semibold text-blue-700 hover:underline"
                >
                    {{ item.company }}
                </router-link>
                <div v-if="item.order_serial" class="text-xs text-gray-400">Obj. č. {{ item.order_serial }}</div>
            </template>
            <span v-else-if="item.note" class="text-gray-500">{{ item.note }}</span>
            <span v-else class="text-gray-300">—</span>
        </td>
        <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap">
            {{ item.shipping_created_at_human }}
        </td>
        <td class="px-4 py-3 text-right whitespace-nowrap">
            <span
                class="text-sm font-bold"
                :class="isIncoming ? 'text-green-700' : 'text-blue-700'"
            >
                {{ isIncoming ? '+' : '−' }}{{ item.quantity }}
            </span>
            <span class="ml-1 text-xs text-gray-400">{{ item.product_unit_value }}</span>
        </td>
        <td class="px-4 py-3 text-right">
            <panel-dropdown :items="dropdownItems" />
        </td>
    </tr>
</template>

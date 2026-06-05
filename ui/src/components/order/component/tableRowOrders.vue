<script setup>
import PanelDropdown from "../../layout/PanelDropdown.vue";
import shippingButton from "./shippingButton.vue";
import noticeLabel from "./noticeLabel.vue";
import useOrders from "../../../store/StoreOrders";
import { computed } from "vue";
import iconStar from "../../../components/icons/star.vue";


const props = defineProps(["order"]);


const { destroyOrder, updateOrder, clickToMark } = useOrders();

const dropdownItems = computed(() => {
    const items = []

    if (!Object.keys(props.order.endpoints).length) {
        return []
    }

    if (props.order.endpoints.storno) {
        items.push({
            label: props.order.isStorned ? "Zrušiť Storno" : "Storno",
            onClick: () => updateOrder({
                id: props.order.id,
                makeStorned: true
            })
        })
    }

    if (props.order.endpoints.destroy) {
        items.push({
            label: "Zmazať",
            onClick: () => destroyOrder(props.order.endpoints.destroy)
        })
    }

    return items
})


</script>

<template>
    <tr class="border-2 border-gray-400" :class="[
        { 'bg-green-100': !order.isOpened },
        { 'bg-blue-100': order.isFinished },
        { 'bg-gray-300': order.isStorned },
    ]">
        <td class="tbody_td">
            <router-link :to="{
                name: 'orders.show',
                params: { orderId: order.id },
            }">
                {{ order.serial_number }}
            </router-link>
        </td>
        <td class="tbody_td">
            <icon-star :status="order.mark.isActive" class="mx-auto cursor-pointer" @click="clickToMark(order)" />
        </td>

        <td class="tbody_td relative cursor-pointer hover:text-blue-800">
            <router-link :to="{
                name: 'orders.show',
                params: { orderId: order.id },
            }">
                <div v-if="order.customer.company" class="font-bold">
                    {{ order.customer.company.substring(0, 30) }}
                </div>
                <div v-else class="font-semibold">
                    {{ order.customer.name }}
                </div>
                <div class="font-semibold">
                    {{ order.customer.city }}
                    <span class="bg-red-600 text-gray-200 rounded-md px-2 text-xs absolute right-0"
                        v-if="order.notices.length">Poznámka</span>
                </div>
            </router-link>
        </td>
        <td class="tbody_td font-bold">
            {{ order.price_sum }} €
        </td>
        <td class="tbody_td">
            {{ order.created_at_human }}
            <shipping-button :order="order" />
        </td>

        <td class="tbody_td" title="zoznam expedícií">
            <div v-for="shipping in order.shippings" :key="shipping.id" class="">
                <notice-label :shipping="shipping" />
            </div>
        </td>

        <td class="tbody_td">
            <panel-dropdown :items="dropdownItems" />
        </td>
    </tr>
</template>

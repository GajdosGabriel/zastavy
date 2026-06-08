<script setup>
import { computed } from "vue";
import useOrders from "../../../store/StoreOrders";

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    showPackStatus: {
        type: Boolean,
        default: true,
    },
    showShippingAction: {
        type: Boolean,
        default: true,
    },
});

const { updateOrder } = useOrders();
const shippingPercentage = computed(() => Number(props.order.shipping_percentage ?? 0));
const remainingQuantity = computed(() => Number(props.order.shipping_remaining_quantity ?? 0));
const shippedQuantity = computed(() => Number(props.order.stock_expedition ?? 0));
const requiredQuantity = computed(() => Number(props.order.shipping_required_quantity ?? 0));
const statusLabel = computed(() => props.order.shipping_status_label ?? (props.order.isFinished ? "Vybavená" : "Nevybavená"));

const markAsDelivered = async () => {
    if (props.order.isStorned) {
        return alert("Položka je stornovaná");
    }

    if (props.order.isDelivered == 1) {
        return;
    }

    await updateOrder({
        id: props.order.id,
        isDelivered: 1,
    });
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <button v-if="showPackStatus && !order.isStorned" type="button" @click="markAsDelivered" :title="order.created_at"
            class="inline-flex min-w-28 items-center justify-center rounded border px-2 py-1 text-xs font-semibold transition"
            :class="{
                'border-green-600 bg-green-600 text-white': order.isDelivered == 1,
                'border-gray-300 bg-white text-gray-700 hover:border-green-500 hover:bg-green-50': order.isDelivered != 1,
            }">
            {{ order.isDelivered == 1 ? "Dodané" : "Dodané" }} {{ order.shippintPercentageCalculator }}
        </button>

        <router-link v-if="showShippingAction && !order.isFinished && !order.isStorned"
            :to="{ name: 'orders.shipping.edit', params: { orderId: order.id } }"
            class="inline-flex min-w-24 items-center justify-center rounded bg-blue-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-blue-700">
            Expedovať
        </router-link>

        <span v-if="showPackStatus && order.isStorned"
            class="inline-flex min-w-28 items-center justify-center rounded bg-gray-700 px-2 py-1 text-xs font-semibold text-white">
            Stornovaná
        </span>

        <span v-if="showPackStatus && !order.orderProducts?.length"
            class="inline-flex min-w-28 items-center justify-center rounded bg-green-700 px-2 py-1 text-xs font-semibold text-white"
            title="Objednávka je prázdna">
            Prázdna
        </span>

        <span v-if="showPackStatus && order.isDeleted"
            class="inline-flex min-w-28 items-center justify-center rounded bg-red-700 px-2 py-1 text-xs font-semibold text-white">
            Obnoviť
        </span>
    </div>

    <!-- <div v-if="showPackStatus" class="mt-2 min-w-44 text-xs text-gray-600">
        <div class="mb-1 flex items-center justify-between gap-2">
            <span class="font-semibold">{{ statusLabel }}</span>
            <span>{{ shippedQuantity }}/{{ requiredQuantity }} ks</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-200">
            <div class="h-full rounded-full transition-all"
                :class="order.isFinished ? 'bg-green-600' : shippedQuantity > 0 ? 'bg-amber-500' : 'bg-red-500'"
                :style="{ width: `${shippingPercentage}%` }"></div>
        </div>
        <div class="mt-1 flex items-center justify-between gap-2">
            <span>{{ shippingPercentage }} %</span>
            <span>Ostáva {{ remainingQuantity }} ks</span>
        </div>
    </div> -->
</template>

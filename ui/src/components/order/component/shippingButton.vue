<script setup>
import { computed } from "vue";

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

const shippingPercentage = computed(() => Number(props.order.shipping_percentage ?? 0));
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">

        <!-- Stornovaná -->
        <span v-if="showPackStatus && order.isStorned"
            class="inline-flex min-w-28 items-center justify-center rounded bg-gray-700 px-2 py-1 text-xs font-semibold text-white">
            Stornovaná
        </span>

        <!-- Prázdna -->
        <span v-else-if="showPackStatus && !order.orderProducts?.length"
            class="inline-flex min-w-28 items-center justify-center rounded bg-green-700 px-2 py-1 text-xs font-semibold text-white"
            title="Objednávka je prázdna">
            Prázdna
        </span>

        <!-- Pripravená na odoslanie — zabalená, čaká na vyzdvihnutie kuriérom -->
        <span v-else-if="showPackStatus && order.status?.value === 'ready_to_ship'"
            class="inline-flex min-w-28 items-center justify-center rounded bg-indigo-600 px-2 py-1 text-xs font-semibold text-white">
            Pripravené na odoslanie
        </span>

        <!-- Vybavená 100% — plne expedovaná -->
        <span v-else-if="showPackStatus && order.isFinished"
            class="inline-flex min-w-28 items-center justify-center rounded bg-green-600 px-2 py-1 text-xs font-semibold text-white">
            Vybavená 100 %
        </span>

        <!-- Čiastočne alebo neexpedovaná — stále aktívna -->
        <span v-else-if="showPackStatus"
            class="inline-flex min-w-28 items-center justify-center rounded border px-2 py-1 text-xs font-semibold"
            :class="shippingPercentage > 0
                ? 'border-amber-400 bg-amber-50 text-amber-700'
                : 'border-gray-300 bg-white text-gray-600'">
            {{ order.shippintPercentageCalculator }}
        </span>

        <!-- Tlačidlo Expedovať -->
        <router-link v-if="showShippingAction && !order.isFinished && !order.isStorned"
            :to="{ name: 'orders.shipping.edit', params: { orderId: order.id } }"
            class="inline-flex min-w-24 items-center justify-center rounded bg-blue-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-blue-700">
            Expedovať
        </router-link>

        <span v-if="showPackStatus && order.isDeleted"
            class="inline-flex min-w-28 items-center justify-center rounded bg-red-700 px-2 py-1 text-xs font-semibold text-white">
            Obnoviť
        </span>
    </div>
</template>

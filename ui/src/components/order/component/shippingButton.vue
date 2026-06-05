<script setup>
import { ref } from "vue";
import useOrders from "../../../store/StoreOrders";
import useShippings from "../../../store/StoreShippings";

const props = defineProps(["order"]);

const { fetchOrder } = useOrders();
const { shipping, storeShipping } = useShippings();
const emailNotify = ref("");

const onClickShipping = async (order) => {

    if (order.isStorned) {
        return alert("Položka je stornovaná");
    }
    if (
        !window.confirm(
            order.isFinished
                ? "Objednávka už bola expedovaná!"
                : "Označiť objednávku ako odoslanú!"
        )
    ) {
        return;
    }
    storeShipping(order);
};

</script>

<template>
    <div v-if="!order.isStorned" class="flex justify-between">
        <div class="flex items-center">
            <div @click="onClickShipping(order)" :disabled="order.isFinished" :title="order.created_at"
                class="hover:bg-blue-400 hover:text-gray-200 px-2 rounded-md border-2 cursor-pointer" :class="{
                    'bg-blue-500 rounded-md border-2 px-2 text-gray-200':
                        order.isFinished,
                }">
                {{ order.isFinished ? "Expedovaná" : "Dodané " + order.shippintPercentageCalculator }}
            </div>
        </div>
    </div>

    <button v-else class="bg-gray-700 text-gray-200 px-2 rounded-md float-right" disabled>
        Stornovaná
    </button>

    <button v-if="!order.orderProducts?.length" class="bg-green-700 text-gray-200 px-2 rounded-md float-right"
        title="Objednávka je prázdna" disabled>
        Prázdna
    </button>

    <button v-if="order.isDeleted" class="bg-red-700 text-gray-200 px-2 rounded-md float-right" disabled>
        Obnoviť
    </button>

    <svg v-if="order.isDelivered !== null" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
        :class="{ 'text-red-400': order.isDelivered == 0 }" fill="none" viewBox="0 0 24 24" stroke="currentColor"
        stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
            d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11" />
    </svg>
</template>

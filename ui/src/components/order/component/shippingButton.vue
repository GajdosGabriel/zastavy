<script setup>
import { ref } from "vue";
import useOrders from "../../../store/StoreOrders";
import useShippings from "../../../store/StoreShippings";

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
const { storeShipping } = useShippings();
const notifyCustomer = ref(true);
const showShippingModal = ref(false);

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

const onClickShipping = () => {
    if (props.order.isStorned) {
        return alert("Položka je stornovaná");
    }

    if (props.order.isFinished) {
        return alert("Objednávka už bola expedovaná!");
    }

    notifyCustomer.value = true;
    showShippingModal.value = true;
};

const closeShippingModal = () => {
    showShippingModal.value = false;
};

const confirmShipping = async () => {
    await storeShipping(props.order, {
        notify_customer: notifyCustomer.value,
    });

    closeShippingModal();
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
            {{ order.isDelivered == 1 ? "Zabalené" : "Zabaliť" }} {{ order.shippintPercentageCalculator }}
        </button>

        <button v-if="showShippingAction && !order.isFinished && !order.isStorned" type="button" @click="onClickShipping"
            class="inline-flex min-w-24 items-center justify-center rounded bg-blue-600 px-3 py-1 text-xs font-semibold text-white transition hover:bg-blue-700">
            Expedovať
        </button>

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

    <svg v-if="showPackStatus && order.isDelivered !== null" xmlns="http://www.w3.org/2000/svg" class="mt-1 h-4 w-4"
        :class="{ 'text-red-400': order.isDelivered == 0, 'text-green-600': order.isDelivered == 1 }" fill="none"
        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
            d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3m0 0V11" />
    </svg>

    <Teleport to="body">
        <div v-if="showShippingModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
            <div class="w-full max-w-sm rounded bg-white p-5 shadow-lg">
                <h3 class="mb-3 text-lg font-semibold text-gray-800">
                    Expedícia objednávky
                </h3>
                <p class="mb-4 text-sm text-gray-600">
                    Označiť objednávku ako odoslanú?
                </p>
                <label class="mb-5 flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" v-model="notifyCustomer" class="rounded" />
                    Poslať email zákazníkovi
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="closeShippingModal"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                        Zrušiť
                    </button>
                    <button type="button" @click="confirmShipping"
                        class="rounded bg-blue-500 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Potvrdiť
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

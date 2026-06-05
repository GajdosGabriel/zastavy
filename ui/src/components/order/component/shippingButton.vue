<script setup>
import { ref } from "vue";
import useShippings from "../../../store/StoreShippings";

const props = defineProps(["order"]);

const { storeShipping } = useShippings();
const notifyCustomer = ref(true);
const showShippingModal = ref(false);

const onClickShipping = () => {
    if (props.order.isStorned) {
        return alert("Polozka je stornovana");
    }

    if (props.order.isFinished) {
        return alert("Objednavka uz bola expedovana!");
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
    <div v-if="!order.isStorned" class="flex justify-between">
        <div class="flex items-center">
            <div @click="onClickShipping" :disabled="order.isFinished" :title="order.created_at"
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

<script setup>
import { ref } from "vue";
import iconEmail from "../../../components/icons/email.vue";
import useNotices from "../../../store/StoreNotices";
import useOrders from "../../../store/StoreOrders";

const props = defineProps(["shipping"]);

const { storeShippingNotice } = useNotices();
const { fetchOrders } = useOrders();
const notifyCustomer = ref(true);
const showNoticeModal = ref(false);

const onClickShipping = () => {
    if (props.shipping.notices.length) {
        alert("Notifikácia už bola zaslaná.");
        return;
    }

    notifyCustomer.value = true;
    showNoticeModal.value = true;
};

const closeNoticeModal = () => {
    showNoticeModal.value = false;
};

const confirmNotice = async () => {
    if (!notifyCustomer.value) {
        closeNoticeModal();
        return;
    }

    await storeShippingNotice(props.shipping, {
        notifyType: "email",
    });

    await fetchOrders();
    closeNoticeModal();
};
</script>

<template>
    <div v-if="shipping" class="flex">
        <button @click="onClickShipping"
            class="rounded-md border-2 bg-white px-2 hover:bg-blue-500 hover:text-gray-200">
            {{ shipping.id }}
        </button>

        <div v-for="notice in shipping.notices" :key="notice.id" class="ml-2 text-center">
            <div class="label lable-green" :title="notice.created_at">
                <icon-email />
                <div style="font-size: .7rem;">
                    {{ notice.created_at_human }}
                </div>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="showNoticeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
            <div class="w-full max-w-sm rounded bg-white p-5 shadow-lg">
                <h3 class="mb-3 text-lg font-semibold text-gray-800">
                    Notifikácia expedície
                </h3>
                <p class="mb-4 text-sm text-gray-600">
                    Expedícia je už zaznamenaná. Môžete k nej odoslať notifikáciu zákazníkovi.
                </p>
                <label class="mb-5 flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" v-model="notifyCustomer" class="rounded" />
                    Poslať email zákazníkovi
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="closeNoticeModal"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                        Zrušiť
                    </button>
                    <button type="button" @click="confirmNotice"
                        class="rounded bg-blue-500 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Potvrdiť
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

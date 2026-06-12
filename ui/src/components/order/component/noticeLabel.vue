<script setup>
import { ref } from "vue";
import useNotices from "../../../store/StoreNotices";
import useOrders from "../../../store/StoreOrders";
import SpinnerButton from "../../icons/spinnerButton.vue";
import loadingStore from "../../../store/StoreLoading";

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

const closeNoticeModal = () => { showNoticeModal.value = false; };

const confirmNotice = async () => {
    if (!notifyCustomer.value) { closeNoticeModal(); return; }
    await storeShippingNotice(props.shipping, { notifyType: "email" });
    await fetchOrders();
    closeNoticeModal();
};
</script>

<template>
    <div v-if="shipping" class="inline-flex items-center gap-1.5 rounded border border-gray-200 bg-white px-2 py-1 shadow-sm">

        <!-- Ikona dodacieho listu + ID v title -->
        <button @click="onClickShipping"
            :title="`DL #${shipping.id}`"
            class="flex items-center gap-1 text-blue-700 hover:text-blue-900">
            <!-- delivery van icon -->
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="1" y="3" width="15" height="13" rx="1" stroke-linejoin="round"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 8h4.5L23 12v4h-7V8z"/>
                <circle cx="5.5" cy="18.5" r="2"/>
                <circle cx="18.5" cy="18.5" r="2"/>
            </svg>
        </button>

        <!-- Odoslaný email -->
        <div v-for="notice in shipping.notices" :key="notice.id">
            <div class="flex items-center gap-1 text-xs text-green-700" :title="notice.created_at">
                <!-- envelope icon -->
                <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span>{{ notice.created_at_human }}</span>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div v-if="showNoticeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
            <div class="w-full max-w-sm rounded bg-white p-5 shadow-lg">
                <h3 class="mb-3 text-lg font-semibold text-gray-800">Notifikácia expedície</h3>
                <p class="mb-4 text-sm text-gray-600">
                    Expedícia je zaznamenaná. Odoslať zákazníkovi email o expedícii?
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
                        :disabled="loadingStore.isLoading"
                        class="inline-flex items-center gap-2 rounded bg-blue-500 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        <SpinnerButton v-if="loadingStore.isLoading" />
                        Potvrdiť
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

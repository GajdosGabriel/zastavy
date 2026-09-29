<script setup>
import { computed, ref } from "vue";
import { useNotices } from "../../../store/StoreNotices";
import useOrders from "../../../store/StoreOrders";
import SpinnerButton from "../../icons/spinnerButton.vue";
import loadingStore from "../../../store/StoreLoading";

const props = defineProps(["shipping"]);

const { storeShippingNotice } = useNotices();
const { fetchOrders } = useOrders();
const notifyCustomer = ref(true);
const showNoticeModal = ref(false);

const notices = computed(() => props.shipping?.notices ?? []);
// Notifikácia emailom môže prísť aj dodatočne, preto ju hľadáme naprieč záznamami.
const emailNotice = computed(() => notices.value.find((notice) => notice.notice === (props.shipping?.is_preparing ? "preparation_email" : "email")) ?? null);
const lastNotice = computed(() => notices.value.filter(n => props.shipping?.is_preparing || n.notice !== "preparation_email").at(-1) ?? null);

const onClickShipping = () => {
    notifyCustomer.value = !emailNotice.value;
    showNoticeModal.value = true;
};

const closeNoticeModal = () => { showNoticeModal.value = false; };

const saveNotice = async (notifyType) => {
    await storeShippingNotice(props.shipping, { notifyType });
    await fetchOrders();
    closeNoticeModal();
};

const confirmNotice = () => saveNotice(notifyCustomer.value ? "email" : "none");
</script>

<template>
    <div v-if="shipping" class="inline-flex items-center gap-1.5 rounded border border-gray-200 bg-white px-2 py-1 shadow-sm">

        <!-- Ikona dodacieho listu + ID v title -->
        <button @click="onClickShipping"
            :title="emailNotice
                ? `DL #${shipping.id} – notifikácia odoslaná`
                : (lastNotice ? `DL #${shipping.id} – ${shipping.is_preparing ? 'príprava' : 'odoslanie'} bez emailu` : `DL #${shipping.id}`)"
            :class="emailNotice
                ? 'text-green-700 hover:text-green-900'
                : (lastNotice ? 'text-gray-500 hover:text-gray-700' : 'text-blue-700 hover:text-blue-900')"
            class="flex cursor-pointer items-center gap-1">
            <!-- package with ribbon icon (tovar zabalený/expedovaný, prepravca ešte neprevzal) -->
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 12v9a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-9"/>
                <rect x="2" y="7" width="20" height="5" rx="1"/>
                <path d="M12 22V7"/>
                <path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/>
                <path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>
            </svg>
            <span v-if="lastNotice" class="text-xs">{{ (emailNotice ?? lastNotice).created_at_human }}</span>
        </button>
    </div>

    <Teleport to="body">
        <div v-if="showNoticeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
            <div class="w-full max-w-sm rounded bg-white p-5 shadow-lg">
                <h3 class="mb-3 text-lg font-semibold text-gray-800">{{ shipping.is_preparing ? "Notifikácia prípravy v sklade" : "Notifikácia odoslania" }}</h3>

                <template v-if="emailNotice">
                    <p class="mb-5 text-sm text-gray-600">
                        {{ shipping.is_preparing ? "Informácia o príprave v sklade" : "Informácia o odoslaní" }} už bola zákazníkovi odoslaná
                        <span class="font-semibold">{{ emailNotice.created_at_human }}</span>.
                    </p>
                    <div class="flex justify-end">
                        <button type="button" @click="closeNoticeModal"
                            class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                            Zavrieť
                        </button>
                    </div>
                </template>

                <template v-else-if="lastNotice">
                    <p class="mb-5 text-sm text-gray-600">
                        {{ shipping.is_preparing ? "Príprava bola zaznamenaná" : "Odoslanie bolo zaznamenané" }} <span class="font-semibold">{{ lastNotice.created_at_human }}</span>
                        bez emailu zákazníkovi.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="closeNoticeModal"
                            class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                            Zavrieť
                        </button>
                        <button type="button" @click="saveNotice('email')"
                            :disabled="loadingStore.isLoading"
                            class="inline-flex items-center gap-2 rounded bg-blue-500 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                            <SpinnerButton v-if="loadingStore.isLoading" />
                            Odoslať email
                        </button>
                    </div>
                </template>

                <template v-else>
                    <p class="mb-4 text-sm text-gray-600">
                        {{ shipping.is_preparing ? "Objednávka sa pripravuje v sklade. Odoslať zákazníkovi e-mail o príprave?" : "Zásielka bola odoslaná. Odoslať zákazníkovi potvrdenie?" }}
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
                </template>
            </div>
        </div>
    </Teleport>
</template>

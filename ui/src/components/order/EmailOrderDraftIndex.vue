<script setup>
import { computed, onMounted, ref } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import axiosInstance from "../../axiosInstance";
import useErrors from "../../store/StoreErrors";
import useFlash from "../../store/StoreFlash";
import router from "../../router";
import { formatFileSize } from "../../models/functions";

// Objednávky a dopyty z e-mailu: maily na obchod@ mimo vlákna objednávky, ktoré AI
// rozpoznala ako objednávku alebo dopyt na ponuku. Dopyty a úplné objednávky vznikli
// samy (záložka Vybavené), ostatné obsluha otvorí v košíku, doplní a odošle.
const tabs = [
    { key: "open", label: "Na spracovanie" },
    { key: "created", label: "Vybavené" },
    { key: "other", label: "Nie sú objednávky" },
];
const statusLabels = {
    pending: "Spracúva sa",
    draft: "Návrh",
    failed: "Nespracované",
    created: "Vybavené",
    not_order: "Nie je objednávka",
    dismissed: "Zahodené",
};
const customerLabels = { existing: "existujúci zákazník", new: "nový zákazník", ambiguous: "nejednoznačný zákazník" };

const tab = ref("open");
const drafts = ref([]);
const counts = ref({});
const loading = ref(false);
const detail = ref(null);
const busy = ref(false);

const load = async () => {
    loading.value = true;
    try {
        const { data } = await axiosInstance.get("/email-order-drafts", { params: { tab: tab.value } });
        drafts.value = data.data;
        counts.value = data.counts;
    } catch (e) {
        useErrors().setErrors(e);
    } finally {
        loading.value = false;
    }
};

const selectTab = (key) => {
    tab.value = key;
    detail.value = null;
    load();
};

const open = async (draft) => {
    try {
        const { data } = await axiosInstance.get(`/email-order-drafts/${draft.id}`);
        detail.value = data.data;
    } catch (e) {
        useErrors().setErrors(e);
    }
};

const setStatus = async (status) => {
    busy.value = true;
    try {
        const { data } = await axiosInstance.patch(`/email-order-drafts/${detail.value.id}`, { status });
        detail.value = data.data;
        await load();
    } catch (e) {
        useErrors().setErrors(e);
    } finally {
        busy.value = false;
    }
};

const retry = async () => {
    busy.value = true;
    try {
        const { data } = await axiosInstance.post(`/email-order-drafts/${detail.value.id}/retry`);
        useFlash().success(data.message);
        detail.value = null;
        await load();
    } catch (e) {
        useErrors().setErrors(e);
    } finally {
        busy.value = false;
    }
};

const download = async (attachment) => {
    try {
        const response = await axiosInstance.get(`/email-order-drafts/${detail.value.id}/attachments/${attachment.id}`, { responseType: "blob" });
        const url = URL.createObjectURL(response.data);
        const link = Object.assign(document.createElement("a"), { href: url, download: attachment.name });
        link.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        useErrors().setErrors(e);
    }
};

const openInCart = () => router.push({ path: "/kosik", query: { email: detail.value.id } });
const canCreate = computed(() => detail.value && !detail.value.order && detail.value.preview);

onMounted(() => {
    document.title = "Objednávky a dopyty z e-mailu";
    load();
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Objednávky a dopyty z e-mailu', buttonLink: { name: 'Späť', link: '/objednavky', icon: 'arrow-left' } }" />

                <div class="mb-4 flex flex-wrap gap-2" role="tablist">
                    <button v-for="item in tabs" :key="item.key" type="button" role="tab" :aria-selected="tab === item.key"
                        class="rounded-full border px-3 py-1 text-sm font-semibold"
                        :class="tab === item.key ? 'border-blue-700 bg-blue-700 text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                        @click="selectTab(item.key)">
                        {{ item.label }} <span class="ml-1 opacity-80">{{ counts[item.key] ?? 0 }}</span>
                    </button>
                </div>

                <div class="grid gap-4 lg:grid-cols-5">
                    <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white shadow-sm lg:col-span-2">
                        <li v-if="loading" class="p-4 text-sm text-gray-500">Načítavam…</li>
                        <li v-else-if="!drafts.length" class="p-4 text-sm text-gray-500">Žiadne e-maily.</li>
                        <li v-for="draft in drafts" v-else :key="draft.id">
                            <button type="button" class="block w-full px-4 py-3 text-left hover:bg-gray-50"
                                :class="detail?.id === draft.id ? 'bg-blue-50' : ''" @click="open(draft)">
                                <span class="flex items-baseline justify-between gap-2">
                                    <span class="truncate text-sm font-semibold text-gray-900">{{ draft.customer || draft.from_name || draft.from_email }}</span>
                                    <span class="shrink-0 text-xs text-gray-500">{{ draft.received_at }}</span>
                                </span>
                                <span class="block truncate text-xs text-gray-600">{{ draft.subject || '(bez predmetu)' }}</span>
                                <span class="mt-1 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-700">{{ statusLabels[draft.status] }}</span>
                                    <span v-if="draft.items_count" class="text-gray-500">položky {{ draft.items_matched }}/{{ draft.items_count }}</span>
                                    <span v-if="draft.attachments_count" class="text-gray-500">📎 {{ draft.attachments_count }}</span>
                                    <span v-if="draft.order" class="font-semibold text-green-700">{{ draft.order.serial_number }}{{ draft.auto_created ? ' · automaticky' : '' }}</span>
                                    <span v-if="draft.quote" class="font-semibold text-violet-700">dopyt {{ draft.quote.number }}</span>
                                </span>
                            </button>
                        </li>
                    </ul>

                    <section v-if="detail" class="space-y-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:col-span-3" aria-live="polite">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ detail.subject || '(bez predmetu)' }}</h2>
                            <p class="text-sm text-gray-600">{{ detail.from_name }} &lt;{{ detail.from_email }}&gt; · {{ detail.received_at }}</p>
                        </div>

                        <p v-if="detail.order" class="rounded bg-green-50 px-3 py-2 text-sm text-green-800">
                            Objednávka
                            <router-link :to="`/objednavky/${detail.order.id}/show`" class="font-semibold underline">{{ detail.order.serial_number }}</router-link>
                            {{ detail.auto_created ? 'vznikla automaticky.' : 'vznikla z košíka.' }}
                        </p>
                        <p v-else-if="detail.quote" class="rounded bg-violet-50 px-3 py-2 text-sm text-violet-800">
                            Dopyt na cenovú ponuku – založená ponuka
                            <router-link :to="`/predaj/ponuky/${detail.quote.id}`" class="font-semibold underline">{{ detail.quote.number }}</router-link>.
                        </p>
                        <p v-else-if="detail.error" class="rounded bg-red-50 px-3 py-2 text-sm text-red-800">{{ detail.error }}</p>

                        <div v-if="detail.preview" class="rounded border border-gray-200 p-3 text-sm">
                            <p class="font-semibold text-gray-800">
                                {{ detail.preview.customer.company || detail.preview.customer.name || 'Zákazník nerozpoznaný' }}
                                <span class="font-normal text-gray-500">— {{ customerLabels[detail.preview.customer_status] }}</span>
                            </p>
                            <ul class="mt-2 space-y-1">
                                <li v-for="(item, index) in detail.preview.items" :key="index" :class="item.cart ? 'text-gray-800' : 'text-amber-800'">
                                    <template v-if="item.cart">{{ item.cart.input_order }} × {{ item.cart.name }} — {{ item.cart.variant_name }}</template>
                                    <template v-else>{{ item.description }} — nespárované</template>
                                </li>
                            </ul>
                        </div>

                        <div v-if="detail.reasons.length && !detail.order && !detail.quote">
                            <h3 class="text-sm font-semibold text-gray-700">Prečo nevznikla automaticky</h3>
                            <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-amber-900">
                                <li v-for="reason in detail.reasons" :key="reason">{{ reason }}</li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Pôvodný e-mail</h3>
                            <pre class="mt-1 max-h-96 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-3 font-sans text-sm text-gray-800">{{ detail.body }}</pre>
                        </div>

                        <div v-if="detail.attachments.length">
                            <h3 class="text-sm font-semibold text-gray-700">Prílohy</h3>
                            <ul class="mt-1 space-y-1 text-sm">
                                <li v-for="file in detail.attachments" :key="file.id">
                                    <button type="button" class="text-blue-700 underline" @click="download(file)">{{ file.name }}</button>
                                    <span class="text-gray-500"> · {{ formatFileSize(file.size) }}</span>
                                </li>
                            </ul>
                        </div>

                        <div v-if="!detail.order && !detail.quote" class="flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                            <button v-if="canCreate" type="button" :disabled="busy" class="rounded bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:opacity-50" @click="openInCart">Otvoriť v košíku</button>
                            <button v-if="detail.status === 'not_order' || detail.status === 'failed' || !detail.preview" type="button" :disabled="busy" class="rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50" @click="retry">Spracovať znova</button>
                            <button v-if="detail.status !== 'not_order'" type="button" :disabled="busy" class="rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50" @click="setStatus('not_order')">Nie je objednávka</button>
                            <button v-if="detail.status === 'not_order' && detail.preview" type="button" :disabled="busy" class="rounded border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50" @click="setStatus('draft')">Vrátiť medzi návrhy</button>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>

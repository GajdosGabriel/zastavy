<script setup>
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import axiosInstance from '../../axiosInstance';
import { PAGE_STOCK } from '../../constants';
import { formatPrice } from '../../models/functions';
import { confirmDialog } from '../../models/confirmDialog';
import useErrors from '../../store/StoreErrors';
import useFlash from '../../store/StoreFlash';
import BaseLayout from '../layout/BaseLayout.vue';
import PageHeader from '../layout/page/pageHeader.vue';

const route = useRoute();
const receipt = ref(null);
const loading = ref(false);
const failed = ref(false);
const cancelling = ref(false);
const reason = ref('');
const date = (value) => value ? value.split('-').reverse().join('.') : '—';
const print = () => window.print();
const load = async () => {
    receipt.value = null;
    failed.value = false;
    loading.value = true;
    const id = route.params.receiptId;
    try {
        const { data } = await axiosInstance.get(`${PAGE_STOCK.URL}/receipts/${id}`);
        if (id === route.params.receiptId) receipt.value = data.data;
    } catch (error) { failed.value = true; useErrors().setErrors(error); }
    finally { loading.value = false; }
};
watch(() => route.params.receiptId, load, { immediate: true });
const cancel = async () => {
    if (cancelling.value || !reason.value.trim() || !receipt.value || receipt.value.cancelled_at) return;
    if (!await confirmDialog({ title: 'Stornovať príjemku?', message: `Storno ${receipt.value.number} odpočíta všetky položky dokladu zo skladu. Doklad zostane v evidencii.`, confirmLabel: 'Stornovať príjemku', tone: 'danger' })) return;
    cancelling.value = true;
    try {
        const { data } = await axiosInstance.post(`${PAGE_STOCK.URL}/receipts/${receipt.value.id}/cancel`, { reason: reason.value.trim() });
        receipt.value = data.data;
        useFlash().success('Príjemka bola stornovaná a sklad bol upravený.');
    } catch (error) { useErrors().setErrors(error); }
    finally { cancelling.value = false; }
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12 receipt-page">
                <div class="receipt-actions"><PageHeader :item="{ title: receipt?.number || 'Príjemka', buttonLink: { name: 'Príjemky', link: 'stocks.receipts.index', icon: 'arrow-left' } }" /></div>
                <p v-if="loading" class="p-6 text-gray-500">Načítavam príjemku…</p>
                <p v-else-if="failed" class="p-6 text-red-600">Príjemku sa nepodarilo načítať. <button type="button" class="underline" @click="load">Skúsiť znova</button></p>
                <template v-else-if="receipt">
                    <div class="receipt-actions mb-5 flex justify-end gap-3"><router-link :to="{ name: 'stocks.create' }" class="rounded-lg border px-4 py-2 text-sm">Nová príjemka</router-link><button type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="print">Vytlačiť / uložiť PDF</button></div>
                    <article class="receipt-document rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-6 flex flex-wrap justify-between gap-4 border-b pb-5"><div><p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Skladový doklad</p><h1 class="text-2xl font-bold">Príjemka {{ receipt.number }}</h1></div><div class="text-right text-sm"><div>Dátum príjmu: <strong>{{ date(receipt.received_at) }}</strong></div><div :class="receipt.cancelled_at ? 'text-red-700' : 'text-green-700'" class="mt-2 font-semibold">{{ receipt.cancelled_at ? 'STORNOVANÁ' : 'Naskladnená' }}</div></div></div>
                        <div v-if="receipt.cancelled_at" class="mb-5 rounded-lg bg-red-50 p-4 text-sm text-red-700">Storno: {{ new Date(receipt.cancelled_at).toLocaleString('sk-SK') }} · {{ receipt.cancellation_reason }}</div>
                        <div class="mb-6 grid gap-6 sm:grid-cols-2">
                            <div><h2 class="mb-2 text-xs font-semibold uppercase text-gray-500">Dodávateľ</h2><p class="font-semibold">{{ receipt.supplier }}</p><p class="whitespace-pre-line text-sm">{{ receipt.supplier_address }}</p><p v-if="receipt.supplier_ico" class="text-sm">IČO: {{ receipt.supplier_ico }}</p><p v-if="receipt.supplier_dic" class="text-sm">DIČ: {{ receipt.supplier_dic }}</p><p v-if="receipt.supplier_vat_id" class="text-sm">IČ DPH: {{ receipt.supplier_vat_id }}</p></div>
                            <dl class="space-y-2 text-sm"><div><dt class="text-gray-500">Sklad / miesto príjmu</dt><dd class="font-semibold">{{ receipt.warehouse }}</dd></div><div><dt class="text-gray-500">Dodací list / faktúra</dt><dd>{{ receipt.document_number || '—' }}</dd></div><div><dt class="text-gray-500">Prevzal</dt><dd>{{ receipt.received_by }}</dd></div></dl>
                        </div>
                        <div class="receipt-table overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-y bg-gray-50 text-left text-xs text-gray-500"><th class="p-2"># / Tovar</th><th class="p-2 text-right">Množstvo</th><th class="p-2 text-right">Cena bez DPH</th><th class="p-2 text-right">Zľava</th><th class="p-2 text-right">DPH</th><th class="p-2 text-right">Bez DPH</th><th class="p-2 text-right">S DPH</th></tr></thead><tbody><tr v-for="(item, index) in receipt.items" :key="item.id" class="border-b"><td class="p-2"><div class="font-semibold">{{ index + 1 }}. {{ item.name }}</div><div>{{ item.variant_name }}</div><div class="text-xs text-gray-500">{{ item.code }}</div><p v-if="item.note" class="mt-1 text-xs text-gray-500">{{ item.note }}</p></td><td class="p-2 text-right">{{ item.quantity }} {{ item.unit || 'ks' }}</td><td class="p-2 text-right">{{ formatPrice(item.price) }} €</td><td class="p-2 text-right">{{ item.discount }} %</td><td class="p-2 text-right">{{ item.vat }} %</td><td class="p-2 text-right">{{ formatPrice(item.net) }} €</td><td class="p-2 text-right font-semibold">{{ formatPrice(item.total) }} €</td></tr></tbody></table></div>
                        <div class="mt-6 grid gap-6 sm:grid-cols-2"><div><h2 class="mb-2 text-sm font-semibold">Rekapitulácia DPH</h2><table class="w-full text-sm"><thead><tr class="border-b text-left text-xs text-gray-500"><th class="py-2">Sadzba</th><th class="text-right">Základ</th><th class="text-right">DPH</th></tr></thead><tbody><tr v-for="row in receipt.vat_summary" :key="row.rate"><td class="py-1">{{ row.rate }} %</td><td class="text-right">{{ formatPrice(row.net) }} €</td><td class="text-right">{{ formatPrice(row.tax) }} €</td></tr></tbody></table></div><dl class="space-y-2 text-sm"><div class="flex justify-between"><dt>Celkom bez DPH</dt><dd>{{ formatPrice(receipt.net) }} €</dd></div><div class="flex justify-between"><dt>DPH</dt><dd>{{ formatPrice(receipt.tax) }} €</dd></div><div class="flex justify-between border-t pt-3 text-lg font-bold"><dt>Celkom s DPH</dt><dd>{{ formatPrice(receipt.total) }} €</dd></div></dl></div>
                        <div v-if="receipt.note" class="mt-6 border-t pt-4"><h2 class="text-sm font-semibold">Poznámka</h2><p class="whitespace-pre-wrap break-words text-sm">{{ receipt.note }}</p></div>
                        <div class="mt-8 grid grid-cols-2 gap-6 border-t pt-4 text-sm"><div>Vystavil: {{ receipt.created_by_name }}<p class="mt-1 text-xs text-gray-500">{{ new Date(receipt.created_at).toLocaleString('sk-SK') }}</p></div><div>Prevzal: {{ receipt.received_by }}<div class="mt-8 border-t pt-1 text-xs text-gray-400">Podpis</div></div></div>
                    </article>
                    <form v-if="!receipt.cancelled_at" class="receipt-actions mt-6 rounded-xl border border-red-100 bg-white p-5" @submit.prevent="cancel"><label for="cancel-reason" class="mb-2 block text-sm font-semibold">Storno príjemky</label><p class="mb-3 text-xs text-gray-500">Stornuje sa celý doklad a odpočíta sa prijaté množstvo. Uveďte dôvod.</p><div class="flex flex-wrap gap-3"><input id="cancel-reason" v-model="reason" required maxlength="255" :disabled="cancelling" class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Dôvod storna" /><button type="submit" :disabled="cancelling || !reason.trim()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ cancelling ? 'Stornujem…' : 'Stornovať príjemku' }}</button></div></form>
                </template>
            </div>
        </template>
    </BaseLayout>
</template>

<style>
@media print {
    body * { visibility: hidden; }
    .receipt-document, .receipt-document * { visibility: visible; }
    .receipt-document { position: absolute; left: 0; top: 0; width: 100%; border: 0 !important; box-shadow: none !important; padding: 0 !important; color: black; }
    .receipt-actions { display: none !important; }
    .receipt-table { overflow: visible !important; }
    .receipt-document table { font-size: 10px; }
    .receipt-document tr { break-inside: avoid; }
    .receipt-document thead { display: table-header-group; }
    @page { size: A4; margin: 15mm; }
}
</style>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { useStocks, todayIso } from '../../store/StoreStocks';
import useErrors from '../../store/StoreErrors';
import useFlash from '../../store/StoreFlash';
import useUsers from '../../store/StoreUsers';
import axiosInstance from '../../axiosInstance';
import { PAGE_STOCK } from '../../constants';
import { formatPrice } from '../../models/functions';
import { receiptLine, receiptTotals, receiptUuid } from '../../models/stockReceipt.mjs';
import useUnsavedChanges from '../../models/useUnsavedChanges';
import router from '../../router';
import BaseLayout from '../layout/BaseLayout.vue';
import PageHeader from '../layout/page/pageHeader.vue';
import SearchableSelect from '../forms/SearchableSelect.vue';

const stock = useStocks();
const errors = useErrors();
const { getVariants } = storeToRefs(stock);
const today = todayIso();
let nextKey = 0;
const emptyItem = () => ({ key: ++nextKey, product_variant_id: null, quantity: '', price: '', discount: 0, vat: '', note: '' });
const form = reactive({
    uuid: receiptUuid(), received_at: today, supplier: '', supplier_address: '',
    supplier_ico: '', supplier_dic: '', supplier_vat_id: '', document_number: '',
    warehouse: 'Hlavný sklad', received_by: useUsers().getUser.name || '', note: '', items: [emptyItem()],
});
const { markAsSaved } = useUnsavedChanges(() => form, { ignore: ['key', 'uuid'] });
const submitting = ref(false);
const submitted = ref(false);
const touched = reactive({});
const loading = ref(true);
const loadVariants = async () => {
    loading.value = true;
    await stock.fetchVariants();
    loading.value = false;
};
onMounted(() => { errors.resetErrors(); loadVariants(); });
const selected = (item) => getVariants.value.find((row) => Number(row.id) === Number(item.product_variant_id));
const optionsFor = (item) => getVariants.value.filter((row) => row.id === item.product_variant_id || !form.items.some((other) => Number(other.product_variant_id) === Number(row.id)))
    .map((row) => ({ value: row.id, label: `[${row.code}] ${row.label}` }));
const totals = computed(() => receiptTotals(form.items));
const quantityTotal = computed(() => form.items.reduce((sum, item) => sum + (Number(item.quantity) || 0), 0));
const missingPrices = computed(() => form.items.some((item) => item.price === ''));
const clientErrors = computed(() => {
    const result = {};
    for (const [key, label] of [['supplier', 'dodávateľa'], ['warehouse', 'sklad'], ['received_by', 'osobu, ktorá tovar prevzala']]) {
        if (!form[key].trim()) result[key] = `Zadajte ${label}.`;
    }
    if (!form.received_at || form.received_at < '2000-01-01' || form.received_at > today) result.received_at = 'Zadajte dátum od 1. 1. 2000 do dneška.';
    form.items.forEach((item, index) => {
        const prefix = `items.${index}.`;
        if (!selected(item)) result[prefix + 'product_variant_id'] = 'Vyberte skladovú položku.';
        else if (form.items.some((other) => other !== item && Number(other.product_variant_id) === Number(item.product_variant_id))) result[prefix + 'product_variant_id'] = 'Táto položka je už na príjemke.';
        if (!Number.isInteger(Number(item.quantity)) || Number(item.quantity) < 1 || Number(item.quantity) > 100000) result[prefix + 'quantity'] = 'Zadajte celé množstvo od 1 do 100 000.';
        for (const [key, max, label] of [['price', 99999.99, 'cenu'], ['discount', 100, 'zľavu'], ['vat', 100, 'DPH']]) {
            const value = item[key];
            if (value === '' || value === null || !/^\d+(\.\d{1,2})?$/.test(String(value)) || Number(value) > max) result[prefix + key] = `Zadajte ${label} od 0 do ${max}, najviac na 2 desatinné miesta.`;
        }
    });
    return result;
});
const fieldError = (key) => errors.fieldErrors[key]?.[0] ?? ((submitted.value || touched[key]) ? clientErrors.value[key] : '') ?? '';
const edit = (key) => { touched[key] = true; errors.clearFieldError(key); };
const inputClass = (key) => `w-full rounded-lg border px-3 py-2 text-sm ${fieldError(key) ? 'border-red-500 bg-red-50' : 'border-gray-300 focus:border-blue-500'}`;
const addItem = () => { if (form.items.length < 100) form.items.push(emptyItem()); };
const removeItem = (index) => {
    form.items.splice(index, 1);
    // Indexy serverových chýb sa po odstránení riadku posunú.
    errors.resetErrors();
    Object.keys(touched).filter((key) => key.startsWith('items.')).forEach((key) => delete touched[key]);
};
const headerFields = [
    { key: 'supplier', label: 'Dodávateľ', required: true, max: 255, placeholder: 'Názov firmy alebo meno' },
    { key: 'supplier_address', label: 'Adresa dodávateľa', max: 255, placeholder: 'Ulica, PSČ, mesto, krajina' },
    { key: 'supplier_ico', label: 'IČO', max: 32 },
    { key: 'supplier_dic', label: 'DIČ', max: 32 },
    { key: 'supplier_vat_id', label: 'IČ DPH', max: 32 },
    { key: 'document_number', label: 'Dodací list / faktúra', max: 64, placeholder: 'Číslo dodávateľského dokladu' },
    { key: 'warehouse', label: 'Sklad / miesto príjmu', required: true, max: 255 },
    { key: 'received_by', label: 'Prevzal', required: true, max: 255, placeholder: 'Meno zodpovednej osoby' },
];
const submit = async () => {
    if (submitting.value) return;
    submitted.value = true;
    if (Object.keys(clientErrors.value).length) return;
    submitting.value = true;
    errors.resetErrors();
    try {
        const payload = Object.fromEntries(Object.entries(form).filter(([key]) => key !== 'items').map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value]));
        payload.items = form.items.map(({ key, ...item }) => ({ ...item, note: item.note.trim() }));
        const { data } = await axiosInstance.post(PAGE_STOCK.URL + '/receipts', payload);
        markAsSaved();
        useFlash().success(`Príjemka ${data.data.number} bola uložená.`);
        await router.push({ name: 'stocks.receipts.show', params: { receiptId: data.data.id } });
    } catch (error) { errors.setErrors(error); }
    finally { submitting.value = false; }
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Nová príjemka', buttonLink: { name: 'Späť na sklad', link: 'stocks.index', icon: 'arrow-left' } }" />
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">Jeden doklad, viac položiek. Číslo príjemky sa pridelí po uložení.</p>
                    <router-link :to="{ name: 'stocks.writeoff' }" class="text-sm font-semibold text-red-700 hover:underline">Odpis zo skladu →</router-link>
                </div>
                <form novalidate @submit.prevent="submit">
                    <fieldset :disabled="submitting" class="min-w-0 grid items-start gap-6 xl:grid-cols-4">
                        <div class="min-w-0 space-y-6 xl:col-span-3">
                            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b bg-gray-50 px-5 py-4"><h2 class="font-semibold text-gray-800">Údaje príjemky</h2></div>
                                <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <div>
                                        <label for="receipt-date" class="mb-1.5 block text-sm font-semibold">Dátum príjmu *</label>
                                        <input id="receipt-date" v-model="form.received_at" type="date" min="2000-01-01" :max="today" :class="inputClass('received_at')" @input="edit('received_at')" />
                                        <p v-if="fieldError('received_at')" class="mt-1 text-xs text-red-600">{{ fieldError('received_at') }}</p>
                                    </div>
                                    <div v-for="field in headerFields" :key="field.key">
                                        <label :for="'receipt-' + field.key" class="mb-1.5 block text-sm font-semibold">{{ field.label }}{{ field.required ? ' *' : '' }}</label>
                                        <input :id="'receipt-' + field.key" v-model="form[field.key]" type="text" :maxlength="field.max" :placeholder="field.placeholder" :class="inputClass(field.key)" @input="edit(field.key)" />
                                        <p v-if="fieldError(field.key)" class="mt-1 text-xs text-red-600">{{ fieldError(field.key) }}</p>
                                    </div>
                                </div>
                            </section>
                            <section class="rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="flex items-center justify-between border-b bg-gray-50 px-5 py-4">
                                    <h2 class="font-semibold text-gray-800">Položky príjemky <span class="text-gray-400">({{ form.items.length }})</span></h2>
                                    <button type="button" :disabled="form.items.length >= 100" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="addItem">+ Pridať položku</button>
                                </div>
                                <div v-if="loading" class="px-5 pt-4 text-sm text-gray-500">Načítavam skladové položky…</div>
                                <div v-else-if="!getVariants.length" class="px-5 pt-4 text-sm text-amber-700">Skladové položky nie sú dostupné. <button type="button" class="underline" @click="loadVariants">Skúsiť znova</button></div>
                                <div v-for="(item, index) in form.items" :key="item.key" class="border-b border-gray-100 p-5 last:border-b-0">
                                    <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-semibold text-gray-700">Položka {{ index + 1 }}</h3><button type="button" :disabled="form.items.length === 1" class="text-xs font-semibold text-red-600 disabled:opacity-30" :aria-label="`Odstrániť položku ${index + 1}`" @click="removeItem(index)">Odstrániť</button></div>
                                    <label class="mb-1.5 block text-sm font-semibold">Skladová položka *</label>
                                    <SearchableSelect v-model="item.product_variant_id" :options="optionsFor(item)" :disabled="submitting || loading" :field-key="`items.${index}.product_variant_id`" :error="fieldError(`items.${index}.product_variant_id`)" placeholder="Vyberte tovar podľa kódu alebo názvu" search-placeholder="Kód, názov, prevedenie…" @update:model-value="edit(`items.${index}.product_variant_id`)" />
                                    <p v-if="selected(item)" class="mt-2 text-xs text-gray-500">Kód: {{ selected(item).code }} · Jednotka: {{ selected(item).unit_value || 'ks' }} · Evidované: {{ selected(item).tracked_quantity ?? 'nesleduje sa' }} · Po príjme: {{ selected(item).tracked_quantity === null ? 'nesleduje sa' : Number(selected(item).tracked_quantity) + (Number(item.quantity) || 0) }}</p>
                                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <div v-for="field in [{ key: 'quantity', label: 'Množstvo *', step: '1', min: 1, max: 100000 }, { key: 'price', label: 'Cena / jedn. bez DPH (€) *', step: '0.01', min: 0, max: 99999.99 }, { key: 'discount', label: 'Zľava (%)', step: '0.01', min: 0, max: 100 }, { key: 'vat', label: 'DPH (%) *', step: '0.01', min: 0, max: 100 }]" :key="field.key">
                                            <label :for="`line-${item.key}-${field.key}`" class="mb-1.5 block text-xs font-semibold">{{ field.label }}</label>
                                            <input :id="`line-${item.key}-${field.key}`" v-model="item[field.key]" type="number" :min="field.min" :max="field.max" :step="field.step" :class="inputClass(`items.${index}.${field.key}`)" @input="edit(`items.${index}.${field.key}`)" />
                                            <p v-if="fieldError(`items.${index}.${field.key}`)" class="mt-1 text-xs text-red-600">{{ fieldError(`items.${index}.${field.key}`) }}</p>
                                        </div>
                                    </div>
                                    <div class="mt-4 grid items-end gap-3 sm:grid-cols-2">
                                        <div><label :for="`line-${item.key}-note`" class="mb-1 block text-xs font-semibold text-gray-500">Poznámka položky</label><input :id="`line-${item.key}-note`" v-model="item.note" maxlength="255" :class="inputClass(`items.${index}.note`)" @input="edit(`items.${index}.note`)" /><p v-if="fieldError(`items.${index}.note`)" class="mt-1 text-xs text-red-600">{{ fieldError(`items.${index}.note`) }}</p></div>
                                        <div class="text-right text-sm"><span class="text-gray-500">Bez DPH: {{ formatPrice(receiptLine(item).net) }} €</span><div class="font-semibold">S DPH: {{ formatPrice(receiptLine(item).total) }} €</div></div>
                                    </div>
                                </div>
                                <div class="border-t px-5 py-3 text-xs text-gray-500">DPH zadajte podľa dodávateľského dokladu; pri nulovej sadzbe zadajte 0. Najviac 100 položiek.</div>
                            </section>
                            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"><label for="receipt-note" class="mb-2 block text-sm font-semibold">Poznámka k príjemke</label><textarea id="receipt-note" v-model="form.note" rows="3" maxlength="2000" :class="inputClass('note')" placeholder="Stav zásielky, spôsob prevzatia, interné informácie…" @input="edit('note')" /><p v-if="fieldError('note')" class="text-xs text-red-600">{{ fieldError('note') }}</p></section>
                        </div>
                        <aside class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm xl:sticky xl:top-6">
                            <h2 class="mb-4 font-semibold">Súhrn príjemky</h2>
                            <dl class="space-y-3 text-sm"><div class="flex justify-between gap-2"><dt>Položky</dt><dd>{{ form.items.length }}</dd></div><div class="flex justify-between gap-2"><dt>Súčet množstiev</dt><dd>{{ quantityTotal }}</dd></div><div class="flex justify-between gap-2"><dt>Bez DPH</dt><dd>{{ formatPrice(totals.net) }} €</dd></div><div class="flex justify-between gap-2"><dt>DPH</dt><dd>{{ formatPrice(totals.tax) }} €</dd></div><div class="flex justify-between gap-2 border-t pt-3 font-bold"><dt>Celkom s DPH</dt><dd>{{ formatPrice(totals.total) }} €</dd></div></dl>
                            <p v-if="missingPrices" class="mt-4 text-xs text-amber-700">Súhrn doplní všetky hodnoty po zadaní cien.</p>
                            <p class="mt-4 text-xs text-gray-500">Uložením sa všetky položky naskladnia naraz. Hodnota skladu používa nákupnú cenu po zľave bez DPH.</p>
                            <p v-if="submitted && Object.keys(clientErrors).length" role="alert" class="mt-4 text-sm font-semibold text-red-600">Skontrolujte označené polia.</p>
                            <button type="submit" :disabled="submitting || loading || !getVariants.length" class="mt-5 w-full rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50">{{ submitting ? 'Ukladám príjemku…' : 'Uložiť a naskladniť' }}</button>
                        </aside>
                    </fieldset>
                </form>
            </div>
        </template>
    </BaseLayout>
</template>

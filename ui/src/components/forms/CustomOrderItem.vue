<script setup>
import { computed, reactive, ref } from 'vue';
import { grossFromNet, money, VAT_RATES } from '../../models/sales';
const emit = defineEmits(['add']);
// Obsluha vždy zadáva cenu bez DPH. Košík a objednávky pracujú s cenou s DPH,
// cenová ponuka bez DPH — `emitNet` určuje, ktorá ide von ako `active_price`.
const props = defineProps({ emitNet: { type: Boolean, default: false } });
const open = ref(false);
const error = ref('');
const item = reactive({ name: '', input_order: 1, unit_value: 'ks', price_net: 0, vat: 23 });
const priceGross = computed(() => grossFromNet(item.price_net || 0, item.vat));
const add = () => {
    if (!item.name.trim() || !Number.isInteger(Number(item.input_order)) || item.input_order < 1 || !Number.isFinite(Number(item.price_net)) || item.price_net < 0) {
        error.value = 'Vyplňte názov, celé kladné množstvo a nezápornú cenu.'; return;
    }
    if (!VAT_RATES.includes(Number(item.vat))) {
        error.value = 'Zvoľte sadzbu DPH.'; return;
    }
    const { price_net, ...rest } = item;
    emit('add', { ...rest, name: item.name.trim(), unit_value: item.unit_value || 'ks', vat: Number(item.vat), active_price: props.emitNet ? Number(price_net) : priceGross.value, is_custom: true, id: 'custom-' + crypto.randomUUID(), min_order: 1 });
    Object.assign(item, { name: '', input_order: 1, unit_value: 'ks', price_net: 0, vat: 23 });
    error.value = ''; open.value = false;
};
</script>
<template>
<div class="my-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
<button type="button" class="font-semibold text-blue-800" @click="open = !open">+ Pridať vlastnú položku</button>
<div v-if="open" class="mt-3 space-y-3">
<p class="text-sm text-gray-600">Služba alebo príplatok bez skladovej zásoby.</p>
<div class="grid gap-3 sm:grid-cols-2">
<label class="text-sm">Názov<input v-model="item.name" maxlength="200" placeholder="Napr. grafická príprava" class="mt-1 w-full rounded-sm border-gray-300" /></label>
<label class="text-sm">Množstvo<input v-model.number="item.input_order" type="number" min="1" step="1" class="mt-1 w-full rounded-sm border-gray-300" /></label>
<label class="text-sm">Jednotka<input v-model="item.unit_value" maxlength="20" class="mt-1 w-full rounded-sm border-gray-300" /></label>
<label class="text-sm">Jednotková cena bez DPH (€)<input v-model.number="item.price_net" type="number" min="0" step="0.01" class="mt-1 w-full rounded-sm border-gray-300" /></label>
<label class="text-sm">DPH<select v-model.number="item.vat" class="mt-1 w-full rounded-sm border-gray-300"><option v-for="rate in VAT_RATES" :key="rate" :value="rate">{{ rate }} %</option></select></label>
<p class="self-end text-sm text-gray-700" aria-live="polite">Cena s DPH: <strong>{{ money(priceGross) }}</strong> / {{ item.unit_value || 'ks' }}</p>
</div><p v-if="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
<button type="button" class="rounded-sm bg-blue-700 px-4 py-2 text-white" @click="add">Pridať položku</button>
</div></div>
</template>

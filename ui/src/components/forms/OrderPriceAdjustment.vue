<script setup>
import { computed } from 'vue';
import { adjustmentAmount } from '../../models/orderPricing';
import { formatDecimal } from '../../models/functions';
const props = defineProps({ modelValue: Object, subtotal: { type: Number, default: 0 }, coupon: { type: Number, default: 0 } });
const emit = defineEmits(['update:modelValue']);
const change = (key, value) => emit('update:modelValue', { ...props.modelValue, [key]: value });
const amount = computed(() => adjustmentAmount(props.subtotal, props.modelValue, props.coupon));
</script>
<template><div class="my-4 rounded-lg border border-gray-200 bg-white p-4">
<label class="flex items-center gap-2 font-semibold"><input type="checkbox" :checked="!!modelValue" @change="emit('update:modelValue', $event.target.checked ? { direction: 'discount', type: 'fixed', value: 0, label: '' } : null)" /> Zľava / prirážka k objednávke</label>
<div v-if="modelValue" class="mt-3 space-y-3">
<div class="grid gap-3 sm:grid-cols-3">
<label class="text-sm">Úprava<select :value="modelValue.direction" @change="change('direction', $event.target.value)" class="mt-1 w-full rounded border-gray-300"><option value="discount">Zľava</option><option value="surcharge">Prirážka</option></select></label>
<label class="text-sm">Spôsob<select :value="modelValue.type" @change="change('type', $event.target.value)" class="mt-1 w-full rounded border-gray-300"><option value="fixed">Suma v €</option><option value="percent">Percentá</option></select></label>
<label class="text-sm">Hodnota<input :value="modelValue.value" @input="change('value', Number($event.target.value))" type="number" min="0" step="0.01" :max="modelValue.direction === 'discount' && modelValue.type === 'percent' ? 100 : 999999" class="mt-1 w-full rounded border-gray-300" /></label>
</div>
<label class="block text-sm">Popis<input :value="modelValue.label" @input="change('label', $event.target.value)" maxlength="200" placeholder="Napr. dohodnutá zľava" class="mt-1 w-full rounded border-gray-300" /></label>
<p class="text-sm text-gray-600">Percentá sa počítajú z položiek bez dopravy a platobného poplatku. Súčet zliav neprekročí cenu položiek.</p>
<p class="font-semibold">Úprava: {{ formatDecimal(amount) }} € · Položky po zľavách: {{ formatDecimal(Math.max(0, subtotal - coupon + amount)) }} €</p>
</div></div></template>
<script setup>
const props = defineProps({
    items: { type: Array, default: () => [] },
    sum: { type: [Number, String], default: 0 },
});
</script>

<template>
    <div class="mb-5 overflow-hidden rounded-md border border-slate-300 bg-slate-50 shadow-sm">
        <div class="grid grid-cols-3 gap-4 bg-slate-800 px-4 py-2 text-sm font-semibold text-white">
            <span>Clipboard</span>
            <span>Ks</span>
            <span class="text-right">Hodnota</span>
        </div>

        <div class="divide-y divide-slate-200 bg-white">
            <div class="grid grid-cols-3 gap-4 px-4 py-2 text-sm" v-for="product in props.items" :key="product.id">
                <span class="font-semibold text-slate-900">{{ product.name }}</span>
                <span class="text-slate-700">{{ product.total_quantity ?? '—' }} ks</span>
                <span class="text-right font-medium text-slate-900">
                    {{ Math.trunc(Number(product.price_from ?? 0) * Number(product.total_quantity ?? 0)) }},- €
                </span>
            </div>
        </div>

        <div class="flex justify-between border-t border-slate-300 bg-slate-100 px-4 py-2 text-sm">
            <span class="font-semibold text-slate-700">Sumár spolu:</span>
            <span class="font-semibold text-slate-900">{{ props.sum }} €</span>
        </div>
    </div>
</template>

<script setup>
// Červený kruh „zľava X %“ cez roh obrázka produktu — nahrádza zľavu kedysi vpálenú do obrázkov.
defineProps({
    percent: { type: Number, required: true },
    // Varianty majú rôzne zľavy → „až X %“.
    upTo: { type: Boolean, default: false },
    large: { type: Boolean, default: false },
});
</script>

<template>
    <span v-if="percent > 0"
        class="pointer-events-none absolute bottom-3 right-3 flex -rotate-12 flex-col items-center justify-center rounded-full border-4 border-white bg-red-600 font-extrabold leading-none text-white shadow-lg"
        :class="large ? 'h-28 w-28 md:h-32 md:w-32' : 'h-20 w-20'">
        <span :class="large ? 'text-base md:text-lg' : 'text-xs'">{{ upTo ? 'zľava až' : 'zľava' }}</span>
        <span :class="large ? 'mt-1 text-3xl md:text-4xl' : 'mt-0.5 text-2xl'">{{ percent }}%</span>
    </span>
</template>

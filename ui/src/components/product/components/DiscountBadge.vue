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
        class="pointer-events-none absolute bottom-3 right-3 flex -rotate-12 flex-col items-center justify-center rounded-full border-[3px] border-white bg-red-600 font-extrabold leading-none text-white shadow-md"
        :class="large ? 'h-20 w-20 md:h-24 md:w-24' : 'h-14 w-14'">
        <span :class="large ? 'text-xs md:text-sm' : 'text-[10px]'">{{ upTo ? 'zľava až' : 'zľava' }}</span>
        <span :class="large ? 'mt-0.5 text-2xl md:text-3xl' : 'mt-0.5 text-lg'">{{ percent }}%</span>
    </span>
</template>

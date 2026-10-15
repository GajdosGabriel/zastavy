<script setup>
// Kostra stránky počas načítania — namiesto prázdneho stavu, ktorý by tvrdil, že nie sú dáta.
defineProps({
    rows: { type: Number, default: 4 },
    label: { type: String, default: "Načítavam…" },
});
</script>

<template>
    <div role="status" aria-live="polite" class="space-y-4">
        <span class="sr-only">{{ label }}</span>
        <div class="grid gap-4 md:grid-cols-2" aria-hidden="true">
            <div class="h-28 animate-pulse rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 h-3 w-24 rounded bg-gray-200"></div>
                <div class="mb-2 h-4 w-48 rounded bg-gray-200"></div>
                <div class="h-3 w-32 rounded bg-gray-100"></div>
            </div>
            <div class="h-28 animate-pulse rounded-lg bg-white p-4 shadow-sm">
                <div class="mb-3 h-3 w-24 rounded bg-gray-200"></div>
                <div class="mb-2 h-4 w-40 rounded bg-gray-200"></div>
                <div class="h-3 w-28 rounded bg-gray-100"></div>
            </div>
        </div>
        <div class="animate-pulse overflow-hidden rounded-lg bg-white shadow-sm" aria-hidden="true">
            <div class="h-10 bg-blue-800/80"></div>
            <div v-for="n in rows" :key="n" class="flex items-center gap-4 border-b border-gray-100 px-4 py-4">
                <div class="h-10 w-10 rounded bg-gray-200"></div>
                <div class="h-3 flex-1 rounded bg-gray-200"></div>
                <div class="h-3 w-16 rounded bg-gray-100"></div>
                <div class="h-3 w-20 rounded bg-gray-100"></div>
            </div>
        </div>
    </div>
</template>

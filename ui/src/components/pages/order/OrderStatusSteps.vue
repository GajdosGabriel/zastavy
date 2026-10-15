<script setup>
import { computed } from "vue";

// Stav objednávky pre zákazníka (status z API: { value, label }).
const props = defineProps({
    status: { type: Object, default: null },
});

const STEPS = [
    { key: "received", label: "Prijatá", values: ["draft"] },
    { key: "processing", label: "Spracováva sa", values: ["processing"] },
    { key: "ready", label: "Pripravená na odoslanie", values: ["ready_to_ship"] },
    { key: "shipped", label: "Odoslaná", values: ["partially_shipped", "shipped"] },
];

// Stornovaná/archivovaná objednávka nemá priebeh — ukáže sa len štítok.
const isTerminal = computed(() => ["cancelled", "archived"].includes(props.status?.value));
const current = computed(() => STEPS.findIndex((step) => step.values.includes(props.status?.value)));
</script>

<template>
    <div v-if="status">
        <p v-if="isTerminal" class="inline-flex rounded-full px-3 py-1 text-sm font-semibold"
            :class="status.value === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'">
            {{ status.label }}
        </p>

        <ol v-else class="grid grid-cols-4 gap-2" aria-label="Stav objednávky">
            <li v-for="(step, index) in STEPS" :key="step.key" class="min-w-0"
                :aria-current="index === current ? 'step' : undefined">
                <div class="h-1.5 rounded-full" :class="index <= current ? 'bg-green-600' : 'bg-gray-200'"></div>
                <p class="mt-1.5 text-xs leading-tight"
                    :class="index === current ? 'font-semibold text-gray-900' : index < current ? 'text-gray-700' : 'text-gray-500'">
                    {{ step.label }}
                    <span v-if="status.value === 'partially_shipped' && step.key === 'shipped'" class="block">(čiastočne)</span>
                </p>
            </li>
        </ol>
    </div>
</template>

<script setup>
import { onUnmounted, ref } from "vue";

const props = defineProps({
    value:  { type: [String, Number], default: '' },
    label:  { type: String, default: 'Kopírovať' },
    size:   { type: String, default: 'h-4 w-4' },
});

const copied = ref(false);
let timer = null;

const copy = async () => {
    if (!props.value) return;

    try {
        await navigator.clipboard.writeText(String(props.value));
    } catch {
        // Fallback pre prehliadače bez clipboard API (http bez localhost)
        const input = document.createElement("textarea");
        input.value = String(props.value);
        document.body.appendChild(input);
        input.select();
        document.execCommand("copy");
        document.body.removeChild(input);
    }

    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => { copied.value = false; }, 1500);
};

onUnmounted(() => clearTimeout(timer));
</script>

<template>
    <button
        v-if="value"
        type="button"
        class="shrink-0 rounded p-0.5 text-gray-400 transition hover:bg-gray-100 hover:text-blue-600"
        :title="copied ? 'Skopírované' : label"
        :aria-label="copied ? 'Skopírované' : label"
        @click.stop.prevent="copy"
    >
        <svg v-if="copied" :class="size" class="text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
        </svg>
        <svg v-else :class="size" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m0 0H5.625" />
        </svg>
    </button>
</template>

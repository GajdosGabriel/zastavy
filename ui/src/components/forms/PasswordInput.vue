<script setup>
import { ref } from "vue";

defineOptions({ inheritAttrs: false });

defineProps({
    modelValue: { default: "" },
});

defineEmits(["update:modelValue"]);

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <input
            v-bind="$attrs"
            :value="modelValue"
            :type="visible ? 'text' : 'password'"
            class="block w-full rounded border border-slate-300 bg-white px-3 py-2.5 pr-11 text-slate-900 shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
            @input="$emit('update:modelValue', $event.target.value)"
        />

        <button
            type="button"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 transition hover:text-slate-700 focus:outline-none"
            :title="visible ? 'Skryť heslo' : 'Zobraziť heslo'"
            :aria-label="visible ? 'Skryť heslo' : 'Zobraziť heslo'"
            @click="visible = !visible"
        >
            <svg
                v-if="!visible"
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                />
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                />
            </svg>

            <svg
                v-else
                xmlns="http://www.w3.org/2000/svg"
                class="h-5 w-5"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.243 4.243L9.88 9.88"
                />
            </svg>
        </button>
    </div>
</template>

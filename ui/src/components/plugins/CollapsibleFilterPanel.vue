<script setup>
import { ref } from "vue";

defineProps({
    id: { type: String, required: true },
});

const expanded = ref(false);
</script>

<template>
    <div class="filter-panel">
        <button
            type="button"
            class="filter-panel-toggle"
            :aria-expanded="expanded"
            :aria-controls="id"
            @click="expanded = !expanded"
        >
            <span>Filtre</span>
            <span class="flex items-center gap-2">
                <span>{{ expanded ? 'Zbaliť' : 'Rozbaliť' }}</span>
                <svg
                    class="h-4 w-4"
                    :class="{ 'rotate-180': expanded }"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </span>
        </button>
        <div :id="id" class="filter-panel-content" :class="{ 'filter-panel-content-expanded': expanded }">
            <slot />
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";

const props = defineProps({
    text: { type: String, default: "" },
    maxLength: { type: Number, default: 30 },
});

const shortenedText = computed(() => {
    const characters = Array.from(props.text || "");
    return characters.length > props.maxLength
        ? `${characters.slice(0, props.maxLength).join("").trimEnd()}...`
        : props.text;
});
</script>

<template>
    <span class="whitespace-nowrap" :title="text">{{ shortenedText }}</span>
</template>

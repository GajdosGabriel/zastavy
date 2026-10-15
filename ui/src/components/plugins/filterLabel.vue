<script setup>
const props = defineProps({
    label: {

    }
});

defineEmits(["labelemit"]);
</script>

<template>
    <!-- Tlačidlo (nie div), aby sa dalo ovládať klávesnicou; aria-pressed hlási zapnutý filter. -->
    <button type="button" @click="$emit('labelemit', props.label)"
        :class="[props.label.key === 'resetFilter' ? 'filter-chip-reset' : props.label.active ? 'filter-chip-active' : '']"
        :aria-pressed="props.label.key === 'resetFilter' ? undefined : Boolean(props.label.active)"
        class="filter-chip">
        {{ props.label.name }}
        <span v-if="props.label.iconRight" class="ml-1">({{ props.label.iconRight }})</span>
    </button>
</template>

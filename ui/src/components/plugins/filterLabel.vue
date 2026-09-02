<script setup>
const props = defineProps({
    label: {

    }
});

</script>

<template>
    <div @click="$emit('labelemit', props.label)"
        :class="[props.label.key === 'resetFilter' ? 'filter-chip-reset' : props.label.active ? 'filter-chip-active' : '']"
        class="filter-chip" :title="props.label.name">
        {{ props.label.name + ' ' }}
        <span v-if="props.label.iconRight"> ({{ props.label.iconRight }})</span>
    </div>
</template>

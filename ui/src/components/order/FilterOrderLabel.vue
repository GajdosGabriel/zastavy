<script setup>
const props = defineProps({
    label: {

    }
});

</script>

<template>
    <div @click="$emit('labelemit', props.label)"
        :class="[props.label.active ? 'bg-gray-600 text-white ' : 'bg-gray-200 text-gray-700']"
        class="flex justify-center items-center  rounded-md cursor-pointer text-sm px-2" :title="props.label.name">
        {{ props.label.name + ' ' }}
        <span v-if="props.label.iconRight"> ({{ props.label.iconRight }})</span>
    </div>
</template>
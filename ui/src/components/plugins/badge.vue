<script setup>
const props = defineProps(['kosik'])
</script>

<template>
    <div class="h-6 w-6 bg-red-600 flex justify-center items-center  rounded-full text-white"
        :class="{ 'bg-green-600': props.kosik.class }" :title="props.kosik.title">
        {{ props.kosik.value }}
    </div>
</template>
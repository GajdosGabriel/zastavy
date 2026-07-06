<script setup>
import { onUnmounted } from 'vue';
import useErrors from '../../store/StoreErrors';

const { resetErrors } = useErrors();

onUnmounted(() => {
    resetErrors();
})
</script>

<template>
    <!-- <div>MainBar</div> -->
</template>
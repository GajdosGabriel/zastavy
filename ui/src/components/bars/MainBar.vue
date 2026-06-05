<script setup>
import { onUnmounted } from 'vue';
import useErrors from '../../store/StoreErrors';

const { state, getErrors, resetErrors } = useErrors();

onUnmounted(() => {
    resetErrors();
})
</script>

<template>
    <!-- <div>MainBar</div> -->
</template>
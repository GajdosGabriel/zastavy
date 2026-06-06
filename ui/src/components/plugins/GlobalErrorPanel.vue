<script setup>
import ErrorPanel from './ErrorPanel.vue';
import useErrors from '../../store/StoreErrors';

const { getErrors } = useErrors();
</script>

<template>
    <div v-if="getErrors.length > 0" class="fixed left-0 right-0 top-4 z-50 mx-auto w-full max-w-5xl px-4">
        <ErrorPanel />
    </div>
</template>

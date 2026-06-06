<script setup>
import ErrorPanel from './ErrorPanel.vue';
import useErrors from '../../store/StoreErrors';

const { getErrors } = useErrors();
</script>

<template>
    <div v-if="getErrors.length > 0" class="col-span-12 px-3 pt-3">
        <ErrorPanel />
    </div>
</template>

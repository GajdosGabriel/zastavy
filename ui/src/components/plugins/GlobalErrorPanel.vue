<script setup>
import { nextTick, ref, watch } from 'vue';
import ErrorPanel from './ErrorPanel.vue';
import useErrors from '../../store/StoreErrors';

const { getErrors } = useErrors();
const errorPanelRef = ref(null);

watch(
    () => getErrors.value.length,
    async (count) => {
        if (!count) {
            return;
        }

        await nextTick();
        errorPanelRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
);
</script>

<template>
    <div v-if="getErrors.length > 0" ref="errorPanelRef" class="col-span-12 px-3 pt-3 scroll-mt-4">
        <ErrorPanel />
    </div>
</template>

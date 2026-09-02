<script setup>
import { watch, nextTick, ref, onMounted, onUnmounted } from 'vue';
import { confirmState, closeConfirmDialog } from '../../models/confirmDialog';

const confirmButton = ref(null);

const onKeydown = (event) => {
    if (!confirmState.isOpen) return;
    if (event.key === 'Escape') closeConfirmDialog(false);
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

watch(() => confirmState.isOpen, async (isOpen) => {
    if (!isOpen) return;
    await nextTick();
    confirmButton.value?.focus();
});
</script>

<template>
    <Teleport to="body">
        <div v-if="confirmState.isOpen"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-40 p-4"
            @click.self="closeConfirmDialog(false)">
            <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                <h3 class="mb-2 text-lg font-semibold text-gray-800">{{ confirmState.title }}</h3>
                <p v-if="confirmState.message" class="mb-5 text-sm text-gray-600">{{ confirmState.message }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="closeConfirmDialog(false)"
                        class="rounded bg-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-300">
                        {{ confirmState.cancelLabel }}
                    </button>
                    <button type="button" ref="confirmButton" @click="closeConfirmDialog(true)"
                        class="rounded px-4 py-2 text-sm font-semibold text-white"
                        :class="confirmState.tone === 'danger'
                            ? 'bg-red-600 hover:bg-red-700'
                            : 'bg-blue-600 hover:bg-blue-700'">
                        {{ confirmState.confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

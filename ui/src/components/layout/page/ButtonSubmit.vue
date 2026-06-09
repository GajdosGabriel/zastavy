<script setup>
import { computed, useAttrs } from 'vue';
import SpinnerButton from '../../icons/spinnerButton.vue';
import loadingStore from '../../../store/StoreLoading';

const props = defineProps({
    item: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: null },
});

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();

const isLoading = computed(() =>
    props.loading !== null
        ? props.loading
        : !!(props.item?.spinner && loadingStore.isLoading)
);

const buttonAttrs = computed(() => ({
    ...attrs,
    type: attrs.type || 'submit',
    disabled: isLoading.value || !!attrs.disabled,
}));
</script>

<template>
    <div v-if="item && Object.keys(item).length">
        <button
            v-bind="buttonAttrs"
            class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-700 disabled:bg-blue-400 disabled:cursor-not-allowed text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
        >
            <SpinnerButton v-if="isLoading" />
            {{ item.name }}
        </button>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import useErrors from '../../store/StoreErrors';

const props = defineProps({
    modelValue: { default: '' },
    type:        { default: 'text' },
    placeholder: { default: '' },
    invalid:     { type: Boolean, default: false },
    error:       { type: String,  default: '' },
    fieldKey:    { type: String,  default: '' },
});

const emit = defineEmits(['update:modelValue']);
const { clearFieldError } = useErrors();

// Keď príde nová chyba z rodiča, zobraziť ju; keď user píše, skryť lokálne
const dirty = ref(false);

watch(() => props.error,   v => { if (v) dirty.value = false; });
watch(() => props.invalid, v => { if (v) dirty.value = false; });

const showError   = () => !dirty.value && !!props.error;
const showInvalid = () => !dirty.value && props.invalid;

const onInput = (e) => {
    emit('update:modelValue', e.target.value);
    dirty.value = true;
    if (props.fieldKey) clearFieldError(props.fieldKey);
};
</script>

<template>
    <input
        v-bind="$attrs"
        :value="modelValue"
        :type="type"
        :placeholder="placeholder"
        class="w-full rounded border px-3 py-2 focus:outline-none focus:ring-1"
        :class="showInvalid() || showError()
            ? 'border-red-500 ring-1 ring-red-500 bg-red-50'
            : 'border-gray-300 focus:border-blue-400 focus:ring-blue-400'"
        @input="onInput"
    />
    <p v-if="showError()" class="mt-1 text-xs font-semibold text-red-600">{{ error }}</p>
</template>

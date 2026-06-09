<script setup>
defineProps({
    modelValue: { default: '' },
    type: { default: 'text' },
    placeholder: { default: '' },
    invalid: { type: Boolean, default: false },
    error: { type: String, default: '' },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <input
        v-bind="$attrs"
        :value="modelValue"
        :type="type"
        :placeholder="placeholder"
        class="w-full rounded border px-3 py-2 focus:outline-none focus:ring-1"
        :class="invalid
            ? 'border-red-500 ring-1 ring-red-500 bg-red-50'
            : 'border-gray-300 focus:border-blue-400 focus:ring-blue-400'"
        @input="$emit('update:modelValue', $event.target.value)"
    />
    <p v-if="error" class="mt-1 text-xs font-semibold text-red-600">{{ error }}</p>
</template>

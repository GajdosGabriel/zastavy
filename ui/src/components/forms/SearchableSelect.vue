<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from "vue";
import useErrors from "../../store/StoreErrors";

const MAX_VISIBLE = 100;

const props = defineProps({
    modelValue: { default: undefined },
    // [{ value, label }]
    options:     { type: Array,   default: () => [] },
    placeholder: { type: String,  default: '— Vybrať —' },
    searchPlaceholder: { type: String, default: 'Hľadať…' },
    emptyText:   { type: String,  default: 'Nič sa nenašlo' },
    required:    { type: Boolean, default: false },
    disabled:    { type: Boolean, default: false },
    error:       { type: String,  default: '' },
    fieldKey:    { type: String,  default: '' },
});

const emit = defineEmits(['update:modelValue']);
const { clearFieldError } = useErrors();

const input = ref(null);
const listbox = ref(null);
const open = ref(false);
const query = ref('');
const highlighted = ref(-1);

let blurTimer = null;

// Normalizácia — bez diakritiky, case-insensitive (Základná === zakladna)
const normalize = (value) => String(value ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase();

const selected = computed(() =>
    props.options.find(o => String(o.value) === String(props.modelValue)) ?? null
);

const selectedLabel = computed(() => selected.value?.label ?? '');

// Pri otvorenom dropdowne v inpute svieti to, čo user píše; inak vybraná položka
const displayValue = computed(() => (open.value ? query.value : selectedLabel.value));

const filtered = computed(() => {
    if (!open.value) return props.options;

    const terms = normalize(query.value).split(/\s+/).filter(Boolean);
    if (!terms.length) return props.options;

    return props.options.filter((o) => {
        const label = normalize(o.label);
        return terms.every(t => label.includes(t));
    });
});

const visible = computed(() => filtered.value.slice(0, MAX_VISIBLE));
const hiddenCount = computed(() => filtered.value.length - visible.value.length);

// ── Otvorenie / zatvorenie ───────────────────────────────────
const openDropdown = () => {
    if (props.disabled) return;
    open.value = true;
    query.value = '';
    highlighted.value = props.options.findIndex(o => String(o.value) === String(props.modelValue));
    nextTick(scrollToHighlighted);
};

const closeDropdown = () => {
    open.value = false;
    query.value = '';
    highlighted.value = -1;
};

const onInput = (e) => {
    open.value = true;
    query.value = e.target.value;
    highlighted.value = filtered.value.length ? 0 : -1;
    nextTick(scrollToHighlighted);
};

const onBlur = () => {
    blurTimer = setTimeout(closeDropdown, 150);
};

const cancelBlur = () => clearTimeout(blurTimer);

const select = (option) => {
    emit('update:modelValue', option.value);
    if (props.fieldKey) clearFieldError(props.fieldKey);
    closeDropdown();
    input.value?.blur();
};

const clear = () => {
    emit('update:modelValue', undefined);
    closeDropdown();
    input.value?.focus();
};

// ── Klávesnica ───────────────────────────────────────────────
const move = (step) => {
    if (!open.value) {
        openDropdown();
        return;
    }
    const count = visible.value.length;
    if (!count) return;
    highlighted.value = (highlighted.value + step + count) % count;
    nextTick(scrollToHighlighted);
};

const onEnter = (e) => {
    if (!open.value) return;
    e.preventDefault();
    const option = visible.value[highlighted.value];
    if (option) select(option);
};

const scrollToHighlighted = () => {
    if (highlighted.value < 0) return;
    listbox.value?.children?.[highlighted.value]?.scrollIntoView({ block: 'nearest' });
};

watch(() => props.modelValue, (value) => {
    if (props.fieldKey && value !== undefined) clearFieldError(props.fieldKey);
});

onUnmounted(() => clearTimeout(blurTimer));
</script>

<template>
    <div class="relative" @mousedown="cancelBlur">
        <div class="relative">
            <input
                ref="input"
                type="text"
                autocomplete="off"
                :value="displayValue"
                :placeholder="open ? searchPlaceholder : placeholder"
                :required="required && !selected"
                :disabled="disabled"
                class="w-full rounded border px-3 py-2 pr-16 focus:outline-none focus:ring-1"
                :class="error
                    ? 'border-red-500 ring-1 ring-red-500 bg-red-50'
                    : 'border-gray-300 focus:border-blue-400 focus:ring-blue-400'"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open"
                @focus="openDropdown"
                @blur="onBlur"
                @input="onInput"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.enter="onEnter"
                @keydown.esc.prevent="closeDropdown"
            />

            <button
                v-if="selected && !disabled"
                type="button"
                class="absolute right-8 top-1/2 -translate-y-1/2 px-1 text-lg leading-none text-gray-400 hover:text-gray-600"
                aria-label="Zrušiť výber"
                @click="clear"
            >
                ×
            </button>

            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </span>
        </div>

        <div v-if="open" class="absolute z-30 mt-1 w-full rounded border border-gray-200 bg-white shadow-lg">
            <ul v-if="visible.length" ref="listbox" class="max-h-64 overflow-y-auto py-1" role="listbox">
                <li
                    v-for="(option, index) in visible"
                    :key="option.value"
                    role="option"
                    :aria-selected="String(option.value) === String(modelValue)"
                    class="cursor-pointer px-3 py-2 text-sm"
                    :class="[
                        index === highlighted ? 'bg-blue-50' : '',
                        String(option.value) === String(modelValue) ? 'font-semibold text-blue-700' : 'text-gray-700',
                    ]"
                    @mousedown.prevent="select(option)"
                    @mouseenter="highlighted = index"
                >
                    {{ option.label }}
                </li>
            </ul>

            <p v-else class="px-3 py-3 text-sm text-gray-500">{{ emptyText }}</p>

            <p v-if="hiddenCount > 0" class="border-t px-3 py-2 text-xs text-gray-500">
                … a ďalších {{ hiddenCount }} — spresnite hľadanie
            </p>
        </div>

        <p v-if="error" class="mt-1 text-xs font-semibold text-red-600">{{ error }}</p>
    </div>
</template>

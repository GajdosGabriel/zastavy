<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";

const MAX_HISTORY = 15;

const props = defineProps({
    modelValue: { type: String, default: '' },
    // localStorage kľúč histórie — pri zmene sa načíta iná história
    historyKey: { type: String, required: true },
    historyTitle: { type: String, default: 'Nedávne hľadania' },
    placeholder: { type: String, default: 'Hľadať…' },
    debounce: { type: Number, default: 400 },
});

const emit = defineEmits(['update:modelValue', 'search']);

const showHistory = ref(false);
const historyItems = ref([]);

let debounceTimer = null;
let blurTimer = null;

const term = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

// ── História (localStorage) ──────────────────────────────────
const loadHistory = () => {
    try {
        historyItems.value = JSON.parse(localStorage.getItem(props.historyKey)) || [];
    } catch {
        localStorage.removeItem(props.historyKey);
        historyItems.value = [];
    }
};

const saveHistory = () => {
    localStorage.setItem(props.historyKey, JSON.stringify(historyItems.value));
};

const addToHistory = (value) => {
    if (!value) return;
    historyItems.value = [value, ...historyItems.value.filter(item => item !== value)].slice(0, MAX_HISTORY);
    saveHistory();
};

const removeFromHistory = (value) => {
    historyItems.value = historyItems.value.filter(item => item !== value);
    saveHistory();
};

const clearHistory = () => {
    historyItems.value = [];
    saveHistory();
    showHistory.value = false;
};

const selectHistory = (value) => {
    term.value = value;
    showHistory.value = false;
    applySearch(value);
};

// ── Hľadanie ─────────────────────────────────────────────────
const applySearch = (value = null) => {
    const search = (value ?? props.modelValue ?? '').trim();

    addToHistory(search);
    emit('search', search);
};

const onSearchInput = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => applySearch(), props.debounce);
};

const onSearchEnter = () => {
    clearTimeout(debounceTimer);
    applySearch();
};

const clearSearch = () => {
    clearTimeout(debounceTimer);
    term.value = '';
    emit('search', '');
};

// ── História dropdown ────────────────────────────────────────
const onInputFocus = () => {
    loadHistory();
    showHistory.value = true;
};

const onInputBlur = () => {
    blurTimer = setTimeout(() => {
        showHistory.value = false;
    }, 200);
};

const cancelBlur = () => {
    clearTimeout(blurTimer);
};

watch(() => props.historyKey, () => {
    loadHistory();
    showHistory.value = false;
});

onMounted(loadHistory);

onUnmounted(() => {
    clearTimeout(debounceTimer);
    clearTimeout(blurTimer);
});

defineExpose({ applySearch });
</script>

<template>
    <div class="filter-search-wrapper" @mousedown="cancelBlur">
        <div class="filter-control">
            <svg class="filter-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
            </svg>
            <input
                v-model="term"
                type="text"
                class="filter-input"
                :placeholder="placeholder"
                autocomplete="off"
                @input="onSearchInput"
                @keydown.enter="onSearchEnter"
                @focus="onInputFocus"
                @blur="onInputBlur"
            />
            <button v-if="term" type="button" class="filter-clear" aria-label="Zrušiť hľadanie" @click="clearSearch">
                ×
            </button>
        </div>

        <!-- História hľadania -->
        <div v-if="showHistory && historyItems.length" class="filter-history-dropdown">
            <div class="filter-history-header">
                <span class="filter-history-title">{{ historyTitle }}</span>
                <button type="button" class="filter-history-clearall" @click="clearHistory">
                    Vymazať všetko
                </button>
            </div>
            <ul class="filter-history-list">
                <li v-for="item in historyItems" :key="item" class="filter-history-item" @mousedown="cancelBlur">
                    <button type="button" class="filter-history-term" @click="selectHistory(item)">
                        <svg class="filter-history-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0 1 1 0 002 0zm0 4a1 1 0 10-2 0 1 1 0 002 0zm0 4a1 1 0 10-2 0 1 1 0 002 0z" clip-rule="evenodd" />
                        </svg>
                        {{ item }}
                    </button>
                    <button type="button" class="filter-history-remove" :title="'Odstrániť ' + item"
                        @click="removeFromHistory(item)">
                        ×
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

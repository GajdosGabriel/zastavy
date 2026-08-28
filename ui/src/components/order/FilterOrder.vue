<script setup>
import { onMounted, onUnmounted, reactive, ref, watch, computed } from "vue";
import { storeToRefs } from "pinia";
import useOrders from "../../store/StoreOrders";
import useQuery from "../../store/StoreQuery";
import FilterOrderLabel from "./FilterOrderLabel.vue";
import { isActive, isConfirmed, isDeleted, isNotificated, resetFilter } from "../../models/filterLabels";

const HISTORY_KEY = "orderFilterHistory";
const MAX_HISTORY = 15;

const ordersStore = useOrders();
const { fetchOrders, fetchOrderStatistics } = ordersStore;
const { getStatuses } = storeToRefs(ordersStore);
const queryStore = useQuery();
const { getQuery } = storeToRefs(queryStore);
const { setQuery, removeQuery, resetQuery } = queryStore;
const labelList = reactive([isActive, isConfirmed, isDeleted, isNotificated]);

const shippedOptions = [
    { label: 'Dnes', value: 'dnes' },
    { label: 'Týždeň', value: 'tyzden' },
    { label: 'Mesiac', value: 'mesiac' },
];

const searchType = ref('customer');
const searchInput = ref('');
const status = ref("");
const shippedAt = ref("");
const showHistory = ref(false);
const historyItems = ref([]);

let debounceTimer = null;
let blurTimer = null;

// ── History (localStorage, samostatná história pre zákazníka a produkt) ──
const storageKey = computed(() => `${HISTORY_KEY}_${searchType.value}`);

const loadHistory = () => {
    try {
        historyItems.value = JSON.parse(localStorage.getItem(storageKey.value)) || [];
    } catch {
        localStorage.removeItem(storageKey.value);
        historyItems.value = [];
    }
};

const saveHistory = () => {
    localStorage.setItem(storageKey.value, JSON.stringify(historyItems.value));
};

const addToHistory = (term) => {
    const value = term.trim();
    if (!value) return;
    historyItems.value = [value, ...historyItems.value.filter(item => item !== value)].slice(0, MAX_HISTORY);
    saveHistory();
};

const removeFromHistory = (term) => {
    historyItems.value = historyItems.value.filter(item => item !== term);
    saveHistory();
};

const clearHistory = () => {
    historyItems.value = [];
    saveHistory();
    showHistory.value = false;
};

const selectHistory = (term) => {
    searchInput.value = term;
    showHistory.value = false;
    applySearch();
};

// ── Vyhľadávanie ─────────────────────────────────────────────
const activeSearchKey = computed(() =>
    searchType.value === 'customer' ? 'bySearchInput=' : 'searchByProduct='
);

const applySearch = () => {
    removeQuery({ key: 'bySearchInput=' });
    removeQuery({ key: 'searchByProduct=' });

    if (searchInput.value && searchInput.value.trim()) {
        setQuery({ key: activeSearchKey.value, value: searchInput.value.trim() });
        addToHistory(searchInput.value.trim());
    }
};

const onSearchInput = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(applySearch, 400);
};

const onSearchEnter = () => {
    clearTimeout(debounceTimer);
    applySearch();
};

const clearSearchInput = () => {
    searchInput.value = '';
    removeQuery({ key: 'bySearchInput=' });
    removeQuery({ key: 'searchByProduct=' });
};

const switchSearchType = () => {
    removeQuery({ key: 'bySearchInput=' });
    removeQuery({ key: 'searchByProduct=' });

    if (searchInput.value && searchInput.value.trim()) {
        setQuery({ key: activeSearchKey.value, value: searchInput.value.trim() });
    }

    loadHistory();
    showHistory.value = false;
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

// ── Watchers ─────────────────────────────────────────────────
watch(getQuery, () => {
    fetchOrders();

    if (getQuery.value.some((item) => item.startsWith('shippedAt='))) {
        fetchOrderStatistics();
    }
}, { deep: true });

watch(status, () => {
    status.value
        ? setQuery({ key: 'status=', value: status.value })
        : removeQuery({ key: 'status=' });
});

watch(shippedAt, () => {
    shippedAt.value
        ? setQuery({ key: 'shippedAt=', value: shippedAt.value })
        : removeQuery({ key: 'shippedAt=' });
});

// ── Label / expedičný filter ─────────────────────────────────
const onClickLabel = (object) => {
    labelList.forEach(item => item.key == object.key ? item.active = true : item.active = false);

    resetQuery();
    setQuery(object.key + object.value);

    if (status.value) {
        setQuery({ key: 'status=', value: status.value });
    }

    if (shippedAt.value) {
        setQuery({ key: 'shippedAt=', value: shippedAt.value });
    }

    if (searchInput.value && searchInput.value.trim()) {
        setQuery({ key: activeSearchKey.value, value: searchInput.value.trim() });
    }
};

// ── Reset všetkého ───────────────────────────────────────────
const onClearQuery = () => {
    searchInput.value = '';
    status.value = "";
    shippedAt.value = "";
    resetQuery();
    labelList.forEach(item => item.active = false);
};

onMounted(loadHistory);

onUnmounted(() => {
    resetQuery();
    clearTimeout(debounceTimer);
    clearTimeout(blurTimer);
});
</script>

<template>
    <div class="filter-panel">
        <div class="filter-row">
            <!-- Radio: typ hľadania -->
            <div class="filter-radio-group" @mousedown="cancelBlur">
                <label :class="['filter-radio-label', searchType === 'customer' && 'filter-radio-active']">
                    <input v-model="searchType" type="radio" value="customer" class="sr-only" @change="switchSearchType" />
                    Zákazník
                </label>
                <label :class="['filter-radio-label', searchType === 'product' && 'filter-radio-active']">
                    <input v-model="searchType" type="radio" value="product" class="sr-only" @change="switchSearchType" />
                    Produkt
                </label>
            </div>

            <!-- Jeden input + história -->
            <div class="filter-search-wrapper" @mousedown="cancelBlur">
                <div class="filter-control">
                    <svg class="filter-search-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <input
                        id="order-search"
                        v-model="searchInput"
                        type="text"
                        class="filter-input"
                        :placeholder="searchType === 'customer' ? 'Názov, IČO, mesto…' : 'Názov produktu…'"
                        autocomplete="off"
                        @input="onSearchInput"
                        @keydown.enter="onSearchEnter"
                        @focus="onInputFocus"
                        @blur="onInputBlur"
                    />
                    <button v-if="searchInput" type="button" class="filter-clear" aria-label="Zrušiť hľadanie"
                        @click="clearSearchInput">
                        ×
                    </button>
                </div>

                <!-- História hľadania -->
                <div v-if="showHistory && historyItems.length" class="filter-history-dropdown">
                    <div class="filter-history-header">
                        <span class="filter-history-title">
                            Nedávne hľadania ({{ searchType === 'customer' ? 'zákazník' : 'produkt' }})
                        </span>
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

            <!-- Status -->
            <div v-if="getStatuses.length" class="filter-field-compact">
                <select id="order-status" v-model="status" class="filter-select-compact">
                    <option value="">Všetky statusy</option>
                    <option v-for="item in getStatuses" :key="item.value" :value="item.value">
                        {{ item.label }}
                    </option>
                </select>
            </div>

            <!-- Expedícia -->
            <div class="filter-field-compact">
                <select id="order-shipped" v-model="shippedAt" class="filter-select-compact">
                    <option value="">Expedícia</option>
                    <option v-for="opt in shippedOptions" :key="opt.value" :value="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Label filtre v jednom riadku -->
        <div class="filter-chips-row">
            <FilterOrderLabel v-for="label in labelList" :key="label.key" :label="label"
                @labelemit="onClickLabel" />
            <FilterOrderLabel v-if="getQuery.length" :label="resetFilter" @labelemit="onClearQuery" />
        </div>
    </div>
</template>

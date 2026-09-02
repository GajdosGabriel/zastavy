<script setup>
import { onUnmounted, reactive, ref, watch, computed } from "vue";
import { storeToRefs } from "pinia";
import useOrders from "../../store/StoreOrders";
import useQuery from "../../store/StoreQuery";
import FilterLabel from "../plugins/filterLabel.vue";
import FilterSearch from "../plugins/filterSearch.vue";
import { isActive, isConfirmed, isDeleted, isNotificated, resetFilter } from "../../models/filterLabels";

const HISTORY_KEY = "orderFilterHistory";

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

// Samostatná história pre zákazníka a produkt
const historyKey = computed(() => `${HISTORY_KEY}_${searchType.value}`);

// ── Vyhľadávanie ─────────────────────────────────────────────
const activeSearchKey = computed(() =>
    searchType.value === 'customer' ? 'bySearchInput=' : 'searchByProduct='
);

const applySearch = (term = null) => {
    const search = (term ?? searchInput.value ?? '').trim();

    removeQuery({ key: 'bySearchInput=' });
    removeQuery({ key: 'searchByProduct=' });

    if (search) {
        setQuery({ key: activeSearchKey.value, value: search });
    }
};

const switchSearchType = () => {
    applySearch();
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

onUnmounted(() => {
    resetQuery();
});
</script>

<template>
    <div class="filter-panel">
        <div class="filter-row">
            <!-- Radio: typ hľadania -->
            <div class="filter-radio-group">
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
            <FilterSearch v-model="searchInput" :history-key="historyKey"
                :history-title="`Nedávne hľadania (${searchType === 'customer' ? 'zákazník' : 'produkt'})`"
                :placeholder="searchType === 'customer' ? 'Názov, IČO, mesto…' : 'Názov produktu…'"
                @search="applySearch" />

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
            <FilterLabel v-for="label in labelList" :key="label.key" :label="label"
                @labelemit="onClickLabel" />
            <FilterLabel v-if="getQuery.length" :label="resetFilter" @labelemit="onClearQuery" />
        </div>
    </div>
</template>

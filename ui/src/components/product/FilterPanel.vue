<script setup>
import { onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useProducts } from "../../store/StoreProducts";
import { useCategories } from "../../store/StoreCategories";
import useQuery from "../../store/StoreQuery";
import FilterSearch from "../plugins/filterSearch.vue";
import FilterLabel from "../plugins/filterLabel.vue";
import { resetFilter } from "../../models/filterLabels";

const { fetchProducts } = useProducts();
const categoriesStore = useCategories();
const { categories } = storeToRefs(categoriesStore);
const { fetchCategories } = categoriesStore;
const queryStore = useQuery();
const { getQuery } = storeToRefs(queryStore);
const { setQuery, removeQuery, resetQuery } = queryStore;

const searchInput = ref("");
const category = ref("");

const labelList = reactive([
    { name: 'Nepublikované', key: 'isUnpublished=', value: 'true', active: false },
    { name: 'Zmazané', key: 'isDeleted=', value: 'true', active: false },
]);

// ── Vyhľadávanie ─────────────────────────────────────────────
const applySearch = (term) => {
    term
        ? setQuery({ key: 'bySearchInput=', value: term })
        : removeQuery({ key: 'bySearchInput=' });
};

// ── Label filtre (dajú sa kombinovať) ────────────────────────
const onClickLabel = (label) => {
    label.active = !label.active;

    label.active
        ? setQuery({ key: label.key, value: label.value })
        : removeQuery({ key: label.key });
};

// ── Reset všetkého ───────────────────────────────────────────
const onClearQuery = () => {
    searchInput.value = "";
    category.value = "";
    labelList.forEach(item => item.active = false);
    resetQuery();
};

// ── Watchers ─────────────────────────────────────────────────
watch(getQuery, () => {
    fetchProducts();
}, { deep: true });

watch(category, () => {
    category.value
        ? setQuery({ key: 'byCategory=', value: category.value })
        : removeQuery({ key: 'byCategory=' });
});

onMounted(() => {
    fetchCategories();
});

onUnmounted(() => {
    resetQuery();
});
</script>

<template>
    <div class="filter-panel">
        <div class="filter-row">
            <!-- Hľadanie + história -->
            <FilterSearch v-model="searchInput" history-key="productFilterHistory"
                history-title="Nedávne hľadania (produkt)" placeholder="Názov, kód, EAN…"
                @search="applySearch" />

            <!-- Kategória -->
            <div class="filter-field-compact">
                <select id="product-category" v-model="category" class="filter-select-compact">
                    <option value="">Všetky kategórie</option>
                    <option v-for="item in categories" :key="item.id" :value="item.id">
                        {{ item.name }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Label filtre v jednom riadku -->
        <div class="filter-chips-row">
            <FilterLabel v-for="label in labelList" :key="label.key" :label="label" @labelemit="onClickLabel" />
            <FilterLabel v-if="getQuery.length" :label="resetFilter" @labelemit="onClearQuery" />
        </div>
    </div>
</template>

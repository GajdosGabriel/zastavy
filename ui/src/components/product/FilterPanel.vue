<script setup>
import ListFilter from "../plugins/ListFilter.vue";
import { computed, onMounted, onUnmounted, reactive, watch } from "vue";
import { storeToRefs } from "pinia";
import { useProducts } from "../../store/StoreProducts";
import { useCategories } from "../../store/StoreCategories";
import useQuery from "../../store/StoreQuery";

const { fetchProducts, resetUrl } = useProducts();
const categoriesStore = useCategories();
const { categories } = storeToRefs(categoriesStore);
const { fetchCategories } = categoriesStore;
const queryStore = useQuery();
const { getQuery } = storeToRefs(queryStore);
const { resetQuery } = queryStore;

const search = { historyKey: "productFilterHistory", placeholder: "Názov, kód, EAN…" };

const selects = computed(() => [{
    id: "category", label: "Kategória", queryKey: "byCategory=", emptyLabel: "Všetky kategórie",
    options: categories.value.map((item) => ({ value: item.id, label: item.name })),
    hidden: !categories.value.length,
}]);

// Dajú sa kombinovať (chipsMode multi).
const chips = reactive([
    { name: "Nepublikované", key: "isUnpublished=", value: "true", active: false },
    { name: "Zmazané", key: "isDeleted=", value: "true", active: false },
]);

watch(getQuery, () => {
    // Zmena filtra začína od prvej strany — stará ?page=N by mohla byť mimo rozsahu.
    resetUrl();
    fetchProducts();
}, { deep: true });

onMounted(() => {
    fetchCategories();
});

onUnmounted(() => {
    resetQuery();
});
</script>

<template>
    <ListFilter id="product-filters" :search="search" :selects="selects" :chips="chips" />
</template>

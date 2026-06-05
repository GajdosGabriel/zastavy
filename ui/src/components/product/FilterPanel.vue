<script setup>
import { onMounted, ref, watch } from "vue";
import useProducts from "../../store/StoreProducts";
import useCategories from "../../store/StoreCategories";
import useQuery from "../../store/StoreQuery";

const { fetchSearchInput, fetchProducts } = useProducts();
const { categories, fetchCategories } = useCategories();
const { state: sq, setQuery, removeQuery } = useQuery();

const onSearchInput = ref("");
const onCategory = ref("");
const props = defineProps(['quickMark', 'quickMarkSum']);

const isDeleted = ref({
    name: 'Zmazané',
    key: 'isDeleted=',
    value: 'true',
    boolean: false,
});
const isUnpublished = ref({
    name: 'Nepublikované',
    key: 'isUnpublished=',
    value: 'true',
    boolean: false,
});

const onInputChangeIsDeleted = (query) => {
    isDeleted.value.boolean ? setQuery(query) : removeQuery(query);
};

const onInputChangeIsUnpublished = (query) => {
    isUnpublished.value.boolean ? setQuery(query) : removeQuery(query);
};

onMounted(() => {
    fetchCategories();
});

watch(sq, () => {
    fetchProducts();
});

watch(onSearchInput, () => {
    fetchSearchInput("bySearchInput=" + onSearchInput.value);
});

watch(onCategory, () => {
    onCategory.value
        ? fetchSearchInput("byCategory=" + onCategory.value)
        : fetchSearchInput("");
});

const clearInput = () => {
    onSearchInput.value = "";
    removeQuery({ key: 'bySearchInput=' });
};

const searchInputText = (object) => {
    removeQuery(object);

    if (object.value) {
        setQuery(object);
    }
};
</script>

<template>
    <div class="filter-panel">
        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-1">
                <div class="filter-field">
                    <label class="filter-label" for="product-search">Hľadanie produktov</label>
                    <div class="filter-control">
                        <input id="product-search" type="text" v-model="onSearchInput" class="filter-input"
                            placeholder="Názov, IČO alebo mesto"
                            @input="searchInputText({ key: 'bySearchInput=', value: $event.target.value })" />
                        <button v-if="onSearchInput" type="button" class="filter-clear" aria-label="Zrušiť hľadanie"
                            @click="clearInput">
                            ×
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <label class="filter-check" for="onUnpublished">
                        <input type="checkbox" id="onUnpublished" v-model="isUnpublished.boolean"
                            @change="onInputChangeIsUnpublished(isUnpublished.key + isUnpublished.boolean)" />
                        {{ isUnpublished.name }}
                    </label>

                    <label class="filter-check" for="isDeleted">
                        <input type="checkbox" id="isDeleted" v-model="isDeleted.boolean"
                            @change="onInputChangeIsDeleted(isDeleted.key + isDeleted.boolean)" />
                        {{ isDeleted.name }}
                    </label>
                </div>

                <div class="filter-field">
                    <label class="filter-label" for="categories">Kategória</label>
                    <select v-model="onCategory" id="categories" class="filter-select">
                        <option value="">Všetky kategórie</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="overflow-hidden rounded-md border border-slate-300 bg-slate-50 lg:col-span-2">
                <div class="grid grid-cols-3 gap-4 bg-slate-800 px-4 py-2 text-sm font-semibold text-white">
                    <span>{{ quickMark.length ? 'Tovar' : 'Clipboard' }}</span>
                    <span v-if="quickMark.length">Ks</span>
                    <span v-if="quickMark.length" class="text-right">Hodnota</span>
                </div>

                <div v-if="quickMark.length" class="divide-y divide-slate-200 bg-white">
                    <div class="grid grid-cols-3 gap-4 px-4 py-2 text-sm" v-for="product in quickMark" :key="product.id">
                        <span class="font-semibold text-slate-900">{{ product.name }}</span>
                        <span class="text-slate-700">{{ product.quantity }} ks</span>
                        <span class="text-right font-medium text-slate-900">
                            {{ Math.trunc(product.active_price * product.quantity) }},- €
                        </span>
                    </div>
                </div>

                <div v-else class="bg-white px-4 py-6 text-sm text-slate-500">
                    Vybrané produkty sa zobrazia tu.
                </div>

                <div v-if="quickMark.length" class="flex justify-between border-t border-slate-300 bg-slate-100 px-4 py-2 text-sm">
                    <span class="font-semibold text-slate-700">Sumár spolu:</span>
                    <span class="font-semibold text-slate-900">{{ quickMarkSum }} €</span>
                </div>
            </div>
        </div>
    </div>
</template>

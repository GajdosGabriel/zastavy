<script setup>
import { onMounted, ref, watch, reactive } from "vue";
import useProducts from "../../store/StoreProducts";
import useCategories from "../../store/StoreCategories";
import iconPlus from "../../components/icons/plus.vue";
import useQuery from "../../store/StoreQuery";
import Cancel from "../icons/cancel.vue";


const { state, fetchSearchInput, fetchProducts } = useProducts();
const { categories, fetchCategories } = useCategories();
const { state: sq, setQuery, removeQuery, getQuery } = useQuery();

const onSearchInput = ref("");
const onUnpublished = ref(false);
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
}

const onInputChangeIsUnpublished = (query) => {
    isUnpublished.value.boolean ? setQuery(query) : removeQuery(query);
}

onMounted(() => {
    // fetchCategories();
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
};

const searchInputText = (object) => {
    removeQuery(object);
    setQuery(object);
};


</script>
<template>
    <div class="w-full flex mb-5 border-2 border-gray-200 rounded p-2">
        <div class="md:w-1/3 p-4">
            <div class="mb-5">
                <div class="form-group flex items-center">
                    <input type="text" v-model="onSearchInput" placeholder="Hľadanie, názov, ico, mesto" class="w-full"
                        @input="searchInputText({ key: 'bySearchInput=', value: $event.target.value })" />
                    <label class="w-6 h-full cursor-pointer px-3" v-if="onSearchInput" @click="clearInput">
                        <Cancel />
                    </label>
                </div>
            </div>

            <div class="mb-5 flex justify-between">
                <label for="onUnpublished">
                    {{ isUnpublished.name }}
                    <input type="checkbox" id="onUnpublished" v-model="isUnpublished.boolean"
                        @change="onInputChangeIsUnpublished(isUnpublished.key + isUnpublished.boolean)" />
                </label>


                <label for="isDeleted">{{ isDeleted.name }}
                    <input type="checkbox" id="isDeleted" v-model="isDeleted.boolean"
                        @change="onInputChangeIsDeleted(isDeleted.key + isDeleted.boolean)" />
                </label>
            </div>

            <div class="form-group">
                <!-- <label for="categories">Choose a car:</label> -->

                <select v-model="onCategory" id="categories" class="w-full">
                    <option value="">Vyber kategoriu</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
            </div>
        </div>

        <div class="border-2 rounded border-gray-300 md:w-2/3 m-4 relative">
            <div v-if="quickMark.length" class="bg-slate-600 text-gray-200 px-2 font-semibold grid grid-cols-3 gap-4">
                <span>Tovar</span>
                <span>Ks</span>
                <span class="text-right">Hodnota</span>
            </div>
            <div v-else class="bg-slate-600 text-gray-200 px-2 font-semibold grid grid-cols-3 gap-4">
                <span>ClipBoard</span>
                <!-- <span>Ks</span> -->
                <!-- <span class="text-right">Hodnota</span> -->
            </div>

            <div class="grid grid-cols-3 gap-1 pt-1" v-for="product in quickMark" :key="product.id">
                <span class="font-semibold">{{ product.name }}</span>
                {{ product.quantity }} ks
                <span class="px-1 ml-2 rounded-md text-right "> {{
                    Math.trunc(product.active_price * product.quantity) }},- €
                </span>
            </div>

            <div v-if="quickMark.length" class="flex justify-between bg-gray-300 p-1 w-full absolute  bottom-0">
                <span class="font-semibold">Sumár spolu:</span> <span class="font-semibold">{{ quickMarkSum }} €</span>
            </div>
        </div>

        <!-- <div class="flex justify-between bg-gray-200 p-2 md:w-1/3 m-4">
            <span>Sumár spolu:</span> <span>{{ quickMarkSum }} €</span>
        </div> -->
    </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive } from "vue";
import useQuery from "../../store/StoreQuery";

const isName = reactive({
    name: 'Meno',
    key: 'bySearchInput=',
    value: '',
    boolean: false,
});

const { setQuery, removeQuery } = useQuery();
const withoutOrder = ref<string>("");

watch(isName, () => {
    setQuery("bySearchInput=" + isName.value);
});

watch(withoutOrder, () => {
    withoutOrder.value
        ? setQuery("withoutOrder=" + withoutOrder.value)
        : removeQuery("withoutOrder=" + withoutOrder.value);
});

const clearInput = () => {
    removeQuery("bySearchInput=" + isName.value);
    isName.value = "";
};
</script>

<template>
    <div class="filter-panel">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="filter-field md:col-span-2">
                <label class="filter-label" for="customer-search">Hľadanie zákazníka</label>
                <div class="filter-control">
                    <input id="customer-search" type="text" v-model="isName.value" class="filter-input"
                        placeholder="Názov, IČO, mesto alebo e-mail" />
                    <button v-if="isName.value" type="button" class="filter-clear" aria-label="Zrušiť hľadanie"
                        @click="clearInput">
                        ×
                    </button>
                </div>
            </div>

            <div class="flex items-end">
                <label for="withoutOrder" class="filter-check">
                    <input type="checkbox" id="withoutOrder" v-model="withoutOrder" />
                    Bez objednávky
                </label>
            </div>
        </div>
    </div>
</template>

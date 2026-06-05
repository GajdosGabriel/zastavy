<script setup>
import { ref, watch } from "vue";
import useQuery from "../../store/StoreQuery";
import useStocks from "../../store/StoreStocks";

const { setQuery, removeQuery, resetQuery } = useQuery();
const { fetchSearchInput } = useStocks();

const onSearchInput = ref("");

const searchInputText = (object) => {
    removeQuery(object);

    if (object.value) {
        setQuery(object);
    }
};

watch(onSearchInput, () => {
    fetchSearchInput("bySearchInput=" + onSearchInput.value);
});

const clearInputText = () => {
    onSearchInput.value = "";
    resetQuery();
};
</script>

<template>
    <div class="filter-panel">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="filter-field md:col-span-2">
                <label class="filter-label" for="stock-search">Hľadanie skladu</label>
                <div class="filter-control">
                    <input id="stock-search" type="text" class="filter-input" placeholder="Názov alebo položka"
                        v-model="onSearchInput"
                        @input="searchInputText({ key: 'bySearchInput=', value: $event.target.value })" />

                    <button v-if="onSearchInput" type="button" class="filter-clear" aria-label="Zrušiť hľadanie"
                        @click="clearInputText">
                        ×
                    </button>
                </div>
            </div>

            <div class="rounded-md border border-slate-300 bg-slate-50 px-4 py-3">
                <div class="text-xs font-semibold uppercase text-slate-600">Rýchly prehľad</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">Nedodaný tovar</div>
            </div>
        </div>
    </div>
</template>

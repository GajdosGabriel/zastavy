<script setup>
import { ref, watch } from "vue";
import useQuery from "../../store/StoreQuery";
import useStocks from "../../store/StoreStocks";

const { setQuery, removeQuery, resetQuery } = useQuery();


const { state, fetchSearchInput } = useStocks();

const onSearchInput = ref("");

const searchInputText = (object) => {
    removeQuery(object);
    setQuery(object);
}
watch(onSearchInput, () => {
    fetchSearchInput("bySearchInput=" + onSearchInput.value);
});

const clearInputText = () => {
    onSearchInput.value = null
    resetQuery();
};
</script>

<template>
    <div class="flex space-x-4 justify-between mb-5 border-2 border-gray-200 rounded p-2">
        <div class="">
            <div class="mb-5">
                <div class="form-group">
                    <input type="text" placeholder="Hľadanie ..." v-model="onSearchInput"
                        @input="searchInputText({ key: 'bySearchInput=', value: $event.target.value })" />

                    <label class="w-6 h-full cursor-pointer px-3"
                        @click="clearInputText('bySearchInput=' + $event.target.value)">
                        <!-- <Cancel /> -->
                        X
                    </label>
                </div>
            </div>
        </div>

        <div class="bg-gray-200 p-2 md:w-1/3">
            <h1>Nedodaný tovar</h1>
        </div>
    </div>
</template>

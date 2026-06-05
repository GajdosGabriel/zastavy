<template>
    <div class="flex space-x-4 justify-between mb-5 border-2 border-gray-200 rounded p-2">
        <div class="">
            <div class="mb-5">
                <div class="flex items-center">
                    <input type="text" v-model="isName.value" placeholder="Hľadanie, názov, ico, mesto, email" />
                    <label class="w-6 h-full cursor-pointer px-3" v-if="isName.value" @click="clearInput">X</label>
                </div>
            </div>

            <label for="withoutOrder" class="mr-4">Bez objednávky
                <input type="checkbox" id="withoutOrder" v-model="withoutOrder" />
            </label>
        </div>

        <div class="bg-gray-200 p-2 md:w-1/3">

        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch, reactive } from "vue";

import useCustomers from "../../store/StoreCustomers";
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

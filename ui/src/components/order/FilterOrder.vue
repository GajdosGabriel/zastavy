<script setup>
import { onUnmounted, reactive, watch } from "vue";
import useOrders from "../../store/StoreOrders";
import useQuery from "../../store/StoreQuery";
import FilterOrderLabel from "./FilterOrderLabel.vue";
import { isActive, isConfirmed, isDeleted, isNotificated, resetFilter } from "../../models/filterLabels";


const { fetchOrders } = useOrders();
const { state, setQuery, removeQuery, resetQuery } = useQuery();
const labelList = reactive([isActive, isConfirmed, isDeleted, isNotificated]);

const input = reactive({
    customer: null,
    product: null,
});


watch(state, () => {
    fetchOrders();
});

const searchInputText = (object) => {
    removeQuery(object);
    setQuery(object);
};

const clearInputCustomer = () => {
    input.customer = null
    resetQuery();
};

const clearInputProduct = () => {
    input.product = null
    resetQuery();
};

const onClearQuery = () => {
    input.customer = null
    input.product = null
    resetQuery();
    labelList.forEach(item => item.active = false);
};

onUnmounted(() => {
    resetQuery();
});

const onClickLabel = (object) => {

    labelList.forEach(item => item.key == object.key ? item.active = true : item.active = false);

    resetQuery();
    setQuery(object.key + object.value);
};
</script>

<template>
    <div class="flex space-x-4 justify-between mb-5 border-2 border-gray-200 rounded p-2">
        <div>
            <div>
                <div class="form-group">
                    <input type="text" placeholder="Hľadanie, názov, ico, mesto" v-model="input.customer"
                        @input="searchInputText({ key: 'bySearchInput=', value: $event.target.value })" />
                    <label class="w-6 h-full cursor-pointer px-3"
                        @click="clearInputCustomer('bySearchInput=' + $event.target.value)">
                        <!-- <Cancel /> -->
                        X
                    </label>
                </div>

                <div class="form-group">
                    <input type="text" @input="searchInputText({ key: 'searchByProduct=', value: $event.target.value })"
                        v-model="input.product" placeholder="Hľadanie podľa produktu" />
                    <label class="w-6 h-full cursor-pointer px-3"
                        @click="clearInputProduct('searchByProduct=' + $event.target.value)">
                        <!-- <Cancel /> -->
                        X
                    </label>
                </div>
            </div>

            <div class="flex grid-cols-4 gap-2">
                <FilterOrderLabel v-for="label in labelList" @labelemit="onClickLabel" :label="label"
                    :key="label.key" />
            </div>


            <div class="flex grid-cols-4 gap-2 mt-2">
                <FilterOrderLabel @labelemit="onClearQuery" :label="resetFilter" v-if="state.query.length" />
            </div>
        </div>

    </div>
</template>

<script setup>
import { onUnmounted, reactive, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import useOrders from "../../store/StoreOrders";
import useQuery from "../../store/StoreQuery";
import FilterOrderLabel from "./FilterOrderLabel.vue";
import { isActive, isConfirmed, isDeleted, isNotificated, resetFilter } from "../../models/filterLabels";

const ordersStore = useOrders();
const { fetchOrders } = ordersStore;
const { getStatuses } = storeToRefs(ordersStore);
const queryStore = useQuery();
const { getQuery } = storeToRefs(queryStore);
const { setQuery, removeQuery, resetQuery } = queryStore;
const labelList = reactive([isActive, isConfirmed, isDeleted, isNotificated]);

const input = reactive({
    customer: null,
    product: null,
});

const status = ref("");

watch(getQuery, () => {
    fetchOrders();
}, { deep: true });

const searchInputText = (object) => {
    removeQuery(object);

    if (object.value) {
        setQuery(object);
    }
};

const clearInputCustomer = () => {
    input.customer = null;
    removeQuery({ key: 'bySearchInput=' });
};

const clearInputProduct = () => {
    input.product = null;
    removeQuery({ key: 'searchByProduct=' });
};

watch(status, () => {
    status.value
        ? setQuery({ key: 'status=', value: status.value })
        : removeQuery({ key: 'status=' });
});

const onClearQuery = () => {
    input.customer = null;
    input.product = null;
    status.value = "";
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

    // Label resetuje celý query — status filter drž ďalej zapnutý.
    if (status.value) {
        setQuery({ key: 'status=', value: status.value });
    }
};
</script>

<template>
    <div class="filter-panel">
        <div class="filter-grid">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="filter-field">
                    <label class="filter-label" for="order-customer-search">Hľadanie zákazníka</label>
                    <div class="filter-control">
                        <input id="order-customer-search" type="text" class="filter-input"
                            placeholder="Názov, IČO, mesto" v-model="input.customer"
                            @input="searchInputText({ key: 'bySearchInput=', value: $event.target.value })" />
                        <button v-if="input.customer" type="button" class="filter-clear" aria-label="Zrušiť hľadanie"
                            @click="clearInputCustomer">
                            ×
                        </button>
                    </div>
                </div>

                <div class="filter-field">
                    <label class="filter-label" for="order-product-search">Hľadanie podľa produktu</label>
                    <div class="filter-control">
                        <input id="order-product-search" type="text" class="filter-input"
                            placeholder="Názov produktu alebo položka" v-model="input.product"
                            @input="searchInputText({ key: 'searchByProduct=', value: $event.target.value })" />
                        <button v-if="input.product" type="button" class="filter-clear"
                            aria-label="Zrušiť hľadanie produktu" @click="clearInputProduct">
                            ×
                        </button>
                    </div>
                </div>

                <div v-if="getStatuses.length" class="filter-field">
                    <label class="filter-label" for="order-status">Status</label>
                    <select id="order-status" v-model="status" class="filter-select">
                        <option value="">Všetky statusy</option>
                        <option v-for="item in getStatuses" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <FilterOrderLabel v-for="label in labelList" :key="label.key" :label="label"
                    @labelemit="onClickLabel" />
                <FilterOrderLabel v-if="getQuery.length" :label="resetFilter" @labelemit="onClearQuery" />
            </div>
        </div>
    </div>
</template>

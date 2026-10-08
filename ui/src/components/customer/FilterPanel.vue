<script setup lang="ts">
import CollapsibleFilterPanel from "../plugins/CollapsibleFilterPanel.vue";
import { ref, watch, reactive } from "vue";
import { storeToRefs } from "pinia";
import useQuery from "../../store/StoreQuery";
import useCustomers from "../../store/StoreCustomers";
import { CUSTOMER_TYPES } from "../../constants";

const isName = reactive({
    name: 'Meno',
    key: 'bySearchInput=',
    value: '',
    boolean: false,
});

const { setQuery, removeQuery } = useQuery();
const { getStatuses } = storeToRefs(useCustomers());
const withoutOrder = ref<string>("");
const status = ref<string>("");
const type = ref<string>("");

watch(isName, () => {
    setQuery("bySearchInput=" + isName.value);
});

watch(withoutOrder, () => {
    withoutOrder.value
        ? setQuery("withoutOrder=" + withoutOrder.value)
        : removeQuery("withoutOrder=" + withoutOrder.value);
});

watch(status, () => {
    status.value
        ? setQuery("status=" + status.value)
        : removeQuery("status=");
});

watch(type, () => {
    type.value
        ? setQuery("type=" + type.value)
        : removeQuery("type=");
});

const clearInput = () => {
    removeQuery("bySearchInput=" + isName.value);
    isName.value = "";
};
</script>

<template>
    <CollapsibleFilterPanel id="customer-filters">
        <div class="grid gap-4 md:grid-cols-5">
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

            <div class="filter-field">
                <label class="filter-label" for="customer-type">Typ</label>
                <select id="customer-type" v-model="type" class="filter-select">
                    <option value="">Všetky typy</option>
                    <option v-for="item in CUSTOMER_TYPES" :key="item.value" :value="item.value">
                        {{ item.label }}
                    </option>
                </select>
            </div>

            <div v-if="getStatuses.length" class="filter-field">
                <label class="filter-label" for="customer-status">Status</label>
                <select id="customer-status" v-model="status" class="filter-select">
                    <option value="">Všetky statusy</option>
                    <option v-for="item in getStatuses" :key="item.value" :value="item.value">
                        {{ item.label }}
                    </option>
                </select>
            </div>

            <div class="flex items-end">
                <label for="withoutOrder" class="filter-check">
                    <input type="checkbox" id="withoutOrder" v-model="withoutOrder" />
                    Bez objednávky
                </label>
            </div>
        </div>
    </CollapsibleFilterPanel>
</template>

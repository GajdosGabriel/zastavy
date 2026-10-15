<script setup lang="ts">
import ListFilter from "../plugins/ListFilter.vue";
import { computed } from "vue";
import { storeToRefs } from "pinia";
import useCustomers from "../../store/StoreCustomers";
import { CUSTOMER_TYPES } from "../../constants";

// Načítanie pri zmene filtra rieši CustomerIndex (sleduje query string).
const { getStatuses } = storeToRefs(useCustomers());

const search = { historyKey: "customerFilterHistory", placeholder: "Názov, IČO, mesto alebo e-mail" };

const selects = computed(() => [
    { id: "type", label: "Typ", queryKey: "type=", emptyLabel: "Všetky typy", options: CUSTOMER_TYPES },
    {
        id: "status", label: "Status", queryKey: "status=", emptyLabel: "Všetky statusy",
        options: getStatuses.value, hidden: !getStatuses.value.length,
    },
]);

const toggles = [{ id: "withoutOrder", label: "Bez objednávky", queryKey: "withoutOrder=", value: "true" }];
</script>

<template>
    <ListFilter id="customer-filters" :search="search" :selects="selects" :toggles="toggles" />
</template>

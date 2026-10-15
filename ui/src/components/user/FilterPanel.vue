<script setup>
import ListFilter from "../plugins/ListFilter.vue";
import { computed } from "vue";
import { storeToRefs } from "pinia";
import { useAdminUsers } from "../../store/StoreAdminUsers";

// Načítanie pri zmene filtra rieši UserIndex (sleduje query string).
const { getStatuses } = storeToRefs(useAdminUsers());

const search = { historyKey: "userFilterHistory", placeholder: "Meno, e-mail, telefón, firma alebo mesto" };

const selects = computed(() => [
    {
        id: "role", label: "Rola", queryKey: "role=", emptyLabel: "Všetky roly",
        options: [
            { value: "super-admin", label: "Super admin" },
            { value: "admin", label: "Admin" },
            { value: "manager", label: "Manažér" },
            { value: "sales", label: "Obchod" },
            { value: "warehouse", label: "Sklad" },
            { value: "customer", label: "Zákazník" },
        ],
    },
    {
        id: "status", label: "Status", queryKey: "status=", emptyLabel: "Všetky statusy",
        options: getStatuses.value, hidden: !getStatuses.value.length,
    },
    {
        id: "login", label: "Prihlásenie", queryKey: "login=", emptyLabel: "Všetci",
        options: [
            { value: "never", label: "Nikdy neprihlásení" },
            { value: "logged", label: "Aspoň raz prihlásení" },
            { value: "last30", label: "Prihlásení za 30 dní" },
            { value: "over90", label: "Neprihlásení viac ako 90 dní" },
        ],
    },
]);
</script>

<template>
    <ListFilter id="user-filters" :search="search" :selects="selects" />
</template>

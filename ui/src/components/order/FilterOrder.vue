<script setup>
import ListFilter from "../plugins/ListFilter.vue";
import { computed, onUnmounted, reactive, watch } from "vue";
import { useRoute } from "vue-router";
import { storeToRefs } from "pinia";
import useOrders from "../../store/StoreOrders";
import useQuery from "../../store/StoreQuery";
import { isActive, isConfirmed, isDeleted, isNotificated } from "../../models/filterLabels";

const ordersStore = useOrders();
const { fetchOrders, fetchOrderStatistics } = ordersStore;
const { getStatuses } = storeToRefs(ordersStore);
const queryStore = useQuery();
const { getQuery } = storeToRefs(queryStore);
const { setQuery, resetQuery } = queryStore;

// Stavové chipy sa navzájom vylučujú (chipsMode single).
const chips = reactive([isActive, isConfirmed, isDeleted, isNotificated]);

const search = {
    historyKey: "orderFilterHistory",
    modes: [
        { value: "customer", label: "Zákazník", queryKey: "bySearchInput=", placeholder: "Názov, IČO, mesto…" },
        { value: "product", label: "Produkt", queryKey: "searchByProduct=", placeholder: "Názov produktu…" },
    ],
};

const selects = computed(() => [
    {
        id: "status", label: "Status", queryKey: "status=", emptyLabel: "Všetky statusy",
        options: getStatuses.value, hidden: !getStatuses.value.length,
    },
    {
        id: "shippedAt", label: "Expedované", queryKey: "shippedAt=", emptyLabel: "Kedykoľvek",
        options: [
            { label: "Dnes", value: "dnes" },
            { label: "Týždeň", value: "tyzden" },
            { label: "Mesiac", value: "mesiac" },
        ],
    },
]);

// ── Predvolený filter z URL (napr. preklik z dashboardu) ─────
// Musí prebehnúť pred registráciou watchera — zoznam si ho načíta
// v OrderIndex onMounted, takže nevznikne dvojitý request.
const route = useRoute();
const initial = { selects: {} };

const applyRouteQuery = () => {
    const { filter, status, shippedAt, product, customer, marked } = route.query;

    if (![filter, status, shippedAt, product, customer, marked].some(Boolean)) {
        return;
    }

    resetQuery();
    chips.forEach((item) => (item.active = false));

    const chip = chips.find((item) => item.key === `${filter}=`);
    if (chip) {
        chip.active = true;
        setQuery({ key: chip.key, value: chip.value });
    }

    if (status) {
        initial.selects.status = String(status);
        setQuery({ key: "status=", value: initial.selects.status });
    }

    if (shippedAt) {
        initial.selects.shippedAt = String(shippedAt);
        setQuery({ key: "shippedAt=", value: initial.selects.shippedAt });
    }

    if (product || customer) {
        const mode = search.modes.find((item) => item.value === (product ? "product" : "customer"));
        initial.mode = mode.value;
        initial.search = String(product || customer);
        setQuery({ key: mode.queryKey, value: initial.search });
    }

    ordersStore.statement.markSelected = Boolean(marked);
    if (marked) {
        setQuery("isMarked=true");
    }
    // Adresu ďalej udržiava v súlade s filtrom OrderIndex.
};

applyRouteQuery();

watch(getQuery, () => {
    // Zmena filtra začína od prvej strany — stará ?page=N by mohla byť mimo rozsahu.
    ordersStore.resetUrl();
    fetchOrders();

    if (getQuery.value.some((item) => item.startsWith("shippedAt="))) {
        fetchOrderStatistics();
    }
}, { deep: true });

onUnmounted(() => {
    resetQuery();
    chips.forEach((item) => (item.active = false));
    ordersStore.statement.markSelected = false;
});
</script>

<template>
    <ListFilter id="order-filters" :search="search" :selects="selects" :chips="chips" chips-mode="single"
        :initial="initial" />
</template>

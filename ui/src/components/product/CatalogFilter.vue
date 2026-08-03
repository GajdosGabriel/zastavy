<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useAttributes } from "../../store/StoreAttributes";
import { useHome } from "../../store/StoreHome";
import useQuery from "../../store/StoreQuery";

const attributesStore = useAttributes();
const { getFacets } = storeToRefs(attributesStore);
const { fetchFacets } = attributesStore;

const { applyFilters } = useHome();
const { setQuery, removeQuery } = useQuery();

// { rozmer: ['100x150', '100x70'] }
const selected = reactive({});
const inStock = ref(false);
const priceFrom = ref("");
const priceTo = ref("");

onMounted(() => fetchFacets());

const isChecked = (code, valueCode) => (selected[code] ?? []).includes(valueCode);

const toggle = (code, valueCode) => {
    const current = selected[code] ?? [];
    selected[code] = current.includes(valueCode)
        ? current.filter((v) => v !== valueCode)
        : [...current, valueCode];

    if (!selected[code].length) {
        delete selected[code];
    }
};

const activeCount = computed(() =>
    Object.values(selected).reduce((sum, values) => sum + values.length, 0)
    + (inStock.value ? 1 : 0)
    + (priceFrom.value ? 1 : 0)
    + (priceTo.value ? 1 : 0)
);

/**
 * Formát `rozmer:100x150|100x70,material:polyester` — v rámci vlastnosti OR,
 * medzi vlastnosťami AND.
 */
const facetQuery = computed(() =>
    Object.entries(selected)
        .filter(([, values]) => values.length)
        .map(([code, values]) => `${code}:${values.join("|")}`)
        .join(",")
);

const syncQuery = () => {
    const apply = (key, value) => {
        if (value === "" || value === null || value === false) {
            removeQuery({ key });
        } else {
            setQuery({ key, value });
        }
    };

    apply("byAttribute=", facetQuery.value);
    apply("inStock=", inStock.value ? "1" : "");
    apply("priceFrom=", priceFrom.value);
    apply("priceTo=", priceTo.value);

    applyFilters();
};

const reset = () => {
    Object.keys(selected).forEach((key) => delete selected[key]);
    inStock.value = false;
    priceFrom.value = "";
    priceTo.value = "";
};

watch([selected, inStock], syncQuery, { deep: true });
</script>

<template>
    <section v-if="getFacets.length" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Filtre</h2>
            <button v-if="activeCount" type="button" @click="reset"
                class="text-xs text-slate-400 transition hover:text-red-600">
                × zrušiť ({{ activeCount }})
            </button>
        </div>

        <div class="space-y-4">
            <div v-for="facet in getFacets" :key="facet.code">
                <p class="mb-1.5 text-sm font-semibold text-slate-700">{{ facet.name }}</p>
                <div class="space-y-1">
                    <label v-for="value in facet.values" :key="value.id"
                        class="flex cursor-pointer items-center justify-between gap-2 rounded px-1.5 py-1 text-sm transition hover:bg-slate-50">
                        <span class="flex items-center gap-2 text-slate-700">
                            <input type="checkbox" class="accent-blue-600"
                                :checked="isChecked(facet.code, value.code)"
                                @change="toggle(facet.code, value.code)" />
                            {{ value.value }}
                        </span>
                        <span class="text-xs text-slate-400">{{ value.count }}</span>
                    </label>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-3">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" v-model="inStock" class="accent-blue-600" />
                    Len skladom
                </label>
            </div>

            <div class="border-t border-slate-100 pt-3">
                <p class="mb-1.5 text-sm font-semibold text-slate-700">Cena s DPH</p>
                <div class="flex items-center gap-2">
                    <input v-model="priceFrom" type="number" min="0" placeholder="od" @change="syncQuery"
                        class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
                    <span class="text-slate-400">–</span>
                    <input v-model="priceTo" type="number" min="0" placeholder="do" @change="syncQuery"
                        class="w-full rounded border border-slate-300 px-2 py-1 text-sm" />
                </div>
            </div>
        </div>
    </section>
</template>

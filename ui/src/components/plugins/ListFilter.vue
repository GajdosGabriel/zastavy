<script setup>
/**
 * Spoločný filter zoznamov (objednávky, produkty, zákazníci, používatelia).
 *
 * Rozloženie je vždy rovnaké: hľadanie (voliteľne s prepínačom „hľadať v“) → selecty → zaškrtávače,
 * pod tým chipy rýchlych filtrov a „Zrušiť filtre“. Komponent iba zapisuje do StoreQuery —
 * načítanie dát rieši zoznam, ktorý sleduje query.
 *
 * Konfigurácia:
 *  search:  { historyKey, placeholder, queryKey = 'bySearchInput=', modes?: [{ value, label, queryKey, placeholder }] }
 *  selects: [{ id, label, queryKey, emptyLabel, options: [{ value, label }], hidden? }]
 *  toggles: [{ id, label, queryKey, value = 'true' }]
 *  chips:   reaktívne objekty { name, key, value, active, iconRight? }; chipsMode 'single' = vylučujú sa
 *  initial: { search, mode, selects: { id: value }, toggles: { id: bool } } — predvolený stav bez zápisu do query
 *           (napr. keď ho z URL zapíše obal sám)
 */
import { computed, reactive, ref, watch } from "vue";
import useQuery from "../../store/StoreQuery";
import CollapsibleFilterPanel from "./CollapsibleFilterPanel.vue";
import FilterSearch from "./filterSearch.vue";
import FilterLabel from "./filterLabel.vue";

const props = defineProps({
    id: { type: String, required: true },
    search: { type: Object, default: null },
    selects: { type: Array, default: () => [] },
    toggles: { type: Array, default: () => [] },
    chips: { type: Array, default: () => [] },
    chipsMode: { type: String, default: "multi" },
    initial: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["reset"]);
const { setQuery, removeQuery } = useQuery();

const searchKey = (field) => field.queryKey ?? "bySearchInput=";

// ── Stav (predvolený z `initial`, watchery sa registrujú až potom → žiadny dvojitý zápis) ──
const term = ref(props.initial.search ?? "");
const mode = ref(props.initial.mode ?? props.search?.modes?.[0]?.value ?? null);
const selectValues = reactive(Object.fromEntries(
    props.selects.map((select) => [select.id, props.initial.selects?.[select.id] ?? ""])
));
const toggleValues = reactive(Object.fromEntries(
    props.toggles.map((toggle) => [toggle.id, Boolean(props.initial.toggles?.[toggle.id])])
));

// ── Hľadanie ─────────────────────────────────────────────────
const modes = computed(() => props.search?.modes ?? []);
const activeMode = computed(() => modes.value.find((item) => item.value === mode.value) ?? null);
const activeSearchKey = computed(() => activeMode.value?.queryKey ?? searchKey(props.search ?? {}));
const searchKeys = computed(() => modes.value.length ? modes.value.map((item) => item.queryKey) : [activeSearchKey.value]);
const historyKey = computed(() => props.search
    ? (activeMode.value ? `${props.search.historyKey}_${activeMode.value.value}` : props.search.historyKey)
    : "");

const applySearch = (value = term.value) => {
    const search = (value ?? "").trim();
    searchKeys.value.forEach((key) => removeQuery({ key }));
    if (search) setQuery({ key: activeSearchKey.value, value: search });
};

// Pri zmene „hľadať v“ sa rovnaký text presunie pod iný parameter.
watch(mode, () => applySearch());

// ── Selecty a zaškrtávače ────────────────────────────────────
props.selects.forEach((select) => {
    watch(() => selectValues[select.id], (value) => {
        value ? setQuery({ key: select.queryKey, value }) : removeQuery({ key: select.queryKey });
    });
});

props.toggles.forEach((toggle) => {
    watch(() => toggleValues[toggle.id], (checked) => {
        checked ? setQuery({ key: toggle.queryKey, value: toggle.value ?? "true" }) : removeQuery({ key: toggle.queryKey });
    });
});

// ── Chipy ────────────────────────────────────────────────────
const onClickChip = (chip) => {
    const activate = !chip.active;

    if (props.chipsMode === "single") {
        props.chips.forEach((item) => {
            if (item !== chip && item.active) {
                item.active = false;
                removeQuery({ key: item.key });
            }
        });
    }

    chip.active = activate;
    activate ? setQuery({ key: chip.key, value: chip.value }) : removeQuery({ key: chip.key });
};

// ── Reset — maže iba vlastné parametre (triedenie či hviezdička zoznamu ostanú) ──
const hasActive = computed(() => Boolean(term.value.trim())
    || Object.values(selectValues).some(Boolean)
    || Object.values(toggleValues).some(Boolean)
    || props.chips.some((chip) => chip.active));

const resetLabel = { name: "Zrušiť filtre", key: "resetFilter", active: false };

const reset = () => {
    term.value = "";
    searchKeys.value.forEach((key) => removeQuery({ key }));
    props.selects.forEach((select) => (selectValues[select.id] = ""));
    props.toggles.forEach((toggle) => (toggleValues[toggle.id] = false));
    props.chips.forEach((chip) => {
        chip.active = false;
        removeQuery({ key: chip.key });
    });
    emit("reset");
};

defineExpose({ reset });
</script>

<template>
    <CollapsibleFilterPanel :id="id">
        <div class="filter-bar">
            <!-- Hľadanie -->
            <div v-if="search" class="filter-field filter-field-search">
                <div class="flex min-h-5 items-center justify-between gap-2">
                    <label class="filter-label" :for="`${id}-search`">Hľadať</label>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div v-if="modes.length" class="filter-radio-group" role="radiogroup" aria-label="Hľadať v">
                        <label v-for="item in modes" :key="item.value"
                            :class="['filter-radio-label', mode === item.value && 'filter-radio-active']">
                            <input v-model="mode" type="radio" :value="item.value" class="sr-only" />
                            {{ item.label }}
                        </label>
                    </div>
                    <FilterSearch v-model="term" :input-id="`${id}-search`" :history-key="historyKey"
                        :history-title="activeMode ? `Nedávne hľadania (${activeMode.label.toLowerCase()})` : 'Nedávne hľadania'"
                        :placeholder="activeMode?.placeholder ?? search.placeholder"
                        @search="applySearch" />
                </div>
            </div>

            <!-- Selecty -->
            <template v-for="select in selects" :key="select.id">
                <div v-if="!select.hidden" class="filter-field filter-field-select">
                    <label class="filter-label" :for="`${id}-${select.id}`">{{ select.label }}</label>
                    <select :id="`${id}-${select.id}`" v-model="selectValues[select.id]" class="filter-select">
                        <option value="">{{ select.emptyLabel }}</option>
                        <option v-for="option in select.options" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                </div>
            </template>

            <!-- Zaškrtávače -->
            <label v-for="toggle in toggles" :key="toggle.id" :for="`${id}-${toggle.id}`" class="filter-check">
                <input :id="`${id}-${toggle.id}`" v-model="toggleValues[toggle.id]" type="checkbox" class="h-4 w-4" />
                {{ toggle.label }}
            </label>
        </div>

        <!-- Rýchle filtre + reset -->
        <div v-if="chips.length || hasActive" class="filter-chips-row" role="group" aria-label="Rýchle filtre">
            <FilterLabel v-for="chip in chips" :key="chip.key" :label="chip" @labelemit="onClickChip" />
            <FilterLabel v-if="hasActive" :label="resetLabel" @labelemit="reset" />
        </div>
    </CollapsibleFilterPanel>
</template>

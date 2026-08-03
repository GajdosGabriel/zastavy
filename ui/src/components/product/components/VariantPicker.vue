<script setup>
import { computed, reactive, watch } from "vue";
import { formatDecimal } from "../../../models/functions";

const props = defineProps({
    variants: { type: Array, default: () => [] },
    modelValue: { type: Object, default: null },
});

const emit = defineEmits(["update:modelValue"]);

const sellable = computed(() => props.variants.filter((v) => v.published));

const idsOf = (variant) => (variant.attribute_values ?? []).map((v) => v.id).sort();

/**
 * Vlastnosti odvodíme z variantov, nie z taxonómie — na karte majú byť len
 * tie hodnoty, ktoré sa naozaj dajú kúpiť.
 */
const attributes = computed(() => {
    const map = new Map();

    sellable.value.forEach((variant) => {
        (variant.attribute_values ?? []).forEach((value) => {
            if (!map.has(value.attribute_id)) {
                map.set(value.attribute_id, { id: value.attribute_id, values: new Map() });
            }
            map.get(value.attribute_id).values.set(value.id, value);
        });
    });

    return [...map.values()].map((attribute) => ({
        id: attribute.id,
        values: [...attribute.values.values()].sort((a, b) => a.sort_order - b.sort_order),
    }));
});

const selected = reactive({});

const adopt = (variant) => {
    Object.keys(selected).forEach((key) => delete selected[key]);
    (variant?.attribute_values ?? []).forEach((value) => {
        selected[value.attribute_id] = value.id;
    });
};

const selectedVariant = computed(() => {
    const chosen = Object.values(selected).filter(Boolean).sort();

    if (!attributes.value.length) {
        return sellable.value[0] ?? null;
    }

    return sellable.value.find(
        (variant) => JSON.stringify(idsOf(variant)) === JSON.stringify(chosen)
    ) ?? null;
});

/**
 * Hodnota je dostupná, ak existuje variant, ktorý ju obsahuje a zároveň
 * sedí so zvyškom výberu — inak by sa dala naklikať neexistujúca kombinácia.
 */
const isAvailable = (attributeId, valueId) =>
    sellable.value.some((variant) => {
        const ids = idsOf(variant);
        if (!ids.includes(valueId)) return false;

        return Object.entries(selected).every(([otherId, otherValue]) =>
            Number(otherId) === attributeId || !otherValue || ids.includes(otherValue)
        );
    });

const onPick = (attributeId, valueId) => {
    selected[attributeId] = valueId;

    if (selectedVariant.value) return;

    // Výber sa rozišiel s ponukou — prevezmeme prvý variant s touto hodnotou.
    const fallback = sellable.value.find((variant) => idsOf(variant).includes(valueId));
    if (fallback) adopt(fallback);
};

watch(selectedVariant, (variant) => emit("update:modelValue", variant), { immediate: true });

watch(
    sellable,
    (variants) => {
        if (!variants.length) return;
        const current = selectedVariant.value;
        if (!current || !variants.some((v) => v.id === current.id)) {
            adopt(variants.find((v) => v.is_default) ?? variants[0]);
        }
    },
    { immediate: true }
);

const labelFor = (attributeId) => {
    const value = sellable.value
        .flatMap((variant) => variant.attribute_values ?? [])
        .find((v) => v.attribute_id === attributeId);

    return value?.attribute?.name ?? 'Prevedenie';
};
</script>

<template>
    <div v-if="attributes.length" class="space-y-4">
        <div v-for="attribute in attributes" :key="attribute.id">
            <p class="mb-2 text-sm font-semibold text-gray-700">{{ labelFor(attribute.id) }}</p>
            <div class="flex flex-wrap gap-2">
                <button v-for="value in attribute.values" :key="value.id" type="button"
                    @click="onPick(attribute.id, value.id)"
                    :disabled="!isAvailable(attribute.id, value.id)"
                    class="rounded-md border px-3 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-50 disabled:text-gray-300 disabled:line-through"
                    :class="selected[attribute.id] === value.id
                        ? 'border-blue-600 bg-blue-50 text-blue-700 ring-1 ring-blue-300'
                        : 'border-gray-300 bg-white text-gray-700 hover:border-blue-400'">
                    {{ value.value }}
                </button>
            </div>
        </div>

        <p v-if="selectedVariant" class="text-xs text-gray-500">
            Kód: <span class="font-mono">{{ selectedVariant.code }}</span>
            <span v-if="!selectedVariant.is_in_stock" class="ml-2 font-semibold text-red-600">
                — momentálne vypredané
            </span>
        </p>
    </div>

    <!-- Produkt bez rozlíšenia: jediná skladová položka, nie je čo vyberať -->
    <p v-else-if="selectedVariant && !selectedVariant.is_in_stock" class="text-sm font-semibold text-red-600">
        Momentálne vypredané
    </p>
</template>

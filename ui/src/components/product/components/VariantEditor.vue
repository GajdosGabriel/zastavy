<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { storeToRefs } from "pinia";
import { useProducts, defaultVariant } from "../../../store/StoreProducts";
import { useAttributes } from "../../../store/StoreAttributes";
import { formatDecimal } from "../../../models/functions";

const props = defineProps({
    productId: { type: [String, Number], default: null },
});

const productStore = useProducts();
const { getVariants } = storeToRefs(productStore);
const { fetchVariants, storeVariant, updateVariant, destroyVariant } = productStore;

const attributesStore = useAttributes();
const { getVariantAttributes } = storeToRefs(attributesStore);
const { fetchAttributes } = attributesStore;

// Rozpracované úpravy držíme mimo store — kým sa neuloží, nesmie sa to
// premietnuť do zoznamu produktov.
const drafts = reactive({});
const isAdding = ref(false);
const newVariant = ref(blankDraft());
const savingId = ref(null);

function blankDraft() {
    return { ...defaultVariant(), selected: {} };
}

const toDraft = (variant) => ({
    ...variant,
    sale_price: variant.sale_price ?? '',
    discount: variant.discount ?? '',
    quantity: variant.quantity ?? '',
    weight: variant.weight ?? '',
    ean: variant.ean ?? '',
    selected: Object.fromEntries(
        (variant.attribute_values ?? []).map((value) => [value.attribute_id, value.id])
    ),
});

const syncDrafts = (variants) => {
    variants.forEach((variant) => {
        if (!drafts[variant.id]) {
            drafts[variant.id] = toDraft(variant);
        }
    });
    Object.keys(drafts)
        .filter((id) => !variants.some((v) => String(v.id) === String(id)))
        .forEach((id) => delete drafts[id]);
};

watch(getVariants, (variants) => syncDrafts(variants ?? []), { immediate: true, deep: false });

onMounted(async () => {
    await fetchAttributes();
    if (props.productId) {
        await fetchVariants(props.productId);
    }
});

const selectionToIds = (selected) =>
    Object.values(selected ?? {}).filter((id) => id !== null && id !== '' && id !== undefined);

const payloadFrom = (draft) => ({
    ...draft,
    attribute_values: selectionToIds(draft.selected),
});

const combinationLabel = (draft) => {
    const ids = selectionToIds(draft.selected);
    if (!ids.length) return 'Bez rozlíšenia';

    return getVariantAttributes.value
        .flatMap((attribute) => attribute.values ?? [])
        .filter((value) => ids.includes(value.id))
        .map((value) => value.value)
        .join(' / ');
};

const activePrice = (draft) => {
    const sale = Number(draft.sale_price);
    return sale > 0 ? sale : Number(draft.price) || 0;
};

// Zľava v % je pohodlnejšia na zadanie, akciová cena je to, čo sa účtuje.
const applyDiscount = (draft) => {
    const price = Number(draft.price);
    const discount = Number(draft.discount);
    if (!price || !discount) return;
    draft.sale_price = formatDecimal(price - (price / 100) * discount);
};

const onSave = async (variantId) => {
    const draft = drafts[variantId];
    savingId.value = variantId;
    await updateVariant(props.productId, { ...payloadFrom(draft), id: variantId });
    savingId.value = null;
    delete drafts[variantId];
    syncDrafts(getVariants.value ?? []);
};

const onDestroy = async (variant) => {
    await destroyVariant(props.productId, variant);
};

const onAdd = async () => {
    savingId.value = 'new';
    const created = await storeVariant(props.productId, payloadFrom(newVariant.value));
    savingId.value = null;

    if (created) {
        newVariant.value = blankDraft();
        isAdding.value = false;
    }
};

const startAdding = () => {
    newVariant.value = blankDraft();
    // Prvý variant produktu musí byť predvolený, inak nemá karta čo ponúknuť.
    newVariant.value.is_default = !(getVariants.value ?? []).length;
    isAdding.value = true;
};

const hasAttributes = computed(() => getVariantAttributes.value.length > 0);
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                Varianty a ceny
            </h2>
            <button v-if="productId" type="button" @click="startAdding"
                class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700">
                Pridať variant
            </button>
        </div>

        <div v-if="!productId" class="px-6 py-5 text-sm text-gray-500">
            Varianty sa dajú pridať až po uložení produktu.
        </div>

        <div v-else class="px-6 py-5 space-y-4">
            <p v-if="!hasAttributes" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Zatiaľ nie je definovaná žiadna vlastnosť (Rozmer, Materiál…).
                <router-link :to="{ name: 'attributes.index' }" class="font-semibold underline">
                    Nastavte ich v správe vlastností
                </router-link>.
            </p>

            <p v-if="!getVariants.length && !isAdding" class="rounded-md border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500">
                Produkt zatiaľ nemá žiadny variant, takže sa nedá objednať.
            </p>

            <!-- Existujúce varianty -->
            <article v-for="variant in getVariants" :key="variant.id"
                class="rounded-md border border-gray-200 p-4"
                :class="variant.is_default ? 'border-blue-300 bg-blue-50/40' : 'bg-white'">
                <div v-if="drafts[variant.id]" class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="font-mono text-xs text-gray-500">{{ variant.code }}</span>
                            <p class="font-semibold text-gray-900">{{ combinationLabel(drafts[variant.id]) }}</p>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <span v-if="variant.is_default"
                                class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">
                                predvolený
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="variant.is_in_stock ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                {{ variant.is_in_stock ? 'skladom' : 'vypredané' }}
                            </span>
                        </div>
                    </div>

                    <!-- Kombinácia hodnôt -->
                    <div v-if="hasAttributes" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="attribute in getVariantAttributes" :key="attribute.id">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                {{ attribute.name }}
                            </label>
                            <select v-model="drafts[variant.id].selected[attribute.id]"
                                class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                                <option :value="undefined">—</option>
                                <option v-for="value in attribute.values" :key="value.id" :value="value.id">
                                    {{ value.value }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Cena €
                            </label>
                            <input type="number" step=".01" v-model.number="drafts[variant.id].price"
                                class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm font-semibold" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-orange-500">
                                Zľava %
                            </label>
                            <input type="number" v-model.number="drafts[variant.id].discount"
                                @change="applyDiscount(drafts[variant.id])"
                                class="w-full rounded-md border border-orange-200 px-2 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-green-600">
                                Akciová cena €
                            </label>
                            <input type="number" step=".01" v-model="drafts[variant.id].sale_price"
                                class="w-full rounded-md border border-green-200 px-2 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Sklad
                            </label>
                            <input type="number" v-model="drafts[variant.id].quantity" placeholder="nesleduje sa"
                                class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Min. odber
                            </label>
                            <input type="number" min="1" v-model.number="drafts[variant.id].min_order"
                                class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Hmotnosť kg
                            </label>
                            <input type="number" step=".01" v-model="drafts[variant.id].weight"
                                class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                                EAN
                            </label>
                            <input type="text" v-model="drafts[variant.id].ean"
                                class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                        </div>
                        <div class="flex items-end gap-4 pb-1.5">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" v-model="drafts[variant.id].published" class="accent-blue-600" />
                                Publikovaný
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" v-model="drafts[variant.id].is_default" class="accent-blue-600" />
                                Predvolený
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                        <span class="text-sm text-gray-500">
                            Predajná cena:
                            <strong class="text-gray-900">{{ formatDecimal(activePrice(drafts[variant.id])) }} €</strong>
                        </span>
                        <div class="flex gap-2">
                            <button type="button" @click="onDestroy(variant)"
                                :disabled="!variant.permissions?.delete?.allowed"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-red-300 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40"
                                :title="variant.permissions?.delete?.allowed ? 'Zmazať variant' : 'Variant je použitý v objednávke'">
                                Zmazať
                            </button>
                            <button type="button" @click="onSave(variant.id)" :disabled="savingId === variant.id"
                                class="rounded-md bg-gray-900 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-gray-700 disabled:opacity-50">
                                {{ savingId === variant.id ? 'Ukladám…' : 'Uložiť variant' }}
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Nový variant -->
            <article v-if="isAdding" class="rounded-md border-2 border-dashed border-blue-300 bg-blue-50/40 p-4 space-y-4">
                <h3 class="font-semibold text-gray-900">Nový variant</h3>

                <div v-if="hasAttributes" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="attribute in getVariantAttributes" :key="attribute.id">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ attribute.name }}
                        </label>
                        <select v-model="newVariant.selected[attribute.id]"
                            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                            <option :value="undefined">—</option>
                            <option v-for="value in attribute.values" :key="value.id" :value="value.id">
                                {{ value.value }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Cena €
                        </label>
                        <input type="number" step=".01" v-model.number="newVariant.price"
                            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm font-semibold" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Sklad
                        </label>
                        <input type="number" v-model="newVariant.quantity" placeholder="nesleduje sa"
                            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Min. odber
                        </label>
                        <input type="number" min="1" v-model.number="newVariant.min_order"
                            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            EAN
                        </label>
                        <input type="text" v-model="newVariant.ean"
                            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                    </div>
                </div>

                <p class="text-xs text-gray-500">
                    Kód variantu sa vygeneruje z kódu produktu a zvolenej kombinácie.
                </p>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="isAdding = false"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                        Zrušiť
                    </button>
                    <button type="button" @click="onAdd" :disabled="savingId === 'new'"
                        class="rounded-md bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50">
                        {{ savingId === 'new' ? 'Ukladám…' : 'Vytvoriť variant' }}
                    </button>
                </div>
            </article>
        </div>
    </section>
</template>

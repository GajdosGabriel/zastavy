<script setup>
import { computed, onMounted, ref } from "vue";
import { storeToRefs } from "pinia";
import { useStocks } from "../../store/StoreStocks";
import useErrors from "../../store/StoreErrors";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import ButtonLink from "../layout/page/ButtonLink.vue";
import SearchableSelect from "../forms/SearchableSelect.vue";
import router from "../../router";
import useUnsavedChanges from "../../models/useUnsavedChanges";

// Pinia store inštancia je reaktívna aj mutovateľná v scripte aj template (bez .value).
const store = useStocks();
const { storeStock, fetchVariants } = store;
const { getVariants } = storeToRefs(store);
const { getFieldErrors } = storeToRefs(useErrors());

const isSubmitting = ref(false);
// Odpis je príjem so záporným množstvom — rozbité, stratené, inventúrna korekcia.
const movement = ref("incoming");

const buttonBack = { name: "Späť", link: "stocks.index", icon: "arrow-left" };

const { markAsSaved } = useUnsavedChanges(() => store.create);

onMounted(() => {
    // Celý sklad, nie prvá stránka produktov — príjem musí vedieť nájsť každý variant.
    fetchVariants();
    store.create = { product_variant_id: null, quantity: "", price: "", note: "" };
});

const variantOptions = computed(() =>
    (getVariants.value ?? []).map((variant) => ({
        value: variant.id,
        label: `[${variant.code}] ${variant.label}`,
    }))
);

const selectedRow = computed(() =>
    (getVariants.value ?? []).find((row) => row.id === store.create.product_variant_id) ?? null
);

const isWriteoff = computed(() => movement.value === "writeoff");

const setMovement = (value) => {
    movement.value = value;
    // Odpis nemá nákupnú cenu — inak by ostala vo formulári skrytá a odoslala sa.
    if (isWriteoff.value) store.create.price = "";
};

const quantity = computed(() => Math.abs(Number(store.create.quantity) || 0));

const newBalance = computed(() => {
    if (!selectedRow.value) return null;
    return selectedRow.value.balance + (isWriteoff.value ? -quantity.value : quantity.value);
});

const canSubmit = computed(() =>
    !isSubmitting.value && !!store.create.product_variant_id && quantity.value > 0
);

const onSubmit = async () => {
    if (!canSubmit.value) return;

    isSubmitting.value = true;
    // Znamienko určuje typ pohybu, formulár drží množstvo vždy kladné.
    store.create.quantity = isWriteoff.value ? -quantity.value : quantity.value;

    const saved = await storeStock();
    isSubmitting.value = false;

    // Pri chybe validácie sa nesmie odnavigovať — chyba by zmizla so stránkou.
    if (!saved) {
        store.create.quantity = quantity.value;
        return;
    }

    markAsSaved();
    router.push({ name: "stocks.index" });
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: isWriteoff ? 'Odpis zo skladu' : 'Príjem tovaru', buttonLink: buttonBack }" />

                <div class="grid gap-6 lg:grid-cols-3">

                    <!-- Hlavný formulár -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Typ pohybu -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Typ pohybu</h2>
                            </div>
                            <div class="grid gap-3 p-5 sm:grid-cols-2">
                                <button
                                    type="button"
                                    class="rounded-lg border px-4 py-3 text-left transition"
                                    :class="!isWriteoff
                                        ? 'border-green-500 bg-green-50 ring-1 ring-green-500'
                                        : 'border-gray-200 hover:border-gray-300'"
                                    @click="setMovement('incoming')"
                                >
                                    <div class="text-sm font-semibold text-gray-900">Príjem</div>
                                    <div class="mt-0.5 text-xs text-gray-500">Naskladnenie od dodávateľa</div>
                                </button>

                                <button
                                    type="button"
                                    class="rounded-lg border px-4 py-3 text-left transition"
                                    :class="isWriteoff
                                        ? 'border-red-500 bg-red-50 ring-1 ring-red-500'
                                        : 'border-gray-200 hover:border-gray-300'"
                                    @click="setMovement('writeoff')"
                                >
                                    <div class="text-sm font-semibold text-gray-900">Odpis</div>
                                    <div class="mt-0.5 text-xs text-gray-500">Rozbité, stratené, inventúra</div>
                                </button>
                            </div>
                        </div>

                        <!-- Výber skladovej položky -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Tovar</h2>
                            </div>
                            <div class="p-5 space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Skladová položka <span class="text-red-500">*</span>
                                    </label>
                                    <SearchableSelect
                                        v-model="store.create.product_variant_id"
                                        :options="variantOptions"
                                        placeholder="— Vyberte skladovú položku —"
                                        search-placeholder="Hľadajte podľa kódu, názvu alebo prevedenia"
                                        empty-text="Žiadna položka nezodpovedá hľadaniu"
                                        field-key="product_variant_id"
                                        :error="getFieldErrors.product_variant_id?.[0] ?? ''"
                                    />
                                    <p v-if="!variantOptions.length" class="mt-1.5 text-xs text-amber-700">
                                        Žiadny produkt zatiaľ nemá variant — sklad sa nedá naskladniť.
                                    </p>
                                </div>

                                <div v-if="selectedRow" class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                                    <div class="font-semibold">{{ selectedRow.label }}</div>
                                    <div class="mt-0.5 text-xs text-blue-600">
                                        Kód: {{ selectedRow.code }} &nbsp;·&nbsp;
                                        Jednotka: {{ selectedRow.unit_value ?? '—' }} &nbsp;·&nbsp;
                                        Podľa pohybov: {{ selectedRow.balance }} &nbsp;·&nbsp;
                                        Evidované v e-shope: {{ selectedRow.tracked_quantity ?? 'nesleduje sa' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Množstvo + cena + poznámka -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                                <h2 class="text-base font-semibold text-gray-800">
                                    {{ isWriteoff ? 'Detaily odpisu' : 'Detaily príjmu' }}
                                </h2>
                            </div>
                            <div class="p-5 space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                                            Počet kusov <span class="text-red-500">*</span>
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model.number="store.create.quantity"
                                                type="number"
                                                min="1"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                placeholder="0"
                                            />
                                            <span v-if="selectedRow?.unit_value" class="shrink-0 text-sm text-gray-500">
                                                {{ selectedRow.unit_value }}
                                            </span>
                                        </div>
                                        <p v-if="getFieldErrors.quantity" class="mt-1 text-xs font-semibold text-red-600">
                                            {{ getFieldErrors.quantity[0] }}
                                        </p>
                                    </div>

                                    <div v-if="!isWriteoff">
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                                            Nákupná cena / ks (€)
                                        </label>
                                        <input
                                            v-model="store.create.price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            placeholder="0.00"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                                        {{ isWriteoff ? 'Dôvod odpisu' : 'Poznámka' }}
                                    </label>
                                    <input
                                        v-model="store.create.note"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        :placeholder="isWriteoff
                                            ? 'Poškodené pri preprave, inventúrny rozdiel, …'
                                            : 'Číslo dodacieho listu, dodávateľ, …'"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bočný panel: súhrn + akcie -->
                    <aside class="space-y-4">
                        <div class="sticky top-4 space-y-4">

                            <!-- Súhrn -->
                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-100 bg-gray-50 px-5 py-3">
                                    <h2 class="text-sm font-semibold text-gray-700">Súhrn</h2>
                                </div>
                                <div class="px-5 py-4 space-y-3 text-sm">
                                    <div class="flex justify-between text-gray-600">
                                        <span>Položka</span>
                                        <span class="max-w-[60%] truncate text-right font-semibold text-gray-900">
                                            {{ selectedRow?.label ?? "—" }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-gray-600">
                                        <span>Množstvo</span>
                                        <span class="font-semibold" :class="isWriteoff ? 'text-red-600' : 'text-green-700'">
                                            {{ isWriteoff ? '−' : '+' }}{{ quantity }}
                                            {{ selectedRow?.unit_value ?? '' }}
                                        </span>
                                    </div>
                                    <div v-if="!isWriteoff && store.create.price" class="flex justify-between text-gray-600">
                                        <span>Cena celkom</span>
                                        <span class="font-semibold text-gray-900">
                                            {{ (Number(store.create.price) * quantity).toFixed(2) }} €
                                        </span>
                                    </div>
                                    <div v-if="selectedRow" class="flex justify-between border-t pt-3 text-gray-600">
                                        <span>Stav po uložení</span>
                                        <span class="font-semibold" :class="newBalance < 0 ? 'text-red-600' : 'text-gray-900'">
                                            {{ newBalance }} {{ selectedRow.unit_value ?? '' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Akcie -->
                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                                <div class="px-5 py-4 space-y-2">
                                    <button
                                        type="button"
                                        @click="onSubmit"
                                        :disabled="!canSubmit"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg px-5 py-3 text-sm font-semibold text-white shadow transition disabled:cursor-not-allowed disabled:bg-gray-400"
                                        :class="isWriteoff ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                                    >
                                        <svg v-if="isSubmitting" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        {{ isSubmitting
                                            ? 'Ukladám...'
                                            : isWriteoff ? 'Odpísať zo skladu' : 'Prijať na sklad' }}
                                    </button>
                                    <ButtonLink :item="buttonBack" class="w-full justify-center" />
                                </div>
                            </div>

                        </div>
                    </aside>
                </div>
            </div>
        </template>
    </BaseLayout>
</template>

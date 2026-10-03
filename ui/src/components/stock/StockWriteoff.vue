<script setup>
import { formatPrice } from "../../models/functions";
import { computed, onMounted, reactive, ref } from "vue";
import { storeToRefs } from "pinia";
import { useStocks, emptyCreate, todayIso } from "../../store/StoreStocks";
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
const errorStore = useErrors();
const { getFieldErrors } = storeToRefs(errorStore);
const { clearFieldError, resetErrors } = errorStore;

const isSubmitting = ref(false);
// Odpis je príjem so záporným množstvom — rozbité, stratené, inventúrna korekcia.
const movement = ref("writeoff");

const buttonBack = { name: "Späť", link: "stocks.index", icon: "arrow-left" };

const { markAsSaved } = useUnsavedChanges(() => store.create);

// Zhodné s hranicami v StockController — server ich kontroluje tiež.
const LIMITS = { quantity: 100000, price: 99999.99, note: 255, supplier: 255, document_number: 64 };

const today = todayIso();

// Chyba sa ukáže až po opustení poľa alebo po pokuse o uloženie —
// prázdny formulár nemá hneď svietiť načerveno.
const submitted = ref(false);
const touched = reactive({});

onMounted(() => {
    // Celý sklad, nie prvá stránka produktov — príjem musí vedieť nájsť každý variant.
    fetchVariants();
    resetErrors();
    store.create = emptyCreate();
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
    if (value === "incoming") { router.push({ name: "stocks.create" }); return; }
    movement.value = value;
    // Odpis nemá nákupnú cenu ani dodávateľa — inak by ostali vo formulári skryté a odoslali sa.
    if (isWriteoff.value) {
        store.create.price = "";
        store.create.supplier = "";
    }
    clearFieldError("note");
};

const quantity = computed(() => Math.abs(Number(store.create.quantity) || 0));

const newBalance = computed(() => {
    if (!selectedRow.value) return null;
    return selectedRow.value.balance + (isWriteoff.value ? -quantity.value : quantity.value);
});

const hasValue = (value) => value !== "" && value !== null && value !== undefined;

const clientErrors = computed(() => {
    const form = store.create;
    const errors = {};

    if (!form.product_variant_id) errors.product_variant_id = "Vyberte skladovú položku.";

    if (!hasValue(form.quantity)) errors.quantity = "Zadajte počet kusov.";
    else if (!Number.isInteger(Number(form.quantity))) errors.quantity = "Počet kusov musí byť celé číslo.";
    else if (Number(form.quantity) <= 0) errors.quantity = "Počet kusov musí byť väčší ako nula.";
    else if (Number(form.quantity) > LIMITS.quantity) errors.quantity = `Počet kusov môže byť najviac ${LIMITS.quantity}.`;

    if (!isWriteoff.value && hasValue(form.price)) {
        const price = Number(form.price);
        if (Number.isNaN(price) || price < 0) errors.price = "Nákupná cena nesmie byť záporná.";
        else if (price > LIMITS.price) errors.price = `Nákupná cena môže byť najviac ${LIMITS.price} €.`;
        else if (!/^\d+(\.\d{1,2})?$/.test(String(form.price))) errors.price = "Nákupná cena môže mať najviac 2 desatinné miesta.";
    }

    if (isWriteoff.value && !form.note.trim()) errors.note = "Pri odpise uveďte dôvod.";

    if (!form.received_at) errors.received_at = "Zadajte dátum.";
    else if (form.received_at > today) errors.received_at = "Dátum nemôže byť v budúcnosti.";

    return errors;
});

// Chyba zo servera má prednosť — klientska kontrola ju len predbieha.
const fieldError = (key) =>
    getFieldErrors.value[key]?.[0]
    ?? ((submitted.value || touched[key]) ? clientErrors.value[key] : undefined)
    ?? "";

const inputClass = (key) => fieldError(key)
    ? "border-red-500 bg-red-50 ring-1 ring-red-500"
    : "border-gray-300 focus:border-blue-500 focus:ring-blue-500";

const onBlur = (key) => (touched[key] = true);

// Sklad smie ísť do mínusu (inventúrna korekcia), ale nemá sa to stať omylom.
const available = computed(() => selectedRow.value?.tracked_quantity ?? selectedRow.value?.balance ?? null);

const writeoffExceedsStock = computed(() =>
    isWriteoff.value && available.value !== null && quantity.value > available.value
);

const formatDate = (iso) => (iso ? iso.split("-").reverse().join(".") : "—");

const onSubmit = async () => {
    if (isSubmitting.value) return;

    submitted.value = true;
    if (Object.keys(clientErrors.value).length) return;

    isSubmitting.value = true;

    // Znamienko určuje typ pohybu, formulár drží množstvo vždy kladné.
    const saved = await storeStock({
        ...store.create,
        quantity: isWriteoff.value ? -quantity.value : quantity.value,
        note: store.create.note.trim(),
        supplier: store.create.supplier.trim(),
        document_number: store.create.document_number.trim(),
    });
    isSubmitting.value = false;

    // Pri chybe validácie sa nesmie odnavigovať — chyba by zmizla so stránkou.
    if (!saved) return;

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
                                        :error="fieldError('product_variant_id')"
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
                                        <label for="stock-quantity" class="mb-1.5 block text-sm font-semibold text-gray-700">
                                            Počet kusov <span class="text-red-500">*</span>
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <input
                                                id="stock-quantity"
                                                v-model.number="store.create.quantity"
                                                type="number"
                                                min="1"
                                                step="1"
                                                :max="LIMITS.quantity"
                                                inputmode="numeric"
                                                class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1"
                                                :class="inputClass('quantity')"
                                                placeholder="0"
                                                @input="clearFieldError('quantity')"
                                                @blur="onBlur('quantity')"
                                            />
                                            <span v-if="selectedRow?.unit_value" class="shrink-0 text-sm text-gray-500">
                                                {{ selectedRow.unit_value }}
                                            </span>
                                        </div>
                                        <p v-if="fieldError('quantity')" class="mt-1 text-xs font-semibold text-red-600">
                                            {{ fieldError('quantity') }}
                                        </p>
                                        <p v-else-if="writeoffExceedsStock" class="mt-1 text-xs font-semibold text-amber-700">
                                            Odpisujete viac, než je na sklade ({{ available }}) — stav pôjde do mínusu.
                                        </p>
                                    </div>

                                    <div v-if="!isWriteoff">
                                        <label for="stock-price" class="mb-1.5 block text-sm font-semibold text-gray-700">
                                            Nákupná cena / ks (€)
                                        </label>
                                        <input
                                            id="stock-price"
                                            v-model="store.create.price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :max="LIMITS.price"
                                            inputmode="decimal"
                                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1"
                                            :class="inputClass('price')"
                                            placeholder="0.00"
                                            @input="clearFieldError('price')"
                                            @blur="onBlur('price')"
                                        />
                                        <p v-if="fieldError('price')" class="mt-1 text-xs font-semibold text-red-600">
                                            {{ fieldError('price') }}
                                        </p>
                                        <p v-else class="mt-1 text-xs text-gray-400">
                                            Z ceny sa počíta hodnota skladu. Bez nej sa príjem do hodnoty nezaráta.
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="stock-received-at" class="mb-1.5 block text-sm font-semibold text-gray-700">
                                            {{ isWriteoff ? 'Dátum odpisu' : 'Dátum príjmu' }} <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            id="stock-received-at"
                                            v-model="store.create.received_at"
                                            type="date"
                                            :max="today"
                                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1"
                                            :class="inputClass('received_at')"
                                            @input="clearFieldError('received_at')"
                                            @blur="onBlur('received_at')"
                                        />
                                        <p v-if="fieldError('received_at')" class="mt-1 text-xs font-semibold text-red-600">
                                            {{ fieldError('received_at') }}
                                        </p>
                                    </div>

                                    <div>
                                        <label for="stock-document-number" class="mb-1.5 block text-sm font-semibold text-gray-700">
                                            {{ isWriteoff ? 'Číslo dokladu' : 'Číslo dodacieho listu / faktúry' }}
                                        </label>
                                        <input
                                            id="stock-document-number"
                                            v-model="store.create.document_number"
                                            type="text"
                                            :maxlength="LIMITS.document_number"
                                            class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1"
                                            :class="inputClass('document_number')"
                                            :placeholder="isWriteoff ? 'Inventúrny zápis, škodový protokol, …' : 'DL-2026/0153'"
                                            @input="clearFieldError('document_number')"
                                        />
                                        <p v-if="fieldError('document_number')" class="mt-1 text-xs font-semibold text-red-600">
                                            {{ fieldError('document_number') }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="!isWriteoff">
                                    <label for="stock-supplier" class="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Dodávateľ
                                    </label>
                                    <input
                                        id="stock-supplier"
                                        v-model="store.create.supplier"
                                        type="text"
                                        :maxlength="LIMITS.supplier"
                                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1"
                                        :class="inputClass('supplier')"
                                        placeholder="Názov firmy, od ktorej tovar prišiel"
                                        @input="clearFieldError('supplier')"
                                    />
                                    <p v-if="fieldError('supplier')" class="mt-1 text-xs font-semibold text-red-600">
                                        {{ fieldError('supplier') }}
                                    </p>
                                </div>

                                <div>
                                    <label for="stock-note" class="mb-1.5 block text-sm font-semibold text-gray-700">
                                        {{ isWriteoff ? 'Dôvod odpisu' : 'Poznámka' }}
                                        <span v-if="isWriteoff" class="text-red-500">*</span>
                                    </label>
                                    <input
                                        id="stock-note"
                                        v-model="store.create.note"
                                        type="text"
                                        :maxlength="LIMITS.note"
                                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-1"
                                        :class="inputClass('note')"
                                        :placeholder="isWriteoff
                                            ? 'Poškodené pri preprave, inventúrny rozdiel, …'
                                            : 'Čokoľvek, čo sa k príjmu oplatí zapamätať'"
                                        @input="clearFieldError('note')"
                                        @blur="onBlur('note')"
                                    />
                                    <p v-if="fieldError('note')" class="mt-1 text-xs font-semibold text-red-600">
                                        {{ fieldError('note') }}
                                    </p>
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
                                            {{ formatPrice(Number(store.create.price) * quantity) }} €
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-gray-600">
                                        <span>Dátum</span>
                                        <span class="font-semibold text-gray-900">{{ formatDate(store.create.received_at) }}</span>
                                    </div>
                                    <div v-if="store.create.document_number" class="flex justify-between gap-3 text-gray-600">
                                        <span>Doklad</span>
                                        <span class="truncate text-right font-semibold text-gray-900">{{ store.create.document_number }}</span>
                                    </div>
                                    <div v-if="!isWriteoff && store.create.supplier" class="flex justify-between gap-3 text-gray-600">
                                        <span>Dodávateľ</span>
                                        <span class="truncate text-right font-semibold text-gray-900">{{ store.create.supplier }}</span>
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
                                        :disabled="isSubmitting"
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

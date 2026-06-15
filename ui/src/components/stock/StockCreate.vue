<script setup>
import { computed, onMounted, ref } from "vue";
import useStocks from "../../store/StoreStocks";
import useProducts from "../../store/StoreProducts";
import useErrors from "../../store/StoreErrors";
import BaseLayout from "../layout/BaseLayout.vue";
import PageHeader from "../layout/page/pageHeader.vue";
import ButtonLink from "../layout/page/ButtonLink.vue";
import router from "../../router";

const { state, storeStock } = useStocks();
const { fetchProducts, getProducts } = useProducts();
const { getFieldErrors } = useErrors();

const isSubmitting = ref(false);
const productSearch = ref("");

const buttonBack = { name: "Späť", link: "stocks.index", icon: "arrow-left" };

onMounted(() => {
    fetchProducts();
    state.create = { product_id: null, quantity: "", price: "", note: "" };
});

const filteredProducts = computed(() => {
    const s = productSearch.value.trim().toLowerCase();
    const all = getProducts.value ?? [];
    if (!s) return all;
    return all.filter(p =>
        [p.code, p.name].filter(Boolean).some(v => String(v).toLowerCase().includes(s))
    );
});

const selectedProduct = computed(() =>
    (getProducts.value ?? []).find(p => p.id === state.create.product_id) ?? null
);

const onSubmit = async () => {
    if (!state.create.product_id || !state.create.quantity) return;
    isSubmitting.value = true;
    await storeStock();
    isSubmitting.value = false;
    router.push({ name: "stocks.index" });
};
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: 'Príjem tovaru', buttonLink: buttonBack }" />

                <div class="grid gap-6 lg:grid-cols-3">

                    <!-- Hlavný formulár -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- Výber produktu -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Tovar</h2>
                            </div>
                            <div class="p-5 space-y-4">
                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Filtrovať produkt
                                    </label>
                                    <input
                                        v-model="productSearch"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        placeholder="Hľadajte podľa kódu alebo názvu"
                                    />
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                                        Produkt <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        v-model="state.create.product_id"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    >
                                        <option :value="null">— Vyberte produkt —</option>
                                        <option v-for="p in filteredProducts" :key="p.id" :value="p.id">
                                            {{ p.code ? `[${p.code}] ` : '' }}{{ p.name }}
                                        </option>
                                    </select>
                                </div>

                                <div v-if="selectedProduct" class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                                    <div class="font-semibold">{{ selectedProduct.name }}</div>
                                    <div class="mt-0.5 text-xs text-blue-600">
                                        Kód: {{ selectedProduct.code ?? '—' }} &nbsp;·&nbsp;
                                        Jednotka: {{ selectedProduct.unit_value ?? '—' }} &nbsp;·&nbsp;
                                        Min. odber: {{ selectedProduct.min_order ?? 1 }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Množstvo + cena + poznámka -->
                        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-100 bg-gray-50 px-5 py-4">
                                <h2 class="text-base font-semibold text-gray-800">Detaily príjmu</h2>
                            </div>
                            <div class="p-5 space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                                            Počet kusov <span class="text-red-500">*</span>
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model.number="state.create.quantity"
                                                type="number"
                                                min="1"
                                                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                placeholder="0"
                                            />
                                            <span v-if="selectedProduct?.unit_value" class="shrink-0 text-sm text-gray-500">
                                                {{ selectedProduct.unit_value }}
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="mb-1.5 block text-sm font-semibold text-gray-700">
                                            Nákupná cena / ks (€)
                                        </label>
                                        <input
                                            v-model="state.create.price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            placeholder="0.00"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-sm font-semibold text-gray-700">Poznámka</label>
                                    <input
                                        v-model="state.create.note"
                                        type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        placeholder="Číslo dodacieho listu, dodávateľ, …"
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
                                        <span>Produkt</span>
                                        <span class="font-semibold text-gray-900 text-right max-w-[60%] truncate">
                                            {{ selectedProduct?.name ?? '—' }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-gray-600">
                                        <span>Množstvo</span>
                                        <span class="font-semibold text-gray-900">
                                            {{ state.create.quantity || 0 }}
                                            {{ selectedProduct?.unit_value ?? '' }}
                                        </span>
                                    </div>
                                    <div v-if="state.create.price" class="flex justify-between text-gray-600">
                                        <span>Cena celkom</span>
                                        <span class="font-semibold text-gray-900">
                                            {{ (Number(state.create.price) * Number(state.create.quantity || 0)).toFixed(2) }} €
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
                                        :disabled="isSubmitting || !state.create.product_id || !state.create.quantity"
                                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-5 py-3 text-sm font-semibold text-white shadow transition hover:bg-green-700 disabled:cursor-not-allowed disabled:bg-gray-400"
                                    >
                                        <svg v-if="isSubmitting" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        {{ isSubmitting ? 'Ukladám...' : 'Prijať na sklad' }}
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

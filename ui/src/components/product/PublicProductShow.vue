<script setup>
import { computed, onUnmounted, ref, watch } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import { useProducts } from "../../store/StoreProducts";
import { useImages } from "../../store/StoreImages";
import useCheckouts from "../../store/StoreCheckouts";
import { useRoute } from "vue-router";
import { storeToRefs } from "pinia";
import kosik from "../checkout/kosikLink.vue";
import kosikButton from "../icons/kosik.vue";
import { formatDecimal, formatPriceWithoutVat } from "../../models/functions";
import RequiredMark from "../forms/RequiredMark.vue";
import VariantPicker from "./components/VariantPicker.vue";
import { applySeo, setJsonLd, productJsonLd, breadcrumbJsonLd, organizationJsonLd } from "../../models/seo";

// store.product je reaktívny aj mutovateľný (Pinia proxy); getProduct getter cez storeToRefs.
const productStore = useProducts();
const { getProduct } = storeToRefs(productStore);
const { fetchProduct, resetProduct } = productStore;
const { getImages } = storeToRefs(useImages());
const { addVariantToCart } = useCheckouts();
const route = useRoute();
const messages = ref([]);
const currentImage = ref(0);

const selectedVariant = ref(null);
const quantity = ref(1);

const productLoaded = computed(() => String(getProduct.value.id) === String(route.params.productId));
const selectedImage = computed(() => getImages.value?.[currentImage.value]?.path ?? getProduct.value.thumb);

const minOrder = computed(() => Number(selectedVariant.value?.min_order ?? 1));
const activePrice = computed(() => Number(selectedVariant.value?.active_price ?? 0));
const basePrice = computed(() => Number(selectedVariant.value?.price ?? 0));
const hasDiscount = computed(() => Number(selectedVariant.value?.sale_price ?? 0) > 0);
const canBuy = computed(() => !!selectedVariant.value && selectedVariant.value.is_in_stock);

const orderTotal = computed(() => formatDecimal(quantity.value * activePrice.value));
const minOrderTotal = computed(() => formatDecimal(minOrder.value * activePrice.value));

const loadProduct = async (productId) => {
    currentImage.value = 0;
    messages.value = [];
    selectedVariant.value = null;
    await fetchProduct(productId);
};

const submitCart = () => {
    if (!selectedVariant.value) return;
    addVariantToCart(getProduct.value, selectedVariant.value, quantity.value);
    messages.value.push(quantity.value);
};

const onClickImage = (index) => {
    currentImage.value = index;
};

// Zmena variantu prepíše množstvo na jeho minimálny odber — každý variant
// môže mať iný (napr. veľké zástavy sa predávajú po jednej).
watch(selectedVariant, (variant) => {
    quantity.value = Number(variant?.min_order ?? 1);
});

// Produkt sa načítava asynchrónne, takže <head> sa dopĺňa až keď sú dáta k dispozícii.
// Popis staviame z variantov — ľudia hľadajú konkrétny rozmer („vlajka Slovenska 150x100“).
watch(
    () => productLoaded.value && getProduct.value.name,
    (name) => {
        if (!name) return;

        const product = getProduct.value;
        const path = route.fullPath.split("?")[0];
        const variantNames = (product.variants ?? [])
            .filter((variant) => variant.published && variant.name)
            .map((variant) => variant.name);

        const priceLabel = Number(product.price_from) > 0
            ? `${(product.variants ?? []).length > 1 ? "od " : ""}${formatDecimal(product.price_from)} € s DPH`
            : "";

        const description = [
            product.description,
            variantNames.length ? `Prevedenia: ${variantNames.slice(0, 6).join(", ")}.` : "",
            priceLabel ? `Cena ${priceLabel}.` : "",
            product.is_in_stock ? "Skladom, expedujeme ihneď." : "",
        ]
            .filter(Boolean)
            .join(" ");

        applySeo({
            title: variantNames.length ? `${name} — ${variantNames[0]}` : name,
            description,
            image: product.images?.[0]?.path ?? product.thumb,
            path,
            type: "product",
        });

        // Organizácia musí byť na stránke prítomná, inak offers.seller odkazuje do prázdna.
        setJsonLd("organization", organizationJsonLd());
        setJsonLd("product", productJsonLd(product, path));
        setJsonLd(
            "breadcrumb",
            breadcrumbJsonLd([
                { name: "Domov", path: "/" },
                { name, path },
            ])
        );
    },
    { immediate: true }
);

watch(
    () => route.params.productId,
    (productId) => {
        if (productId) {
            loadProduct(productId);
        }
    },
    { immediate: true }
);

onUnmounted(() => {
    resetProduct();
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="col-span-12 px-3 pb-8 md:px-7">
                <nav class="mb-4 text-sm text-gray-500">
                    <router-link :to="{ name: 'public.index' }" class="hover:text-blue-700">
                        Produkty
                    </router-link>
                    <span class="mx-2">/</span>
                    <span class="text-gray-700">{{ productLoaded ? getProduct.name : '' }}</span>
                </nav>

                <section v-if="productLoaded" class="grid gap-6 lg:grid-cols-12">
                    <div class="lg:col-span-7">
                        <div class="overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                            <div class="flex min-h-96 items-center justify-center bg-gray-50 p-4 md:p-8">
                                <img :src="selectedImage" :alt="getProduct.name"
                                    class="max-h-[520px] w-full object-contain" />
                            </div>
                        </div>

                        <div v-if="getImages.length > 1" class="mt-4 flex flex-wrap gap-3">
                            <button v-for="(image, index) in getImages" :key="image.id" type="button"
                                @click="onClickImage(index)"
                                class="h-20 w-20 overflow-hidden rounded border bg-white p-1 shadow-sm hover:border-blue-500"
                                :class="currentImage === index ? 'border-blue-600 ring-2 ring-blue-200' : 'border-gray-200'">
                                <img :src="image.path" :alt="getProduct.name" class="h-full w-full object-contain" />
                            </button>
                        </div>

                        <section class="mt-6 rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                            <h2 class="mb-3 text-xl font-semibold text-gray-900">Popis tovaru</h2>
                            <p class="leading-7 text-gray-600">
                                {{ getProduct.description || 'Popis produktu pripravujeme.' }}
                            </p>
                        </section>

                        <!-- Prehľad všetkých variantov: parametre aj skladová dostupnosť -->
                        <section v-if="(getProduct.variants ?? []).length > 1"
                            class="mt-6 overflow-hidden rounded-md border border-gray-200 bg-white shadow-sm">
                            <h2 class="border-b border-gray-100 px-5 py-3 text-xl font-semibold text-gray-900">
                                Prehľad prevedení
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="px-5 py-2 text-left">Prevedenie</th>
                                            <th class="px-5 py-2 text-left">Kód</th>
                                            <th class="px-5 py-2 text-right">Cena s DPH</th>
                                            <th class="px-5 py-2 text-right">Dostupnosť</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="variant in getProduct.variants" :key="variant.id"
                                            :class="selectedVariant?.id === variant.id ? 'bg-blue-50/60' : ''">
                                            <td class="px-5 py-2 font-medium text-gray-900">{{ variant.name || '—' }}</td>
                                            <td class="px-5 py-2 font-mono text-xs text-gray-500">{{ variant.code }}</td>
                                            <td class="px-5 py-2 text-right font-semibold text-gray-900">
                                                {{ formatDecimal(variant.active_price) }} €
                                            </td>
                                            <td class="px-5 py-2 text-right">
                                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                                    :class="variant.is_in_stock ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                                    {{ variant.is_in_stock ? 'skladom' : 'vypredané' }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <aside class="lg:col-span-5">
                        <div class="sticky top-4 space-y-5">
                            <section class="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                                <div class="mb-4 border-b border-gray-200 pb-4">
                                    <p class="mb-2 text-sm font-semibold uppercase text-blue-800">
                                        Zástavy a vlajky
                                    </p>
                                    <h1 class="text-3xl font-semibold leading-tight text-gray-900">
                                        {{ getProduct.name }}
                                    </h1>
                                </div>

                                <!-- Výber prevedenia -->
                                <div v-if="(getProduct.variants ?? []).length" class="mb-4 border-b border-gray-200 pb-4">
                                    <VariantPicker :variants="getProduct.variants" v-model="selectedVariant" />
                                </div>

                                <div v-if="selectedVariant" class="space-y-2 border-b border-gray-200 pb-4">
                                    <div class="flex items-end justify-between gap-4">
                                        <span class="text-sm text-gray-500">Cena s DPH</span>
                                        <div class="text-right">
                                            <span v-if="hasDiscount" class="mr-2 text-sm text-gray-400 line-through">
                                                {{ formatDecimal(basePrice) }} €
                                            </span>
                                            <span class="text-3xl font-bold text-red-600">
                                                {{ formatDecimal(activePrice) }} €
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-sm text-gray-500">
                                        <span>Cena bez DPH ({{ getProduct.vat }}%)</span>
                                        <span>{{ formatPriceWithoutVat(activePrice, getProduct.vat) }} €</span>
                                    </div>
                                </div>

                                <p v-else class="rounded bg-gray-50 p-3 text-sm text-gray-500">
                                    Tento produkt momentálne nemá dostupné prevedenie.
                                </p>

                                <form v-if="selectedVariant" class="mt-5" @submit.prevent="submitCart">
                                    <label class="mb-2 block text-sm font-semibold text-gray-700" for="input_order">
                                        Množstvo <RequiredMark />
                                    </label>
                                    <div class="flex gap-3">
                                        <input id="input_order" type="number" v-model.number="quantity"
                                            class="w-28 rounded border-gray-300 text-center"
                                            :min="minOrder" required />
                                        <button :disabled="!canBuy"
                                            class="flex flex-1 items-center justify-center rounded bg-blue-700 px-4 py-2 font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-gray-400">
                                            <kosikButton />
                                            <span class="ml-2">{{ canBuy ? 'Kúpiť' : 'Vypredané' }}</span>
                                        </button>
                                    </div>
                                </form>

                                <div v-if="selectedVariant" class="mt-4 rounded bg-blue-50 p-3 text-sm text-blue-900">
                                    <div class="flex justify-between">
                                        <span>Min. odber</span>
                                        <strong>{{ minOrder }} {{ getProduct.unit_value }}</strong>
                                    </div>
                                    <div class="mt-1 flex justify-between">
                                        <span>Spolu za výber</span>
                                        <strong>{{ orderTotal }} € s DPH</strong>
                                    </div>
                                    <div class="mt-1 flex justify-between text-blue-700">
                                        <span>Minimálna objednávka</span>
                                        <span>{{ minOrderTotal }} €</span>
                                    </div>
                                </div>

                                <router-link :to="{ name: 'public.cart.index' }">
                                    <transition-group enter-active-class="duration-300 ease-out"
                                        enter-from-class="transform opacity-0 scale-75"
                                        enter-to-class="opacity-100 scale-100"
                                        leave-active-class="transform duration-200 ease-in"
                                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-75">
                                        <p v-for="(message, index) in messages" :key="index"
                                            class="mt-4 rounded border border-green-500 bg-green-100 p-3 text-sm font-semibold text-green-800 shadow-sm">
                                            Do košíka ste vložili {{ message }} {{ getProduct.unit_value }}.
                                        </p>
                                    </transition-group>
                                </router-link>
                            </section>

                            <section class="grid gap-3 rounded-md border border-gray-200 bg-white p-5 text-sm text-gray-700 shadow-sm sm:grid-cols-3 lg:grid-cols-1">
                                <div>
                                    <div class="font-semibold text-gray-900">Overený predajca</div>
                                    <div class="text-gray-500">Špecializácia na vlajky a symboly.</div>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Faktúra k objednávke</div>
                                    <div class="text-gray-500">Pre firmy, obce aj organizácie.</div>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Rýchly nákup</div>
                                    <div class="text-gray-500">Košík si pamätá zvolené prevedenie.</div>
                                </div>
                            </section>

                            <section class="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                                <kosik />
                            </section>

                        </div>
                    </aside>
                </section>

                <section v-else class="grid gap-6 lg:grid-cols-12">
                    <div class="lg:col-span-7">
                        <div class="min-h-96 rounded-md border border-gray-200 bg-gray-50 shadow-sm"></div>
                    </div>
                    <aside class="lg:col-span-5">
                        <div class="rounded-md border border-gray-200 bg-white p-5 shadow-sm">
                            <div class="h-6 w-32 rounded bg-gray-100"></div>
                            <div class="mt-4 h-10 w-3/4 rounded bg-gray-100"></div>
                            <div class="mt-6 h-20 rounded bg-gray-100"></div>
                        </div>
                    </aside>
                </section>
            </div>
        </template>
    </BaseLayout>
</template>

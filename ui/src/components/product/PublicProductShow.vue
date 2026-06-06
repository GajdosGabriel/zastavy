<script setup>
import { computed, onUnmounted, ref, watch } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useProducts from "../../store/StoreProducts";
import useImages from "../../store/StoreImages";
import useCheckouts from "../../store/StoreCheckouts";
import { useRoute } from "vue-router";
import kosik from "../checkout/kosikLink.vue";
import kosikButton from "../icons/kosik.vue";
import { formatDecimal, formatPriceWithoutVat } from "../../models/functions";
import RequiredMark from "../forms/RequiredMark.vue";

const { state, getProduct, fetchProduct, resetProduct } = useProducts();
const { getImages } = useImages();
const { submitCartToIndex } = useCheckouts();
const route = useRoute();
const messages = ref([]);
const currentImage = ref(0);

const productLoaded = computed(() => String(getProduct.value.id) === String(route.params.productId));
const selectedImage = computed(() => getImages.value?.[currentImage.value]?.path ?? getProduct.value.thumb);
const activePrice = computed(() => Number(getProduct.value.active_price ?? 0));
const inputOrder = computed(() => Number(state.product.input_order ?? getProduct.value.min_order ?? 1));
const orderTotal = computed(() => formatDecimal(inputOrder.value * activePrice.value));
const minOrderTotal = computed(() => formatDecimal(Number(getProduct.value.min_order ?? 1) * activePrice.value));
const hasDiscount = computed(() => Number(getProduct.value.sale_price ?? 0) > 0);

const loadProduct = async (productId) => {
    currentImage.value = 0;
    messages.value = [];
    await fetchProduct(productId);
};

const submitCart = () => {
    submitCartToIndex(state.product);
    messages.value.push(state.product.input_order);
};

const onClickImage = (index) => {
    currentImage.value = index;
};

watch(
    () => getProduct.value.name,
    (name) => {
        if (name) {
            document.title = name;
        }
    }
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

                                <div class="space-y-2 border-b border-gray-200 pb-4">
                                    <div class="flex items-end justify-between gap-4">
                                        <span class="text-sm text-gray-500">Cena s DPH</span>
                                        <div class="text-right">
                                            <span v-if="hasDiscount" class="mr-2 text-sm text-gray-400 line-through">
                                                {{ getProduct.price }} €
                                            </span>
                                            <span class="text-3xl font-bold text-red-600">
                                                {{ getProduct.active_price }} €
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-sm text-gray-500">
                                        <span>Cena bez DPH ({{ getProduct.vat }}%)</span>
                                        <span>{{ formatPriceWithoutVat(getProduct.active_price, getProduct.vat) }} €</span>
                                    </div>
                                </div>

                                <form class="mt-5" @submit.prevent="submitCart">
                                    <label class="mb-2 block text-sm font-semibold text-gray-700" for="input_order">
                                        Množstvo <RequiredMark />
                                    </label>
                                    <div class="flex gap-3">
                                        <input id="input_order" type="number" v-model="state.product.input_order"
                                            class="w-28 rounded border-gray-300 text-center"
                                            :min="getProduct.min_order" required />
                                        <button
                                            class="flex flex-1 items-center justify-center rounded bg-blue-700 px-4 py-2 font-semibold text-white hover:bg-blue-800">
                                            <kosikButton />
                                            <span class="ml-2">Kúpiť</span>
                                        </button>
                                    </div>
                                </form>

                                <div class="mt-4 rounded bg-blue-50 p-3 text-sm text-blue-900">
                                    <div class="flex justify-between">
                                        <span>Min. odber</span>
                                        <strong>{{ getProduct.min_order }} {{ getProduct.unit_value }}</strong>
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
                                    <div class="text-gray-500">Košík si pamätá zvolený počet kusov.</div>
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

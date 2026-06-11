<script setup>
import BaseLayout from '../layout/BaseLayout.vue';
import { ref, watch, onMounted, onUnmounted, computed } from "@vue/runtime-core";
import useProducts from "../../store/StoreProducts";
import useImages from "../../store/StoreImages";
import UseCategories from "../../store/StoreCategories";
import { useRoute } from "vue-router";
import router from "../../router";
import { formatDecimal } from "../../models/functions";
import PageHeader from '../layout/page/pageHeader.vue';
import buttonSubmitComponent from '../layout/page/ButtonSubmit.vue';
import buttonRouterLink from '../layout/page/ButtonLink.vue';
import useUnsavedChanges from '../../models/useUnsavedChanges';
import RequiredMark from '../forms/RequiredMark.vue';

const { state, getProduct, updateProduct, storeProduct, fetchProduct, setProduct } = useProducts();
const { destroyImage, storeImages, reorderImages } = useImages();

const dragIndex = ref(null);

const onDragStart = (index) => { dragIndex.value = index; };
const onDragOver = (e) => { e.preventDefault(); };
const onDrop = async (targetIndex) => {
    if (dragIndex.value === null || dragIndex.value === targetIndex) return;
    const imgs = [...state.product.images];
    const [moved] = imgs.splice(dragIndex.value, 1);
    imgs.splice(targetIndex, 0, moved);
    state.product.images = imgs;
    dragIndex.value = null;
    await reorderImages(state.product.id, imgs.map(i => i.id));
};

const { categories, fetchCategories } = UseCategories();
const productId = computed(() => useRoute().params.productId);
const { setOriginalData, markAsSaved, isFormChanged } = useUnsavedChanges(() => getProduct.value);

onMounted(async () => {
    if (productId.value) {
        await fetchProduct(productId.value);
        setOriginalData(getProduct.value);
    }
    fetchCategories();
});

let selectedImageFiles = ref([]);
let imageUrls = ref([]);

const handleImageSelected = (event) => {
    if (event.target.files.length === 0) {
        selectedImageFiles.value = [];
        imageUrls.value = [];
        return;
    }
    selectedImageFiles.value = Array.from(event.target.files);
};

const onSubmitForm = async () => {
    if (state.product.price < state.product.sale_price) {
        alert('Cena po zľave nemôže byť vyššia ako je základná cena.');
        return;
    }

    if (productId.value) {
        await updateProduct();

        if (selectedImageFiles.value.length) {
            const product = await storeImages(productId.value, selectedImageFiles.value);
            if (product) {
                state.product.images = product.images;
                selectedImageFiles.value = [];
                imageUrls.value = [];
            }
        }

        await fetchProduct(productId.value);
        setOriginalData(getProduct.value);
        markAsSaved();
        return;
    } else {
        const product = await storeProduct();
        if (product?.id && selectedImageFiles.value.length) {
            await storeImages(product.id, selectedImageFiles.value);
        }
    }

    router.push({ name: "products.index" });
    markAsSaved();
};

watch(selectedImageFiles, (files) => {
    imageUrls.value = [];
    files.forEach((file) => {
        let fileReader = new FileReader();
        fileReader.readAsDataURL(file);
        fileReader.addEventListener("load", () => {
            imageUrls.value.push(fileReader.result);
        });
    });
});

const onClickImageRemove = async (imageId) => {
    const wasDeleted = await destroyImage(state.product.id, imageId);
    if (wasDeleted) {
        state.product.images = state.product.images.filter((image) => image.id !== imageId);
    }
};

onUnmounted(() => setProduct({}));

const pageTitle = computed(() => productId.value ? 'Upraviť produkt' : 'Nový produkt');
const buttonSubmit = { name: 'Uložiť', spinner: true };
const buttonBack = { name: 'Späť', spinner: true, link: '/products', icon: 'arrow-left' };

const calculatedSalePrice = computed(() => {
    const price = Number(state.product.price);
    const discount = Number(state.product.discount);
    if (!price || !discount) return null;
    return formatDecimal(price - (price / 100) * discount);
});
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: pageTitle, buttonLink: buttonBack }" />

                <form @submit.prevent="onSubmitForm" enctype="multipart/form-data" class="space-y-5 mb-6">

                    <!-- Základné info -->
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-3">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Základné informácie</h2>
                        </div>
                        <div class="space-y-4 px-6 py-5">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="name">
                                        Názov <RequiredMark />
                                    </label>
                                    <input id="name" type="text" v-model="getProduct.name"
                                        placeholder="Názov produktu" required
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="code">
                                        Kód tovaru <RequiredMark />
                                    </label>
                                    <input id="code" type="text" v-model.trim="getProduct.code"
                                        placeholder="napr. TOV-000001" required
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700" for="description">
                                    Popis
                                </label>
                                <textarea id="description" v-model="getProduct.description"
                                    placeholder="Popis produktu" rows="4"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                            </div>
                        </div>
                    </section>

                    <!-- Ceny -->
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-3">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Ceny a zľavy</h2>
                        </div>
                        <div class="px-6 py-5">
                            <div class="grid gap-4 sm:grid-cols-3">

                                <!-- Základná cena -->
                                <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500" for="price">
                                        Základná cena <RequiredMark />
                                    </label>
                                    <div class="relative">
                                        <input id="price" type="number" v-model.number="getProduct.price"
                                            placeholder="0.00" step=".01" required
                                            class="w-full rounded-md border border-gray-300 py-2 pl-3 pr-8 text-lg font-semibold text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                                        <span class="absolute right-3 top-2.5 text-sm text-gray-400">€</span>
                                    </div>
                                </div>

                                <!-- Zľava -->
                                <div class="rounded-md border border-orange-100 bg-orange-50 p-4">
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-orange-500" for="discount">
                                        Zľava
                                    </label>
                                    <div class="relative">
                                        <input id="discount" type="number" v-model.number="getProduct.discount"
                                            placeholder="0"
                                            class="w-full rounded-md border border-orange-200 py-2 pl-3 pr-8 text-lg font-semibold text-orange-700 shadow-sm focus:border-orange-400 focus:outline-none focus:ring-1 focus:ring-orange-400" />
                                        <span class="absolute right-3 top-2.5 text-sm text-orange-400">%</span>
                                    </div>
                                    <p v-if="calculatedSalePrice" class="mt-2 text-xs text-orange-600">
                                        Vypočítaná cena: <strong>{{ calculatedSalePrice }} €</strong>
                                    </p>
                                </div>

                                <!-- Cena po zľave -->
                                <div class="rounded-md border p-4"
                                    :class="getProduct.sale_price ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50'">
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide"
                                        :class="getProduct.sale_price ? 'text-green-600' : 'text-gray-500'"
                                        for="sale_price">
                                        Cena po zľave
                                    </label>
                                    <div class="relative">
                                        <input id="sale_price" type="number" v-model.number="getProduct.sale_price"
                                            placeholder="0.00" step=".01"
                                            class="w-full rounded-md border py-2 pl-3 pr-8 text-lg font-semibold shadow-sm focus:outline-none focus:ring-1"
                                            :class="getProduct.sale_price
                                                ? 'border-green-300 text-green-700 focus:border-green-500 focus:ring-green-400'
                                                : 'border-gray-300 text-gray-900 focus:border-blue-500 focus:ring-blue-500'" />
                                        <span class="absolute right-3 top-2.5 text-sm"
                                            :class="getProduct.sale_price ? 'text-green-400' : 'text-gray-400'">€</span>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-400">Ručné zadanie prepíše výpočet</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Odber a jednotky -->
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-3">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Odber a jednotky</h2>
                        </div>
                        <div class="px-6 py-5">
                            <div class="grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="min_order">
                                        Min. odber <RequiredMark />
                                    </label>
                                    <input id="min_order" type="number" v-model.number="getProduct.min_order"
                                        placeholder="1" required
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="unit_value">
                                        Jednotka <RequiredMark />
                                    </label>
                                    <select id="unit_value" v-model="getProduct.unit_value" required
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option value="ks">kusy</option>
                                        <option value="l">litre</option>
                                        <option value="kg">hmotnosť</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700" for="vat">
                                        Sadzba DPH <RequiredMark />
                                    </label>
                                    <select id="vat" v-model="getProduct.vat" required
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                        <option :value="23">23 %</option>
                                        <option :value="10">10 %</option>
                                        <option :value="0">0 %</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Kategórie -->
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-3">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Kategória</h2>
                        </div>
                        <div class="flex flex-wrap gap-2 px-6 py-5">
                            <label v-for="category in categories" :key="category.id"
                                class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm transition"
                                :class="getProduct.categories?.includes(category.id)
                                    ? 'border-blue-500 bg-blue-50 text-blue-700 font-medium'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'">
                                <input type="checkbox" :id="category.name"
                                    v-model="getProduct.categories" :value="category.id"
                                    class="accent-blue-600" />
                                {{ category.name }}
                            </label>
                        </div>
                    </section>

                    <!-- Obrázky -->
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-3">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Obrázky</h2>
                        </div>
                        <div class="px-6 py-5 space-y-4">

                            <!-- Upload -->
                            <div>
                                <label for="myfile"
                                    class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-2 text-sm text-gray-600 hover:border-blue-400 hover:bg-blue-50 hover:text-blue-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Pridať obrázky
                                </label>
                                <input type="file" id="myfile" accept="image/*" @change="handleImageSelected" multiple class="hidden" />
                            </div>

                            <!-- Náhľad nových -->
                            <div v-if="imageUrls.length" class="flex flex-wrap gap-3">
                                <div v-for="imageUrl in imageUrls" :key="imageUrl"
                                    class="rounded-md border-2 border-blue-300 bg-blue-50 p-1">
                                    <img :src="imageUrl" alt="" class="h-24 w-24 rounded object-contain" />
                                    <p class="mt-1 text-center text-xs text-blue-500">Nový</p>
                                </div>
                            </div>

                            <!-- Existujúce s drag&drop -->
                            <div v-if="state.product.images?.length">
                                <p class="mb-2 text-xs text-gray-400">Potiahnite obrázky pre zmenu poradia</p>
                                <div class="flex flex-wrap gap-3">
                                    <div
                                        v-for="(image, index) in state.product.images"
                                        :key="image.id"
                                        draggable="true"
                                        @dragstart="onDragStart(index)"
                                        @dragover="onDragOver"
                                        @drop="onDrop(index)"
                                        :class="[
                                            'group relative rounded-md border-2 p-1 cursor-grab select-none transition',
                                            dragIndex === index
                                                ? 'opacity-40 border-blue-400'
                                                : 'border-gray-200 hover:border-gray-400'
                                        ]"
                                    >
                                        <span class="absolute left-1.5 top-1.5 rounded bg-gray-700/60 px-1 text-xs text-white">{{ index + 1 }}</span>
                                        <img :src="image.path" alt="" class="h-24 w-24 rounded object-contain pointer-events-none" />
                                        <button type="button" @click="onClickImageRemove(image.id)"
                                            class="mt-1 w-full rounded text-center text-xs text-gray-400 hover:bg-red-50 hover:text-red-600 py-0.5 transition">
                                            Odstrániť
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Akcie -->
                    <div class="flex justify-between">
                        <buttonRouterLink :item="buttonBack" />
                        <buttonSubmitComponent :item="buttonSubmit" />
                    </div>

                </form>
            </div>
        </template>
    </BaseLayout>
</template>

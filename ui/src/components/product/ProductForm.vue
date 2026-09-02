<script setup>
import BaseLayout from '../layout/BaseLayout.vue';
import { ref, watch, onMounted, onUnmounted, computed } from "@vue/runtime-core";
import { useProducts } from "../../store/StoreProducts";
import { useImages } from "../../store/StoreImages";
import { useCategories } from "../../store/StoreCategories";
import { storeToRefs } from "pinia";
import { useRoute } from "vue-router";
import router from "../../router";
import PageHeader from '../layout/page/pageHeader.vue';
import VariantEditor from './components/VariantEditor.vue';
import buttonSubmitComponent from '../layout/page/ButtonSubmit.vue';
import buttonRouterLink from '../layout/page/ButtonLink.vue';
import useUnsavedChanges from '../../models/useUnsavedChanges';
import RequiredMark from '../forms/RequiredMark.vue';
import HtmlEditor from '../forms/HtmlEditor.vue';

// store.product je reaktívny aj mutovateľný (Pinia proxy); getProduct getter cez storeToRefs.
const productStore = useProducts();
const { getProduct } = storeToRefs(productStore);
const { updateProduct, storeProduct, fetchProduct, setProduct } = productStore;
const { destroyImage, storeImages, reorderImages } = useImages();

const dragIndex = ref(null);

const onDragStart = (index) => { dragIndex.value = index; };
const onDragOver = (e) => { e.preventDefault(); };
const onDrop = async (targetIndex) => {
    if (dragIndex.value === null || dragIndex.value === targetIndex) return;
    const imgs = [...productStore.product.images];
    const [moved] = imgs.splice(dragIndex.value, 1);
    imgs.splice(targetIndex, 0, moved);
    productStore.product.images = imgs;
    dragIndex.value = null;
    await reorderImages(productStore.product.id, imgs.map(i => i.id));
};

const categoriesStore = useCategories();
const { categories } = storeToRefs(categoriesStore);
const { fetchCategories } = categoriesStore;
const productId = computed(() => useRoute().params.productId);

let selectedImageFiles = ref([]);
let imageUrls = ref([]);

// Obrázky sa ukladajú vlastnými requestmi (poradie aj mazanie hneď), preto
// do porovnania ide len produkt a súbory, ktoré ešte čakajú na nahratie.
const formSnapshot = () => {
    const { images, ...product } = getProduct.value ?? {};
    return { product, newImages: selectedImageFiles.value.map((file) => file.name) };
};
const { setOriginalData, markAsSaved } = useUnsavedChanges(formSnapshot);

onMounted(async () => {
    if (productId.value) {
        await fetchProduct(productId.value);
        setOriginalData();
    }
    fetchCategories();
});

const handleImageSelected = (event) => {
    if (event.target.files.length === 0) {
        selectedImageFiles.value = [];
        imageUrls.value = [];
        return;
    }
    selectedImageFiles.value = Array.from(event.target.files);
};

const onSubmitForm = async () => {
    if (productId.value) {
        await updateProduct();

        if (selectedImageFiles.value.length) {
            const product = await storeImages(productId.value, selectedImageFiles.value);
            if (product) {
                productStore.product.images = product.images;
                selectedImageFiles.value = [];
                imageUrls.value = [];
            }
        }

        await fetchProduct(productId.value);
        setOriginalData();
        return;
    } else {
        const product = await storeProduct();
        if (product?.id && selectedImageFiles.value.length) {
            await storeImages(product.id, selectedImageFiles.value);
        }
    }

    markAsSaved();
    router.push({ name: "products.index" });
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
    const wasDeleted = await destroyImage(productStore.product.id, imageId);
    if (wasDeleted) {
        productStore.product.images = productStore.product.images.filter((image) => image.id !== imageId);
    }
};

onUnmounted(() => setProduct({}));

const pageTitle = computed(() => productId.value ? 'Upraviť produkt' : 'Nový produkt');
const buttonSubmit = { name: 'Uložiť', spinner: true };
const buttonBack = { name: 'Späť', spinner: true, link: '/products', icon: 'arrow-left' };
</script>

<template>
    <BaseLayout>
        <template #main>
            <div class="page-body col-span-12">
                <PageHeader :item="{ title: pageTitle, buttonLink: buttonBack }" />

                <form @submit.prevent="onSubmitForm" enctype="multipart/form-data" class="mb-6">

                    <div class="grid items-start gap-5 lg:grid-cols-2">

                    <!-- Ľavý stĺpec -->
                    <div class="space-y-5">

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
                                <HtmlEditor v-model="getProduct.description" placeholder="Popis produktu" />
                            </div>
                        </div>
                    </section>

                    <!-- Jednotky a DPH -->
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-3">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Jednotky a DPH</h2>
                        </div>
                        <div class="px-6 py-5">
                            <div class="grid gap-4 sm:grid-cols-2">
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

                    <!-- Dostupnosť -->
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-100 px-6 py-3">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Dostupnosť</h2>
                        </div>
                        <div class="px-6 py-5">
                            <label class="flex cursor-pointer items-start gap-3">
                                <input type="checkbox" v-model="getProduct.made_to_order"
                                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span>
                                    <span class="block text-sm font-medium text-gray-700">
                                        Tovar na zákazku — nesledovať sklad
                                    </span>
                                    <span class="block text-xs text-gray-500">
                                        Produkt sa dá objednať vždy. Na karte sa namiesto „skladom“
                                        alebo „vypredané“ zobrazí „na zákazku“ — bez ohľadu na množstvo pri variantoch.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </section>

                    </div><!-- /ľavý stĺpec -->

                    <!-- Pravý stĺpec -->
                    <div class="space-y-5">

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
                            <div v-if="productStore.product.images?.length">
                                <p class="mb-2 text-xs text-gray-400">Potiahnite obrázky pre zmenu poradia</p>
                                <div class="flex flex-wrap gap-3">
                                    <div
                                        v-for="(image, index) in productStore.product.images"
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

                    </div><!-- /pravý stĺpec -->

                    <!-- Varianty: cena a sklad žijú tu, nie na produkte -->
                    <div class="lg:col-span-2">
                        <VariantEditor :productId="productId" />
                    </div>

                    </div><!-- /grid -->

                    <!-- Akcie -->
                    <div class="mt-5 flex justify-between">
                        <buttonRouterLink :item="buttonBack" />
                        <buttonSubmitComponent :item="buttonSubmit" />
                    </div>

                </form>
            </div>
        </template>
    </BaseLayout>
</template>

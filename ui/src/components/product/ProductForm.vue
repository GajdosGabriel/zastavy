<script setup>
import BaseLayout from '../layout/BaseLayout.vue';
import { ref, watch, onMounted, onUnmounted, computed } from "@vue/runtime-core";
import useProducts from "../../store/StoreProducts";
import useImages from "../../store/StoreImages";
import UseCategories from "../../store/StoreCategories";
import { useRoute } from "vue-router";
import router from "../../router";
import { formatDecimal } from "../../models/functions";
import buttonRouterLink from '../layout/page/ButtonLink.vue';
import buttonSubmitComponent from '../layout/page/ButtonSubmit.vue';
import useUnsavedChanges from '../../models/useUnsavedChanges';

const { state, getProduct, updateProduct, storeProduct, fetchProduct, setProduct } = useProducts();
const { destroyImage, storeImages } = useImages();
const { categories, fetchCategories } = UseCategories();

const productId = computed(() => useRoute().params.productId);
const { setOriginalData, markAsSaved, isFormChanged } = useUnsavedChanges(() => getProduct.value);

onMounted(async () => {
    if (productId.value) {
        await fetchProduct(productId.value); // počkaj kým sa načíta produkt
        setOriginalData(getProduct.value); // nastavíme originál
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
        return
    };

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
    markAsSaved(); // označíme ako uložené
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

const categoryFilter = (categoryId) => {
    return state.product.categories.filter((value) => value.id == categoryId).length;
};

onUnmounted(() => {
    setProduct({});
});



const buttonSubmit = { name: 'Uložiť', spinner: true }
const buttonBack = { name: 'Späť', spinner: true, link: '/products', icon: 'arrow-left' }

</script>


<template>
    <BaseLayout>
        <template #main>
            <h1 class="page-heading">{{ productId ? 'Upraviť produkt' : 'Nový produkt' }}
                <buttonRouterLink :item="buttonBack" class="text-sm" />
            </h1>

            <div class="page-body col-span-12">

                <form @submit.prevent="onSubmitForm" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4"
                    enctype="multipart/form-data">

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                            Názov
                        </label>
                        <input
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            id="name" type="text" v-model="getProduct.name" placeholder="Názov produktu" />
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="description">
                            Popis
                        </label>
                        <textarea
                            class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            id="description" v-model="getProduct.description" placeholder="Popis produktu"
                            rows="5"></textarea>
                    </div>

                    <div class="flex mb-5 space-x-3">
                        <div class="">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="price">
                                Cena
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="price" type="number" v-model.number="getProduct.price" placeholder="Predajná cena"
                                step=".01" />
                        </div>
                        <div class="">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="discont">
                                Zľava v %
                                <span class="text-gray-400" v-if="getProduct.discount">cena
                                    {{
                                        formatDecimal(
                                            state.product.price -
                                            (state.product.price / 100) *
                                            state.product.discount
                                        )
                                    }}
                                    €</span>
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="discont" type="number" v-model.number="getProduct.discount"
                                placeholder="Zľava z ceny v %" />
                        </div>

                        <div class="">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="sale_price">
                                Cena po zľave
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="sale_price" type="number" v-model.number="getProduct.sale_price"
                                placeholder="Predajná cena" step=".01" />
                        </div>

                        <div class="">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="min_order">
                                Min. odber
                            </label>
                            <input
                                class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="min_order" type="number" v-model.number="getProduct.min_order"
                                placeholder="Minimálny odber" />
                        </div>

                        <div class="w-24">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="unit_value">
                                Jednotka
                            </label>
                            <select
                                class="shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="unit_value" v-model="getProduct.unit_value" required>
                                <option :value="'ks'">kusy</option>
                                <option :value="'l'">litre</option>
                                <option :value="'kg'">hmotnosť</option>
                            </select>
                        </div>

                        <div class="w-24">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="vat">
                                DPH
                            </label>
                            <select
                                class="shadow appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                id="vat" v-model="getProduct.vat" required>
                                <option :value="23">23%</option>
                                <option :value="10">10%</option>
                                <option :value="0">0%</option>
                            </select>
                        </div>
                    </div>

                    <!-- 2. Row -->
                    <div class="mb-4">
                        <h1 class="font-semibold">Kategória</h1>
                        <div class="flex">
                            <div v-for="category in categories" :key="category.id"
                                class="border-2 border-gray-300 rounded-md px-2 mr-3 bg-yellow-100">
                                <label :for="category.name">
                                    {{ category.name }}
                                    <input type="checkbox" :id="category.name" :checked="false"
                                        v-model="getProduct.categories" :value="category.id" />
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="text-right flex justify-between">
                        <div class="text-left">
                            <label for="myfile" class="block">Vložiť obrázok</label>
                            <input type="file" id="myfile" accept="image/*" @change="handleImageSelected" multiple />

                            <div v-if="imageUrls.length" class="flex flex-wrap">
                                <div v-for="imageUrl in imageUrls" :key="imageUrl"
                                    class="text-center border-2 border-blue-200 rounded-md mx-2 mt-4 bg-blue-50">
                                    <img :src="imageUrl" alt="" class="h-24 p-4" />
                                </div>
                            </div>

                            <div class="flex flex-wrap">
                                <div v-for="image in state.product.images" :key="image.id"
                                    class="text-center border-2 rounded-md mx-2 mt-4 hover:bg-gray-100 hover:border-gray-300">
                                    <img :src="image.path" alt="" class="h-24 p-4" />
                                    <div @click="onClickImageRemove(image.id)"
                                        class="cursor-pointer hover:bg-gray-300 px-2 rounded-md text-sm">
                                        Odstrániť
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between mt-5">
                        <buttonRouterLink :item="buttonBack" />
                        <buttonSubmitComponent :item="buttonSubmit" />
                    </div>
                </form>
            </div>
        </template>
    </BaseLayout>
</template>

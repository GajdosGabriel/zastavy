<script setup>
import useStocks from "../../store/StoreStocks";
import useProducts from "../../store/StoreProducts";
import { onMounted } from "@vue/runtime-core";
import router from "../../router";
import validationBar from "../bars/ValidationBar.vue";
import BaseLayout from "../layout/BaseLayout.vue";
import Plus from '../icons/plus.vue';



const { state, storeStock } = useStocks();
const { state: products, fetchProducts } = useProducts();

onMounted(() => {
    fetchProducts();
});

const saveStock = () => {
    storeStock();
    router.push({ name: "stocks.index" });
};

</script>


<template>
    <BaseLayout>
        <!-- Content for the default slot -->
        <template #main>
            <h1 class="page-heading">Príjem tovaru
                <button class="btn btn-primary flex">
                    <Plus />
                    <div class="ml-2 text-sm">Nový product</div>
                </button>
            </h1>
            <div class="page-body col-span-12">

                <form @submit.prevent="saveStock" class="bg-white shadow-md rounded md:px-8 pt-6 pb-8 mb-4">
                    <validationBar :validations="null" />

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="product">
                            Názov tovaru
                        </label>
                        <select
                            class="shadow w-full appearance-none border rounded py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                            id="product" v-model="state.create.product_id" required>
                            <option v-for="product in products.products" :key="product.id" :value="product.id">
                                {{ product.name }}
                            </option>
                        </select>
                    </div>

                    <div class="md:flex justify-between mb-5">
                        <div class="flex mb-5 md:space-x-3">
                            <div class="">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="price">
                                    Množstvo
                                </label>
                                <input
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    id="price" type="number" v-model="state.create.quantity"
                                    placeholder="Prijatý počet kusov" required />
                            </div>

                            <div class="">
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="price">
                                    Cena s DPH
                                </label>
                                <input
                                    class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                    id="price" type="number" v-model="state.create.price" step="0.01"
                                    placeholder="Predajná cena s DPH" />
                            </div>
                        </div>
                    </div>

                    <div class="text-right md:flex justify-between">
                        <router-link :to="{ name: 'stocks.index' }"
                            class="bg-gray-200 hover:bg-gray-300 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Späť
                        </router-link>
                        <div>
                            <button
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline"
                                type="submit">
                                Uložiť
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </template>
    </BaseLayout>
</template>

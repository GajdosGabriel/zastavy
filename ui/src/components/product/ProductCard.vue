<script setup>
import { ref } from "vue";
import useCheckouts from "../../store/Checkouts";
import kosikButton from "../icons/kosik.vue";


const props = defineProps(["product"])


const { submitCartToIndex } = useCheckouts();
const messages = ref([]);

const submitCart = async (form) => {
    submitCartToIndex(form);

    messages.value.push(form.input_order);
};


</script>

<template>
    <!-- Tag Discount -->

    <div class="pb-2 bg-white rounded-xl shadow-xl z-10 border-2 border-blue-900 mb-4">
        <!-- Product Title -->
        <div class="text-sm md:text-base font-bold p-4 bg-blue-900 text-gray-200 text-center mb-2 rounded-t-xl">
            <router-link :to="{
                name: 'products.show',
                params: {
                    productId: product.id,
                    productSlug: product.slug,
                },
                query: { pageTitle: product.name }
            }">
                {{ product.name }}
            </router-link>
        </div>
        <div class="relative">
            <router-link :to="{
                name: 'products.show',
                params: {
                    productId: product.id,
                    productSlug: product.slug,
                },
                query: { pageTitle: product.name }
            }">
                <img :src="product.thumb" class="mx-auto h-56 p-6" alt="" />
            </router-link>
        </div>

        <div class="px-2 py-1">
            <div class="px-2">
                <div class="font-semibold text-center text-gray-900 text-lg mb-4 bg-gray-200 rounded-b-2xl shadow">
                    <router-link :to="{
                        name: 'products.show',
                        params: {
                            productId: product.id,
                            productSlug: product.slug,
                        },
                        query: { pageTitle: product.name }
                    }">
                        {{ product.name }}
                    </router-link>
                </div>
            </div>

            <div class="font-medium text-gray-900">
                Cena: {{ product.price }},- € <span class="text-xs">s DPH</span>
            </div>

            <div v-if="product.discount" class="text-gray-900 text-lg">
                Zľava
                <span class="font-semibold">-{{ product.discount }} % </span><br />
                Cena po zľave:
                <span class="font-semibold">{{ product.sale_price }}</span>,- € <span class="text-xs"> s DPH</span>
            </div>

            <div v-if="product.attributes" class="mb-2 rounded-fulltext-gray-900 font-semibold">
                Rozmery:
                <span class="font-semibold">{{ product.attributes }}</span>
            </div>

            <p v-if="product.description" class="pb-1 md:pb-2 text-xs md:text-sm text-gray-500">
                {{ product.description.substring(0, 150) }}
                <router-link :to="{
                    name: 'products.show',
                    params: {
                        productId: product.id,
                        productSlug: product.slug,
                    },
                    query: { pageTitle: product.name }
                }">
                    <span class="hover:underline">viac popisu</span>
                </router-link>
            </p>

            <form @submit.prevent="submitCart(product)">
                <div class="flex justify-center">
                    <input type="number" v-model="product.input_order" class="rounded w-24" :min="product.min_order"
                        required />
                    <button
                        class="ml-4 border-2 border-gray-400 px-4 rounded-md hover:bg-blue-300 flex bg-blue-200 items-center">
                        <kosikButton />
                        Kúpiť
                    </button>
                </div>
            </form>
            <p class="pt-2 md:pt-2 text-xs md:text-sm text-gray-500 text-center">
                <span>
                    {{ product.min_order }} {{ product.unit_value }} =
                    {{ product.min_order * product.active_price }},- € s
                    DPH</span>
            </p>

            <router-link :to="{ name: 'cart.index' }">
                <transition-group enter-active-class="duration-300 ease-out"
                    enter-from-class="transform opacity-0 scale-75" enter-to-class=" opacity-100 scale-100"
                    leave-active-class="transform duration-200 ease-in" leave-from-class=" opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-75">
                    <p v-for="(message, index) in messages" :key="index"
                        class="bg-green-300 p-3 rounded-md border-2 border-green-500 shadow-md mb-4">
                        Do košíka ste vložili
                        {{ message + " " + product.unit_value }}.
                    </p>
                </transition-group>
            </router-link>
        </div>
    </div>
</template>

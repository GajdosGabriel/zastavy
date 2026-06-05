<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import BaseLayout from "../layout/BaseLayout.vue";
import useProducts from "../../store/StoreProducts";
import useImages from "../../store/StoreImages";
import useCheckouts from "../../store/StoreCheckouts";
import { useRoute } from "vue-router";
import kosik from "../checkout/kosikLink.vue";
import kosikButton from "../icons/kosik.vue";
import nazory from "../pages/nazoryZakaznikov.vue";
import { formatPriceWithoutVat } from '../../models/functions'


const { state, getProduct, fetchProduct } = useProducts();
const { getImages, setImages } = useImages();
const { state: cart, submitCartToIndex } = useCheckouts();
const zoomProductImage = ref(true);
const sumPerItem = ref(0);
const messages = ref([]);
const currentImage = ref(0);
const {
    params: { productId }
} = useRoute();

onMounted(() => {
    fetchProduct(productId);
});


const onClickZoomProductImage = () => {
    zoomProductImage.value = !zoomProductImage.value;
};

const submitCart = () => {
    submitCartToIndex(state.product);
    messages.value.push(state.product.input_order);
};

watch(getProduct, () => {
    sumPerItem.value =
        getProduct.min_order * getProduct.sale_price;
});

const onClickImage = (index) => {
    if (index) {
        currentImage.value = index;
    }

    currentImage.value = index;
};

watch(
    () => getProduct,
    (count, prevCount) => {
        document.title = getProduct.name;
    }
);

onUnmounted(() => {
    setImages([]);
})


</script>

<template>
    <BaseLayout>

        <template #main>
            <h1 class="page-heading">{{ getProduct.name }}</h1>
            <div class="page-body col-span-12">

                <div class="md:grid grid-cols-10 gap-10">

                    <div class="p-3 col-span-7 border-solid pl-2 border-slate-300  border-4 rounded-md gap-8"
                        :class="[zoomProductImage ? '' : 'grid-auto']">
                        <div>
                            <img :src="getImages[currentImage]?.path ?? getProduct.thumb"
                                v-if="getImages[currentImage]?.path" class="cursor-pointer"
                                :class="[zoomProductImage ? 'w-1/2' : 'w-full object-cover']" :alt="getProduct.name"
                                @click="onClickZoomProductImage" />
                            <div class="py-4 flex">
                                <div v-for="(image, index) in getImages" :key="image.id"
                                    class="m-1 cursor-pointer border-2 rounded-sm shadow-sm border-gray-300 hover:border-blue-400"
                                    @click="onClickImage(index)">
                                    <img :src="image.path" class="p-2 w-20" alt="" />
                                </div>
                            </div>
                        </div>

                        <div class="text-center">
                            <h3 class="font-semibold mb-4 border-b-2 border-gray-200">
                                Vložiť do košíka
                            </h3>

                            <div class="font-medium mb-2 text-left flex justify-between">
                                <div>Cena s DPH</div>
                                <div class="text-red-500 font-semibold">
                                    <span v-if="getProduct.sale_price != 0"
                                        class="line-through text-gray-500 text-sm mr-3">
                                        {{ getProduct.price }} </span>
                                    <span>{{ getProduct.active_price }} €</span>
                                </div>
                            </div>
                            <div class="font-medium mb-4 text-left text-sm flex justify-between text-gray-400">
                                <div>Cena bez DPH ({{ getProduct.vat }}%)</div>
                                <div class="font-semibold">
                                    {{ formatPriceWithoutVat(getProduct.active_price, getProduct.vat) }} €
                                </div>
                            </div>
                            <form @submit.prevent="submitCart">
                                <div class="flex justify-center">
                                    <input type="number" v-model="state.product.input_order" class="rounded w-24"
                                        :min="getProduct.min_order" required />
                                    <button
                                        class="ml-4 border-2 border-gray-400 px-4 rounded-md hover:bg-blue-300 flex bg-blue-200 items-center">
                                        <kosikButton />
                                        Kúpiť
                                    </button>
                                </div>
                            </form>
                            <p class="pt-2 md:pt-2 text-xs md:text-sm text-gray-500">
                                <span>{{ getProduct.min_order }} {{ getProduct.unit_value }} =
                                    {{
                                        getProduct.min_order *
                                        getProduct.active_price
                                    }},- € s DPH</span>
                            </p>
                            <router-link :to="{ name: 'public.cart.index' }">
                                <transition-group enter-active-class="duration-300 ease-out"
                                    enter-from-class="transform opacity-0 scale-75"
                                    enter-to-class=" opacity-100 scale-100"
                                    leave-active-class="transform duration-200 ease-in"
                                    leave-from-class=" opacity-100 scale-100" leave-to-class="opacity-0 scale-75">
                                    <p v-for="(message, index) in messages" :key="index"
                                        class="bg-green-300 p-3 rounded-md border-2 border-green-500 shadow-md mb-4">
                                        Do košíka ste vložili
                                        {{ message + " " + getProduct.unit_value }}.
                                    </p>
                                </transition-group>
                            </router-link>
                        </div>

                        <div class="col-span-2">
                            <span class="font-semibold">Popis tovaru</span>
                            {{ getProduct.name }}
                            <p class="text-xs md:text-sm text-gray-500">
                                {{ getProduct.description }}
                            </p>
                        </div>
                    </div>

                    <div class="p-3 col-span-3 border-solid pl-2 border-slate-300  border-4 rounded-md">
                        <kosik />
                        <nazory />
                    </div>
                </div>
            </div>
        </template>

    </BaseLayout>
</template>

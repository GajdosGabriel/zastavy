<script setup>
import { ref } from "vue";
import useCheckouts from "../../store/StoreCheckouts";
import router from "../../router";
import { formatDecimal, formatUnitName } from '../../models/functions';
import kosikButton from "../icons/kosik.vue";


const props = defineProps(['item']);

const { submitCartToIndex } = useCheckouts();
const messages = ref([]);

const submitCart = (form) => {
    submitCartToIndex(form);

    messages.value.push(form.input_order + " " + formatUnitName(form.input_order));

    props.item.input_order = Number(props.item.min_order) || 1;
};

const onClickProductCart = () => {
    router.push({
        name: 'public.products.show',
        params: {
            productId: props.item.id,
            productSlug: props.item.slug,
        }
    })
};

</script>

<template>
    <!-- Tag Discount -->

    <div class="pb-2 bg-white rounded-xl shadow-xl z-10 border-2 border-blue-900 mb-4">
        <!-- Product Title -->
        <div class="text-sm md:text-base font-bold p-4 bg-blue-900 text-gray-200 text-center mb-2 rounded-t-xl">
            <a @click="onClickProductCart" class="cursor-pointer">
                {{ item.name }}
            </a>
        </div>
        <div class="relative">
            <a @click="onClickProductCart" class="cursor-pointer">
                <img :src="item.images[0]?.path" class="mx-auto h-56 p-6" :alt="item.name" />
            </a>
        </div>

        <div class="px-2 py-1">
            <div class="px-2">
                <div class="font-semibold text-center text-gray-900 text-lg mb-4 bg-gray-200 rounded-b-2xl shadow">
                    <a @click="onClickProductCart" class="cursor-pointer">
                        {{ item.name }}
                    </a>
                </div>
            </div>

            <div class="font-medium text-gray-900">
                Cena: {{ item.price }},- € <span class="text-xs">s DPH</span>
            </div>

            <div v-if="item.discount" class="text-gray-900 text-lg">
                Zľava
                <span class="font-semibold">-{{ item.discount }} % </span><br />
                Cena po zľave:
                <span class="font-semibold">{{ item.sale_price }}</span>,- € <span class="text-xs"> s DPH</span>
            </div>

            <div v-if="item.attributes" class="mb-2 rounded-fulltext-gray-900 font-semibold">
                Rozmery:
                <span class="font-semibold">{{ item.attributes }}</span>
            </div>

            <p v-if="item.description" class="pb-1 md:pb-2 text-xs md:text-sm text-gray-500">
                {{ item.description.substring(0, 150) }}
                <a @click="onClickProductCart" class="cursor-pointer">
                    <span class="hover:underline">viac popisu</span>
                </a>
            </p>

            <form @submit.prevent="submitCart(item)">
                <div class="flex justify-center">
                    <input type="number" v-model.number="item.input_order" class="rounded w-24" :min="item.min_order"
                        name="ddd" required />
                    <button
                        class="ml-4 border-2 border-gray-400 px-4 rounded-md hover:bg-blue-300 flex bg-blue-200 items-center">
                        <kosikButton />
                        Kúpiť
                    </button>
                </div>
            </form>
            <p class="pt-2 md:pt-2 text-xs md:text-sm text-gray-500 text-center">
                <span>
                    {{ item.input_order }} {{ formatUnitName(item.input_order) }} =
                    {{ formatDecimal(item.input_order * item.active_price) }},- € s
                    DPH</span>
            </p>

            <router-link :to="{ name: 'public.cart.index' }">
                <transition-group enter-active-class="duration-300 ease-out"
                    enter-from-class="transform opacity-0 scale-75" enter-to-class=" opacity-100 scale-100"
                    leave-active-class="transform duration-200 ease-in" leave-from-class=" opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-75">
                    <p v-for="(message, index) in messages" :key="index"
                        class="bg-green-300 p-3 rounded-md border-2 border-green-500 shadow-md mb-4">
                        Do košíka ste vložili
                        {{ message }}.
                    </p>
                </transition-group>
            </router-link>
        </div>
    </div>
</template>

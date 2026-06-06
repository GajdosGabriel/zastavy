<script setup>
import { ref } from "vue";
import useCheckouts from "../../store/StoreCheckouts";
import router from "../../router";
import { formatDecimal, formatUnitName } from "../../models/functions";
import kosikButton from "../icons/kosik.vue";

const props = defineProps(["item"]);

const { submitCartToIndex } = useCheckouts();
const messages = ref([]);

const submitCart = (form) => {
    submitCartToIndex(form);
    messages.value.push(form.input_order + " " + formatUnitName(form.input_order));
    props.item.input_order = Number(props.item.min_order) || 1;
};

const onClickProductCart = () => {
    router.push({
        name: "public.products.show",
        params: {
            productId: props.item.id,
            productSlug: props.item.slug,
        },
    });
};
</script>

<template>
    <article class="flex h-full flex-col overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm transition hover:border-blue-300 hover:shadow-md">
        <div class="min-h-16 bg-blue-900 p-3 text-center text-sm font-bold text-gray-100 md:text-base">
            <a @click="onClickProductCart" class="cursor-pointer">
                {{ item.name }}
            </a>
        </div>

        <div class="flex h-56 items-center justify-center bg-slate-50 p-5">
            <a @click="onClickProductCart" class="cursor-pointer">
                <img :src="item.images[0]?.path" class="max-h-48 w-full object-contain" :alt="item.name" />
            </a>
        </div>

        <div class="flex flex-1 flex-col px-4 py-4">
            <h2 class="mb-3 text-center text-lg font-semibold text-slate-900">
                <a @click="onClickProductCart" class="cursor-pointer hover:text-blue-800">
                    {{ item.name }}
                </a>
            </h2>

            <div class="rounded-md bg-slate-100 px-3 py-2 text-center font-semibold text-slate-900">
                Cena: {{ item.price }},- € <span class="text-xs font-medium text-slate-500">s DPH</span>
            </div>

            <div v-if="item.discount" class="mt-3 rounded-md border border-red-100 bg-red-50 p-3 text-sm text-slate-900">
                Zľava <span class="font-semibold">-{{ item.discount }} %</span><br />
                Cena po zľave:
                <span class="font-semibold">{{ item.sale_price }}</span>,- € <span class="text-xs">s DPH</span>
            </div>

            <div v-if="item.attributes" class="mt-3 text-sm font-semibold text-slate-700">
                Rozmery: <span>{{ item.attributes }}</span>
            </div>

            <p v-if="item.description" class="mt-3 flex-1 text-xs leading-5 text-slate-500 md:text-sm">
                {{ item.description.substring(0, 150) }}
                <a @click="onClickProductCart" class="cursor-pointer">
                    <span class="font-semibold text-blue-800 hover:underline">viac popisu</span>
                </a>
            </p>

            <form class="mt-4" @submit.prevent="submitCart(item)">
                <div class="flex items-center justify-center gap-3">
                    <input
                        v-model.number="item.input_order"
                        type="number"
                        class="w-24 rounded border-slate-300 text-center"
                        :min="item.min_order"
                        required
                    />
                    <button class="inline-flex items-center rounded-md bg-blue-700 px-4 py-2 font-semibold text-white transition hover:bg-blue-800">
                        <kosikButton />
                        <span class="ml-1">Kúpiť</span>
                    </button>
                </div>
            </form>

            <p class="pt-3 text-center text-xs text-slate-500 md:text-sm">
                {{ item.input_order }} {{ formatUnitName(item.input_order) }} =
                {{ formatDecimal(item.input_order * item.active_price) }},- € s DPH
            </p>

            <router-link :to="{ name: 'public.cart.index' }">
                <transition-group
                    enter-active-class="duration-300 ease-out"
                    enter-from-class="transform opacity-0 scale-75"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transform duration-200 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-75"
                >
                    <p
                        v-for="(message, index) in messages"
                        :key="index"
                        class="mb-4 rounded-md border border-green-300 bg-green-50 p-3 text-sm font-semibold text-green-800 shadow-sm"
                    >
                        Do košíka ste vložili {{ message }}.
                    </p>
                </transition-group>
            </router-link>
        </div>
    </article>
</template>

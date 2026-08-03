<script setup>
import { computed, ref } from "vue";
import useCheckouts from "../../store/StoreCheckouts";
import router from "../../router";
import { formatDecimal, formatUnitName } from "../../models/functions";
import kosikButton from "../icons/kosik.vue";

const props = defineProps(["item"]);

const { addVariantToCart } = useCheckouts();
const messages = ref([]);

const variants = computed(() => (props.item.variants ?? []).filter((v) => v.published));
const hasChoice = computed(() => variants.value.length > 1);

// Pri jedinom variante sa dá kupovať priamo z karty, inak sa musí vybrať na detaile.
const singleVariant = computed(() =>
    hasChoice.value ? null : (props.item.default_variant ?? variants.value[0] ?? null)
);

const quantity = ref(Number(props.item.default_variant?.min_order ?? 1));

const priceFrom = computed(() => Number(props.item.price_from ?? 0));
const priceTo = computed(() => Number(props.item.price_to ?? 0));
const isPriceRange = computed(() => hasChoice.value && priceTo.value > priceFrom.value);

const submitCart = () => {
    if (!singleVariant.value) return;
    addVariantToCart(props.item, singleVariant.value, quantity.value);
    messages.value.push(quantity.value + " " + formatUnitName(quantity.value));
    quantity.value = Number(singleVariant.value.min_order) || 1;
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

        <div class="relative flex h-56 items-center justify-center bg-slate-50 p-5">
            <a @click="onClickProductCart" class="cursor-pointer">
                <img :src="item.images?.[0]?.path ?? item.thumb" class="max-h-48 w-full object-contain" :alt="item.name" />
            </a>
            <span v-if="!item.is_in_stock"
                class="absolute right-3 top-3 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">
                vypredané
            </span>
        </div>

        <div class="flex flex-1 flex-col px-4 py-4">
            <h2 class="mb-3 text-center text-lg font-semibold text-slate-900">
                <a @click="onClickProductCart" class="cursor-pointer hover:text-blue-800">
                    {{ item.name }}
                </a>
            </h2>

            <div class="rounded-md bg-slate-100 px-3 py-2 text-center font-semibold text-slate-900">
                <span v-if="isPriceRange">od {{ formatDecimal(priceFrom) }} €</span>
                <span v-else>Cena: {{ formatDecimal(priceFrom) }} €</span>
                <span class="text-xs font-medium text-slate-500"> s DPH</span>
            </div>

            <div v-if="hasChoice" class="mt-3 flex flex-wrap justify-center gap-1.5">
                <span v-for="variant in variants.slice(0, 4)" :key="variant.id"
                    class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-600">
                    {{ variant.name }}
                </span>
                <span v-if="variants.length > 4" class="px-1 text-xs text-slate-400">
                    +{{ variants.length - 4 }}
                </span>
            </div>

            <div v-else-if="singleVariant?.name" class="mt-3 text-center text-sm font-semibold text-slate-700">
                {{ singleVariant.name }}
            </div>

            <p v-if="item.description" class="mt-3 flex-1 text-xs leading-5 text-slate-500 md:text-sm">
                {{ item.description.substring(0, 150) }}
                <a @click="onClickProductCart" class="cursor-pointer">
                    <span class="font-semibold text-blue-800 hover:underline">viac popisu</span>
                </a>
            </p>

            <!-- Viac variantov = zákazník musí najprv vybrať, ktorý chce -->
            <button v-if="hasChoice" type="button" @click="onClickProductCart"
                class="mt-4 inline-flex w-full items-center justify-center rounded-md border-2 border-blue-700 px-4 py-2 font-semibold text-blue-700 transition hover:bg-blue-50">
                Vybrať variant
            </button>

            <form v-else-if="singleVariant" class="mt-4" @submit.prevent="submitCart">
                <div class="flex items-center justify-center gap-3">
                    <input
                        v-model.number="quantity"
                        type="number"
                        class="w-24 rounded border-slate-300 text-center"
                        :min="singleVariant.min_order"
                        required
                    />
                    <button class="inline-flex items-center rounded-md bg-blue-700 px-4 py-2 font-semibold text-white transition hover:bg-blue-800">
                        <kosikButton />
                        <span class="ml-1">Kúpiť</span>
                    </button>
                </div>
            </form>

            <p v-else class="mt-4 rounded-md bg-slate-50 px-3 py-2 text-center text-sm text-slate-500">
                Momentálne nie je v ponuke.
            </p>

            <p v-if="singleVariant" class="pt-3 text-center text-xs text-slate-500 md:text-sm">
                {{ quantity }} {{ formatUnitName(quantity) }} =
                {{ formatDecimal(quantity * Number(singleVariant.active_price ?? singleVariant.price)) }},- € s DPH
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

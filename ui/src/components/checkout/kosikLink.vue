<script setup>
import kosik from "../icons/kosik.vue";
import badge from "../plugins/badge.vue";
import useCheckouts from "../../store/StoreCheckouts";

const { getCarts } = useCheckouts();
</script>

<template>
    <div class="w-full rounded-md border border-blue-900 bg-blue-900 text-center text-gray-200 shadow-sm">
        <router-link :to="{ name: 'public.cart.index' }" class="flex items-center justify-center p-2">
            <kosik />
            <span class="text-white"> Váš košík </span>
            <badge :kosik="{ value: getCarts.length, title: 'Počet položiek', class: null }" class="ml-2" />
        </router-link>
    </div>
</template>

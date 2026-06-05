<script setup>

import kosik from "../icons/kosik.vue";
import badge from "../plugins/badge.vue"

import useCheckouts from "../../store/StoreCheckouts";

const { getCarts } = useCheckouts();

</script>

<template>
    <div class="w-full bg-blue-900 md:mb-6 text-center text-gray-200 rounded-md pt-1">
        <router-link :to="{ name: 'public.cart.index' }" class="flex justify-center p-2 content-center items-center">
            <kosik />
            <span class="text-white"> Váš košík </span>
            <badge :kosik="{ value: getCarts.length, title: 'Počet položiek', class: null }" class="ml-2" />
        </router-link>
    </div>
</template>
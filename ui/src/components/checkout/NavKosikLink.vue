<script setup>
import useCheckouts from '../../store/StoreCheckouts';

const { getCarts } = useCheckouts();
</script>

<template>
    <router-link :to="{ name: 'public.cart.index' }" class="nav_link relative flex items-center gap-1.5 px-2 py-1 rounded-md transition hover:bg-gray-200">
        <span class="relative">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span
                v-if="getCarts?.length > 0"
                class="absolute -top-2 -right-2 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white"
                title="Počet položiek"
            >
                {{ getCarts.length }}
            </span>
        </span>
        <span class="text-sm font-medium text-gray-700">Košík</span>
    </router-link>
</template>

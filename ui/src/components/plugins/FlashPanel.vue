<script setup>
import { storeToRefs } from 'pinia';
import useFlash from '../../store/StoreFlash';

const flash = useFlash();
const { message } = storeToRefs(flash);
</script>

<template>
    <div v-if="message" class="col-span-12 px-3 pt-3" role="status">
        <div class="relative rounded border border-green-400 bg-green-100 px-4 py-3 pr-12 text-green-800">
            <span class="block sm:inline">{{ message }}</span>
            <button type="button" @click="flash.clear()" aria-label="Zatvoriť"
                class="absolute bottom-0 right-0 top-0 px-4 text-xl leading-none text-green-700">&times;</button>
        </div>
    </div>
</template>

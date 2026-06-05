<script setup>
import { onMounted, ref } from "@vue/runtime-core";

const month = new Date().getMonth();
const message = ref("");
const background = ref("");

onMounted(getMonth);

function getMonth() {
    if (month == 0 || month == 1) {
        message.value = "Prebieha Novoročná akcia!";
        background.value = "bg-rose-700";
    }
    if (month == 2 || month == 3) {
        message.value = "Prebieha Jarná akcia!";
        background.value = "bg-emerald-600";
    }
    if (month == 4 || month == 5) {
        message.value = "Prebieha Letná akcia!";
        background.value = "bg-sky-700";
    }

    if (month == 9 || month == 10) {
        message.value = "Prebieha Jesenná akcia!";
        background.value = "bg-orange-700";
    }

    if (month == 11) {
        message.value = "Prebieha Vianočná akcia!";
        background.value = "bg-fuchsia-700";
    }
}

</script>

<template>
    <div v-if="message" class="text-center text-gray-100 p-1 text-lg" :class="background">
        <strong>
            <div>{{ message }}</div>
            <div>
                Najlacnejšie Zástavy a Vlajky na Slovensku
            </div>
        </strong>
    </div>
</template>

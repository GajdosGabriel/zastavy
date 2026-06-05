<script setup lang="ts">
import buttonLink from './ButtonLink.vue';

const props = defineProps(['item']);

</script>

<template>
    <div class="flex justify-between items-center">
        <h1 class="page-heading">{{ item.title }}</h1>
        <buttonLink :item="item.buttonLink" class="text-sm" />
    </div>
</template>
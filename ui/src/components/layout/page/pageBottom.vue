<script setup lang="ts">
import buttonLink from './ButtonLink.vue';
import buttonSubmit from './ButtonSubmit.vue';

const props = defineProps(['item']);

</script>

<template>
    <div class="flex justify-between mt-5">
        <buttonLink :item="item.buttonLink" />
        <buttonSubmit :item="item.buttonSubmit" />
    </div>
</template>
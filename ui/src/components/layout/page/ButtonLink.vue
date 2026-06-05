<script setup>
import plus from '../../icons/plus.vue';
import arrow from '../../icons/arrowLeft.vue';

const props = defineProps(['item']);

</script>

<template>
    <div>
        <router-link :to="props.item.link" v-if="(typeof props.item !== 'undefined' && Object.keys(props.item).length)"
            class="bg-gray-200 hover:bg-gray-300 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline flex items-center">
            <plus v-if="props.item.icon == 'plus'" class="mr-1" />
            <arrow v-if="props.item.icon == 'arrow-left'" class="mr-1" />
            {{ props.item.name }}
        </router-link>
    </div>
</template>